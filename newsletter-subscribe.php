<?php
ob_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json; charset=UTF-8');

function newsletter_json_response($success, $message, $status_code = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status_code);
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletter_json_response(false, 'Method not allowed.', 405);
}

$email_raw = trim((string)($_POST['email'] ?? ''));
$email = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ? $email_raw : '';

if ($email === '') {
    newsletter_json_response(false, 'Please enter a valid email address.', 422);
}

$conn = getDBConnection();

$create_sql = "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    source VARCHAR(100) DEFAULT 'popup',
    status ENUM('active','unsubscribed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
if (!$conn->query($create_sql)) {
    $conn->close();
    newsletter_json_response(false, 'Database setup failed for newsletter subscribers.', 500);
}

$stmt = $conn->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1");
if (!$stmt) {
    $conn->close();
    newsletter_json_response(false, 'Could not prepare subscriber lookup query.', 500);
}
$stmt->bind_param('s', $email);
$ok_exec = $stmt->execute();
$existing = null;
if ($ok_exec) {
    $stmt->bind_result($existing_id);
    if ($stmt->fetch()) {
        $existing = ['id' => (int)$existing_id];
    }
}
$stmt->close();

if ($existing) {
    $subscriber_id = (int)$existing['id'];
    $update = $conn->prepare("UPDATE newsletter_subscribers SET status = 'active', source = 'popup' WHERE id = ?");
    if (!$update) {
        $conn->close();
        newsletter_json_response(false, 'Could not prepare subscriber update query.', 500);
    }
    $update->bind_param('i', $subscriber_id);
    $ok = $update->execute();
    $update->close();
} else {
    $insert = $conn->prepare("INSERT INTO newsletter_subscribers (email, source, status) VALUES (?, 'popup', 'active')");
    if (!$insert) {
        $conn->close();
        newsletter_json_response(false, 'Could not prepare subscriber insert query.', 500);
    }
    $insert->bind_param('s', $email);
    $ok = $insert->execute();
    $insert->close();
}

if (!$ok) {
    $conn->close();
    newsletter_json_response(false, 'Could not save subscription. Please try again.', 500);
}

$subject = 'New Newsletter Subscription - FAYMURE';
$body = "A new newsletter subscription was received.\n\n";
$body .= "Email: {$email}\n";
$body .= "Source: Popup form\n";
$body .= "Date: " . date('Y-m-d H:i:s') . "\n";
$body .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
send_form_notification_email($subject, $body, $email);

$conn->close();

newsletter_json_response(true, 'Subscribed successfully. Thank you for joining us!');
?>
