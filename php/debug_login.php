<?php
require_once 'includes/config.php';

$email = 'admin@crm.com';
$password = 'admin123';

try {
    $db = getDB();
    
    // Check what users exist
    echo "<h3>All users in database:</h3>";
    $stmt = $db->prepare("SELECT id, name, email, role, active FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Role: {$user['role']}, Active: {$user['active']}<br>";
    }
    
    echo "<hr>";
    
    // Check specific admin user
    echo "<h3>Admin user check:</h3>";
    $stmt = $db->prepare("SELECT id, name, email, password_hash, role, active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "User found:<br>";
        echo "ID: {$user['id']}<br>";
        echo "Name: {$user['name']}<br>";
        echo "Email: {$user['email']}<br>";
        echo "Role: {$user['role']}<br>";
        echo "Active: {$user['active']}<br>";
        echo "Password hash: {$user['password_hash']}<br>";
        
        // Test password
        if (password_verify($password, $user['password_hash'])) {
            echo "<strong>Password verification: SUCCESS</strong><br>";
        } else {
            echo "<strong>Password verification: FAILED</strong><br>";
            
            // Create new hash
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            echo "New hash would be: $newHash<br>";
        }
        
        // Check role and active status
        if ($user['role'] === 'admin') {
            echo "Role check: PASSED<br>";
        } else {
            echo "Role check: FAILED (not admin)<br>";
        }
        
        if ($user['active']) {
            echo "Active check: PASSED<br>";
        } else {
            echo "Active check: FAILED (inactive)<br>";
        }
        
    } else {
        echo "No user found with email: $email<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>