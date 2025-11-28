<?php
// Main entry point for CRM API
require_once 'includes/config.php';
setApiHeaders();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route to appropriate handler
if (strpos($path, '/api/auth/') !== false) {
    require_once 'includes/auth.php';
} elseif (strpos($path, '/api/leads') !== false) {
    require_once 'includes/leads.php';
} elseif (strpos($path, '/api/agents') !== false) {
    require_once 'includes/agents.php';
} elseif (strpos($path, '/test_leaderboard') !== false) {
    require_once 'test_leaderboard.php';
} elseif (strpos($path, '/api/leaderboard') !== false || strpos($path, '/leaderboard') !== false) {
    require_once 'includes/leaderboard.php';
} elseif (strpos($path, '/reminders/') !== false && strpos($path, '/update') !== false) {
    require_once 'includes/reminder_update.php';
} elseif (strpos($path, '/reminders/') !== false && strpos($path, '/status') !== false) {
    require_once 'includes/reminder_status.php';
} elseif (strpos($path, '/reminders') !== false) {
    require_once 'includes/reminders.php';
} elseif (strpos($path, 'whatsapp_templates') !== false) {
    require_once 'includes/whatsapp_templates.php';
} elseif (strpos($path, '/api/reports') !== false || strpos($path, '/api/expenses') !== false || strpos($path, '/api/ad_budgets') !== false) {
    require_once 'includes/reports.php';
} elseif (strpos($path, '/register_onesignal') !== false) {
    require_once 'register_onesignal.php';
} elseif (strpos($path, '/check_onesignal') !== false) {
    require_once 'check_onesignal.php';
} elseif (strpos($path, '/test_onesignal_notification') !== false) {
    require_once 'test_onesignal_notification.php';
} elseif (strpos($path, '/notify_agent') !== false) {
    require_once 'notify_agent.php';
} elseif (strpos($path, '/process_notifications') !== false) {
    require_once 'process_notifications.php';
} elseif (strpos($path, '/register_pusher') !== false) {
    require_once 'register_pusher.php';
} elseif (strpos($path, '/test_connection') !== false) {
    require_once 'test_connection.php';
} elseif ($path === '/admin' || $path === '/admin/') {
    // Redirect to admin login
    header('Location: /admin/login.php');
    exit();
} else {
    sendResponse(false, 'Invalid endpoint');
}
?>