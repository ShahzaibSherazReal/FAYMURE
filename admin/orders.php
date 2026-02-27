<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'shop_orders';
if (!in_array($active_tab, ['shop_orders', 'quote_requests'], true)) {
    $active_tab = 'shop_orders';
}
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query params for redirect (preserve tab, search, status)
$query_params = function() use ($active_tab, $search, $status_filter) {
    $p = 'tab=' . urlencode($active_tab);
    if ($search !== '') $p .= '&search=' . urlencode($search);
    if ($status_filter !== '') $p .= '&status=' . urlencode($status_filter);
    return $p;
};

// Handle status update (for both shop orders and quote requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $table = isset($_POST['order_type']) ? $_POST['order_type'] : 'orders';
    if ($order_id > 0 && in_array($table, ['orders', 'quote_requests'], true)) {
        $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: orders.php?' . $query_params());
    exit;
}

// Delete one shop order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shop_order']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $oi = $conn->query("SHOW TABLES LIKE 'order_items'");
        if ($oi && $oi->num_rows > 0) {
            $conn->query("DELETE FROM order_items WHERE order_id = $id");
        }
        $conn->query("DELETE FROM orders WHERE id = $id");
    }
    header('Location: orders.php?' . $query_params());
    exit;
}

// Delete selected shop orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shop_selected']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function ($i) { return $i > 0; });
    if (!empty($ids)) {
        $oi = $conn->query("SHOW TABLES LIKE 'order_items'");
        if ($oi && $oi->num_rows > 0) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $stmt->close();
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: orders.php?' . $query_params());
    exit;
}

// Delete all shop orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shop_all'])) {
    $oi = $conn->query("SHOW TABLES LIKE 'order_items'");
    if ($oi && $oi->num_rows > 0) {
        $conn->query("DELETE FROM order_items");
    }
    $conn->query("DELETE FROM orders");
    header('Location: orders.php?' . $query_params());
    exit;
}

// Delete one quote request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quote_one']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $conn->query("DELETE FROM quote_requests WHERE id = $id");
    }
    header('Location: orders.php?' . $query_params());
    exit;
}

// Delete selected quote requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quote_selected']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function ($i) { return $i > 0; });
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM quote_requests WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: orders.php?' . $query_params());
    exit;
}

// Delete all quote requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quote_all'])) {
    $conn->query("DELETE FROM quote_requests");
    header('Location: orders.php?' . $query_params());
    exit;
}

// Shop orders: from `orders` table with search and status filter
$shop_orders = [];
$orders_exists = $conn->query("SHOW TABLES LIKE 'orders'");
if ($orders_exists && $orders_exists->num_rows > 0) {
    $sql = "SELECT * FROM orders WHERE 1=1";
    $params = [];
    $types = '';
    if ($status_filter !== '') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    if ($search !== '') {
        $sql .= " AND (customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? OR order_number LIKE ? OR notes LIKE ?)";
        $term = '%' . $conn->real_escape_string($search) . '%';
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
        $types .= str_repeat('s', 5);
    }
    $sql .= " ORDER BY created_at DESC";
    if (empty($params)) {
        $res = $conn->query($sql);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    if ($res) {
        $shop_orders = $res->fetch_all(MYSQLI_ASSOC);
    }
}

// Quote requests: from `quote_requests` with search and status filter
$quote_requests = [];
$qr_exists = $conn->query("SHOW TABLES LIKE 'quote_requests'");
if ($qr_exists && $qr_exists->num_rows > 0) {
    $sql = "SELECT qr.*, p.name as product_name FROM quote_requests qr 
            LEFT JOIN products p ON qr.product_id = p.id 
            WHERE 1=1";
    $params = [];
    $types = '';
    if ($status_filter !== '') {
        $sql .= " AND qr.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    if ($search !== '') {
        $sql .= " AND (qr.customer_name LIKE ? OR qr.customer_email LIKE ? OR qr.customer_phone LIKE ? OR qr.message LIKE ? OR p.name LIKE ?)";
        $term = '%' . $conn->real_escape_string($search) . '%';
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
        $types .= str_repeat('s', 5);
    }
    $sql .= " ORDER BY qr.created_at DESC";
    if (empty($params)) {
        $res = $conn->query($sql);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    if ($res) {
        $quote_requests = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Orders</h1>

            <?php
                $q_shop = 'tab=shop_orders'; if ($search !== '') $q_shop .= '&search=' . urlencode($search); if ($status_filter !== '') $q_shop .= '&status=' . urlencode($status_filter);
                $q_quote = 'tab=quote_requests'; if ($search !== '') $q_quote .= '&search=' . urlencode($search); if ($status_filter !== '') $q_quote .= '&status=' . urlencode($status_filter);
            ?>
            <div class="admin-tabs">
                <a href="?<?php echo $q_shop; ?>" class="tab-link <?php echo $active_tab === 'shop_orders' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i> Shop Orders
                </a>
                <a href="?<?php echo $q_quote; ?>" class="tab-link <?php echo $active_tab === 'quote_requests' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar"></i> Quote Requests
                </a>
            </div>

            <!-- Search & Filter -->
            <div class="filter-section" style="margin: 16px 0; display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                <form method="GET" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <input type="text" name="search" placeholder="Search customer, email, order #, product…" value="<?php echo htmlspecialchars($search); ?>" style="min-width: 220px; padding: 8px 12px;">
                    <select name="status" style="padding: 8px 12px;">
                        <option value="">All statuses</option>
                        <?php if ($active_tab === 'shop_orders'): ?>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <?php else: ?>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="quoted" <?php echo $status_filter === 'quoted' ? 'selected' : ''; ?>>Quoted</option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <?php endif; ?>
                    </select>
                    <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                </form>
                <a href="?tab=<?php echo htmlspecialchars($active_tab); ?>" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;"><i class="fas fa-times"></i> Clear filters</a>
            </div>

            <!-- Shop Orders tab -->
            <?php if ($active_tab === 'shop_orders'): ?>
                <div class="bulk-actions" style="margin-bottom: 12px;">
                    <form method="POST" id="bulkDeleteShopForm" style="display: inline;" onsubmit="return confirm('Delete selected shop orders? This cannot be undone.');">
                        <input type="hidden" name="delete_shop_selected" value="1">
                        <div id="bulkShopIds"></div>
                        <button type="submit" class="btn-secondary" id="btnDeleteShopSelected" style="display: none;"><i class="fas fa-trash"></i> Delete selected</button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete ALL shop orders? This cannot be undone.');">
                        <input type="hidden" name="delete_shop_all" value="1">
                        <button type="submit" class="btn-secondary" style="color: #c00;"><i class="fas fa-trash-alt"></i> Delete all</button>
                    </form>
                </div>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="selectAllShop" title="Select all"></th>
                                <th>ID</th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shop_orders)): ?>
                                <tr>
                                    <td colspan="10">No shop orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($shop_orders as $order): ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-shop" name="ids[]" value="<?php echo (int)$order['id']; ?>" form="bulkDeleteShopForm"></td>
                                        <td><?php echo (int)$order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['order_number'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_phone'] ?? '—'); ?></td>
                                        <td><?php echo isset($order['total_amount']) ? '$' . number_format((float)$order['total_amount'], 2) : '—'; ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                                <input type="hidden" name="order_type" value="orders">
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <option value="pending" <?php echo ($order['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo ($order['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="completed" <?php echo ($order['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo ($order['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo !empty($order['created_at']) ? date('M d, Y', strtotime($order['created_at'])) : '—'; ?></td>
                                        <td>
                                            <button type="button" onclick="viewShopOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)" class="btn-view" title="View"><i class="fas fa-eye"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this order?');">
                                                <input type="hidden" name="delete_shop_order" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$order['id']; ?>">
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

            <!-- Quote Requests tab -->
            <?php if ($active_tab === 'quote_requests'): ?>
                <div class="bulk-actions" style="margin-bottom: 12px;">
                    <form method="POST" id="bulkDeleteQuoteForm" style="display: inline;" onsubmit="return confirm('Delete selected quote requests? This cannot be undone.');">
                        <input type="hidden" name="delete_quote_selected" value="1">
                        <div id="bulkQuoteIds"></div>
                        <button type="submit" class="btn-secondary" id="btnDeleteQuoteSelected" style="display: none;"><i class="fas fa-trash"></i> Delete selected</button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete ALL quote requests? This cannot be undone.');">
                        <input type="hidden" name="delete_quote_all" value="1">
                        <button type="submit" class="btn-secondary" style="color: #c00;"><i class="fas fa-trash-alt"></i> Delete all</button>
                    </form>
                </div>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="selectAllQuote" title="Select all"></th>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Quantity</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quote_requests)): ?>
                                <tr>
                                    <td colspan="11">No quote requests found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quote_requests as $qr): ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-quote" name="ids[]" value="<?php echo (int)$qr['id']; ?>" form="bulkDeleteQuoteForm"></td>
                                        <td><?php echo (int)$qr['id']; ?></td>
                                        <td><?php echo htmlspecialchars($qr['product_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($qr['customer_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($qr['customer_email'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($qr['customer_phone'] ?? '—'); ?></td>
                                        <td><?php echo (int)($qr['quantity'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars(substr($qr['message'] ?? '', 0, 40)) . (strlen($qr['message'] ?? '') > 40 ? '…' : ''); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="order_id" value="<?php echo (int)$qr['id']; ?>">
                                                <input type="hidden" name="order_type" value="quote_requests">
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <option value="pending" <?php echo ($qr['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="quoted" <?php echo ($qr['status'] ?? '') === 'quoted' ? 'selected' : ''; ?>>Quoted</option>
                                                    <option value="accepted" <?php echo ($qr['status'] ?? '') === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                    <option value="rejected" <?php echo ($qr['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    <option value="cancelled" <?php echo ($qr['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <noscript><button type="submit">Update</button></noscript>
                                            </form>
                                        </td>
                                        <td><?php echo !empty($qr['created_at']) ? date('M d, Y', strtotime($qr['created_at'])) : '—'; ?></td>
                                        <td>
                                            <button type="button" onclick="viewQuoteRequest(<?php echo htmlspecialchars(json_encode($qr)); ?>)" class="btn-view" title="View"><i class="fas fa-eye"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quote request?');">
                                                <input type="hidden" name="delete_quote_one" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$qr['id']; ?>">
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

    <div id="orderModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:560px;">
            <span class="close" onclick="closeOrderModal()">&times;</span>
            <h2 id="orderModalTitle">Order Details</h2>
            <div id="orderDetails"></div>
        </div>
    </div>

    <script>
        function viewShopOrder(order) {
            var total = order.total_amount != null ? '$' + parseFloat(order.total_amount).toFixed(2) : '—';
            var html = '<p><strong>Order #:</strong> ' + (order.order_number || '—') + '</p>' +
                '<p><strong>Customer:</strong> ' + (order.customer_name || '—') + '</p>' +
                '<p><strong>Email:</strong> ' + (order.customer_email || '—') + '</p>' +
                '<p><strong>Phone:</strong> ' + (order.customer_phone || '—') + '</p>' +
                '<p><strong>Total:</strong> ' + total + '</p>' +
                '<p><strong>Status:</strong> ' + (order.status || '—') + '</p>' +
                '<p><strong>Date:</strong> ' + (order.created_at ? new Date(order.created_at).toLocaleString() : '—') + '</p>';
            if (order.shipping_address || order.shipping_city) {
                html += '<p><strong>Shipping:</strong><br>' + [order.shipping_address, order.shipping_city, order.shipping_state, order.shipping_zip, order.shipping_country].filter(Boolean).join(', ') + '</p>';
            }
            if (order.notes) {
                html += '<p><strong>Notes:</strong> ' + order.notes + '</p>';
            }
            document.getElementById('orderModalTitle').textContent = 'Shop Order Details';
            document.getElementById('orderDetails').innerHTML = html;
            document.getElementById('orderModal').style.display = 'block';
        }

        function viewQuoteRequest(qr) {
            var html = '<p><strong>Product:</strong> ' + (qr.product_name || 'N/A') + '</p>' +
                '<p><strong>Customer:</strong> ' + (qr.customer_name || '—') + '</p>' +
                '<p><strong>Email:</strong> ' + (qr.customer_email || '—') + '</p>' +
                '<p><strong>Phone:</strong> ' + (qr.customer_phone || '—') + '</p>' +
                '<p><strong>Quantity:</strong> ' + (qr.quantity || '—') + '</p>' +
                '<p><strong>Message:</strong><br>' + (qr.message || '—') + '</p>' +
                '<p><strong>Status:</strong> ' + (qr.status || '—') + '</p>' +
                '<p><strong>Date:</strong> ' + (qr.created_at ? new Date(qr.created_at).toLocaleString() : '—') + '</p>';
            document.getElementById('orderModalTitle').textContent = 'Quote Request Details';
            document.getElementById('orderDetails').innerHTML = html;
            document.getElementById('orderModal').style.display = 'block';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('orderModal');
            if (event.target === modal) closeOrderModal();
        };

        (function() {
            var selectAllShop = document.getElementById('selectAllShop');
            var selectAllQuote = document.getElementById('selectAllQuote');
            var btnShop = document.getElementById('btnDeleteShopSelected');
            var btnQuote = document.getElementById('btnDeleteQuoteSelected');

            function toggleBulkBtn(checkboxClass, btn) {
                if (!btn) return;
                var n = document.querySelectorAll('.' + checkboxClass + ':checked').length;
                btn.style.display = n ? 'inline-block' : 'none';
            }

            if (selectAllShop) {
                selectAllShop.onclick = function() {
                    var t = selectAllShop.closest('table');
                    var cbs = t ? t.querySelectorAll('.row-check-shop') : [];
                    cbs.forEach(function(cb) { cb.checked = selectAllShop.checked; });
                    toggleBulkBtn('row-check-shop', btnShop);
                };
            }
            document.querySelectorAll('.row-check-shop').forEach(function(cb) {
                cb.addEventListener('change', function() { toggleBulkBtn('row-check-shop', btnShop); });
            });
            toggleBulkBtn('row-check-shop', btnShop);

            if (selectAllQuote) {
                selectAllQuote.onclick = function() {
                    var t = selectAllQuote.closest('table');
                    var cbs = t ? t.querySelectorAll('.row-check-quote') : [];
                    cbs.forEach(function(cb) { cb.checked = selectAllQuote.checked; });
                    toggleBulkBtn('row-check-quote', btnQuote);
                };
            }
            document.querySelectorAll('.row-check-quote').forEach(function(cb) {
                cb.addEventListener('change', function() { toggleBulkBtn('row-check-quote', btnQuote); });
            });
            toggleBulkBtn('row-check-quote', btnQuote);
        })();
    </script>
    <style>
        .btn-icon.btn-danger { background: none; border: none; cursor: pointer; padding: 6px 10px; color: #666; }
        .btn-icon.btn-danger:hover { color: #c00; }
    </style>
</body>
</html>
