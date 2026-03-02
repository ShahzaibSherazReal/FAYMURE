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
    // Form uses category_type and additional_details; map to DB fields product_type and description
    $product_type = sanitize($_POST['category_type'] ?? $_POST['product_type'] ?? '');
    $description = sanitize($_POST['additional_details'] ?? $_POST['description'] ?? '');
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
        // Check if custom_designs table exists, create it if it doesn't
        $table_check = $conn->query("SHOW TABLES LIKE 'custom_designs'");
        if (!$table_check || $table_check->num_rows == 0) {
            // Create custom_designs table
            $create_table_sql = "CREATE TABLE IF NOT EXISTS custom_designs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(100) NOT NULL,
                customer_email VARCHAR(100) NOT NULL,
                customer_phone VARCHAR(20),
                product_type VARCHAR(255),
                description TEXT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                timeline VARCHAR(255),
                budget VARCHAR(255),
                images TEXT,
                user_id INT NULL,
                status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $conn->query($create_table_sql);
        }
        
        $images_json = json_encode($uploaded_images);
        $message = "Custom Design Request\n\n";
        $message .= "Product Type: " . $product_type . "\n";
        $message .= "Description: " . $description . "\n";
        $message .= "Quantity: " . $quantity . "\n";
        $message .= "Timeline: " . $timeline . "\n";
        $message .= "Budget: " . $budget . "\n";
        $message .= "Images: " . count($uploaded_images) . " uploaded";
        
        // Save to database - always use custom_designs table now
        $stmt = $conn->prepare("INSERT INTO custom_designs (customer_name, customer_email, customer_phone, product_type, description, quantity, timeline, budget, images, user_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $stmt->bind_param("sssssisssi", $name, $email, $phone, $product_type, $description, $quantity, $timeline, $budget, $images_json, $user_id);
        
        if ($stmt->execute()) {
            // Send email to admin (suppress errors if mail server is not configured)
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
            
            // Attempt to send email, but don't fail if mail server is not configured
            @mail($to, $subject, $email_message, $headers);
            
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

$base = defined('BASE_PATH') ? BASE_PATH : '';
$base_assets = $base ? $base . '/' : '';
$hero_carousel_images = [
    $base_assets . 'assets/images/design-hero-carousel-1.png',
    $base_assets . 'assets/images/design-hero-carousel-2.png',
    $base_assets . 'assets/images/design-hero-carousel-3.png',
    $base_assets . 'assets/images/design-hero-carousel-4.png',
];
$conn->close();
?>
    <main class="custom-design-page">
        <!-- Hero Section: Carousel -->
        <section class="custom-design-hero hero-carousel">
            <div class="hero-carousel-track">
                <?php foreach ($hero_carousel_images as $idx => $src): ?>
                <div class="hero-carousel-slide<?php echo $idx === 0 ? ' active' : ''; ?>" style="background-image: url('<?php echo htmlspecialchars($src); ?>');" role="img" aria-label="Leather manufacturing"></div>
                <?php endforeach; ?>
            </div>
            <div class="hero-overlay"></div>
            <div class="container hero-content">
                <h1 class="hero-title">Masterful Leather Manufacturing</h1>
            </div>
            <div class="hero-carousel-dots" aria-label="Carousel navigation">
                <?php foreach ($hero_carousel_images as $idx => $src): ?>
                <button type="button" class="hero-carousel-dot<?php echo $idx === 0 ? ' active' : ''; ?>" data-index="<?php echo $idx; ?>" aria-label="Go to slide <?php echo $idx + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Workflow Section -->
        <section class="custom-design-workflow">
            <div class="container">
                <div class="workflow-steps">
                    <div class="workflow-step">
                        <div class="workflow-icon"><i class="fas fa-leaf"></i></div>
                        <span class="workflow-label">Ethical Sourcing</span>
                    </div>
                    <div class="workflow-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                    <div class="workflow-step">
                        <div class="workflow-icon"><i class="fas fa-cogs"></i></div>
                        <span class="workflow-label">Tanning</span>
                    </div>
                    <div class="workflow-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                    <div class="workflow-step">
                        <div class="workflow-icon"><i class="fas fa-cut"></i></div>
                        <span class="workflow-label">Precision Cutting</span>
                    </div>
                    <div class="workflow-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                    <div class="workflow-step">
                        <div class="workflow-icon"><i class="fas fa-sync-alt"></i></div>
                        <span class="workflow-label">Expert Stitching</span>
                    </div>
                    <div class="workflow-arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></div>
                    <div class="workflow-step">
                        <div class="workflow-icon"><i class="fas fa-certificate"></i></div>
                        <span class="workflow-label">Final Quality Check</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="container">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Thank You!</h2>
                    <p>Your design request has been submitted successfully. We'll get back to you soon.</p>
                    <a href="<?php echo $base; ?>/explore-custom-design" class="btn-primary">Submit Another Request</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <section class="form-section">
                    <h2 class="section-title">Submit Your Design Request</h2>
                    <form method="POST" enctype="multipart/form-data" class="custom-design-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category_type">Category Type *</label>
                                <select id="category_type" name="category_type" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity *</label>
                                <select id="quantity" name="quantity" required>
                                    <?php for ($q = 100; $q <= 5000; $q += 50): ?>
                                        <option value="<?php echo $q; ?>"><?php echo number_format($q); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="additional_details">Additional Details *</label>
                            <textarea id="additional_details" name="additional_details" rows="6" required placeholder="Describe your product idea, specifications, materials, colors, dimensions, and any other requirements..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="design_images">Choose multiple files from device (optional)</label>
                            <input type="file" id="design_images" name="design_images[]" accept="image/*" multiple>
                            <small>You can upload multiple images (sketches, reference photos, etc.)</small>
                            <div id="imagePreviewContainer" class="image-preview-container"></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Submit
                            </button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </main>
    
    <style>
        .custom-design-page {
            padding: 0 0 100px;
            background: var(--background-color);
        }

        .custom-design-hero {
            background: #2c1810;
            padding: 80px 0 100px;
            text-align: center;
            position: relative;
            min-height: 380px;
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        .hero-carousel .hero-carousel-track {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
        }
        .hero-carousel .hero-carousel-slide {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
        }
        .hero-carousel .hero-carousel-slide.active {
            opacity: 1;
            z-index: 1;
        }
        .hero-carousel .hero-overlay {
            z-index: 2;
        }
        .hero-carousel .hero-carousel-dots {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 3;
        }
        .hero-carousel .hero-carousel-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #c9a962;
            background: transparent;
            cursor: pointer;
            padding: 0;
            transition: background 0.3s ease;
        }
        .hero-carousel .hero-carousel-dot:hover,
        .hero-carousel .hero-carousel-dot.active {
            background: #c9a962;
        }
        .custom-design-hero .hero-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(44,24,16,0.75) 0%, rgba(61,35,24,0.7) 50%, rgba(44,24,16,0.75) 100%);
            z-index: 0;
        }
        .custom-design-hero .hero-content {
            position: relative;
            z-index: 1;
        }
        .custom-design-hero .hero-title {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 42px;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: #c9a962;
            margin: 0;
        }

        .custom-design-workflow {
            background: #2c1810;
            padding: 60px 0 80px;
            border-top: 1px solid rgba(201, 169, 98, 0.2);
        }
        .workflow-steps {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            max-width: 1000px;
            margin: 0 auto;
            gap: 8px;
        }
        .workflow-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            flex: 0 0 auto;
        }
        .workflow-icon {
            width: 70px;
            height: 70px;
            border: 2px solid #c9a962;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c9a962;
            font-size: 28px;
            background: transparent;
        }
        .workflow-label {
            font-size: 14px;
            font-weight: 500;
            color: #c9a962;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .workflow-arrow {
            color: #c9a962;
            font-size: 18px;
            opacity: 0.8;
        }

        .form-section .back-link { color: var(--primary-color); }
        .form-section .back-link:hover { color: var(--accent-color); }

        .form-section {
            max-width: 900px;
            margin: 0 auto;
            padding: 60px 50px;
            background: #fff;
            border: 1px solid var(--border-color);
            margin-top: -40px;
            position: relative;
            z-index: 2;
        }
        .form-section .section-title {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 35px;
            text-align: center;
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
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
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
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
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
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
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
            .custom-design-hero {
                padding: 50px 0 70px;
                min-height: 320px;
            }
            .custom-design-hero .hero-title {
                font-size: 28px;
            }
            .custom-design-workflow {
                padding: 40px 16px 60px;
            }
            .workflow-steps {
                flex-wrap: nowrap;
                overflow-x: auto;
                justify-content: flex-start;
                gap: 12px;
                padding-bottom: 12px;
                -webkit-overflow-scrolling: touch;
            }
            .workflow-step {
                flex: 0 0 auto;
                min-width: 90px;
            }
            .workflow-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            .workflow-label {
                font-size: 10px;
                text-align: center;
            }
            .workflow-arrow {
                flex: 0 0 auto;
                transform: none;
            }
            .custom-design-page {
                padding: 0 0 50px;
            }
            .form-section {
                padding: 40px 20px;
                margin-top: -30px;
            }
            .form-section .section-title {
                font-size: 22px;
            }
            .form-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }
    </style>
    
    <script>
        // Hero carousel: auto-advance and dot navigation
        (function() {
            var slides = document.querySelectorAll('.hero-carousel-slide');
            var dots = document.querySelectorAll('.hero-carousel-dot');
            if (slides.length === 0) return;
            var current = 0;
            var total = slides.length;
            function goTo(index) {
                current = (index + total) % total;
                slides.forEach(function(s, i) { s.classList.toggle('active', i === current); });
                dots.forEach(function(d, i) { d.classList.toggle('active', i === current); });
            }
            dots.forEach(function(dot, i) {
                dot.addEventListener('click', function() { goTo(i); });
            });
            setInterval(function() { goTo(current + 1); }, 5000);
        })();

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

