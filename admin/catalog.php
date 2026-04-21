<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$sort_col_check = $conn->query("SHOW COLUMNS FROM products LIKE 'sort_order'");
if (!$sort_col_check || $sort_col_check->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN sort_order INT DEFAULT 0 AFTER category_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category_order']) && isset($_POST['category_order'])) {
    $order = json_decode((string)$_POST['category_order'], true);
    if (is_array($order)) {
        $position = 1;
        $stmt = $conn->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND deleted_at IS NULL");
        if ($stmt) {
            foreach ($order as $id) {
                $id = (int)$id;
                if ($id <= 0) continue;
                $stmt->bind_param("ii", $position, $id);
                $stmt->execute();
                $position++;
            }
            $stmt->close();
        }
    }
    header('Location: ' . $base . '/admin/catalog?reordered=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_homepage_order']) && isset($_POST['homepage_order'])) {
    $order = json_decode((string)$_POST['homepage_order'], true);
    $allowed = ['catalog', 'choose_path', 'latest_creation', 'info_carousel'];
    if (is_array($order)) {
        $clean = [];
        foreach ($order as $item) {
            if (in_array($item, $allowed, true) && !in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }
        foreach ($allowed as $item) {
            if (!in_array($item, $clean, true)) $clean[] = $item;
        }
        $value = json_encode($clean);
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('homepage_sections_order', ?) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)");
        if ($stmt) {
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: ' . $base . '/admin/catalog?home_order_saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product_order']) && isset($_POST['product_order'])) {
    $cat = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $order = json_decode((string)$_POST['product_order'], true);
    if ($cat > 0 && is_array($order)) {
        $position = 1;
        $stmt = $conn->prepare("UPDATE products SET sort_order = ? WHERE id = ? AND category_id = ? AND deleted_at IS NULL");
        if ($stmt) {
            foreach ($order as $id) {
                $id = (int)$id;
                if ($id <= 0) continue;
                $stmt->bind_param("iii", $position, $id, $cat);
                $stmt->execute();
                $position++;
            }
            $stmt->close();
        }
    }
    header('Location: ' . $base . '/admin/catalog?category=' . $cat . '&products_reordered=1');
    exit;
}

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

$homepage_sections = [
    ['id' => 'catalog', 'label' => 'Catalog'],
    ['id' => 'choose_path', 'label' => 'Choose Your Path'],
    ['id' => 'latest_creation', 'label' => 'Our Latest Creation'],
    ['id' => 'info_carousel', 'label' => 'Vision/Mission/Services Carousel'],
];
$row = $conn->query("SELECT content_value FROM site_content WHERE content_key = 'homepage_sections_order' LIMIT 1");
if ($row && $row->num_rows > 0) {
    $saved = json_decode((string)$row->fetch_assoc()['content_value'], true);
    if (is_array($saved)) {
        $lookup = [];
        foreach ($homepage_sections as $s) $lookup[$s['id']] = $s;
        $sorted = [];
        foreach ($saved as $id) {
            if (isset($lookup[$id])) {
                $sorted[] = $lookup[$id];
                unset($lookup[$id]);
            }
        }
        foreach ($lookup as $remaining) $sorted[] = $remaining;
        if (!empty($sorted)) $homepage_sections = $sorted;
    }
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
        $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? AND deleted_at IS NULL ORDER BY sort_order, name");
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
        .catalog-categories { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-top: 14px; }
        .catalog-cat-item { position: relative; }
        .catalog-cat-item.dragging { opacity: 0.6; }
        .catalog-cat-drag { position:absolute; top:8px; right:8px; z-index:3; width:28px; height:28px; border-radius:50%; border:1px solid rgba(0,31,63,0.14); background:#fff; color:#4f6278; display:flex; align-items:center; justify-content:center; cursor:grab; }
        .catalog-cat-drag:active { cursor:grabbing; }
        .catalog-cat-card { display: block; padding: 24px; background: #fff; border: 1px solid var(--border-color, #ddd); border-radius: 8px; text-decoration: none; color: inherit; transition: box-shadow 0.2s, border-color 0.2s; }
        .catalog-cat-card:hover { border-color: var(--primary-color, #c9a962); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .catalog-cat-card img { width: 100%; height: 140px; object-fit: cover; border-radius: 6px; margin-bottom: 12px; background: #f5f5f5; }
        .catalog-cat-card .no-img { width: 100%; height: 140px; background: #f0f0f0; border-radius: 6px; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; color: #999; }
        .catalog-cat-card h3 { margin: 0 0 8px; font-size: 1.1rem; color: var(--primary-color); }
        .catalog-cat-card p { margin: 0; font-size: 0.9rem; color: #666; }
        .sort-panel { background:#fff; border:1px solid rgba(0,31,63,0.12); border-radius:10px; padding:14px 16px; margin-bottom:16px; }
        .sort-panel h3 { margin:0 0 6px; color:var(--primary-color); font-size:1rem; }
        .sortable-list { list-style:none; margin:12px 0; padding:0; display:flex; flex-direction:column; gap:8px; }
        .sortable-item { background:#f8fbff; border:1px solid rgba(0,31,63,0.12); border-radius:8px; padding:10px 12px; display:flex; align-items:center; gap:10px; cursor:move; }
        .sortable-item .drag { color:#6e7f95; }
        .sortable-item.dragging { opacity:0.6; }
        .sort-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .product-row.dragging { opacity:0.55; }
        .product-drag { color:#6e7f95; cursor:grab; width:24px; text-align:center; }
        .product-drag:active { cursor:grabbing; }
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
                <?php if (isset($_GET['reordered'])): ?>
                    <div class="success-notification"><i class="fas fa-check-circle"></i> Category order updated.</div>
                <?php endif; ?>
                <?php if (isset($_GET['home_order_saved'])): ?>
                    <div class="success-notification"><i class="fas fa-check-circle"></i> Homepage section order updated.</div>
                <?php endif; ?>

                <div class="sort-panel">
                    <h3><i class="fas fa-layer-group"></i> Reorder Homepage Sections</h3>
                    <p style="margin:0; color:#556575; font-size:13px;">Drag to set homepage section sequence: Catalog, Choose Path, Latest Creation, Carousel.</p>
                    <form method="POST" id="homepageOrderForm">
                        <input type="hidden" name="save_homepage_order" value="1">
                        <input type="hidden" name="homepage_order" id="homepageOrderInput">
                        <ul class="sortable-list" id="homepageSortableList">
                            <?php foreach ($homepage_sections as $section): ?>
                                <li class="sortable-item" draggable="true" data-id="<?php echo htmlspecialchars($section['id']); ?>">
                                    <span class="drag"><i class="fas fa-grip-vertical"></i></span>
                                    <span><?php echo htmlspecialchars($section['label']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="sort-actions">
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Homepage Order</button>
                        </div>
                    </form>
                </div>

                <p class="catalog-notice" style="background: #f0f4f8; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 4px solid var(--primary-color, #c9a962);">
                    Categories are managed in <strong>Explore</strong>. Click a category below to add, edit, or delete products. Changes apply everywhere on the site (shop, explore, product pages).
                </p>
                <div class="sort-panel">
                    <h3><i class="fas fa-grip-lines"></i> Arrange Categories by Thumbnails</h3>
                    <p style="margin:0; color:#556575; font-size:13px;">Drag category cards by the top-right handle, then save. This order updates on the user-facing site.</p>
                </div>
                <p><a href="<?php echo $base; ?>/admin/explore" class="btn-secondary"><i class="fas fa-compass"></i> Manage categories in Explore</a></p>

                <?php if (empty($categories)): ?>
                    <p>No categories yet. <a href="<?php echo $base; ?>/admin/explore">Add categories in Explore</a> first.</p>
                <?php else: ?>
                    <form method="POST" id="categoryThumbOrderForm">
                        <input type="hidden" name="save_category_order" value="1">
                        <input type="hidden" name="category_order" id="categoryThumbOrderInput">
                    <div class="catalog-categories" id="categoryThumbSortable">
                        <?php foreach ($categories as $cat): ?>
                            <div class="catalog-cat-item" draggable="true" data-id="<?php echo (int)$cat['id']; ?>">
                                <span class="catalog-cat-drag" title="Drag to reorder"><i class="fas fa-grip-lines"></i></span>
                                <a href="<?php echo $base; ?>/admin/catalog?category=<?php echo (int)$cat['id']; ?>" class="catalog-cat-card">
                                    <?php if (!empty($cat['image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-img"><i class="fas fa-folder"></i></div>
                                    <?php endif; ?>
                                    <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 80)); ?><?php echo strlen($cat['description'] ?? '') > 80 ? '…' : ''; ?></p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sort-actions" style="margin-top:12px;">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Category Order</button>
                    </div>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <!-- Category detail: products in this category -->
                <div class="catalog-breadcrumb">
                    <a href="<?php echo $base; ?>/admin/catalog"><i class="fas fa-th-large"></i> Catalog</a>
                    <span> &rarr; <?php echo htmlspecialchars($current_category['name']); ?></span>
                </div>

                <?php if (isset($_GET['products_reordered'])): ?>
                    <div class="success-notification"><i class="fas fa-check-circle"></i> Product order updated for this category.</div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
                    <h2 class="content-group-title" style="margin: 0;">Products in <?php echo htmlspecialchars($current_category['name']); ?></h2>
                    <a href="<?php echo $base; ?>/admin/product-add?category_id=<?php echo (int)$current_category['id']; ?>" class="btn-primary"><i class="fas fa-plus"></i> Add product</a>
                </div>

                <form method="POST" id="productOrderForm">
                    <input type="hidden" name="save_product_order" value="1">
                    <input type="hidden" name="category_id" value="<?php echo (int)$current_category['id']; ?>">
                    <input type="hidden" name="product_order" id="productOrderInput">
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:40px;"><i class="fas fa-grip-vertical"></i></th>
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
                                    <td colspan="8">No products in this category. <a href="<?php echo $base; ?>/admin/product-add?category_id=<?php echo (int)$current_category['id']; ?>">Add one</a>.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($category_products as $p): ?>
                                    <tr class="product-row" draggable="true" data-id="<?php echo (int)$p['id']; ?>">
                                        <td><span class="product-drag"><i class="fas fa-grip-lines"></i></span></td>
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
                <?php if (!empty($category_products)): ?>
                    <div class="sort-actions" style="margin-top:12px;">
                        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Product Order</button>
                    </div>
                <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <script>
    (function() {
        function makeSortable(list) {
            if (!list) return;
            let dragged = null;
            list.querySelectorAll('.sortable-item').forEach(function(item) {
                item.addEventListener('dragstart', function() {
                    dragged = item;
                    item.classList.add('dragging');
                });
                item.addEventListener('dragend', function() {
                    item.classList.remove('dragging');
                });
                item.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    const box = item.getBoundingClientRect();
                    const next = (e.clientY - box.top) > (box.height / 2);
                    if (!dragged || dragged === item) return;
                    if (next) item.parentNode.insertBefore(dragged, item.nextSibling);
                    else item.parentNode.insertBefore(dragged, item);
                });
            });
        }

        function bindSave(formId, listId, inputId) {
            const form = document.getElementById(formId);
            const list = document.getElementById(listId);
            const input = document.getElementById(inputId);
            if (!form || !list || !input) return;
            makeSortable(list);
            form.addEventListener('submit', function() {
                const ids = Array.from(list.querySelectorAll('.sortable-item')).map(function(li) { return li.dataset.id; });
                input.value = JSON.stringify(ids);
            });
        }

        function makeSortableGrid(grid) {
            if (!grid) return;
            let dragged = null;
            grid.querySelectorAll('.catalog-cat-item').forEach(function(item) {
                item.addEventListener('dragstart', function(e) {
                    dragged = item;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                item.addEventListener('dragend', function() {
                    item.classList.remove('dragging');
                });
                item.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    if (!dragged || dragged === item) return;
                    const box = item.getBoundingClientRect();
                    const shouldInsertAfter = (e.clientY > box.top + box.height / 2);
                    if (shouldInsertAfter) {
                        item.parentNode.insertBefore(dragged, item.nextSibling);
                    } else {
                        item.parentNode.insertBefore(dragged, item);
                    }
                });
            });
        }

        bindSave('homepageOrderForm', 'homepageSortableList', 'homepageOrderInput');

        const thumbGrid = document.getElementById('categoryThumbSortable');
        const thumbForm = document.getElementById('categoryThumbOrderForm');
        const thumbInput = document.getElementById('categoryThumbOrderInput');
        if (thumbGrid && thumbForm && thumbInput) {
            makeSortableGrid(thumbGrid);
            thumbForm.addEventListener('submit', function() {
                const ids = Array.from(thumbGrid.querySelectorAll('.catalog-cat-item')).map(function(item) { return item.dataset.id; });
                thumbInput.value = JSON.stringify(ids);
            });
        }

        const productForm = document.getElementById('productOrderForm');
        const productInput = document.getElementById('productOrderInput');
        const productRows = document.querySelectorAll('.product-row');
        if (productForm && productInput && productRows.length) {
            let draggedRow = null;
            productRows.forEach(function(row) {
                row.addEventListener('dragstart', function(e) {
                    draggedRow = row;
                    row.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                row.addEventListener('dragend', function() {
                    row.classList.remove('dragging');
                });
                row.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    if (!draggedRow || draggedRow === row) return;
                    const box = row.getBoundingClientRect();
                    const insertAfter = e.clientY > box.top + box.height / 2;
                    const tbody = row.parentNode;
                    if (insertAfter) tbody.insertBefore(draggedRow, row.nextSibling);
                    else tbody.insertBefore(draggedRow, row);
                });
            });
            productForm.addEventListener('submit', function() {
                const ids = Array.from(document.querySelectorAll('.product-row')).map(function(row) { return row.dataset.id; });
                productInput.value = JSON.stringify(ids);
            });
        }
    })();
    </script>
</body>
</html>
