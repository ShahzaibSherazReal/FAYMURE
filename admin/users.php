<?php
require_once 'check-auth.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $id = intval($_POST['id']);
    if ($id != $_SESSION['user_id']) {
        $conn->query("UPDATE users SET deleted_at = NOW() WHERE id = $id");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore'])) {
    $id = intval($_POST['id']);
    if ($id > 0) {
        $conn->query("UPDATE users SET deleted_at = NULL WHERE id = $id");
    }
}

$users_sql = "SELECT
                u.*,
                COALESCE(v.total_events, 0) AS total_events,
                v.last_activity
              FROM users u
              LEFT JOIN (
                SELECT user_id, COUNT(*) AS total_events, MAX(created_at) AS last_activity
                FROM visitor_events
                WHERE user_id IS NOT NULL
                GROUP BY user_id
              ) v ON v.user_id = u.id
              ORDER BY u.created_at DESC";
$users_result = $conn->query($users_sql);
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <h1>Users</h1>
            
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Admin</th>
                            <th>Status</th>
                            <th>Events</th>
                            <th>Last Activity</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['is_admin'] ? '<span class="badge">Yes</span>' : 'No'; ?></td>
                                    <td>
                                        <?php if (!empty($user['deleted_at'])): ?>
                                            <span class="badge" style="background:#8b96a6;">Deleted</span>
                                        <?php else: ?>
                                            <span class="badge">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)($user['total_events'] ?? 0); ?></td>
                                    <td><?php echo !empty($user['last_activity']) ? date('M d, Y H:i', strtotime($user['last_activity'])) : '—'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="actions">
                                        <button type="button"
                                            class="btn-view btn-icon-small js-user-activity"
                                            data-user-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            title="View user activity">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id'] && empty($user['deleted_at'])): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete" class="btn-delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php elseif (!empty($user['deleted_at'])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="restore" class="btn-view btn-icon-small" title="Restore user"><i class="fas fa-undo"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="userActivityModal" class="modal">
        <div class="modal-content">
            <span class="close" id="userActivityClose">&times;</span>
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
                <h2 id="userActivityTitle" style="margin:0;">User Activity</h2>
                <button type="button" id="userActivityRefresh" class="btn-secondary" style="display:none;"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            <iframe id="userActivityFrame" src="" style="width:100%; height:70vh; border:none; border-radius:12px; background:#f6f5f3;"></iframe>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('userActivityModal');
        var closeBtn = document.getElementById('userActivityClose');
        var frame = document.getElementById('userActivityFrame');
        var titleEl = document.getElementById('userActivityTitle');
        var refreshBtn = document.getElementById('userActivityRefresh');
        var buttons = document.querySelectorAll('.js-user-activity');
        var currentUserId = null;

        function openModal(userId, username) {
            if (!modal || !frame) return;
            currentUserId = userId;
            titleEl.textContent = 'User Activity - ' + (username || ('User #' + userId));
            frame.src = '';
            frame.src = 'user-logs.php?user_id=' + encodeURIComponent(userId) + '&_=' + Date.now();
            modal.style.display = 'block';
            if (refreshBtn) refreshBtn.style.display = 'inline-flex';
        }

        function refreshFrame() {
            if (frame && currentUserId) {
                frame.src = 'user-logs.php?user_id=' + encodeURIComponent(currentUserId) + '&_=' + Date.now();
            }
        }

        function closeModal() {
            if (!modal || !frame) return;
            modal.style.display = 'none';
            frame.src = '';
            currentUserId = null;
            if (refreshBtn) refreshBtn.style.display = 'none';
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshFrame);
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var userId = this.getAttribute('data-user-id');
                var username = this.getAttribute('data-username');
                openModal(userId, username);
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                closeModal();
            }
        });
    });
    </script>
</body>
</html>

