<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();

// Handle form submissions
$success = false;
$error = '';
$form_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_type = $_POST['form_type'] ?? '';
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);

    if ($form_type == 'quote') {
        // Quote request form
        $message = sanitize($_POST['message'] ?? '');

        if ($name && $email) {
            // Check if quote_requests table exists, create it if it doesn't
            $table_check = $conn->query("SHOW TABLES LIKE 'quote_requests'");
            if (!$table_check || $table_check->num_rows == 0) {
                // Create quote_requests table
                $create_table_sql = "CREATE TABLE IF NOT EXISTS quote_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    customer_name VARCHAR(100) NOT NULL,
                    customer_email VARCHAR(100) NOT NULL,
                    customer_phone VARCHAR(20),
                    message TEXT,
                    quantity INT NOT NULL DEFAULT 1,
                    user_id INT NULL,
                    status ENUM('pending', 'quoted', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                $conn->query($create_table_sql);
            }

            $product_result = $conn->query("SELECT name FROM products WHERE id = $product_id");
            $product_name = 'N/A';
            if ($product_result) {
                $product = $product_result->fetch_assoc();
                if ($product && is_array($product)) {
                    $product_name = $product['name'] ?? 'N/A';
                }
            }

            // Save to database - always use quote_requests table now
            $stmt = $conn->prepare("INSERT INTO quote_requests (product_id, customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $stmt->bind_param("issssii", $product_id, $name, $email, $phone, $message, $quantity, $user_id);

            if ($stmt->execute()) {
                $to = ADMIN_EMAIL;
                $subject = "New Quote Request";
                $email_message = "New quote request received:\n\n";
                $email_message .= "Customer Name: " . $name . "\n";
                $email_message .= "Email: " . $email . "\n";
                $email_message .= "Phone: " . $phone . "\n";
                $email_message .= "Product: " . $product_name . "\n";
                $email_message .= "Quantity: " . $quantity . "\n";
                $email_message .= "Message: " . $message . "\n";

                $headers = "From: " . $email . "\r\n";
                $headers .= "Reply-To: " . $email . "\r\n";

                // Attempt to send email, but don't fail if mail server is not configured
                @mail($to, $subject, $email_message, $headers);

                $success = true;
            }
            else {
                $error = "Failed to submit request. Please try again.";
            }
            $stmt->close();
        }
        else {
            $error = "Please fill in all required fields.";
        }
    }
    elseif ($form_type == 'customize') {
        // Customization form
        $customizations = sanitize($_POST['customizations'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($name && $email && $description) {
            // Check if product_customizations table exists, create it if it doesn't
            $table_check = $conn->query("SHOW TABLES LIKE 'product_customizations'");
            if (!$table_check || $table_check->num_rows == 0) {
                // Create product_customizations table
                $create_table_sql = "CREATE TABLE IF NOT EXISTS product_customizations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    customer_name VARCHAR(100) NOT NULL,
                    customer_email VARCHAR(100) NOT NULL,
                    customer_phone VARCHAR(20),
                    customizations TEXT,
                    description TEXT NOT NULL,
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

            $product_result = $conn->query("SELECT name FROM products WHERE id = $product_id");
            $product_name = 'N/A';
            if ($product_result) {
                $product = $product_result->fetch_assoc();
                if ($product && is_array($product)) {
                    $product_name = $product['name'] ?? 'N/A';
                }
            }

            // Save to database - always use product_customizations table now
            $stmt = $conn->prepare("INSERT INTO product_customizations (product_id, customer_name, customer_email, customer_phone, customizations, description, quantity, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $stmt->bind_param("isssssii", $product_id, $name, $email, $phone, $customizations, $description, $quantity, $user_id);

            if ($stmt->execute()) {
                $to = ADMIN_EMAIL;
                $subject = "New Product Customization Request";
                $email_message = "New customization request received:\n\n";
                $email_message .= "Customer Name: " . $name . "\n";
                $email_message .= "Email: " . $email . "\n";
                $email_message .= "Phone: " . $phone . "\n";
                $email_message .= "Base Product: " . $product_name . "\n";
                $email_message .= "Quantity: " . $quantity . "\n";
                $email_message .= "Customizations: " . $customizations . "\n";
                $email_message .= "Description: " . $description . "\n";

                $headers = "From: " . $email . "\r\n";
                $headers .= "Reply-To: " . $email . "\r\n";

                // Attempt to send email, but don't fail if mail server is not configured
                @mail($to, $subject, $email_message, $headers);

                $success = true;
            }
            else {
                $error = "Failed to submit request. Please try again.";
            }
            $stmt->close();
        }
        else {
            $error = "Please fill in all required fields.";
        }
    }
}

// Get selected category from URL
$selected_category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Get categories
$categories = [];
$categories_result = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
if ($categories_result) {
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
}

// Get products for selected category
$products = [];
$selected_category = null;
if ($selected_category_id > 0) {
    $category_result = $conn->query("SELECT * FROM categories WHERE id = $selected_category_id AND deleted_at IS NULL");
    if ($category_result) {
        $selected_category = $category_result->fetch_assoc();
    }

    if ($selected_category) {
        $products_result = $conn->query("SELECT id, name, slug, description, product_details, moq, image FROM products WHERE category_id = $selected_category_id AND deleted_at IS NULL AND status = 'active' ORDER BY created_at DESC");
        if ($products_result) {
            $products = $products_result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Get browse page content before closing connection
$browse_title = 'Browse & Customize';
$browse_subtitle = 'Browse our product categories and request quotes for bulk orders. You can also request customizations to our existing products.';

$result = $conn->query("SELECT content_value FROM site_content WHERE content_key='browse_title'");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row && is_array($row) && !empty($row['content_value'])) {
        $browse_title = $row['content_value'];
    }
}

$result = $conn->query("SELECT content_value FROM site_content WHERE content_key='browse_subtitle'");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row && is_array($row) && !empty($row['content_value'])) {
        $browse_subtitle = $row['content_value'];
    }
}

$conn->close();
?>
    <main class="browse-customize-page">
        <div class="container">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Thank You!</h2>
                    <p>Your request has been submitted successfully. We'll get back to you soon.</p>
                    <a href="<?php echo(defined('BASE_PATH') ? BASE_PATH : ''); ?>/explore-browse" class="btn-primary">Browse More</a>
                </div>
            <?php
else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php
    endif; ?>
                
                <?php if ($selected_category_id > 0 && $selected_category): ?>
                    <!-- Products View -->
                    <div class="products-view">
                        <div class="view-header">
                            <h2><?php echo htmlspecialchars($selected_category['name']); ?></h2>
                            <?php if ($selected_category['description']): ?>
                                <p><?php echo htmlspecialchars($selected_category['description']); ?></p>
                            <?php
        endif; ?>
                        </div>
                        
                        <?php if (!empty($products)): ?>
                            <div class="products-grid-modern stagger">
                                <?php foreach ($products as $product): ?>
                                    <div class="product-card-modern reveal">
                                        <div class="product-card-link" onclick="showProductDetail(<?php echo htmlspecialchars(json_encode($product)); ?>)" style="cursor: pointer;">
                                            <div class="product-image-wrapper">
                                                <?php if ($product['image']): ?>
                                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-main">
                                                <?php
                else: ?>
                                                    <div class="product-image-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php
                endif; ?>
                                                <div class="quick-view-icon" onclick="event.stopPropagation(); showProductDetail(<?php echo htmlspecialchars(json_encode($product)); ?>);">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="product-card-body">
                                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                                
                                                <div class="product-price-section">
                                                    <span class="product-price">Get Quote</span>
                                                </div>
                                                
                                                <div class="product-moq">
                                                    <span class="moq-label">Min. order:</span>
                                                    <span class="moq-value"><?php echo number_format($product['moq'] ?? 1); ?> <?php echo($product['moq'] ?? 1) > 1 ? 'pieces' : 'piece'; ?></span>
                                                </div>
                                                
                                                <div class="product-seller-info">
                                                    <div class="seller-name-section">
                                                        <span class="seller-name"><?php echo SITE_NAME; ?></span>
                                                        <span class="verified-badge" title="Verified Supplier">
                                                            <i class="fas fa-check-circle"></i>
                                                        </span>
                                                    </div>
                                                    <div class="seller-meta">
                                                        <span class="seller-country">
                                                            <i class="fas fa-flag"></i> PK
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="product-rating">
                                                    <div class="stars">
                                                        <?php
                $rating = 4.5; // Default rating, can be fetched from reviews table if available
                $fullStars = floor($rating);
                $hasHalfStar = ($rating - $fullStars) >= 0.5;
                for ($i = 0; $i < $fullStars; $i++) {
                    echo '<i class="fas fa-star"></i>';
                }
                if ($hasHalfStar) {
                    echo '<i class="fas fa-star-half-alt"></i>';
                }
                for ($i = $fullStars + ($hasHalfStar ? 1 : 0); $i < 5; $i++) {
                    echo '<i class="far fa-star"></i>';
                }
?>
                                                    </div>
                                                    <span class="rating-value"><?php echo number_format($rating, 1); ?>/5.0</span>
                                                    <span class="rating-count">(<?php echo rand(5, 50); ?>)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php
            endforeach; ?>
                            </div>
                        <?php
        else: ?>
                            <div class="no-products">
                                <p>No products available in this category yet.</p>
                            </div>
                        <?php
        endif; ?>
                    </div>
                <?php
    else: ?>
                    <!-- Categories View -->
                    <div class="categories-view">
                        <div class="categories-grid stagger">
                            <?php foreach ($categories as $category): ?>
                                <a href="<?php echo(defined('BASE_PATH') ? BASE_PATH : ''); ?>/explore-browse?category=<?php echo $category['id']; ?>" class="category-card hover-lift reveal">
                                    <div class="category-image">
                                        <?php if ($category['image']): ?>
                                            <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                        <?php
            else: ?>
                                            <div class="placeholder-image">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php
            endif; ?>
                                    </div>
                                    <div class="category-info">
                                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <?php if ($category['description']): ?>
                                            <p><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?>...</p>
                                        <?php
            endif; ?>
                                    </div>
                                </a>
                            <?php
        endforeach; ?>
                        </div>
                    </div>
                <?php
    endif; ?>
            <?php
endif; ?>
        </div>
    </main>
    
    <!-- Product Detail Modal -->
    <div id="productDetailModal" class="modal">
        <div class="modal-content product-detail-modal">
            <span class="close" onclick="closeModal('productDetailModal')">&times;</span>
            <div class="product-detail-content">
                <div class="product-detail-image">
                    <img id="detailProductImage" src="" alt="">
                </div>
                <div class="product-detail-info">
                    <h2 id="detailProductName"></h2>
                    <div class="product-detail-specs">
                        <div class="spec-item">
                            <span class="spec-label">MOQ:</span>
                            <span class="spec-value" id="detailProductMOQ"></span>
                        </div>
                        <div class="spec-item" id="detailProductPriceContainer" style="display: flex;">
                            <span class="spec-label">Pricing:</span>
                            <span class="spec-value" id="detailProductPrice">Get Quote</span>
                        </div>
                    </div>
                    <div class="product-detail-section">
                        <h3>Description</h3>
                        <p id="detailProductDescription"></p>
                    </div>
                    <div class="product-detail-section">
                        <h3>Product Details</h3>
                        <p id="detailProductDetails"></p>
                    </div>
                    <div class="product-detail-actions">
                        <button class="btn-primary btn-quote-detail" onclick="openQuoteFromDetail()">
                            <i class="fas fa-calculator"></i> Get Quote
                        </button>
                        <button class="btn-primary btn-customize-detail" onclick="openCustomizeFromDetail()">
                            <i class="fas fa-palette"></i> Customize
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quote Request Modal -->
    <div id="quoteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('quoteModal')">&times;</span>
            <h2>Request Quote</h2>
            <form method="POST" id="quoteForm" class="quote-form">
                <input type="hidden" name="form_type" value="quote">
                <input type="hidden" name="product_id" id="quoteProductId">
                
                <div class="form-group">
                    <label>Product</label>
                    <input type="text" id="quoteProductName" readonly>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quote_name">Full Name *</label>
                        <input type="text" id="quote_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quote_email">Email Address *</label>
                        <input type="email" id="quote_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quote_phone">Phone Number</label>
                        <input type="tel" id="quote_phone" name="phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quote_quantity">Quantity *</label>
                        <input type="number" id="quote_quantity" name="quantity" min="1" value="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="quote_message">Additional Details / Requirements</label>
                    <textarea id="quote_message" name="message" rows="4" placeholder="Any additional information about your quote request..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Quote Request
                    </button>
                    <button type="button" onclick="closeModal('quoteModal')" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Customize Product Modal -->
    <div id="customizeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('customizeModal')">&times;</span>
            <h2>Customize Product</h2>
            <form method="POST" id="customizeForm" class="customize-form">
                <input type="hidden" name="form_type" value="customize">
                <input type="hidden" name="product_id" id="customizeProductId">
                
                <div class="form-group">
                    <label>Base Product</label>
                    <input type="text" id="customizeProductName" readonly>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customize_name">Full Name *</label>
                        <input type="text" id="customize_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customize_email">Email Address *</label>
                        <input type="email" id="customize_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customize_phone">Phone Number</label>
                        <input type="tel" id="customize_phone" name="phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customize_quantity">Quantity *</label>
                        <input type="number" id="customize_quantity" name="quantity" min="1" value="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="customize_customizations">Requested Customizations *</label>
                    <textarea id="customize_customizations" name="customizations" rows="4" required placeholder="Describe the changes or customizations you'd like to make to this product..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="customize_description">Detailed Description *</label>
                    <textarea id="customize_description" name="description" rows="6" required placeholder="Provide a detailed description of your customization requirements. Include specifications, materials, colors, dimensions, and any other details..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Customization Request
                    </button>
                    <button type="button" onclick="closeModal('customizeModal')" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        .browse-customize-page {
            padding: 20px 0 100px 0;
            background: var(--background-color);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 30px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--accent-color);
        }
        
        .page-title {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .page-subtitle {
            font-size: 18px;
            color: var(--text-color);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.8;
            font-weight: 300;
        }
        
        /* Categories View */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .category-card {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .category-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 20px var(--shadow);
            transform: translateY(-4px);
        }
        
        .category-image {
            width: 100%;
            height: 250px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .category-image.placeholder {
            color: var(--text-secondary);
            font-size: 48px;
        }
        
        .category-info {
            padding: 25px;
        }
        
        .category-info h3 {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .category-info p {
            color: var(--text-color);
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .view-products {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Products View */
        .products-view {
            margin-top: 0;
        }
        
        .view-header {
            margin-bottom: 20px;
            text-align: left;
            padding-top: 20px;
        }
        
        .btn-back-categories {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .btn-back-categories:hover {
            background: var(--primary-color);
            color: #fff;
        }
        
        .view-header h2 {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        
        .view-header p {
            color: var(--text-color);
            font-size: 16px;
            margin-bottom: 0;
        }
        
        .products-grid-modern {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 0;
        }
        
        .product-card-modern {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e8e8e8;
        }
        
        .product-card-modern:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .product-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-image-wrapper {
            position: relative;
            width: 100%;
            height: 280px;
            background: #f8f9fa;
            overflow: hidden;
        }
        
        .product-image-main {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card-modern:hover .product-image-main {
            transform: scale(1.05);
        }
        
        .product-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #999;
            font-size: 48px;
        }
        
        .quick-view-icon {
            position: absolute;
            bottom: 12px;
            left: 12px;
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 2;
        }
        
        .product-card-modern:hover .quick-view-icon {
            opacity: 1;
        }
        
        .quick-view-icon:hover {
            background: var(--primary-color);
            color: #fff;
        }
        
        .quick-view-icon i {
            font-size: 16px;
            color: var(--primary-color);
        }
        
        .quick-view-icon:hover i {
            color: #fff;
        }
        
        .product-card-body {
            padding: 6px 10px;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            line-height: 1.2;
            margin: 0 0 4px 0;
            height: 36px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-price-section {
            margin-bottom: 2px;
        }
        
        .product-price {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .product-moq {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            font-size: 18px;
            color: #666;
        }
        
        .moq-label {
            font-weight: 400;
        }
        
        .moq-value {
            font-weight: 500;
            color: #333;
        }
        
        .product-seller-info {
            padding-top: 4px;
            border-top: 1px solid #f0f0f0;
            margin-bottom: 4px;
        }
        
        .seller-name-section {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }
        
        .seller-name {
            font-size: 13px;
            font-weight: 500;
            color: #333;
        }
        
        .verified-badge {
            color: #1890ff;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
        }
        
        .seller-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #999;
        }
        
        .seller-experience {
            font-weight: 400;
        }
        
        .seller-country {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .seller-country i {
            font-size: 11px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }
        
        .stars {
            display: flex;
            gap: 2px;
            color: #ffc107;
        }
        
        .stars i {
            font-size: 12px;
        }
        
        .rating-value {
            font-weight: 500;
            color: #333;
        }
        
        .rating-count {
            color: #999;
        }
        
        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: var(--background-color);
            margin: 5% auto;
            padding: 40px;
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 700px;
            position: relative;
        }
        
        .product-detail-modal {
            max-width: 900px;
        }
        
        .product-detail-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .product-detail-image {
            width: 100%;
        }
        
        .product-detail-image img {
            width: 100%;
            height: auto;
            border: 1px solid var(--border-color);
        }
        
        .product-detail-info h2 {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .product-detail-specs {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid var(--border-color);
        }
        
        .spec-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .spec-label {
            font-weight: 600;
            color: var(--text-color);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .spec-value {
            color: var(--primary-color);
            font-weight: 500;
            font-size: 16px;
        }
        
        .product-detail-section {
            margin-bottom: 25px;
        }
        
        .product-detail-section h3 {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .product-detail-section p {
            color: var(--text-color);
            line-height: 1.8;
            font-size: 15px;
        }
        
        .product-detail-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-quote-detail,
        .btn-customize-detail {
            flex: 1;
            padding: 16px;
            font-size: 14px;
        }
        
        .btn-customize-detail {
            background: var(--accent-color);
        }
        
        .btn-customize-detail:hover {
            background: #e55a0f;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: var(--text-color);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: var(--primary-color);
        }
        
        .quote-form,
        .customize-form {
            margin-top: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-color);
            font-size: 15px;
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 31, 63, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-primary {
            padding: 14px 30px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: var(--dark-color);
        }
        
        .btn-secondary {
            padding: 14px 30px;
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
        }
        
        .btn-secondary:hover {
            background: var(--primary-color);
            color: #fff;
        }
        
        .success-message,
        .error-message {
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 4px;
            text-align: center;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .success-message i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .success-message h2 {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .browse-customize-page {
                padding: 50px 0;
            }
            
            .page-title {
                font-size: 32px;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .products-grid-modern {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 20px;
            }
            
            .product-image-wrapper {
                height: 240px;
            }
            
            .product-card-body {
                padding: 14px;
            }
            
            .product-title {
                font-size: 13px;
                height: 38px;
            }
            
            .product-price {
                font-size: 16px;
            }
            
            .product-detail-content {
                grid-template-columns: 1fr;
            }
            
            .product-detail-actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid-modern {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .product-image-wrapper {
                height: 200px;
            }
        }
    </style>
    
    <script>
        let currentProduct = null;
        
        function showProductDetail(product) {
            currentProduct = product;
            
            // Set product details
            document.getElementById('detailProductName').textContent = product.name || 'N/A';
            document.getElementById('detailProductMOQ').textContent = product.moq || '1';
            
            document.getElementById('detailProductPrice').textContent = 'Get Quote';
            document.getElementById('detailProductPriceContainer').style.display = 'flex';
            
            document.getElementById('detailProductDescription').textContent = product.description || 'No description available.';
            document.getElementById('detailProductDetails').textContent = product.product_details || 'No details available.';
            
            // Set product image
            const imgElement = document.getElementById('detailProductImage');
            if (product.image) {
                imgElement.src = product.image;
                imgElement.alt = product.name;
                imgElement.style.display = 'block';
            } else {
                imgElement.style.display = 'none';
            }
            
            // Show modal
            document.getElementById('productDetailModal').style.display = 'block';
        }
        
        function openQuoteFromDetail() {
            if (currentProduct) {
                closeModal('productDetailModal');
                setTimeout(() => {
                    showQuoteModal(currentProduct.id, currentProduct.name);
                }, 300);
            }
        }
        
        function openCustomizeFromDetail() {
            if (currentProduct) {
                closeModal('productDetailModal');
                setTimeout(() => {
                    showCustomizeModal(currentProduct.id, currentProduct.name);
                }, 300);
            }
        }
        
        function showQuoteModal(productId, productName) {
            document.getElementById('quoteProductId').value = productId;
            document.getElementById('quoteProductName').value = productName;
            document.getElementById('quoteModal').style.display = 'block';
        }
        
        function showCustomizeModal(productId, productName) {
            document.getElementById('customizeProductId').value = productId;
            document.getElementById('customizeProductName').value = productName;
            document.getElementById('customizeModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'quoteModal') {
                document.getElementById('quoteForm').reset();
            } else if (modalId === 'customizeModal') {
                document.getElementById('customizeForm').reset();
            }
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

<?php require_once 'includes/footer.php'; ?>
