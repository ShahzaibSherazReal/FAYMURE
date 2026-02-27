<?php
/**
 * Blog Admin – helpers: auth, CSRF, redirect, slugify, audit.
 */
require_once dirname(__DIR__) . '/config.php';

function blog_admin_conn() {
    return getDBConnection();
}

function blog_admin_csrf_token() {
    if (empty($_SESSION[BLOG_ADMIN_CSRF_TOKEN_NAME])) {
        $_SESSION[BLOG_ADMIN_CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[BLOG_ADMIN_CSRF_TOKEN_NAME];
}

function blog_admin_csrf_field() {
    return '<input type="hidden" name="' . htmlspecialchars(BLOG_ADMIN_CSRF_TOKEN_NAME) . '" value="' . htmlspecialchars(blog_admin_csrf_token()) . '">';
}

function blog_admin_validate_csrf() {
    $token = $_POST[BLOG_ADMIN_CSRF_TOKEN_NAME] ?? '';
    if (!hash_equals(blog_admin_csrf_token(), $token)) {
        return false;
    }
    return true;
}

function blog_admin_redirect($path = '', $code = 302) {
    $base = BLOG_ADMIN_BASE;
    $path = $path === '' ? '' : (strpos($path, '/') === 0 ? $path : '/' . $path);
    $url = $base . $path;
    // Use full URL so redirect works on live (avoids 404 when base path or rewrites differ)
    if (!preg_match('#^https?://#', $url) && isset($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $url;
    }
    header('Location: ' . $url, true, $code);
    exit;
}

function blog_admin_is_logged_in() {
    return !empty($_SESSION['blog_admin_user_id']) && !empty($_SESSION['blog_admin_username']);
}

function blog_admin_user_id() {
    return isset($_SESSION['blog_admin_user_id']) ? (int) $_SESSION['blog_admin_user_id'] : 0;
}

function blog_admin_username() {
    return isset($_SESSION['blog_admin_username']) ? $_SESSION['blog_admin_username'] : '';
}

function blog_admin_role() {
    return isset($_SESSION['blog_admin_role']) ? $_SESSION['blog_admin_role'] : 'editor';
}

function blog_admin_require_login() {
    if (!blog_admin_is_logged_in()) {
        blog_admin_redirect('/login');
    }
}

function blog_admin_slugify($text) {
    if ($text === null || trim($text) === '') return '';
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function blog_admin_audit_table_exists($conn) {
    $r = $conn->query("SHOW TABLES LIKE 'blog_admin_audit_log'");
    return $r && $r->num_rows > 0;
}

function blog_admin_audit($conn, $action, $entity_type, $entity_id = null, $old_value = null, $new_value = null) {
    if (!blog_admin_audit_table_exists($conn)) return;
    $user_id = blog_admin_user_id();
    $username = blog_admin_username();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (is_array($old_value)) $old_value = json_encode($old_value);
    if (is_array($new_value)) $new_value = json_encode($new_value);
    $stmt = $conn->prepare("INSERT INTO blog_admin_audit_log (user_id, username, action, entity_type, entity_id, old_value, new_value, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssisss', $user_id, $username, $action, $entity_type, $entity_id, $old_value, $new_value, $ip);
    $stmt->execute();
}

/** Rate limit login: returns true if under limit */
function blog_admin_login_rate_ok($conn) {
    if (!blog_admin_audit_table_exists($conn)) return true;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $window = time() - BLOG_ADMIN_LOGIN_RATE_WINDOW;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM blog_admin_audit_log WHERE action = 'login_failed' AND ip = ? AND created_at > FROM_UNIXTIME(?)");
    $stmt->bind_param('si', $ip, $window);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r && (int) $r['c'] < BLOG_ADMIN_LOGIN_RATE_LIMIT;
}

/** Record failed login for rate limiting */
function blog_admin_record_login_failed($conn) {
    if (!blog_admin_audit_table_exists($conn)) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $stmt = $conn->prepare("INSERT INTO blog_admin_audit_log (user_id, username, action, entity_type, entity_id, ip) VALUES (0, '', 'login_failed', 'auth', 0, ?)");
    $stmt->bind_param('s', $ip);
    $stmt->execute();
}

/** Estimate reading time (minutes) from content blocks JSON */
function blog_admin_reading_time_blocks($blocks_json) {
    if (empty($blocks_json)) return 1;
    $blocks = is_string($blocks_json) ? json_decode($blocks_json, true) : $blocks_json;
    if (!is_array($blocks)) return 1;
    $words = 0;
    foreach ($blocks as $b) {
        if (isset($b['content']) && is_string($b['content'])) $words += str_word_count($b['content']);
        if (isset($b['items']) && is_array($b['items'])) foreach ($b['items'] as $i) $words += str_word_count((string)$i);
    }
    return max(1, (int) ceil($words / 200));
}
