<?php
// Check reminder notification logs
echo "<h2>Reminder Notification Logs</h2>";

// Check PHP error log
$errorLogPath = ini_get('error_log');
if (!$errorLogPath) {
    $errorLogPath = '/tmp/php_errors.log'; // Default path
}

echo "<h3>Error Log Path: $errorLogPath</h3>";

if (file_exists($errorLogPath)) {
    $logs = file_get_contents($errorLogPath);
    $reminderLogs = array_filter(explode("\n", $logs), function($line) {
        return strpos($line, '[REMINDER') !== false;
    });
    
    echo "<h3>Recent Reminder Logs:</h3>";
    echo "<pre style='background:#f0f0f0; padding:10px; max-height:400px; overflow:auto;'>";
    
    if (empty($reminderLogs)) {
        echo "No reminder logs found. Logs should contain [REMINDER CRON], [REMINDER ERROR], or [REMINDER SUCCESS]";
    } else {
        foreach (array_slice($reminderLogs, -20) as $log) {
            echo htmlspecialchars($log) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p style='color:red'>Error log file not found at: $errorLogPath</p>";
}

// Check cron job status
echo "<h3>Cron Job Test</h3>";
echo "<p>Manual cron execution: <a href='process_notifications.php' target='_blank'>Run Now</a></p>";
echo "<p>Reminder test: <a href='test_reminder_notification.php' target='_blank'>Test Reminders</a></p>";

// Show current time
echo "<h3>Server Time</h3>";
echo "<p>Current server time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Timezone: " . date_default_timezone_get() . "</p>";
?>