<?php
// Blog admin loads this for BASE_PATH only; it uses its own session.
if (!defined('BLOG_ADMIN_LOADING')) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'FAYMURE');
define('SITE_URL', 'http://localhost/FAYMURE');
define('ADMIN_EMAIL', 'admin@faymure.com');

// Canonical base URL for SEO (always https://www in production). Used for <link rel="canonical">.
define('CANONICAL_BASE_URL', 'https://www.faymure.com');

// Base path for clean URLs (no trailing slash). Use '' if site is at domain root.
define('BASE_PATH', '/FAYMURE');

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
    if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
        // Map common .php to clean path for redirects
        $clean = ['index.php' => '/', 'shop.php' => '/shop', 'cart.php' => '/cart', 'login.php' => '/login', 'logout.php' => '/logout', 'signup.php' => '/signup', 'categories.php' => '/categories', 'products.php' => '/products'];
        if (isset($clean[$url])) {
            $url = $clean[$url];
        } elseif (strpos($url, 'product-detail.php') === 0) {
            $url = '/product-detail' . (strpos($url, '?') !== false ? substr($url, strpos($url, '?')) : '');
        }
        $url = (BASE_PATH !== '' ? BASE_PATH : '') . ($url === '' || $url === '/' ? '/' : (strpos($url, '/') === 0 ? $url : '/' . $url));
    }
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Whether the blog section is hidden from the site (controlled by dashboard toggle).
 * When true, blog nav link is hidden and /blog/* returns 404.
 */
function is_blog_hidden() {
    $conn = getDBConnection();
    $r = $conn->query("SELECT content_value FROM site_content WHERE content_key = 'blog_hidden'");
    $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    $conn->close();
    return $row && isset($row['content_value']) && $row['content_value'] === '1';
}

/**
 * Generate SEO-friendly slug from a string (e.g. product name).
 * Returns lowercase alphanumeric and hyphens only; empty string becomes 'product'.
 */
function slugify($text) {
    if ($text === null || $text === '') {
        return 'product';
    }
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug === '' ? 'product' : $slug;
}
?>

