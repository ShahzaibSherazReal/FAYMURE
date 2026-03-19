<?php
require_once 'check-auth.php';
require_once __DIR__ . '/../includes/image-upload-webp.php';

$conn = getDBConnection();
$success = false;
$error = '';

$upload_dir = '../assets/images/categories/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Ensure categories.images exists for multi-thumbnails
$col = $conn->query("SHOW COLUMNS FROM categories LIKE 'images'");
if (!$col || $col->num_rows == 0) {
    $conn->query("ALTER TABLE categories ADD COLUMN images TEXT NULL AFTER image");
}

// Ensure subcategories table exists
$conn->query("CREATE TABLE IF NOT EXISTS subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uniq_cat_slug (category_id, slug),
    KEY idx_cat (category_id),
    CONSTRAINT fk_subcategories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function upload_category_images_explore($upload_dir, $field = 'images') {
    $saved = [];
    if (!isset($_FILES[$field]) || empty($_FILES[$field]['name'])) return $saved;
    $names = $_FILES[$field]['name'];
    $tmp = $_FILES[$field]['tmp_name'];
    $errs = $_FILES[$field]['error'];
    if (!is_array($names)) {
        $names = [$names];
        $tmp = [$tmp];
        $errs = [$errs];
    }
    foreach ($names as $i => $n) {
        if (!isset($errs[$i]) || $errs[$i] !== UPLOAD_ERR_OK) continue;
        $ext = pathinfo($n, PATHINFO_EXTENSION);
        $fname = uniqid() . '.' . $ext;
        if (move_uploaded_file($tmp[$i], $upload_dir . $fname)) {
            $webp = convert_file_to_webp($upload_dir . $fname);
            $saved[] = $webp ? str_replace('../', '', $webp) : ('assets/images/categories/' . $fname);
        }
    }
    return $saved;
}

function ensureSiteContent($conn, $key, $default) {
    $esc = $conn->real_escape_string($key);
    $r = $conn->query("SELECT id FROM site_content WHERE content_key='$esc'");
    if ($r && $r->num_rows > 0) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $default);
    $stmt->execute();
    $stmt->close();
}

// 1. Save Catalog title & tagline (explore_title, explore_subtitle)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_catalog_heading'])) {
    $title = sanitize($_POST['catalog_title'] ?? '');
    $tagline = sanitize($_POST['catalog_tagline'] ?? '');
    ensureSiteContent($conn, 'explore_title', 'Catalog');
    ensureSiteContent($conn, 'explore_subtitle', 'Browse our product categories');
    $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = 'explore_title'");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = 'explore_subtitle'");
    $stmt->bind_param("s", $tagline);
    $stmt->execute();
    $stmt->close();
    $success = true;
    header('Location: explore?saved=1');
    exit;
}

// 2. Add category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_action']) && $_POST['category_action'] === 'add') {
    $name = sanitize($_POST['name'] ?? '');
    $tagline = sanitize($_POST['tagline'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    if (empty($slug) && $name !== '') {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
        $slug = trim($slug, '-');
    }
    // Support both legacy single image field and new multiple images[]
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && (!isset($_FILES['images']) || empty($_FILES['images']['name']))) {
        $_FILES['images'] = [
            'name' => [$_FILES['image']['name']],
            'type' => [$_FILES['image']['type']],
            'tmp_name' => [$_FILES['image']['tmp_name']],
            'error' => [$_FILES['image']['error']],
            'size' => [$_FILES['image']['size']],
        ];
    }
    $imgs = upload_category_images_explore($upload_dir, 'images');
    $imgs = array_values(array_unique(array_filter($imgs)));
    $image = $imgs[0] ?? '';
    $images_json = json_encode($imgs);
    if ($name !== '' && $slug !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, image, images) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $slug, $tagline, $image, $images_json);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Failed to add category. Slug may already exist.';
        }
        $stmt->close();
    } else {
        $error = 'Title and slug are required.';
    }
    if (!$error) {
        header('Location: explore?saved=1');
        exit;
    }
}

// 3. Edit category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_action']) && $_POST['category_action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $tagline = sanitize($_POST['tagline'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    if ($id > 0 && $name !== '' && $slug !== '') {
        $current = $conn->query("SELECT image, images FROM categories WHERE id = $id");
        $current = $current ? $current->fetch_assoc() : null;
        $image = $current['image'] ?? '';
        $imgs = json_decode($current['images'] ?? '[]', true);
        if (!is_array($imgs)) $imgs = [];
        if ($image && !in_array($image, $imgs, true)) array_unshift($imgs, $image);

        // Remove selected images
        $remove_list = json_decode($_POST['remove_images'] ?? '[]', true);
        if (!is_array($remove_list)) $remove_list = [];
        if (!empty($remove_list)) {
            $imgs = array_values(array_filter($imgs, function($p) use ($remove_list) { return !in_array($p, $remove_list, true); }));
            foreach ($remove_list as $p) {
                if ($p !== '' && file_exists('../' . $p)) @unlink('../' . $p);
            }
        }

        // Remove all (legacy checkbox)
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            foreach ($imgs as $p) {
                if ($p !== '' && file_exists('../' . $p)) @unlink('../' . $p);
            }
            $imgs = [];
        }

        // Upload new images (append)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && (!isset($_FILES['images']) || empty($_FILES['images']['name']))) {
            $_FILES['images'] = [
                'name' => [$_FILES['image']['name']],
                'type' => [$_FILES['image']['type']],
                'tmp_name' => [$_FILES['image']['tmp_name']],
                'error' => [$_FILES['image']['error']],
                'size' => [$_FILES['image']['size']],
            ];
        }
        $uploaded = upload_category_images_explore($upload_dir, 'images');
        if (!empty($uploaded)) {
            $imgs = array_values(array_unique(array_merge($imgs, $uploaded)));
        }

        $image = $imgs[0] ?? '';
        $images_json = json_encode(array_values(array_filter($imgs)));

        $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, images = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $slug, $tagline, $image, $images_json, $id);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Failed to update category.';
        }
        $stmt->close();
    } else {
        $error = 'Invalid data.';
    }
    if (!$error) {
        header('Location: explore?saved=1');
        exit;
    }
}

// 4. Delete one category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_one']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $conn->query("UPDATE categories SET deleted_at = NOW() WHERE id = $id");
        $success = true;
    }
    header('Location: explore?saved=1');
    exit;
}

// 5. Delete selected categories
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_selected']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function ($i) { return $i > 0; });
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE categories SET deleted_at = NOW() WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $stmt->close();
        $success = true;
    }
    header('Location: explore?saved=1');
    exit;
}

// 6. Delete all categories
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all'])) {
    $conn->query("UPDATE categories SET deleted_at = NOW() WHERE deleted_at IS NULL");
    $success = true;
    header('Location: explore?saved=1');
    exit;
}

// 7. Subcategories CRUD (add/edit/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subcat_action'])) {
    $action = $_POST['subcat_action'];
    $id = (int)($_POST['subcat_id'] ?? 0);
    $category_id = (int)($_POST['subcat_category_id'] ?? 0);
    $name = sanitize($_POST['subcat_name'] ?? '');
    $slug = sanitize($_POST['subcat_slug'] ?? '');
    $sort_order = (int)($_POST['subcat_sort_order'] ?? 0);

    if ($slug === '' && $name !== '') {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
        $slug = trim($slug, '-');
    }

    if ($action === 'add') {
        if ($category_id > 0 && $name !== '' && $slug !== '') {
            $stmt = $conn->prepare("INSERT INTO subcategories (category_id, name, slug, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $category_id, $name, $slug, $sort_order);
            if ($stmt->execute()) {
                header('Location: explore?saved=1');
                exit;
            }
            $error = 'Failed to add subcategory. Slug may already exist for this category.';
            $stmt->close();
        } else {
            $error = 'Please select a category and enter a subcategory name.';
        }
    }
    if ($action === 'edit') {
        if ($id > 0 && $category_id > 0 && $name !== '' && $slug !== '') {
            $stmt = $conn->prepare("UPDATE subcategories SET category_id = ?, name = ?, slug = ?, sort_order = ? WHERE id = ?");
            $stmt->bind_param("issii", $category_id, $name, $slug, $sort_order, $id);
            if ($stmt->execute()) {
                header('Location: explore?saved=1');
                exit;
            }
            $error = 'Failed to update subcategory.';
            $stmt->close();
        } else {
            $error = 'Invalid subcategory data.';
        }
    }
    if ($action === 'delete') {
        if ($id > 0) {
            $conn->query("UPDATE subcategories SET deleted_at = NOW() WHERE id = $id");
            header('Location: explore?saved=1');
            exit;
        }
    }
}

// Load catalog heading
$catalog_title = 'Catalog';
$catalog_tagline = 'Browse our product categories';
$r = $conn->query("SELECT content_key, content_value FROM site_content WHERE content_key IN ('explore_title','explore_subtitle')");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['content_key'] === 'explore_title') $catalog_title = $row['content_value'] ?: $catalog_title;
        if ($row['content_key'] === 'explore_subtitle') $catalog_tagline = $row['content_value'] ?: $catalog_tagline;
    }
}

// Load categories
$categories = [];
$res = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
if ($res) {
    $categories = $res->fetch_all(MYSQLI_ASSOC);
}

// Load subcategories
$subcategories = [];
$res = $conn->query("SELECT sc.*, c.name AS category_name FROM subcategories sc JOIN categories c ON c.id = sc.category_id WHERE sc.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY c.sort_order, c.name, sc.sort_order, sc.name");
if ($res) {
    $subcategories = $res->fetch_all(MYSQLI_ASSOC);
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
    <title>Explore - Catalog & Categories - <?php echo SITE_NAME; ?> Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-section { margin-bottom: 30px; }
        .form-section .section-title { margin-bottom: 15px; font-size: 18px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input[type="text"], .form-group textarea { width: 100%; max-width: 500px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .bulk-actions { margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .bulk-actions form { display: inline; }
        .admin-table th:first-child, .admin-table td:first-child { width: 40px; text-align: center; }
        .admin-table img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; }
        .btn-icon { background: none; border: none; cursor: pointer; padding: 6px 10px; color: #333; }
        .btn-icon:hover { color: var(--primary-color, #c9a962); }
        .btn-icon.btn-danger:hover { color: #c00; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
        .modal-content { background: #fff; margin: 5% auto; padding: 24px; max-width: 520px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .modal .close { float: right; font-size: 28px; cursor: pointer; line-height: 1; color: #666; }
        .modal .close:hover { color: #000; }
        .modal h2 { margin-top: 0; margin-bottom: 20px; }
        .modal .form-group input[type="text"], .modal .form-group textarea { max-width: 100%; }
        .modal .form-actions { margin-top: 20px; display: flex; gap: 10px; }
    </style>
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Explore – Catalog & Categories</h1>
            </div>

            <?php if ($success): ?>
                <div class="success-notification">
                    <i class="fas fa-check-circle"></i> Changes saved successfully!
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-notification">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Catalog title & tagline -->
            <section class="form-section">
                <h2 class="section-title">Catalog section heading</h2>
                <form method="POST">
                    <input type="hidden" name="save_catalog_heading" value="1">
                    <div class="form-group">
                        <label for="catalog_title">Title</label>
                        <input type="text" id="catalog_title" name="catalog_title" value="<?php echo htmlspecialchars($catalog_title); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="catalog_tagline">Tagline</label>
                        <textarea id="catalog_tagline" name="catalog_tagline" rows="2"><?php echo htmlspecialchars($catalog_tagline); ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save heading</button>
                </form>
            </section>

            <!-- Categories -->
            <section class="form-section">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                    <h2 class="section-title" style="margin: 0;">Categories</h2>
                    <button type="button" onclick="openAddModal()" class="btn-primary"><i class="fas fa-plus"></i> Add category</button>
                </div>

                <div class="bulk-actions">
                    <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('Delete selected categories?');" style="display: inline;">
                        <input type="hidden" name="delete_selected" value="1">
                        <div id="bulkIdsContainer"></div>
                        <button type="submit" class="btn-secondary" id="btnDeleteSelected" style="display: none;"><i class="fas fa-trash"></i> Delete selected</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Delete ALL categories? This cannot be undone.');" style="display: inline;">
                        <input type="hidden" name="delete_all" value="1">
                        <button type="submit" class="btn-secondary" style="color: #c00;"><i class="fas fa-trash-alt"></i> Delete all</button>
                    </form>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCat" title="Select all"></th>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Tagline</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6">No categories yet. Add one above.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><input type="checkbox" class="cat-check" name="ids[]" value="<?php echo (int)$cat['id']; ?>" form="bulkDeleteForm"></td>
                                        <td><?php echo (int)$cat['id']; ?></td>
                                        <td>
                                            <?php if (!empty($cat['image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($cat['image']); ?>" alt="">
                                            <?php else: ?>
                                                <span style="color: #999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 60)); ?><?php echo strlen($cat['description'] ?? '') > 60 ? '…' : ''; ?></td>
                                        <td>
                                            <button type="button" class="btn-icon" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($cat)); ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button type="button" class="btn-icon" onclick="openAddSubcatModal(<?php echo (int)$cat['id']; ?>)" title="Add subcategory"><i class="fas fa-plus"></i></button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                                <input type="hidden" name="delete_one" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                <button type="submit" class="btn-icon btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Subcategories -->
            <section class="form-section">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                    <h2 class="section-title" style="margin: 0;">Subcategories</h2>
                    <button type="button" onclick="openAddSubcatModal()" class="btn-primary"><i class="fas fa-plus"></i> Add subcategory</button>
                </div>
                <p style="margin-top: 0; color: #666;">Click a category’s <strong>+</strong> icon to add a subcategory inside that category.</p>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Sort</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subcategories)): ?>
                                <tr><td colspan="6">No subcategories yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subcategories as $sc): ?>
                                    <tr>
                                        <td><?php echo (int)$sc['id']; ?></td>
                                        <td><?php echo htmlspecialchars($sc['category_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($sc['name']); ?></td>
                                        <td><?php echo htmlspecialchars($sc['slug']); ?></td>
                                        <td><?php echo (int)($sc['sort_order'] ?? 0); ?></td>
                                        <td>
                                            <button type="button" class="btn-icon" onclick="openEditSubcatModal(<?php echo htmlspecialchars(json_encode($sc)); ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this subcategory?');">
                                                <input type="hidden" name="subcat_action" value="delete">
                                                <input type="hidden" name="subcat_id" value="<?php echo (int)$sc['id']; ?>">
                                                <button type="submit" class="btn-icon btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- Add/Edit Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCategoryModal()">&times;</span>
            <h2 id="categoryModalTitle">Add category</h2>
            <form method="POST" id="categoryForm" enctype="multipart/form-data">
                <input type="hidden" name="category_action" id="categoryAction" value="add">
                <input type="hidden" name="id" id="categoryId" value="">
                <div class="form-group">
                    <label for="cat_name">Title *</label>
                    <input type="text" id="cat_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="cat_slug">Slug *</label>
                    <input type="text" id="cat_slug" name="slug" required>
                </div>
                <div class="form-group">
                    <label for="cat_tagline">Tagline</label>
                    <textarea id="cat_tagline" name="tagline" rows="2"></textarea>
                </div>
                <div class="form-group" id="catImageGroup">
                    <label for="cat_images">Thumbnails</label>
                    <div id="currentCatImagesWrap" style="margin-bottom: 10px; display: none;">
                        <div id="currentCatImagesGrid" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;"></div>
                        <input type="hidden" name="remove_images" id="removeCatImagesInput" value="[]">
                        <label style="display: block; margin-top: 8px;"><input type="checkbox" name="remove_image" value="1"> Remove all thumbnails</label>
                    </div>
                    <input type="file" id="cat_images" name="images[]" accept="image/*" multiple>
                    <small>Add multiple thumbnails. The first image will be the cover and the website will show them as a carousel.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save</button>
                    <button type="button" id="btnAddSubcatFromCategory" onclick="openAddSubcatModalFromCurrentCategory()" class="btn-secondary" style="display:none;"><i class="fas fa-plus"></i> Add subcategory</button>
                    <button type="button" onclick="closeCategoryModal()" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Subcategory Modal -->
    <div id="subcatModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSubcatModal()">&times;</span>
            <h2 id="subcatModalTitle">Add subcategory</h2>
            <form method="POST" id="subcatForm">
                <input type="hidden" name="subcat_action" id="subcatAction" value="add">
                <input type="hidden" name="subcat_id" id="subcatId" value="">
                <div class="form-group">
                    <label for="subcat_category_id">Category *</label>
                    <select id="subcat_category_id" name="subcat_category_id" required style="width: 100%; max-width: 500px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subcat_name">Name *</label>
                    <input type="text" id="subcat_name" name="subcat_name" required>
                </div>
                <div class="form-group">
                    <label for="subcat_slug">Slug *</label>
                    <input type="text" id="subcat_slug" name="subcat_slug" required>
                </div>
                <div class="form-group">
                    <label for="subcat_sort_order">Sort order</label>
                    <input type="text" id="subcat_sort_order" name="subcat_sort_order" value="0">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save</button>
                    <button type="button" onclick="closeSubcatModal()" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var currentEditingCategoryId = 0;
        function openAddModal() {
            document.getElementById('categoryModalTitle').textContent = 'Add category';
            document.getElementById('categoryAction').value = 'add';
            document.getElementById('categoryId').value = '';
            currentEditingCategoryId = 0;
            document.getElementById('categoryForm').reset();
            document.getElementById('currentCatImagesWrap').style.display = 'none';
            var btn = document.getElementById('btnAddSubcatFromCategory');
            if (btn) btn.style.display = 'none';
            document.getElementById('categoryModal').style.display = 'block';
        }
        function openEditModal(cat) {
            document.getElementById('categoryModalTitle').textContent = 'Edit category';
            document.getElementById('categoryAction').value = 'edit';
            document.getElementById('categoryId').value = cat.id;
            currentEditingCategoryId = parseInt(cat.id || '0', 10) || 0;
            document.getElementById('cat_name').value = cat.name || '';
            document.getElementById('cat_slug').value = cat.slug || '';
            document.getElementById('cat_tagline').value = cat.description || '';
            var btn = document.getElementById('btnAddSubcatFromCategory');
            if (btn) btn.style.display = currentEditingCategoryId ? 'inline-block' : 'none';
            var wrap = document.getElementById('currentCatImagesWrap');
            var grid = document.getElementById('currentCatImagesGrid');
            var removeInput = document.getElementById('removeCatImagesInput');
            if (grid) grid.innerHTML = '';
            if (removeInput) removeInput.value = '[]';
            var removeList = [];
            var imgs = [];
            try { imgs = JSON.parse(cat.images || '[]'); } catch (e) { imgs = []; }
            if (!Array.isArray(imgs)) imgs = [];
            if (cat.image && imgs.indexOf(cat.image) === -1) imgs.unshift(cat.image);
            imgs = imgs.filter(Boolean);
            if (imgs.length) {
                wrap.style.display = 'block';
                imgs.forEach(function(p) {
                    var item = document.createElement('div');
                    item.style.position = 'relative';
                    item.style.width = '88px';
                    item.style.height = '66px';
                    var im = document.createElement('img');
                    im.src = '../' + p;
                    im.style.width = '100%';
                    im.style.height = '100%';
                    im.style.objectFit = 'cover';
                    im.style.borderRadius = '6px';
                    im.style.border = '1px solid #ddd';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = '×';
                    btn.title = 'Remove';
                    btn.style.position = 'absolute';
                    btn.style.top = '-8px';
                    btn.style.right = '-8px';
                    btn.style.width = '24px';
                    btn.style.height = '24px';
                    btn.style.borderRadius = '999px';
                    btn.style.border = 'none';
                    btn.style.cursor = 'pointer';
                    btn.style.background = 'rgba(0,0,0,0.75)';
                    btn.style.color = '#fff';
                    btn.addEventListener('click', function() {
                        if (removeList.indexOf(p) === -1) removeList.push(p);
                        if (removeInput) removeInput.value = JSON.stringify(removeList);
                        item.style.display = 'none';
                    });
                    item.appendChild(im);
                    item.appendChild(btn);
                    grid.appendChild(item);
                });
            } else {
                wrap.style.display = 'none';
            }
            document.getElementById('categoryModal').style.display = 'block';
        }
        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        function openAddSubcatModal(categoryId) {
            document.getElementById('subcatModalTitle').textContent = 'Add subcategory';
            document.getElementById('subcatAction').value = 'add';
            document.getElementById('subcatId').value = '';
            document.getElementById('subcatForm').reset();
            if (categoryId) {
                document.getElementById('subcat_category_id').value = categoryId;
            }
            document.getElementById('subcatModal').style.display = 'block';
        }
        function openAddSubcatModalFromCurrentCategory() {
            if (!currentEditingCategoryId) return;
            closeCategoryModal();
            openAddSubcatModal(currentEditingCategoryId);
        }
        function openEditSubcatModal(sc) {
            document.getElementById('subcatModalTitle').textContent = 'Edit subcategory';
            document.getElementById('subcatAction').value = 'edit';
            document.getElementById('subcatId').value = sc.id || '';
            document.getElementById('subcat_category_id').value = sc.category_id || '';
            document.getElementById('subcat_name').value = sc.name || '';
            document.getElementById('subcat_slug').value = sc.slug || '';
            document.getElementById('subcat_sort_order').value = sc.sort_order || 0;
            document.getElementById('subcatModal').style.display = 'block';
        }
        function closeSubcatModal() {
            document.getElementById('subcatModal').style.display = 'none';
        }
        document.getElementById('cat_name').addEventListener('input', function() {
            var slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (document.getElementById('categoryAction').value === 'add') {
                document.getElementById('cat_slug').value = slug;
            }
        });
        var selectAll = document.getElementById('selectAllCat');
        var checks = document.querySelectorAll('.cat-check');
        var btnDel = document.getElementById('btnDeleteSelected');
        if (selectAll) {
            selectAll.onclick = function() {
                var table = selectAll.closest('table');
                var cbs = table ? table.querySelectorAll('.cat-check') : [];
                cbs.forEach(function(cb) { cb.checked = selectAll.checked; });
                btnDel.style.display = document.querySelectorAll('.cat-check:checked').length ? 'inline-block' : 'none';
            };
        }
        checks.forEach(function(cb) {
            cb.addEventListener('change', function() {
                btnDel.style.display = document.querySelectorAll('.cat-check:checked').length ? 'inline-block' : 'none';
            });
        });
        window.onclick = function(e) {
            if (e.target.id === 'categoryModal') closeCategoryModal();
            if (e.target.id === 'subcatModal') closeSubcatModal();
        };
    </script>

    <script>
        document.getElementById('subcat_name').addEventListener('input', function() {
            var slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (document.getElementById('subcatAction').value === 'add') {
                document.getElementById('subcat_slug').value = slug;
            }
        });
    </script>
</body>
</html>
