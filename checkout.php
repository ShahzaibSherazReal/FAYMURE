<?php
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/cart-functions.php';

$conn = getDBConnection();

$cart_items = getCartItems($conn);
$cart_total = getCartTotal($conn);
$shipping = $cart_total >= 100 ? 0 : 10;
$final_total = $cart_total + $shipping;

if (empty($cart_items)) {
    redirect('cart.php');
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_email = sanitize($_POST['customer_email'] ?? '');
    $customer_phone = sanitize($_POST['customer_phone'] ?? '');
    $customer_address = sanitize($_POST['customer_address'] ?? '');
    $customer_city = sanitize($_POST['customer_city'] ?? '');
    $customer_state = sanitize($_POST['customer_state'] ?? '');
    $customer_zip = sanitize($_POST['customer_zip'] ?? '');
    $customer_country = sanitize($_POST['customer_country'] ?? '');
    $shipping_method = sanitize($_POST['shipping_method'] ?? 'standard');
    $payment_method = sanitize($_POST['payment_method'] ?? 'cod');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if ($customer_name && $customer_email && $customer_address && $customer_city && $customer_zip && $customer_country) {
        // Create order
        $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        
        // Create orders table if it doesn't exist with proper structure
        $table_check = $conn->query("SHOW TABLES LIKE 'orders'");
        if ($table_check && $table_check->num_rows > 0) {
            // Check if order_number column exists
            $col_check = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_number'");
            if (!$col_check || $col_check->num_rows == 0) {
                $conn->query("ALTER TABLE orders ADD COLUMN order_number VARCHAR(50) UNIQUE, ADD COLUMN shipping_address TEXT, ADD COLUMN shipping_city VARCHAR(100), ADD COLUMN shipping_state VARCHAR(100), ADD COLUMN shipping_zip VARCHAR(20), ADD COLUMN shipping_country VARCHAR(100), ADD COLUMN shipping_method VARCHAR(50), ADD COLUMN payment_method VARCHAR(50), ADD COLUMN notes TEXT, ADD COLUMN total_amount DECIMAL(10,2)");
            }
        }
        
        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_number, customer_name, customer_email, customer_phone, shipping_address, shipping_city, shipping_state, shipping_zip, shipping_country, shipping_method, payment_method, notes, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $status = 'pending';
        $stmt->bind_param("issssssssssssd", $user_id, $order_number, $customer_name, $customer_email, $customer_phone, $customer_address, $customer_city, $customer_state, $customer_zip, $customer_country, $shipping_method, $payment_method, $notes, $final_total);
        
        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            
            // Create order_items table if it doesn't exist
            $items_table_check = $conn->query("SHOW TABLES LIKE 'order_items'");
            if (!$items_table_check || $items_table_check->num_rows == 0) {
                $conn->query("CREATE TABLE IF NOT EXISTS order_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    product_id INT NOT NULL,
                    quantity INT NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
                )");
            }
            
            // Insert order items
            foreach ($cart_items as $item) {
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $item['id'], $item['cart_quantity'], $item['price']);
                $item_stmt->execute();
                $item_stmt->close();
            }
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Redirect to receipt
            header('Location: receipt.php?order=' . $order_number);
            exit;
        } else {
            $error = "Failed to process order. Please try again.";
        }
        
        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}

$conn->close();
?>
    <main class="checkout-page">
        <div class="container">
            <h1 class="page-title reveal">Checkout</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="checkout-layout">
                <div class="checkout-form-section">
                    <form method="POST" class="checkout-form">
                        <h2>Shipping Information</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_name">Full Name *</label>
                                <input type="text" id="customer_name" name="customer_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_email">Email Address *</label>
                                <input type="email" id="customer_email" name="customer_email" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_phone">Phone Number</label>
                                <input type="tel" id="customer_phone" name="customer_phone">
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_country">Country *</label>
                                <input type="text" id="customer_country" name="customer_country" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_address">Street Address *</label>
                            <input type="text" id="customer_address" name="customer_address" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_city">City *</label>
                                <input type="text" id="customer_city" name="customer_city" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_state">State/Province</label>
                                <input type="text" id="customer_state" name="customer_state">
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_zip">ZIP/Postal Code *</label>
                                <input type="text" id="customer_zip" name="customer_zip" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_method">Shipping Method *</label>
                            <select id="shipping_method" name="shipping_method" required>
                                <option value="standard">Standard (5-7 business days) - Free</option>
                                <option value="express">Express (2-3 business days) - $15.00</option>
                                <option value="overnight">Overnight (1 business day) - $30.00</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="cod">Cash on Delivery</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Credit/Debit Card</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Order Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="4" placeholder="Special instructions for your order..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary btn-submit-order">
                            <i class="fas fa-lock"></i> Complete Order
                        </button>
                    </form>
                </div>
                
                <div class="order-summary-section">
                    <h2>Order Summary</h2>
                    <div class="order-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <div class="order-item-image">
                                    <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="order-item-details">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p>Qty: <?php echo $item['cart_quantity']; ?> × $<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                                <div class="order-item-total">
                                    $<?php echo number_format(($item['price'] ?? 0) * $item['cart_quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span><?php echo $shipping == 0 ? 'Free' : '$' . number_format($shipping, 2); ?></span>
                        </div>
                        <div class="total-row final">
                            <span>Total:</span>
                            <span>$<?php echo number_format($final_total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .checkout-page {
            padding: 100px 0;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 50px;
            text-align: center;
        }
        
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
        }
        
        .checkout-form h2,
        .order-summary-section h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .order-items {
            margin-bottom: 30px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-item-image img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-details h4 {
            font-size: 16px;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .order-item-total {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .order-totals {
            border-top: 2px solid var(--primary-color);
            padding-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        
        .total-row.final {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .btn-submit-order {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            margin-top: 20px;
        }
        
        @media (max-width: 968px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>

