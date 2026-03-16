<?php
require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$visitor_code = get_or_create_visitor_code();
$page_path = isset($_POST['page']) ? sanitize($_POST['page']) : '/';
$duration = isset($_POST['duration']) ? max(0, (int) $_POST['duration']) : 0;

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$country = get_country_from_ip($ip);

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

$now = date('Y-m-d H:i:s');

try {
    $conn = getDBConnection();
    $t = $conn->query("SHOW TABLES LIKE 'visitor_logs'");
    if (!$t || $t->num_rows === 0) {
        $conn->close();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO visitor_logs (visitor_code, user_id, user_name, country, page_path, visit_count, total_time_seconds, last_duration_seconds, first_visited_at, last_visited_at)
        VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            visit_count = visit_count + 1,
            total_time_seconds = total_time_seconds + VALUES(total_time_seconds),
            last_duration_seconds = VALUES(last_duration_seconds),
            country = IF(VALUES(country) IS NOT NULL AND VALUES(country) != '', VALUES(country), country),
            user_id = IF(VALUES(user_id) IS NOT NULL, VALUES(user_id), user_id),
            user_name = IF(VALUES(user_name) IS NOT NULL AND VALUES(user_name) != '', VALUES(user_name), user_name),
            last_visited_at = VALUES(last_visited_at)
    ");
    if ($stmt) {
        $stmt->bind_param('sisssiiss', $visitor_code, $user_id, $username, $country, $page_path, $duration, $duration, $now, $now);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
} catch (Throwable $e) {
    // silent
}

header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
