<?php
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;

$settings = [];
$r = $conn->query("SELECT setting_key, setting_value FROM blog_admin_settings");
if ($r) while ($row = $r->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && blog_admin_validate_csrf()) {
    $featured_ids = trim($_POST['featured_post_ids'] ?? '');
    $stmt = $conn->prepare("INSERT INTO blog_admin_settings (setting_key, setting_value) VALUES ('featured_post_ids', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('s', $featured_ids);
    $stmt->execute();
    blog_admin_audit($conn, 'update', 'settings', 0, null, 'featured_post_ids');
    $_SESSION['blog_admin_flash_success'] = 'Settings saved.';
    blog_admin_redirect('/settings');
}
$settings['featured_post_ids'] = $settings['featured_post_ids'] ?? '';
$conn->close();
$flash_success = $_SESSION['blog_admin_flash_success'] ?? ''; unset($_SESSION['blog_admin_flash_success']);
$page_title = 'Settings';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Blog settings</h1>
    <?php if ($flash_success): ?><div class="blog-admin-alert blog-admin-alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
    <form method="post">
        <?php echo blog_admin_csrf_field(); ?>
        <div class="form-group">
            <label for="featured_post_ids">Featured post IDs (comma-separated, for homepage)</label>
            <input type="text" id="featured_post_ids" name="featured_post_ids" value="<?php echo htmlspecialchars($settings['featured_post_ids']); ?>" placeholder="1, 2, 3">
        </div>
        <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Save</button>
    </form>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../views/layout.php';
