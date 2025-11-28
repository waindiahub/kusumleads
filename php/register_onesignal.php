<?php
/**
 * Register OneSignal Player ID directly into users.onesignal_player_id
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'includes/config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['user_id']) || empty($input['player_id'])) {
    echo json_encode(['success' => false, 'message' => 'user_id and player_id required']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("
        UPDATE users 
        SET onesignal_player_id = ?, updated_at = NOW() 
        WHERE id = ?
    ");

    $stmt->execute([
        $input['player_id'],
        $input['user_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Player ID registered',
        'user_id' => $input['user_id'],
        'player_id' => $input['player_id']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
