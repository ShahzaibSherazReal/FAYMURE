<?php
/**
 * Blog tag archive: /blog/tag/{slug}
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/blog-functions.php';

if (is_blog_hidden()) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested page could not be found.</p></body></html>';
    exit;
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/blog');
    exit;
}

$conn = getDBConnection();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$blog_base = blog_base();
if (!blog_tables_exist($conn)) {
    $conn->close();
    header('Location: ' . $base . '/blog');
    exit;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9;
$offset = ($page - 1) * $per_page;

$data = blog_get_by_tag_slug($conn, $slug, $per_page, $offset);
$conn->close();

if ($data['tag'] === null) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Tag not found | ' . SITE_NAME;
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-content"><div class="container"><h1>Tag not found</h1><p><a href="' . $blog_base . '">Back to Blog</a></p></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$tag = $data['tag'];
$posts = $data['posts'];
$total = $data['total'];
$total_pages = (int) ceil($total / $per_page);
$site_url = defined('SITE_URL') ? SITE_URL : '';

$page_title = htmlspecialchars($tag['tag_name']) . ' | Blog | ' . SITE_NAME;
$page_meta_description = 'Posts tagged with ' . $tag['tag_name'];
$page_canonical = $site_url . $blog_base . '/tag/' . $tag['tag_slug'];
if ($page > 1) $page_canonical .= '?page=' . $page;
$page_og_title = $page_title;
$page_og_description = $page_meta_description;
$page_extra_css = 'assets/css/blog.css';

$breadcrumb_items = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $site_url . $base . '/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $site_url . $blog_base],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $tag['tag_name'], 'item' => $page_canonical]
];
$page_extra_head = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumb_items], JSON_UNESCAPED_SLASHES) . '</script>';

require dirname(__DIR__) . '/includes/header.php';
?>
    <main class="blog-main page-content">
        <div class="container">
            <nav class="blog-breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo $base; ?>/"><?php echo SITE_NAME; ?></a>
                <span class="sep">/</span>
                <a href="<?php echo $blog_base; ?>">Blog</a>
                <span class="sep">/</span>
                <span><?php echo htmlspecialchars($tag['tag_name']); ?></span>
            </nav>
            <h1 class="blog-page-title">Tag: <?php echo htmlspecialchars($tag['tag_name']); ?></h1>

            <div class="blog-grid">
                <?php if (empty($posts)): ?>
                <p class="blog-empty">No posts with this tag yet.</p>
                <?php else: ?>
                <?php foreach ($posts as $post): ?>
                <article class="blog-card">
                    <a href="<?php echo $blog_base; ?>/post/<?php echo htmlspecialchars($post['slug']); ?>" class="blog-card-link">
                        <?php if (!empty($post['featured_image'])): ?>
                        <div class="blog-card-image-wrap">
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="" loading="lazy" class="blog-card-image">
                        </div>
                        <?php endif; ?>
                        <div class="blog-card-body">
                            <h2 class="blog-card-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                            <?php if (!empty($post['excerpt'])): ?>
                            <p class="blog-card-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                            <?php endif; ?>
                            <div class="blog-card-meta">
                                <?php if (!empty($post['author_name'])): ?>
                                <span><?php echo htmlspecialchars($post['author_name']); ?></span>
                                <?php endif; ?>
                                <span><?php echo !empty($post['published_at']) ? date('M j, Y', strtotime($post['published_at'])) : ''; ?></span>
                                <?php if (!empty($post['reading_time_minutes'])): ?>
                                <span><?php echo (int)$post['reading_time_minutes']; ?> min read</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="blog-pagination" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="<?php echo $blog_base; ?>/tag/<?php echo htmlspecialchars($tag['tag_slug']); ?>?page=<?php echo $page - 1; ?>" class="blog-pagination-prev">Previous</a>
                <?php endif; ?>
                <span class="blog-pagination-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="<?php echo $blog_base; ?>/tag/<?php echo htmlspecialchars($tag['tag_slug']); ?>?page=<?php echo $page + 1; ?>" class="blog-pagination-next">Next</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
