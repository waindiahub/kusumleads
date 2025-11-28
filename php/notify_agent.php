<?php
/**
 * Agent Notification Endpoint
 * Receives webhook from Google Sheets and sends OneSignal notification to specific agent
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Helper function for consistent API response
function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration and OneSignal sender file
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/send_onesignal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method allowed');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(false, 'Invalid JSON payload');
}

// Required fields
$required = ['agent_id', 'lead_id', 'lead_name', 'lead_phone'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        sendResponse(false, "Missing required field: $field");
    }
}

try {
    $agentId   = $input['agent_id'];
    $leadId    = $input['lead_id'];
    $leadName  = $input['lead_name'];
    $leadPhone = $input['lead_phone'];
    
    $title = "New Lead Assigned";
    $message = "New lead assigned: {$leadName} ({$leadPhone})";
    
    $leadData = [
        'lead_id'    => $leadId,
        'lead_name'  => $leadName,
        'lead_phone' => $leadPhone,
        'type'       => 'new_lead'
    ];
    
    // Send notification
    $result = sendOneSignalNotification($agentId, $title, $message, $leadData);
    
    if ($result['success']) {
        sendResponse(true, 'Notification sent successfully', [
            'notification_id' => $result['response']['id'] ?? null,
            'recipients'      => $result['response']['recipients'] ?? 0
        ]);
    } else {
        sendResponse(false, 'Failed to send notification', [
            'error'     => $result['response']['errors'] ?? 'Unknown error',
            'http_code' => $result['http_code']
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(false, 'Notification failed: ' . $e->getMessage());
}

?>
