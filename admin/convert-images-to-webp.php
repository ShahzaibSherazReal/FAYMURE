<?php
/**
 * One-time script: convert existing JPG/PNG/GIF images to WebP and update database references.
 * Run once from browser (Admin must be logged in) or CLI: php convert-images-to-webp.php
 */
require_once __DIR__ . '/check-auth.php';

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

// Send output early so errors don't result in a blank page
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Convert to WebP</title></head><body>';
    echo '<p>Running WebP conversion…</p>';
    if (function_exists('flush')) {
        flush();
    }
}

$webp_include = __DIR__ . '/../includes/image-upload-webp.php';
if (!is_file($webp_include)) {
    if ($is_cli) {
        echo "Error: Missing file includes/image-upload-webp.php\n";
    } else {
        echo '<p><strong>Error:</strong> Missing file <code>includes/image-upload-webp.php</code> on the server. Upload it and try again.</p>';
        echo '<p><a href="dashboard">Back to Dashboard</a></p></body></html>';
    }
    exit;
}
require_once $webp_include;
if (!function_exists('convert_file_to_webp')) {
    if ($is_cli) {
        echo "Error: WebP conversion function not available. Check that GD or Imagick has WebP support.\n";
    } else {
        echo '<p><strong>Error:</strong> WebP conversion is not available on this server (GD or Imagick with WebP support is required).</p>';
        echo '<p><a href="dashboard">Back to Dashboard</a></p></body></html>';
    }
    exit;
}

try {
    $conn = getDBConnection();
} catch (Throwable $e) {
    if ($is_cli) {
        echo "Error: " . $e->getMessage() . "\n";
    } else {
        echo '<p><strong>Error connecting to database:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><a href="dashboard">Back to Dashboard</a></p></body></html>';
    }
    exit;
}

$project_root = dirname(__DIR__);

$dirs_to_scan = [
    'assets/images',
    'assets/images/products',
    'assets/images/categories',
    'uploads/blog',
    'uploads/customizations',
];

$extensions = ['jpg', 'jpeg', 'png', 'gif'];
$converted = 0;
$errors = [];
$updates = [];

foreach ($dirs_to_scan as $dir) {
    $abs_dir = $project_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($abs_dir)) {
        continue;
    }
    $files = scandir($abs_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            continue;
        }
        $abs_path = $abs_dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($abs_path)) {
            continue;
        }
        $rel_path = $dir . '/' . $file;
        $base = pathinfo($file, PATHINFO_FILENAME);
        $new_rel = $dir . '/' . $base . '.webp';
        $new_path = convert_file_to_webp($abs_path);
        if (!$new_path) {
            $errors[] = "Convert failed: $rel_path";
            continue;
        }
        $converted++;
        $old_basename = $file;
        $new_basename = $base . '.webp';
        $updates[] = [
            'old_rel' => $rel_path,
            'new_rel' => $new_rel,
            'old_basename' => $old_basename,
            'new_basename' => $new_basename,
        ];
    }
}

// Update database references
foreach ($updates as $u) {
    $old_rel = $conn->real_escape_string($u['old_rel']);
    $new_rel = $conn->real_escape_string($u['new_rel']);
    $old_basename = $conn->real_escape_string($u['old_basename']);
    $new_basename = $conn->real_escape_string($u['new_basename']);

    // site_content: full path and filename-only (e.g. hero_poster in assets/images/)
    $conn->query("UPDATE site_content SET content_value = '$new_rel' WHERE content_value = '$old_rel'");
    if ($u['old_rel'] === 'assets/images/' . $u['old_basename']) {
        $conn->query("UPDATE site_content SET content_value = '$new_basename' WHERE content_value = '$old_basename'");
    }

    // products.image
    $conn->query("UPDATE products SET image = '$new_rel' WHERE image = '$old_rel'");

    // products.images (JSON array)
    $conn->query("UPDATE products SET images = REPLACE(images, '$old_rel', '$new_rel') WHERE images LIKE '%$old_rel%'");

    // categories
    $conn->query("UPDATE categories SET image = '$new_rel' WHERE image = '$old_rel'");

    // blog_media: paths are stored with leading slash
    $old_slash = $conn->real_escape_string('/' . ltrim($u['old_rel'], '/'));
    $new_slash = $conn->real_escape_string('/' . ltrim($u['new_rel'], '/'));
    $conn->query("UPDATE blog_media SET file_path = REPLACE(file_path, '$old_basename', '$new_basename'), thumb_path = REPLACE(thumb_path, '$old_basename', '$new_basename'), medium_path = REPLACE(medium_path, '$old_basename', '$new_basename'), large_path = REPLACE(large_path, '$old_basename', '$new_basename') WHERE file_path LIKE '%$old_basename%' OR thumb_path LIKE '%$old_basename%' OR medium_path LIKE '%$old_basename%' OR large_path LIKE '%$old_basename%'");

    // product_customizations.images (JSON array of paths like uploads/customizations/xxx.jpg)
    $conn->query("UPDATE product_customizations SET images = REPLACE(images, '$old_rel', '$new_rel') WHERE images LIKE '%$old_rel%'");
}

$conn->close();

if ($is_cli) {
    echo "Converted $converted image(s) to WebP.\n";
    if (!empty($errors)) {
        foreach ($errors as $e) {
            echo "  Error: $e\n";
        }
    }
} else {
    echo '<p><strong>Done.</strong> Converted ' . (int)$converted . ' image(s) to WebP. Database references updated.</p>';
    if (!empty($errors)) {
        echo '<p>Errors:</p><ul>';
        foreach ($errors as $e) {
            echo '<li>' . htmlspecialchars($e) . '</li>';
        }
        echo '</ul>';
    }
    echo '<p><a href="dashboard">Back to Dashboard</a></p></body></html>';
}
