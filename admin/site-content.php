<?php
require_once 'check-auth.php';
require_once __DIR__ . '/../includes/image-upload-webp.php';

$conn = getDBConnection();

// Handle remove hero video
if (isset($_POST['remove_hero_video'])) {
    $video_file = '../assets/videos/hero.mp4';
    if (file_exists($video_file)) {
        unlink($video_file);
    }
    $stmt = $conn->prepare("DELETE FROM site_content WHERE content_key='hero_video'");
    $stmt->execute();
    $stmt->close();
    $success = true;
}

// Handle remove hero image
if (isset($_POST['remove_hero_image'])) {
    foreach (['../assets/images/hero-poster.jpg', '../assets/images/hero-poster.png', '../assets/images/hero-poster.webp'] as $image_file) {
        if (file_exists($image_file)) {
            unlink($image_file);
        }
    }
    $stmt = $conn->prepare("DELETE FROM site_content WHERE content_key='hero_poster'");
    $stmt->execute();
    $stmt->close();
    $success = true;
}

// Handle remove image requests
if (isset($_POST['remove_image'])) {
    $image_key = sanitize($_POST['remove_image']);
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key=?");
    $stmt->bind_param("s", $image_key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $image_path = '../' . $row['content_value'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM site_content WHERE content_key=?");
    $stmt->bind_param("s", $image_key);
    $stmt->execute();
    $stmt->close();
    $success = true;
}

// Handle file uploads
$upload_dir_images = '../assets/images/';
if (!file_exists($upload_dir_images)) {
    mkdir($upload_dir_images, 0777, true);
}

$upload_dir_videos = '../assets/videos/';
if (!file_exists($upload_dir_videos)) {
    mkdir($upload_dir_videos, 0777, true);
}

// Hero video upload
if (isset($_FILES['hero_video']) && $_FILES['hero_video']['error'] == 0) {
    $file_name = 'hero.mp4';
    $target_file = $upload_dir_videos . $file_name;
    if (move_uploaded_file($_FILES['hero_video']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('hero_video', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $file_name, $file_name);
        $stmt->execute();
        $stmt->close();
        $upload_success = true;
    }
}

// Hero image upload
if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == 0) {
    $file_ext = pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION);
    $file_name = 'hero-poster.' . $file_ext;
    $target_file = $upload_dir_images . $file_name;
    if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $target_file)) {
        $webp_path = convert_file_to_webp($target_file);
        $file_name = $webp_path ? basename($webp_path) : $file_name;
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('hero_poster', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $file_name, $file_name);
        $stmt->execute();
        $stmt->close();
        $upload_success = true;
    }
}

// Vision image upload
if (isset($_FILES['vision_image']) && $_FILES['vision_image']['error'] == 0) {
    $file_ext = pathinfo($_FILES['vision_image']['name'], PATHINFO_EXTENSION);
    $file_name = 'vision.' . $file_ext;
    $target_file = $upload_dir_images . $file_name;
    if (move_uploaded_file($_FILES['vision_image']['tmp_name'], $target_file)) {
        $webp_path = convert_file_to_webp($target_file);
        $image_path = $webp_path ? str_replace('../', '', $webp_path) : ('assets/images/' . $file_name);
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('vision_image', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $image_path, $image_path);
        $stmt->execute();
        $stmt->close();
        $upload_success = true;
    }
}

// Mission image upload
if (isset($_FILES['mission_image']) && $_FILES['mission_image']['error'] == 0) {
    $file_ext = pathinfo($_FILES['mission_image']['name'], PATHINFO_EXTENSION);
    $file_name = 'mission.' . $file_ext;
    $target_file = $upload_dir_images . $file_name;
    if (move_uploaded_file($_FILES['mission_image']['tmp_name'], $target_file)) {
        $webp_path = convert_file_to_webp($target_file);
        $image_path = $webp_path ? str_replace('../', '', $webp_path) : ('assets/images/' . $file_name);
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('mission_image', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $image_path, $image_path);
        $stmt->execute();
        $stmt->close();
        $upload_success = true;
    }
}

// Services image upload
if (isset($_FILES['services_image']) && $_FILES['services_image']['error'] == 0) {
    $file_ext = pathinfo($_FILES['services_image']['name'], PATHINFO_EXTENSION);
    $file_name = 'services.' . $file_ext;
    $target_file = $upload_dir_images . $file_name;
    if (move_uploaded_file($_FILES['services_image']['tmp_name'], $target_file)) {
        $webp_path = convert_file_to_webp($target_file);
        $image_path = $webp_path ? str_replace('../', '', $webp_path) : ('assets/images/' . $file_name);
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('services_image', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $image_path, $image_path);
        $stmt->execute();
        $stmt->close();
        $upload_success = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    foreach ($_POST as $key => $value) {
        if ($key != 'submit' && $key != 'hero_video' && $key != 'hero_image' && 
            $key != 'vision_image' && $key != 'mission_image' && $key != 'services_image' &&
            $key != 'remove_image') {
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE content_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
    }
    $success = true;
}

// Handle FAQ item management
if (isset($_POST['add_faq'])) {
    $faq_question = $_POST['faq_question'] ?? '';
    $faq_answer = $_POST['faq_answer'] ?? '';
    if ($faq_question && $faq_answer) {
        // Get existing FAQs
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='faq_items'");
        $faqs = [];
        if ($result && $row = $result->fetch_assoc()) {
            $faqs = json_decode($row['content_value'], true) ?: [];
        }
        $faqs[] = ['question' => $faq_question, 'answer' => $faq_answer];
        $faqs_json = json_encode($faqs);
        $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('faq_items', ?) 
                                ON DUPLICATE KEY UPDATE content_value = ?");
        $stmt->bind_param("ss", $faqs_json, $faqs_json);
        $stmt->execute();
        $stmt->close();
        $success = true;
    }
}

if (isset($_POST['delete_faq']) && isset($_POST['faq_index'])) {
    $faq_index = intval($_POST['faq_index']);
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='faq_items'");
    if ($result && $row = $result->fetch_assoc()) {
        $faqs = json_decode($row['content_value'], true) ?: [];
        if (isset($faqs[$faq_index])) {
            unset($faqs[$faq_index]);
            $faqs = array_values($faqs); // Re-index array
            $faqs_json = json_encode($faqs);
            $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key='faq_items'");
            $stmt->bind_param("s", $faqs_json);
            $stmt->execute();
            $stmt->close();
            $success = true;
        }
    }
}

if (isset($_POST['delete_review']) && isset($_POST['review_id'])) {
    $rid = (int)$_POST['review_id'];
    if ($rid > 0) {
        $stmt = $conn->prepare("UPDATE reviews SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();
        $success = true;
    }
}

// Load all content
$content = [];
$content_map = [];

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $content = $conn->query("SELECT * FROM site_content ORDER BY content_key")->fetch_all(MYSQLI_ASSOC);
    foreach ($content as $item) {
        $content_map[$item['content_key']] = $item['content_value'] ?? '';
    }
}

// Load FAQs
$faqs = [];
$faq_result = $conn->query("SELECT content_value FROM site_content WHERE content_key='faq_items'");
if ($faq_result && $row = $faq_result->fetch_assoc()) {
    $faqs = json_decode($row['content_value'], true) ?: [];
}

// Load reviews (for homepage carousel)
$reviews_list = [];
$reviews_table = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($reviews_table && $reviews_table->num_rows > 0) {
    $reviews_result = $conn->query("SELECT id, customer_name, review_text, rating, status FROM reviews ORDER BY created_at DESC");
    if ($reviews_result) {
        $reviews_list = $reviews_result->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Content - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Site Content Management</h1>
                <nav class="content-quick-nav">
                    <a href="#home">Home</a>
                    <a href="#about">About Us</a>
                    <a href="#manufacturing">Manufacturing</a>
                    <a href="#contact">Contact</a>
                </nav>
            </div>
            
            <?php if (isset($success) || isset($upload_success)): ?>
                <div class="success-message">Content updated successfully!</div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <!-- ========== HOME ========== -->
                <h2 class="content-group-title" id="home">Home</h2>
                
                <!-- Hero Section -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Hero Section</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    
                    <div class="form-group">
                        <label for="hero_tagline">Hero Tagline</label>
                        <input type="text" id="hero_tagline" name="hero_tagline" value="<?php echo htmlspecialchars($content_map['hero_tagline'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="hero_video">Hero Video (MP4)</label>
                        <input type="file" id="hero_video" name="hero_video" accept="video/mp4">
                        <small>Current: <?php echo htmlspecialchars($content_map['hero_video'] ?? 'hero.mp4'); ?></small>
                        <?php if (isset($content_map['hero_video'])): ?>
                            <div style="margin-top: 10px;">
                                <button type="button" onclick="removeHeroVideo()" class="btn-danger">Remove Video</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="hero_image">Hero Poster Image</label>
                        <input type="file" id="hero_image" name="hero_image" accept="image/*">
                        <small>Current: <?php echo htmlspecialchars($content_map['hero_poster'] ?? 'hero-poster.jpg'); ?></small>
                        <?php if (isset($content_map['hero_poster'])): ?>
                            <div style="margin-top: 10px;">
                                <button type="button" onclick="removeHeroImage()" class="btn-danger">Remove Image</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
                
                <!-- Carousel (Vision, Mission, Services) -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>About Us</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="about_content">About Us Content (HTML allowed)</label>
                        <textarea id="about_content" name="about_content" rows="20" style="font-family: monospace;"><?php echo htmlspecialchars($content_map['about_content'] ?? ''); ?></textarea>
                        <small>You can use HTML tags for formatting. The content will replace the About Us page.</small>
                    </div>
                    </div>
                </div>
                
                <!-- Contact Section (Contact page + used as fallback for footer) -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Contact Information</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <p class="form-hint">These appear on the Contact page. Email and phone also show in the footer if set there.</p>
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($content_map['contact_email'] ?? $content_map['footer_email'] ?? ''); ?>" placeholder="e.g. info@faymure.com">
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($content_map['contact_phone'] ?? $content_map['footer_phone'] ?? ''); ?>" placeholder="e.g. +1 (555) 123-4567">
                    </div>
                    <div class="form-group">
                        <label for="contact_address">Contact Address</label>
                        <textarea id="contact_address" name="contact_address" rows="3" placeholder="Street, City, Country"><?php echo htmlspecialchars($content_map['contact_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="contact_whatsapp">WhatsApp Number</label>
                        <input type="text" id="contact_whatsapp" name="contact_whatsapp" value="<?php echo htmlspecialchars($content_map['contact_whatsapp'] ?? '923252100730'); ?>" placeholder="Country code + number, no + or spaces (e.g. 923252100730)">
                    </div>
                    </div>
                </div>
                
                <!-- FAQ Section -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>FAQ Management</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    
                    <div style="background: #f9f9f9; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                        <h3 style="margin-bottom: 15px;">Add New FAQ</h3>
                        <div class="form-group">
                            <label for="faq_question">Question</label>
                            <input type="text" id="faq_question" name="faq_question" placeholder="Enter question">
                        </div>
                        <div class="form-group">
                            <label for="faq_answer">Answer</label>
                            <textarea id="faq_answer" name="faq_answer" rows="3" placeholder="Enter answer"></textarea>
                        </div>
                        <button type="submit" name="add_faq" class="btn-primary">Add FAQ</button>
                    </div>
                    
                    <h3 style="margin-bottom: 15px;">Existing FAQs</h3>
                    <?php if (empty($faqs)): ?>
                        <p>No FAQs added yet.</p>
                    <?php else: ?>
                        <?php foreach ($faqs as $index => $faq): ?>
                            <div style="background: #fff; padding: 15px; margin-bottom: 15px; border: 1px solid var(--border-color);">
                                <strong>Q: <?php echo htmlspecialchars($faq['question']); ?></strong>
                                <p style="margin-top: 8px;">A: <?php echo htmlspecialchars($faq['answer']); ?></p>
                                <button type="button" class="btn-delete" onclick="submitDeleteFaq(<?php echo $index; ?>)">Delete</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
                
                <!-- Privacy Policy -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Privacy Policy</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="privacy_content">Privacy Policy Content (HTML allowed)</label>
                        <textarea id="privacy_content" name="privacy_content" rows="15" style="font-family: monospace;"><?php echo htmlspecialchars($content_map['privacy_content'] ?? ''); ?></textarea>
                    </div>
                    </div>
                </div>
                
                <!-- Terms & Conditions -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Terms & Conditions</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="terms_content">Terms & Conditions Content (HTML allowed)</label>
                        <textarea id="terms_content" name="terms_content" rows="15" style="font-family: monospace;"><?php echo htmlspecialchars($content_map['terms_content'] ?? ''); ?></textarea>
                    </div>
                    </div>
                </div>
                
                <!-- Vision, Mission, Services (existing) -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Vision</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="vision_title">Vision Title</label>
                        <input type="text" id="vision_title" name="vision_title" value="<?php echo htmlspecialchars($content_map['vision_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="vision_text">Vision Text</label>
                        <textarea id="vision_text" name="vision_text" rows="3"><?php echo htmlspecialchars($content_map['vision_text'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="vision_image">Vision Image</label>
                        <?php if (!empty($content_map['vision_image'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../<?php echo htmlspecialchars($content_map['vision_image']); ?>" alt="Vision" style="max-width: 200px; display: block; margin-bottom: 10px;">
                                <button type="button" class="btn-delete" onclick="submitRemoveImage('vision_image')">Remove Image</button>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="vision_image" name="vision_image" accept="image/*">
                        <small>Upload a new image to replace the current one</small>
                    </div>
                    </div>
                </div>
                
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Mission</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="mission_title">Mission Title</label>
                        <input type="text" id="mission_title" name="mission_title" value="<?php echo htmlspecialchars($content_map['mission_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mission_text">Mission Text</label>
                        <textarea id="mission_text" name="mission_text" rows="3"><?php echo htmlspecialchars($content_map['mission_text'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="mission_image">Mission Image</label>
                        <?php if (!empty($content_map['mission_image'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../<?php echo htmlspecialchars($content_map['mission_image']); ?>" alt="Mission" style="max-width: 200px; display: block; margin-bottom: 10px;">
                                <button type="button" class="btn-delete" onclick="submitRemoveImage('mission_image')">Remove Image</button>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="mission_image" name="mission_image" accept="image/*">
                        <small>Upload a new image to replace the current one</small>
                    </div>
                    </div>
                </div>
                
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Services</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="services_title">Services Title</label>
                        <input type="text" id="services_title" name="services_title" value="<?php echo htmlspecialchars($content_map['services_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="services_text">Services Text</label>
                        <textarea id="services_text" name="services_text" rows="3"><?php echo htmlspecialchars($content_map['services_text'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="services_image">Services Image</label>
                        <?php if (!empty($content_map['services_image'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../<?php echo htmlspecialchars($content_map['services_image']); ?>" alt="Services" style="max-width: 200px; display: block; margin-bottom: 10px;">
                                <button type="button" class="btn-delete" onclick="submitRemoveImage('services_image')">Remove Image</button>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="services_image" name="services_image" accept="image/*">
                        <small>Upload a new image to replace the current one</small>
                    </div>
                    </div>
                </div>
                
                <!-- Reviews (Homepage) -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Homepage Reviews</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <p>These reviews appear in the &quot;What Our Customers Say&quot; section on the homepage.</p>
                    <div class="form-group" style="background: #f9f9f9; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                        <h3 style="margin-bottom: 15px;">Add New Review</h3>
                        <div class="form-group">
                            <label for="review_customer_name">Customer Name</label>
                            <input type="text" id="review_customer_name" name="review_customer_name" placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group">
                            <label for="review_text">Review Text</label>
                            <textarea id="review_text" name="review_text" rows="3" placeholder="Review content"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="review_rating">Rating (1-5)</label>
                            <input type="number" id="review_rating" name="review_rating" min="1" max="5" value="5">
                        </div>
                        <button type="submit" name="add_review" class="btn-primary">Add Review</button>
                    </div>
                    <h3 style="margin-bottom: 15px;">Existing Reviews</h3>
                    <?php if (empty($reviews_list)): ?>
                        <p>No reviews yet. Add one above.</p>
                    <?php else: ?>
                        <div class="reviews-admin-list">
                        <?php foreach ($reviews_list as $rev): ?>
                            <div class="review-admin-item" style="background: #fff; padding: 15px; margin-bottom: 15px; border: 1px solid var(--border-color);">
                                <strong><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                                <span class="status-badge status-<?php echo $rev['status'] ?? 'active'; ?>"><?php echo ucfirst($rev['status'] ?? 'active'); ?></span>
                                <p style="margin: 8px 0;">&ldquo;<?php echo htmlspecialchars($rev['review_text']); ?>&rdquo;</p>
                                <p style="margin: 4px 0; color: #666;">Rating: <?php echo (int)$rev['rating']; ?> / 5</p>
                                <?php if (($rev['status'] ?? '') !== 'inactive'): ?>
                                <button type="button" class="btn-delete" onclick="submitDeleteReview(<?php echo (int)$rev['id']; ?>)">Remove</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
                
                <!-- Footer (Home) -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Footer</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="footer_our_story">Our Story (footer section text)</label>
                        <textarea id="footer_our_story" name="footer_our_story" rows="3"><?php echo htmlspecialchars($content_map['footer_our_story'] ?? 'FAYMURE is dedicated to crafting premium leather goods that combine traditional craftsmanship with modern design.'); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="footer_email">Footer Email</label>
                        <input type="email" id="footer_email" name="footer_email" value="<?php echo htmlspecialchars($content_map['footer_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_phone">Footer Phone</label>
                        <input type="text" id="footer_phone" name="footer_phone" value="<?php echo htmlspecialchars($content_map['footer_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_facebook">Facebook URL</label>
                        <input type="url" id="footer_facebook" name="footer_facebook" value="<?php echo htmlspecialchars($content_map['footer_facebook'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_instagram">Instagram URL</label>
                        <input type="url" id="footer_instagram" name="footer_instagram" value="<?php echo htmlspecialchars($content_map['footer_instagram'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_twitter">Twitter URL</label>
                        <input type="url" id="footer_twitter" name="footer_twitter" value="<?php echo htmlspecialchars($content_map['footer_twitter'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_linkedin">LinkedIn URL</label>
                        <input type="url" id="footer_linkedin" name="footer_linkedin" value="<?php echo htmlspecialchars($content_map['footer_linkedin'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="footer_youtube">YouTube URL</label>
                        <input type="url" id="footer_youtube" name="footer_youtube" value="<?php echo htmlspecialchars($content_map['footer_youtube'] ?? ''); ?>">
                    </div>
                    </div>
                </div>
                
                <!-- ========== ABOUT US ========== -->
                <h2 class="content-group-title" id="about">About Us</h2>
                
                <!-- About Us Section -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Manufacturing</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="manufacturing_title">Page Title</label>
                        <input type="text" id="manufacturing_title" name="manufacturing_title" value="<?php echo htmlspecialchars($content_map['manufacturing_title'] ?? 'Manufacturing'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="manufacturing_content">Content Paragraph (HTML allowed)</label>
                        <textarea id="manufacturing_content" name="manufacturing_content" rows="10" style="font-family: monospace;"><?php echo htmlspecialchars($content_map['manufacturing_content'] ?? ''); ?></textarea>
                        <small>This content appears above the form on the manufacturing page. You can use HTML tags for formatting.</small>
                    </div>
                    <div class="form-group">
                        <a href="../manufacturing.php" target="_blank" class="btn-secondary" style="display: inline-block; margin-top: 10px;">
                            <i class="fas fa-external-link-alt"></i> View Manufacturing Page
                        </a>
                    </div>
                    </div>
                </div>
                
                <!-- ========== PRODUCT DEFAULTS ========== -->
                <h2 class="content-group-title" id="product-defaults">Product defaults</h2>
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Default key features</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="default_product_key_features">Default key features (one per line)</label>
                        <textarea id="default_product_key_features" name="default_product_key_features" rows="6" placeholder="Premium genuine leather&#10;Handcrafted&#10;Quality stitching"><?php echo htmlspecialchars($content_map['default_product_key_features'] ?? ''); ?></textarea>
                        <small>Shown on the product page when a product has no key features set. Leave empty to hide the Key Features section for those products.</small>
                    </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="submit" class="btn-primary">Save All Changes</button>
                </div>
            </form>
        </div>
    </main>

    <style>
        .collapsible-section {
            margin-bottom: 20px;
            border: 1px solid var(--border-color, #ddd);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--light-color, #f9f9f9);
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
            margin: 0;
            border-bottom: 1px solid var(--border-color, #ddd);
        }
        
        .section-header:hover {
            background: var(--border-color, #e9e9e9);
        }
        
        .section-header .toggle-icon {
            transition: transform 0.3s;
            color: var(--text-color, #666);
        }
        
        .collapsible-section.collapsed .section-header .toggle-icon {
            transform: rotate(-90deg);
        }
        
        .section-content {
            padding: 20px;
            transition: max-height 0.3s ease-out, padding 0.3s ease-out;
            overflow: hidden;
        }
        
        .collapsible-section.collapsed .section-content {
            max-height: 0;
            padding: 0 20px;
        }
        
        .section-header span {
            font-size: 1.2em;
            font-weight: 600;
        }
    </style>
    
    <script>
        function toggleSection(header) {
            const section = header.closest('.collapsible-section');
            section.classList.toggle('collapsed');
        }
        
        // Initialize: all sections expanded by default
        document.addEventListener('DOMContentLoaded', function() {
            // Optional: You can collapse all sections on load by uncommenting:
            // document.querySelectorAll('.collapsible-section').forEach(section => {
            //     section.classList.add('collapsed');
            // });
        });
        
        function removeHeroVideo() {
            if (confirm('Remove hero video? This will delete the video file.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = ''; // post to current URL (works with clean URLs)
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_hero_video';
                input.value = '1';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function removeHeroImage() {
            if (confirm('Remove hero poster image? This will delete the image file.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = ''; // post to current URL (works with clean URLs)
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_hero_image';
                input.value = '1';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function submitRemoveImage(key) {
            if (confirm('Remove this image?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_image';
                input.value = key;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function submitDeleteFaq(index) {
            if (confirm('Delete this FAQ?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var i1 = document.createElement('input');
                i1.type = 'hidden';
                i1.name = 'faq_index';
                i1.value = index;
                var i2 = document.createElement('input');
                i2.type = 'hidden';
                i2.name = 'delete_faq';
                i2.value = '1';
                form.appendChild(i1);
                form.appendChild(i2);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function submitDeleteReview(reviewId) {
            if (confirm('Remove this review from the homepage?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_review';
                input.value = '1';
                var inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'review_id';
                inputId.value = reviewId;
                form.appendChild(input);
                form.appendChild(inputId);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
