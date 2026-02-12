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
            $table_check = $conn->query("SHOW TABLES LIKE 'quote_requests'");
            $table_name = ($table_check && $table_check->num_rows > 0) ? 'quote_requests' : 'orders';
            
            $product_result = $conn->query("SELECT name FROM products WHERE id = $product_id");
            $product_name = 'N/A';
            if ($product_result) {
                $product = $product_result->fetch_assoc();
                if ($product && is_array($product)) {
                    $product_name = $product['name'] ?? 'N/A';
                }
            }
            
            $full_message = "Quote Request for Product\n\n";
            $full_message .= "Product: " . $product_name . "\n";
            $full_message .= "Quantity: " . $quantity . "\n";
            $full_message .= "Additional Details: " . $message;
            
            $column_check = $conn->query("SHOW COLUMNS FROM $table_name LIKE 'product_id'");
            $has_product_id = ($column_check && $column_check->num_rows > 0);
            
            if ($has_product_id) {
                $stmt = $conn->prepare("INSERT INTO $table_name (product_id, customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
                $stmt->bind_param("issssii", $product_id, $name, $email, $phone, $full_message, $quantity, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO $table_name (customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
                $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
                $stmt->bind_param("ssssii", $name, $email, $phone, $full_message, $quantity, $user_id);
            }
            
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
                
                mail($to, $subject, $email_message, $headers);
                
                $success = true;
            } else {
                $error = "Failed to submit request. Please try again.";
            }
            $stmt->close();
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($form_type == 'customize') {
        // Customization form
        $customizations = sanitize($_POST['customizations'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if ($name && $email && $description) {
            $table_check = $conn->query("SHOW TABLES LIKE 'custom_designs'");
            $table_name = ($table_check && $table_check->num_rows > 0) ? 'custom_designs' : 'orders';
            
            $product_result = $conn->query("SELECT name FROM products WHERE id = $product_id");
            $product_name = 'N/A';
            if ($product_result) {
                $product = $product_result->fetch_assoc();
                if ($product && is_array($product)) {
                    $product_name = $product['name'] ?? 'N/A';
                }
            }
            
            $full_message = "Product Customization Request\n\n";
            $full_message .= "Base Product: " . $product_name . "\n";
            $full_message .= "Quantity: " . $quantity . "\n";
            $full_message .= "Customizations: " . $customizations . "\n";
            $full_message .= "Detailed Description: " . $description;
            
            if ($table_name == 'custom_designs') {
                $stmt = $conn->prepare("INSERT INTO custom_designs (customer_name, customer_email, customer_phone, product_type, description, quantity, user_id) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
                $product_type = "Customization of: " . $product_name;
                $stmt->bind_param("sssssii", $name, $email, $phone, $product_type, $full_message, $quantity, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO orders (product_id, customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
                $stmt->bind_param("issssii", $product_id, $name, $email, $phone, $full_message, $quantity, $user_id);
            }
            
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
                
                mail($to, $subject, $email_message, $headers);
                
                $success = true;
            } else {
                $error = "Failed to submit request. Please try again.";
            }
            $stmt->close();
        } else {
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
        $products_result = $conn->query("SELECT id, name, slug, description, product_details, moq, price, image FROM products WHERE category_id = $selected_category_id AND deleted_at IS NULL AND status = 'active' ORDER BY created_at DESC");
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
            <div class="page-header">
                <a href="explore.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Explore
                </a>
                <h1 class="page-title"><?php echo htmlspecialchars($browse_title); ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($browse_subtitle); ?></p>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Thank You!</h2>
                    <p>Your request has been submitted successfully. We'll get back to you soon.</p>
                    <a href="explore-browse.php" class="btn-primary">Browse More</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($selected_category_id > 0 && $selected_category): ?>
                    <!-- Products View -->
                    <div class="products-view">
                        <div class="view-header">
                            <button onclick="window.location.href='explore-browse.php'" class="btn-back-categories">
                                <i class="fas fa-arrow-left"></i> Back to Categories
                            </button>
                            <h2><?php echo htmlspecialchars($selected_category['name']); ?></h2>
                            <?php if ($selected_category['description']): ?>
                                <p><?php echo htmlspecialchars($selected_category['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($products)): ?>
                            <div class="products-grid">
                                <?php foreach ($products as $product): ?>
                                    <div class="product-card" onclick="showProductDetail(<?php echo htmlspecialchars(json_encode($product)); ?>)" style="cursor: pointer;">
                                        <?php if (!empty($product['image'])): ?>
                                            <div class="product-image">
                                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="product-image placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-info">
                                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                            <?php if ($product['price']): ?>
                                                <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                                            <?php endif; ?>
                                            <button class="btn-view-details" onclick="event.stopPropagation()">
                                                View Details <i class="fas fa-arrow-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-products">
                                <p>No products available in this category yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Categories View -->
                    <div class="categories-view">
                        <div class="categories-grid">
                            <?php foreach ($categories as $category): ?>
                                <div class="category-card" onclick="window.location.href='explore-browse.php?category=<?php echo $category['id']; ?>'">
                                    <?php if (!empty($category['image'])): ?>
                                        <div class="category-image">
                                            <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="category-image placeholder">
                                            <i class="fas fa-folder"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="category-info">
                                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <?php if ($category['description']): ?>
                                            <p><?php echo htmlspecialchars($category['description']); ?></p>
                                        <?php endif; ?>
                                        <span class="view-products">View Products <i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
                        <div class="spec-item" id="detailProductPriceContainer" style="display: none;">
                            <span class="spec-label">Price:</span>
                            <span class="spec-value" id="detailProductPrice"></span>
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
            padding: 100px 0;
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
            font-family: 'Playfair Display', serif;
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
            font-family: 'Playfair Display', serif;
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
            margin-top: 40px;
        }
        
        .view-header {
            margin-bottom: 40px;
            text-align: center;
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
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .view-header p {
            color: var(--text-color);
            font-size: 16px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .product-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 20px var(--shadow);
        }
        
        .product-image {
            width: 100%;
            height: 250px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image.placeholder {
            color: var(--text-secondary);
            font-size: 48px;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-info h3 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .product-price {
            font-size: 18px;
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .btn-view-details {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
        }
        
        .btn-view-details:hover {
            background: var(--dark-color);
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
            font-family: 'Playfair Display', serif;
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
            font-family: 'Playfair Display', serif;
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
            font-family: 'Inter', sans-serif;
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
            font-family: 'Inter', sans-serif;
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
            font-family: 'Inter', sans-serif;
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
            font-family: 'Playfair Display', serif;
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
            
            .categories-grid,
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-detail-content {
                grid-template-columns: 1fr;
            }
            
            .product-detail-actions {
                flex-direction: column;
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
            
            if (product.price) {
                document.getElementById('detailProductPrice').textContent = '$' + parseFloat(product.price).toFixed(2);
                document.getElementById('detailProductPriceContainer').style.display = 'flex';
            } else {
                document.getElementById('detailProductPriceContainer').style.display = 'none';
            }
            
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
