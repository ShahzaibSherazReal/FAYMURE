<?php
/**
 * Single blog post: /blog/post/{slug}
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

$cache_key = 'blog_post_' . preg_replace('/[^a-z0-9\-]/', '_', $slug);
$cached = blog_cache_get($cache_key, 600);
if ($cached !== false) {
    $conn->close();
    $page_title = $cached['page_title'];
    $page_meta_description = $cached['page_meta_description'];
    $page_canonical = $cached['page_canonical'];
    $page_og_title = $cached['page_og_title'];
    $page_og_description = $cached['page_og_description'];
    $page_og_image = $cached['page_og_image'];
    $page_og_type = 'article';
    $page_extra_head = $cached['page_extra_head'];
    $page_extra_css = $cached['page_extra_css'];
    require dirname(__DIR__) . '/includes/header.php';
    echo $cached['html'];
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$post = blog_get_post_by_slug($conn, $slug);
if (!$post) {
    $conn->close();
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Post not found | ' . SITE_NAME;
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-content"><div class="container"><h1>Post not found</h1><p><a href="' . $blog_base . '">Back to Blog</a></p></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$related = blog_get_related($conn, $post['id'], 3);
$conn->close();

$site_url = defined('SITE_URL') ? SITE_URL : '';
$page_title = !empty($post['meta_title']) ? $post['meta_title'] : (htmlspecialchars($post['title']) . ' | ' . SITE_NAME . ' Blog');
$page_meta_description = !empty($post['meta_description']) ? $post['meta_description'] : (mb_substr(strip_tags($post['excerpt'] ?? ''), 0, 160));
$page_canonical = $site_url . $blog_base . '/post/' . $post['slug'];
$page_og_title = $post['title'];
$page_og_description = $page_meta_description;
$page_og_type = 'article';
$page_og_image = '';
if (!empty($post['featured_image'])) {
    $page_og_image = (strpos($post['featured_image'], 'http') === 0) ? $post['featured_image'] : ($site_url . $base . (strpos($post['featured_image'], '/') === 0 ? '' : '/') . $post['featured_image']);
}
$page_extra_css = 'assets/css/blog.css';

$breadcrumb_items = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $site_url . $base . '/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $site_url . $blog_base],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $page_canonical]
];
$schema_breadcrumb = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumb_items];
$schema_article = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'description' => $page_meta_description,
    'url' => $page_canonical,
    'datePublished' => !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : '',
    'dateModified' => !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : '',
    'author' => ['@type' => 'Person', 'name' => $post['author_name'] ?? SITE_NAME],
    'publisher' => ['@type' => 'Organization', 'name' => SITE_NAME, 'logo' => ['@type' => 'ImageObject', 'url' => $site_url . $base . '/assets/images/favicon.png']]
];
if (!empty($page_og_image)) $schema_article['image'] = $page_og_image;
$page_extra_head = '<script type="application/ld+json">' . json_encode($schema_breadcrumb, JSON_UNESCAPED_SLASHES) . '</script>' .
    '<script type="application/ld+json">' . json_encode($schema_article, JSON_UNESCAPED_SLASHES) . '</script>';

$content_html = blog_render_blocks($post['content_blocks'] ?? '', $base);

ob_start();
?>
    <main class="blog-main blog-article page-content">
        <div class="container container-narrow">
            <nav class="blog-breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo $base; ?>/"><?php echo SITE_NAME; ?></a>
                <span class="sep">/</span>
                <a href="<?php echo $blog_base; ?>">Blog</a>
                <span class="sep">/</span>
                <span><?php echo htmlspecialchars($post['title']); ?></span>
            </nav>

            <article>
                <header class="blog-article-header">
                    <h1 class="blog-article-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                    <div class="blog-article-meta">
                        <?php if (!empty($post['author_name'])): ?>
                        <span class="blog-article-author"><a href="<?php echo $blog_base; ?>"><?php echo htmlspecialchars($post['author_name']); ?></a></span>
                        <?php endif; ?>
                        <time datetime="<?php echo !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : ''; ?>">
                            <?php echo !empty($post['published_at']) ? date('F j, Y', strtotime($post['published_at'])) : ''; ?>
                        </time>
                        <?php if (!empty($post['updated_at']) && $post['updated_at'] !== $post['published_at']): ?>
                        <span>Updated <?php echo date('M j, Y', strtotime($post['updated_at'])); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($post['reading_time_minutes'])): ?>
                        <span><?php echo (int)$post['reading_time_minutes']; ?> min read</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($post['categories']) || !empty($post['tags'])): ?>
                    <div class="blog-article-terms">
                        <?php foreach ($post['categories'] as $cat): ?>
                        <a href="<?php echo $blog_base; ?>/category/<?php echo htmlspecialchars($cat['slug']); ?>" class="blog-term blog-term-cat"><?php echo htmlspecialchars($cat['name']); ?></a>
                        <?php endforeach; ?>
                        <?php foreach ($post['tags'] as $tag): ?>
                        <a href="<?php echo $blog_base; ?>/tag/<?php echo htmlspecialchars($tag['slug']); ?>" class="blog-term blog-term-tag"><?php echo htmlspecialchars($tag['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </header>

                <?php if (!empty($post['featured_image'])): ?>
                <figure class="blog-article-featured-image">
                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="" loading="eager">
                </figure>
                <?php endif; ?>

                <div class="blog-article-body">
                    <?php echo $content_html; ?>
                </div>

                <?php if (!empty($post['gallery'])): ?>
                <div class="blog-article-gallery">
                    <?php foreach ($post['gallery'] as $img): ?>
                    <figure>
                        <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="<?php echo htmlspecialchars($img['caption'] ?? ''); ?>" loading="lazy">
                        <?php if (!empty($img['caption'])): ?><figcaption><?php echo htmlspecialchars($img['caption']); ?></figcaption><?php endif; ?>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </article>

            <?php if (!empty($related)): ?>
            <section class="blog-related">
                <h2 class="blog-section-title">Related posts</h2>
                <div class="blog-grid">
                    <?php foreach ($related as $rp): ?>
                    <article class="blog-card">
                        <a href="<?php echo $blog_base; ?>/post/<?php echo htmlspecialchars($rp['slug']); ?>" class="blog-card-link">
                            <?php if (!empty($rp['featured_image'])): ?>
                            <div class="blog-card-image-wrap">
                                <img src="<?php echo htmlspecialchars($rp['featured_image']); ?>" alt="" loading="lazy" class="blog-card-image">
                            </div>
                            <?php endif; ?>
                            <div class="blog-card-body">
                                <h3 class="blog-card-title"><?php echo htmlspecialchars($rp['title']); ?></h3>
                                <?php if (!empty($rp['excerpt'])): ?>
                                <p class="blog-card-excerpt"><?php echo htmlspecialchars($rp['excerpt']); ?></p>
                                <?php endif; ?>
                                <span class="blog-card-meta"><?php echo !empty($rp['published_at']) ? date('M j, Y', strtotime($rp['published_at'])) : ''; ?></span>
                            </div>
                        </a>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>
<?php
$html = ob_get_clean();

blog_cache_set($cache_key, [
    'page_title' => $page_title,
    'page_meta_description' => $page_meta_description,
    'page_canonical' => $page_canonical,
    'page_og_title' => $page_og_title,
    'page_og_description' => $page_og_description,
    'page_og_image' => $page_og_image,
    'page_extra_head' => $page_extra_head,
    'page_extra_css' => $page_extra_css,
    'html' => $html
], 600);

require dirname(__DIR__) . '/includes/header.php';
echo $html;
require dirname(__DIR__) . '/includes/footer.php';
