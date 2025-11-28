<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$files = [
    'leaderboard.php' => file_exists('leaderboard.php'),
    'test_leaderboard.php' => file_exists('test_leaderboard.php'),
    'includes/leaderboard.php' => file_exists('includes/leaderboard.php'),
    'includes/config.php' => file_exists('includes/config.php'),
    'includes/jwt_helper.php' => file_exists('includes/jwt_helper.php')
];

echo json_encode([
    'success' => true,
    'message' => 'Debug info',
    'data' => [
        'request_uri' => $_SERVER['REQUEST_URI'],
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'script_name' => $_SERVER['SCRIPT_NAME'],
        'files_exist' => $files,
        'current_dir' => getcwd(),
        'php_version' => phpversion()
    ]
]);
?>