<?php
/**
 * Production sitemap generator: outputs valid XML sitemap at /sitemap.xml
 * Includes: homepage, static pages, product pages, category pages, blog (posts, categories, tags).
 * Uses lastmod from updated_at where available; memory-efficient (streaming) for large URL counts.
 */
ob_start();
require_once __DIR__ . '/config/config.php';

$site_url = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
$base_url = $site_url; // SITE_URL already includes base path (e.g. /FAYMURE locally)

/** Escape for XML text content (loc, etc.) */
function sitemap_esc($s) {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Output a single <url> entry (streaming). */
function sitemap_echo_url($loc, $lastmod = '', $changefreq = 'monthly', $priority = '0.5') {
    echo '  <url>' . "\n";
    echo '    <loc>' . sitemap_esc($loc) . '</loc>' . "\n";
    if ($lastmod !== '') {
        echo '    <lastmod>' . sitemap_esc($lastmod) . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . sitemap_esc($changefreq) . '</changefreq>' . "\n";
    echo '    <priority>' . sitemap_esc($priority) . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

ob_end_clean();
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // optional: avoid indexing the sitemap URL itself in some engines
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// --- Homepage ---
sitemap_echo_url($base_url . '/', date('Y-m-d'), 'daily', '1.0');

// --- Static pages (no lastmod; use today or omit) ---
$static = [
    ['/about', 'weekly', '0.8'],
    ['/contact', 'monthly', '0.7'],
    ['/manufacturing', 'monthly', '0.7'],
    ['/shop', 'weekly', '0.9'],
    ['/explore', 'weekly', '0.8'],
    ['/explore-custom-design', 'monthly', '0.7'],
    ['/explore-browse', 'weekly', '0.8'],
    ['/products', 'daily', '0.9'],
    ['/categories', 'weekly', '0.8'],
    ['/faq', 'monthly', '0.6'],
    ['/privacy', 'yearly', '0.4'],
    ['/terms', 'yearly', '0.4'],
];
foreach ($static as $s) {
    sitemap_echo_url($base_url . $s[0], '', $s[1], $s[2]);
}

$conn = getDBConnection();

// --- Products (stream: one row at a time) ---
$res = $conn->query(
    "SELECT slug, updated_at FROM products WHERE status = 'active' AND deleted_at IS NULL ORDER BY id"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $loc = $base_url . '/product-detail/' . $row['slug'];
        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : '';
        sitemap_echo_url($loc, $lastmod, 'weekly', '0.7');
    }
    $res->free();
}

// --- Category pages: /categories is a single listing (already in static). If you add per-category URLs (e.g. /category/{slug}), add a loop here. ---

// --- Blog (only if blog is not hidden and tables exist) ---
if (!is_blog_hidden()) {
    require_once __DIR__ . '/blog/includes/blog-functions.php';
    if (blog_tables_exist($conn)) {
        $blog_base = $base_url . '/blog';
        sitemap_echo_url($blog_base, '', 'daily', '0.8');
        sitemap_echo_url($blog_base . '/search', '', 'monthly', '0.5');

        $visible_sql = blog_visible_sql('p');
        try {
            $res = $conn->query("SELECT slug, updated_at FROM blog_posts WHERE ($visible_sql) ORDER BY id");
        } catch (mysqli_sql_exception $e) {
            $res = $conn->query("SELECT slug, updated_at FROM blog_posts WHERE published_at IS NOT NULL AND published_at <= NOW() ORDER BY id");
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $loc = $blog_base . '/post/' . $row['slug'];
                $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : '';
                sitemap_echo_url($loc, $lastmod, 'monthly', '0.7');
            }
            $res->free();
        }

        $res = $conn->query("SELECT slug FROM blog_categories");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                sitemap_echo_url($blog_base . '/category/' . $row['slug'], '', 'weekly', '0.6');
            }
            $res->free();
        }

        $res = $conn->query("SELECT slug FROM blog_tags");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                sitemap_echo_url($blog_base . '/tag/' . $row['slug'], '', 'weekly', '0.6');
            }
            $res->free();
        }
    }
}

$conn->close();

echo '</urlset>';
