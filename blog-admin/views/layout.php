<?php
$base = BLOG_ADMIN_BASE;
$current_user = blog_admin_username();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Blog Admin'; ?> | FAYMURE</title>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="blog-admin-body">
    <?php if (blog_admin_is_logged_in()): ?>
    <header class="blog-admin-header">
        <div class="blog-admin-header-inner">
            <a href="<?php echo $base; ?>/dashboard" class="blog-admin-logo">Blog CMS</a>
            <nav class="blog-admin-nav">
                <a href="<?php echo $base; ?>/dashboard"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="<?php echo $base; ?>/posts"><i class="fas fa-file-alt"></i> Posts</a>
                <a href="<?php echo $base; ?>/post-new"><i class="fas fa-plus"></i> New post</a>
                <a href="<?php echo $base; ?>/categories"><i class="fas fa-folder"></i> Categories</a>
                <a href="<?php echo $base; ?>/tags"><i class="fas fa-tags"></i> Tags</a>
                <a href="<?php echo $base; ?>/media"><i class="fas fa-images"></i> Media</a>
                <a href="<?php echo $base; ?>/settings"><i class="fas fa-cog"></i> Settings</a>
                <a href="<?php echo $base; ?>/audit"><i class="fas fa-history"></i> Audit log</a>
            </nav>
            <div class="blog-admin-user">
                <span><?php echo htmlspecialchars($current_user); ?></span>
                <a href="<?php echo $base; ?>/logout" class="blog-admin-logout">Logout</a>
            </div>
        </div>
    </header>
    <main class="blog-admin-main">
        <?php endif; ?>
        <?php if (!empty($flash_error)): ?>
        <div class="blog-admin-alert blog-admin-alert-error"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_success)): ?>
        <div class="blog-admin-alert blog-admin-alert-success"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php echo $content ?? ''; ?>
    <?php if (blog_admin_is_logged_in()): ?>
    </main>
    <?php endif; ?>
    <script src="<?php echo $base; ?>/assets/js/admin.js"></script>
    <?php if (!empty($page_script)) echo $page_script; ?>
</body>
</html>
