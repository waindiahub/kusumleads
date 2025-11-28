<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Test direct connection
try {
    $pdo = new PDO(
        "mysql:host=srv1666.hstgr.io;dbname=u675018328_kusum;charset=utf8mb4",
        "u675018328_kusum",
        "NUUOOe7#C",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "<p style='color:green'>✓ Direct PDO connection successful</p>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Users table has {$result['count']} records</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Direct connection failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Test via config.php
try {
    require_once 'includes/config.php';
    echo "<p style='color:green'>✓ Config.php loaded successfully</p>";
    
    $db = getDB();
    echo "<p style='color:green'>✓ getDB() function works</p>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM leads");
    $result = $stmt->fetch();
    echo "<p>Leads table has {$result['count']} records</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Config connection failed: " . $e->getMessage() . "</p>";
}
?>