<?php
require_once 'check-auth.php';
require_once __DIR__ . '/../includes/image-upload-webp.php';

$conn = getDBConnection();
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $product_details = sanitize($_POST['product_details'] ?? '');
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
    $subcategory = sanitize($_POST['subcategory'] ?? 'unisex');
    $moq = intval($_POST['moq'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
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

    // Handle main image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . ($file_ext ?: 'jpg');
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
            $webp = convert_file_to_webp($upload_dir . $file_name);
            $image = $webp ? str_replace('../', '', $webp) : ('assets/images/products/' . $file_name);
        }
    }

    // Handle additional images
    $images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] == 0) {
                $file_ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . ($file_ext ?: 'jpg');
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $webp = convert_file_to_webp($upload_dir . $file_name);
                    $images[] = $webp ? str_replace('../', '', $webp) : ('assets/images/products/' . $file_name);
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
                $res = $slug_check->get_result();
                if (!$res || $res->num_rows === 0) {
                    break;
                }
                $suffix++;
                $slug = $original_slug . '-' . $suffix;
                $slug_check->bind_param("s", $slug);
            }
            $slug_check->close();
        }

        // Ensure product columns exist (one-time migration if table was created before sku/key_features/specifications)
        $check = $conn->query("SHOW COLUMNS FROM products LIKE 'sku'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN sku VARCHAR(100) DEFAULT NULL");
            $conn->query("ALTER TABLE products ADD COLUMN key_features TEXT");
            $conn->query("ALTER TABLE products ADD COLUMN specifications TEXT");
        }

        $images_json = json_encode($images);
        $stmt = $conn->prepare("INSERT INTO products (name, slug, sku, description, product_details, key_features, specifications, category_id, subcategory, moq, price, image, images, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            // Type string: 7s + i + s + i + d + 3s = 14 (must match bind count exactly)
            $bind_types = str_repeat('s', 7) . 'i' . 's' . 'i' . 'd' . str_repeat('s', 3);
            $stmt->bind_param($bind_types, $name, $slug, $sku, $description, $product_details, $key_features, $specifications, $category_id, $subcategory, $moq, $price, $image, $images_json, $status);
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
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$preselect_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
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
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="slug">Slug *</label>
                        <input type="text" id="slug" name="slug" required>
                        <small>URL-friendly version (e.g., leather-jacket-001)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sku">SKU</label>
                        <input type="text" id="sku" name="sku" placeholder="e.g. BAG-001 or leave blank to auto-generate">
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
                        <label for="subcategory">Subcategory</label>
                        <select id="subcategory" name="subcategory">
                            <option value="unisex">Unisex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="moq">Minimum Order Quantity (MOQ)</label>
                        <input type="number" id="moq" name="moq" min="1" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" id="price" name="price" step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="product_details">Product Details</label>
                    <textarea id="product_details" name="product_details" rows="5"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Specifications</label>
                    <div class="spec-table-wrap">
                        <table class="admin-spec-table">
                            <thead>
                                <tr><th>Spec</th><th>Value</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="spec-label">Material</td><td><input type="text" name="spec_Material" value="" placeholder="e.g. Premium Genuine Leather"></td></tr>
                                <tr><td class="spec-label">Dimensions</td><td><input type="text" name="spec_Dimensions" value="" placeholder="e.g. 40 x 30 x 10 cm"></td></tr>
                                <tr><td class="spec-label">Weight</td><td><input type="text" name="spec_Weight" value="" placeholder="e.g. 1.2 kg"></td></tr>
                                <tr><td class="spec-label">Lining</td><td><input type="text" name="spec_Lining" value="" placeholder="e.g. Premium Fabric Lining"></td></tr>
                                <tr><td class="spec-label">Hardware</td><td><input type="text" name="spec_Hardware" value="" placeholder="e.g. Premium Metal Hardware"></td></tr>
                                <tr><td class="spec-label">Origin</td><td><input type="text" name="spec_Origin" value="" placeholder="e.g. Handcrafted in Pakistan"></td></tr>
                                <tr><td class="spec-label">Category</td><td>
                                    <select name="spec_Category">
                                        <option value="">— Select —</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>"<?php echo ($preselect_category_id && (int)$cat['id'] === $preselect_category_id) ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td></tr>
                                <tr><td class="spec-label">SKU</td><td><input type="text" name="spec_SKU" value="" placeholder="e.g. BAG-001 or leave blank"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image">Main Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="images">Additional Images</label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple>
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
        // Auto-generate slug from name
        document.getElementById('name').addEventListener('input', function() {
            const slug = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        });
    </script>
</body>
</html>

