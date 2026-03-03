<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($username && $email && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $conn = getDBConnection();
            
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND deleted_at IS NULL");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already exists.";
            } else {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Email already exists.";
                } else {
                    // Create user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $username, $email, $hashed_password);
                    
                    if ($stmt->execute()) {
                        $success = true;
                    } else {
                        $error = "Failed to create account. Please try again.";
                    }
                }
            }
            $stmt->close();
            $conn->close();
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">FAYMURE</div>
            <h1>Sign Up</h1>
            <?php if ($success): ?>
                <div class="success-message">
                    Account created successfully! <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/login">Login here</a>
                </div>
            <?php elseif ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!$success): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary">Sign Up</button>
                </form>
            <?php endif; ?>
            <p class="auth-link">Already have an account? <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/login">Login</a></p>
            <div class="auth-alt">
                <p class="auth-alt-label">Or continue with</p>
                <div class="auth-alt-buttons">
                    <button type="button" class="btn-auth-alt btn-auth-google">
                        <i class="fab fa-google"></i>
                        <span>Continue with Google</span>
                    </button>
                    <button type="button" class="btn-auth-alt btn-auth-email">
                        <i class="fa-regular fa-envelope"></i>
                        <span>Continue with Email</span>
                    </button>
                </div>
            </div>
            <p class="auth-link"><a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/">Back to Home</a></p>
        </div>
    </div>
</body>
</html>

