<?php
/**
 * Fix Admin User Script
 * This script will create or update the admin user with the correct password
 * Run this once if you're having login issues
 */

require_once 'config/database.php';

$conn = getDBConnection();

// Generate proper password hash for 'admin123'
$hashed_password = password_hash('admin123', PASSWORD_DEFAULT);

// Check if admin user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'admin'");
$stmt->execute();
$result = $stmt->get_result();
$admin_exists = $result->num_rows > 0;
$stmt->close();

if ($admin_exists) {
    // Update existing admin user
    $stmt = $conn->prepare("UPDATE users SET password = ?, is_admin = 1, email = 'admin@faymure.com' WHERE username = 'admin'");
    $stmt->bind_param("s", $hashed_password);
    
    if ($stmt->execute()) {
        echo "<h2>✓ Admin User Updated Successfully!</h2>";
        echo "<p>The admin password has been reset.</p>";
    } else {
        echo "<h2>✗ Error updating admin user</h2>";
        echo "<p>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    // Create new admin user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)");
    $email = 'admin@faymure.com';
    $is_admin = 1;
    $stmt->bind_param("sssi", $username, $email, $hashed_password, $is_admin);
    $username = 'admin';
    
    if ($stmt->execute()) {
        echo "<h2>✓ Admin User Created Successfully!</h2>";
        echo "<p>A new admin user has been created.</p>";
    } else {
        echo "<h2>✗ Error creating admin user</h2>";
        echo "<p>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

echo "<hr>";
echo "<h3>Admin Login Credentials:</h3>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";

// Verify the password works
$test_password = 'admin123';
$stmt = $conn->prepare("SELECT password FROM users WHERE username = 'admin'");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (password_verify($test_password, $row['password'])) {
        echo "<p style='color: green;'><strong>✓ Password verification successful!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Password verification failed!</strong></p>";
    }
}
$stmt->close();

$conn->close();
?>

