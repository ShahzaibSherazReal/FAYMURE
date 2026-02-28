<?php

/**
 * EXAMPLE: Image upload with WebP conversion
 * ------------------------------------------
 * Copy the sections below into your admin panel (product-add, banner editor, etc.).
 * Requires: require_once __DIR__ . '/../includes/image-upload-webp.php';
 */

// =============================================================================
// EXAMPLE 1 — HTML FORM (use in your admin view/template)
// =============================================================================
/*
<form method="post" enctype="multipart/form-data" action="">
    <div class="form-group">
        <label for="product_image">Product image (JPG, PNG or WebP, max 5MB)</label>
        <input type="file"
               id="product_image"
               name="product_image"
               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
               required>
        <small>Image will be converted to WebP and compressed (quality 80).</small>
    </div>
    <button type="submit" name="submit_product" value="1">Save product</button>
</form>
*/

// =============================================================================
// EXAMPLE 2 — PHP UPLOAD HANDLER (use in your admin script when processing POST)
// =============================================================================
/*
// At top of admin script:
require_once __DIR__ . '/../includes/image-upload-webp.php';

// When handling form submit (e.g. product add/edit):
$imagePath = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product'])) {

    if (!empty($_FILES['product_image']['name']) && is_uploaded_file($_FILES['product_image']['tmp_name'])) {

        $result = upload_image_to_webp($_FILES['product_image'], 'products');
        // Second arg = subfolder under uploads/ (e.g. 'products', 'banners', or '' for uploads/)

        if (isset($result['error'])) {
            $error = $result['error'];
            // e.g. "Only JPG, PNG and WebP images are allowed." / "File must be between 1 byte and 5 MB."
        } else {
            $imagePath = $result['path'];
            // e.g. "uploads/products/20250122_143022_a1b2c3d4e5f6.webp" — store this in DB
        }
    }

    if ($error === '' && $imagePath !== null) {
        // Save to database, e.g.:
        // $stmt = $conn->prepare("INSERT INTO products (name, image, ...) VALUES (?, ?, ...)");
        // $stmt->bind_param("ss...", $name, $imagePath, ...);
        // $stmt->execute();
        header('Location: ' . $base . '/admin/products');
        exit;
    }
}

// If $error is set, re-display form with <?php echo htmlspecialchars($error); ?>
*/
