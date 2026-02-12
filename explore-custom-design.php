<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $product_type = sanitize($_POST['product_type'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $timeline = sanitize($_POST['timeline'] ?? '');
    $budget = sanitize($_POST['budget'] ?? '');
    
    // Handle multiple image uploads
    $upload_dir = 'assets/images/custom-designs/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $uploaded_images = [];
    if (isset($_FILES['design_images']) && !empty($_FILES['design_images']['name'][0])) {
        foreach ($_FILES['design_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['design_images']['error'][$key] == 0) {
                $file_ext = pathinfo($_FILES['design_images']['name'][$key], PATHINFO_EXTENSION);
                $file_name = uniqid() . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $uploaded_images[] = $upload_dir . $file_name;
                }
            }
        }
    }
    
    if ($name && $email && $description) {
        // Check if custom_designs table exists, otherwise use orders
        $table_check = $conn->query("SHOW TABLES LIKE 'custom_designs'");
        $table_name = ($table_check && $table_check->num_rows > 0) ? 'custom_designs' : 'orders';
        
        $images_json = json_encode($uploaded_images);
        $message = "Custom Design Request\n\n";
        $message .= "Product Type: " . $product_type . "\n";
        $message .= "Description: " . $description . "\n";
        $message .= "Quantity: " . $quantity . "\n";
        $message .= "Timeline: " . $timeline . "\n";
        $message .= "Budget: " . $budget . "\n";
        $message .= "Images: " . count($uploaded_images) . " uploaded";
        
        // Save to database
        if ($table_name == 'custom_designs') {
            $stmt = $conn->prepare("INSERT INTO custom_designs (customer_name, customer_email, customer_phone, product_type, description, quantity, timeline, budget, images, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $stmt->bind_param("sssssisssi", $name, $email, $phone, $product_type, $description, $quantity, $timeline, $budget, $images_json, $user_id);
        } else {
            // Use orders table with product_id = 0 for custom designs
            $stmt = $conn->prepare("INSERT INTO orders (product_id, customer_name, customer_email, customer_phone, message, quantity, user_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $product_id = 0;
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $stmt->bind_param("issssii", $product_id, $name, $email, $phone, $message, $quantity, $user_id);
        }
        
        if ($stmt->execute()) {
            // Send email to admin
            $to = ADMIN_EMAIL;
            $subject = "New Custom Design Request";
            $email_message = "New custom design request received:\n\n";
            $email_message .= "Customer Name: " . $name . "\n";
            $email_message .= "Email: " . $email . "\n";
            $email_message .= "Phone: " . $phone . "\n";
            $email_message .= "Product Type: " . $product_type . "\n";
            $email_message .= "Description: " . $description . "\n";
            $email_message .= "Quantity: " . $quantity . "\n";
            $email_message .= "Timeline: " . $timeline . "\n";
            $email_message .= "Budget: " . $budget . "\n";
            $email_message .= "Images: " . count($uploaded_images) . " uploaded\n";
            
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

// Get categories for product type dropdown
$categories = [];
$categories_result = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
if ($categories_result) {
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
}

// Get custom design page content before closing connection
$custom_design_title = 'Design Your Own Product';
$custom_design_subtitle = 'Create a unique product from scratch. Share your vision, upload inspiration images, and let us bring your design to life.';

$result = $conn->query("SELECT content_value FROM site_content WHERE content_key='custom_design_title'");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row && is_array($row) && !empty($row['content_value'])) {
        $custom_design_title = $row['content_value'];
    }
}

$result = $conn->query("SELECT content_value FROM site_content WHERE content_key='custom_design_subtitle'");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row && is_array($row) && !empty($row['content_value'])) {
        $custom_design_subtitle = $row['content_value'];
    }
}

$conn->close();
?>
    <main class="custom-design-page">
        <div class="container">
            <div class="page-header">
                <a href="explore.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Explore
                </a>
                <h1 class="page-title"><?php echo htmlspecialchars($custom_design_title); ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($custom_design_subtitle); ?></p>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Thank You!</h2>
                    <p>Your design request has been submitted successfully. We'll get back to you soon.</p>
                    <a href="explore-custom-design.php" class="btn-primary">Submit Another Request</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Custom Design Form -->
                <section class="form-section">
                    <h2 class="section-title">Submit Your Design Request</h2>
                    <form method="POST" enctype="multipart/form-data" class="custom-design-form">
                        <div class="form-row">
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
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="product_type">Product Type *</label>
                                <select id="product_type" name="product_type" required>
                                    <option value="">Select a category...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Estimated Quantity *</label>
                                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="timeline">Timeline</label>
                                <input type="text" id="timeline" name="timeline" placeholder="e.g., 4-6 weeks">
                            </div>
                            
                            <div class="form-group">
                                <label for="budget">Budget Range</label>
                                <input type="text" id="budget" name="budget" placeholder="e.g., $500-$1000">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Product Description & Requirements *</label>
                            <textarea id="description" name="description" rows="6" required placeholder="Describe your product idea in detail. Include specifications, materials, colors, dimensions, and any other requirements..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="design_images">Upload Inspiration Images / Sketches</label>
                            <input type="file" id="design_images" name="design_images[]" accept="image/*" multiple>
                            <small>You can upload multiple images (sketches, reference photos, inspiration, etc.)</small>
                            <div id="imagePreviewContainer" class="image-preview-container"></div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-submit btn-press">
                                <i class="fas fa-paper-plane"></i> Submit Design Request
                            </button>
                            <a href="explore.php" class="btn-secondary">Cancel</a>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </main>
    
    <style>
        .custom-design-page {
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
        
        .form-section {
            max-width: 900px;
            margin: 0 auto;
            padding: 50px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
        }
        
        .custom-design-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
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
        
        .form-group small {
            margin-top: 5px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .image-preview-item {
            position: relative;
            aspect-ratio: 1;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn-submit {
            padding: 16px 40px;
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
        
        .btn-secondary {
            padding: 16px 40px;
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            text-decoration: none;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            .custom-design-page {
                padding: 50px 0;
            }
            
            .page-title {
                font-size: 32px;
            }
            
            .form-section {
                padding: 30px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <script>
        // Image preview for custom design form
        document.getElementById('design_images')?.addEventListener('change', function(e) {
            const container = document.getElementById('imagePreviewContainer');
            container.innerHTML = '';
            
            if (this.files && this.files.length > 0) {
                Array.from(this.files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'image-preview-item';
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            div.appendChild(img);
                            container.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>

<?php require_once 'includes/footer.php'; ?>

