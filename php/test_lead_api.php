<?php
/**
 * Test Lead Ingestion API
 * 
 * This file allows you to test the lead ingestion API endpoint
 * Usage: POST to this file with JSON data or use the form below
 */

require_once 'includes/config.php';

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    setApiHeaders();
    http_response_code(200);
    exit();
}

// If GET request, show test form (don't set JSON headers for HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Only set CORS headers, not JSON content type
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Test Lead Ingestion API</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
            button { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
            button:hover { background: #0056b3; }
            .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
            .log-viewer { background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 5px; max-height: 400px; overflow-y: auto; }
            pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        </style>
    </head>
    <body>
        <h1>Test Lead Ingestion API</h1>
        
        <form id="testForm">
            <div class="form-group">
                <label>External ID (Required):</label>
                <input type="text" name="external_id" value="TEST_<?php echo time(); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Full Name (Required):</label>
                <input type="text" name="full_name" value="Test User" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number (Required):</label>
                <input type="text" name="phone_number" value="+919876543210" required>
            </div>
            
            <div class="form-group">
                <label>Created Time (Required):</label>
                <input type="text" name="created_time" value="<?php echo date('Y-m-d H:i:s'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>City:</label>
                <input type="text" name="city" value="Mumbai">
            </div>
            
            <div class="form-group">
                <label>Campaign Name:</label>
                <input type="text" name="campaign_name" value="Test Campaign">
            </div>
            
            <div class="form-group">
                <label>Form Name:</label>
                <input type="text" name="form_name" value="Test Form">
            </div>
            
            <div class="form-group">
                <label>Sheet Source:</label>
                <input type="text" name="sheet_source" value="Test Sheet">
            </div>
            
            <div class="form-group">
                <label>Is Organic:</label>
                <input type="checkbox" name="is_organic" value="1">
            </div>
            
            <button type="submit">Test API</button>
        </form>
        
        <div id="result"></div>
        
        <div class="log-viewer">
            <h3>Recent Error Logs</h3>
            <pre id="errorLogs">Loading...</pre>
        </div>
        
        <div class="log-viewer">
            <h3>Recent Success Logs</h3>
            <pre id="successLogs">Loading...</pre>
        </div>
        
        <script>
            document.getElementById('testForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const data = {};
                formData.forEach((value, key) => {
                    if (key === 'is_organic') {
                        data[key] = value === '1' ? 1 : 0;
                    } else {
                        data[key] = value;
                    }
                });
                
                const resultDiv = document.getElementById('result');
                resultDiv.innerHTML = '<p>Sending request...</p>';
                
                try {
                    const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    const statusClass = result.success ? 'success' : 'error';
                    
                    resultDiv.innerHTML = `
                        <div class="result ${statusClass}">
                            <h3>Response (HTTP ${response.status}):</h3>
                            <pre>${JSON.stringify(result, null, 2)}</pre>
                        </div>
                    `;
                    
                    // Reload logs
                    loadLogs();
                } catch (error) {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h3>Error:</h3>
                            <pre>${error.message}</pre>
                        </div>
                    `;
                }
            });
            
            function loadLogs() {
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=view_logs&type=error')
                    .then(r => r.text())
                    .then(text => {
                        document.getElementById('errorLogs').textContent = text || 'No error logs found';
                    });
                    
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=view_logs&type=success')
                    .then(r => r.text())
                    .then(text => {
                        document.getElementById('successLogs').textContent = text || 'No success logs found';
                    });
            }
            
            // Load logs on page load
            loadLogs();
            setInterval(loadLogs, 5000); // Refresh every 5 seconds
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Handle log viewing
if (isset($_GET['action']) && $_GET['action'] === 'view_logs') {
    // Set plain text headers for log viewing
    header('Content-Type: text/plain; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    
    $type = $_GET['type'] ?? 'error';
    require_once 'includes/config.php';
    $logDir = getLogDirectory();
    $logFile = $logDir . '/lead_ingestion_' . $type . '.log';
    
    if (file_exists($logFile)) {
        $lines = file($logFile);
        // Show last 50 lines
        echo implode('', array_slice($lines, -50));
    } else {
        echo "Log file not found: " . basename($logFile);
    }
    exit();
}

// Handle POST request - test the API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    // Set JSON headers for API responses
    setApiHeaders();
    
    try {
        // Get the JSON input
        $rawInput = file_get_contents("php://input");
        
        if (empty($rawInput)) {
            ob_end_clean();
            sendResponse(false, "Empty request body. Please send JSON data.");
        }
        
        $input = json_decode($rawInput, true);
        
        if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            ob_end_clean();
            logError("JSON_ERROR", "JSON decode failed: " . $error, ['raw_input' => substr($rawInput, 0, 500)]);
            sendResponse(false, "Invalid JSON payload: " . $error);
        }
        
        if (!$input || !is_array($input)) {
            ob_end_clean();
            sendResponse(false, "Invalid JSON payload or not an object");
        }
        
        // Load the ingestion function directly without triggering routing
        // Set a flag to skip routing when included
        define('SKIP_LEADS_ROUTING', true);
        
        // Temporarily modify REQUEST_URI to match the route (so routing doesn't fail)
        $originalUri = $_SERVER['REQUEST_URI'] ?? '';
        $originalPath = parse_url($originalUri, PHP_URL_PATH);
        $_SERVER['REQUEST_URI'] = '/api/leads/ingest_google_sheet';
        
        // Include the file - routing will execute but will match our fake URI
        require_once 'includes/leads.php';
        
        // Restore original URI
        $_SERVER['REQUEST_URI'] = $originalUri;
        
        // If routing didn't call the function (shouldn't happen), call it directly
        // But actually, the routing should have called ingestGoogleSheet() which calls ingestGoogleSheetWithData()
        // However, we already have the parsed input, so we need to call it directly
        // Let's check if the function exists and call it
        if (function_exists('ingestGoogleSheetWithData')) {
            ingestGoogleSheetWithData($input, $rawInput);
        } else {
            throw new Exception("Function ingestGoogleSheetWithData not found after including leads.php");
        }
        
    } catch (Throwable $e) {
        $output = ob_get_clean();
        if (!empty($output)) {
            logError("OUTPUT_BUFFER", "Unexpected output before error", ['output' => $output]);
        }
        
        logError("FATAL_ERROR", "Uncaught exception in test_lead_api.php: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(500);
        sendResponse(false, "Server error: " . $e->getMessage());
    }
    
    // Clean any unexpected output
    ob_end_clean();
}

?>

