<?php
require_once 'check-auth.php';

$conn = getDBConnection();
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';

// Handle blog visibility toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blog_visibility'])) {
    $hide = isset($_POST['blog_hidden']) && $_POST['blog_hidden'] === '1' ? '1' : '0';
    $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('blog_hidden', ?) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)");
    if ($stmt) {
        $stmt->bind_param('s', $hide);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: ' . $base . '/admin/dashboard?blog_updated=1');
    exit;
}

// Current blog visibility: hidden when blog_hidden = '1'
$blog_hidden = false;
$r = $conn->query("SELECT content_value FROM site_content WHERE content_key = 'blog_hidden'");
if ($r && $r->num_rows > 0) {
    $row = $r->fetch_assoc();
    $blog_hidden = (isset($row['content_value']) && $row['content_value'] === '1');
}

// Helper: safe count for a table (table may not exist)
function safeCount($conn, $table, $where = '1=1') {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!$r || $r->num_rows == 0) return 0;
    $q = $conn->query("SELECT COUNT(*) as c FROM `$table` WHERE $where");
    return $q ? (int)$q->fetch_assoc()['c'] : 0;
}

// Products & Categories
$total_products = safeCount($conn, 'products', 'deleted_at IS NULL');
$active_products = safeCount($conn, 'products', 'deleted_at IS NULL AND status = "active"');
$total_categories = safeCount($conn, 'categories', 'deleted_at IS NULL');

// Shop orders (orders table)
$shop_orders_total = safeCount($conn, 'orders');
$shop_orders_pending = safeCount($conn, 'orders', "status = 'pending'");

// Quote requests
$quote_requests_total = safeCount($conn, 'quote_requests');
$quote_requests_pending = safeCount($conn, 'quote_requests', "status = 'pending'");

// Form submissions (Design your own, Wholesale, Customizations)
$design_submissions = safeCount($conn, 'custom_designs');
$wholesale_submissions = safeCount($conn, 'quote_requests'); // same as quote_requests
$customization_submissions = safeCount($conn, 'product_customizations');
$form_submissions_pending = 0;
if (safeCount($conn, 'custom_designs', '1=1') > 0) {
    $form_submissions_pending += safeCount($conn, 'custom_designs', "status = 'pending'");
}
if (safeCount($conn, 'product_customizations', '1=1') > 0) {
    $form_submissions_pending += safeCount($conn, 'product_customizations', "status = 'pending'");
}
$form_submissions_pending += safeCount($conn, 'quote_requests', "status = 'pending'");

// Users
$total_users = safeCount($conn, 'users', 'deleted_at IS NULL');

// Recent shop orders (schema may have order_number/total_amount or older setup-database columns only)
$recent_shop_orders = [];
$t = $conn->query("SHOW TABLES LIKE 'orders'");
if ($t && $t->num_rows > 0) {
    $cols = [];
    $cr = $conn->query("SHOW COLUMNS FROM orders");
    if ($cr) while ($row = $cr->fetch_assoc()) $cols[] = $row['Field'];
    $want = ['id', 'order_number', 'customer_name', 'customer_email', 'total_amount', 'status', 'created_at'];
    $select = array_intersect($want, $cols);
    if (empty($select)) $select = ['id', 'customer_name', 'status', 'created_at'];
    $sel = implode(', ', array_map(function ($c) { return "`$c`"; }, $select));
    $orderBy = in_array('created_at', $cols, true) ? ' ORDER BY created_at DESC' : '';
    $res = $conn->query("SELECT $sel FROM orders{$orderBy} LIMIT 5");
    if ($res) $recent_shop_orders = $res->fetch_all(MYSQLI_ASSOC);
}

// Recent quote requests
$recent_quote_requests = [];
$t = $conn->query("SHOW TABLES LIKE 'quote_requests'");
if ($t && $t->num_rows > 0) {
    $res = $conn->query("SELECT qr.id, qr.customer_name, qr.status, qr.created_at, p.name as product_name FROM quote_requests qr LEFT JOIN products p ON qr.product_id = p.id ORDER BY qr.created_at DESC LIMIT 5");
    if ($res) $recent_quote_requests = $res->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-welcome { margin-bottom: 28px; color: #555; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: #fff; border: 1px solid var(--border-color, #e0e0e0); border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px; transition: box-shadow 0.2s; }
        .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-card a { text-decoration: none; color: inherit; display: flex; align-items: center; gap: 16px; flex: 1; }
        .stat-icon { width: 48px; height: 48px; border-radius: 8px; background: var(--primary-color, #c9a962); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .stat-info h3 { margin: 0 0 4px; font-size: 1.6rem; }
        .stat-info p { margin: 0; font-size: 0.95rem; color: #333; }
        .stat-info small { display: block; margin-top: 4px; font-size: 0.85rem; color: #666; }
        .dashboard-section { background: #fff; border: 1px solid var(--border-color, #e0e0e0); border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .dashboard-section h2 { margin: 0 0 16px; font-size: 1.2rem; color: var(--primary-color); padding-bottom: 10px; border-bottom: 2px solid var(--primary-color); }
        .dashboard-section .table-container { overflow-x: auto; }
        .dashboard-section .admin-table { margin: 0; }
        .btn-view-all { display: inline-block; margin-top: 12px; padding: 8px 16px; background: var(--primary-color); color: #fff; text-decoration: none; border-radius: 6px; font-size: 0.9rem; }
        .btn-view-all:hover { background: var(--dark-color, #1a1a1a); color: #fff; }
        .quick-links { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
        .quick-links a { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #f8f8f8; border: 1px solid #eee; border-radius: 6px; text-decoration: none; color: #333; font-size: 0.95rem; transition: background 0.2s, border-color 0.2s; }
        .quick-links a:hover { background: #f0f4f8; border-color: var(--primary-color); color: var(--primary-color); }
        .quick-links a i { font-size: 1.1rem; opacity: 0.9; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }
        /* Blog visibility toggle */
        .toggle-switch { position: relative; display: inline-block; width: 52px; height: 28px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 28px; transition: 0.3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background: var(--primary-color, #c9a962); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }
        .toggle-label { font-size: 0.95rem; color: #333; }
    </style>
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>Dashboard</h1>
            <p class="dashboard-welcome">Overview of your store and recent activity.</p>

            <?php if (isset($_GET['blog_updated'])): ?>
                <div class="success-notification" style="margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> Blog visibility updated.
                </div>
            <?php endif; ?>

            <!-- Blog visibility toggle -->
            <div class="dashboard-section" style="margin-bottom: 24px;">
                <h2><i class="fas fa-blog"></i> Blog section</h2>
                <p style="margin: 0 0 12px; color: #555;">When turned ON, the blog is hidden from the main site (nav link and all blog pages). Turn OFF to show the blog again with all content.</p>
                <form method="post" class="blog-visibility-form" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <input type="hidden" name="blog_visibility" value="1">
                    <label class="toggle-switch">
                        <input type="checkbox" name="blog_hidden" value="1" <?php echo $blog_hidden ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label"><?php echo $blog_hidden ? 'Blog hidden from site' : 'Blog visible on site'; ?></span>
                </form>
            </div>

            <!-- Stats: key admin areas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <a href="<?php echo $base; ?>/admin/catalog">
                        <div class="stat-icon"><i class="fas fa-box"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $total_products; ?></h3>
                            <p>Products</p>
                            <small><?php echo $active_products; ?> active</small>
                        </div>
                    </a>
                </div>
                <div class="stat-card">
                    <a href="<?php echo $base; ?>/admin/explore">
                        <div class="stat-icon"><i class="fas fa-tags"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $total_categories; ?></h3>
                            <p>Categories</p>
                            <small>Catalog & Explore</small>
                        </div>
                    </a>
                </div>
                <div class="stat-card">
                    <a href="<?php echo $base; ?>/admin/orders?tab=shop_orders">
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $shop_orders_total; ?></h3>
                            <p>Shop Orders</p>
                            <small><?php echo $shop_orders_pending; ?> pending</small>
                        </div>
                    </a>
                </div>
                <div class="stat-card">
                    <a href="<?php echo $base; ?>/admin/orders?tab=quote_requests">
                        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $quote_requests_total; ?></h3>
                            <p>Quote Requests</p>
                            <small><?php echo $quote_requests_pending; ?> pending</small>
                        </div>
                    </a>
                </div>
                <div class="stat-card">
                    <a href="<?php echo $base; ?>/admin/form-submissions">
                        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $design_submissions + $quote_requests_total + $customization_submissions; ?></h3>
                            <p>Form Submissions</p>
                            <small><?php echo $form_submissions_pending; ?> pending</small>
                        </div>
                    </a>
                </div>
                <div class="stat-card">
                    <a href="<?php echo $base; ?>/admin/users">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $total_users; ?></h3>
                            <p>Users</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Quick links -->
            <div class="dashboard-section">
                <h2>Quick links</h2>
                <div class="quick-links">
                    <a href="<?php echo $base; ?>/admin/catalog"><i class="fas fa-th-large"></i> Catalog</a>
                    <a href="<?php echo $base; ?>/admin/product-add"><i class="fas fa-plus"></i> Add product</a>
                    <a href="<?php echo $base; ?>/admin/orders"><i class="fas fa-shopping-cart"></i> Orders</a>
                    <a href="<?php echo $base; ?>/admin/form-submissions"><i class="fas fa-file-alt"></i> Form submissions</a>
                    <a href="<?php echo $base; ?>/admin/explore"><i class="fas fa-compass"></i> Explore (categories)</a>
                    <a href="<?php echo $base; ?>/admin/shop"><i class="fas fa-store"></i> Shop</a>
                    <a href="<?php echo $base; ?>/admin/site-content"><i class="fas fa-edit"></i> Site content</a>
                    <a href="<?php echo $base; ?>/admin/users"><i class="fas fa-users"></i> Users</a>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Recent shop orders -->
                <div class="dashboard-section">
                    <h2>Recent shop orders</h2>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_shop_orders)): ?>
                                    <tr><td colspan="5">No shop orders yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_shop_orders as $o): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($o['order_number'] ?? (isset($o['id']) ? '#' . $o['id'] : '—')); ?></td>
                                            <td><?php echo htmlspecialchars($o['customer_name'] ?? ''); ?></td>
                                            <td><?php echo isset($o['total_amount']) ? '$' . number_format((float)$o['total_amount'], 2) : '—'; ?></td>
                                            <td><span class="status-badge status-<?php echo $o['status'] ?? 'pending'; ?>"><?php echo ucfirst($o['status'] ?? 'pending'); ?></span></td>
                                            <td><?php echo !empty($o['created_at']) ? date('M d, Y', strtotime($o['created_at'])) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="<?php echo $base; ?>/admin/orders?tab=shop_orders" class="btn-view-all">View all shop orders</a>
                </div>

                <!-- Recent quote requests -->
                <div class="dashboard-section">
                    <h2>Recent quote requests</h2>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_quote_requests)): ?>
                                    <tr><td colspan="4">No quote requests yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_quote_requests as $q): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($q['product_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($q['customer_name'] ?? ''); ?></td>
                                            <td><span class="status-badge status-<?php echo $q['status'] ?? 'pending'; ?>"><?php echo ucfirst($q['status'] ?? 'pending'); ?></span></td>
                                            <td><?php echo !empty($q['created_at']) ? date('M d, Y', strtotime($q['created_at'])) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="<?php echo $base; ?>/admin/orders?tab=quote_requests" class="btn-view-all">View all quote requests</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
