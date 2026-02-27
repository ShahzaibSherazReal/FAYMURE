<?php
/**
 * Blog search: /blog/search?q=keyword
 * Rate-limited; uses prepared statements.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/blog-functions.php';

if (is_blog_hidden()) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested page could not be found.</p></body></html>';
    exit;
}

if (!blog_search_rate_ok()) {
    header('HTTP/1.0 429 Too Many Requests');
    echo 'Too many requests. Please try again in a minute.';
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$conn = getDBConnection();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$blog_base = blog_base();
if (!blog_tables_exist($conn)) {
    $conn->close();
    header('Location: ' . $base . '/blog');
    exit;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

if ($q === '') {
    $posts = [];
    $total = 0;
} else {
    $posts = blog_search($conn, $q, $per_page, $offset);
    $total = blog_search_count($conn, $q);
}
$conn->close();

$total_pages = (int) ceil($total / $per_page);
$site_url = defined('SITE_URL') ? SITE_URL : '';

$page_title = ($q !== '' ? 'Search: ' . htmlspecialchars($q) . ' | ' : '') . 'Blog | ' . SITE_NAME;
$page_meta_description = $q !== '' ? ('Blog search results for ' . $q) : 'Search the blog.';
$page_canonical = $site_url . $blog_base . '/search';
if ($q !== '') $page_canonical .= '?q=' . rawurlencode($q);
if ($page > 1) $page_canonical .= ($q !== '' ? '&' : '?') . 'page=' . $page;
$page_og_title = $page_title;
$page_og_description = $page_meta_description;
$page_extra_css = 'assets/css/blog.css';

require dirname(__DIR__) . '/includes/header.php';
?>
    <main class="blog-main page-content">
        <div class="container">
            <nav class="blog-breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo $base; ?>/"><?php echo SITE_NAME; ?></a>
                <span class="sep">/</span>
                <a href="<?php echo $blog_base; ?>">Blog</a>
                <span class="sep">/</span>
                <span>Search</span>
            </nav>
            <h1 class="blog-page-title">Search the blog</h1>
            <form action="<?php echo $blog_base; ?>/search" method="get" class="blog-search-form blog-search-form-page" role="search">
                <label for="blog-search-q">Search</label>
                <input type="search" id="blog-search-q" name="q" placeholder="Keyword..." value="<?php echo htmlspecialchars($q); ?>">
                <button type="submit">Search</button>
            </form>

            <?php if ($q !== ''): ?>
            <p class="blog-search-result-count"><?php echo $total; ?> result<?php echo $total !== 1 ? 's' : ''; ?> for &ldquo;<?php echo htmlspecialchars($q); ?>&rdquo;</p>
            <div class="blog-grid">
                <?php if (empty($posts)): ?>
                <p class="blog-empty">No posts found. Try different keywords.</p>
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
                <a href="<?php echo $blog_base; ?>/search?q=<?php echo rawurlencode($q); ?>&page=<?php echo $page - 1; ?>" class="blog-pagination-prev">Previous</a>
                <?php endif; ?>
                <span class="blog-pagination-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="<?php echo $blog_base; ?>/search?q=<?php echo rawurlencode($q); ?>&page=<?php echo $page + 1; ?>" class="blog-pagination-next">Next</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
