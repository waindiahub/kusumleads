<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

// -------------------------------------------------------
// Frontend cache/version helpers
// -------------------------------------------------------
if (!defined('APP_ASSET_VERSION')) {
    define('APP_ASSET_VERSION', '2025.11.28');
}

if (!function_exists('enforceNoCacheHeaders')) {
    function enforceNoCacheHeaders(): void {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

enforceNoCacheHeaders();

// Database Configuration
define('DB_HOST', '193.203.184.167');
define('DB_NAME', 'u675018328_kusum');
define('DB_USER', 'u675018328_kusum');
define('DB_PASS', 'NUUOOe7#C');

// JWT Configuration
define('JWT_SECRET', 'your-jwt-secret-key-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400); // 24 hours

// -------------------------------------------------------
// OneSignal Configuration
// -------------------------------------------------------
define('ONESIGNAL_APP_ID', 'ca751a15-6451-457b-aa3c-3b9a52eee8f6');

// NEVER SHARE PUBLICLY
define('ONESIGNAL_REST_API_KEY',
'os_v2_app_zj2ruflekfcxxkr4honff3xi6zsghuot5wpupy4awtbp5ffio54rbtjdlbfktiftxghs2wf2npqdndnh47ijdom4nkg5n4lnq7eykty');

// -------------------------------------------------------
// CORS Headers
// -------------------------------------------------------
function setApiHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, Cache-Control, X-File-Name');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');
    header('Vary: Origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// -------------------------------------------------------
// Hostinger-Optimized DB Class (NO PERSISTENT CONNECTIONS)
// -------------------------------------------------------
class DB {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $maxRetries = 3;
        $retryDelay = 1;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::ATTR_TIMEOUT => 5
                ];
                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '+05:30'";
                }

                self::$pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    $options
                );

                return self::$pdo;

            } catch (PDOException $e) {

                // Hostinger block reached
                if (strpos($e->getMessage(), 'max_connections_per_hour') !== false) {
                    http_response_code(503);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Server is busy. Try again after 1 minute.'
                    ]);
                    exit();
                }

                // If last retry → throw 500
                if ($i === $maxRetries - 1) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Database connection failed'
                    ]);
                    exit();
                }

                sleep($retryDelay);
            }
        }
    }
}

function getDB() {
    return DB::connect();
}

function getSetting($key) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// -------------------------------------------------------
// Error Logging Helper
// -------------------------------------------------------
function getLogDirectory() {
    // Try multiple possible paths
    $possiblePaths = [
        __DIR__ . '/../logs',  // Relative to includes directory
        dirname(__DIR__) . '/logs',  // Explicit parent directory
        $_SERVER['DOCUMENT_ROOT'] . '/logs',  // Document root
        dirname($_SERVER['SCRIPT_FILENAME']) . '/logs',  // Script directory
    ];
    
    foreach ($possiblePaths as $path) {
        $realPath = realpath(dirname($path));
        if ($realPath && is_writable($realPath)) {
            $logDir = $realPath . '/logs';
            if (!is_dir($logDir)) {
                if (@mkdir($logDir, 0755, true)) {
                    return $logDir;
                }
            } else {
                return $logDir;
            }
        }
    }
    
    // Fallback: use includes directory parent
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    return $logDir;
}

function logError($type, $message, $data = null) {
    $logDir = getLogDirectory();
    $logFile = $logDir . '/lead_ingestion_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message";
    
    if ($data !== null) {
        $logEntry .= "\nData: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    // Try to write to log file
    $written = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // If file write failed, log to PHP error log with directory info
    if ($written === false) {
        $errorInfo = "Failed to write to: $logFile | Directory exists: " . (is_dir($logDir) ? 'yes' : 'no') . " | Writable: " . (is_writable($logDir) ? 'yes' : 'no');
        error_log("[$type] $message | $errorInfo" . ($data ? " | Data: " . json_encode($data) : ""));
    } else {
        // Also log to PHP error log for backup
        error_log("[$type] $message" . ($data ? " | Data: " . json_encode($data) : ""));
    }
}

function logSuccess($type, $message, $data = null) {
    $logDir = getLogDirectory();
    $logFile = $logDir . '/lead_ingestion_success.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message";
    
    if ($data !== null) {
        $logEntry .= "\nData: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// -------------------------------------------------------
// JSON Response Helper
// -------------------------------------------------------
function sendResponse($success, $message, $data = null) {
    // Set appropriate HTTP status code
    if ($success) {
        http_response_code(200);
    } else {
        http_response_code(400); // Bad Request for validation errors
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

?>
