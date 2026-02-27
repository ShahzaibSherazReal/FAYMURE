<?php
/**
 * Blog Admin – Post create/edit with block editor and SEO.
 */
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;
$id = isset($sub) && $sub !== '' ? (int) $sub : (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

$post = null;
if (!$is_new) {
    $stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $post = $row;
    if (!$post) {
        $conn->close();
        $_SESSION['blog_admin_flash_error'] = 'Post not found.';
        blog_admin_redirect('/posts');
    }
    $post_cats = [];
    $post_tags = [];
    $r = $conn->query("SELECT category_id FROM blog_post_categories WHERE post_id = " . (int)$post['id']);
    if ($r) while ($row = $r->fetch_assoc()) $post_cats[] = (int)$row['category_id'];
    $r = $conn->query("SELECT tag_id FROM blog_post_tags WHERE post_id = " . (int)$post['id']);
    if ($r) while ($row = $r->fetch_assoc()) $post_tags[] = (int)$row['tag_id'];
}

$authors = [];
$r = $conn->query("SELECT id, name, slug FROM blog_authors ORDER BY name");
if ($r) $authors = $r->fetch_all(MYSQLI_ASSOC);
$categories = [];
$r = $conn->query("SELECT id, name, slug FROM blog_categories ORDER BY name");
if ($r) $categories = $r->fetch_all(MYSQLI_ASSOC);
$tags = [];
$r = $conn->query("SELECT id, name, slug FROM blog_tags ORDER BY name");
if ($r) $tags = $r->fetch_all(MYSQLI_ASSOC);

$flash_error = $_SESSION['blog_admin_flash_error'] ?? '';
$flash_success = $_SESSION['blog_admin_flash_success'] ?? '';
unset($_SESSION['blog_admin_flash_error'], $_SESSION['blog_admin_flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && blog_admin_validate_csrf()) {
    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'scheduled']) ? $_POST['status'] : 'draft';
    $published_at = !empty($_POST['published_at']) ? $_POST['published_at'] : null;
    $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
    $featured_image = trim($_POST['featured_image'] ?? '');
    $author_id = (int)($_POST['author_id'] ?? 0);
    $author_id = $author_id > 0 ? $author_id : null;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $og_title = trim($_POST['og_title'] ?? '');
    $og_description = trim($_POST['og_description'] ?? '');
    $og_image = trim($_POST['og_image'] ?? '');
    $canonical_url = trim($_POST['canonical_url'] ?? '');
    $robots_noindex = isset($_POST['robots_noindex']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $content_blocks_raw = $_POST['content_blocks'] ?? '[]';
    $content_blocks = json_decode($content_blocks_raw, true);
    if (!is_array($content_blocks)) $content_blocks = [];
    $content_blocks_json = $conn->real_escape_string(json_encode($content_blocks));
    $reading_min = blog_admin_reading_time_blocks($content_blocks);

    if ($title === '') {
        $flash_error = 'Title is required.';
    } else {
        if ($slug === '') $slug = blog_admin_slugify($title);
        $slug = blog_admin_slugify($slug) ?: 'post-' . time();
        $stmt_check = $conn->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $oid = $is_new ? 0 : $id;
        $stmt_check->bind_param('si', $slug, $oid);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) $slug = $slug . '-' . ($is_new ? time() : $id);

        if ($is_new) {
            $stmt = $conn->prepare("INSERT INTO blog_posts (slug, title, excerpt, featured_image, author_id, reading_time_minutes, is_featured, status, meta_title, meta_description, og_title, og_description, og_image, canonical_url, robots_noindex, content_blocks, published_at, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $pub = $status === 'published' ? ($published_at ?: date('Y-m-d H:i:s')) : null;
            $sched = $status === 'scheduled' ? ($scheduled_at ?: null) : null;
            $stmt->bind_param('sssssissssssssisssi', $slug, $title, $excerpt, $featured_image, $author_id, $reading_min, $is_featured, $status, $meta_title, $meta_description, $og_title, $og_description, $og_image, $canonical_url, $robots_noindex, $content_blocks_json, $pub, $sched, blog_admin_user_id());
            $stmt->execute();
            $new_id = $conn->insert_id;
            blog_admin_audit($conn, 'create', 'post', $new_id, null, $title);
            foreach ((array)($_POST['category_ids'] ?? []) as $cid) {
                $cid = (int)$cid;
                if ($cid) $conn->query("INSERT IGNORE INTO blog_post_categories (post_id, category_id) VALUES ($new_id, $cid)");
            }
            foreach ((array)($_POST['tag_ids'] ?? []) as $tid) {
                $tid = (int)$tid;
                if ($tid) $conn->query("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES ($new_id, $tid)");
            }
            $_SESSION['blog_admin_flash_success'] = 'Post created.';
            blog_admin_redirect('/post-edit/' . $new_id);
        } else {
            $pub = $status === 'published' ? ($published_at ?: ($post['published_at'] ?? date('Y-m-d H:i:s'))) : null;
            $sched = $status === 'scheduled' ? $scheduled_at : null;
            $stmt = $conn->prepare("UPDATE blog_posts SET slug=?, title=?, excerpt=?, featured_image=?, author_id=?, reading_time_minutes=?, is_featured=?, status=?, meta_title=?, meta_description=?, og_title=?, og_description=?, og_image=?, canonical_url=?, robots_noindex=?, content_blocks=?, published_at=?, scheduled_at=? WHERE id=?");
            $stmt->bind_param('sssssissssssssiiiii', $slug, $title, $excerpt, $featured_image, $author_id, $reading_min, $is_featured, $status, $meta_title, $meta_description, $og_title, $og_description, $og_image, $canonical_url, $robots_noindex, $content_blocks_json, $pub, $sched, $id);
            $stmt->execute();
            $uid = blog_admin_user_id();
            $conn->query("INSERT INTO blog_post_revisions (post_id, title, excerpt, content_blocks, meta_title, meta_description, created_by) SELECT id, title, excerpt, content_blocks, meta_title, meta_description, $uid FROM blog_posts WHERE id = $id");
            $conn->query("DELETE FROM blog_post_categories WHERE post_id = $id");
            foreach ((array)($_POST['category_ids'] ?? []) as $cid) {
                $cid = (int)$cid;
                if ($cid) $conn->query("INSERT IGNORE INTO blog_post_categories (post_id, category_id) VALUES ($id, $cid)");
            }
            $conn->query("DELETE FROM blog_post_tags WHERE post_id = $id");
            foreach ((array)($_POST['tag_ids'] ?? []) as $tid) {
                $tid = (int)$tid;
                if ($tid) $conn->query("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES ($id, $tid)");
            }
            blog_admin_audit($conn, 'update', 'post', $id, $post['title'] ?? null, $title);
            $_SESSION['blog_admin_flash_success'] = 'Post saved.';
            blog_admin_redirect('/post-edit/' . $id);
        }
    }
}

$content_blocks = $post ? (is_string($post['content_blocks']) ? json_decode($post['content_blocks'], true) : $post['content_blocks']) : [];
if (!is_array($content_blocks)) $content_blocks = [];

$page_title = $is_new ? 'New post' : ('Edit: ' . ($post['title'] ?? ''));
ob_start();
include __DIR__ . '/../views/post-edit.php';
$content = ob_get_clean();
$conn->close();
require __DIR__ . '/../views/layout.php';
