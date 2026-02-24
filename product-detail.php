<?php
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/cart-functions.php';

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    addToCart($product_id, $quantity);
    $cart_conn = getDBConnection();
    $cart_row = $cart_conn->query("SELECT slug, name FROM products WHERE id = " . (int)$product_id)->fetch_assoc();
    $cart_conn->close();
    $cart_slug = (!empty($cart_row['slug']) ? $cart_row['slug'] : slugify($cart_row['name'] ?? 'product'));
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/product-detail/' . rawurlencode($cart_slug) . '?added=1');
    exit;
}

// Handle quote request form
$quote_success = false;
$quote_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_type']) && $_POST['form_type'] == 'quote') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $company = sanitize($_POST['company'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $message = sanitize($_POST['message'] ?? '');
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($name && $email && $product_id && $quantity >= 100) {
        $conn = getDBConnection();
        
        // Check if quote_requests table exists, create it if it doesn't
        $table_check = $conn->query("SHOW TABLES LIKE 'quote_requests'");
        if (!$table_check || $table_check->num_rows == 0) {
            $create_table_sql = "CREATE TABLE IF NOT EXISTS quote_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                customer_name VARCHAR(100) NOT NULL,
                customer_email VARCHAR(100) NOT NULL,
                customer_phone VARCHAR(20),
                company_name VARCHAR(255),
                message TEXT,
                quantity INT NOT NULL DEFAULT 1,
                user_id INT NULL,
                status ENUM('pending', 'reviewing', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $conn->query($create_table_sql);
        }
        
        // Check if company_name column exists, add if not
        $column_check = $conn->query("SHOW COLUMNS FROM quote_requests LIKE 'company_name'");
        if (!$column_check || $column_check->num_rows == 0) {
            $conn->query("ALTER TABLE quote_requests ADD COLUMN company_name VARCHAR(255) AFTER customer_phone");
        }
        
        $stmt = $conn->prepare("INSERT INTO quote_requests (product_id, customer_name, customer_email, customer_phone, company_name, message, quantity, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $stmt->bind_param("isssssii", $product_id, $name, $email, $phone, $company, $message, $quantity, $user_id);
        
        if ($stmt->execute()) {
            $quote_success = true;
            $product_result = $conn->query("SELECT name FROM products WHERE id = $product_id");
            $product_name = $product_result && $product_result->num_rows > 0 ? $product_result->fetch_assoc()['name'] : 'Product';
            @mail(ADMIN_EMAIL, "New Quote Request - " . $product_name, "New quote request received for: " . $product_name . "\n\nCustomer: $name\nEmail: $email\nPhone: $phone\nCompany: $company\nQuantity: $quantity\nMessage: $message", "From: $email");
        } else {
            $quote_error = "Failed to submit request. Please try again.";
        }
        $stmt->close();
        $conn->close();
    } else {
        $quote_error = $name && $email && $product_id && $quantity > 0 && $quantity < 100
            ? "Minimum quantity for wholesale is 100."
            : "Please fill in all required fields.";
    }
}

// Handle customization form
$customize_success = false;
$customize_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_type']) && $_POST['form_type'] == 'customize') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $company = sanitize($_POST['company'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $description = sanitize($_POST['description'] ?? '');
    $customization_options = isset($_POST['customization_options']) ? $_POST['customization_options'] : [];
    $customization_ability = sanitize($_POST['customization_ability'] ?? '');
    $product_id = intval($_POST['product_id'] ?? 0);
    
    // Handle file uploads
    $uploaded_images = [];
    if (isset($_FILES['customization_images']) && !empty($_FILES['customization_images']['name'][0])) {
        $upload_dir = 'uploads/customizations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['customization_images']['name'] as $key => $filename) {
            if ($_FILES['customization_images']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['customization_images']['tmp_name'][$key];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $new_filename = uniqid() . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    $uploaded_images[] = $upload_path;
                }
            }
        }
    }
    
    if ($name && $email && $description && $product_id && $quantity >= 100) {
        $conn = getDBConnection();
        
        // Check if product_customizations table exists, create it if it doesn't
        $table_check = $conn->query("SHOW TABLES LIKE 'product_customizations'");
        if (!$table_check || $table_check->num_rows == 0) {
            $create_table_sql = "CREATE TABLE IF NOT EXISTS product_customizations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                customer_name VARCHAR(100) NOT NULL,
                customer_email VARCHAR(100) NOT NULL,
                customer_phone VARCHAR(20),
                company_name VARCHAR(255),
                customizations TEXT,
                description TEXT NOT NULL,
                customization_options TEXT,
                customization_ability VARCHAR(100),
                images TEXT,
                quantity INT NOT NULL DEFAULT 1,
                user_id INT NULL,
                status ENUM('pending', 'reviewing', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $conn->query($create_table_sql);
        }
        
        // Check and add missing columns
        $columns_to_add = [
            'company_name' => "ALTER TABLE product_customizations ADD COLUMN company_name VARCHAR(255) AFTER customer_phone",
            'customization_options' => "ALTER TABLE product_customizations ADD COLUMN customization_options TEXT AFTER customizations",
            'customization_ability' => "ALTER TABLE product_customizations ADD COLUMN customization_ability VARCHAR(100) AFTER customization_options"
        ];
        
        foreach ($columns_to_add as $col => $sql) {
            $col_check = $conn->query("SHOW COLUMNS FROM product_customizations LIKE '$col'");
            if (!$col_check || $col_check->num_rows == 0) {
                $conn->query($sql);
            }
        }
        
        $customizations_json = json_encode($customization_options);
        $images_json = json_encode($uploaded_images);
        
        $stmt = $conn->prepare("INSERT INTO product_customizations (product_id, customer_name, customer_email, customer_phone, company_name, customizations, customization_options, customization_ability, description, images, quantity, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $customizations_text = $description; // Using description as customizations text
        $stmt->bind_param("isssssssssii", $product_id, $name, $email, $phone, $company, $customizations_text, $customizations_json, $customization_ability, $description, $images_json, $quantity, $user_id);
        
        if ($stmt->execute()) {
            $customize_success = true;
            $product_result = $conn->query("SELECT name FROM products WHERE id = $product_id");
            $product_name = $product_result && $product_result->num_rows > 0 ? $product_result->fetch_assoc()['name'] : 'Product';
            @mail(ADMIN_EMAIL, "New Customization Request - " . $product_name, "New customization request received for: " . $product_name . "\n\nCustomer: $name\nEmail: $email\nPhone: $phone\nCompany: $company\nQuantity: $quantity\nDescription: $description", "From: $email");
        } else {
            $customize_error = "Failed to submit request. Please try again.";
        }
        $stmt->close();
        $conn->close();
    } else {
        $customize_error = ($name && $email && $description && $product_id && $quantity > 0 && $quantity < 100)
            ? "Minimum quantity for customization is 100."
            : "Please fill in all required fields.";
    }
}

$product_slug_param = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$product_id_param = isset($_GET['id']) ? intval($_GET['id']) : 0;
$conn = getDBConnection();

// Ensure products table has slug column
$col = $conn->query("SHOW COLUMNS FROM products LIKE 'slug'");
if (!$col || $col->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN slug VARCHAR(255) DEFAULT NULL");
}

$product = null;
$product_id = 0;

if ($product_slug_param !== '') {
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
                            JOIN categories c ON p.category_id = c.id 
                            WHERE p.slug = ? AND p.deleted_at IS NULL");
    $stmt->bind_param("s", $product_slug_param);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($product) {
        $product_id = (int)$product['id'];
    }
}

if (!$product && $product_id_param > 0) {
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
                            JOIN categories c ON p.category_id = c.id 
                            WHERE p.id = ? AND p.deleted_at IS NULL");
    $stmt->bind_param("i", $product_id_param);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($product) {
        $product_id = (int)$product['id'];
        $slug = !empty($product['slug']) ? $product['slug'] : null;
        if ($slug === null || $slug === '') {
            $slug = slugify($product['name'] ?? 'product');
            $check = $conn->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
            $n = 0;
            $base_slug = $slug;
            do {
                $check->bind_param("si", $slug, $product_id);
                $check->execute();
                $taken = $check->get_result()->num_rows > 0;
                if ($taken) {
                    $n++;
                    $slug = $base_slug . '-' . $n;
                }
            } while ($taken);
            $conn->query("UPDATE products SET slug = '" . $conn->real_escape_string($slug) . "' WHERE id = " . $product_id);
        }
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/product-detail/' . rawurlencode($slug), true, 301);
        exit;
    }
}

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
// Ensure image URLs work from any URL depth (e.g. /product-detail/slug): use root-relative or base-prefixed paths
$base_prefix = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
$image_urls = array_map(function($path) use ($base_prefix) {
    if ($path === '' || $path === null) return $base_prefix . 'assets/images/placeholder.jpg';
    if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) return $path;
    $path = ltrim($path, '/');
    return $base_prefix . $path;
}, $images);
if (empty($image_urls)) {
    $image_urls = [$base_prefix . 'assets/images/placeholder.jpg'];
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

// Generate SKU if not exists
$product_sku = $product['sku'] ?? 'FAY-' . str_pad($product['id'], 6, '0', STR_PAD_LEFT);
$stock_status = 'Make to order'; // Can be enhanced with actual stock management
$delivery_estimate = 'Based on order.';

$conn->close();

// Prepare product schema for SEO
$product_schema = [
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $product['name'],
    'description' => $product['description'] ?? '',
    'image' => $images,
    'sku' => $product_sku,
    'brand' => [
        '@type' => 'Brand',
        'name' => SITE_NAME
    ],
    'offers' => [
        '@type' => 'Offer',
        'price' => $product['price'] ?? 0,
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
        'url' => rtrim(SITE_URL, '/') . '/product-detail/' . rawurlencode(!empty($product['slug']) ? $product['slug'] : slugify($product['name'] ?? 'product'))
    ]
];
if ($avg_rating > 0) {
    $product_schema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => $avg_rating,
        'reviewCount' => $review_count
    ];
}
?>
<script type="application/ld+json">
<?php echo json_encode($product_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>

<main class="product-detail-page premium-product-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li><a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/">Home</a></li>
                <li><a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/explore">Catalog</a></li>
                <li><a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/products?category=<?php echo urlencode($product['category_name'] ?? ''); ?>"><?php echo htmlspecialchars($product['category_name'] ?? 'Products'); ?></a></li>
                <li class="active"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success premium-alert">
                <i class="fas fa-check-circle"></i> Product added to cart successfully!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['review_submitted'])): ?>
            <div class="alert alert-success premium-alert">
                <i class="fas fa-check-circle"></i> Thank you! Your review has been submitted and is pending approval.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error premium-alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="product-detail-layout premium-layout">
            <!-- Product Images Gallery -->
            <div class="product-gallery-section">
                <div class="product-images reveal">
                    <div class="main-image-container">
                        <div class="image-badges">
                            <span class="badge badge-premium"><i class="fas fa-award"></i> Handcrafted</span>
                            <span class="badge badge-premium"><i class="fas fa-gem"></i> Premium Leather</span>
                        </div>
                        <div class="main-image-wrapper" id="mainImageWrapper">
<img src="<?php echo htmlspecialchars($image_urls[0]); ?>"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 id="mainImage"
                                 class="main-product-image"
                                 loading="lazy">
                            <button class="zoom-btn" onclick="openFullscreen()" title="View Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (count($image_urls) > 1): ?>
                        <div class="thumbnail-gallery">
                            <?php foreach ($image_urls as $index => $img_url): ?>
                                <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeImage('<?php echo htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $index; ?>)">
                                    <img src="<?php echo htmlspecialchars($img_url); ?>" alt="Thumbnail <?php echo $index + 1; ?>" loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Information -->
            <div class="product-info-section">
                <div class="product-header">
                    <h1 class="product-title premium-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <!-- Rating & Reviews Summary -->
                    <?php if ($review_count > 0): ?>
                        <div class="rating-summary-header">
                            <div class="stars-inline">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'active' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-value-header"><?php echo number_format($avg_rating, 1); ?></span>
                            <span class="review-count-header">(<?php echo $review_count; ?> <?php echo $review_count === 1 ? 'review' : 'reviews'; ?>)</span>
                            <a href="#reviews-section" class="review-link">View All</a>
                        </div>
                    <?php endif; ?>

                    <!-- Price Section -->
                    <div class="product-price-section premium-price">
                        <?php if ($product['price'] && $product['price'] > 0): ?>
                            <span class="price-main">$<?php echo number_format($product['price'], 2); ?></span>
                        <?php else: ?>
                            <span class="price-main price-contact">Contact for Price</span>
                        <?php endif; ?>
                    </div>

                    <!-- Product Meta Info -->
                    <div class="product-meta-info">
                        <div class="meta-item">
                            <span class="meta-label">SKU:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($product_sku); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Availability:</span>
                            <span class="meta-value stock-status in-stock">
                                <i class="fas fa-check-circle"></i> <?php echo $stock_status; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Delivery:</span>
                            <span class="meta-value">
                                <i class="fas fa-truck"></i> <?php echo $delivery_estimate; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">MOQ:</span>
                            <span class="meta-value"><?php echo number_format($product['moq'] ?? 1); ?> <?php echo ($product['moq'] ?? 1) > 1 ? 'pieces' : 'piece'; ?></span>
                        </div>
                    </div>

                    <!-- Product Actions Section (above Key Features) -->
                    <div class="product-actions-section premium-actions">
                        <div class="action-buttons-three">
                            <button type="button" onclick="openQuoteModal()" class="btn-action btn-get-quote premium-btn">
                                <i class="fas fa-boxes"></i>
                                <span>Wholesale</span>
                            </button>
                            <button type="button" onclick="openCustomizeModal()" class="btn-action btn-customization premium-btn">
                                <i class="fas fa-palette"></i>
                                <span>Customization</span>
                            </button>
                            <a href="https://wa.me/923252100730?text=<?php echo urlencode('Hi! I\'m interested in: ' . $product['name']); ?>" target="_blank" class="btn-action btn-chat-now premium-btn">
                                <i class="fab fa-whatsapp"></i>
                                <span>Chat Now</span>
                            </a>
                        </div>

                        <div class="secondary-actions">
                            <button type="button" class="btn-icon btn-wishlist" onclick="addToWishlist(<?php echo $product['id']; ?>)" title="Add to Wishlist">
                                <i class="far fa-heart"></i>
                                <span>Wishlist</span>
                            </button>
                            <button type="button" class="btn-icon btn-share" onclick="shareProduct()" title="Share Product">
                                <i class="fas fa-share-alt"></i>
                                <span>Share</span>
                            </button>
                        </div>
                    </div>

                    <!-- Key Selling Points -->
                    <div class="key-features">
                        <h3 class="features-title">Key Features</h3>
                        <ul class="features-list">
                            <li><i class="fas fa-check-circle"></i> Premium Handcrafted Quality</li>
                            <li><i class="fas fa-check-circle"></i> Genuine Leather Material</li>
                            <li><i class="fas fa-check-circle"></i> Expert Artisan Craftsmanship</li>
                            <li><i class="fas fa-check-circle"></i> Durable & Long-lasting</li>
                            <li><i class="fas fa-check-circle"></i> 30-Day Satisfaction Guarantee</li>
                        </ul>
                    </div>

                    <!-- Guarantee Badge -->
                    <div class="guarantee-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>100% Satisfaction Guaranteed</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information Sections -->
        <div class="product-details-sections">
            <!-- Product Description -->
            <section class="detail-section" id="description-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-align-left"></i>
                    Product Description
                </h2>
                <div class="section-content premium-content">
                    <?php if ($product['description']): ?>
                        <div class="product-description-text">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    <?php else: ?>
                        <p class="no-content">No description available.</p>
                    <?php endif; ?>
                    
                    <!-- Craftsmanship Story -->
                    <div class="craftsmanship-story">
                        <h3>Craftsmanship Excellence</h3>
                        <p>Each piece in our collection is meticulously handcrafted by skilled artisans who have dedicated their lives to perfecting the art of leatherwork. Our commitment to quality ensures that every product meets the highest standards of excellence, combining traditional techniques with modern design sensibilities.</p>
                    </div>
                </div>
            </section>

            <!-- Specifications Table -->
            <section class="detail-section" id="specifications-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-list-alt"></i>
                    Specifications
                </h2>
                <div class="section-content premium-content">
                    <table class="specifications-table">
                        <tbody>
                            <tr>
                                <td class="spec-label">Material</td>
                                <td class="spec-value">Premium Genuine Leather</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Dimensions</td>
                                <td class="spec-value">Custom (varies by product)</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Weight</td>
                                <td class="spec-value">Varies by size</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Lining</td>
                                <td class="spec-value">Premium Fabric Lining</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Hardware</td>
                                <td class="spec-value">Premium Metal Hardware</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Origin</td>
                                <td class="spec-value">Handcrafted in Pakistan</td>
                            </tr>
                            <tr>
                                <td class="spec-label">Category</td>
                                <td class="spec-value"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td class="spec-label">SKU</td>
                                <td class="spec-value"><?php echo htmlspecialchars($product_sku); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Care Instructions -->
            <section class="detail-section" id="care-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-spa"></i>
                    Care Instructions
                </h2>
                <div class="section-content premium-content">
                    <div class="care-instructions">
                        <h3>How to Care for Your Leather Product</h3>
                        <ul class="care-list">
                            <li><strong>Cleaning:</strong> Use a soft, damp cloth to gently wipe away dirt and dust. Avoid using harsh chemicals or excessive water.</li>
                            <li><strong>Conditioning:</strong> Apply a high-quality leather conditioner every 3-6 months to maintain suppleness and prevent cracking.</li>
                            <li><strong>Storage:</strong> Store in a cool, dry place away from direct sunlight. Use a dust bag or breathable cover when not in use.</li>
                            <li><strong>Protection:</strong> Avoid exposure to extreme temperatures, moisture, and direct sunlight for extended periods.</li>
                            <li><strong>Maintenance:</strong> Regularly inspect for signs of wear and address any issues promptly to extend the product's lifespan.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Manufacturing Details -->
            <section class="detail-section" id="manufacturing-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-industry"></i>
                    Manufacturing Details
                </h2>
                <div class="section-content premium-content">
                    <div class="manufacturing-info">
                        <h3>Our Craftsmanship</h3>
                        <p>Every product is carefully crafted by experienced artisans using time-honored techniques passed down through generations. Our manufacturing process combines traditional handcrafting methods with modern quality control standards to ensure each piece meets our exacting standards.</p>
                        
                        <h3>Quality Assurance</h3>
                        <ul>
                            <li>Rigorous quality inspection at every stage</li>
                            <li>Premium material selection and sourcing</li>
                            <li>Hand-finished details and attention to craftsmanship</li>
                            <li>Durability testing and quality certification</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Why Choose Us -->
            <section class="detail-section" id="why-choose-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-star"></i>
                    Why Choose <?php echo SITE_NAME; ?>
                </h2>
                <div class="section-content premium-content">
                    <div class="why-choose-grid">
                        <div class="why-item">
                            <i class="fas fa-award"></i>
                            <h4>Premium Quality</h4>
                            <p>Only the finest materials and craftsmanship go into every product.</p>
                        </div>
                        <div class="why-item">
                            <i class="fas fa-users"></i>
                            <h4>Expert Artisans</h4>
                            <p>Skilled craftspeople with decades of experience in leatherwork.</p>
                        </div>
                        <div class="why-item">
                            <i class="fas fa-heart"></i>
                            <h4>Handcrafted</h4>
                            <p>Each piece is individually crafted with care and attention to detail.</p>
                        </div>
                        <div class="why-item">
                            <i class="fas fa-shield-alt"></i>
                            <h4>Guaranteed</h4>
                            <p>100% satisfaction guarantee on all our products.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQs -->
            <section class="detail-section" id="faq-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-question-circle"></i>
                    Frequently Asked Questions
                </h2>
                <div class="section-content premium-content">
                    <div class="faq-list">
                        <div class="faq-item">
                            <h3 class="faq-question" onclick="toggleFAQ(this)">
                                What is the minimum order quantity?
                                <i class="fas fa-chevron-down"></i>
                            </h3>
                            <div class="faq-answer">
                                <p>The minimum order quantity (MOQ) for this product is <?php echo number_format($product['moq'] ?? 1); ?> <?php echo ($product['moq'] ?? 1) > 1 ? 'pieces' : 'piece'; ?>. For bulk orders, please contact us for custom pricing.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <h3 class="faq-question" onclick="toggleFAQ(this)">
                                How long does shipping take?
                                <i class="fas fa-chevron-down"></i>
                            </h3>
                            <div class="faq-answer">
                                <p>Standard shipping takes 5-7 business days. Express shipping (2-3 business days) is available for an additional fee. International shipping times vary by destination.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <h3 class="faq-question" onclick="toggleFAQ(this)">
                                Can I customize this product?
                                <i class="fas fa-chevron-down"></i>
                            </h3>
                            <div class="faq-answer">
                                <p>Yes! We offer customization options including colors, sizes, and personalization. Please contact us or visit our "Design Your Own Product" section for more details.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <h3 class="faq-question" onclick="toggleFAQ(this)">
                                What is your return policy?
                                <i class="fas fa-chevron-down"></i>
                            </h3>
                            <div class="faq-answer">
                                <p>We offer a 30-day return policy. Items must be unused and in original packaging. Contact our support team to initiate a return, and we'll process your refund within 5-7 business days.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <h3 class="faq-question" onclick="toggleFAQ(this)">
                                Do you offer wholesale pricing?
                                <i class="fas fa-chevron-down"></i>
                            </h3>
                            <div class="faq-answer">
                                <p>Yes, we offer competitive wholesale pricing for bulk orders. Please contact us with your requirements, and we'll provide a customized quote.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
                    
            <!-- Reviews Section -->
            <section class="detail-section" id="reviews-section">
                <h2 class="section-title premium-section-title">
                    <i class="fas fa-star"></i>
                    Customer Reviews
                    <span class="review-count-badge"><?php echo $review_count; ?> <?php echo $review_count === 1 ? 'Review' : 'Reviews'; ?></span>
                </h2>
                <div class="section-content premium-content">
                    <?php if ($review_count > 0): ?>
                        <div class="rating-overview">
                            <div class="rating-display">
                                <div class="rating-number"><?php echo number_format($avg_rating, 1); ?></div>
                                <div class="rating-stars-large">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-text">Based on <?php echo $review_count; ?> <?php echo $review_count === 1 ? 'review' : 'reviews'; ?></div>
                            </div>
                        </div>
                        
                        <div class="reviews-list-premium">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header-premium">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                <?php echo strtoupper(substr($review['reviewer_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                                <div class="review-rating-premium">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="review-date-premium"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <p class="review-text-premium"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-reviews">
                            <i class="fas fa-comment-alt"></i>
                            <p>No reviews yet. Be the first to review this product!</p>
                        </div>
                    <?php endif; ?>

                    <!-- Add Review Form -->
                    <div class="add-review-section">
                        <h3 class="add-review-title">Write a Review</h3>
                        <form method="POST" action="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/add-review" class="review-form-premium">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Your Name *</label>
                                    <input type="text" name="reviewer_name" required class="form-input">
                                </div>
                                <div class="form-group">
                                    <label>Your Email *</label>
                                    <input type="email" name="reviewer_email" required class="form-input">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Rating *</label>
                                <div class="rating-input-premium">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" required>
                                        <label for="rating<?php echo $i; ?>" class="star-label-premium">
                                            <i class="fas fa-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Your Review *</label>
                                <textarea name="review_text" rows="5" required class="form-textarea" placeholder="Share your experience with this product..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary btn-submit-review">
                                <i class="fas fa-paper-plane"></i> Submit Review
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Fullscreen Image Modal -->
    <div id="fullscreenModal" class="fullscreen-modal" onclick="closeFullscreen()">
        <span class="close-fullscreen" onclick="closeFullscreen()">&times;</span>
        <img class="fullscreen-image" id="fullscreenImage" src="" alt="Fullscreen view">
    </div>

    <!-- Quote Request Modal -->
    <div id="quoteModal" class="premium-modal">
        <div class="modal-overlay" onclick="closeQuoteModal()"></div>
        <div class="modal-content-premium">
            <div class="modal-header">
                <h2><i class="fas fa-boxes"></i> Wholesale Request</h2>
                <button class="modal-close" onclick="closeQuoteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($quote_success): ?>
                    <div class="modal-success">
                        <i class="fas fa-check-circle"></i>
                        <h3>Thank You!</h3>
                        <p>Your wholesale request has been submitted successfully. We'll get back to you soon.</p>
                        <button onclick="closeQuoteModal()" class="btn-primary">Close</button>
                    </div>
                <?php else: ?>
                    <?php if ($quote_error): ?>
                        <div class="modal-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($quote_error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="premium-form-modal">
                        <input type="hidden" name="form_type" value="quote">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        
                        <div class="form-group-modal">
                            <label>Full Name *</label>
                            <input type="text" name="name" required class="form-input-modal" placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Email Address *</label>
                            <input type="email" name="email" required class="form-input-modal" placeholder="Enter your email">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Phone Number *</label>
                            <input type="tel" name="phone" required class="form-input-modal" placeholder="Enter your phone number">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Company Name</label>
                            <input type="text" name="company" class="form-input-modal" placeholder="Enter your company name (optional)">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" min="100" value="100" required class="form-input-modal" placeholder="Min. 100">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Additional Details</label>
                            <textarea name="message" rows="4" class="form-textarea-modal" placeholder="Any additional information about your quote request..."></textarea>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="submit" class="btn-primary btn-submit-modal">
                                <i class="fas fa-paper-plane"></i> Submit Wholesale Request
                            </button>
                            <button type="button" onclick="closeQuoteModal()" class="btn-secondary btn-cancel-modal">Cancel</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Customization Request Modal -->
    <div id="customizeModal" class="premium-modal">
        <div class="modal-overlay" onclick="closeCustomizeModal()"></div>
        <div class="modal-content-premium">
            <div class="modal-header">
                <h2><i class="fas fa-palette"></i> Request Customization</h2>
                <button class="modal-close" onclick="closeCustomizeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($customize_success): ?>
                    <div class="modal-success">
                        <i class="fas fa-check-circle"></i>
                        <h3>Thank You!</h3>
                        <p>Your customization request has been submitted successfully. We'll get back to you soon.</p>
                        <button onclick="closeCustomizeModal()" class="btn-primary">Close</button>
                    </div>
                <?php else: ?>
                    <?php if ($customize_error): ?>
                        <div class="modal-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($customize_error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="premium-form-modal">
                        <input type="hidden" name="form_type" value="customize">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        
                        <div class="form-group-modal">
                            <label>Full Name *</label>
                            <input type="text" name="name" required class="form-input-modal" placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Email Address *</label>
                            <input type="email" name="email" required class="form-input-modal" placeholder="Enter your email">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Phone Number *</label>
                            <input type="tel" name="phone" required class="form-input-modal" placeholder="Enter your phone number">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Company Name</label>
                            <input type="text" name="company" class="form-input-modal" placeholder="Enter your company name (optional)">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" min="100" value="100" required class="form-input-modal" placeholder="Min. 100">
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Brief Description of Required Customization *</label>
                            <textarea name="description" rows="4" required class="form-textarea-modal" placeholder="Describe the changes or customizations you'd like to make to this product..."></textarea>
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Customization Options</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="customization_options[]" value="customized_logo">
                                    <span>Customized logo (Min. order: 300 pieces)</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="customization_options[]" value="customized_packaging">
                                    <span>Customized packaging (Min. order: 500 pieces)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Supplier's Customization Ability *</label>
                            <select name="customization_ability" required class="form-input-modal">
                                <option value="">Select customization ability...</option>
                                <option value="minor">Minor customization</option>
                                <option value="drawing">Drawing-based customization</option>
                                <option value="sample">Sample-based customization</option>
                                <option value="full">Full customization</option>
                            </select>
                        </div>
                        
                        <div class="form-group-modal">
                            <label>Upload Images (Optional)</label>
                            <div class="file-upload-area">
                                <input type="file" name="customization_images[]" id="customization_images" multiple accept="image/*" class="file-input">
                                <label for="customization_images" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload or drag and drop</span>
                                    <small>You can upload multiple images (JPG, PNG, GIF)</small>
                                </label>
                                <div id="imagePreview" class="image-preview-container"></div>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="submit" class="btn-primary btn-submit-modal">
                                <i class="fas fa-paper-plane"></i> Submit Customization Request
                            </button>
                            <button type="button" onclick="closeCustomizeModal()" class="btn-secondary btn-cancel-modal">Cancel</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        /* Premium Product Detail Page Styles */
        .premium-product-page {
            padding: 40px 0;
            background: #f8f9fa;
        }

        /* Breadcrumb */
        .breadcrumb-nav {
            margin-bottom: 30px;
        }
        .breadcrumb {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 10px;
            font-size: 14px;
        }
        .breadcrumb li {
            color: #666;
        }
        .breadcrumb li:not(:last-child)::after {
            content: '/';
            margin-left: 10px;
            color: #999;
        }
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        .breadcrumb a:hover {
            color: var(--secondary-color);
        }
        .breadcrumb .active {
            color: #333;
        }

        /* Alerts */
        .premium-alert {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Layout */
        .premium-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }

        /* Product Gallery */
        .product-gallery-section {
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        .main-image-container {
            position: relative;
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .image-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-premium {
            background: linear-gradient(135deg, #D4AF37 0%, #FFD700 100%);
            color: #001f3f;
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
        }
        .main-image-wrapper {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
            cursor: zoom-in;
        }
        .main-product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .main-image-wrapper:hover .main-product-image {
            transform: scale(1.05);
        }
        .zoom-btn {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0, 31, 63, 0.8);
            color: #fff;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 5;
        }
        .zoom-btn:hover {
            background: var(--primary-color);
            transform: scale(1.1);
        }
        .thumbnail-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
        }
        .thumbnail-item {
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
            background: #fff;
        }
        .thumbnail-item:hover,
        .thumbnail-item.active {
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }
        .thumbnail-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Product Info */
        .product-info-section {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .product-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            line-height: 1.3;
            font-family: 'Playfair Display', serif;
        }
        .rating-summary-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .stars-inline {
            display: flex;
            gap: 3px;
        }
        .stars-inline .fa-star {
            color: #ddd;
            font-size: 16px;
        }
        .stars-inline .fa-star.active {
            color: #FFD700;
        }
        .rating-value-header {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 18px;
        }
        .review-count-header {
            color: #666;
            font-size: 14px;
        }
        .review-link {
            margin-left: auto;
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .review-link:hover {
            text-decoration: underline;
        }
        .product-price-section {
            margin-bottom: 30px;
        }
        .price-main {
            font-size: 42px;
            font-weight: 700;
            color: var(--primary-color);
            font-family: 'Playfair Display', serif;
        }
        .price-contact {
            font-size: 28px;
            color: #666;
        }
        .product-meta-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-value {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }
        .stock-status {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stock-status.in-stock {
            color: #28a745;
        }
        .key-features {
            margin-bottom: 25px;
            padding: 25px;
            background: linear-gradient(135deg, rgba(0, 31, 63, 0.03) 0%, rgba(212, 175, 55, 0.05) 100%);
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
        }
        .features-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }
        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .features-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }
        .features-list li i {
            color: var(--secondary-color);
        }
        .guarantee-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #003366 100%);
            color: #fff;
            border-radius: 8px;
            margin-bottom: 30px;
            font-weight: 600;
        }
        .guarantee-badge i {
            font-size: 20px;
            color: var(--secondary-color);
        }

        /* Actions Section */
        .product-actions-section {
            margin-bottom: 30px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .action-buttons-three {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .premium-btn {
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
            border: none;
            cursor: pointer;
            text-align: center;
        }
        .btn-action {
            flex-direction: column;
            padding: 20px 15px;
        }
        .btn-action i {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .btn-action span {
            font-size: 14px;
        }
        .btn-get-quote {
            background: var(--primary-color);
            color: #fff;
        }
        .btn-get-quote:hover {
            background: #003366;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 31, 63, 0.3);
        }
        .btn-customization {
            background: var(--secondary-color);
            color: var(--primary-color);
        }
        .btn-customization:hover {
            background: #FFD700;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.3);
        }
        .btn-chat-now {
            background: #25D366;
            color: #fff;
        }
        .btn-chat-now:hover {
            background: #20BA5A;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.3);
        }
        .secondary-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 12px 20px;
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #666;
            font-size: 12px;
        }
        .btn-icon:hover {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            transform: translateY(-2px);
        }
        .btn-icon i {
            font-size: 20px;
        }

        /* Trust Signals */
        .trust-signals {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .trust-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .trust-item i {
            font-size: 24px;
            color: var(--secondary-color);
        }

        /* Shipping Summary */
        .shipping-returns-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-item {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .summary-item i {
            font-size: 28px;
            color: var(--secondary-color);
        }
        .summary-item strong {
            display: block;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        .summary-item p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }

        /* Detail Sections */
        .product-details-sections {
            background: #fff;
            padding: 60px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .detail-section {
            margin-bottom: 60px;
        }
        .detail-section:last-child {
            margin-bottom: 0;
        }
        .premium-section-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Playfair Display', serif;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--secondary-color);
        }
        .premium-section-title i {
            color: var(--secondary-color);
        }
        .review-count-badge {
            margin-left: auto;
            padding: 5px 15px;
            background: var(--secondary-color);
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .premium-content {
            line-height: 1.8;
            color: #555;
        }

        /* Specifications Table */
        .specifications-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .specifications-table tr {
            border-bottom: 1px solid #e9ecef;
        }
        .specifications-table tr:last-child {
            border-bottom: none;
        }
        .spec-label {
            padding: 15px;
            font-weight: 600;
            color: var(--primary-color);
            width: 200px;
            background: #f8f9fa;
        }
        .spec-value {
            padding: 15px;
            color: #333;
        }

        /* Care Instructions */
        .care-list {
            list-style: none;
            padding: 0;
        }
        .care-list li {
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .care-list li:last-child {
            border-bottom: none;
        }
        .care-list strong {
            color: var(--primary-color);
        }

        /* Why Choose Us */
        .why-choose-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 30px;
        }
        .why-item {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        .why-item:hover {
            transform: translateY(-5px);
        }
        .why-item i {
            font-size: 40px;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }
        .why-item h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* FAQ */
        .faq-list {
            margin-top: 20px;
        }
        .faq-item {
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        .faq-question {
            padding: 20px;
            background: #f8f9fa;
            margin: 0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--primary-color);
            transition: all 0.3s;
        }
        .faq-question:hover {
            background: #e9ecef;
        }
        .faq-question i {
            transition: transform 0.3s;
        }
        .faq-question.active i {
            transform: rotate(180deg);
        }
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
        }
        .faq-answer.active {
            padding: 20px;
            max-height: 500px;
        }
        .faq-answer p {
            margin: 0;
            color: #666;
        }

        /* Reviews */
        .rating-overview {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, rgba(0, 31, 63, 0.05) 0%, rgba(212, 175, 55, 0.1) 100%);
            border-radius: 12px;
            margin-bottom: 40px;
        }
        .rating-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .rating-number {
            font-size: 72px;
            font-weight: 700;
            color: var(--primary-color);
            font-family: 'Playfair Display', serif;
        }
        .rating-stars-large {
            display: flex;
            gap: 5px;
        }
        .rating-stars-large .fa-star {
            font-size: 32px;
            color: #ddd;
        }
        .rating-stars-large .fa-star.active {
            color: #FFD700;
        }
        .rating-text {
            color: #666;
            font-size: 16px;
        }
        .reviews-list-premium {
            margin-bottom: 40px;
        }
        .review-card {
            padding: 25px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .review-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .reviewer-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
        }
        .reviewer-name {
            color: var(--primary-color);
            font-size: 16px;
        }
        .review-rating-premium {
            display: flex;
            gap: 2px;
            margin-top: 5px;
        }
        .review-rating-premium .fa-star {
            font-size: 14px;
            color: #ddd;
        }
        .review-rating-premium .fa-star.active {
            color: #FFD700;
        }
        .review-date-premium {
            color: #999;
            font-size: 13px;
        }
        .review-text-premium {
            color: #555;
            line-height: 1.7;
            margin: 0;
        }
        .no-reviews {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .no-reviews i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        /* Review Form */
        .add-review-section {
            margin-top: 40px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .add-review-title {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-family: 'Playfair Display', serif;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary-color);
        }
        .form-input,
        .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--secondary-color);
        }
        .rating-input-premium {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }
        .rating-input-premium input[type="radio"] {
            display: none;
        }
        .star-label-premium {
            cursor: pointer;
            font-size: 32px;
            color: #ddd;
            transition: color 0.2s;
        }
        .rating-input-premium input[type="radio"]:checked ~ .star-label-premium,
        .star-label-premium:hover,
        .star-label-premium:hover ~ .star-label-premium {
            color: #FFD700;
        }
        .btn-submit-review {
            padding: 14px 30px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-submit-review:hover {
            background: #003366;
            transform: translateY(-2px);
        }

        /* Fullscreen Modal */
        .fullscreen-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            cursor: zoom-out;
        }
        .fullscreen-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fullscreen-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        .close-fullscreen {
            position: absolute;
            top: 30px;
            right: 50px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10000;
        }
        .close-fullscreen:hover {
            color: var(--secondary-color);
        }

        /* Craftsmanship Story */
        .craftsmanship-story {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, rgba(0, 31, 63, 0.03) 0%, rgba(212, 175, 55, 0.05) 100%);
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
        }
        .craftsmanship-story h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }

        /* Premium Modals */
        .premium-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
        }
        .premium-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        .modal-content-premium {
            position: relative;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 10001;
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, var(--primary-color) 0%, #003366 100%);
            color: #fff;
            border-radius: 12px 12px 0 0;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Playfair Display', serif;
        }
        .modal-header h2 i {
            color: var(--secondary-color);
        }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 32px;
            cursor: pointer;
            padding: 0;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 30px;
        }
        .premium-form-modal {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .form-group-modal {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group-modal label {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }
        .form-input-modal,
        .form-textarea-modal {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-input-modal:focus,
        .form-textarea-modal:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        .form-textarea-modal {
            resize: vertical;
            min-height: 100px;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 10px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .checkbox-label:hover {
            border-color: var(--secondary-color);
            background: rgba(212, 175, 55, 0.05);
        }
        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--secondary-color);
        }
        .checkbox-label span {
            flex: 1;
            color: #333;
        }
        .file-upload-area {
            margin-top: 10px;
        }
        .file-input {
            display: none;
        }
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .file-upload-label:hover {
            border-color: var(--secondary-color);
            background: rgba(212, 175, 55, 0.05);
        }
        .file-upload-label i {
            font-size: 48px;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }
        .file-upload-label span {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        .file-upload-label small {
            color: #999;
            font-size: 12px;
        }
        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .image-preview-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e9ecef;
        }
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: #fff;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .btn-submit-modal {
            flex: 1;
            padding: 14px 24px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit-modal:hover {
            background: #003366;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 31, 63, 0.3);
        }
        .btn-cancel-modal {
            padding: 14px 24px;
            background: #e9ecef;
            color: #333;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-cancel-modal:hover {
            background: #dee2e6;
        }
        .modal-success,
        .modal-error {
            text-align: center;
            padding: 40px 20px;
        }
        .modal-success i {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .modal-success h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }
        .modal-error {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-error i {
            font-size: 20px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .premium-layout {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .product-gallery-section {
                position: static;
            }
            .action-buttons-three {
                grid-template-columns: 1fr;
            }
            .trust-signals {
                grid-template-columns: repeat(2, 1fr);
            }
            .why-choose-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .product-title {
                font-size: 28px;
            }
            .price-main {
                font-size: 32px;
            }
            .product-info-section,
            .product-details-sections {
                padding: 25px;
            }
            .trust-signals {
                grid-template-columns: 1fr;
            }
            .shipping-returns-summary {
                grid-template-columns: 1fr;
            }
            .modal-content-premium {
                width: 95%;
                max-height: 95vh;
            }
            .modal-header {
                padding: 20px;
            }
            .modal-header h2 {
                font-size: 20px;
            }
            .modal-body {
                padding: 20px;
            }
            .modal-actions {
                flex-direction: column;
            }
            .btn-submit-modal,
            .btn-cancel-modal {
                width: 100%;
            }
        }
    </style>
    
    <script>
        let currentImageIndex = 0;
        const images = <?php echo json_encode($image_urls); ?>;

        function changeImage(src, index) {
            document.getElementById('mainImage').src = src;
            currentImageIndex = index || 0;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-item').forEach((item, i) => {
                if (i === currentImageIndex) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }

        function openFullscreen() {
            const modal = document.getElementById('fullscreenModal');
            const img = document.getElementById('fullscreenImage');
            const mainImg = document.getElementById('mainImage');
            img.src = mainImg.src;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeFullscreen() {
            const modal = document.getElementById('fullscreenModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFullscreen();
                closeQuoteModal();
                closeCustomizeModal();
            }
        });

        // Quote Modal Functions
        function openQuoteModal() {
            const modal = document.getElementById('quoteModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeQuoteModal() {
            const modal = document.getElementById('quoteModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Customization Modal Functions
        function openCustomizeModal() {
            const modal = document.getElementById('customizeModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCustomizeModal() {
            const modal = document.getElementById('customizeModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Image Preview for Customization Form
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('customization_images');
            const previewContainer = document.getElementById('imagePreview');
            
            if (fileInput && previewContainer) {
                fileInput.addEventListener('change', function(e) {
                    previewContainer.innerHTML = '';
                    const files = Array.from(e.target.files);
                    
                    files.forEach((file, index) => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const previewItem = document.createElement('div');
                                previewItem.className = 'image-preview-item';
                                previewItem.innerHTML = `
                                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                                    <button type="button" class="remove-image" onclick="removeImagePreview(${index})" title="Remove image">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;
                                previewContainer.appendChild(previewItem);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                });
            }
        });

        function removeImagePreview(index) {
            const fileInput = document.getElementById('customization_images');
            const previewContainer = document.getElementById('imagePreview');
            
            if (fileInput && previewContainer) {
                const dt = new DataTransfer();
                const files = Array.from(fileInput.files);
                
                files.forEach((file, i) => {
                    if (i !== index) {
                        dt.items.add(file);
                    }
                });
                
                fileInput.files = dt.files;
                
                // Re-trigger preview update
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        }

        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            element.classList.toggle('active');
            answer.classList.toggle('active');
        }


        function addToWishlist(productId) {
            // Placeholder for wishlist functionality
            alert('Wishlist functionality coming soon!');
        }

        function shareProduct() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($product['name']); ?>',
                    text: 'Check out this premium leather product!',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Product link copied to clipboard!');
                });
            }
        }

        // Image zoom on hover
        document.addEventListener('DOMContentLoaded', function() {
            const mainImageWrapper = document.getElementById('mainImageWrapper');
            if (mainImageWrapper) {
                mainImageWrapper.addEventListener('mousemove', function(e) {
                    const img = this.querySelector('img');
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const xPercent = (x / rect.width) * 100;
                    const yPercent = (y / rect.height) * 100;
                    img.style.transformOrigin = xPercent + '% ' + yPercent + '%';
                });
            }

            // Auto-open modals if there's a success/error message
            <?php if ($quote_success || $quote_error): ?>
                openQuoteModal();
            <?php endif; ?>
            <?php if ($customize_success || $customize_error): ?>
                openCustomizeModal();
            <?php endif; ?>
        });
    </script>

<?php require_once 'includes/footer.php'; ?>

