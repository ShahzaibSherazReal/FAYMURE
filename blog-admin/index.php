<?php
/**
 * Blog Admin – front controller. Isolated from main admin.
 */
require_once __DIR__ . '/includes/functions.php';

$p = isset($_GET['p']) ? trim($_GET['p']) : 'dashboard';
$p = $p === '' ? 'dashboard' : $p;
$parts = explode('/', $p);
$page = $parts[0];
$sub = $parts[1] ?? '';

// Logout (no auth required)
if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    blog_admin_redirect('/login');
}

// Install
if ($page === 'install') {
    require __DIR__ . '/install.php';
    exit;
}

// Public: login, forgot-password
if ($page === 'login' || $page === 'forgot-password') {
    require __DIR__ . '/controllers/login.php';
    exit;
}

// Require login for all other pages
blog_admin_require_login();

switch ($page) {
    case 'dashboard':
        require __DIR__ . '/controllers/dashboard.php';
        break;
    case 'posts':
        require __DIR__ . '/controllers/posts.php';
        break;
    case 'post-new':
    case 'post-edit':
        require __DIR__ . '/controllers/post-edit.php';
        break;
    case 'categories':
        require __DIR__ . '/controllers/categories.php';
        break;
    case 'tags':
        require __DIR__ . '/controllers/tags.php';
        break;
    case 'media':
        require __DIR__ . '/controllers/media.php';
        break;
    case 'settings':
        require __DIR__ . '/controllers/settings.php';
        break;
    case 'audit':
        require __DIR__ . '/controllers/audit.php';
        break;
    default:
        blog_admin_redirect('/dashboard');
}
