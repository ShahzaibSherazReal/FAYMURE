<?php
require_once 'check-auth.php';

if (!isAdmin()) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/admin/dashboard');
    exit;
}

if (!isset($_GET['user_id']) || !ctype_digit((string)$_GET['user_id'])) {
    header('Location: users.php');
    exit;
}

$userId = (int) $_GET['user_id'];

$conn = getDBConnection();

$userStmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE id = ? AND deleted_at IS NULL");
if (!$userStmt) {
    $conn->close();
    header('Location: users.php');
    exit;
}
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
    $conn->close();
    header('Location: users.php');
    exit;
}

$hasVisitorLogs = false;
$check = @$conn->query("SHOW TABLES LIKE 'visitor_logs'");
if ($check && $check->num_rows > 0) {
    $hasVisitorLogs = true;
}

$logs = [];
$summary = [
    'total_visits' => 0,
    'total_time_seconds' => 0,
    'first_seen' => null,
    'last_seen' => null
];

if ($hasVisitorLogs) {
    $sumStmt = $conn->prepare("
        SELECT
            COALESCE(SUM(visit_count), 0) AS total_visits,
            COALESCE(SUM(total_time_seconds), 0) AS total_time_seconds,
            MIN(first_visited_at) AS first_seen,
            MAX(last_visited_at) AS last_seen
        FROM visitor_logs
        WHERE user_id = ?
    ");
    if ($sumStmt) {
        $sumStmt->bind_param('i', $userId);
        $sumStmt->execute();
        $sumRes = $sumStmt->get_result()->fetch_assoc();
        $sumStmt->close();
        if ($sumRes) {
            $summary['total_visits'] = (int) $sumRes['total_visits'];
            $summary['total_time_seconds'] = (int) $sumRes['total_time_seconds'];
            $summary['first_seen'] = $sumRes['first_seen'];
            $summary['last_seen'] = $sumRes['last_seen'];
        }
    }

    $logStmt = $conn->prepare("
        SELECT page_path, country, visit_count, total_time_seconds, last_duration_seconds, first_visited_at, last_visited_at
        FROM visitor_logs
        WHERE user_id = ?
        ORDER BY last_visited_at DESC
        LIMIT 500
    ");
    if ($logStmt) {
        $logStmt->bind_param('i', $userId);
        $logStmt->execute();
        $logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $logStmt->close();
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
    <title>User Activity - <?php echo htmlspecialchars($user['username']); ?> - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container" style="padding: 20px;">
        <div style="margin-bottom: 16px;">
            <h1 style="margin: 0 0 4px 0;">User Activity</h1>
            <p style="margin: 0; color: var(--text-secondary);">
                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                — <?php echo htmlspecialchars($user['email']); ?>
            </p>
        </div>

        <?php if (!$hasVisitorLogs): ?>
            <p>Activity tracking is not set up on this server. Run <strong>setup-database</strong> once (e.g. yoursite.com/setup-database) to create the tracking table, then have users browse the site while logged in.</p>
        <?php elseif ($summary['total_visits'] == 0 && empty($logs)): ?>
            <p>No activity for this user yet. Activity is recorded when they browse the site while logged in. Ensure <strong>track-visit.php</strong> and the tracking script in <strong>main.js</strong> are deployed, and that <code>BASE_PATH</code> in config matches your live URL (use <code>''</code> if the site is at the domain root).</p>
        <?php else: ?>
            <section style="margin-bottom: 20px;">
                <h2 style="font-size: 1rem; margin-bottom: 8px;">Summary</h2>
                <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    <div class="summary-card" style="padding: 12px 16px; background: #fff; border-radius: 8px; border: 1px solid rgba(0,31,63,0.08); min-width: 120px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary);">Total Visits</div>
                        <div style="font-size: 1.25rem; font-weight: 600;"><?php echo $summary['total_visits']; ?></div>
                    </div>
                    <div class="summary-card" style="padding: 12px 16px; background: #fff; border-radius: 8px; border: 1px solid rgba(0,31,63,0.08); min-width: 120px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary);">Total Time (min)</div>
                        <div style="font-size: 1.25rem; font-weight: 600;"><?php echo round($summary['total_time_seconds'] / 60, 1); ?></div>
                    </div>
                    <div class="summary-card" style="padding: 12px 16px; background: #fff; border-radius: 8px; border: 1px solid rgba(0,31,63,0.08); min-width: 140px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary);">First Seen</div>
                        <div style="font-size: 0.9rem;"><?php echo $summary['first_seen'] ? date('Y-m-d H:i', strtotime($summary['first_seen'])) : '-'; ?></div>
                    </div>
                    <div class="summary-card" style="padding: 12px 16px; background: #fff; border-radius: 8px; border: 1px solid rgba(0,31,63,0.08); min-width: 140px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary);">Last Seen</div>
                        <div style="font-size: 0.9rem;"><?php echo $summary['last_seen'] ? date('Y-m-d H:i', strtotime($summary['last_seen'])) : '-'; ?></div>
                    </div>
                </div>
            </section>

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Country</th>
                            <th>Visits</th>
                            <th>Total Time (min)</th>
                            <th>Last (s)</th>
                            <th>First Visited</th>
                            <th>Last Visited</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7">No activity recorded for this user yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['page_path']); ?></td>
                                    <td><?php echo htmlspecialchars($log['country'] ?: 'Unknown'); ?></td>
                                    <td><?php echo (int) $log['visit_count']; ?></td>
                                    <td><?php echo round((int) $log['total_time_seconds'] / 60, 1); ?></td>
                                    <td><?php echo (int) $log['last_duration_seconds']; ?></td>
                                    <td><?php echo $log['first_visited_at'] ? date('Y-m-d H:i', strtotime($log['first_visited_at'])) : '-'; ?></td>
                                    <td><?php echo $log['last_visited_at'] ? date('Y-m-d H:i', strtotime($log['last_visited_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
