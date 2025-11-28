<?php
// Handle CORS
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

if ($method === 'POST' && str_contains($path, '/auth/login')) {
    login();
} elseif ($method === 'POST' && str_contains($path, '/auth/update-player-id')) {
    updatePlayerId();
} else {
    sendResponse(false, 'Invalid endpoint');
}

function login() {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        sendResponse(false, 'Email and password required');
    }
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("SELECT u.*, a.id as agent_id FROM users u LEFT JOIN agents a ON u.id = a.id WHERE u.email = ? AND u.active = 1");
        $stmt->execute([$input['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($input['password'], $user['password_hash'])) {
            sendResponse(false, 'Invalid credentials');
        }
        
        // Generate JWT token
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'exp' => time() + JWT_EXPIRY
        ];
        
        $token = generateJWT($payload);
        
        // Return user data without password
        unset($user['password_hash']);
        
        sendResponse(true, 'Login successful', [
            'token' => $token,
            'user' => $user
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Login failed: ' . $e->getMessage());
    }
}

function updatePlayerId() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['agent_id']) || !isset($input['player_id'])) {
        sendResponse(false, 'Agent ID and Player ID are required');
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE agents SET onesignal_player_id = ? WHERE id = ?");
        $result = $stmt->execute([$input['player_id'], $input['agent_id']]);
        
        if ($result) {
            sendResponse(true, 'Player ID updated successfully');
        } else {
            sendResponse(false, 'Failed to update player ID');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}
?>