<?php
/**
 * Test Log Directory Permissions
 * This script helps diagnose log directory issues
 */

require_once 'includes/config.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Log Directory Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
        .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        h2 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>Log Directory Diagnostics</h1>
    
    <?php
    // Test 1: Check possible log directories
    echo '<div class="section">';
    echo '<h2>1. Checking Possible Log Directory Paths</h2>';
    
    $possiblePaths = [
        'Relative (includes/../logs)' => __DIR__ . '/includes/../logs',
        'Parent Directory' => dirname(__DIR__) . '/logs',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] . '/logs',
        'Script Directory' => dirname($_SERVER['SCRIPT_FILENAME']) . '/logs',
        'Current Directory' => __DIR__ . '/logs',
    ];
    
    foreach ($possiblePaths as $name => $path) {
        $realPath = realpath(dirname($path));
        $fullPath = $realPath ? $realPath . '/logs' : $path;
        $exists = is_dir($fullPath);
        $writable = $exists && is_writable($fullPath);
        $parentWritable = is_writable(dirname($fullPath));
        
        $status = $exists ? ($writable ? 'success' : 'warning') : 'error';
        $icon = $exists ? ($writable ? '✓' : '⚠') : '✗';
        
        echo "<div class='$status'>";
        echo "<strong>$icon $name:</strong><br>";
        echo "Path: <code>$fullPath</code><br>";
        echo "Exists: " . ($exists ? 'Yes' : 'No') . "<br>";
        echo "Writable: " . ($writable ? 'Yes' : 'No') . "<br>";
        echo "Parent Writable: " . ($parentWritable ? 'Yes' : 'No') . "<br>";
        if ($realPath) {
            echo "Real Path: <code>$realPath</code><br>";
        }
        echo "</div><br>";
    }
    echo '</div>';
    
    // Test 2: Try to create log directory
    echo '<div class="section">';
    echo '<h2>2. Testing Directory Creation</h2>';
    
    $testDirs = [
        __DIR__ . '/logs',
        dirname(__DIR__) . '/logs',
    ];
    
    foreach ($testDirs as $testDir) {
        echo "<h3>Testing: <code>$testDir</code></h3>";
        
        if (is_dir($testDir)) {
            echo "<div class='success'>✓ Directory already exists</div>";
        } else {
            $created = @mkdir($testDir, 0755, true);
            if ($created) {
                echo "<div class='success'>✓ Directory created successfully</div>";
            } else {
                $error = error_get_last();
                echo "<div class='error'>✗ Failed to create directory. Error: " . ($error['message'] ?? 'Unknown error') . "</div>";
            }
        }
        
        if (is_dir($testDir)) {
            $writable = is_writable($testDir);
            echo "<div class='" . ($writable ? 'success' : 'error') . "'>";
            echo $writable ? '✓ Directory is writable' : '✗ Directory is NOT writable';
            echo "</div>";
            
            // Try to write a test file
            $testFile = $testDir . '/test_write_' . time() . '.txt';
            $writeTest = @file_put_contents($testFile, 'test');
            if ($writeTest !== false) {
                echo "<div class='success'>✓ Can write files to directory</div>";
                @unlink($testFile);
            } else {
                echo "<div class='error'>✗ Cannot write files to directory</div>";
            }
        }
        echo "<br>";
    }
    echo '</div>';
    
    // Test 3: Test actual log functions
    echo '<div class="section">';
    echo '<h2>3. Testing Log Functions</h2>';
    
    $logDir = getLogDirectory();
    echo "<p><strong>Selected Log Directory:</strong> <code>$logDir</code></p>";
    
    // Test error log
    echo "<h3>Testing logError()</h3>";
    logError('TEST', 'This is a test error log entry', ['test' => true, 'timestamp' => time()]);
    $errorLog = $logDir . '/lead_ingestion_errors.log';
    if (file_exists($errorLog)) {
        echo "<div class='success'>✓ Error log file created: <code>$errorLog</code></div>";
        echo "<div>File size: " . filesize($errorLog) . " bytes</div>";
    } else {
        echo "<div class='error'>✗ Error log file NOT created: <code>$errorLog</code></div>";
    }
    
    // Test success log
    echo "<h3>Testing logSuccess()</h3>";
    logSuccess('TEST', 'This is a test success log entry', ['test' => true, 'timestamp' => time()]);
    $successLog = $logDir . '/lead_ingestion_success.log';
    if (file_exists($successLog)) {
        echo "<div class='success'>✓ Success log file created: <code>$successLog</code></div>";
        echo "<div>File size: " . filesize($successLog) . " bytes</div>";
    } else {
        echo "<div class='error'>✗ Success log file NOT created: <code>$successLog</code></div>";
    }
    
    echo '</div>';
    
    // Test 4: Server Information
    echo '<div class="section">';
    echo '<h2>4. Server Information</h2>';
    echo "<pre>";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
    echo "Current Directory (__DIR__): " . __DIR__ . "\n";
    echo "Parent Directory: " . dirname(__DIR__) . "\n";
    echo "User: " . (function_exists('get_current_user') ? get_current_user() : 'Unknown') . "\n";
    echo "Error Log: " . ini_get('error_log') . "\n";
    echo "</pre>";
    echo '</div>';
    
    // Test 5: Show log contents if they exist
    echo '<div class="section">';
    echo '<h2>5. Current Log Contents</h2>';
    
    if (file_exists($errorLog)) {
        echo "<h3>Error Log (last 20 lines):</h3>";
        echo "<pre>" . htmlspecialchars(implode("\n", array_slice(file($errorLog), -20))) . "</pre>";
    } else {
        echo "<div class='error'>Error log file does not exist</div>";
    }
    
    if (file_exists($successLog)) {
        echo "<h3>Success Log (last 20 lines):</h3>";
        echo "<pre>" . htmlspecialchars(implode("\n", array_slice(file($successLog), -20))) . "</pre>";
    } else {
        echo "<div class='error'>Success log file does not exist</div>";
    }
    
    echo '</div>';
    ?>
    
    <div class="section">
        <h2>Next Steps</h2>
        <ol>
            <li>If directories cannot be created, check file permissions via FTP/cPanel</li>
            <li>Manually create the <code>logs</code> directory in your <code>phpcode</code> folder</li>
            <li>Set permissions to 755 or 777 (depending on your server)</li>
            <li>Refresh this page to verify</li>
            <li>Then test the API at <a href="test_lead_api.php">test_lead_api.php</a></li>
        </ol>
    </div>
</body>
</html>

