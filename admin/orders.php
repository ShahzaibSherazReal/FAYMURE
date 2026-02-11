<?php
require_once 'check-auth.php';

$conn = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = sanitize($_POST['status']);
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();
}

// Check if quote_requests table exists
$table_check = $conn->query("SHOW TABLES LIKE 'quote_requests'");
$table_name = ($table_check->num_rows > 0) ? 'quote_requests' : 'orders';

// Get orders
$orders = $conn->query("SELECT o.*, p.name as product_name FROM $table_name o 
                        LEFT JOIN products p ON o.product_id = p.id 
                        ORDER BY o.created_at DESC")->fetch_all(MYSQLI_ASSOC);

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
            <h1>Orders / Quote Requests</h1>
            
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Customer Name</th>
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
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10">No orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['product_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo $order['quantity'] ?? 1; ?></td>
                                    <td><?php echo htmlspecialchars(substr($order['message'] ?? '', 0, 50)); ?>...</td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="status-select">
                                                <option value="pending" <?php echo ($order['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo ($order['status'] ?? '') == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="completed" <?php echo ($order['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ($order['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td class="actions">
                                        <button onclick="viewOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)" class="btn-view"><i class="fas fa-eye"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Order Detail Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeOrderModal()">&times;</span>
            <h2>Order Details</h2>
            <div id="orderDetails"></div>
        </div>
    </div>
    
    <script>
        function viewOrder(order) {
            document.getElementById('orderDetails').innerHTML = `
                <p><strong>Product:</strong> ${order.product_name || 'N/A'}</p>
                <p><strong>Customer:</strong> ${order.customer_name}</p>
                <p><strong>Email:</strong> ${order.customer_email}</p>
                <p><strong>Phone:</strong> ${order.customer_phone || 'N/A'}</p>
                <p><strong>Quantity:</strong> ${order.quantity || 1}</p>
                <p><strong>Message:</strong> ${order.message || 'N/A'}</p>
                <p><strong>Status:</strong> ${order.status}</p>
                <p><strong>Date:</strong> ${new Date(order.created_at).toLocaleString()}</p>
            `;
            document.getElementById('orderModal').style.display = 'block';
        }
        
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
    </script>
</body>
</html>

