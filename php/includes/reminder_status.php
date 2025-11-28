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
    if (preg_match('/\/reminders\/(\d+)\/status/', $path, $matches)) {
        $reminderId = $matches[1];
        
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? '';
        
        if (empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Status is required']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Update both tables for compatibility
            $stmt1 = $db->prepare("
                UPDATE followup_reminders 
                SET status = ? 
                WHERE id = ? AND agent_id = ?
            ");
            
            $stmt2 = $db->prepare("
                UPDATE reminders 
                SET is_completed = CASE WHEN ? = 'completed' THEN TRUE ELSE FALSE END
                WHERE id = ? AND agent_id = ?
            ");
            
            $stmt1->execute([$status, $reminderId, $token['user_id']]);
            $stmt2->execute([$status, $reminderId, $token['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
}
?>