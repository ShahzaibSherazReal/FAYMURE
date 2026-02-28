<?php
/**
 * Dynamic robots.txt generator at /robots.txt
 * Includes sitemap URL and disallows admin/private paths.
 */
ob_start();
require_once __DIR__ . '/config/config.php';

$site_url = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
$path_prefix = $base;
$sitemap_url = $site_url . '/sitemap.xml'; // SITE_URL already includes base path

ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Disallow: {$path_prefix}/admin/\n";
echo "Disallow: {$path_prefix}/blog-admin/\n";
echo "Disallow: {$path_prefix}/login\n";
echo "Disallow: {$path_prefix}/logout\n";
echo "Disallow: {$path_prefix}/signup\n";
echo "Disallow: {$path_prefix}/checkout\n";
echo "Disallow: {$path_prefix}/cart\n";
echo "Disallow: {$path_prefix}/receipt\n";
echo "\nSitemap: {$sitemap_url}\n";
