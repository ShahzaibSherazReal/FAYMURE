<?php
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page_num - 1) * $per_page;
$total = (int) $conn->query("SELECT COUNT(*) AS c FROM blog_admin_audit_log")->fetch_assoc()['c'];
$total_pages = (int) ceil($total / $per_page);
$rows = $conn->query("SELECT * FROM blog_admin_audit_log ORDER BY created_at DESC LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
$conn->close();
$page_title = 'Audit log';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Audit log</h1>
    <table class="blog-admin-table">
        <thead>
            <tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>ID</th><th>Details</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td><?php echo htmlspecialchars($r['username'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($r['action']); ?></td>
                <td><?php echo htmlspecialchars($r['entity_type']); ?></td>
                <td><?php echo (int)$r['entity_id']; ?></td>
                <td><?php echo htmlspecialchars(mb_substr($r['new_value'] ?? '', 0, 80)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($total_pages > 1): ?>
    <nav class="blog-admin-pagination">
        <?php if ($page_num > 1): ?><a href="?page=<?php echo $page_num - 1; ?>">Previous</a><?php endif; ?>
        <span>Page <?php echo $page_num; ?> of <?php echo $total_pages; ?></span>
        <?php if ($page_num < $total_pages): ?><a href="?page=<?php echo $page_num + 1; ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../views/layout.php';
