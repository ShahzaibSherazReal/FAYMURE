<?php
/**
 * Blog Admin – Media library: list, upload (with resize/compress), delete.
 * All uploads are converted to WebP.
 */
require_once dirname(__DIR__, 2) . '/includes/image-upload-webp.php';
$conn = blog_admin_conn();
$base = BLOG_ADMIN_BASE;
$upload_dir = BLOG_ADMIN_UPLOAD_DIR;
$upload_url = BLOG_ADMIN_UPLOAD_URL;
$max_mb = BLOG_ADMIN_MAX_UPLOAD_MB;
$allowed = BLOG_ADMIN_ALLOWED_MIMES;

if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && blog_admin_validate_csrf()) {
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && (int)($_POST['id'] ?? 0) > 0) {
        $id = (int)$_POST['id'];
        $row = $conn->query("SELECT file_path, thumb_path, medium_path, large_path FROM blog_media WHERE id = $id")->fetch_assoc();
        if ($row) {
            $root = dirname(__DIR__, 2);
            foreach (['file_path', 'thumb_path', 'medium_path', 'large_path'] as $k) {
                if (!empty($row[$k])) @unlink($root . $row[$k]);
            }
            $conn->query("DELETE FROM blog_media WHERE id = $id");
            blog_admin_audit($conn, 'delete', 'media', $id, null, null);
            $_SESSION['blog_admin_flash_success'] = 'Media deleted.';
        }
        blog_admin_redirect('/media');
    }
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $_SESSION['blog_admin_flash_error'] = 'Invalid file type.';
            blog_admin_redirect('/media');
        }
        if ($_FILES['file']['size'] > $max_mb * 1024 * 1024) {
            $_SESSION['blog_admin_flash_error'] = 'File too large (max ' . $max_mb . 'MB).';
            blog_admin_redirect('/media');
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) $ext = 'jpg';
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $path = '/uploads/blog/' . $name;
        $full = dirname(__DIR__, 2) . $path;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $full)) {
            $webp_path = convert_file_to_webp($full);
            if ($webp_path) {
                $full = $webp_path;
                $name = basename($webp_path);
                $path = '/uploads/blog/' . $name;
            }
            $w = $h = null;
            if (function_exists('getimagesize')) { $info = @getimagesize($full); if ($info) { $w = $info[0]; $h = $info[1]; } }
            $thumb = $medium = $large = null;
            if (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromwebp')) {
                $thumb_path = '/uploads/blog/thumb_' . $name;
                $medium_path = '/uploads/blog/medium_' . $name;
                $large_path = '/uploads/blog/large_' . $name;
                blog_admin_resize_image($full, dirname(__DIR__, 2) . $thumb_path, 150, 150);
                blog_admin_resize_image($full, dirname(__DIR__, 2) . $medium_path, 600, 600);
                blog_admin_resize_image($full, dirname(__DIR__, 2) . $large_path, 1200, 1200);
                $thumb = $thumb_path; $medium = $medium_path; $large = $large_path;
            }
            $stmt = $conn->prepare("INSERT INTO blog_media (file_path, file_name_original, mime_type, file_size, width, height, thumb_path, medium_path, large_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $orig = $_FILES['file']['name'];
            $sz = (int)$_FILES['file']['size'];
            $mime = 'image/webp';
            $stmt->bind_param('sssiiisss', $path, $orig, $mime, $sz, $w, $h, $thumb, $medium, $large);
            $stmt->execute();
            blog_admin_audit($conn, 'upload', 'media', $conn->insert_id, null, $orig);
            $_SESSION['blog_admin_flash_success'] = 'File uploaded.';
        } else {
            $_SESSION['blog_admin_flash_error'] = 'Upload failed.';
        }
        blog_admin_redirect('/media');
    }
}

function blog_admin_resize_image($src, $dest, $max_w, $max_h) {
    $info = @getimagesize($src);
    if (!$info) return;
    $w = $info[0]; $h = $info[1]; $mime = $info['mime'];
    $dest_ext = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    $img = null;
    if ($mime === 'image/jpeg') $img = @imagecreatefromjpeg($src);
    elseif ($mime === 'image/png') $img = @imagecreatefrompng($src);
    elseif ($mime === 'image/gif') $img = @imagecreatefromgif($src);
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($src);
    if (!$img) return;
    if ($w <= $max_w && $h <= $max_h) {
        if ($dest_ext === 'webp' && function_exists('imagewebp')) {
            imagewebp($img, $dest, 85);
        } else {
            if ($mime === 'image/jpeg') imagejpeg($img, $dest, 85);
            elseif ($mime === 'image/png') imagepng($img, $dest, 8);
            elseif ($mime === 'image/gif') imagegif($img, $dest);
            elseif ($mime === 'image/webp' && function_exists('imagewebp')) imagewebp($img, $dest, 85);
        }
        imagedestroy($img);
        return;
    }
    $ratio = min($max_w / $w, $max_h / $h);
    $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
    $out = imagecreatetruecolor($nw, $nh);
    if ($out) {
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
        }
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if ($dest_ext === 'webp' && function_exists('imagewebp')) {
            imagewebp($out, $dest, 85);
        } elseif ($mime === 'image/jpeg') {
            imagejpeg($out, $dest, 85);
        } elseif ($mime === 'image/png') {
            imagepng($out, $dest, 8);
        } elseif ($mime === 'image/gif') {
            imagegif($out, $dest);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            imagewebp($out, $dest, 85);
        }
        imagedestroy($out);
    }
    imagedestroy($img);
}

$list = $conn->query("SELECT * FROM blog_media ORDER BY created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$conn->close();
$flash_success = $_SESSION['blog_admin_flash_success'] ?? ''; $flash_error = $_SESSION['blog_admin_flash_error'] ?? '';
unset($_SESSION['blog_admin_flash_success'], $_SESSION['blog_admin_flash_error']);
$page_title = 'Media';
ob_start();
?>
<div class="blog-admin-page">
    <h1>Media</h1>
    <?php if ($flash_success): ?><div class="blog-admin-alert blog-admin-alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
    <?php if ($flash_error): ?><div class="blog-admin-alert blog-admin-alert-error"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form-group">
        <?php echo blog_admin_csrf_field(); ?>
        <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
        <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Upload (max <?php echo $max_mb; ?>MB)</button>
    </form>
    <div class="blog-admin-media-grid">
        <?php foreach ($list as $m): ?>
        <div class="blog-admin-media-item">
            <img src="<?php echo htmlspecialchars((defined('BASE_PATH') ? BASE_PATH : '') . ($m['thumb_path'] ?: $m['file_path'])); ?>" alt="">
            <div class="meta"><?php echo htmlspecialchars($m['file_name_original']); ?></div>
            <div class="meta"><input type="text" readonly value="<?php echo htmlspecialchars((defined('BASE_PATH') ? BASE_PATH : '') . $m['file_path']); ?>" style="width:100%;font-size:0.75rem;" onclick="this.select();"></div>
            <form method="post" style="margin:0.25rem;">
                <?php echo blog_admin_csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                <button type="submit" class="blog-admin-btn blog-admin-btn-secondary" style="padding:0.25rem 0.5rem;font-size:0.85rem;" onclick="return confirm('Delete?');">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../views/layout.php';
