<?php
session_start();

// Site configuration
define('SITE_NAME', 'FAYMURE');
define('SITE_URL', 'http://localhost/FAYMURE');
define('ADMIN_EMAIL', 'admin@faymure.com');

// Include database connection
require_once __DIR__ . '/database.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>

