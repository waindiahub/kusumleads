<?php
require_once 'includes/config.php';

try {
    $db = getDB();
    
    // Update admin password
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@crm.com'");
    $stmt->execute([$passwordHash]);
    
    if ($stmt->rowCount() > 0) {
        echo "Admin password updated successfully!<br>";
    } else {
        echo "Admin user not found, creating new one...<br>";
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute(['Admin User', 'admin@crm.com', $passwordHash]);
        echo "Admin user created successfully!<br>";
    }
    
    echo "Admin user created successfully!<br>";
    echo "Email: admin@crm.com<br>";
    echo "Password: admin123";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>