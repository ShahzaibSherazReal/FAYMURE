<?php
require_once 'config/config.php';

// best-effort logout event
if (function_exists('vt_get_or_create_guest_id')) {
    // send via backend insert to visitor_events
    try {
        $gid = vt_get_or_create_guest_id();
        $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $conn = getDBConnection();
        $t = $conn->query("SHOW TABLES LIKE 'visitor_events'");
        if ($t && $t->num_rows > 0) {
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
            $sid = isset($_SESSION['vt_session_id']) ? (string)$_SESSION['vt_session_id'] : null;
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO visitor_events (guest_id, user_id, session_id, event_type, page_url, page_path, referrer, ip_address, user_agent, duration_seconds, created_at)
                VALUES (?, ?, ?, 'logout', ?, ?, ?, ?, ?, 0, ?)");
            if ($stmt) {
                $page_url = (defined('SITE_URL') ? SITE_URL : '') . (defined('BASE_PATH') ? BASE_PATH : '') . '/logout';
                $page_path = '/logout';
                $ref = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
                $stmt->bind_param('sisssssss', $gid, $uid, $sid, $page_url, $page_path, $ref, $ip, $ua, $now);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->close();
    } catch (Throwable $e) {}
}

session_destroy();
redirect('index.php');
?>

