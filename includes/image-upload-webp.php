<?php

declare(strict_types=1);

/**
 * Production-ready image upload with WebP conversion.
 * Use in admin panel for products, banners, etc.
 *
 * - Accepts: jpg, jpeg, png, webp
 * - Max size: 5MB
 * - Converts to WebP (Imagick or GD), quality 80, preserves PNG transparency
 * - Unique filenames, saves under /uploads/, deletes original after conversion
 * - Returns path for DB or error message. No external libraries.
 *
 * Security: Validate MIME (finfo), getimagesize(), extension whitelist. Recommend
 * disabling PHP in uploads (e.g. .htaccess: php_flag engine off or deny *.php).
 *
 * PHP 8+. Copy-paste ready.
 */

/** Max file size in bytes (5MB) */
if (!defined('IMAGE_UPLOAD_MAX_BYTES')) {
    define('IMAGE_UPLOAD_MAX_BYTES', 5 * 1024 * 1024);
}

/** Allowed MIME types (must match allowed extensions) */
if (!defined('IMAGE_UPLOAD_ALLOWED_MIMES')) {
    define('IMAGE_UPLOAD_ALLOWED_MIMES', ['image/jpeg', 'image/png', 'image/webp']);
}

/** Allowed file extensions (lowercase) */
if (!defined('IMAGE_UPLOAD_ALLOWED_EXT')) {
    define('IMAGE_UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp']);
}

/** WebP output quality (0–100) */
if (!defined('IMAGE_UPLOAD_WEBP_QUALITY')) {
    define('IMAGE_UPLOAD_WEBP_QUALITY', 80);
}

/** Upload directory relative to project root (no leading slash) */
if (!defined('IMAGE_UPLOAD_DIR')) {
    define('IMAGE_UPLOAD_DIR', 'uploads');
}

/**
 * Validate and convert an uploaded image to WebP.
 *
 * @param array $file The $_FILES['field_name'] element (must have name, type, tmp_name, size, error)
 * @param string $subdir Optional subdirectory under uploads/ (e.g. 'products', 'banners'). Default ''.
 * @return array Success: ['path' => 'uploads/xxx.webp']. Error: ['error' => 'message']
 */
function upload_image_to_webp(array $file, string $subdir = ''): array
{
    // --- 1. Basic sanity: must be a valid upload array
    $required = ['name', 'type', 'tmp_name', 'size', 'error'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $file)) {
            return ['error' => 'Invalid upload data.'];
        }
    }

    // --- 2. PHP upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File too large.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        $msg = $messages[$file['error']] ?? 'Upload error (code ' . $file['error'] . ').';
        return ['error' => $msg];
    }

    // --- 3. File size
    if ($file['size'] <= 0 || $file['size'] > IMAGE_UPLOAD_MAX_BYTES) {
        return ['error' => 'File must be between 1 byte and 5 MB.'];
    }

    // --- 4. Secure filename: only allow one extension from whitelist
    $basename = basename($file['name']);
    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    if (!in_array($ext, IMAGE_UPLOAD_ALLOWED_EXT, true)) {
        return ['error' => 'Only JPG, PNG and WebP images are allowed.'];
    }

    // --- 5. MIME type via finfo (do not trust client-provided type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return ['error' => 'Server could not verify file type.'];
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime === false || !in_array($mime, IMAGE_UPLOAD_ALLOWED_MIMES, true)) {
        return ['error' => 'Invalid or disallowed file type.'];
    }

    // --- 6. Verify it is a real image (prevents script/other files with fake extension)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || !isset($imageInfo[2])) {
        return ['error' => 'File is not a valid image.'];
    }
    $allowedImgTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!in_array($imageInfo[2], $allowedImgTypes, true)) {
        return ['error' => 'Image type not allowed.'];
    }

    // --- 7. Build upload directory and ensure it exists
    $baseDir = rtrim(IMAGE_UPLOAD_DIR, '/');
    $subdir = trim(str_replace(['..', '\\'], '', $subdir), '/');
    $targetDir = $subdir !== '' ? $baseDir . '/' . $subdir : $baseDir;

    $absoluteDir = dirname(__DIR__) . '/' . $targetDir;
    if (!is_dir($absoluteDir)) {
        if (!@mkdir($absoluteDir, 0755, true)) {
            return ['error' => 'Upload folder is missing and could not be created.'];
        }
    }
    if (!is_writable($absoluteDir)) {
        return ['error' => 'Upload folder is not writable.'];
    }

    // --- 8. Unique filename: timestamp + random string (no extension until we write .webp)
    $unique = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
    $webpFilename = $unique . '.webp';
    $webpPath = $targetDir . '/' . $webpFilename;
    $absoluteWebpPath = $absoluteDir . '/' . $webpFilename;

    // --- 9. Convert to WebP (Imagick preferred, then GD)
    $converted = false;

    if (extension_loaded('imagick')) {
        $converted = convert_to_webp_imagick($file['tmp_name'], $absoluteWebpPath, $imageInfo);
    }

    if (!$converted && extension_loaded('gd')) {
        $converted = convert_to_webp_gd($file['tmp_name'], $absoluteWebpPath, $imageInfo);
    }

    if (!$converted) {
        return ['error' => 'Server cannot convert images to WebP. Enable Imagick or GD with WebP support.'];
    }

    // --- 10. Delete original uploaded file
    @unlink($file['tmp_name']);

    // --- 11. Return path for database (relative, e.g. uploads/products/20250101_120000_abc123.webp)
    return ['path' => $webpPath];
}

/**
 * Convert image to WebP using Imagick.
 * Preserves PNG/WebP transparency.
 */
function convert_to_webp_imagick(string $srcPath, string $destPath, array $imageInfo): bool
{
    try {
        $im = new Imagick($srcPath);
    } catch (Throwable $e) {
        return false;
    }

    try {
        $im->setImageFormat('webp');
        $im->setImageCompressionQuality(IMAGE_UPLOAD_WEBP_QUALITY);

        // Preserve transparency for PNG/WebP
        if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_WEBP) {
            $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $im->setBackgroundColor(new ImagickPixel('transparent'));
        }

        $result = $im->writeImage($destPath);
        $im->clear();
        $im->destroy();
        return $result;
    } catch (Throwable $e) {
        $im->clear();
        $im->destroy();
        return false;
    }
}

/**
 * Convert image to WebP using GD.
 * Preserves PNG transparency via imagealphablending and SaveAlpha.
 */
function convert_to_webp_gd(string $srcPath, string $destPath, array $imageInfo): bool
{
    $type = $imageInfo[2];
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($srcPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($srcPath),
        default       => null,
    };

    if ($img === false || $img === null) {
        return false;
    }

    $width = imagesx($img);
    $height = imagesy($img);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($img);
        return false;
    }

    // Preserve transparency for PNG/WebP
    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    $result = imagewebp($img, $destPath, IMAGE_UPLOAD_WEBP_QUALITY);
    imagedestroy($img);

    return $result;
}
