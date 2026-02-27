<?php
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && blog_admin_validate_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && trim($_POST['name'] ?? '') !== '') {
        $name = trim($_POST['name']);
        $slug = blog_admin_slugify($_POST['slug'] ?? $name) ?: 'tag-' . time();
        $stmt = $conn->prepare("INSERT INTO blog_tags (slug, name) VALUES (?, ?)");
        $stmt->bind_param('ss', $slug, $name);
        $stmt->execute();
        blog_admin_audit($conn, 'create', 'tag', $conn->insert_id, null, $name);
        $_SESSION['blog_admin_flash_success'] = 'Tag added.';
        blog_admin_redirect('/tags');
    }
    if ($action === 'delete' && (int)($_POST['id'] ?? 0) > 0) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM blog_tags WHERE id = $id");
        blog_admin_audit($conn, 'delete', 'tag', $id, null, null);
        $_SESSION['blog_admin_flash_success'] = 'Tag deleted.';
        blog_admin_redirect('/tags');
    }
}

$tags = $conn->query("SELECT t.*, (SELECT COUNT(*) FROM blog_post_tags WHERE tag_id = t.id) AS post_count FROM blog_tags t ORDER BY t.name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
$flash_success = $_SESSION['blog_admin_flash_success'] ?? ''; unset($_SESSION['blog_admin_flash_success']);
$page_title = 'Tags';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Tags</h1>
    <?php if ($flash_success): ?><div class="blog-admin-alert blog-admin-alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
    <form method="post" class="form-group" style="max-width:400px; display:flex; gap:0.5rem; align-items:flex-end;">
        <?php echo blog_admin_csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div><label>Name</label><input type="text" name="name" required></div>
        <div><label>Slug</label><input type="text" name="slug" placeholder="auto"></div>
        <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Add tag</button>
    </form>
    <table class="blog-admin-table">
        <thead><tr><th>Name</th><th>Slug</th><th>Posts</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($tags as $t): ?>
            <tr>
                <td><?php echo htmlspecialchars($t['name']); ?></td>
                <td><?php echo htmlspecialchars($t['slug']); ?></td>
                <td><?php echo (int)$t['post_count']; ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this tag?');">
                        <?php echo blog_admin_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                        <button type="submit" class="blog-admin-btn blog-admin-btn-secondary" style="padding:0.25rem 0.5rem;font-size:0.85rem;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../views/layout.php';
