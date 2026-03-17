<?php
require_once 'check-auth.php';

if (!isAdmin()) {
    redirect('dashboard.php');
}

$conn = getDBConnection();

// If tables missing, show friendly message
$hasEvents = false;
$t = $conn->query("SHOW TABLES LIKE 'visitor_events'");
if ($t && $t->num_rows > 0) $hasEvents = true;

$today = date('Y-m-d');
$from = isset($_GET['from']) ? $_GET['from'] : $today;
$to = isset($_GET['to']) ? $_GET['to'] : $today;
$event_type = isset($_GET['event_type']) ? trim($_GET['event_type']) : '';
$visitor_kind = isset($_GET['visitor_kind']) ? trim($_GET['visitor_kind']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$summary = [
    'total_visitors_today' => 0,
    'anonymous_visitors_today' => 0,
    'logged_in_visitors_today' => 0,
    'product_views_today' => 0,
    'add_to_cart_today' => 0,
    'searches_today' => 0,
    'checkout_started_today' => 0,
    'checkout_completed_today' => 0,
];

$events = [];
$totalRows = 0;
$totalPages = 1;

if ($hasEvents) {
    // Summary for today
    $r = $conn->query("SELECT 
        COUNT(DISTINCT guest_id) AS total_visitors,
        COUNT(DISTINCT CASE WHEN user_id IS NULL THEN guest_id END) AS anonymous_visitors,
        COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN guest_id END) AS logged_in_visitors
        FROM visitor_events
        WHERE DATE(created_at) = CURDATE()");
    if ($r) {
        $row = $r->fetch_assoc();
        $summary['total_visitors_today'] = (int)$row['total_visitors'];
        $summary['anonymous_visitors_today'] = (int)$row['anonymous_visitors'];
        $summary['logged_in_visitors_today'] = (int)$row['logged_in_visitors'];
    }

    $map = [
        'product_view' => 'product_views_today',
        'add_to_cart' => 'add_to_cart_today',
        'search' => 'searches_today',
        'checkout_started' => 'checkout_started_today',
        'checkout_completed' => 'checkout_completed_today',
    ];
    foreach ($map as $etype => $key) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM visitor_events WHERE event_type = '" . $conn->real_escape_string($etype) . "' AND DATE(created_at) = CURDATE()");
        if ($r) $summary[$key] = (int)$r->fetch_assoc()['c'];
    }

    // Filters
    $filters = [];
    $params = [];
    $types = '';

    // Date range inclusive
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) {
        $filters[] = "created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $from . " 00:00:00";
        $params[] = $to . " 00:00:00";
        $types .= 'ss';
    }
    if ($event_type !== '') {
        $filters[] = "event_type = ?";
        $params[] = $event_type;
        $types .= 's';
    }
    if ($visitor_kind === 'guest') {
        $filters[] = "user_id IS NULL";
    } elseif ($visitor_kind === 'user') {
        $filters[] = "user_id IS NOT NULL";
    }
    if ($q !== '') {
        $filters[] = "(guest_id LIKE CONCAT('%', ?, '%') OR session_id LIKE CONCAT('%', ?, '%') OR page_path LIKE CONCAT('%', ?, '%') OR search_term LIKE CONCAT('%', ?, '%'))";
        $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        $types .= 'ssss';
    }

    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    // Count
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM visitor_events $where");
    if ($stmt) {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $totalRows = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
    }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // Fetch
    $sql = "SELECT id, guest_id, user_id, session_id, event_type, page_path, product_id, category_id, search_term, button_name, ip_address, user_agent, created_at
            FROM visitor_events
            $where
            ORDER BY id DESC
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params2 = $params;
        $types2 = $types . 'ii';
        $params2[] = $offset;
        $params2[] = $perPage;
        $stmt->bind_param($types2, ...$params2);
        $stmt->execute();
        $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$conn->close();
$base = defined('BASE_PATH') ? BASE_PATH : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Visitor Activity Analytics - Admin - <?php echo SITE_NAME; ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    .summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 18px; }
    .summary-card { background:#fff; border:1px solid rgba(0,31,63,0.08); border-radius:12px; padding:14px 16px; }
    .summary-card .k { font-size:11px; text-transform:uppercase; color: var(--text-secondary); letter-spacing:0.04em; }
    .summary-card .v { font-size:22px; font-weight:600; color: var(--primary-color); margin-top:6px; }
    .filters { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin: 12px 0 16px; }
    .filters label { font-size: 12px; color: var(--text-secondary); display:block; margin-bottom:4px; }
    .filters input, .filters select { padding: 10px 12px; border: 1px solid rgba(0,31,63,0.12); border-radius: 8px; }
    .pill { font-size:11px; padding:2px 8px; border-radius:999px; background: rgba(0,31,63,0.08); color: var(--primary-color); display:inline-block; }
    .ua { max-width: 340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block; color: var(--text-secondary); font-size: 12px; }
  </style>
</head>
<body>
<?php include 'includes/admin-header.php'; ?>

<main class="admin-main">
  <div class="admin-container">
    <div class="page-header">
      <h1>Visitor Activity Analytics</h1>
    </div>

    <?php if (!$hasEvents): ?>
      <p>Tracking tables not found. Run <strong>setup-database</strong> once to create them.</p>
    <?php else: ?>
      <div class="summary-grid">
        <div class="summary-card"><div class="k">Total visitors today</div><div class="v"><?php echo $summary['total_visitors_today']; ?></div></div>
        <div class="summary-card"><div class="k">Anonymous visitors today</div><div class="v"><?php echo $summary['anonymous_visitors_today']; ?></div></div>
        <div class="summary-card"><div class="k">Logged-in visitors today</div><div class="v"><?php echo $summary['logged_in_visitors_today']; ?></div></div>
        <div class="summary-card"><div class="k">Product views today</div><div class="v"><?php echo $summary['product_views_today']; ?></div></div>
        <div class="summary-card"><div class="k">Add to cart today</div><div class="v"><?php echo $summary['add_to_cart_today']; ?></div></div>
        <div class="summary-card"><div class="k">Searches today</div><div class="v"><?php echo $summary['searches_today']; ?></div></div>
        <div class="summary-card"><div class="k">Checkout started today</div><div class="v"><?php echo $summary['checkout_started_today']; ?></div></div>
        <div class="summary-card"><div class="k">Checkout completed today</div><div class="v"><?php echo $summary['checkout_completed_today']; ?></div></div>
      </div>

      <form method="GET" class="filters">
        <div>
          <label>From</label>
          <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div>
          <label>To</label>
          <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div>
          <label>Event type</label>
          <select name="event_type">
            <option value="">All</option>
            <?php foreach (['page_view','time_on_page','product_view','category_view','search','add_to_cart','remove_from_cart','update_cart','checkout_started','checkout_completed','contact_submit','login','signup','logout','button_click'] as $et): ?>
              <option value="<?php echo $et; ?>" <?php echo $event_type === $et ? 'selected' : ''; ?>><?php echo $et; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Visitor</label>
          <select name="visitor_kind">
            <option value="" <?php echo $visitor_kind === '' ? 'selected' : ''; ?>>All</option>
            <option value="guest" <?php echo $visitor_kind === 'guest' ? 'selected' : ''; ?>>Guest</option>
            <option value="user" <?php echo $visitor_kind === 'user' ? 'selected' : ''; ?>>Logged-in</option>
          </select>
        </div>
        <div style="flex:1; min-width:220px;">
          <label>Search</label>
          <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="guest_id, session_id, URL, search term">
        </div>
        <button type="submit" class="btn-primary">Apply</button>
      </form>

      <div class="table-container">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Visitor</th>
              <th>Session</th>
              <th>Event</th>
              <th>Page</th>
              <th>Info</th>
              <th>IP</th>
              <th>User agent</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($events)): ?>
            <tr><td colspan="8">No events found.</td></tr>
          <?php else: ?>
            <?php foreach ($events as $e): ?>
              <tr>
                <td>
                  <span class="pill"><?php echo $e['user_id'] ? 'user' : 'guest'; ?></span><br>
                  <a href="<?php echo $base; ?>/admin/visitor-analytics-detail?guest_id=<?php echo urlencode($e['guest_id']); ?>" style="text-decoration:none;">
                    <?php echo htmlspecialchars($e['guest_id']); ?>
                  </a>
                  <?php if ($e['user_id']): ?><br><small>User #<?php echo (int)$e['user_id']; ?></small><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($e['session_id'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($e['event_type']); ?></td>
                <td><?php echo htmlspecialchars($e['page_path'] ?: '—'); ?></td>
                <td>
                  <?php if (!empty($e['product_id'])): ?>Product #<?php echo (int)$e['product_id']; ?><br><?php endif; ?>
                  <?php if (!empty($e['category_id'])): ?>Category #<?php echo (int)$e['category_id']; ?><br><?php endif; ?>
                  <?php if (!empty($e['search_term'])): ?>Search: <?php echo htmlspecialchars($e['search_term']); ?><br><?php endif; ?>
                  <?php if (!empty($e['button_name'])): ?>Btn: <?php echo htmlspecialchars($e['button_name']); ?><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($e['ip_address'] ?: ''); ?></td>
                <td><span class="ua" title="<?php echo htmlspecialchars($e['user_agent'] ?: ''); ?>"><?php echo htmlspecialchars($e['user_agent'] ?: ''); ?></span></td>
                <td><?php echo htmlspecialchars($e['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
          <?php for ($p=1; $p<=$totalPages; $p++): ?>
            <?php $qs = $_GET; $qs['page'] = $p; ?>
            <a class="btn-secondary<?php echo $p===$page ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($qs)); ?>"><?php echo $p; ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
</body>
</html>

