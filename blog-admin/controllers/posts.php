<?php
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && blog_admin_validate_csrf()) {
    $bulk = isset($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    if ($bulk === 'delete' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        blog_admin_audit($conn, 'bulk_delete', 'post', null, null, implode(',', $ids));
        $_SESSION['blog_admin_flash_success'] = count($ids) . ' post(s) deleted.';
        blog_admin_redirect('/posts?' . http_build_query(array_filter(['search' => $search, 'status' => $status_filter, 'page' => $page_num])));
    }
    if ($bulk === 'publish' && !empty($ids)) {
        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'published', published_at = COALESCE(published_at, NOW()) WHERE id = ?");
        foreach ($ids as $id) { $stmt->bind_param('i', $id); $stmt->execute(); }
        blog_admin_audit($conn, 'bulk_publish', 'post', null, null, implode(',', $ids));
        $_SESSION['blog_admin_flash_success'] = count($ids) . ' post(s) published.';
        blog_admin_redirect('/posts?' . http_build_query(array_filter(['search' => $search, 'status' => $status_filter, 'page' => $page_num])));
    }
    if ($bulk === 'unpublish' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'draft' WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $_SESSION['blog_admin_flash_success'] = count($ids) . ' post(s) set to draft.';
        blog_admin_redirect('/posts?' . http_build_query(array_filter(['search' => $search, 'status' => $status_filter, 'page' => $page_num])));
    }
}

$where = ['1=1'];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.excerpt LIKE ?)';
    $term = '%' . $conn->real_escape_string($search) . '%';
    $params[] = $term; $params[] = $term;
    $types .= 'ss';
}
if ($status_filter !== '') {
    $where[] = 'p.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}
$where_sql = implode(' AND ', $where);

$count_sql = "SELECT COUNT(*) AS c FROM blog_posts p WHERE $where_sql";
if ($params) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['c'];
} else {
    $total = (int) $conn->query($count_sql)->fetch_assoc()['c'];
}
$total_pages = (int) ceil($total / $per_page);

$list_sql = "SELECT p.id, p.title, p.slug, p.status, p.is_featured, p.published_at, p.created_at, a.name AS author_name
    FROM blog_posts p
    LEFT JOIN blog_authors a ON p.author_id = a.id
    WHERE $where_sql
    ORDER BY p.updated_at DESC
    LIMIT ? OFFSET ?";
$params[] = $per_page; $params[] = $offset;
$types .= 'ii';
if (count($params) > 2) {
    $stmt = $conn->prepare($list_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare($list_sql);
    $stmt->bind_param('ii', $per_page, $offset);
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$flash_success = $_SESSION['blog_admin_flash_success'] ?? '';
unset($_SESSION['blog_admin_flash_success']);

$conn->close();

$page_title = 'Posts';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Posts</h1>
    <form method="get" class="blog-admin-filters">
        <input type="text" name="search" placeholder="Search title..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="status">
            <option value="">All statuses</option>
            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
        </select>
        <button type="submit">Filter</button>
    </form>
    <form method="post" id="posts-bulk-form">
        <?php echo blog_admin_csrf_field(); ?>
        <input type="hidden" name="bulk_action" id="bulk_action_val">
        <div class="blog-admin-filters">
            <button type="button" class="blog-admin-btn blog-admin-btn-secondary" onclick="document.getElementById('bulk_action_val').value='publish'; document.getElementById('posts-bulk-form').submit();">Publish selected</button>
            <button type="button" class="blog-admin-btn blog-admin-btn-secondary" onclick="document.getElementById('bulk_action_val').value='unpublish'; document.getElementById('posts-bulk-form').submit();">Unpublish</button>
            <button type="button" class="blog-admin-btn blog-admin-btn-secondary" onclick="if(confirm('Delete selected?')){ document.getElementById('bulk_action_val').value='delete'; document.getElementById('posts-bulk-form').submit(); }">Delete selected</button>
        </div>
    <table class="blog-admin-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all-posts"></th>
                <th>Title</th>
                <th>Author</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $row): ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>"></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['author_name'] ?? '—'); ?></td>
                <td><span class="blog-admin-badge blog-admin-badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                <td><?php echo !empty($row['is_featured']) ? 'Yes' : '—'; ?></td>
                <td><?php echo $row['published_at'] ? date('M j, Y', strtotime($row['published_at'])) : date('M j, Y', strtotime($row['created_at'])); ?></td>
                <td>
                    <a href="<?php echo $base; ?>/post-edit/<?php echo (int)$row['id']; ?>">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </form>
    <?php if ($total_pages > 1): ?>
    <nav class="blog-admin-pagination">
        <?php if ($page_num > 1): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page_num - 1])); ?>">Previous</a>
        <?php endif; ?>
        <span>Page <?php echo $page_num; ?> of <?php echo $total_pages; ?></span>
        <?php if ($page_num < $total_pages): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page_num + 1])); ?>">Next</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>
<script>
document.getElementById('select-all-posts') && document.getElementById('select-all-posts').addEventListener('change', function() {
    document.querySelectorAll('input[name="ids[]"]').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
