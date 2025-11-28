<?php
/**
 * Process Notification Queue (CRON JOB)
 * Sends pending notifications using OneSignal Player ID
 */

// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

require_once 'includes/config.php';
require_once 'send_onesignal.php';

// Set custom error log using absolute path (better for cron jobs)
$logDir = getLogDirectory();
$errorLogFile = $logDir . '/notification_cron_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $errorLogFile);

try {
    $db = getDB();
    
    // Fetch pending notifications (batch of 10)
    $stmt = $db->prepare("
        SELECT nq.id, nq.agent_id, nq.lead_id, nq.lead_name, nq.lead_phone, nq.notification_type, u.email AS agent_email
        FROM notification_queue nq
        JOIN users u ON nq.agent_id = u.id
        WHERE nq.status = 'pending'
        ORDER BY nq.created_at ASC
        LIMIT 10
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    if (empty($notifications)) {
        echo "No pending notifications.\n";
        exit;
    }

    foreach ($notifications as $notif) {
        try {
            // Build title & message
            $title = "New Lead Assigned";
            $message = "You have a new lead: {$notif['lead_name']} ({$notif['lead_phone']})";

            $leadData = [
                'lead_id'    => $notif['lead_id'],
                'lead_name'  => $notif['lead_name'],
                'lead_phone' => $notif['lead_phone'],
                'type'       => $notif['notification_type']
            ];

            // SEND NOTIFICATION USING CORRECT FUNCTION
            $result = sendOneSignalNotificationToAgent(
                $notif['agent_id'],
                $title,
                $message,
                $leadData
            );
            $emailEnabled = getSetting('email_notifications') === '1';
            if ($emailEnabled && !empty($notif['agent_email'])) {
                @mail($notif['agent_email'], $title, $message);
            }

            // Update queue status
            $status = $result['success'] ? 'sent' : 'failed';

            $update = $db->prepare("
                UPDATE notification_queue
                SET status = ?, sent_at = NOW()
                WHERE id = ?
            ");
            $update->execute([
                $status,
                $notif['id']
            ]);

            echo "Notification {$notif['id']} → {$status}\n";
        } catch (Exception $e) {
            $logFile = getLogDirectory() . '/notification_cron_errors.log';
            error_log("[NOTIFICATION ERROR] Failed to process notification {$notif['id']}: " . $e->getMessage(), 3, $logFile);
            echo "Error processing notification {$notif['id']}: " . $e->getMessage() . "\n";
            
            // Mark as failed
            $update = $db->prepare("UPDATE notification_queue SET status = 'failed', sent_at = NOW() WHERE id = ?");
            $update->execute([$notif['id']]);
        }
    }
    
    // Process reminder notifications
    echo "Processing reminder notifications...\n";
    processReminderNotifications($db);

} catch (Exception $e) {
    echo "CRON Error: " . $e->getMessage() . "\n";
}

function processReminderNotifications($db) {
    try {
        echo "Checking for due reminders...\n";
        
        // Get due reminders that haven't been notified (only from reminders table)
        $stmt = $db->prepare("
            SELECT r.id, r.agent_id, r.reminder_note, r.reminder_time, l.full_name, l.phone_number, u.onesignal_player_id as player_id
            FROM reminders r
            JOIN leads l ON r.lead_id = l.id
            JOIN users u ON r.agent_id = u.id
            WHERE r.reminder_time <= NOW()
            AND r.is_completed = FALSE
            AND r.notification_sent = FALSE
        ");
        
        $stmt->execute();
        $reminders = $stmt->fetchAll();
        
        echo "Current time: " . date('Y-m-d H:i:s') . "\n";
        echo "Found " . count($reminders) . " due reminders\n";
        
        $logFile = getLogDirectory() . '/notification_cron_errors.log';
        error_log("[REMINDER CRON] Found " . count($reminders) . " due reminders", 3, $logFile);
        
        foreach ($reminders as $reminder) {
            echo "Processing reminder {$reminder['id']} for agent {$reminder['agent_id']}\n";
            echo "Reminder time: {$reminder['reminder_time']}\n";
            
            $title = "Follow-up Reminder";
            $message = "Reminder: Follow up with {$reminder['full_name']} ({$reminder['phone_number']})";
            if ($reminder['reminder_note']) {
                $message .= " - {$reminder['reminder_note']}";
            }
            
            // Check if agent has player_id
            if (empty($reminder['player_id'])) {
                echo "ERROR: Agent ID {$reminder['agent_id']} has no player_id\n";
                $logFile = getLogDirectory() . '/notification_cron_errors.log';
                error_log("[REMINDER ERROR] Agent ID {$reminder['agent_id']} has no player_id for reminder {$reminder['id']}", 3, $logFile);
                continue;
            }
            
            echo "Sending notification to player_id: {$reminder['player_id']}\n";
            
            $reminderData = [
                'type' => 'reminder',
                'lead_name' => $reminder['full_name'],
                'lead_phone' => $reminder['phone_number']
            ];
            
            $logFile = getLogDirectory() . '/notification_cron_errors.log';
            error_log("[REMINDER CRON] Sending notification to agent {$reminder['agent_id']} for reminder {$reminder['id']}", 3, $logFile);
            
            $result = sendOneSignalNotificationToAgent(
                $reminder['agent_id'],
                $title,
                $message,
                $reminderData
            );
            
            if ($result['success']) {
                // Mark notification as sent
                $updateStmt = $db->prepare("UPDATE reminders SET notification_sent = TRUE WHERE id = ?");
                $updateStmt->execute([$reminder['id']]);
                error_log("[REMINDER SUCCESS] Notification sent for reminder {$reminder['id']}", 3, $logFile);
                echo "Reminder notification sent for reminder {$reminder['id']}\n";
            } else {
                error_log("[REMINDER ERROR] Failed to send notification for reminder {$reminder['id']}: " . json_encode($result), 3, $logFile);
                echo "Failed to send reminder {$reminder['id']}\n";
            }
        }
        
    } catch (Exception $e) {
        $logFile = getLogDirectory() . '/notification_cron_errors.log';
        error_log("[REMINDER CRON ERROR] " . $e->getMessage(), 3, $logFile);
        echo "Reminder processing error: " . $e->getMessage() . "\n";
    }
}
?>
