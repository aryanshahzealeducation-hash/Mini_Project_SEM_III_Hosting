<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Get download token from URL
$token = sanitize($_GET['token'] ?? '');

if (empty($token)) {
    redirect('orders.php');
}

// Validate download token
$download = $db->query("
    SELECT d.*, p.name as product_name, p.file_path 
    FROM downloads d 
    JOIN products p ON d.product_id = p.id 
    WHERE d.download_token = ? AND d.user_id = ? AND d.expires_at > NOW()
", [$token, $userId])->fetch();

if (!$download) {
    $_SESSION['error'] = 'Invalid or expired download link';
    redirect('orders.php');
}

// Check if file exists
if (empty($download['file_path']) || !file_exists($download['file_path'])) {
    $_SESSION['error'] = 'Download file not available';
    redirect('orders.php');
}

// Increment download count
$db->query("UPDATE downloads SET download_count = download_count + 1, last_downloaded = NOW() WHERE id = ?", [$download['id']]);

// Get file info
$filePath = $download['file_path'];
$fileName = basename($filePath);
$fileSize = filesize($filePath);

// Set appropriate headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Read and output the file
readfile($filePath);
exit;
?>
