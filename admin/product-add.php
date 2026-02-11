<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $product_details = sanitize($_POST['product_details'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $subcategory = sanitize($_POST['subcategory'] ?? 'unisex');
    $moq = intval($_POST['moq'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../assets/images/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
            $image = 'assets/images/products/' . $file_name;
        }
    }
    
    // Handle multiple images
    $images = [];
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
        $stmt = $conn->prepare("INSERT INTO products (name, slug, description, product_details, category_id, subcategory, moq, price, image, images, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssisidsss", $name, $slug, $description, $product_details, $category_id, $subcategory, $moq, $price, $image, $images_json, $status);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to add product. " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);
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
                <a href="products.php" class="btn-secondary">Back to Products</a>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">
                    Product added successfully! <a href="products.php">View Products</a> | <a href="product-add.php">Add Another</a>
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
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
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
                    <label for="image">Main Image</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="images">Additional Images</label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Add Product</button>
                    <a href="products.php" class="btn-secondary">Cancel</a>
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

