<?php
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/cart-functions.php';

$conn = getDBConnection();

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['remove_item'])) {
        $product_id = intval($_POST['product_id']);
        removeFromCart($product_id);
        header('Location: cart.php');
        exit;
    }
    
    if (isset($_POST['update_quantity'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        updateCartQuantity($product_id, $quantity);
        header('Location: cart.php');
        exit;
    }
}

$cart_items = getCartItems($conn);
$cart_total = getCartTotal($conn);
$cart_count = getCartCount();

$conn->close();
?>
    <main class="cart-page">
        <div class="container">
            <h1 class="page-title reveal">Shopping Cart</h1>
            
            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart" style="font-size: 80px; color: var(--border-color); margin-bottom: 30px;"></i>
                    <h2>Your cart is empty</h2>
                    <p>Start shopping to add items to your cart.</p>
                    <a href="shop.php" class="btn-primary">
                        <i class="fas fa-store"></i> Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item-image">
                                    <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="cart-item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="cart-item-category"><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></p>
                                    <p class="cart-item-price">$<?php echo number_format($item['price'] ?? 0, 2); ?> each</p>
                                </div>
                                <div class="cart-item-quantity">
                                    <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                        <button type="button" onclick="decreaseQty(<?php echo $item['id']; ?>)" class="qty-btn">-</button>
                                        <input type="number" name="quantity" id="qty_<?php echo $item['id']; ?>" value="<?php echo $item['cart_quantity']; ?>" min="1" readonly style="width: 60px; text-align: center; padding: 8px; border: 1px solid var(--border-color);">
                                        <button type="button" onclick="increaseQty(<?php echo $item['id']; ?>)" class="qty-btn">+</button>
                                        <button type="submit" name="update_quantity" class="btn-update" style="display: none;" id="update_<?php echo $item['id']; ?>">Update</button>
                                    </form>
                                </div>
                                <div class="cart-item-total">
                                    <p class="item-total">$<?php echo number_format(($item['price'] ?? 0) * $item['cart_quantity'], 2); ?></p>
                                </div>
                                <div class="cart-item-actions">
                                    <form method="POST" onsubmit="return confirm('Remove this item from cart?');">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="remove_item" class="btn-remove" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-summary">
                        <h3>Order Summary</h3>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span><?php echo $cart_total >= 100 ? 'Free' : '$10.00'; ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?php echo number_format($cart_total + ($cart_total >= 100 ? 0 : 10), 2); ?></span>
                        </div>
                        <a href="checkout.php" class="btn-primary btn-checkout">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </a>
                        <a href="shop.php" class="btn-secondary">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <style>
        .cart-page {
            padding: 100px 0;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 50px;
            text-align: center;
        }
        
        .empty-cart {
            text-align: center;
            padding: 100px 20px;
        }
        
        .empty-cart h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr 200px 120px 60px;
            gap: 20px;
            padding: 30px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            align-items: center;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
        
        .cart-item-details h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .cart-item-category {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .cart-item-price {
            color: var(--primary-color);
            font-weight: 500;
            font-size: 16px;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
        }
        
        .qty-btn {
            width: 35px;
            height: 35px;
            border: 1px solid var(--border-color);
            background: var(--background-color);
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .qty-btn:hover {
            background: var(--primary-color);
            color: #fff;
        }
        
        .item-total {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .btn-remove {
            background: transparent;
            border: none;
            color: var(--accent-color);
            cursor: pointer;
            font-size: 18px;
            padding: 8px;
            transition: color 0.3s ease;
        }
        
        .btn-remove:hover {
            color: #dc3545;
        }
        
        .cart-summary {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            padding: 30px;
            position: sticky;
            top: 120px;
            height: fit-content;
        }
        
        .cart-summary h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-row.total {
            border-bottom: 2px solid var(--primary-color);
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 10px;
        }
        
        .btn-checkout {
            width: 100%;
            margin-top: 20px;
            padding: 16px;
            font-size: 16px;
        }
        
        @media (max-width: 968px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
    
    <script>
        function increaseQty(productId) {
            const input = document.getElementById('qty_' + productId);
            input.value = parseInt(input.value) + 1;
            document.getElementById('update_' + productId).style.display = 'inline-block';
        }
        
        function decreaseQty(productId) {
            const input = document.getElementById('qty_' + productId);
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                document.getElementById('update_' + productId).style.display = 'inline-block';
            }
        }
    </script>

<?php require_once 'includes/footer.php'; ?>

