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
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $product_details = sanitize($_POST['product_details'] ?? '');
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
    $subcategory = sanitize($_POST['subcategory'] ?? 'unisex');
    $moq = intval($_POST['moq'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    
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
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../assets/images/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        // Remove old image if exists
        if (!empty($product['image']) && file_exists('../' . $product['image'])) {
            unlink('../' . $product['image']);
        }
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
            $image = 'assets/images/products/' . $file_name;
        }
    }
    
    $images = json_decode($product['images'] ?? '[]', true) ?: [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] == 0) {
                $file_ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . $file_ext;
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $images[] = 'assets/images/products/' . $file_name;
                }
            }
        }
    }
    
    if ($name && $slug && $category_id) {
        $images_json = json_encode($images);
        $stmt = $conn->prepare("UPDATE products SET name=?, slug=?, sku=?, description=?, product_details=?, key_features=?, specifications=?, category_id=?, subcategory=?, moq=?, price=?, image=?, images=?, status=? WHERE id=?");
        // 15 params: 7 strings, int, string, int, double, 3 strings, int (product_id)
        $bind_types = str_repeat('s', 7) . 'i' . 's' . 'i' . 'd' . str_repeat('s', 3) . 'i';
        $stmt->bind_param($bind_types, $name, $slug, $sku, $description, $product_details, $key_features, $specifications, $category_id, $subcategory, $moq, $price, $image, $images_json, $status, $product_id);
        
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
$images = json_decode($product['images'] ?? '[]', true) ?: [];
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
                        <label for="subcategory">Subcategory</label>
                        <select id="subcategory" name="subcategory">
                            <option value="unisex" <?php echo ($product['subcategory'] ?? 'unisex') == 'unisex' ? 'selected' : ''; ?>>Unisex</option>
                            <option value="male" <?php echo ($product['subcategory'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($product['subcategory'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
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
                
                <div class="form-group">
                    <label>Main Image</label>
                    <?php if ($product['image']): ?>
                        <div style="margin-bottom: 15px;">
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="Current" style="max-width: 200px; display: block; margin-bottom: 10px; border: 1px solid var(--border-color);">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="remove_main_image" value="1">
                                <button type="submit" class="btn-delete" onclick="return confirm('Remove main image?')">Remove Image</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <label for="image"><?php echo $product['image'] ? 'Upload New Main Image' : 'Upload Main Image'; ?></label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label>Additional Images</label>
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery" style="margin-bottom: 15px;">
                            <?php foreach ($images as $index => $img): ?>
                                <div class="gallery-item" style="position: relative; display: inline-block; margin: 5px;">
                                    <img src="../<?php echo htmlspecialchars($img); ?>" alt="Gallery" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid var(--border-color);">
                                    <form method="POST" style="position: absolute; top: 0; right: 0;">
                                        <input type="hidden" name="remove_image_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="btn-delete" style="padding: 2px 6px; font-size: 10px;" onclick="return confirm('Remove this image?')" title="Remove">×</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <label for="images">Add More Images</label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple>
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
</body>
</html>

