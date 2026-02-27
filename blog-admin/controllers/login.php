<?php
/**
 * Blog Admin – Login & Forgot password (placeholder)
 */
$conn = blog_admin_conn();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 'login') {
    if (!blog_admin_validate_csrf()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!blog_admin_login_rate_ok($conn)) {
        $error = 'Too many failed attempts. Try again in a minute.';
    } else {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        if ($username === '' || $password === '') {
            $error = 'Please enter username and password.';
        } else {
            $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM blog_admin_users WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if ($row !== null && password_verify($password, $row['password_hash'])) {
                $_SESSION['blog_admin_user_id'] = (int) $row['id'];
                $_SESSION['blog_admin_username'] = $row['username'];
                $_SESSION['blog_admin_role'] = $row['role'];
                blog_admin_audit($conn, 'login', 'auth', $row['id']);
                $conn->close();
                blog_admin_redirect('/dashboard');
            } else {
                blog_admin_record_login_failed($conn);
                $error = 'Invalid username or password.';
            }
        }
    }
}

if ($p === 'forgot-password') {
    $page_title = 'Forgot password';
    $content = '<div class="blog-admin-page"><h1>Forgot password</h1><p>Contact the site administrator to reset your Blog CMS password.</p><p><a href="' . BLOG_ADMIN_BASE . '/login">Back to login</a></p></div>';
    require __DIR__ . '/../views/layout.php';
    exit;
}

$page_title = 'Blog Admin Login';
require __DIR__ . '/../views/login.php';
