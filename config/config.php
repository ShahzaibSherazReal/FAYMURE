<?php
// Blog admin loads this for BASE_PATH only; it uses its own session.
if (!defined('BLOG_ADMIN_LOADING')) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'FAYMURE');
define('SITE_URL', 'http://localhost/FAYMURE');
define('ADMIN_EMAIL', 'admin@faymure.com');
/** Inbox for public form submissions (quotes, contact, custom design); also stored in admin panel */
define('FORM_NOTIFICATION_EMAIL', 'info@faymure.com');

// Canonical base URL for SEO (always https://www in production). Used for <link rel="canonical">.
define('CANONICAL_BASE_URL', 'https://www.faymure.com');

// Base path for clean URLs (no trailing slash). Use '' if site is at domain root.
define('BASE_PATH', '/FAYMURE');

// Include database connection
require_once __DIR__ . '/database.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function redirect($url) {
    if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
        // Map common .php to clean path for redirects
        $clean = ['index.php' => '/', 'shop.php' => '/shop', 'cart.php' => '/cart', 'login.php' => '/login', 'logout.php' => '/logout', 'signup.php' => '/signup', 'categories.php' => '/categories', 'products.php' => '/products'];
        if (isset($clean[$url])) {
            $url = $clean[$url];
        } elseif (strpos($url, 'product-detail.php') === 0) {
            $url = '/product-detail' . (strpos($url, '?') !== false ? substr($url, strpos($url, '?')) : '');
        }
        $url = (BASE_PATH !== '' ? BASE_PATH : '') . ($url === '' || $url === '/' ? '/' : (strpos($url, '/') === 0 ? $url : '/' . $url));
    }
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Whether the blog section is hidden from the site (controlled by dashboard toggle).
 * When true, blog nav link is hidden and /blog/* returns 404.
 */
function is_blog_hidden() {
    $conn = getDBConnection();
    $r = $conn->query("SELECT content_value FROM site_content WHERE content_key = 'blog_hidden'");
    $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    $conn->close();
    return $row && isset($row['content_value']) && $row['content_value'] === '1';
}

/**
 * Generate SEO-friendly slug from a string (e.g. product name).
 * Returns lowercase alphanumeric and hyphens only; empty string becomes 'product'.
 */
function slugify($text) {
    if ($text === null || $text === '') {
        return 'product';
    }
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug === '' ? 'product' : $slug;
}

/**
 * Visitor tracking: get or set anonymous visitor code (cookie).
 */
function get_or_create_visitor_code() {
    if (!empty($_COOKIE['visitor_code'])) {
        return $_COOKIE['visitor_code'];
    }
    $code = bin2hex(random_bytes(16));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('visitor_code', $code, time() + 365 * 24 * 60 * 60, '/', '', $secure, true);
    $_COOKIE['visitor_code'] = $code;
    return $code;
}

/**
 * Get visitor country from IP using free lookup sources.
 */
function get_country_from_ip($ip) {
    // Cloudflare can provide ISO country code directly without any external API call.
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $cf_country = trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']);
        if (preg_match('/^[A-Z]{2}$/', $cf_country)) {
            return $cf_country;
        }
    }

    $ip = trim((string)$ip);
    $ip = ($ip === '::1' || $ip === '') ? '127.0.0.1' : $ip;
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'Unknown';
    }

    $ch = @curl_init();
    if (!$ch) {
        return 'Unknown';
    }

    curl_setopt($ch, CURLOPT_URL, 'https://ipwho.is/' . rawurlencode($ip));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

    $res = @curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);

    if ($res === false || $err) {
        return 'Unknown';
    }

    $data = json_decode((string)$res, true);
    if (!is_array($data) || empty($data['success'])) {
        return 'Unknown';
    }

    $country = trim((string)($data['country'] ?? ''));
    if ($country === '' || strlen($country) > 80) {
        return 'Unknown';
    }

    return $country;
}

/**
 * Link all visitor_logs for current visitor_code to this user (after login/signup).
 */
function associate_current_visitor_with_user($user_id, $username) {
    if (!$user_id) return;
    $code = get_or_create_visitor_code();
    $conn = getDBConnection();
    $t = $conn->query("SHOW TABLES LIKE 'visitor_logs'");
    if (!$t || $t->num_rows === 0) {
        $conn->close();
        return;
    }
    $stmt = $conn->prepare("UPDATE visitor_logs SET user_id = ?, user_name = ? WHERE visitor_code = ?");
    if ($stmt) {
        $stmt->bind_param('iss', $user_id, $username, $code);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

// ---- Visitor analytics helpers ----
function vt_get_cookie_value($name) {
    return isset($_COOKIE[$name]) ? (string)$_COOKIE[$name] : '';
}

function vt_set_cookie($name, $value, $days = 365) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie($name, $value, time() + ($days * 24 * 60 * 60), '/', '', $secure, true);
    $_COOKIE[$name] = $value;
}

function vt_get_or_create_guest_id() {
    $gid = vt_get_cookie_value('guest_id');
    if ($gid !== '' && preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $gid)) {
        return $gid;
    }
    $gid = 'g_' . bin2hex(random_bytes(16));
    vt_set_cookie('guest_id', $gid, 365);
    return $gid;
}

function vt_get_session_id() {
    // JS supplies a UUID session_id; server-side fallback if needed
    if (!empty($_SESSION['vt_session_id'])) return $_SESSION['vt_session_id'];
    $sid = bin2hex(random_bytes(16));
    $_SESSION['vt_session_id'] = $sid;
    return $sid;
}

function vt_link_guest_to_user($user_id) {
    if (!$user_id) return;
    $guest_id = vt_get_or_create_guest_id();
    try {
        $conn = getDBConnection();
        $t = $conn->query("SHOW TABLES LIKE 'visitor_profiles'");
        if (!$t || $t->num_rows === 0) { $conn->close(); return; }
        $stmt = $conn->prepare("UPDATE visitor_profiles SET user_id = ? WHERE guest_id = ?");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $guest_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("UPDATE visitor_sessions SET user_id = ? WHERE guest_id = ?");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $guest_id);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("UPDATE visitor_events SET user_id = ? WHERE guest_id = ? AND user_id IS NULL");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $guest_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (Throwable $e) {}
}

/**
 * Where to send site form submission notifications (plain PHP mail()).
 */
function form_notification_recipient() {
    return (defined('FORM_NOTIFICATION_EMAIL') && FORM_NOTIFICATION_EMAIL !== '')
        ? FORM_NOTIFICATION_EMAIL
        : ADMIN_EMAIL;
}

/**
 * Build absolute path under project root from a stored relative path (blocks path traversal).
 */
function form_notification_attachment_path($relative_path) {
    $relative_path = str_replace('\\', '/', (string) $relative_path);
    if ($relative_path === '' || strpos($relative_path, '..') !== false) {
        return null;
    }
    $relative_path = ltrim($relative_path, '/');
    $root = dirname(__DIR__);
    $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    $real_root = realpath($root);
    $real_file = is_file($full) ? realpath($full) : false;
    if ($real_root === false || $real_file === false) {
        return null;
    }
    if (strpos($real_file, $real_root) !== 0) {
        return null;
    }
    return $real_file;
}

/**
 * @param string      $subject      Email subject
 * @param string      $body         Plain text body
 * @param string|null $reply_to     Submitter email for Reply-To (optional)
 * @param array       $attachments  List of ['path' => absolute path, 'name' => optional filename]
 */
function send_form_notification_email($subject, $body, $reply_to = null, array $attachments = []) {
    $to = form_notification_recipient();
    $from = $to;
    $rt = $reply_to !== null && trim((string) $reply_to) !== '' ? trim((string) $reply_to) : null;

    $valid_attachments = [];
    $max_bytes = 8 * 1024 * 1024;
    foreach ($attachments as $att) {
        if (empty($att['path']) || !is_readable($att['path']) || !is_file($att['path'])) {
            continue;
        }
        $size = filesize($att['path']);
        if ($size === false || $size > $max_bytes) {
            continue;
        }
        $name = isset($att['name']) ? (string) $att['name'] : basename($att['path']);
        $name = preg_replace('/[^\x20-\x7E]/', '_', $name);
        $name = str_replace(["\r", "\n", '"'], '', $name);
        if ($name === '') {
            $name = 'attachment';
        }
        $valid_attachments[] = ['path' => $att['path'], 'name' => $name];
    }

    if (empty($valid_attachments)) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= 'From: ' . SITE_NAME . ' <' . $from . ">\r\n";
        if ($rt !== null && filter_var($rt, FILTER_VALIDATE_EMAIL)) {
            $headers .= 'Reply-To: ' . $rt . "\r\n";
        }
        @mail($to, $subject, $body, $headers);
        return;
    }

    $boundary = 'bnd_' . bin2hex(random_bytes(12));
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= 'From: ' . SITE_NAME . ' <' . $from . ">\r\n";
    if ($rt !== null && filter_var($rt, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . $rt . "\r\n";
    }
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n", "\r\n", $body);

    $msg = "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $msg .= $body . "\r\n";

    foreach ($valid_attachments as $att) {
        $path = $att['path'];
        $filename = $att['name'];
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if (is_string($m) && $m !== '') {
                $mime = $m;
            }
        } elseif (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $m = finfo_file($f, $path);
                finfo_close($f);
                if (is_string($m) && $m !== '') {
                    $mime = $m;
                }
            }
        }
        $mime = preg_replace('/[\r\n]/', '', $mime);

        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: {$mime}; name=\"{$filename}\"\r\n";
        $msg .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($content)) . "\r\n";
    }
    $msg .= "--{$boundary}--\r\n";

    @mail($to, $subject, $msg, $headers);
}

/**
 * Send newsletter email directly to a subscriber.
 * Returns true when mail() accepts the message for delivery.
 */
function send_newsletter_email($to, $subject, $body) {
    $to = trim((string) $to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = form_notification_recipient();
    $subj = trim((string) $subject);
    if ($subj === '') {
        $subj = SITE_NAME . ' Newsletter';
    }
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= 'From: ' . SITE_NAME . ' <' . $from . ">\r\n";
    return (bool) @mail($to, $subj, (string) $body, $headers);
}
?>

