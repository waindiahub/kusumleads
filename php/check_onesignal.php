<?php
/**
 * Check OneSignal Player ID Registration Status
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method allowed');
}

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    sendResponse(false, 'User ID is required');
}

try {
    $db = getDB();
    
    // Check if user has OneSignal player ID registered
    $stmt = $db->prepare("
        SELECT onesignal_player_id, updated_at 
        FROM users 
        WHERE id = ?
    ");
    
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'User not found');
    }
    
    $registered = !empty($user['onesignal_player_id']);
    
    sendResponse(true, 'Status checked successfully', [
        'user_id' => $userId,
        'registered' => $registered,
        'player_id' => $user['onesignal_player_id'],
        'last_updated' => $user['updated_at']
    ]);
    
} catch (Exception $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
?>