<?php
$base = BLOG_ADMIN_BASE;
$site_base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
$style_path = __DIR__ . '/../../assets/css/style.css';
$style_ver = file_exists($style_path) ? filemtime($style_path) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Admin Login | FAYMURE</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($site_base); ?>/assets/css/style.css?v=<?php echo $style_ver; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">FAYMURE</div>
            <h1>Blog CMS Login</h1>
            <?php if ($error !== ''): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="">
                <?php echo blog_admin_csrf_field(); ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password" title="Show password">
                            <i class="far fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Login</button>
            </form>
            <p class="auth-link"><a href="<?php echo htmlspecialchars($base); ?>/forgot-password">Forgot password?</a> (Contact site admin.)</p>
            <p class="auth-link"><a href="<?php echo htmlspecialchars($site_base ?: '/'); ?>/">Back to Home</a></p>
        </div>
    </div>
    <script>
    (function() {
        var btn = document.getElementById('togglePassword');
        var input = document.getElementById('password');
        if (!btn || !input) return;
        btn.addEventListener('click', function() {
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                btn.setAttribute('aria-label', 'Hide password');
                btn.setAttribute('title', 'Hide password');
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            } else {
                input.type = 'password';
                btn.setAttribute('aria-label', 'Show password');
                btn.setAttribute('title', 'Show password');
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            }
        });
    })();
    </script>
</body>
</html>
