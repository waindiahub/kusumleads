<?php
require_once 'config.php';
require_once 'jwt_helper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = validateJWT();
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Extract reminder ID from path
    if (preg_match('/\/reminders\/(\d+)\/update/', $path, $matches)) {
        $reminderId = $matches[1];
        
        $input = json_decode(file_get_contents('php://input'), true);
        $reminderTime = $input['reminder_time'] ?? '';
        $reminderNote = $input['reminder_note'] ?? '';
        
        if (empty($reminderTime)) {
            echo json_encode(['success' => false, 'message' => 'Reminder time is required']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Only update new reminders table
            $stmt = $db->prepare("
                UPDATE reminders 
                SET reminder_time = ?, reminder_note = ?, notification_sent = FALSE
                WHERE id = ? AND agent_id = ?
            ");
            
            $stmt->execute([$reminderTime, $reminderNote, $reminderId, $token['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Reminder updated']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
}
?>