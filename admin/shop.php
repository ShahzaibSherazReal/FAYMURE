<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$active_tab = $_GET['tab'] ?? 'products';

// Handle shop hero content save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['shop_hero_action'])) {
    $hero_title = sanitize($_POST['shop_hero_title'] ?? '');
    $hero_subtitle = sanitize($_POST['shop_hero_subtitle'] ?? '');
    
    if ($hero_title) {
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('shop_hero_title', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $hero_title, $hero_title);
        $stmt->execute();
        $stmt->close();
    }
    
    if ($hero_subtitle) {
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('shop_hero_subtitle', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $hero_subtitle, $hero_subtitle);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: shop.php?tab=hero&saved=1');
    exit;
}

$upload_dir = '../assets/images/categories/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle category add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_action'])) {
    $cat_action = $_POST['category_action'];
    if ($cat_action == 'add' || $cat_action == 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $slug = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $image = '';
        
        if ($cat_action == 'edit') {
            $id = intval($_POST['id']);
            $current = $conn->query("SELECT image FROM categories WHERE id = $id")->fetch_assoc();
            $image = $current['image'] ?? '';
            
            if (isset($_POST['remove_image'])) {
                if (!empty($image) && file_exists('../' . $image)) {
                    unlink('../' . $image);
                }
                $image = '';
            }
        }
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            if ($cat_action == 'edit' && !empty($current['image']) && file_exists('../' . $current['image'])) {
                unlink('../' . $current['image']);
            }
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
                $image = 'assets/images/categories/' . $file_name;
            }
        }
        
        if ($name && $slug) {
            if ($cat_action == 'add') {
                $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, image) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $slug, $description, $image);
            } else {
                $stmt = $conn->prepare("UPDATE categories SET name=?, slug=?, description=?, image=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $slug, $description, $image, $id);
            }
            $stmt->execute();
            $stmt->close();
            header('Location: shop.php?tab=categories&saved=1');
            exit;
        }
    }
}

// Handle bulk actions for products
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'delete_product' || $action == 'delete_selected_products' || $action == 'delete_all_products') {
        if ($action == 'delete_product') {
            $id = intval($_POST['id']);
            $product = $conn->query("SELECT image, images FROM products WHERE id = $id")->fetch_assoc();
            // Delete images
            if ($product['image'] && file_exists('../' . $product['image'])) {
                unlink('../' . $product['image']);
            }
            $images = json_decode($product['images'] ?? '[]', true) ?: [];
            foreach ($images as $img) {
                if (file_exists('../' . $img)) {
                    unlink('../' . $img);
                }
            }
            $conn->query("UPDATE products SET deleted_at = NOW() WHERE id = $id");
        } elseif ($action == 'delete_selected_products') {
            $ids = json_decode($_POST['selected_ids'] ?? '[]', true);
            if (!empty($ids)) {
                $ids_str = implode(',', array_map('intval', $ids));
                $products = $conn->query("SELECT image, images FROM products WHERE id IN ($ids_str)")->fetch_all(MYSQLI_ASSOC);
                foreach ($products as $product) {
                    if ($product['image'] && file_exists('../' . $product['image'])) {
                        unlink('../' . $product['image']);
                    }
                    $images = json_decode($product['images'] ?? '[]', true) ?: [];
                    foreach ($images as $img) {
                        if (file_exists('../' . $img)) {
                            unlink('../' . $img);
                        }
                    }
                }
                $conn->query("UPDATE products SET deleted_at = NOW() WHERE id IN ($ids_str)");
            }
        } elseif ($action == 'delete_all_products') {
            $products = $conn->query("SELECT image, images FROM products WHERE deleted_at IS NULL")->fetch_all(MYSQLI_ASSOC);
            foreach ($products as $product) {
                if ($product['image'] && file_exists('../' . $product['image'])) {
                    unlink('../' . $product['image']);
                }
                $images = json_decode($product['images'] ?? '[]', true) ?: [];
                foreach ($images as $img) {
                    if (file_exists('../' . $img)) {
                        unlink('../' . $img);
                    }
                }
            }
            $conn->query("UPDATE products SET deleted_at = NOW() WHERE deleted_at IS NULL");
        }
        header('Location: shop.php?tab=products&deleted=1');
        exit;
    }
    
    // Handle category actions
    if ($action == 'delete_category' || $action == 'delete_selected_categories' || $action == 'delete_all_categories') {
        $upload_dir = '../assets/images/categories/';
        if ($action == 'delete_category') {
            $id = intval($_POST['id']);
            $cat = $conn->query("SELECT image FROM categories WHERE id = $id")->fetch_assoc();
            if ($cat && !empty($cat['image']) && file_exists('../' . $cat['image'])) {
                unlink('../' . $cat['image']);
            }
            $conn->query("UPDATE categories SET deleted_at = NOW() WHERE id = $id");
        } elseif ($action == 'delete_selected_categories') {
            $ids = json_decode($_POST['selected_ids'] ?? '[]', true);
            if (!empty($ids)) {
                $ids_str = implode(',', array_map('intval', $ids));
                $cats = $conn->query("SELECT image FROM categories WHERE id IN ($ids_str)")->fetch_all(MYSQLI_ASSOC);
                foreach ($cats as $cat) {
                    if (!empty($cat['image']) && file_exists('../' . $cat['image'])) {
                        unlink('../' . $cat['image']);
                    }
                }
                $conn->query("UPDATE categories SET deleted_at = NOW() WHERE id IN ($ids_str)");
            }
        } elseif ($action == 'delete_all_categories') {
            $cats = $conn->query("SELECT image FROM categories WHERE deleted_at IS NULL")->fetch_all(MYSQLI_ASSOC);
            foreach ($cats as $cat) {
                if (!empty($cat['image']) && file_exists('../' . $cat['image'])) {
                    unlink('../' . $cat['image']);
                }
            }
            $conn->query("UPDATE categories SET deleted_at = NOW() WHERE deleted_at IS NULL");
        }
        header('Location: shop.php?tab=categories&deleted=1');
        exit;
    }
}

// Get products
$category_filter = $_GET['category'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.deleted_at IS NULL";
$params = [];
$types = "";

if ($category_filter != 'all') {
    $query .= " AND p.category_id = ?";
    $params[] = intval($category_filter);
    $types .= "i";
}

if ($status_filter != 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories
$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$all_categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get shop hero content
$shop_hero_title = 'Shop Premium Leather Goods';
$shop_hero_subtitle = 'Discover our exquisite collection of handcrafted leather products';
$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_hero_title'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $shop_hero_title = $row['content_value'];
    }
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_hero_subtitle'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $shop_hero_subtitle = $row['content_value'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Management - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .shop-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .shop-tab {
            padding: 15px 30px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: var(--text-color);
            transition: all 0.3s ease;
            position: relative;
            bottom: -2px;
        }
        
        .shop-tab:hover {
            color: var(--primary-color);
        }
        
        .shop-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .bulk-actions-bar {
            background: var(--background-color);
            padding: 15px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .bulk-actions-bar select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            background: #fff;
            font-size: 14px;
        }
        
        .bulk-actions-bar button {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .selected-count {
            color: var(--primary-color);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1><i class="fas fa-store"></i> Shop Management</h1>
                <div>
                    <?php if ($active_tab == 'products'): ?>
                        <a href="product-add.php" class="btn-primary"><i class="fas fa-plus"></i> Add Product</a>
                    <?php else: ?>
                        <button onclick="showAddCategoryModal()" class="btn-primary"><i class="fas fa-plus"></i> Add Category</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="success-message">Items deleted successfully!</div>
            <?php endif; ?>
            <?php if (isset($_GET['saved'])): ?>
                <div class="success-message">Category saved successfully!</div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="shop-tabs">
                <button class="shop-tab <?php echo $active_tab == 'products' ? 'active' : ''; ?>" onclick="switchTab('products')">
                    <i class="fas fa-box"></i> Products
                </button>
                <button class="shop-tab <?php echo $active_tab == 'categories' ? 'active' : ''; ?>" onclick="switchTab('categories')">
                    <i class="fas fa-tags"></i> Categories
                </button>
                <button class="shop-tab <?php echo $active_tab == 'hero' ? 'active' : ''; ?>" onclick="switchTab('hero')">
                    <i class="fas fa-image"></i> Shop Hero
                </button>
            </div>
            
            <!-- Products Tab -->
            <div class="tab-content <?php echo $active_tab == 'products' ? 'active' : ''; ?>" id="productsTab">
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <input type="hidden" name="tab" value="products">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="category">
                            <option value="all">All Categories</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status">
                            <option value="all">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        
                        <button type="submit" class="btn-filter">Filter</button>
                        <a href="shop.php?tab=products" class="btn-secondary">Clear</a>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions-bar">
                    <span class="selected-count" id="selectedCount">0 selected</span>
                    <select id="bulkActionSelect">
                        <option value="">Bulk Actions</option>
                        <option value="delete_selected">Delete Selected</option>
                        <option value="delete_all">Delete All</option>
                    </select>
                    <button onclick="executeBulkAction('products')" class="btn-danger">Apply</button>
                </div>
                
                <!-- Products Table -->
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllProducts" onchange="toggleAllProducts(this)"></th>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>MOQ</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="10">No products found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>" onchange="updateSelectedCount()"></td>
                                        <td><?php echo $product['id']; ?></td>
                                        <td>
                                            <?php if ($product['image']): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="" class="table-image">
                                            <?php else: ?>
                                                <div class="table-image-placeholder"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $product['price'] ? '$' . number_format($product['price'], 2) : 'N/A'; ?></td>
                                        <td><?php echo $product['moq'] ?? 1; ?></td>
                                        <td><span class="status-badge status-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                        <td class="actions">
                                            <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn-edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div class="tab-content <?php echo $active_tab == 'categories' ? 'active' : ''; ?>" id="categoriesTab">
                <!-- Bulk Actions -->
                <div class="bulk-actions-bar">
                    <span class="selected-count" id="selectedCountCat">0 selected</span>
                    <select id="bulkActionSelectCat">
                        <option value="">Bulk Actions</option>
                        <option value="delete_selected">Delete Selected</option>
                        <option value="delete_all">Delete All</option>
                    </select>
                    <button onclick="executeBulkAction('categories')" class="btn-danger">Apply</button>
                </div>
                
                <!-- Categories Table -->
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCategories" onchange="toggleAllCategories(this)"></th>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="7">No categories found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><input type="checkbox" class="category-checkbox" value="<?php echo $cat['id']; ?>" onchange="updateSelectedCountCat()"></td>
                                        <td><?php echo $cat['id']; ?></td>
                                        <td>
                                            <?php if (!empty($cat['image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($cat['image']); ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                                            <?php else: ?>
                                                <div class="table-image-placeholder"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                        <td><?php echo htmlspecialchars($cat['slug']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 50)); ?>...</td>
                                        <td class="actions">
                                            <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" class="btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Shop Hero Tab -->
            <div class="tab-content <?php echo $active_tab == 'hero' ? 'active' : ''; ?>" id="heroTab">
                <div class="admin-form-container">
                    <h2>Edit Shop Hero Section</h2>
                    <p class="form-description">Customize the hero section that appears at the top of the shop page.</p>
                    
                    <?php if (isset($_GET['saved']) && $active_tab == 'hero'): ?>
                        <div class="success-message">Shop hero content saved successfully!</div>
                    <?php endif; ?>
                    
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="shop_hero_action" value="save">
                        
                        <div class="form-group">
                            <label for="shop_hero_title">Hero Title *</label>
                            <input type="text" id="shop_hero_title" name="shop_hero_title" value="<?php echo htmlspecialchars($shop_hero_title); ?>" required>
                            <small>The main heading displayed in the hero section</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="shop_hero_subtitle">Hero Subtitle *</label>
                            <textarea id="shop_hero_subtitle" name="shop_hero_subtitle" rows="3" required><?php echo htmlspecialchars($shop_hero_subtitle); ?></textarea>
                            <small>The descriptive text displayed below the title</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                    
                    <div class="preview-section" style="margin-top: 40px; padding: 30px; background: var(--background-color); border: 1px solid var(--border-color);">
                        <h3 style="margin-bottom: 20px; color: var(--primary-color);">Preview</h3>
                        <div style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%); color: #fff; padding: 60px 40px; text-align: center; border-radius: 4px;">
                            <h1 style="font-family: 'Playfair Display', serif; font-size: 36px; font-weight: 500; margin-bottom: 20px; letter-spacing: 1px;" id="previewTitle"><?php echo htmlspecialchars($shop_hero_title); ?></h1>
                            <p style="font-size: 18px; font-weight: 300; opacity: 0.9; letter-spacing: 0.5px;" id="previewSubtitle"><?php echo htmlspecialchars($shop_hero_subtitle); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCategoryModal()">&times;</span>
            <h2 id="modalTitle">Add Category</h2>
            <form method="POST" id="categoryForm" enctype="multipart/form-data" action="shop.php?tab=categories">
                <input type="hidden" name="category_action" id="formAction" value="add">
                <input type="hidden" name="id" id="categoryId">
                
                <div class="form-group">
                    <label for="cat_name">Name *</label>
                    <input type="text" id="cat_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="cat_slug">Slug *</label>
                    <input type="text" id="cat_slug" name="slug" required>
                </div>
                
                <div class="form-group">
                    <label for="cat_description">Description</label>
                    <textarea id="cat_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group" id="imageGroup">
                    <label for="cat_image">Cover Image</label>
                    <div id="currentImageContainer" style="margin-bottom: 10px; display: none;">
                        <img id="currentImagePreview" src="" alt="Current Image" style="max-width: 300px; max-height: 200px; display: block; margin-bottom: 10px; border: 1px solid var(--border-color); border-radius: 4px; object-fit: cover;">
                        <button type="button" id="removeImageBtn" class="btn-delete" onclick="removeCategoryImage()">Remove Image</button>
                    </div>
                    <input type="file" id="cat_image" name="image" accept="image/*">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save</button>
                    <button type="button" onclick="closeCategoryModal()" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <form method="POST" id="bulkForm" style="display:none;" action="shop.php?tab=<?php echo $active_tab; ?>">
        <input type="hidden" name="action" id="bulkAction">
        <input type="hidden" name="selected_ids" id="selectedIds">
    </form>
    
    <script>
        function switchTab(tab) {
            window.location.href = 'shop.php?tab=' + tab;
        }
        
        function toggleAllProducts(checkbox) {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }
        
        function toggleAllCategories(checkbox) {
            const checkboxes = document.querySelectorAll('.category-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCountCat();
        }
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.product-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected + ' selected';
        }
        
        function updateSelectedCountCat() {
            const selected = document.querySelectorAll('.category-checkbox:checked').length;
            document.getElementById('selectedCountCat').textContent = selected + ' selected';
        }
        
        function executeBulkAction(type) {
            const action = type === 'products' ? 
                document.getElementById('bulkActionSelect').value : 
                document.getElementById('bulkActionSelectCat').value;
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            let selected = [];
            if (type === 'products') {
                selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
            } else {
                selected = Array.from(document.querySelectorAll('.category-checkbox:checked')).map(cb => cb.value);
            }
            
            if (action === 'delete_all') {
                if (!confirm('Delete ALL ' + type + '? This cannot be undone!')) return;
                document.getElementById('bulkAction').value = 'delete_all_' + type;
            } else if (action === 'delete_selected') {
                if (selected.length === 0) {
                    alert('Please select at least one item');
                    return;
                }
                if (!confirm('Delete ' + selected.length + ' selected ' + type + '?')) return;
                document.getElementById('bulkAction').value = 'delete_selected_' + type;
                document.getElementById('selectedIds').value = JSON.stringify(selected);
            }
            
            document.getElementById('bulkForm').submit();
        }
        
        function showAddCategoryModal() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryForm').action = 'shop.php?tab=categories';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('currentImageContainer').style.display = 'none';
            document.getElementById('categoryModal').style.display = 'block';
        }
        
        function editCategory(cat) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('categoryForm').action = 'shop.php?tab=categories';
            document.getElementById('categoryId').value = cat.id;
            document.getElementById('cat_name').value = cat.name;
            document.getElementById('cat_slug').value = cat.slug;
            document.getElementById('cat_description').value = cat.description || '';
            
            const imageContainer = document.getElementById('currentImageContainer');
            const imagePreview = document.getElementById('currentImagePreview');
            if (cat.image) {
                imagePreview.src = '../' + cat.image;
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            
            document.getElementById('categoryModal').style.display = 'block';
        }
        
        function removeCategoryImage() {
            if (confirm('Remove this image?')) {
                const form = document.getElementById('categoryForm');
                const removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_image';
                removeInput.value = '1';
                form.appendChild(removeInput);
                form.submit();
            }
        }
        
        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        
        // Auto-generate slug
        document.getElementById('cat_name')?.addEventListener('input', function() {
            const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            document.getElementById('cat_slug').value = slug;
        });
        
        // Live preview for shop hero
        const heroTitleInput = document.getElementById('shop_hero_title');
        const heroSubtitleInput = document.getElementById('shop_hero_subtitle');
        const previewTitle = document.getElementById('previewTitle');
        const previewSubtitle = document.getElementById('previewSubtitle');
        
        if (heroTitleInput && previewTitle) {
            heroTitleInput.addEventListener('input', function() {
                previewTitle.textContent = this.value || 'Shop Premium Leather Goods';
            });
        }
        
        if (heroSubtitleInput && previewSubtitle) {
            heroSubtitleInput.addEventListener('input', function() {
                previewSubtitle.textContent = this.value || 'Discover our exquisite collection of handcrafted leather products';
            });
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('categoryModal');
            if (event.target == modal) {
                closeCategoryModal();
            }
        }
    </script>
</body>
</html>

