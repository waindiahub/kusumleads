<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Simple insert test
        $stmt = $db->prepare("INSERT INTO leads (external_id, created_time, full_name, phone_number, raw_json) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->execute([
            $input['external_id'] ?? 'test_' . time(),
            $input['full_name'] ?? 'Test User',
            $input['phone_number'] ?? '1234567890',
            json_encode($input)
        ]);
        
        $leadId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Test lead created', 
            'lead_id' => $leadId
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
}
?>