<?php
/**
 * Blog module – shared functions, cache, SEO helpers.
 * Use prepared statements; escape output in templates.
 */

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}

$base = defined('BASE_PATH') ? BASE_PATH : '';
$blog_base = rtrim($base . '/blog', '/');

/** SQL condition for visible posts (published or scheduled and due) */
function blog_visible_sql($alias = 'p') {
    $a = $alias ? $alias . '.' : '';
    return "({$a}status = 'published' AND {$a}published_at <= NOW()) OR ({$a}status = 'scheduled' AND {$a}scheduled_at IS NOT NULL AND {$a}scheduled_at <= NOW())";
}

/** Blog base URL path (no trailing slash) */
function blog_base() {
    global $blog_base;
    return $blog_base;
}

/** Full URL for a path (uses SITE_URL + BASE_PATH) */
function blog_full_url($path) {
    $url = defined('SITE_URL') ? SITE_URL : '';
    $url .= $path;
    return $url;
}

/** Simple file cache: get. Returns false if miss or expired. */
function blog_cache_get($key, $ttl_seconds = 300) {
    $dir = dirname(__DIR__, 2) . '/cache/blog';
    if (!is_dir($dir)) return false;
    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.cache';
    if (!is_file($file)) return false;
    if (filemtime($file) + $ttl_seconds < time()) {
        @unlink($file);
        return false;
    }
    $data = @file_get_contents($file);
    return $data === false ? false : unserialize($data);
}

/** Simple file cache: set */
function blog_cache_set($key, $data, $ttl_seconds = 300) {
    $dir = dirname(__DIR__, 2) . '/cache/blog';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.cache';
    file_put_contents($file, serialize($data));
}

/** Return true if blog tables exist (so queries are safe to run) */
function blog_tables_exist($conn) {
    $r = $conn->query("SHOW TABLES LIKE 'blog_posts'");
    return $r && $r->num_rows > 0;
}

/** Invalidate blog caches (call when post/category/tag is updated) */
function blog_cache_clear() {
    $dir = dirname(__DIR__, 2) . '/cache/blog';
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.cache') ?: [] as $f) @unlink($f);
}

/** Rate limit: max requests per minute for search. Returns true if allowed. */
function blog_search_rate_ok() {
    $key = 'blog_search_' . (session_id() ?: substr(md5($_SERVER['REMOTE_ADDR'] ?? '0'), 0, 12));
    $now = time();
    if (!isset($_SESSION[$key])) $_SESSION[$key] = ['c' => 0, 'start' => $now];
    $s = &$_SESSION[$key];
    if ($now - $s['start'] >= 60) { $s['c'] = 0; $s['start'] = $now; }
    $s['c']++;
    return $s['c'] <= 30;
}

/** Featured posts (for index page) */
function blog_get_featured($conn, $limit = 3) {
    $limit = (int) $limit;
    $stmt = $conn->prepare("SELECT p.id, p.slug, p.title, p.excerpt, p.featured_image, p.reading_time_minutes, p.published_at,
        a.name AS author_name, a.slug AS author_slug
        FROM blog_posts p
        LEFT JOIN blog_authors a ON p.author_id = a.id
        WHERE (" . blog_visible_sql('p') . ") AND p.is_featured = 1
        ORDER BY p.published_at DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** Latest posts with pagination */
function blog_get_latest($conn, $limit = 9, $offset = 0) {
    $limit = (int) $limit;
    $offset = (int) $offset;
    $stmt = $conn->prepare("SELECT p.id, p.slug, p.title, p.excerpt, p.featured_image, p.reading_time_minutes, p.published_at,
        a.name AS author_name, a.slug AS author_slug
        FROM blog_posts p
        LEFT JOIN blog_authors a ON p.author_id = a.id
        WHERE " . blog_visible_sql('p') . "
        ORDER BY p.published_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** Count published posts */
function blog_count_posts($conn) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM blog_posts WHERE " . str_replace('p.', '', blog_visible_sql('p')));
    $row = $res ? $res->fetch_assoc() : null;
    return $row ? (int) $row['c'] : 0;
}

/** Single post by slug (with author, categories, tags) */
function blog_get_post_by_slug($conn, $slug) {
    $stmt = $conn->prepare("SELECT p.*, a.name AS author_name, a.slug AS author_slug, a.avatar_url AS author_avatar
        FROM blog_posts p LEFT JOIN blog_authors a ON p.author_id = a.id
        WHERE p.slug = ? AND (" . blog_visible_sql('p') . ")");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return null;
    $post = $res->fetch_assoc();
    $id = (int) $post['id'];
    $cats = $conn->query("SELECT c.id, c.slug, c.name FROM blog_categories c INNER JOIN blog_post_categories pc ON c.id = pc.category_id WHERE pc.post_id = $id");
    $post['categories'] = $cats ? $cats->fetch_all(MYSQLI_ASSOC) : [];
    $tags = $conn->query("SELECT t.id, t.slug, t.name FROM blog_tags t INNER JOIN blog_post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = $id");
    $post['tags'] = $tags ? $tags->fetch_all(MYSQLI_ASSOC) : [];
    $imgs = $conn->query("SELECT image_url, caption, sort_order FROM blog_post_images WHERE post_id = $id ORDER BY sort_order, id");
    $post['gallery'] = $imgs ? $imgs->fetch_all(MYSQLI_ASSOC) : [];
    return $post;
}

/** Related posts (by shared category or tag, exclude current id) */
function blog_get_related($conn, $post_id, $limit = 3) {
    $post_id = (int) $post_id;
    $limit = (int) $limit;
    $stmt = $conn->prepare("SELECT DISTINCT p.id, p.slug, p.title, p.excerpt, p.featured_image, p.published_at
        FROM blog_posts p
        INNER JOIN (SELECT post_id FROM blog_post_categories WHERE category_id IN (SELECT category_id FROM blog_post_categories WHERE post_id = ?)
                    UNION
                    SELECT post_id FROM blog_post_tags WHERE tag_id IN (SELECT tag_id FROM blog_post_tags WHERE post_id = ?)) rel ON p.id = rel.post_id
        WHERE p.id != ? AND (" . blog_visible_sql('p') . ")
        ORDER BY p.published_at DESC LIMIT ?");
    $stmt->bind_param('iiii', $post_id, $post_id, $post_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** Posts by category slug with pagination */
function blog_get_by_category_slug($conn, $slug, $limit = 9, $offset = 0) {
    $limit = (int) $limit;
    $offset = (int) $offset;
    $stmt = $conn->prepare("SELECT c.id AS cat_id, c.name AS cat_name, c.slug AS cat_slug, c.description FROM blog_categories c WHERE c.slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return ['category' => null, 'posts' => [], 'total' => 0];
    $category = $res->fetch_assoc();
    $cid = (int) $category['cat_id'];
    $count = $conn->query("SELECT COUNT(*) AS c FROM blog_post_categories pc INNER JOIN blog_posts p ON p.id = pc.post_id WHERE pc.category_id = $cid AND (" . blog_visible_sql('p') . ")");
    $total = ($count && ($r = $count->fetch_assoc())) ? (int) $r['c'] : 0;
    $stmt = $conn->prepare("SELECT p.id, p.slug, p.title, p.excerpt, p.featured_image, p.reading_time_minutes, p.published_at, a.name AS author_name, a.slug AS author_slug
        FROM blog_posts p
        INNER JOIN blog_post_categories pc ON p.id = pc.post_id
        LEFT JOIN blog_authors a ON p.author_id = a.id
        WHERE pc.category_id = ? AND (" . blog_visible_sql('p') . ")
        ORDER BY p.published_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('iii', $cid, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $posts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    return ['category' => $category, 'posts' => $posts, 'total' => $total];
}

/** Posts by tag slug with pagination */
function blog_get_by_tag_slug($conn, $slug, $limit = 9, $offset = 0) {
    $limit = (int) $limit;
    $offset = (int) $offset;
    $stmt = $conn->prepare("SELECT t.id AS tag_id, t.name AS tag_name, t.slug AS tag_slug FROM blog_tags t WHERE t.slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return ['tag' => null, 'posts' => [], 'total' => 0];
    $tag = $res->fetch_assoc();
    $tid = (int) $tag['tag_id'];
    $count = $conn->query("SELECT COUNT(*) AS c FROM blog_post_tags pt INNER JOIN blog_posts p ON p.id = pt.post_id WHERE pt.tag_id = $tid AND (" . blog_visible_sql('p') . ")");
    $total = ($count && ($r = $count->fetch_assoc())) ? (int) $r['c'] : 0;
    $stmt = $conn->prepare("SELECT p.id, p.slug, p.title, p.excerpt, p.featured_image, p.reading_time_minutes, p.published_at, a.name AS author_name, a.slug AS author_slug
        FROM blog_posts p
        INNER JOIN blog_post_tags pt ON p.id = pt.post_id
        LEFT JOIN blog_authors a ON p.author_id = a.id
        WHERE pt.tag_id = ? AND (" . blog_visible_sql('p') . ")
        ORDER BY p.published_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('iii', $tid, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $posts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    return ['tag' => $tag, 'posts' => $posts, 'total' => $total];
}

/** Search by keyword (title + content in blocks). Safe prepared statement. */
function blog_search($conn, $keyword, $limit = 20, $offset = 0) {
    $limit = (int) $limit;
    $offset = (int) $offset;
    $like = '%' . $conn->real_escape_string($keyword) . '%';
    $stmt = $conn->prepare("SELECT p.id, p.slug, p.title, p.excerpt, p.featured_image, p.published_at, a.name AS author_name
        FROM blog_posts p
        LEFT JOIN blog_authors a ON p.author_id = a.id
        WHERE (" . blog_visible_sql('p') . ") AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content_blocks LIKE ?)
        ORDER BY p.published_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('sssii', $like, $like, $like, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function blog_search_count($conn, $keyword) {
    $like = '%' . $conn->real_escape_string($keyword) . '%';
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM blog_posts WHERE (" . str_replace('p.', '', blog_visible_sql('p')) . ") AND (title LIKE ? OR excerpt LIKE ? OR content_blocks LIKE ?)");
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ? (int) $r['c'] : 0;
}

/** Render content_blocks (paragraph, heading, image, quote, list). Escapes HTML where needed. */
function blog_render_blocks($blocks_json, $base_path = '') {
    if (empty($blocks_json)) return '';
    $blocks = is_string($blocks_json) ? json_decode($blocks_json, true) : $blocks_json;
    if (!is_array($blocks)) return '';
    $html = '';
    foreach ($blocks as $b) {
        $type = isset($b['type']) ? $b['type'] : 'paragraph';
        if ($type === 'paragraph') {
            $html .= '<p>' . nl2br(htmlspecialchars($b['content'] ?? '')) . '</p>';
        } elseif ($type === 'heading') {
            $lvl = (int) ($b['level'] ?? 2);
            $lvl = max(1, min(6, $lvl));
            $html .= '<h' . $lvl . '>' . htmlspecialchars($b['content'] ?? '') . '</h' . $lvl . '>';
        } elseif ($type === 'image') {
            $url = $b['url'] ?? '';
            if ($url && strpos($url, 'http') !== 0) $url = $base_path . (strpos($url, '/') === 0 ? '' : '/') . $url;
            $cap = isset($b['caption']) ? '<figcaption>' . htmlspecialchars($b['caption']) . '</figcaption>' : '';
            $html .= '<figure class="blog-block-image">' . ($url ? '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($b['caption'] ?? '') . '" loading="lazy">' : '') . $cap . '</figure>';
        } elseif ($type === 'quote') {
            $html .= '<blockquote class="blog-block-quote">' . nl2br(htmlspecialchars($b['content'] ?? '')) . '</blockquote>';
        } elseif ($type === 'list') {
            $items = isset($b['items']) && is_array($b['items']) ? $b['items'] : [];
            $html .= '<ul class="blog-block-list">';
            foreach ($items as $item) $html .= '<li>' . htmlspecialchars($item) . '</li>';
            $html .= '</ul>';
        } elseif ($type === 'gallery') {
            $imgs = isset($b['images']) && is_array($b['images']) ? $b['images'] : [];
            $html .= '<div class="blog-block-gallery">';
            foreach ($imgs as $img) {
                $url = is_array($img) ? ($img['url'] ?? '') : $img;
                if ($url && strpos($url, 'http') !== 0) $url = $base_path . (strpos($url, '/') ? '' : '/') . $url;
                $cap = is_array($img) && isset($img['caption']) ? htmlspecialchars($img['caption']) : '';
                $html .= '<figure><img src="' . htmlspecialchars($url) . '" alt="' . $cap . '" loading="lazy">' . ($cap ? '<figcaption>' . $cap . '</figcaption>' : '') . '</figure>';
            }
            $html .= '</div>';
        }
    }
    return $html;
}

/** Breadcrumbs array: [ ['label'=>'Blog', 'url'=>'/blog'], ... ] */
function blog_breadcrumbs($items) {
    return $items;
}
