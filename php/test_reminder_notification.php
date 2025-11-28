<?php
// Test reminder notification system
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Kolkata');

require_once 'includes/config.php';
require_once 'send_onesignal.php';

echo "<h2>Reminder Notification Test</h2>";

try {
    $db = getDB();
    
    // Check reminders table exists
    $stmt = $db->query("SHOW TABLES LIKE 'reminders'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color:red'>ERROR: reminders table does not exist!</p>";
        echo "<p>Run this SQL to create it:</p>";
        echo "<pre>CREATE TABLE reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    agent_id INT NOT NULL,
    reminder_time DATETIME NOT NULL,
    reminder_note TEXT,
    is_completed BOOLEAN DEFAULT FALSE,
    notification_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</pre>";
        exit;
    }
    
    echo "<p style='color:green'>✓ Reminders table exists</p>";
    
    // Check for due reminders
    $stmt = $db->prepare("
        SELECT r.id, r.agent_id, r.reminder_note, r.reminder_time, 
               l.full_name, l.phone_number, u.onesignal_player_id as player_id, u.name as agent_name
        FROM reminders r
        JOIN leads l ON r.lead_id = l.id
        JOIN users u ON r.agent_id = u.id
        WHERE r.reminder_time <= NOW()
        AND r.is_completed = FALSE
        AND r.notification_sent = FALSE
        LIMIT 5
    ");
    
    $stmt->execute();
    $reminders = $stmt->fetchAll();
    
    echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>Found " . count($reminders) . " due reminders</p>";
    
    if (count($reminders) == 0) {
        echo "<p>No due reminders found. Creating a test reminder...</p>";
        
        // Create test reminder
        $testStmt = $db->prepare("
            INSERT INTO reminders (lead_id, agent_id, reminder_time, reminder_note) 
            SELECT l.id, l.agent_id, NOW() - INTERVAL 1 MINUTE, 'Test reminder'
            FROM leads l 
            LIMIT 1
        ");
        $testStmt->execute();
        
        if ($testStmt->rowCount() > 0) {
            echo "<p style='color:green'>✓ Test reminder created</p>";
            // Re-run query
            $stmt->execute();
            $reminders = $stmt->fetchAll();
        }
    }
    
    foreach ($reminders as $reminder) {
        echo "<hr>";
        echo "<h3>Reminder ID: {$reminder['id']}</h3>";
        echo "<p>Agent: {$reminder['agent_name']} (ID: {$reminder['agent_id']})</p>";
        echo "<p>Lead: {$reminder['full_name']} ({$reminder['phone_number']})</p>";
        echo "<p>Due: {$reminder['reminder_time']}</p>";
        echo "<p>OneSignal Player ID: " . ($reminder['player_id'] ?: 'NOT SET') . "</p>";
        
        if (empty($reminder['player_id'])) {
            echo "<p style='color:red'>ERROR: Agent has no OneSignal Player ID!</p>";
            continue;
        }
        
        // Test notification
        $title = "Follow-up Reminder";
        $message = "Reminder: Follow up with {$reminder['full_name']} ({$reminder['phone_number']})";
        if ($reminder['reminder_note']) {
            $message .= " - {$reminder['reminder_note']}";
        }
        
        echo "<p>Sending notification...</p>";
        echo "<p>Title: $title</p>";
        echo "<p>Message: $message</p>";
        
        $result = sendOneSignalNotificationToAgent(
            $reminder['agent_id'],
            $title,
            $message,
            ['type' => 'reminder']
        );
        
        if ($result['success']) {
            echo "<p style='color:green'>✓ Notification sent successfully!</p>";
            
            // Mark as sent
            $updateStmt = $db->prepare("UPDATE reminders SET notification_sent = TRUE WHERE id = ?");
            $updateStmt->execute([$reminder['id']]);
            echo "<p>✓ Marked as sent in database</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to send notification</p>";
            echo "<p>Error: " . json_encode($result) . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
}
?>