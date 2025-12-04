<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cafenix');

// Site Configuration
define('SITE_NAME', 'CafeNIX');
define('SITE_URL', 'http://localhost/CafeNix/');
define('ADMIN_EMAIL', 'admin@cafenix.com');

// Security
define('JWT_SECRET', 'your-super-secret-jwt-key-change-this');
define('HASH_COST', 12);

// File Upload
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', ['zip', 'rar', 'pdf', 'jpg', 'png', 'gif']);

// Currency
define('CURRENCY', 'INR');
define('CURRENCY_SYMBOL', '₹');

// Pagination
define('ITEMS_PER_PAGE', 12);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');
?>
