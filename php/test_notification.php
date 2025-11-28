<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

setApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed');
}

sendResponse(false, 'Notification service has been removed');
?>