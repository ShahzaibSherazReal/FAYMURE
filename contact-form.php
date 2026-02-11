<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$product_id = $_GET['product_id'] ?? 0;
$conn = getDBConnection();
$product = null;

if ($product_id) {
    $stmt = $conn->prepare("SELECT name FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);

    if ($name && $email && $product_id) {
        // Check if quote_requests table exists, otherwise use orders
        $table_check = $conn->query("SHOW TABLES LIKE 'quote_requests'");
        $table_name = ($table_check->num_rows > 0) ? 'quote_requests' : 'orders';
        
        // Save to database
        $stmt = $conn->prepare("INSERT INTO $table_name (product_id, customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $stmt->bind_param("issssii", $product_id, $name, $email, $phone, $message, $quantity, $user_id);
        
        if ($stmt->execute()) {
            // Send email to admin
            $product_name = $conn->query("SELECT name FROM products WHERE id = $product_id")->fetch_assoc()['name'] ?? 'Product';
            
            $to = ADMIN_EMAIL;
            $subject = "New Product Inquiry - " . $product_name;
            $email_message = "New inquiry received:\n\n";
            $email_message .= "Product: " . $product_name . "\n";
            $email_message .= "Customer Name: " . $name . "\n";
            $email_message .= "Email: " . $email . "\n";
            $email_message .= "Phone: " . $phone . "\n";
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
    <main class="contact-form-page">
        <div class="container">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Thank You!</h2>
                    <p>Your inquiry has been submitted successfully. We'll get back to you soon.</p>
                    <a href="index.php" class="btn-primary">Back to Home</a>
                </div>
            <?php else: ?>
                <h1>Get in Contact / Get Quote</h1>
                <?php if ($product): ?>
                    <p class="form-product-info">Inquiry for: <strong><?php echo htmlspecialchars($product['name']); ?></strong></p>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" class="contact-form">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    
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
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="1">
                    </div>

                    <div class="form-group">
                        <label for="message">Message / Additional Details</label>
                        <textarea id="message" name="message" rows="5"></textarea>
                    </div>

                    <button type="submit" class="btn-submit btn-press">
                        <i class="fas fa-paper-plane"></i> Submit Inquiry
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>

