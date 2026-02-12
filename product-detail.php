<?php
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/cart-functions.php';

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    addToCart($product_id, $quantity);
    header('Location: product-detail.php?id=' . $product_id . '&added=1');
    exit;
}

$product_id = $_GET['id'] ?? 0;
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.id = ? AND p.deleted_at IS NULL");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    redirect('shop.php');
}

$images = [];
if ($product['images']) {
    $images = json_decode($product['images'], true) ?: [];
}
if ($product['image']) {
    array_unshift($images, $product['image']);
}

// Check if product_reviews table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'product_reviews'");
if (!$table_check || $table_check->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        reviewer_name VARCHAR(100) NOT NULL,
        reviewer_email VARCHAR(100) NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        review_text TEXT NOT NULL,
        approved TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
}

// Get reviews
$reviews = [];
$reviews_stmt = $conn->prepare("SELECT * FROM product_reviews WHERE product_id = ? AND approved = 1 ORDER BY created_at DESC LIMIT 10");
if ($reviews_stmt) {
    $reviews_stmt->bind_param("i", $product_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    while ($review = $reviews_result->fetch_assoc()) {
        $reviews[] = $review;
    }
    $reviews_stmt->close();
}

// Get average rating
$avg_rating = 0;
$review_count = 0;
$rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM product_reviews WHERE product_id = ? AND approved = 1");
if ($rating_stmt) {
    $rating_stmt->bind_param("i", $product_id);
    $rating_stmt->execute();
    $rating_result = $rating_stmt->get_result();
    $rating_data = $rating_result->fetch_assoc();
    if ($rating_data) {
        $avg_rating = $rating_data['avg_rating'] ?? 0;
        $review_count = $rating_data['review_count'] ?? 0;
    }
    $rating_stmt->close();
}

$conn->close();
?>
    <main class="product-detail-page">
        <div class="container">
            <div class="product-detail-layout">
                <div class="product-images reveal">
                    <div class="main-image img-zoom">
                        <img src="<?php echo htmlspecialchars($images[0] ?? 'assets/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainImage">
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($images as $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Thumbnail" onclick="changeImage('<?php echo htmlspecialchars($img); ?>')" class="img-zoom">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h1 class="reveal"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <?php if ($product['price']): ?>
                        <div class="product-price-main">
                            <span class="price-value">$<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['added'])): ?>
                        <div class="success-message" style="margin: 20px 0;">
                            <i class="fas fa-check-circle"></i> Product added to cart!
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['review_submitted'])): ?>
                        <div class="success-message" style="margin: 20px 0;">
                            <i class="fas fa-check-circle"></i> Thank you! Your review has been submitted and is pending approval.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message" style="margin: 20px 0;">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($product['description']): ?>
                        <div class="product-description">
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reviews Section (Expandable) -->
                    <div class="product-section collapsible-section">
                        <h3 class="section-header" onclick="toggleSection(this)">
                            <span>Reviews (<?php echo $review_count; ?>)</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </h3>
                        <div class="section-content" style="display: none;">
                            <?php if ($review_count > 0): ?>
                                <div class="rating-summary">
                                    <div class="avg-rating">
                                        <span class="rating-value"><?php echo number_format($avg_rating, 1); ?></span>
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'active' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="review-count-text">Based on <?php echo $review_count; ?> review(s)</span>
                                    </div>
                                </div>
                                <div class="reviews-list">
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="review-item">
                                            <div class="review-header">
                                                <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                            </div>
                                            <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--text-secondary); font-style: italic;">No reviews yet. Be the first to review!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Add Review Section (Expandable) -->
                    <div class="product-section collapsible-section">
                        <h3 class="section-header" onclick="toggleSection(this)">
                            <span>Add Review</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </h3>
                        <div class="section-content" style="display: none;">
                            <form method="POST" action="add-review.php" class="review-form">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <div class="form-group">
                                    <label>Your Name *</label>
                                    <input type="text" name="reviewer_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Your Email *</label>
                                    <input type="email" name="reviewer_email" required>
                                </div>
                                <div class="form-group">
                                    <label>Rating *</label>
                                    <div class="rating-input">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" required>
                                            <label for="rating<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Your Review *</label>
                                    <textarea name="review_text" rows="4" required></textarea>
                                </div>
                                <button type="submit" class="btn-primary">Submit Review</button>
                            </form>
                        </div>
                    </div>

                    <?php if ($product['product_details']): ?>
                        <div class="product-section collapsible-section">
                            <h3 class="section-header" onclick="toggleSection(this)">
                                <span>Product Details</span>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </h3>
                            <div class="section-content" style="display: none;">
                                <div class="details-content">
                                    <?php echo nl2br(htmlspecialchars($product['product_details'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Shipping Details -->
                    <div class="shipping-details">
                        <h4><i class="fas fa-shipping-fast"></i> Shipping Information</h4>
                        <ul>
                            <li><span class="emoji">🚚</span> Free shipping on orders over $100</li>
                            <li><span class="emoji">⏱️</span> Standard delivery: 5-7 business days</li>
                            <li><span class="emoji">🌍</span> International shipping available</li>
                            <li><span class="emoji">📦</span> Secure packaging guaranteed</li>
                            <li><span class="emoji">↩️</span> 30-day return policy</li>
                        </ul>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <form method="POST" class="add-to-cart-form">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="quantity-selector">
                            <label>Quantity:</label>
                            <div class="quantity-controls">
                                <button type="button" onclick="decreaseQuantity()" class="qty-btn">-</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" readonly>
                                <button type="button" onclick="increaseQuantity()" class="qty-btn">+</button>
                            </div>
                        </div>
                        <div class="product-actions">
                            <button type="submit" name="add_to_cart" class="btn-primary btn-cart">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            <a href="shop.php" class="btn-secondary">
                                <i class="fas fa-store"></i> Continue Shopping
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <style>
        .product-price-main {
            font-size: 32px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 20px 0;
        }
        
        .product-section {
            margin: 30px 0;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        
        .section-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .section-header:hover {
            color: var(--secondary-color);
        }
        
        .toggle-icon {
            transition: transform 0.3s ease;
        }
        
        .section-header.active .toggle-icon {
            transform: rotate(180deg);
        }
        
        .section-content {
            margin-top: 15px;
        }
        
        .rating-summary {
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(0, 31, 63, 0.05);
            border-radius: 8px;
        }
        
        .avg-rating {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .rating-value {
            font-size: 48px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .stars {
            display: flex;
            gap: 5px;
        }
        
        .stars .fa-star {
            color: #ddd;
            font-size: 20px;
        }
        
        .stars .fa-star.active {
            color: #FFD700;
        }
        
        .review-count-text {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .review-item {
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .review-rating {
            display: flex;
            gap: 2px;
        }
        
        .review-rating .fa-star {
            color: #ddd;
            font-size: 14px;
        }
        
        .review-rating .fa-star.active {
            color: #FFD700;
        }
        
        .review-date {
            color: var(--text-secondary);
            font-size: 12px;
            margin-left: auto;
        }
        
        .review-text {
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .review-form {
            margin-top: 20px;
        }
        
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }
        
        .rating-input input[type="radio"] {
            display: none;
        }
        
        .rating-input .star-label {
            cursor: pointer;
            font-size: 24px;
            color: #ddd;
            transition: color 0.2s;
        }
        
        .rating-input input[type="radio"]:checked ~ .star-label,
        .rating-input .star-label:hover,
        .rating-input .star-label:hover ~ .star-label {
            color: #FFD700;
        }
        
        .shipping-details {
            margin: 30px 0;
            padding: 20px;
            background: rgba(0, 31, 63, 0.03);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .shipping-details h4 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .shipping-details ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .shipping-details li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .shipping-details .emoji {
            font-size: 18px;
        }
        
        .add-to-cart-form {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }
        
        .quantity-selector {
            margin-bottom: 20px;
        }
        
        .quantity-selector label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .qty-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border-color);
            background: var(--background-color);
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        
        .qty-btn:hover {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
        
        .add-to-cart-form #quantity {
            width: 80px;
            text-align: center;
            border: 1px solid var(--border-color);
            padding: 10px;
        }
        
        .product-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-cart {
            flex: 1;
            min-width: 200px;
        }
    </style>
    
    <script>
        function changeImage(src) {
            document.getElementById('mainImage').src = src;
        }
        
        function toggleSection(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('active');
            if (content.style.display === 'none') {
                content.style.display = 'block';
            } else {
                content.style.display = 'none';
            }
        }
        
        function increaseQuantity() {
            const qtyInput = document.getElementById('quantity');
            qtyInput.value = parseInt(qtyInput.value) + 1;
        }
        
        function decreaseQuantity() {
            const qtyInput = document.getElementById('quantity');
            if (parseInt(qtyInput.value) > 1) {
                qtyInput.value = parseInt(qtyInput.value) - 1;
            }
        }
    </script>

<?php require_once 'includes/footer.php'; ?>

