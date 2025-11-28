<?php
echo "<h2>Connection Limit Status</h2>";

// Check current time and suggest when to try again
$currentHour = date('H');
$nextHour = ($currentHour + 1) % 24;

echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Connection limit resets at: " . date('Y-m-d') . " " . sprintf('%02d:00:00', $nextHour) . "</p>";

$minutesUntilReset = (60 - date('i'));
echo "<p style='color:orange'>Wait approximately <strong>{$minutesUntilReset} minutes</strong> for the connection limit to reset.</p>";

echo "<h3>Immediate Solutions:</h3>";
echo "<ul>";
echo "<li>Contact hosting provider to increase connection limit</li>";
echo "<li>Reduce cron job frequency (run every 10 minutes instead of every minute)</li>";
echo "<li>Upgrade hosting plan</li>";
echo "</ul>";

echo "<h3>Current Limit:</h3>";
echo "<p>500 connections per hour exceeded</p>";
?>