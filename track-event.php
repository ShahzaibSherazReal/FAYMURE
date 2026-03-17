<?php
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// Lightweight abuse control: allow only same-origin requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($host && $originHost && strcasecmp($host, $originHost) !== 0) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden_origin']);
        exit;
    }
}

// Parse JSON or form-encoded
$raw = file_get_contents('php://input');
$data = null;
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $data = $decoded;
}
if ($data === null) {
    $data = $_POST;
}

function s($v, $max = 500) {
    $v = is_string($v) ? trim($v) : '';
    if (strlen($v) > $max) $v = substr($v, 0, $max);
    return $v;
}

$guest_id = s($data['guest_id'] ?? '', 64);
$session_id = s($data['session_id'] ?? '', 36);
$event_type = s($data['event_type'] ?? '', 50);
$page_url = s($data['page_url'] ?? '', 500);
$page_path = s($data['page_path'] ?? '', 500);
$referrer = s($data['referrer'] ?? '', 500);
$user_agent = s($_SERVER['HTTP_USER_AGENT'] ?? '', 500);
$ip = s($_SERVER['REMOTE_ADDR'] ?? '', 45);
$duration = (int)($data['duration_seconds'] ?? 0);
if ($duration < 0) $duration = 0;
if ($duration > 24 * 60 * 60) $duration = 24 * 60 * 60;

$product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
$category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
$search_term = s($data['search_term'] ?? '', 255);
$button_name = s($data['button_name'] ?? '', 100);

$metadata = $data['metadata'] ?? null;
if (is_string($metadata)) {
    $tmp = json_decode($metadata, true);
    if (is_array($tmp)) $metadata = $tmp;
}
if (!is_array($metadata)) $metadata = null;

// Require minimal fields
$allowed = [
    'page_view','time_on_page','product_view','category_view','search',
    'add_to_cart','remove_from_cart','update_cart','checkout_started','checkout_completed',
    'contact_submit','login','signup','logout','button_click'
];
if ($guest_id === '' || $event_type === '' || !in_array($event_type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

// Simple duplicate suppression: same guest+session+event+page within 2 seconds
$now = new DateTimeImmutable('now');
$created_at = $now->format('Y-m-d H:i:s');

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

try {
    $conn = getDBConnection();

    // Ensure tables exist (setup-database creates them; if missing, fail gracefully)
    $t = $conn->query("SHOW TABLES LIKE 'visitor_events'");
    if (!$t || $t->num_rows === 0) {
        $conn->close();
        echo json_encode(['ok' => true, 'skipped' => true]);
        exit;
    }

    // Upsert profile
    $p = $conn->prepare("INSERT INTO visitor_profiles (guest_id, user_id, first_seen_at, last_seen_at, first_ip, last_ip, first_user_agent, last_user_agent, referrer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            user_id = IF(VALUES(user_id) IS NOT NULL, VALUES(user_id), user_id),
            last_seen_at = VALUES(last_seen_at),
            last_ip = VALUES(last_ip),
            last_user_agent = VALUES(last_user_agent),
            referrer = IF(referrer IS NULL OR referrer = '', VALUES(referrer), referrer)");
    if ($p) {
        $p->bind_param('sisssssss', $guest_id, $user_id, $created_at, $created_at, $ip, $ip, $user_agent, $user_agent, $referrer);
        $p->execute();
        $p->close();
    }

    // Session upsert
    if ($session_id !== '') {
        $sstmt = $conn->prepare("INSERT INTO visitor_sessions (session_id, guest_id, user_id, started_at, last_seen_at, referrer, landing_url, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = IF(VALUES(user_id) IS NOT NULL, VALUES(user_id), user_id),
                last_seen_at = VALUES(last_seen_at),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent)");
        if ($sstmt) {
            $landing = $page_url ?: $page_path;
            $sstmt->bind_param('ssissssss', $session_id, $guest_id, $user_id, $created_at, $created_at, $referrer, $landing, $ip, $user_agent);
            $sstmt->execute();
            $sstmt->close();
        }
    }

    // Duplicate suppression
    $dup = $conn->prepare("SELECT id FROM visitor_events
        WHERE guest_id = ? AND (session_id <=> ?) AND event_type = ? AND (page_path <=> ?) AND created_at >= (NOW() - INTERVAL 2 SECOND)
        ORDER BY id DESC LIMIT 1");
    if ($dup) {
        $dup->bind_param('ssss', $guest_id, $session_id, $event_type, $page_path);
        $dup->execute();
        $exists = $dup->get_result()->num_rows > 0;
        $dup->close();
        if ($exists) {
            $conn->close();
            echo json_encode(['ok' => true, 'deduped' => true]);
            exit;
        }
    }

    $meta_json = $metadata ? json_encode($metadata) : null;
    $stmt = $conn->prepare("INSERT INTO visitor_events
        (guest_id, user_id, session_id, event_type, page_url, page_path, product_id, category_id, search_term, button_name, referrer, ip_address, user_agent, duration_seconds, metadata_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param(
            'sissssiiissssiss',
            $guest_id,
            $user_id,
            $session_id,
            $event_type,
            $page_url,
            $page_path,
            $product_id,
            $category_id,
            $search_term,
            $button_name,
            $referrer,
            $ip,
            $user_agent,
            $duration,
            $meta_json,
            $created_at
        );
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();
} catch (Throwable $e) {
    // never break frontend
}

echo json_encode(['ok' => true]);

