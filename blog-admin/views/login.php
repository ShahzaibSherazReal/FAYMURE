<?php
$base = BLOG_ADMIN_BASE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Admin Login | FAYMURE</title>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="blog-admin-body blog-admin-login-page">
    <div class="blog-admin-login-box">
        <h1><i class="fas fa-pen-fancy"></i> Blog CMS</h1>
        <p class="blog-admin-login-sub">Sign in to manage the blog</p>
        <?php if ($error !== ''): ?>
        <div class="blog-admin-alert blog-admin-alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="" class="blog-admin-login-form">
            <?php echo blog_admin_csrf_field(); ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="submit">Sign in</button>
        </form>
        <p class="blog-admin-login-forgot"><a href="<?php echo $base; ?>/forgot-password">Forgot password?</a> (Contact site admin.)</p>
    </div>
</body>
</html>
