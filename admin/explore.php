<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$active_tab = $_GET['tab'] ?? 'explore-page';
$success = false;
$error = '';

// Setup upload directories
$upload_dir_images = '../assets/images/';
if (!file_exists($upload_dir_images)) {
    mkdir($upload_dir_images, 0777, true);
}

// Handle explore page content save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_explore_page'])) {
    $title = sanitize($_POST['explore_title'] ?? '');
    $subtitle = sanitize($_POST['explore_subtitle'] ?? '');
    $option1_title = sanitize($_POST['option1_title'] ?? '');
    $option1_description = sanitize($_POST['option1_description'] ?? '');
    $option2_title = sanitize($_POST['option2_title'] ?? '');
    $option2_description = sanitize($_POST['option2_description'] ?? '');
    
    $fields = [
        'explore_title' => $title,
        'explore_subtitle' => $subtitle,
        'explore_option1_title' => $option1_title,
        'explore_option1_description' => $option1_description,
        'explore_option2_title' => $option2_title,
        'explore_option2_description' => $option2_description
    ];
    
    foreach ($fields as $key => $value) {
        $check = $conn->query("SELECT id FROM site_content WHERE content_key='$key'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = ?");
            $stmt->bind_param("ss", $value, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
        }
        $stmt->execute();
        if ($stmt->error) {
            error_log("Explore page save error for $key: " . $stmt->error);
        }
        $stmt->close();
    }
    
    $success = true;
    header('Location: explore.php?tab=explore-page&saved=1');
    exit;
}

// Handle browse page content save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_browse_page'])) {
    $title = sanitize($_POST['browse_title'] ?? '');
    $subtitle = sanitize($_POST['browse_subtitle'] ?? '');
    
    $fields = [
        'browse_title' => $title,
        'browse_subtitle' => $subtitle
    ];
    
    foreach ($fields as $key => $value) {
        $check = $conn->query("SELECT id FROM site_content WHERE content_key='$key'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = ?");
            $stmt->bind_param("ss", $value, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
        }
        $stmt->execute();
        if ($stmt->error) {
            error_log("Browse page save error for $key: " . $stmt->error);
        }
        $stmt->close();
    }
    
    $success = true;
    header('Location: explore.php?tab=browse-page&saved=1');
    exit;
}

// Handle custom design page content save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_custom_design_page'])) {
    $title = sanitize($_POST['custom_design_title'] ?? '');
    $subtitle = sanitize($_POST['custom_design_subtitle'] ?? '');
    
    $fields = [
        'custom_design_title' => $title,
        'custom_design_subtitle' => $subtitle
    ];
    
    foreach ($fields as $key => $value) {
        $check = $conn->query("SELECT id FROM site_content WHERE content_key='$key'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = ?");
            $stmt->bind_param("ss", $value, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
        }
        $stmt->execute();
        if ($stmt->error) {
            error_log("Custom design page save error for $key: " . $stmt->error);
        }
        $stmt->close();
    }
    
    $success = true;
    header('Location: explore.php?tab=custom-design-page&saved=1');
    exit;
}

// Get current content
function getContent($key, $default = '') {
    global $conn;
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='$key'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            return $row['content_value'];
        }
    }
    return $default;
}

$explore_title = getContent('explore_title', 'Explore Our Services');
$explore_subtitle = getContent('explore_subtitle', 'Choose how you\'d like to work with us');
$option1_title = getContent('explore_option1_title', 'Design Your Own Product');
$option1_description = getContent('explore_option1_description', 'Create a unique product from scratch. Share your vision, upload inspiration images, and let us bring your design to life.');
$option2_title = getContent('explore_option2_title', 'Browse & Customize');
$option2_description = getContent('explore_option2_description', 'Browse our product categories and request quotes for bulk orders. You can also request customizations to our existing products.');

$browse_title = getContent('browse_title', 'Browse & Customize');
$browse_subtitle = getContent('browse_subtitle', 'Browse our product categories and request quotes for bulk orders. You can also request customizations to our existing products.');

$custom_design_title = getContent('custom_design_title', 'Design Your Own Product');
$custom_design_subtitle = getContent('custom_design_subtitle', 'Create a unique product from scratch. Share your vision, upload inspiration images, and let us bring your design to life.');

// Get products and categories for management
$products = [];
$categories = [];

$products_result = $conn->query("SELECT p.*, c.name as category_name FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.deleted_at IS NULL 
                                ORDER BY p.created_at DESC");
if ($products_result) {
    $products = $products_result->fetch_all(MYSQLI_ASSOC);
}

$categories_result = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
if ($categories_result) {
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    $stmt = $conn->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->close();
    header('Location: explore.php?tab=products&saved=1');
    exit;
}

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_category'])) {
    $category_id = intval($_POST['category_id']);
    $stmt = $conn->prepare("UPDATE categories SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $stmt->close();
    header('Location: explore.php?tab=categories&saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $success = true;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore & Browse Management - <?php echo SITE_NAME; ?> Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Explore & Browse Management</h1>
            </div>
            
            <?php if ($success): ?>
                <div class="success-notification">
                    <i class="fas fa-check-circle"></i> Changes saved successfully!
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="admin-tabs">
                <a href="?tab=explore-page" class="tab-link <?php echo $active_tab == 'explore-page' ? 'active' : ''; ?>">
                    <i class="fas fa-compass"></i> Explore Page
                </a>
                <a href="?tab=browse-page" class="tab-link <?php echo $active_tab == 'browse-page' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag"></i> Browse Page
                </a>
                <a href="?tab=custom-design-page" class="tab-link <?php echo $active_tab == 'custom-design-page' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> Custom Design Page
                </a>
                <a href="?tab=products" class="tab-link <?php echo $active_tab == 'products' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="?tab=categories" class="tab-link <?php echo $active_tab == 'categories' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </div>
            
            <!-- Explore Page Tab -->
            <?php if ($active_tab == 'explore-page'): ?>
                <form method="POST" class="admin-form">
                    <div class="form-section">
                        <h2 class="section-title">Explore Page Content</h2>
                        
                        <div class="form-group">
                            <label for="explore_title">Page Title *</label>
                            <input type="text" id="explore_title" name="explore_title" value="<?php echo htmlspecialchars($explore_title); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="explore_subtitle">Page Subtitle *</label>
                            <input type="text" id="explore_subtitle" name="explore_subtitle" value="<?php echo htmlspecialchars($explore_subtitle); ?>" required>
                        </div>
                        
                        <h3 class="subsection-title">Option 1: Design Your Own</h3>
                        <div class="form-group">
                            <label for="option1_title">Title *</label>
                            <input type="text" id="option1_title" name="option1_title" value="<?php echo htmlspecialchars($option1_title); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="option1_description">Description *</label>
                            <textarea id="option1_description" name="option1_description" rows="3" required><?php echo htmlspecialchars($option1_description); ?></textarea>
                        </div>
                        
                        <h3 class="subsection-title">Option 2: Browse & Customize</h3>
                        <div class="form-group">
                            <label for="option2_title">Title *</label>
                            <input type="text" id="option2_title" name="option2_title" value="<?php echo htmlspecialchars($option2_title); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="option2_description">Description *</label>
                            <textarea id="option2_description" name="option2_description" rows="3" required><?php echo htmlspecialchars($option2_description); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_explore_page" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="../explore.php" target="_blank" class="btn-secondary">
                            <i class="fas fa-external-link-alt"></i> View Page
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Browse Page Tab -->
            <?php if ($active_tab == 'browse-page'): ?>
                <form method="POST" class="admin-form">
                    <div class="form-section">
                        <h2 class="section-title">Browse Page Content</h2>
                        
                        <div class="form-group">
                            <label for="browse_title">Page Title *</label>
                            <input type="text" id="browse_title" name="browse_title" value="<?php echo htmlspecialchars($browse_title); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="browse_subtitle">Page Subtitle *</label>
                            <textarea id="browse_subtitle" name="browse_subtitle" rows="3" required><?php echo htmlspecialchars($browse_subtitle); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_browse_page" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="../explore-browse.php" target="_blank" class="btn-secondary">
                            <i class="fas fa-external-link-alt"></i> View Page
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Custom Design Page Tab -->
            <?php if ($active_tab == 'custom-design-page'): ?>
                <form method="POST" class="admin-form">
                    <div class="form-section">
                        <h2 class="section-title">Custom Design Page Content</h2>
                        
                        <div class="form-group">
                            <label for="custom_design_title">Page Title *</label>
                            <input type="text" id="custom_design_title" name="custom_design_title" value="<?php echo htmlspecialchars($custom_design_title); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="custom_design_subtitle">Page Subtitle *</label>
                            <textarea id="custom_design_subtitle" name="custom_design_subtitle" rows="3" required><?php echo htmlspecialchars($custom_design_subtitle); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_custom_design_page" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="../explore-custom-design.php" target="_blank" class="btn-secondary">
                            <i class="fas fa-external-link-alt"></i> View Page
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Products Tab -->
            <?php if ($active_tab == 'products'): ?>
                <div class="admin-table-section">
                    <div class="table-header">
                        <h2>Products</h2>
                        <a href="product-add.php" class="btn-primary">
                            <i class="fas fa-plus"></i> Add New Product
                        </a>
                    </div>
                    
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>MOQ</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No products found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td>
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-image" style="font-size: 24px; color: #ccc;"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $product['price'] ? '$' . number_format($product['price'], 2) : 'N/A'; ?></td>
                                            <td><?php echo $product['moq'] ?? 1; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $product['status']; ?>">
                                                    <?php echo ucfirst($product['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn-icon btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Categories Tab -->
            <?php if ($active_tab == 'categories'): ?>
                <div class="admin-table-section">
                    <div class="table-header">
                        <h2>Categories</h2>
                        <a href="shop.php?tab=categories" class="btn-primary">
                            <i class="fas fa-plus"></i> Manage Categories
                        </a>
                    </div>
                    
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No categories found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo $category['id']; ?></td>
                                            <td>
                                                <?php if (!empty($category['image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($category['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-image" style="font-size: 24px; color: #ccc;"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)) . (strlen($category['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                            <td>
                                                <a href="shop.php?tab=categories&edit=<?php echo $category['id']; ?>" class="btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn-icon btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

