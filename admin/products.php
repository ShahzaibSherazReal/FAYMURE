<?php
require_once 'check-auth.php';

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                $id = intval($_POST['id']);
                $conn->query("UPDATE products SET deleted_at = NOW() WHERE id = $id");
                break;
            case 'delete_selected':
                $ids = $_POST['selected_ids'] ?? [];
                if (!empty($ids)) {
                    $ids_str = implode(',', array_map('intval', $ids));
                    $conn->query("UPDATE products SET deleted_at = NOW() WHERE id IN ($ids_str)");
                }
                break;
            case 'delete_all':
                $conn->query("UPDATE products SET deleted_at = NOW() WHERE deleted_at IS NULL");
                break;
        }
    }
}

// Filters
$category_filter = $_GET['category'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
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

if ($date_filter != 'all') {
    $date_condition = match($date_filter) {
        'today' => "DATE(p.created_at) = CURDATE()",
        'week' => "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => ""
    };
    if ($date_condition) {
        $query .= " AND $date_condition";
    }
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Products</h1>
                <a href="product-add.php" class="btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="category">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
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
                    
                    <select name="date">
                        <option value="all">All Time</option>
                        <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                    
                    <button type="submit" class="btn-filter">Filter</button>
                    <a href="products.php" class="btn-secondary">Clear</a>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <form method="POST" id="bulkForm" onsubmit="return confirm('Are you sure?');">
                    <input type="hidden" name="action" id="bulkAction" value="">
                    <input type="hidden" name="selected_ids" id="selectedIds" value="">
                    <button type="button" onclick="deleteSelected()" class="btn-danger">Delete Selected</button>
                    <button type="button" onclick="deleteAll()" class="btn-danger">Delete All</button>
                </form>
            </div>
            
            <!-- Products Table -->
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Subcategory</th>
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
                                    <td><input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>"></td>
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
                                    <td><?php echo ucfirst($product['subcategory'] ?? 'unisex'); ?></td>
                                    <td><?php echo $product['moq'] ?? 1; ?></td>
                                    <td><span class="status-badge status-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn-delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        function deleteSelected() {
            const selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                alert('Please select at least one product');
                return;
            }
            document.getElementById('selectedIds').value = JSON.stringify(selected);
            document.getElementById('bulkAction').value = 'delete_selected';
            document.getElementById('bulkForm').submit();
        }
        
        function deleteAll() {
            if (!confirm('Delete ALL products? This cannot be undone!')) return;
            document.getElementById('bulkAction').value = 'delete_all';
            document.getElementById('bulkForm').submit();
        }
    </script>
</body>
</html>

