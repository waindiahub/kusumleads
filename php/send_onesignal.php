<?php 
/**
 * OneSignal Notification Service
 * Sends push notifications using include_player_ids
 * Uses onesignal_player_id stored in the users table
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php'; // Must contain ONESIGNAL_APP_ID & ONESIGNAL_REST_API_KEY

/**
 * Send a notification to an agent using stored OneSignal Player ID
 */
function sendOneSignalNotificationToAgent($agentId, $title, $message, $leadData = [])
{
    try {
        $db = getDB();

        // Fetch OneSignal Player ID from database
        $stmt = $db->prepare("SELECT onesignal_player_id FROM users WHERE id = ?");
        $stmt->execute([$agentId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['onesignal_player_id'])) {
            return [
                'success' => false,
                'error' => 'Agent does not have a OneSignal Player ID registered.',
                'agent_id' => $agentId,
                'player_id' => null
            ];
        }

        $playerId = $row['onesignal_player_id'];

        // Send notification
        return sendOneSignalRaw($playerId, $title, $message, $leadData);

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Database Error: ' . $e->getMessage(),
            'agent_id' => $agentId
        ];
    }
}


/**
 * Low-Level Sender — Sends Push Notification to ONE PLAYER ID
 */
function sendOneSignalRaw($playerId, $title, $message, $data = [])
{
    if (!defined('ONESIGNAL_APP_ID') || !defined('ONESIGNAL_REST_API_KEY')) {
        return [
            'success' => false,
            'error' => 'OneSignal keys are missing in config.php'
        ];
    }

    $payload = [
        'app_id' => ONESIGNAL_APP_ID,
        'include_player_ids' => [$playerId], // DEVICE TARGETING
        'headings' => ['en' => $title],
        'contents' => ['en' => $message],
        'data' => is_array($data) ? $data : []
    ];

    $jsonPayload = json_encode($payload);

    $ch = curl_init("https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . ONESIGNAL_REST_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    return [
        'success'      => ($httpCode === 200 && empty($result['errors'])),
        'http_code'    => $httpCode,
        'response'     => $result,
        'player_id'    => $playerId
    ];
}
?>
