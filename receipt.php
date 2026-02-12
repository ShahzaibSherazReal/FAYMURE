<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$order_number = $_GET['order'] ?? '';

if (!$order_number) {
    redirect('shop.php');
}

$conn = getDBConnection();

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->bind_param("s", $order_number);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    redirect('shop.php');
}

// Get order items
$items_stmt = $conn->prepare("SELECT oi.*, p.name as product_name, p.image FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              WHERE oi.order_id = ?");
$items_stmt->bind_param("i", $order['id']);
$items_stmt->execute();
$order_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$conn->close();
?>
    <main class="receipt-page">
        <div class="container">
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1>Order Confirmed!</h1>
                    <p class="receipt-message">Thank you for your order. We've received your order and will begin processing it shortly.</p>
                </div>
                
                <div class="receipt-content">
                    <div class="receipt-section">
                        <h2>Order Details</h2>
                        <div class="receipt-info">
                            <div class="info-row">
                                <span class="label">Order Number:</span>
                                <span class="value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Order Date:</span>
                                <span class="value"><?php echo date('F d, Y g:i A', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Status:</span>
                                <span class="value status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Total Amount:</span>
                                <span class="value total-amount">$<?php echo number_format($order['total_amount'] ?? 0, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="receipt-section">
                        <h2>Shipping Address</h2>
                        <div class="address-block">
                            <p><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></p>
                            <p>
                                <?php echo htmlspecialchars($order['shipping_city'] ?? ''); ?>, 
                                <?php echo htmlspecialchars($order['shipping_state'] ?? ''); ?> 
                                <?php echo htmlspecialchars($order['shipping_zip'] ?? ''); ?>
                            </p>
                            <p><?php echo htmlspecialchars($order['shipping_country'] ?? ''); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <?php if ($order['customer_phone']): ?>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="receipt-section">
                        <h2>Order Items</h2>
                        <div class="items-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="item-info">
                                                    <?php if ($item['image']): ?>
                                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                                            <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" style="text-align: right; font-weight: 600;">Total:</td>
                                        <td style="font-weight: 600; font-size: 18px; color: var(--primary-color);">
                                            $<?php echo number_format($order['total_amount'] ?? 0, 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="receipt-section">
                        <h2>Payment & Shipping</h2>
                        <div class="receipt-info">
                            <div class="info-row">
                                <span class="label">Payment Method:</span>
                                <span class="value"><?php echo strtoupper($order['payment_method'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Shipping Method:</span>
                                <span class="value"><?php echo ucfirst($order['shipping_method'] ?? 'Standard'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                        <div class="receipt-section">
                            <h2>Order Notes</h2>
                            <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="receipt-actions">
                        <a href="shop.php" class="btn-primary">
                            <i class="fas fa-store"></i> Continue Shopping
                        </a>
                        <button onclick="window.print()" class="btn-secondary">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .receipt-page {
            padding: 100px 0;
        }
        
        .receipt-container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            padding: 50px;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 50px;
            padding-bottom: 30px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .receipt-header h1 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .receipt-message {
            color: var(--text-color);
            font-size: 16px;
        }
        
        .receipt-section {
            margin-bottom: 40px;
        }
        
        .receipt-section h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .receipt-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(123, 91, 58, 0.1);
        }
        
        .info-row .label {
            font-weight: 500;
            color: var(--text-color);
        }
        
        .info-row .value {
            color: var(--primary-color);
        }
        
        .total-amount {
            font-size: 24px;
            font-weight: 600;
        }
        
        .address-block {
            line-height: 1.8;
        }
        
        .items-table {
            overflow-x: auto;
        }
        
        .items-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th,
        .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .items-table th {
            background: rgba(0, 31, 63, 0.05);
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .item-info img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
        
        .receipt-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--primary-color);
        }
        
        @media print {
            .receipt-actions {
                display: none;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>

