<?php
require_once 'check-auth.php';

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
    $image_file = '../assets/images/hero-poster.jpg';
    if (file_exists($image_file)) {
        unlink($image_file);
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
        $image_path = 'assets/images/' . $file_name;
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
        $image_path = 'assets/images/' . $file_name;
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
        $image_path = 'assets/images/' . $file_name;
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
            </div>
            
            <?php if (isset($success) || isset($upload_success)): ?>
                <div class="success-message">Content updated successfully!</div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
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
                
                <!-- About Us Section -->
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
                
                <!-- Contact Section -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Contact Information</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($content_map['contact_email'] ?? $content_map['footer_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($content_map['contact_phone'] ?? $content_map['footer_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_address">Contact Address</label>
                        <textarea id="contact_address" name="contact_address" rows="3"><?php echo htmlspecialchars($content_map['contact_address'] ?? ''); ?></textarea>
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
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="faq_index" value="<?php echo $index; ?>">
                                    <button type="submit" name="delete_faq" class="btn-delete" onclick="return confirm('Delete this FAQ?')">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Shipping Information</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="shipping_content">Shipping Content (HTML allowed)</label>
                        <textarea id="shipping_content" name="shipping_content" rows="10" style="font-family: monospace;"><?php echo htmlspecialchars($content_map['shipping_content'] ?? ''); ?></textarea>
                    </div>
                    </div>
                </div>
                
                <!-- Return Policy -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Return Policy</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
                    <div class="form-group">
                        <label for="returns_content">Return Policy Content (HTML allowed)</label>
                        <textarea id="returns_content" name="returns_content" rows="10" style="font-family: monospace;"><?php echo htmlspecialchars($content_map['returns_content'] ?? ''); ?></textarea>
                    </div>
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
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="remove_image" value="vision_image">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Remove this image?')">Remove Image</button>
                                </form>
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
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="remove_image" value="mission_image">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Remove this image?')">Remove Image</button>
                                </form>
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
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="remove_image" value="services_image">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Remove this image?')">Remove Image</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="services_image" name="services_image" accept="image/*">
                        <small>Upload a new image to replace the current one</small>
                    </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="content-section collapsible-section">
                    <h2 class="section-header" onclick="toggleSection(this)">
                        <span>Footer</span>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </h2>
                    <div class="section-content">
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
                form.action = 'site-content.php';
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
                form.action = 'site-content.php';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_hero_image';
                input.value = '1';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
