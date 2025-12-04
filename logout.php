<?php
require_once 'config.php';
require_once 'functions.php';

// Destroy session
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Redirect to login
redirect('login.php');
?>
