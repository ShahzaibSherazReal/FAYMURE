<?php
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && blog_admin_validate_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && trim($_POST['name'] ?? '') !== '') {
        $name = trim($_POST['name']);
        $slug = blog_admin_slugify($_POST['slug'] ?? $name) ?: 'category-' . time();
        $desc = trim($_POST['description'] ?? '');
        $stmt = $conn->prepare("INSERT INTO blog_categories (slug, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $slug, $name, $desc);
        $stmt->execute();
        blog_admin_audit($conn, 'create', 'category', $conn->insert_id, null, $name);
        $_SESSION['blog_admin_flash_success'] = 'Category added.';
        blog_admin_redirect('/categories');
    }
    if ($action === 'edit' && (int)($_POST['id'] ?? 0) > 0) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $slug = blog_admin_slugify($_POST['slug'] ?? $name);
        $desc = trim($_POST['description'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("UPDATE blog_categories SET name=?, slug=?, description=? WHERE id=?");
            $stmt->bind_param('sssi', $name, $slug, $desc, $id);
            $stmt->execute();
            blog_admin_audit($conn, 'update', 'category', $id, null, $name);
            $_SESSION['blog_admin_flash_success'] = 'Category updated.';
        }
        blog_admin_redirect('/categories');
    }
    if ($action === 'delete' && (int)($_POST['id'] ?? 0) > 0) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM blog_categories WHERE id = $id");
        blog_admin_audit($conn, 'delete', 'category', $id, null, null);
        $_SESSION['blog_admin_flash_success'] = 'Category deleted.';
        blog_admin_redirect('/categories');
    }
}

$categories = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM blog_post_categories WHERE category_id = c.id) AS post_count FROM blog_categories c ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
$flash_success = $_SESSION['blog_admin_flash_success'] ?? ''; unset($_SESSION['blog_admin_flash_success']);
$page_title = 'Categories';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Categories</h1>
    <?php if ($flash_success): ?><div class="blog-admin-alert blog-admin-alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
    <form method="post" class="form-group" style="max-width:400px;">
        <?php echo blog_admin_csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <label>Name</label>
        <input type="text" name="name" required>
        <label>Slug</label>
        <input type="text" name="slug" placeholder="auto">
        <label>Description</label>
        <textarea name="description" rows="2"></textarea>
        <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Add category</button>
    </form>
    <table class="blog-admin-table">
        <thead><tr><th>Name</th><th>Slug</th><th>Posts</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo htmlspecialchars($c['slug']); ?></td>
                <td><?php echo (int)$c['post_count']; ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                        <?php echo blog_admin_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <button type="submit" class="blog-admin-btn blog-admin-btn-secondary" style="padding:0.25rem 0.5rem;font-size:0.85rem;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../views/layout.php';
