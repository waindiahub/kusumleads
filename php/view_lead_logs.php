<?php
/**
 * View Lead Ingestion Logs
 * Simple page to view error and success logs
 */

require_once 'includes/config.php';

// getLogDirectory() is now defined in config.php, so we can use it directly
$logDir = getLogDirectory();
$errorLog = $logDir . '/lead_ingestion_errors.log';
$successLog = $logDir . '/lead_ingestion_success.log';

function readLogFile($file, $lines = 100) {
    if (!file_exists($file)) {
        return "Log file not found: " . basename($file) . "\nFull path: " . $file . "\n\nPlease check:\n1. File permissions\n2. Log directory exists\n3. Run test_log_permissions.php to diagnose";
    }
    
    $content = file_get_contents($file);
    if (empty($content)) {
        return "Log file is empty.";
    }
    
    $allLines = explode("\n", $content);
    $recentLines = array_slice($allLines, -$lines);
    
    return implode("\n", $recentLines);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Lead Ingestion Logs</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .log-section { margin-bottom: 30px; }
        .log-section h2 { background: #333; color: white; padding: 10px; margin: 0; }
        .log-content { background: #f8f9fa; padding: 15px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
        .error-log { border-left: 4px solid #dc3545; }
        .success-log { border-left: 4px solid #28a745; }
        .refresh-btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-bottom: 20px; }
        .refresh-btn:hover { background: #0056b3; }
        .stats { background: #e9ecef; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Lead Ingestion Logs</h1>
    
    <button class="refresh-btn" onclick="location.reload()">Refresh Logs</button>
    
    <div class="stats">
        <strong>Log Files Location:</strong> <code><?php echo $logDir; ?></code><br>
        <strong>Error Log:</strong> 
        <?php 
        if (file_exists($errorLog)) {
            $errorSize = filesize($errorLog);
            $errorModified = date('Y-m-d H:i:s', filemtime($errorLog));
            echo '✓ Found (' . number_format($errorSize) . ' bytes, last modified: ' . $errorModified . ')';
        } else {
            echo '✗ Not found at: <code>' . $errorLog . '</code>';
        }
        ?><br>
        <strong>Success Log:</strong> 
        <?php 
        if (file_exists($successLog)) {
            $successSize = filesize($successLog);
            $successModified = date('Y-m-d H:i:s', filemtime($successLog));
            echo '✓ Found (' . number_format($successSize) . ' bytes, last modified: ' . $successModified . ')';
        } else {
            echo '✗ Not found at: <code>' . $successLog . '</code>';
        }
        ?><br>
        <strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
        <strong>Directory Writable:</strong> <?php echo is_writable($logDir) ? '✓ Yes' : '✗ No'; ?>
    </div>
    
    <div class="log-section">
        <h2 class="error-log">Error Logs (Last 100 lines)</h2>
        <div class="log-content">
            <pre><?php echo htmlspecialchars(readLogFile($errorLog, 100)); ?></pre>
        </div>
    </div>
    
    <div class="log-section">
        <h2 class="success-log">Success Logs (Last 100 lines)</h2>
        <div class="log-content">
            <pre><?php echo htmlspecialchars(readLogFile($successLog, 100)); ?></pre>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 10 seconds
        setTimeout(function() {
            location.reload();
        }, 10000);
    </script>
</body>
</html>

