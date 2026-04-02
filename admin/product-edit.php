<?php
require_once 'check-auth.php';

$product_id = $_GET['id'] ?? 0;
$conn = getDBConnection();

$product = null;
if ($product_id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$product) {
    redirect('products.php');
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $product_details = trim($_POST['product_details'] ?? '');
    $key_features = $product['key_features'] ?? ''; // Keep existing; field removed from form
    // Build specifications from table fields
    $spec_keys = ['Material', 'Dimensions', 'Weight', 'Lining', 'Hardware', 'Origin', 'Category', 'SKU'];
    $spec_lines = [];
    foreach ($spec_keys as $key) {
        $val = trim($_POST['spec_' . $key] ?? '');
        $spec_lines[] = $key . ': ' . $val;
    }
    $specifications = implode("\n", $spec_lines);
    $category_id = intval($_POST['category_id'] ?? 0);
    $enable_subcategory = isset($_POST['enable_subcategory']) && $_POST['enable_subcategory'] === '1';
    $subcategory_id = null;
    if ($enable_subcategory && isset($_POST['subcategory_id']) && $_POST['subcategory_id'] !== '') {
        $subcategory_id = (int)$_POST['subcategory_id'];
        if ($subcategory_id <= 0) $subcategory_id = null;
    }
    $subcategory = sanitize($_POST['subcategory'] ?? 'unisex');
    $moq = intval($_POST['moq'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    $is_latest_creation = isset($_POST['is_latest_creation']) ? 1 : 0;
    
    // Handle remove main image
    if (isset($_POST['remove_main_image'])) {
        if (!empty($product['image']) && file_exists('../' . $product['image'])) {
            unlink('../' . $product['image']);
        }
        $image = '';
    } else {
        $image = $product['image'];
    }
    
    // Handle remove additional image
    if (isset($_POST['remove_image_index'])) {
        $images = json_decode($product['images'] ?? '[]', true) ?: [];
        $remove_index = intval($_POST['remove_image_index']);
        if (isset($images[$remove_index])) {
            $img_path = '../' . $images[$remove_index];
            if (file_exists($img_path)) {
                unlink($img_path);
            }
            unset($images[$remove_index]);
            $images = array_values($images); // Re-index
            $images_json = json_encode($images);
            $stmt = $conn->prepare("UPDATE products SET images=? WHERE id=?");
            $stmt->bind_param("si", $images_json, $product_id);
            $stmt->execute();
            $stmt->close();
            $product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
        }
    }
    
    // Handle image upload (PNG, JPG, JPEG, WebP — store as-is, no conversion)
    $upload_dir = '../assets/images/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        if (!empty($product['image']) && file_exists('../' . $product['image'])) {
            unlink('../' . $product['image']);
        }
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if ($file_ext === '' || !in_array($file_ext, $allowed_extensions, true)) {
            $file_ext = 'jpg';
        }
        $file_name = uniqid() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
            $image = 'assets/images/products/' . $file_name;
        }
    }

    $images = json_decode($product['images'] ?? '[]', true) ?: [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] == 0) {
                $file_ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                if ($file_ext === '' || !in_array($file_ext, $allowed_extensions, true)) {
                    $file_ext = 'jpg';
                }
                $file_name = uniqid() . '.' . $file_ext;
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $images[] = 'assets/images/products/' . $file_name;
                }
            }
        }
    }
    
    if ($name && $slug && $category_id) {
        $check_latest = $conn->query("SHOW COLUMNS FROM products LIKE 'is_latest_creation'");
        if (!$check_latest || $check_latest->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN is_latest_creation TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }
        $check_cs = $conn->query("SHOW COLUMNS FROM products LIKE 'color_swatches'");
        if (!$check_cs || $check_cs->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN color_swatches TEXT DEFAULT NULL");
        }
        $color_swatches = [];
        if (!empty($_POST['color_swatch_name']) && is_array($_POST['color_swatch_name'])) {
            foreach ($_POST['color_swatch_name'] as $i => $swatch_name) {
                $swatch_name = trim($swatch_name ?? '');
                if ($swatch_name === '') continue;
                $hex = isset($_POST['color_swatch_hex'][$i]) ? trim($_POST['color_swatch_hex'][$i]) : '';
                $img = isset($_POST['color_swatch_image'][$i]) ? trim($_POST['color_swatch_image'][$i]) : '';
                $color_swatches[] = ['name' => $swatch_name, 'hex' => $hex, 'image' => $img];
            }
        }
        $color_swatches_json = json_encode($color_swatches);
        $images_json = json_encode($images);
        $stmt = $conn->prepare("UPDATE products SET name=?, slug=?, sku=?, description=?, product_details=?, key_features=?, specifications=?, category_id=?, subcategory_id=?, subcategory=?, moq=?, price=?, image=?, images=?, status=?, is_latest_creation=?, color_swatches=? WHERE id=?");
        $subcategory_id_for_bind = $subcategory_id === null ? null : (int)$subcategory_id;
        $bind_types = "sssssssiisidsssisi";
        $stmt->bind_param($bind_types, $name, $slug, $sku, $description, $product_details, $key_features, $specifications, $category_id, $subcategory_id_for_bind, $subcategory, $moq, $price, $image, $images_json, $status, $is_latest_creation, $color_swatches_json, $product_id);
        
        if ($stmt->execute()) {
            $success = true;
            $product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
        } else {
            $error = "Failed to update product. " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$subcategories = $conn->query("SELECT id, category_id, name FROM subcategories WHERE deleted_at IS NULL ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$images = json_decode($product['images'] ?? '[]', true) ?: [];
$product_image_list = [];
if (!empty($product['image'])) $product_image_list[] = $product['image'];
$product_image_list = array_merge($product_image_list, $images);
$color_swatches_parsed = json_decode($product['color_swatches'] ?? '[]', true) ?: [];
$common_colors = [
    'Black' => '#000000',
    'White' => '#FFFFFF',
    'Brown' => '#5D4037',
    'Navy' => '#001F3F',
    'Tan' => '#D2B48C',
    'Red' => '#B71C1C',
    'Burgundy' => '#722F37',
    'Grey' => '#616161',
    'Oxblood' => '#4A0000',
    'Espresso' => '#4B2E2A',
    'Whiskey' => '#7A4A2B',
    'Beige' => '#F5F5DC',
    'Camel' => '#C69C6D',
    'Rust' => '#B7410E',
    'Olive green' => '#6B8E23',
];
$return_catalog = isset($_GET['return']) && $_GET['return'] === 'catalog' && isset($_GET['category']);
$return_category_id = $return_catalog ? (int)$_GET['category'] : 0;

// Parse specifications for table (Label: Value per line)
$spec_parsed = [];
if (!empty(trim($product['specifications'] ?? ''))) {
    foreach (explode("\n", $product['specifications']) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $pos = strpos($line, ':');
        if ($pos !== false) {
            $spec_parsed[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }
    }
}
$spec_Material = $spec_parsed['Material'] ?? '';
$spec_Dimensions = $spec_parsed['Dimensions'] ?? '';
$spec_Weight = $spec_parsed['Weight'] ?? '';
$spec_Lining = $spec_parsed['Lining'] ?? '';
$spec_Hardware = $spec_parsed['Hardware'] ?? '';
$spec_Origin = $spec_parsed['Origin'] ?? '';
$spec_Category = $spec_parsed['Category'] ?? '';
$spec_SKU = $spec_parsed['SKU'] ?? '';

$base = defined('BASE_PATH') ? BASE_PATH : '';
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .color-swatches-wrap { margin-bottom: 8px; }
        .color-swatches-list { max-height: 200px; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px; background: #fafafa; margin-bottom: 8px; }
        .color-swatches-list::-webkit-scrollbar { width: 6px; }
        .color-swatches-list::-webkit-scrollbar-thumb { background: #bbb; border-radius: 3px; }
        .color-swatch-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
        .color-swatch-row:last-child { margin-bottom: 0; }
        .color-swatch-row .color-preset { width: 130px; padding: 5px 8px; font-size: 12px; }
        .color-swatch-row input[type="text"] { width: 90px; padding: 5px 8px; font-size: 12px; }
        .color-swatch-row .color-hex-inp { width: 68px; padding: 5px 6px; font-size: 11px; }
        .color-swatch-row select[name="color_swatch_image[]"] { min-width: 120px; padding: 5px 8px; font-size: 12px; flex: 1; max-width: 160px; }
        .color-swatch-row .remove-color-swatch { padding: 4px 8px; font-size: 11px; flex-shrink: 0; }
        .form-hint { font-size: 12px; color: #666; margin-bottom: 8px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Edit Product</h1>
                <?php if ($return_category_id): ?>
                    <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo $return_category_id; ?>" class="btn-secondary">Back to Catalog</a>
                <?php endif; ?>
                <a href="<?php echo $base; ?>/admin/products" class="btn-secondary">Back to Products</a>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">Product updated successfully! <?php if ($return_category_id): ?><a href="<?php echo $base; ?>/admin/catalog?category=<?php echo $return_category_id; ?>">Back to Catalog</a><?php endif; ?></div>
            <?php elseif ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <div class="form-group">
                    <label>Main Image</label>
                    <?php if ($product['image']): ?>
                        <div style="margin-bottom: 15px;">
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="Current" style="max-width: 200px; display: block; margin-bottom: 10px; border: 1px solid var(--border-color);">
                            <button type="button" class="btn-delete" onclick="submitRemoveMainImage()">Remove Image</button>
                        </div>
                    <?php endif; ?>
                    <label for="image"><?php echo $product['image'] ? 'Upload New Main Image' : 'Upload Main Image'; ?></label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                </div>
                
                <div class="form-group">
                    <label>Additional Images</label>
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery" style="margin-bottom: 15px;">
                            <?php foreach ($images as $index => $img): ?>
                                <div class="gallery-item" style="position: relative; display: inline-block; margin: 5px;">
                                    <img src="../<?php echo htmlspecialchars($img); ?>" alt="Gallery" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid var(--border-color);">
                                    <button type="button" class="btn-delete" style="position: absolute; top: 0; right: 0; padding: 2px 6px; font-size: 10px;" onclick="submitRemoveImageIndex(<?php echo $index; ?>)" title="Remove">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <label for="images">Add More Images</label>
                    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple>
                </div>
                
                <div class="form-group color-swatches-wrap">
                    <label>Product colors &amp; linked images</label>
                    <p class="form-hint">Add a color and link to an image. Customers can click a color on the product page to see that image.</p>
                    <div class="color-swatches-list" id="colorSwatchesContainer">
                        <?php foreach ($color_swatches_parsed as $idx => $swatch): 
                            $sname = $swatch['name'] ?? '';
                            $shex = $swatch['hex'] ?? '';
                            $simg = $swatch['image'] ?? '';
                        ?>
                        <div class="color-swatch-row">
                            <select class="color-preset" title="Quick fill">
                                <option value="">— Choose color —</option>
                                <?php foreach ($common_colors as $cname => $chex): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>|<?php echo htmlspecialchars($chex); ?>"<?php echo ($sname === $cname) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__"<?php echo ($sname !== '' && !isset($common_colors[$sname])) ? ' selected' : ''; ?>>Add custom color</option>
                            </select>
                            <input type="text" name="color_swatch_name[]" placeholder="Color name" value="<?php echo htmlspecialchars($sname); ?>">
                            <input type="text" name="color_swatch_hex[]" placeholder="#hex" value="<?php echo htmlspecialchars($shex); ?>" class="color-hex-inp">
                            <select name="color_swatch_image[]">
                                <option value="">— Link to image —</option>
                                <?php foreach ($product_image_list as $imgIdx => $imgPath): ?>
                                    <option value="<?php echo htmlspecialchars($imgPath); ?>"<?php echo ($simg === $imgPath) ? ' selected' : ''; ?>><?php echo $imgIdx === 0 ? 'Main image' : ('Additional ' . $imgIdx); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-delete remove-color-swatch">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="addColorSwatch" class="btn-secondary">+ Add color</button>
                    <script type="application/json" id="productImageListJson"><?php echo json_encode(array_map(function($p, $i) { return ['path' => $p, 'label' => $i === 0 ? 'Main image' : ('Additional ' . $i)]; }, $product_image_list, array_keys($product_image_list))); ?></script>
                </div>
                
                <hr style="margin: 24px 0; border: 0; border-top: 1px solid var(--border-color);">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="slug">Slug *</label>
                        <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($product['slug']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sku">SKU</label>
                        <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" placeholder="Leave blank to auto-generate">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" id="enable_subcategory" name="enable_subcategory" value="1" <?php echo (!empty($product['subcategory_id'])) ? 'checked' : ''; ?>>
                            Add this product to a subcategory
                        </label>
                        <div id="subcategoryWrap" style="margin-top: 10px;">
                            <label for="subcategory_id">Subcategory</label>
                            <select id="subcategory_id" name="subcategory_id">
                                <option value="">— Select —</option>
                                <?php foreach ($subcategories as $sc): ?>
                                    <option value="<?php echo (int)$sc['id']; ?>"
                                        data-category-id="<?php echo (int)$sc['category_id']; ?>"
                                        <?php echo (!empty($product['subcategory_id']) && (int)$product['subcategory_id'] === (int)$sc['id']) ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Optional: visible on the user category page as a filter.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="subcategory">Subcategory</label>
                        <select id="subcategory" name="subcategory">
                            <option value="unisex" <?php echo ($product['subcategory'] ?? 'unisex') == 'unisex' ? 'selected' : ''; ?>>Unisex</option>
                            <option value="male" <?php echo ($product['subcategory'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($product['subcategory'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <small>This field is actually <strong>Gender</strong> (legacy name).</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="moq">MOQ</label>
                        <input type="number" id="moq" name="moq" min="1" value="<?php echo $product['moq'] ?? 1; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $product['price'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($product['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($product['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; margin-top: 28px;">
                            <input type="checkbox" name="is_latest_creation" value="1" <?php echo !empty($product['is_latest_creation']) ? 'checked' : ''; ?>>
                            Show in "Our Latest Creation" section on homepage
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="product_details">Product Details</label>
                    <textarea id="product_details" name="product_details" rows="5"><?php echo htmlspecialchars($product['product_details'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Specifications</label>
                    <div class="spec-table-wrap">
                        <table class="admin-spec-table">
                            <thead>
                                <tr><th>Spec</th><th>Value</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="spec-label">Material</td><td><input type="text" name="spec_Material" value="<?php echo htmlspecialchars($spec_Material); ?>" placeholder="e.g. Premium Genuine Leather"></td></tr>
                                <tr><td class="spec-label">Dimensions</td><td><input type="text" name="spec_Dimensions" value="<?php echo htmlspecialchars($spec_Dimensions); ?>" placeholder="e.g. 40 x 30 x 10 cm"></td></tr>
                                <tr><td class="spec-label">Weight</td><td><input type="text" name="spec_Weight" value="<?php echo htmlspecialchars($spec_Weight); ?>" placeholder="e.g. 1.2 kg"></td></tr>
                                <tr><td class="spec-label">Lining</td><td><input type="text" name="spec_Lining" value="<?php echo htmlspecialchars($spec_Lining); ?>" placeholder="e.g. Premium Fabric Lining"></td></tr>
                                <tr><td class="spec-label">Hardware</td><td><input type="text" name="spec_Hardware" value="<?php echo htmlspecialchars($spec_Hardware); ?>" placeholder="e.g. Premium Metal Hardware"></td></tr>
                                <tr><td class="spec-label">Origin</td><td><input type="text" name="spec_Origin" value="<?php echo htmlspecialchars($spec_Origin); ?>" placeholder="e.g. Handcrafted in Pakistan"></td></tr>
                                <tr><td class="spec-label">Category</td><td>
                                    <select name="spec_Category">
                                        <option value="">— Select —</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>"<?php echo ($spec_Category !== '' && $spec_Category === $cat['name']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td></tr>
                                <tr><td class="spec-label">SKU</td><td><input type="text" name="spec_SKU" value="<?php echo htmlspecialchars($spec_SKU); ?>" placeholder="e.g. BAG-001"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Update Product</button>
                    <?php if ($return_category_id): ?>
                        <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo $return_category_id; ?>" class="btn-secondary">Cancel</a>
                    <?php else: ?>
                        <a href="<?php echo $base; ?>/admin/products" class="btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>
    <script>
        (function() {
            var nameEl = document.getElementById('name');
            var slugEl = document.getElementById('slug');
            if (nameEl && slugEl) {
                function slugify(text) {
                    if (!text || typeof text !== 'string') return '';
                    return text.toLowerCase().trim()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                }
                var lastAutoSlug = slugify(nameEl.value);
                slugEl.addEventListener('input', function() { lastAutoSlug = null; });
                nameEl.addEventListener('input', function() {
                    var newSlug = slugify(nameEl.value);
                    if (slugEl.value === '' || slugEl.value === lastAutoSlug) {
                        slugEl.value = newSlug;
                        lastAutoSlug = newSlug;
                    } else {
                        lastAutoSlug = null;
                    }
                });
            }
        })();
        (function() {
            var container = document.getElementById('colorSwatchesContainer');
            var addBtn = document.getElementById('addColorSwatch');
            var imageListJson = document.getElementById('productImageListJson');
            if (!container || !addBtn) return;
            var imageOpts = imageListJson ? JSON.parse(imageListJson.textContent || '[]') : [];
            addBtn.addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'color-swatch-row';
                // This list is used when adding additional color rows dynamically.
                // Keep it in sync with $common_colors so existing swatches can be edited.
                var presetOpts = '<option value="">— Choose color —</option>' +
                    '<option value="Black|#000000">Black</option>' +
                    '<option value="White|#FFFFFF">White</option>' +
                    '<option value="Brown|#5D4037">Brown</option>' +
                    '<option value="Navy|#001F3F">Navy</option>' +
                    '<option value="Tan|#D2B48C">Tan</option>' +
                    '<option value="Red|#B71C1C">Red</option>' +
                    '<option value="Burgundy|#722F37">Burgundy</option>' +
                    '<option value="Grey|#616161">Grey</option>' +
                    '<option value="Oxblood|#4A0000">Oxblood</option>' +
                    '<option value="Espresso|#4B2E2A">Espresso</option>' +
                    '<option value="Whiskey|#7A4A2B">Whiskey</option>' +
                    '<option value="Beige|#F5F5DC">Beige</option>' +
                    '<option value="Camel|#C69C6D">Camel</option>' +
                    '<option value="Rust|#B7410E">Rust</option>' +
                    '<option value="Olive green|#6B8E23">Olive green</option>' +
                    '<option value="__custom__">Add custom color</option>';
                row.innerHTML = '<select class="color-preset" title="Quick fill">' + presetOpts + '</select><input type="text" name="color_swatch_name[]" placeholder="Color name"><input type="text" name="color_swatch_hex[]" placeholder="#hex" class="color-hex-inp"><select name="color_swatch_image[]"><option value="">— Link to image —</option>' + imageOpts.map(function(o) { return '<option value="' + (o.path || '').replace(/"/g, '&quot;') + '">' + (o.label || '').replace(/</g, '&lt;') + '</option>'; }).join('') + '</select><button type="button" class="btn-delete remove-color-swatch">Remove</button>';
                container.appendChild(row);
                bindRow(row);
            });
            function bindRow(row) {
                var preset = row.querySelector('.color-preset');
                if (preset) preset.addEventListener('change', function() {
                    var v = this.value;
                    var nameInp = row.querySelector('input[name="color_swatch_name[]"]');
                    var hexInp = row.querySelector('input[name="color_swatch_hex[]"]');
                    if (v === '__custom__') { if (nameInp) nameInp.value = ''; if (hexInp) hexInp.value = ''; return; }
                    if (v && v.indexOf('|') !== -1) { var parts = v.split('|'); if (nameInp) nameInp.value = parts[0] || ''; if (hexInp) hexInp.value = parts[1] || ''; }
                });
                var rm = row.querySelector('.remove-color-swatch');
                if (rm) rm.addEventListener('click', function() { row.remove(); });
            }
            container.querySelectorAll('.color-swatch-row').forEach(bindRow);
        })();

        // Subcategory checkbox + filter options by chosen category
        (function() {
            var enable = document.getElementById('enable_subcategory');
            var wrap = document.getElementById('subcategoryWrap');
            var catSel = document.getElementById('category_id');
            var subSel = document.getElementById('subcategory_id');
            if (!enable || !wrap || !catSel || !subSel) return;

            var allOpts = Array.from(subSel.querySelectorAll('option'));
            function applyCategoryFilter() {
                var catId = parseInt(catSel.value || '0', 10);
                var current = subSel.value;
                allOpts.forEach(function(opt) {
                    var dc = opt.getAttribute('data-category-id');
                    if (!dc) return;
                    opt.hidden = !(catId > 0 && parseInt(dc || '0', 10) === catId);
                });
                var selectedOpt = subSel.querySelector('option[value="' + (current || '') + '"]');
                if (selectedOpt && selectedOpt.hidden) subSel.value = '';
            }

            function applyEnabled() {
                wrap.style.display = enable.checked ? 'block' : 'none';
                if (!enable.checked) subSel.value = '';
            }

            enable.addEventListener('change', applyEnabled);
            catSel.addEventListener('change', applyCategoryFilter);
            applyCategoryFilter();
            applyEnabled();
        })();

        function submitRemoveMainImage() {
            if (confirm('Remove main image?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_main_image';
                input.value = '1';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function submitRemoveImageIndex(index) {
            if (confirm('Remove this image?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_image_index';
                input.value = index;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

