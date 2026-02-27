<?php
/**
 * Blog index: featured posts + latest with pagination.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/blog-functions.php';

if (is_blog_hidden()) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested page could not be found.</p></body></html>';
    exit;
}

$conn = getDBConnection();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$blog_base = blog_base();

if (!blog_tables_exist($conn)) {
    $conn->close();
    $page_title = 'Blog | ' . SITE_NAME;
    $page_meta_description = 'Blog';
    $page_extra_css = 'assets/css/blog.css';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="blog-main page-content"><div class="container">';
    echo '<h1 class="blog-page-title">Blog</h1>';
    echo '<p class="blog-intro">The blog is not set up yet. Run the database setup to create the blog tables and a sample post.</p>';
    echo '<p><a href="' . $base . '/setup-database.php">Run database setup</a></p>';
    echo '</div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$cache_key = 'blog_index_' . (int)($_GET['page'] ?? 1);
$cached = blog_cache_get($cache_key, 300);
if ($cached !== false) {
    $conn->close();
    $page_title = $cached['page_title'];
    $page_meta_description = $cached['page_meta_description'];
    $page_canonical = $cached['page_canonical'];
    $page_og_title = $cached['page_og_title'];
    $page_og_description = $cached['page_og_description'];
    $page_extra_head = $cached['page_extra_head'];
    $page_extra_css = $cached['page_extra_css'];
    require dirname(__DIR__) . '/includes/header.php';
    echo $cached['html'];
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9;
$offset = ($page - 1) * $per_page;
$featured = blog_get_featured($conn, 3);
$latest = blog_get_latest($conn, $per_page, $offset);
$total = blog_count_posts($conn);
$total_pages = (int) ceil($total / $per_page);

$page_title = 'Blog | ' . SITE_NAME;
$page_meta_description = 'Tips, guides, and stories about leather care, craftsmanship, and premium leather goods.';
$page_canonical = blog_full_url($blog_base);
$page_og_title = $page_title;
$page_og_description = $page_meta_description;
$page_og_type = 'website';
$page_extra_css = 'assets/css/blog.css';

$breadcrumbs = [
    ['label' => 'Blog', 'url' => $blog_base]
];

$schema_breadcrumb = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => blog_full_url($base . '/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $page_canonical]
    ]
];
$page_extra_head = '<script type="application/ld+json">' . json_encode($schema_breadcrumb, JSON_UNESCAPED_SLASHES) . '</script>';

ob_start();
?>
    <main class="blog-main page-content">
        <div class="container">
            <nav class="blog-breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo $base; ?>/"><?php echo SITE_NAME; ?></a>
                <span class="sep">/</span>
                <span>Blog</span>
            </nav>
            <h1 class="blog-page-title">Blog</h1>
            <p class="blog-intro">Tips, guides, and stories about leather care and premium leather goods.</p>

            <?php if (!empty($featured)): ?>
            <section class="blog-featured">
                <h2 class="blog-section-title">Featured</h2>
                <div class="blog-featured-grid">
                    <?php foreach ($featured as $fp): ?>
                    <article class="blog-card blog-card-featured">
                        <a href="<?php echo $blog_base; ?>/post/<?php echo htmlspecialchars($fp['slug']); ?>" class="blog-card-link">
                            <?php if (!empty($fp['featured_image'])): ?>
                            <div class="blog-card-image-wrap">
                                <img src="<?php echo htmlspecialchars($fp['featured_image']); ?>" alt="" loading="lazy" class="blog-card-image">
                            </div>
                            <?php endif; ?>
                            <div class="blog-card-body">
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($fp['title']); ?></h3>
                                <?php if (!empty($fp['excerpt'])): ?>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars($fp['excerpt']); ?></p>
                                <?php endif; ?>
                                <div class="blog-card-meta">
                                    <?php if (!empty($fp['author_name'])): ?>
                                    <span><?php echo htmlspecialchars($fp['author_name']); ?></span>
                                    <?php endif; ?>
                                    <span><?php echo !empty($fp['published_at']) ? date('M j, Y', strtotime($fp['published_at'])) : ''; ?></span>
                                    <?php if (!empty($fp['reading_time_minutes'])): ?>
                                    <span><?php echo (int)$fp['reading_time_minutes']; ?> min read</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="blog-latest">
                <h2 class="blog-section-title">Latest posts</h2>
                <div class="blog-grid">
                    <?php if (empty($latest)): ?>
                    <p class="blog-empty">No posts yet. Check back soon.</p>
                    <?php else: ?>
                    <?php foreach ($latest as $post): ?>
                    <article class="blog-card">
                        <a href="<?php echo $blog_base; ?>/post/<?php echo htmlspecialchars($post['slug']); ?>" class="blog-card-link">
                            <?php if (!empty($post['featured_image'])): ?>
                            <div class="blog-card-image-wrap">
                                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="" loading="lazy" class="blog-card-image">
                            </div>
                            <?php endif; ?>
                            <div class="blog-card-body">
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
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
                    <a href="<?php echo $blog_base; ?>?page=<?php echo $page - 1; ?>" class="blog-pagination-prev">Previous</a>
                    <?php endif; ?>
                    <span class="blog-pagination-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $blog_base; ?>?page=<?php echo $page + 1; ?>" class="blog-pagination-next">Next</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </section>

            <aside class="blog-side-actions">
                <form action="<?php echo $blog_base; ?>/search" method="get" class="blog-search-form" role="search">
                    <label for="blog-search-q">Search blog</label>
                    <input type="search" id="blog-search-q" name="q" placeholder="Search..." value="">
                    <button type="submit">Search</button>
                </form>
            </aside>
        </div>
    </main>
<?php
$html = ob_get_clean();
$conn->close();

blog_cache_set($cache_key, [
    'page_title' => $page_title,
    'page_meta_description' => $page_meta_description,
    'page_canonical' => $page_canonical,
    'page_og_title' => $page_og_title,
    'page_og_description' => $page_og_description,
    'page_extra_head' => $page_extra_head,
    'page_extra_css' => $page_extra_css,
    'html' => $html
], 300);

require dirname(__DIR__) . '/includes/header.php';
echo $html;
require dirname(__DIR__) . '/includes/footer.php';
