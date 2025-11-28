<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === '/' || $uri === '' || $uri === '/index.php') { require __DIR__ . '/index.php'; return; }
if (preg_match('#^/api/whatsapp#', $uri)) { require __DIR__ . '/includes/whatsapp.php'; return; }
if (preg_match('#^/api/#', $uri)) { require __DIR__ . '/index.php'; return; }
if (preg_match('#^/auth/#', $uri)) { require __DIR__ . '/includes/auth.php'; return; }
if (preg_match('#^/leads#', $uri)) { require __DIR__ . '/includes/leads.php'; return; }
if (preg_match('#^/agents/#', $uri)) { require __DIR__ . '/includes/agents.php'; return; }
if (preg_match('#^/reports/#', $uri)) { require __DIR__ . '/includes/reports.php'; return; }
if (preg_match('#^/leaderboard#', $uri)) { require __DIR__ . '/index.php'; return; }
if (preg_match('#^/reminders#', $uri)) { require __DIR__ . '/index.php'; return; }
if (preg_match('#^/whatsapp_templates#', $uri)) { require __DIR__ . '/whatsapp_templates.php'; return; }
$path = __DIR__ . $uri;
if (is_file($path)) { return false; }
require __DIR__ . '/index.php';
