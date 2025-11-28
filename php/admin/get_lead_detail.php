<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$leadId = $_GET['id'] ?? 0;
$db = getDB();

// Get lead details
$stmt = $db->prepare("SELECT l.*, la.status as assignment_status, la.assigned_at,
    u.name as agent_name, ar.response_status, ar.price_offered, ar.response_text, ar.created_at as response_at
    FROM leads l
    LEFT JOIN lead_assignments la ON l.id = la.lead_id
    LEFT JOIN users u ON la.agent_id = u.id
    LEFT JOIN agent_responses ar ON l.id = ar.lead_id
    WHERE l.id = ?");
$stmt->execute([$leadId]);
$lead = $stmt->fetch();

if (!$lead) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Lead not found']);
    exit();
}

// Build timeline
$timeline = [];

// Lead creation
$timeline[] = [
    'action' => 'Lead Created',
    'description' => 'Lead was created in the system',
    'timestamp' => date('M j, Y H:i:s', strtotime($lead['created_at']))
];

// Assignment
if ($lead['assigned_at']) {
    $timeline[] = [
        'action' => 'Lead Assigned',
        'description' => 'Assigned to ' . ($lead['agent_name'] ?? 'Unknown Agent'),
        'timestamp' => date('M j, Y H:i:s', strtotime($lead['assigned_at']))
    ];
}

// Response
if ($lead['response_at']) {
    $timeline[] = [
        'action' => 'Response Submitted',
        'description' => 'Status: ' . ucfirst($lead['response_status']) . 
                        ($lead['price_offered'] ? ' | Price: ₹' . number_format($lead['price_offered']) : ''),
        'timestamp' => date('M j, Y H:i:s', strtotime($lead['response_at']))
    ];
}

// Get reminders
$stmt = $db->prepare("SELECT * FROM followup_reminders WHERE lead_id = ? ORDER BY reminder_time");
$stmt->execute([$leadId]);
$reminders = $stmt->fetchAll();

foreach ($reminders as $reminder) {
    $timeline[] = [
        'action' => 'Reminder ' . ucfirst($reminder['status']),
        'description' => $reminder['reminder_note'] ?? 'Follow-up reminder',
        'timestamp' => date('M j, Y H:i:s', strtotime($reminder['reminder_time']))
    ];
}

// Sort timeline by timestamp
usort($timeline, function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $lead,
    'timeline' => $timeline
]);

