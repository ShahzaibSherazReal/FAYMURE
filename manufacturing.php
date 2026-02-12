<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$manufacturing_title = 'Manufacturing';
$manufacturing_content = '';

// Get manufacturing content from database
$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='manufacturing_title'");
    if ($result && $row = $result->fetch_assoc()) {
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $manufacturing_title = $row['content_value'];
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='manufacturing_content'");
    if ($result && $row = $result->fetch_assoc()) {
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $manufacturing_content = $row['content_value'];
        }
    }
}

// Get all categories for the dropdown
$categories = [];
$categories_check = $conn->query("SHOW TABLES LIKE 'categories'");
if ($categories_check && $categories_check->num_rows > 0) {
    $categories_result = $conn->query("SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
    if ($categories_result) {
        $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
    }
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $product_type = sanitize($_POST['product_type'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);

    if ($name && $email) {
        // Check if quote_requests table exists, otherwise use orders
        $table_check = $conn->query("SHOW TABLES LIKE 'quote_requests'");
        $table_name = ($table_check->num_rows > 0) ? 'quote_requests' : 'orders';
        
        // Check if product_id column exists, if not, we'll use NULL
        $column_check = $conn->query("SHOW COLUMNS FROM $table_name LIKE 'product_id'");
        $has_product_id = ($column_check && $column_check->num_rows > 0);
        
        if ($has_product_id) {
            // Save to database with product_id = 0 for manufacturing inquiries
            $product_id = 0;
            $stmt = $conn->prepare("INSERT INTO $table_name (product_id, customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $stmt->bind_param("issssii", $product_id, $name, $email, $phone, $message, $quantity, $user_id);
        } else {
            // Table doesn't have product_id column, use alternative approach
            $stmt = $conn->prepare("INSERT INTO $table_name (customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $stmt->bind_param("ssssii", $name, $email, $phone, $message, $quantity, $user_id);
        }
        
        if ($stmt->execute()) {
            // Send email to admin
            $to = ADMIN_EMAIL;
            $subject = "New Manufacturing Inquiry";
            $email_message = "New manufacturing inquiry received:\n\n";
            $email_message .= "Customer Name: " . $name . "\n";
            $email_message .= "Email: " . $email . "\n";
            $email_message .= "Phone: " . $phone . "\n";
            if ($product_type) {
                $email_message .= "Product Type: " . $product_type . "\n";
            }
            $email_message .= "Quantity: " . $quantity . "\n";
            $email_message .= "Message: " . $message . "\n";
            
            $headers = "From: " . $email . "\r\n";
            $headers .= "Reply-To: " . $email . "\r\n";
            
            mail($to, $subject, $email_message, $headers);
            
            $success = true;
        } else {
            $error = "Failed to submit form. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}

$conn->close();
?>
    <main class="page-content manufacturing-page">
        <div class="container">
            <!-- Explore Button at Top -->
            <div class="manufacturing-header">
                <a href="categories.php" class="btn-explore btn-press reveal" data-delay="0">
                    <?php echo t('explore'); ?> <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <h1 class="page-title"><?php echo htmlspecialchars($manufacturing_title); ?></h1>
            
            <!-- Content Paragraph -->
            <div class="content-section">
                <?php if (!empty($manufacturing_content)): ?>
                    <div class="intro-text">
                        <?php echo $manufacturing_content; ?>
                    </div>
                <?php else: ?>
                    <div class="intro-text">
                        <p>At FAYMURE, we specialize in premium leather goods manufacturing with a commitment to excellence, quality, and craftsmanship. Our state-of-the-art manufacturing facilities combine traditional techniques with modern technology to produce leather products that meet the highest standards of durability, style, and sophistication.</p>
                        <p>Whether you're looking to manufacture belts, shoes, bags, wallets, gloves, jackets, rugs, furniture leather, or keychains, we have the expertise and capacity to bring your vision to life. We work with businesses of all sizes, from startups to established brands, offering flexible manufacturing solutions tailored to your specific needs.</p>
                        <p>Our manufacturing services include design consultation, material selection, prototyping, quality control, and timely delivery. We use only the finest quality leather and materials, ensuring that every product we manufacture reflects our commitment to excellence.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form Section -->
            <div class="form-section">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h2>Thank You!</h2>
                        <p>Your manufacturing inquiry has been submitted successfully. We'll get back to you soon.</p>
                        <a href="index.php" class="btn-primary">Back to Home</a>
                    </div>
                <?php else: ?>
                    <h2 class="section-heading">Submit Manufacturing Inquiry</h2>
                    
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" class="contact-form manufacturing-form">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>

                        <div class="form-group">
                            <label for="product_type">Product Type</label>
                            <select id="product_type" name="product_type">
                                <option value="">Select a category...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other">Other (Please specify in message)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quantity">Estimated Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="1" value="1">
                        </div>

                        <div class="form-group">
                            <label for="message">Project Details / Additional Information *</label>
                            <textarea id="message" name="message" rows="6" required placeholder="Please provide details about your manufacturing requirements, specifications, timeline, and any other relevant information."></textarea>
                        </div>

                        <button type="submit" class="btn-submit btn-press">
                            <i class="fas fa-paper-plane"></i> Submit Inquiry
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <style>
        .manufacturing-page {
            padding: 100px 0;
        }

        .manufacturing-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .manufacturing-header .btn-explore {
            display: inline-block;
            padding: 16px 40px;
            background: transparent;
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 0;
            font-weight: 300;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border: 1px solid var(--primary-color);
            font-family: 'Inter', sans-serif;
        }

        .manufacturing-header .btn-explore:hover {
            background: var(--primary-color);
            color: #fff;
            transform: translateY(-2px);
        }

        .content-section {
            margin-bottom: 60px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        .intro-text {
            font-size: 18px;
            line-height: 1.8;
            color: var(--text-color);
            font-weight: 300;
        }

        .intro-text p {
            margin-bottom: 20px;
        }

        .intro-text p:last-child {
            margin-bottom: 0;
        }

        .form-section {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
        }

        .section-heading {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .manufacturing-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 400;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-color);
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23001F3F' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 31, 63, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .btn-submit:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .success-message {
            text-align: center;
            padding: 60px 20px;
        }

        .success-message i {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }

        .success-message h2 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 16px;
        }

        .success-message p {
            font-size: 16px;
            color: var(--text-color);
            margin-bottom: 30px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .manufacturing-page {
                padding: 50px 0;
            }

            .manufacturing-header {
                margin-bottom: 40px;
            }

            .content-section {
                margin-bottom: 40px;
            }

            .form-section {
                padding: 30px 20px;
            }

            .section-heading {
                font-size: 24px;
                margin-bottom: 20px;
            }

            .intro-text {
                font-size: 16px;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>

