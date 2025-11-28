<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/config.php';
require_once 'send_onesignal.php';   // MUST contain sendOneSignalNotificationToAgent()

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method allowed');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['lead_id'])) {
    sendResponse(false, 'Lead ID is required');
}

try {
    $db = getDB();

    // Fetch lead + assigned agent + their OneSignal player ID
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.full_name,
            l.phone_number,
            la.agent_id,
            u.name AS agent_name,
            u.onesignal_player_id
        FROM leads l
        JOIN lead_assignments la ON l.id = la.lead_id
        JOIN users u ON la.agent_id = u.id
        WHERE l.id = ?
    ");

    $stmt->execute([$input['lead_id']]);
    $lead = $stmt->fetch();

    if (!$lead) {
        sendResponse(false, 'Lead not found OR not assigned to any agent.');
    }

    if (empty($lead['onesignal_player_id'])) {
        sendResponse(false, 'Agent does NOT have a OneSignal Player ID saved.');
    }

    // Default title + message OR override from input
    $title = $input['title'] ?? 'New Lead Assigned';
    $message = $input['message'] ?? "New lead: {$lead['full_name']} ({$lead['phone_number']})";

    // Data passed to notification
    $leadData = [
        'lead_id' => $lead['id'],
        'lead_name' => $lead['full_name'],
        'lead_phone' => $lead['phone_number'],
        'type' => 'lead_notification'
    ];

    // SEND PUSH NOTIFICATION (correct function)
    $result = sendOneSignalNotificationToAgent(
        $lead['agent_id'],
        $title,
        $message,
        $leadData
    );

    // Response handling
    if ($result['success']) {
        sendResponse(true, 'Notification sent successfully', [
            'player_id' => $result['player_id'],
            'response' => $result['response']
        ]);
    } else {
        sendResponse(false, 'Failed to send notification', [
            'player_id' => $result['player_id'] ?? null,
            'response'  => $result['response'] ?? 'Unknown error'
        ]);
    }

} catch (Exception $e) {
    sendResponse(false, 'Server Error: ' . $e->getMessage());
}

?>
