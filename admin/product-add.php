<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$success = false;
$error = '';
$sticky = []; // Preserve form data on validation error

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Detect empty POST (often caused by post_max_size exceeded on live hosts)
    if (empty($_POST) || (!isset($_POST['category_id']) && !isset($_POST['name']))) {
        $error = "Form submission failed. Your request may exceed the server upload limit. Try using smaller images (under 2 MB each) or fewer images, then try again.";
    } else {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $product_details = trim($_POST['product_details'] ?? '');
    $key_features = ''; // Not editable in add form; use default on product page if needed
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
    if ($status !== 'active' && $status !== 'inactive') {
        $status = 'active';
    }

    // Slug: use provided or generate from name; ensure safe for DB
    if ($slug !== '') {
        $slug = slugify($slug);
    }
    if ($slug === '' && $name !== '') {
        $slug = slugify($name);
    }
    if ($slug === '') {
        $slug = 'product-' . uniqid();
    }

    // Upload directory (always set so additional images block can use it)
    $upload_dir = '../assets/images/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

    // Handle main image upload (PNG, JPG, JPEG, WebP — store as-is, no conversion)
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if ($file_ext === '' || !in_array($file_ext, $allowed_extensions, true)) {
            $file_ext = 'jpg';
        }
        $file_name = uniqid() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
            $image = 'assets/images/products/' . $file_name;
        }
    }

    // Handle additional images (same formats, no conversion)
    $images = [];
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

    if ($name !== '' && $slug !== '' && $category_id > 0) {
        // Ensure slug is unique (append -2, -3, ... if duplicate)
        $original_slug = $slug;
        $slug_check = $conn->prepare("SELECT id FROM products WHERE slug = ?");
        if ($slug_check) {
            $slug_check->bind_param("s", $slug);
            $suffix = 0;
            while (true) {
                $slug_check->execute();
                $slug_check->store_result();
                $nr = $slug_check->num_rows;
                $slug_check->free_result();
                if ($nr === 0) {
                    break;
                }
                $suffix++;
                $slug = $original_slug . '-' . $suffix;
                $slug_check->bind_param("s", $slug);
            }
            $slug_check->close();
        }

        // Ensure product columns exist
        $check = $conn->query("SHOW COLUMNS FROM products LIKE 'sku'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN sku VARCHAR(100) DEFAULT NULL");
            $conn->query("ALTER TABLE products ADD COLUMN key_features TEXT");
            $conn->query("ALTER TABLE products ADD COLUMN specifications TEXT");
        }
        $check_cs = $conn->query("SHOW COLUMNS FROM products LIKE 'color_swatches'");
        if (!$check_cs || $check_cs->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN color_swatches TEXT DEFAULT NULL");
        }
        $check_sub = $conn->query("SHOW COLUMNS FROM products LIKE 'subcategory_id'");
        if (!$check_sub || $check_sub->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN subcategory_id INT NULL DEFAULT NULL AFTER category_id");
        }
        $check_latest = $conn->query("SHOW COLUMNS FROM products LIKE 'is_latest_creation'");
        if (!$check_latest || $check_latest->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN is_latest_creation TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }

        $product_image_list = array_merge([$image], $images);
        $color_swatches = [];
        if (!empty($_POST['color_swatch_name']) && is_array($_POST['color_swatch_name'])) {
            foreach ($_POST['color_swatch_name'] as $i => $swatch_name) {
                $swatch_name = trim($swatch_name ?? '');
                if ($swatch_name === '') continue;
                $hex = isset($_POST['color_swatch_hex'][$i]) ? trim($_POST['color_swatch_hex'][$i]) : '';
                $imgVal = isset($_POST['color_swatch_image'][$i]) ? trim($_POST['color_swatch_image'][$i]) : '';
                $imgPath = '';
                if ($imgVal !== '' && is_numeric($imgVal)) {
                    $idx = (int) $imgVal;
                    if (isset($product_image_list[$idx])) {
                        $imgPath = $product_image_list[$idx];
                    }
                }
                $color_swatches[] = ['name' => $swatch_name, 'hex' => $hex, 'image' => $imgPath];
            }
        }
        $color_swatches_json = json_encode($color_swatches);
        $images_json = json_encode($images);
        $stmt = $conn->prepare("INSERT INTO products (name, slug, sku, description, product_details, key_features, specifications, category_id, subcategory_id, subcategory, moq, price, image, images, status, is_latest_creation, color_swatches) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $subcategory_id_for_bind = $subcategory_id === null ? null : (int)$subcategory_id;
            $bind_types = "sssssssiisidsssis";
            $stmt->bind_param($bind_types, $name, $slug, $sku, $description, $product_details, $key_features, $specifications, $category_id, $subcategory_id_for_bind, $subcategory, $moq, $price, $image, $images_json, $status, $is_latest_creation, $color_swatches_json);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Failed to add product. " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database error. " . $conn->error;
        }
    } else {
        if ($category_id <= 0) {
            $error = "Please select a category.";
        } elseif ($name === '') {
            $error = "Please enter a product name.";
        } else {
            $error = "Please fill in all required fields.";
        }
        $sticky = [
            'name' => $name, 'slug' => $slug, 'sku' => $sku,
            'description' => $description, 'product_details' => $product_details,
            'category_id' => $category_id,
            'enable_subcategory' => $enable_subcategory ? '1' : '0',
            'subcategory_id' => $subcategory_id,
            'subcategory' => $subcategory,
            'moq' => $moq, 'price' => $price, 'status' => $status,
            'is_latest_creation' => $is_latest_creation,
            'spec_Material' => trim($_POST['spec_Material'] ?? ''),
            'spec_Dimensions' => trim($_POST['spec_Dimensions'] ?? ''),
            'spec_Weight' => trim($_POST['spec_Weight'] ?? ''),
            'spec_Lining' => trim($_POST['spec_Lining'] ?? ''),
            'spec_Hardware' => trim($_POST['spec_Hardware'] ?? ''),
            'spec_Origin' => trim($_POST['spec_Origin'] ?? ''),
            'spec_Category' => trim($_POST['spec_Category'] ?? ''),
            'spec_SKU' => trim($_POST['spec_SKU'] ?? ''),
        ];
    }
    }
}

$categories = [];
if ($__r = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")) {
    while ($__row = $__r->fetch_assoc()) {
        $categories[] = $__row;
    }
}
$subcategories = [];
if ($__r = $conn->query("SELECT id, category_id, name FROM subcategories WHERE deleted_at IS NULL ORDER BY sort_order, name")) {
    while ($__row = $__r->fetch_assoc()) {
        $subcategories[] = $__row;
    }
}
$preselect_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if (!empty($sticky['category_id'])) {
    $preselect_category_id = (int)$sticky['category_id'];
}
$base = defined('BASE_PATH') ? BASE_PATH : '';
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Add New Product</h1>
                <?php if ($preselect_category_id): ?>
                    <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo $preselect_category_id; ?>" class="btn-secondary">Back to Catalog</a>
                <?php endif; ?>
                <a href="<?php echo $base; ?>/admin/products" class="btn-secondary">Back to Products</a>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">
                    Product added successfully!
                    <?php if ($preselect_category_id): ?>
                        <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo $preselect_category_id; ?>">Back to Catalog</a> |
                    <?php endif; ?>
                    <a href="<?php echo $base; ?>/admin/products">View Products</a> | <a href="<?php echo $base; ?>/admin/product-add<?php echo $preselect_category_id ? '?category_id=' . $preselect_category_id : ''; ?>">Add Another</a>
                </div>
            <?php elseif ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" class="admin-form">
                <div class="form-group">
                    <label for="image">Main Image</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
                </div>
                
                <div class="form-group">
                    <label for="images">Additional Images</label>
                    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple>
                </div>
                <div class="form-group color-swatches-wrap">
                    <label>Product colors &amp; linked images</label>
                    <p class="form-hint">Add a color and link to an image. Customers can click a color on the product page to see that image.</p>
                    <div class="color-swatches-list" id="colorSwatchesContainer"></div>
                    <button type="button" id="addColorSwatch" class="btn-secondary">+ Add color</button>
                </div>
                
                <hr style="margin: 24px 0; border: 0; border-top: 1px solid var(--border-color);">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($sticky['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="slug">Slug *</label>
                        <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($sticky['slug'] ?? ''); ?>" required>
                        <small>URL-friendly version (e.g., leather-jacket-001)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sku">SKU</label>
                        <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($sticky['sku'] ?? ''); ?>" placeholder="e.g. BAG-001 or leave blank to auto-generate">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"<?php echo ($preselect_category_id && (int)$cat['id'] === $preselect_category_id) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" id="enable_subcategory" name="enable_subcategory" value="1" <?php echo (!empty($sticky['enable_subcategory']) && $sticky['enable_subcategory'] === '1') ? 'checked' : ''; ?>>
                            Add this product to a subcategory
                        </label>
                        <div id="subcategoryWrap" style="margin-top: 10px;">
                            <label for="subcategory_id">Subcategory</label>
                            <select id="subcategory_id" name="subcategory_id">
                                <option value="">— Select —</option>
                                <?php foreach ($subcategories as $sc): ?>
                                    <option value="<?php echo (int)$sc['id']; ?>"
                                        data-category-id="<?php echo (int)$sc['category_id']; ?>"
                                        <?php echo (!empty($sticky['subcategory_id']) && (int)$sticky['subcategory_id'] === (int)$sc['id']) ? ' selected' : ''; ?>>
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
                            <option value="unisex"<?php echo ($sticky['subcategory'] ?? '') === 'unisex' ? ' selected' : ''; ?>>Unisex</option>
                            <option value="male"<?php echo ($sticky['subcategory'] ?? '') === 'male' ? ' selected' : ''; ?>>Male</option>
                            <option value="female"<?php echo ($sticky['subcategory'] ?? '') === 'female' ? ' selected' : ''; ?>>Female</option>
                        </select>
                        <small>This field is actually <strong>Gender</strong> (legacy name).</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="moq">Minimum Order Quantity (MOQ)</label>
                        <input type="number" id="moq" name="moq" min="1" value="<?php echo (int)($sticky['moq'] ?? 1); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($sticky['price'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active"<?php echo ($sticky['status'] ?? '') === 'inactive' ? '' : ' selected'; ?>>Active</option>
                            <option value="inactive"<?php echo ($sticky['status'] ?? '') === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; margin-top: 28px;">
                            <input type="checkbox" name="is_latest_creation" value="1" <?php echo !empty($sticky['is_latest_creation']) ? 'checked' : ''; ?>>
                            Show in "Our Latest Creation" section on homepage
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($sticky['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="product_details">Product Details</label>
                    <textarea id="product_details" name="product_details" rows="5"><?php echo htmlspecialchars($sticky['product_details'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Specifications</label>
                    <div class="spec-table-wrap">
                        <table class="admin-spec-table">
                            <thead>
                                <tr><th>Spec</th><th>Value</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="spec-label">Material</td><td><input type="text" name="spec_Material" value="<?php echo htmlspecialchars($sticky['spec_Material'] ?? ''); ?>" placeholder="e.g. Premium Genuine Leather"></td></tr>
                                <tr><td class="spec-label">Dimensions</td><td><input type="text" name="spec_Dimensions" value="<?php echo htmlspecialchars($sticky['spec_Dimensions'] ?? ''); ?>" placeholder="e.g. 40 x 30 x 10 cm"></td></tr>
                                <tr><td class="spec-label">Weight</td><td><input type="text" name="spec_Weight" value="<?php echo htmlspecialchars($sticky['spec_Weight'] ?? ''); ?>" placeholder="e.g. 1.2 kg"></td></tr>
                                <tr><td class="spec-label">Lining</td><td><input type="text" name="spec_Lining" value="<?php echo htmlspecialchars($sticky['spec_Lining'] ?? ''); ?>" placeholder="e.g. Premium Fabric Lining"></td></tr>
                                <tr><td class="spec-label">Hardware</td><td><input type="text" name="spec_Hardware" value="<?php echo htmlspecialchars($sticky['spec_Hardware'] ?? ''); ?>" placeholder="e.g. Premium Metal Hardware"></td></tr>
                                <tr><td class="spec-label">Origin</td><td><input type="text" name="spec_Origin" value="<?php echo htmlspecialchars($sticky['spec_Origin'] ?? ''); ?>" placeholder="e.g. Handcrafted in Pakistan"></td></tr>
                                <tr><td class="spec-label">Category</td><td>
                                    <select name="spec_Category">
                                        <option value="">— Select —</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>"<?php echo (($sticky['spec_Category'] ?? '') === $cat['name'] || ($preselect_category_id && (int)$cat['id'] === $preselect_category_id)) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td></tr>
                                <tr><td class="spec-label">SKU</td><td><input type="text" name="spec_SKU" value="<?php echo htmlspecialchars($sticky['spec_SKU'] ?? ''); ?>" placeholder="e.g. BAG-001 or leave blank"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Add Product</button>
                    <?php if ($preselect_category_id): ?>
                        <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo $preselect_category_id; ?>" class="btn-secondary">Cancel</a>
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

        // Color swatches (add product: image options are indices 0 = Main, 1 = Additional 1, ... 10 = Additional 10)
        (function() {
            var container = document.getElementById('colorSwatchesContainer');
            var addBtn = document.getElementById('addColorSwatch');
            if (!container || !addBtn) return;
            var imageOpts = [
                { value: '0', label: 'Main image' },
                { value: '1', label: 'Additional 1' },
                { value: '2', label: 'Additional 2' },
                { value: '3', label: 'Additional 3' },
                { value: '4', label: 'Additional 4' },
                { value: '5', label: 'Additional 5' },
                { value: '6', label: 'Additional 6' },
                { value: '7', label: 'Additional 7' },
                { value: '8', label: 'Additional 8' },
                { value: '9', label: 'Additional 9' },
                { value: '10', label: 'Additional 10' }
            ];
            // color_swatch_name[] preset options (also prefill the hex input)
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
            addBtn.addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'color-swatch-row';
                row.innerHTML = '<select class="color-preset" title="Quick fill">' + presetOpts + '</select><input type="text" name="color_swatch_name[]" placeholder="Color name"><input type="text" name="color_swatch_hex[]" placeholder="#hex" class="color-hex-inp"><select name="color_swatch_image[]"><option value="">— Link to image —</option>' + imageOpts.map(function(o) { return '<option value="' + o.value + '">' + o.label + '</option>'; }).join('') + '</select><button type="button" class="btn-delete remove-color-swatch">Remove</button>';
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
    </script>
</body>
</html>

