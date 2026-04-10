<?php
require_once 'check-auth.php';

if (!isAdmin()) {
    redirect('dashboard.php');
}

$guest_id = isset($_GET['guest_id']) ? trim($_GET['guest_id']) : '';
if ($guest_id === '') {
    redirect('visitor-analytics.php');
}

$conn = getDBConnection();
$hasEvents = false;
$t = $conn->query("SHOW TABLES LIKE 'visitor_events'");
if ($t && $t->num_rows > 0) $hasEvents = true;

$profile = null;
$sessions = [];
$events = [];
$visitor_country = 'Unknown';

if ($hasEvents) {
    $stmt = $conn->prepare("SELECT * FROM visitor_profiles WHERE guest_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $guest_id);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT session_id, started_at, last_seen_at, landing_url, referrer, ip_address, user_agent FROM visitor_sessions WHERE guest_id = ? ORDER BY last_seen_at DESC LIMIT 30");
    if ($stmt) {
        $stmt->bind_param('s', $guest_id);
        $stmt->execute();
        $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Determine visitor country from best available IP (profile last_ip -> first_ip -> latest session ip)
    $country_ip = '';
    if (!empty($profile['last_ip'])) {
        $country_ip = (string)$profile['last_ip'];
    } elseif (!empty($profile['first_ip'])) {
        $country_ip = (string)$profile['first_ip'];
    } elseif (!empty($sessions) && !empty($sessions[0]['ip_address'])) {
        $country_ip = (string)$sessions[0]['ip_address'];
    }
    if ($country_ip !== '') {
        $visitor_country = get_country_from_ip($country_ip);
        if (!is_string($visitor_country) || trim($visitor_country) === '') {
            $visitor_country = 'Unknown';
        }
    }

    $stmt = $conn->prepare("SELECT id, session_id, event_type, page_path, product_id, category_id, search_term, button_name, duration_seconds, created_at
        FROM visitor_events WHERE guest_id = ? ORDER BY id DESC LIMIT 400");
    if ($stmt) {
        $stmt->bind_param('s', $guest_id);
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
  <title>Visitor Detail - Admin - <?php echo SITE_NAME; ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    .card { background:#fff; border:1px solid rgba(0,31,63,0.08); border-radius:12px; padding:14px 16px; }
    .k { font-size:11px; text-transform:uppercase; color: var(--text-secondary); letter-spacing:0.04em; }
    .v { font-size:14px; margin-top:4px; }
    .timeline { max-height: 520px; overflow:auto; }
    .row { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid rgba(0,0,0,0.05); }
    .row:last-child { border-bottom:none; }
    .evt { font-weight:600; color: var(--primary-color); }
    .meta { color: var(--text-secondary); font-size: 12px; }
  </style>
</head>
<body>
<?php include 'includes/admin-header.php'; ?>

<main class="admin-main">
  <div class="admin-container">
    <div class="page-header">
      <h1>Visitor Detail</h1>
      <a class="btn-secondary" href="<?php echo $base; ?>/admin/visitor-analytics"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (!$hasEvents): ?>
      <p>Tracking tables not found. Run <strong>setup-database</strong> once.</p>
    <?php else: ?>
      <div class="grid">
        <div class="card">
          <div class="k">Guest ID</div>
          <div class="v"><strong><?php echo htmlspecialchars($guest_id); ?></strong></div>
          <div style="margin-top:10px;"></div>
          <div class="k">Linked user</div>
          <div class="v"><?php echo !empty($profile['user_id']) ? ('User #' . (int)$profile['user_id']) : '—'; ?></div>
          <div style="margin-top:10px;"></div>
          <div class="k">First seen</div>
          <div class="v"><?php echo htmlspecialchars($profile['first_seen_at'] ?? '—'); ?></div>
          <div style="margin-top:10px;"></div>
          <div class="k">Last seen</div>
          <div class="v"><?php echo htmlspecialchars($profile['last_seen_at'] ?? '—'); ?></div>
          <div style="margin-top:10px;"></div>
          <div class="k">Country</div>
          <div class="v"><?php echo htmlspecialchars($visitor_country); ?></div>
        </div>
        <div class="card">
          <div class="k">Event breakdown (last 400)</div>
          <canvas id="evtChart" height="160"></canvas>
        </div>
      </div>

      <div class="grid" style="margin-top:16px;">
        <div class="card">
          <div class="k">Recent sessions</div>
          <?php if (empty($sessions)): ?>
            <p>No sessions.</p>
          <?php else: ?>
            <div class="table-container" style="margin-top:10px;">
              <table class="admin-table">
                <thead><tr><th>Session</th><th>Started</th><th>Last seen</th><th>Landing</th></tr></thead>
                <tbody>
                  <?php foreach ($sessions as $s): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($s['session_id']); ?></td>
                      <td><?php echo htmlspecialchars($s['started_at']); ?></td>
                      <td><?php echo htmlspecialchars($s['last_seen_at']); ?></td>
                      <td><?php echo htmlspecialchars($s['landing_url'] ?: ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <div class="card timeline">
          <div class="k">Activity timeline (newest first)</div>
          <?php if (empty($events)): ?>
            <p>No events.</p>
          <?php else: ?>
            <?php foreach ($events as $e): ?>
              <div class="row">
                <div style="min-width:120px;" class="meta"><?php echo htmlspecialchars($e['created_at']); ?></div>
                <div style="flex:1;">
                  <div class="evt"><?php echo htmlspecialchars($e['event_type']); ?></div>
                  <div class="meta">
                    <?php if (!empty($e['page_path'])): ?><?php echo htmlspecialchars($e['page_path']); ?><?php endif; ?>
                    <?php if (!empty($e['product_id'])): ?> · Product #<?php echo (int)$e['product_id']; ?><?php endif; ?>
                    <?php if (!empty($e['category_id'])): ?> · Category #<?php echo (int)$e['category_id']; ?><?php endif; ?>
                    <?php if (!empty($e['search_term'])): ?> · Search: <?php echo htmlspecialchars($e['search_term']); ?><?php endif; ?>
                    <?php if (!empty($e['button_name'])): ?> · Button: <?php echo htmlspecialchars($e['button_name']); ?><?php endif; ?>
                    <?php if (!empty($e['duration_seconds'])): ?> · \(<?php echo (int)$e['duration_seconds']; ?>s\)<?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <script>
      (function() {
        var counts = {};
        <?php foreach ($events as $e): ?>
          counts[<?php echo json_encode($e['event_type']); ?>] = (counts[<?php echo json_encode($e['event_type']); ?>] || 0) + 1;
        <?php endforeach; ?>
        var labels = Object.keys(counts);
        var data = labels.map(function(k){ return counts[k]; });
        var ctx = document.getElementById('evtChart');
        if (!ctx) return;
        new Chart(ctx, {
          type: 'bar',
          data: { labels: labels, datasets: [{ label: 'Events', data: data, backgroundColor: 'rgba(0,31,63,0.7)' }]},
          options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { autoSkip: false } } } }
        });
      })();
      </script>
    <?php endif; ?>
  </div>
</main>
</body>
</html>

