<?php
$conn = blog_admin_conn();

$draft_count = 0;
$published_count = 0;
$scheduled_count = 0;
$r = $conn->query("SELECT status, COUNT(*) AS c FROM blog_posts GROUP BY status");
if ($r) while ($row = $r->fetch_assoc()) { ${$row['status'] . '_count'} = (int) $row['c']; }

$recent = [];
$res = $conn->query("SELECT id, title, slug, status, published_at, created_at FROM blog_posts ORDER BY updated_at DESC LIMIT 10");
if ($res) $recent = $res->fetch_all(MYSQLI_ASSOC);

$conn->close();

$page_title = 'Dashboard';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Dashboard</h1>
    <div class="blog-admin-cards">
        <div class="blog-admin-card">
            <span class="blog-admin-card-n"><?php echo $draft_count; ?></span>
            <span class="blog-admin-card-l">Drafts</span>
        </div>
        <div class="blog-admin-card">
            <span class="blog-admin-card-n"><?php echo $published_count; ?></span>
            <span class="blog-admin-card-l">Published</span>
        </div>
        <div class="blog-admin-card">
            <span class="blog-admin-card-n"><?php echo $scheduled_count; ?></span>
            <span class="blog-admin-card-l">Scheduled</span>
        </div>
    </div>
    <p><a href="<?php echo BLOG_ADMIN_BASE; ?>/post-new" class="blog-admin-btn blog-admin-btn-primary"><i class="fas fa-plus"></i> New post</a></p>
    <h2>Recent posts</h2>
    <table class="blog-admin-table">
        <thead><tr><th>Title</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
            <?php if (empty($recent)): ?>
            <tr><td colspan="4">No posts yet.</td></tr>
            <?php else: ?>
            <?php foreach ($recent as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><span class="blog-admin-badge blog-admin-badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                <td><?php echo $row['published_at'] ? date('M j, Y', strtotime($row['published_at'])) : date('M j, Y', strtotime($row['created_at'])); ?></td>
                <td><a href="<?php echo BLOG_ADMIN_BASE; ?>/post-edit/<?php echo (int)$row['id']; ?>">Edit</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
