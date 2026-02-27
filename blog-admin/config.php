<?php
/**
 * Blog Admin – isolated config. Do not require main site config for security.
 */
if (!defined('BLOG_ADMIN_CONFIG')) {
    define('BLOG_ADMIN_LOADING', true);
    require_once __DIR__ . '/../config/config.php';
    if (!defined('BASE_PATH')) define('BASE_PATH', '/FAYMURE');
    define('BLOG_ADMIN_CONFIG', true);
}

// Isolated session (separate from main admin)
define('BLOG_ADMIN_SESSION_NAME', 'FAYMURE_BLOG_ADMIN_SESSION');
define('BLOG_ADMIN_SESSION_LIFETIME', 7200); // 2 hours
define('BLOG_ADMIN_BASE', rtrim((defined('BASE_PATH') ? BASE_PATH : '') . '/blog-admin', '/'));
define('BLOG_ADMIN_UPLOAD_DIR', dirname(__DIR__) . '/uploads/blog');
define('BLOG_ADMIN_UPLOAD_URL', (defined('BASE_PATH') ? BASE_PATH : '') . '/uploads/blog');
define('BLOG_ADMIN_MAX_UPLOAD_MB', 5);
define('BLOG_ADMIN_LOGIN_RATE_LIMIT', 5);   // max attempts
define('BLOG_ADMIN_LOGIN_RATE_WINDOW', 60); // 1 minute
define('BLOG_ADMIN_CSRF_TOKEN_NAME', 'blog_admin_csrf');

// Allowed image MIME types
define('BLOG_ADMIN_ALLOWED_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Start isolated session with secure options
if (session_status() === PHP_SESSION_NONE) {
    session_name(BLOG_ADMIN_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => BLOG_ADMIN_SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
