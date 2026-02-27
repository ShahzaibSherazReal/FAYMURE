<?php
/**
 * Blog sitemap: /blog/sitemap.xml
 * Lists all published posts, category pages, and tag pages.
 */
require_once dirname(__DIR__) . '/config/config.php';

if (is_blog_hidden()) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

$conn = getDBConnection();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$site_url = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
$blog_base = $site_url . $base . '/blog';

$urls = [];

$urls[] = ['loc' => $blog_base, 'priority' => '0.9', 'changefreq' => 'daily'];
$urls[] = ['loc' => $blog_base . '/search', 'priority' => '0.5', 'changefreq' => 'monthly'];

$res = $conn->query("SELECT slug, updated_at FROM blog_posts WHERE (status = 'published' AND published_at <= NOW()) OR (status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()) ORDER BY updated_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $urls[] = [
            'loc' => $blog_base . '/post/' . $row['slug'],
            'lastmod' => !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : '',
            'priority' => '0.8',
            'changefreq' => 'monthly'
        ];
    }
}

$res = $conn->query("SELECT slug FROM blog_categories");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $urls[] = ['loc' => $blog_base . '/category/' . $row['slug'], 'priority' => '0.7', 'changefreq' => 'weekly'];
    }
}

$res = $conn->query("SELECT slug FROM blog_tags");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $urls[] = ['loc' => $blog_base . '/tag/' . $row['slug'], 'priority' => '0.6', 'changefreq' => 'weekly'];
    }
}

$conn->close();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $loc = htmlspecialchars($u['loc']);
    $lastmod = isset($u['lastmod']) && $u['lastmod'] !== '' ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '';
    $priority = isset($u['priority']) ? $u['priority'] : '0.5';
    $changefreq = isset($u['changefreq']) ? $u['changefreq'] : 'monthly';
    echo "  <url><loc>{$loc}</loc>{$lastmod}<priority>{$priority}</priority><changefreq>{$changefreq}</changefreq></url>\n";
}
echo '</urlset>';
