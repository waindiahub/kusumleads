<?php
/**
 * Test OneSignal Notification Sending
 * Usage: test_onesignal_notification.php?user_id=1&message=Test
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/config.php';

$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$message = $_GET['message'] ?? $_POST['message'] ?? 'Test notification from CRM';

if (!$userId) {
    sendResponse(false, 'User ID is required');
}

try {
    $db = getDB();
    
    // Get user's OneSignal player ID
    $stmt = $db->prepare("
        SELECT name, onesignal_player_id 
        FROM users 
        WHERE id = ? AND onesignal_player_id IS NOT NULL
    ");
    
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'User not found or OneSignal not registered');
    }
    
    if (empty($user['onesignal_player_id'])) {
        sendResponse(false, 'User does not have OneSignal player ID registered');
    }
    
    // OneSignal API configuration
    $appId = 'ca751a15-6451-457b-aa3c-3b9a52eee8f6';
    $restApiKey = 'YzNlNzJhNzMtNzE0Zi00ZjVjLWI2YzMtNzE4ZGY4NzI4YzI4'; // Replace with your REST API key
    
    // Prepare notification data
    $notificationData = [
        'app_id' => $appId,
        'include_player_ids' => [$user['onesignal_player_id']],
        'headings' => ['en' => 'CRM Notification'],
        'contents' => ['en' => $message],
        'data' => [
            'type' => 'test',
            'user_id' => $userId,
            'timestamp' => time()
        ]
    ];
    
    // Send notification via OneSignal API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $restApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['id'])) {
        sendResponse(true, 'Test notification sent successfully', [
            'user_name' => $user['name'],
            'player_id' => $user['onesignal_player_id'],
            'notification_id' => $responseData['id'],
            'recipients' => $responseData['recipients'] ?? 0,
            'message' => $message
        ]);
    } else {
        sendResponse(false, 'Failed to send notification', [
            'http_code' => $httpCode,
            'response' => $responseData,
            'player_id' => $user['onesignal_player_id']
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>