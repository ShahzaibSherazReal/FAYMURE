<?php
/**
 * Hostinger debug: open https://yoursite.com/blog-admin/test-rewrite.php
 * If you see "OK" below, the blog-admin folder is reachable and PHP runs.
 * If you get 404, .htaccess rewrites are not sending /blog-admin/* to this folder.
 */
header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "Server time: " . date('c') . "\n";
echo "PHP: " . PHP_VERSION . "\n";
