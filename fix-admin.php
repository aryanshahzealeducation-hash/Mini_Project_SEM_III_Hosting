<?php
// Simple script to create the correct admin user
// This will help you login with the credentials: admin@cafenix.com / password

// Database connection details
$host = 'localhost';
$dbname = 'cafenix';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hash the password "password"
    $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
    
    // Delete existing admin user
    $pdo->exec("DELETE FROM users WHERE email = 'admin@cafenix.com'");
    
    // Insert new admin user with correct password
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, status, email_verified) 
        VALUES (?, ?, ?, 'admin', 'active', TRUE)
    ");
    $stmt->execute(['Admin', 'admin@cafenix.com', $hashedPassword]);
    
    echo "✅ Admin user created successfully!<br><br>";
    echo "📧 Email: admin@cafenix.com<br>";
    echo "🔑 Password: password<br><br>";
    echo "🔐 Password Hash: " . $hashedPassword . "<br><br>";
    
    // Test the password verification
    if (password_verify('password', $hashedPassword)) {
        echo "✅ Password verification test: PASSED<br>";
    } else {
        echo "❌ Password verification test: FAILED<br>";
    }
    
    // Test database query
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute(['admin@cafenix.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify('password', $user['password'])) {
        echo "✅ Database login test: PASSED<br>";
        echo "👤 User found: " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")<br>";
        echo "🔐 Role: " . htmlspecialchars($user['role']) . "<br>";
        echo "📊 Status: " . htmlspecialchars($user['status']) . "<br>";
    } else {
        echo "❌ Database login test: FAILED<br>";
        if (!$user) {
            echo "   - User not found in database<br>";
        } else {
            echo "   - Password verification failed<br>";
        }
    }
    
    echo "<br><hr><br>";
    echo "<h3>🚀 Next Steps:</h3>";
    echo "1. Go to: <a href='http://localhost/CafeNix/login.php'>http://localhost/CafeNix/login.php</a><br>";
    echo "2. Login with: admin@cafenix.com / password<br>";
    echo "3. You should be redirected to the dashboard<br>";
    echo "4. Access admin panel at: <a href='http://localhost/CafeNix/admin/'>http://localhost/CafeNix/admin/</a><br>";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
    echo "<br>Please check:<br>";
    echo "1. XAMPP is running (Apache & MySQL)<br>";
    echo "2. Database 'cafenix' exists<br>";
    echo "3. MySQL credentials are correct (usually root/empty)<br>";
}
?>
