<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'jwt_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && strpos($path, '/reminders') !== false) {
    getReminders();
} elseif ($method === 'POST' && preg_match('/\/reminders\/(\d+)\/complete/', $path, $matches)) {
    completeReminder($matches[1]);
} elseif ($method === 'POST' && strpos($path, '/reminders') !== false) {
    createReminder();
} else {
    sendResponse(false, 'Invalid endpoint');
}

function getReminders() {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    
    $agentId = $token['role'] === 'agent' ? $token['user_id'] : ($_GET['agent_id'] ?? null);
    
    $db = getDB();
    
    try {
        // Only get from new reminders table
        if ($agentId) {
            $stmt = $db->prepare("
                SELECT r.*, l.full_name as lead_name, l.phone_number as lead_phone
                FROM reminders r
                JOIN leads l ON r.lead_id = l.id
                WHERE r.agent_id = ? AND r.is_completed = FALSE
                ORDER BY r.reminder_time ASC
                LIMIT 10
            ");
            $stmt->execute([$agentId]);
        } else {
            $stmt = $db->prepare("
                SELECT r.*, l.full_name as lead_name, l.phone_number as lead_phone
                FROM reminders r
                JOIN leads l ON r.lead_id = l.id
                WHERE r.is_completed = FALSE
                ORDER BY r.reminder_time ASC
                LIMIT 10
            ");
            $stmt->execute();
        }
        
        $reminders = $stmt->fetchAll();
        
        sendResponse(true, 'Reminders retrieved', $reminders);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to get reminders: ' . $e->getMessage());
    }
}

function completeReminder($reminderId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            UPDATE followup_reminders 
            SET status = 'completed', completed_at = NOW() 
            WHERE id = ? AND agent_id = ?
        ");
        
        $stmt->execute([$reminderId, $token['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(true, 'Reminder completed');
        } else {
            sendResponse(false, 'Reminder not found or access denied');
        }
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to complete reminder: ' . $e->getMessage());
    }
}

function createReminder() {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input['lead_id'] || !$input['reminder_time']) {
        sendResponse(false, "Lead ID and reminder time are required");
    }
    
    $db = getDB();
    
    try {
        // Only insert into new reminders table
        $stmt = $db->prepare("
            INSERT INTO reminders (lead_id, agent_id, reminder_time, reminder_note)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['lead_id'],
            $token['user_id'],
            $input['reminder_time'],
            $input['reminder_note'] ?? null
        ]);
        
        sendResponse(true, 'Reminder created', ['id' => $db->lastInsertId()]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to create reminder: ' . $e->getMessage());
    }
}
?>