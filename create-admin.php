<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();

// Create admin user with password "password"
$hashedPassword = password_hash('password', PASSWORD_DEFAULT, ['cost' => HASH_COST]);

// Delete existing admin user if exists
$db->query("DELETE FROM users WHERE email = 'admin@cafenix.com'");

// Insert new admin user
$db->query("
    INSERT INTO users (name, email, password, role, status, email_verified) 
    VALUES (?, ?, ?, 'admin', 'active', TRUE)
", ['Admin', 'admin@cafenix.com', $hashedPassword]);

echo "Admin user created successfully!<br>";
echo "Email: admin@cafenix.com<br>";
echo "Password: password<br>";
echo "Hash: " . $hashedPassword . "<br>";

// Verify the password
$verify = password_verify('password', $hashedPassword);
echo "Password verification: " . ($verify ? 'SUCCESS' : 'FAILED') . "<br>";

// Test login
$user = $db->query("SELECT * FROM users WHERE email = ? AND status = 'active'", ['admin@cafenix.com'])->fetch();

if ($user && password_verify('password', $user['password'])) {
    echo "Login test: SUCCESS<br>";
    echo "User found: " . $user['name'] . " (" . $user['email'] . ")<br>";
} else {
    echo "Login test: FAILED<br>";
}
?>
