<?php
// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Configuration - Use remote SQL
require_once 'includes/config.php';

// getDB() function is now imported from config.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug: Log raw input
        error_log("Raw input: " . file_get_contents('php://input'));
        error_log("Parsed input: " . var_export($input, true));
        
        $deviceId = $input['device_id'] ?? '';
        $userId = $input['user_id'] ?? '';
        
        if (!$userId) {
            throw new Exception('User ID is required. Received: ' . var_export($input, true));
        }
        
        // Generate device ID if not provided
        if (!$deviceId && $userId) {
            $deviceId = 'web_' . $userId . '_' . time();
        }
        
        $db = getDB();
        
        // Debug: Log received data
        error_log("Received userId: " . var_export($userId, true));
        error_log("Received deviceId: " . var_export($deviceId, true));
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        error_log("User found: " . var_export($user, true));
        
        if (!$user) {
            throw new Exception("User not found for ID: {$userId}");
        }
        
        // Update or insert agent record and mark as online
        $stmt = $db->prepare("INSERT INTO agents (id, last_login) VALUES (?, NOW()) 
                             ON DUPLICATE KEY UPDATE last_login = NOW()");
        $stmt->execute([$userId]);
        
        // Log the login
        error_log("Agent {$userId} logged in");
        
        // Debug: Check if record was created/updated
        $stmt = $db->prepare("SELECT last_login FROM agents WHERE id = ?");
        $stmt->execute([$userId]);
        $agent = $stmt->fetch();
        
        error_log("Agent record after update: " . json_encode($agent));
        
        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => $agent
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
}
?>