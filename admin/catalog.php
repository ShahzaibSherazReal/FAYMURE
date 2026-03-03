<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$base = defined('BASE_PATH') ? BASE_PATH : '';

// Delete product (soft delete) when in category view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $cat = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    if ($id > 0) {
        $conn->query("UPDATE products SET deleted_at = NOW() WHERE id = $id");
    }
    $url = $base . '/admin/catalog' . ($cat > 0 ? '?category=' . $cat : '');
    header('Location: ' . $url);
    exit;
}

// Load all categories (single source of truth; managed in Explore)
$categories = [];
$r = $conn->query("SELECT id, name, slug, description, image, sort_order FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
if ($r) {
    $categories = $r->fetch_all(MYSQLI_ASSOC);
}

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$current_category = null;
$category_products = [];

if ($category_id > 0) {
    foreach ($categories as $c) {
        if ((int)$c['id'] === $category_id) {
            $current_category = $c;
            break;
        }
    }
    if ($current_category) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? AND deleted_at IS NULL ORDER BY name");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $category_products = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .catalog-categories { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-top: 20px; }
        .catalog-cat-card { display: block; padding: 24px; background: #fff; border: 1px solid var(--border-color, #ddd); border-radius: 8px; text-decoration: none; color: inherit; transition: box-shadow 0.2s, border-color 0.2s; }
        .catalog-cat-card:hover { border-color: var(--primary-color, #c9a962); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .catalog-cat-card img { width: 100%; height: 140px; object-fit: cover; border-radius: 6px; margin-bottom: 12px; background: #f5f5f5; }
        .catalog-cat-card .no-img { width: 100%; height: 140px; background: #f0f0f0; border-radius: 6px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; color: #999; }
        .catalog-cat-card h3 { margin: 0 0 8px; font-size: 1.1rem; color: var(--primary-color); }
        .catalog-cat-card p { margin: 0; font-size: 0.9rem; color: #666; }
        .catalog-breadcrumb { margin-bottom: 20px; }
        .catalog-breadcrumb a { color: var(--primary-color); text-decoration: none; }
        .catalog-breadcrumb a:hover { text-decoration: underline; }
        .btn-icon.btn-danger { background: none; border: none; cursor: pointer; padding: 6px 10px; color: #666; }
        .btn-icon.btn-danger:hover { color: #c00; }
        @media (max-width: 768px) {
            .catalog-categories { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .catalog-cat-card { padding: 10px 8px; }
            .catalog-cat-card img, .catalog-cat-card .no-img { height: 72px; margin-bottom: 8px; }
            .catalog-cat-card h3 { font-size: 11px; }
            .catalog-cat-card p { font-size: 9px; }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Catalog</h1>
            </div>

            <?php if (!$current_category): ?>
                <!-- List all categories -->
                <p class="catalog-notice" style="background: #f0f4f8; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 4px solid var(--primary-color, #c9a962);">
                    Categories are managed in <strong>Explore</strong>. Click a category below to add, edit, or delete products. Changes apply everywhere on the site (shop, explore, product pages).
                </p>
                <p><a href="<?php echo $base; ?>/admin/explore" class="btn-secondary"><i class="fas fa-compass"></i> Manage categories in Explore</a></p>

                <?php if (empty($categories)): ?>
                    <p>No categories yet. <a href="<?php echo $base; ?>/admin/explore">Add categories in Explore</a> first.</p>
                <?php else: ?>
                    <div class="catalog-categories">
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo (int)$cat['id']; ?>" class="catalog-cat-card">
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                                <?php else: ?>
                                    <div class="no-img"><i class="fas fa-folder"></i></div>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 80)); ?><?php echo strlen($cat['description'] ?? '') > 80 ? '…' : ''; ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Category detail: products in this category -->
                <div class="catalog-breadcrumb">
                    <a href="<?php echo $base; ?>/admin/catalog"><i class="fas fa-th-large"></i> Catalog</a>
                    <span> &rarr; <?php echo htmlspecialchars($current_category['name']); ?></span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
                    <h2 class="content-group-title" style="margin: 0;">Products in <?php echo htmlspecialchars($current_category['name']); ?></h2>
                    <a href="<?php echo $base; ?>/admin/product-add?category_id=<?php echo (int)$current_category['id']; ?>" class="btn-primary"><i class="fas fa-plus"></i> Add product</a>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Price</th>
                                <th>MOQ</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($category_products)): ?>
                                <tr>
                                    <td colspan="7">No products in this category. <a href="<?php echo $base; ?>/admin/product-add?category_id=<?php echo (int)$current_category['id']; ?>">Add one</a>.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($category_products as $p): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($p['image']); ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <span style="color: #999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><?php echo htmlspecialchars($p['slug'] ?? '—'); ?></td>
                                        <td><?php echo isset($p['price']) ? '$' . number_format((float)$p['price'], 2) : '—'; ?></td>
                                        <td><?php echo (int)($p['moq'] ?? 1); ?></td>
                                        <td><span class="status-badge status-<?php echo $p['status'] ?? 'active'; ?>"><?php echo ucfirst($p['status'] ?? 'active'); ?></span></td>
                                        <td>
                                            <a href="<?php echo $base; ?>/admin/product-edit?id=<?php echo (int)$p['id']; ?>&return=catalog&category=<?php echo (int)$current_category['id']; ?>" class="btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                                                <input type="hidden" name="delete_product" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                <input type="hidden" name="category_id" value="<?php echo (int)$current_category['id']; ?>">
                                                <button type="submit" class="btn-icon btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
