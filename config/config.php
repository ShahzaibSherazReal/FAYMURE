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

/**
 * Visitor tracking: get or set anonymous visitor code (cookie).
 */
function get_or_create_visitor_code() {
    if (!empty($_COOKIE['visitor_code'])) {
        return $_COOKIE['visitor_code'];
    }
    $code = bin2hex(random_bytes(16));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('visitor_code', $code, time() + 365 * 24 * 60 * 60, '/', '', $secure, true);
    $_COOKIE['visitor_code'] = $code;
    return $code;
}

/**
 * Get country from IP (Cloudflare header or ipapi.co).
 */
function get_country_from_ip($ip) {
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        return $_SERVER['HTTP_CF_IPCOUNTRY'];
    }
    $country = 'Unknown';
    $ip = ($ip === '::1' || $ip === '') ? '127.0.0.1' : $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $ch = @curl_init();
        if ($ch) {
            curl_setopt($ch, CURLOPT_URL, 'https://ipapi.co/' . $ip . '/country_name/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $res = @curl_exec($ch);
            if ($res && !curl_errno($ch)) {
                $country = trim($res) ?: 'Unknown';
            }
            curl_close($ch);
        }
    }
    return $country;
}

/**
 * Link all visitor_logs for current visitor_code to this user (after login/signup).
 */
function associate_current_visitor_with_user($user_id, $username) {
    if (!$user_id) return;
    $code = get_or_create_visitor_code();
    $conn = getDBConnection();
    $t = $conn->query("SHOW TABLES LIKE 'visitor_logs'");
    if (!$t || $t->num_rows === 0) {
        $conn->close();
        return;
    }
    $stmt = $conn->prepare("UPDATE visitor_logs SET user_id = ?, user_name = ? WHERE visitor_code = ?");
    if ($stmt) {
        $stmt->bind_param('iss', $user_id, $username, $code);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

// ---- Visitor analytics helpers ----
function vt_get_cookie_value($name) {
    return isset($_COOKIE[$name]) ? (string)$_COOKIE[$name] : '';
}

function vt_set_cookie($name, $value, $days = 365) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie($name, $value, time() + ($days * 24 * 60 * 60), '/', '', $secure, true);
    $_COOKIE[$name] = $value;
}

function vt_get_or_create_guest_id() {
    $gid = vt_get_cookie_value('guest_id');
    if ($gid !== '' && preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $gid)) {
        return $gid;
    }
    $gid = 'g_' . bin2hex(random_bytes(16));
    vt_set_cookie('guest_id', $gid, 365);
    return $gid;
}

function vt_get_session_id() {
    // JS supplies a UUID session_id; server-side fallback if needed
    if (!empty($_SESSION['vt_session_id'])) return $_SESSION['vt_session_id'];
    $sid = bin2hex(random_bytes(16));
    $_SESSION['vt_session_id'] = $sid;
    return $sid;
}

function vt_link_guest_to_user($user_id) {
    if (!$user_id) return;
    $guest_id = vt_get_or_create_guest_id();
    try {
        $conn = getDBConnection();
        $t = $conn->query("SHOW TABLES LIKE 'visitor_profiles'");
        if (!$t || $t->num_rows === 0) { $conn->close(); return; }
        $stmt = $conn->prepare("UPDATE visitor_profiles SET user_id = ? WHERE guest_id = ?");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $guest_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("UPDATE visitor_sessions SET user_id = ? WHERE guest_id = ?");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $guest_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("UPDATE visitor_events SET user_id = ? WHERE guest_id = ? AND user_id IS NULL");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $guest_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (Throwable $e) {}
}
?>

