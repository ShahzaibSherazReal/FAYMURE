<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$email = 'contact@faymure.com';
$phone = '+1 (555) 123-4567';
$address = '';

// Check if content_value column exists
$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='contact_email'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $email = $row['content_value'];
        } else {
            $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_email'");
            if ($result) {
                $row = $result->fetch_assoc();
                if ($row && is_array($row) && !empty($row['content_value'])) {
                    $email = $row['content_value'];
                }
            }
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='contact_phone'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $phone = $row['content_value'];
        } else {
            $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_phone'");
            if ($result) {
                $row = $result->fetch_assoc();
                if ($row && is_array($row) && !empty($row['content_value'])) {
                    $phone = $row['content_value'];
                }
            }
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='contact_address'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $address = $row['content_value'];
        }
    }
    
    // Get social media links
    $facebook = '#';
    $instagram = '#';
    $twitter = '#';
    $linkedin = '#';
    $youtube = '#';
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_facebook'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $facebook = $row['content_value'];
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_instagram'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $instagram = $row['content_value'];
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_twitter'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $twitter = $row['content_value'];
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_linkedin'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $linkedin = $row['content_value'];
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_youtube'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            $youtube = $row['content_value'];
        }
    }
}
$conn->close();
?>
    <main class="contact-page">
        <div class="container">
            <div class="contact-hero">
                <h1 class="page-title reveal">Get in Touch</h1>
                <p class="page-subtitle reveal" data-delay="100">We'd love to hear from you. Reach out to us through any of the channels below.</p>
            </div>
            
            <div class="contact-content">
                <div class="contact-info-section">
                    <div class="contact-info-grid">
                        <div class="contact-card reveal" data-delay="200">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h3>Email Us</h3>
                            <p><a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a></p>
                            <span class="contact-label">We'll respond within 24 hours</span>
                        </div>
                        
                        <div class="contact-card reveal" data-delay="300">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <h3>Call Us</h3>
                            <p><a href="tel:<?php echo htmlspecialchars($phone); ?>"><?php echo htmlspecialchars($phone); ?></a></p>
                            <span class="contact-label">Mon-Fri, 9AM-6PM EST</span>
                        </div>
                        
                        <?php if (!empty($address)): ?>
                        <div class="contact-card reveal" data-delay="400">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h3>Visit Us</h3>
                            <p><?php echo nl2br(htmlspecialchars($address)); ?></p>
                            <span class="contact-label">Come see our showroom</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="social-section reveal" data-delay="500">
                        <h2>Follow Us</h2>
                        <p class="social-intro">Stay connected with us on social media</p>
                        <div class="social-links-grid">
                            <?php if ($facebook != '#'): ?>
                            <a href="<?php echo htmlspecialchars($facebook); ?>" target="_blank" rel="noopener noreferrer" class="social-link facebook">
                                <i class="fab fa-facebook-f"></i>
                                <span>Facebook</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($instagram != '#'): ?>
                            <a href="<?php echo htmlspecialchars($instagram); ?>" target="_blank" rel="noopener noreferrer" class="social-link instagram">
                                <i class="fab fa-instagram"></i>
                                <span>Instagram</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($twitter != '#'): ?>
                            <a href="<?php echo htmlspecialchars($twitter); ?>" target="_blank" rel="noopener noreferrer" class="social-link twitter">
                                <i class="fab fa-twitter"></i>
                                <span>Twitter</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($linkedin != '#'): ?>
                            <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="social-link linkedin">
                                <i class="fab fa-linkedin-in"></i>
                                <span>LinkedIn</span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($youtube != '#'): ?>
                            <a href="<?php echo htmlspecialchars($youtube); ?>" target="_blank" rel="noopener noreferrer" class="social-link youtube">
                                <i class="fab fa-youtube"></i>
                                <span>YouTube</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .contact-page {
            padding: 120px 0;
            background: linear-gradient(135deg, rgba(0, 31, 63, 0.02) 0%, rgba(255, 255, 255, 1) 100%);
        }
        
        .contact-hero {
            text-align: center;
            margin-bottom: 80px;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 56px;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .page-subtitle {
            font-size: 20px;
            color: var(--text-color);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.8;
            font-weight: 300;
        }
        
        .contact-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-bottom: 80px;
        }
        
        .contact-card {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            padding: 50px 40px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .contact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .contact-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 31, 63, 0.15);
            border-color: var(--primary-color);
        }
        
        .contact-card:hover::before {
            transform: scaleX(1);
        }
        
        .contact-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: rgba(0, 31, 63, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .contact-card:hover .contact-icon {
            background: var(--primary-color);
            color: #fff;
            transform: scale(1.1);
        }
        
        .contact-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .contact-card p {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .contact-card a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s ease;
            font-weight: 500;
        }
        
        .contact-card a:hover {
            color: var(--accent-color);
        }
        
        .contact-label {
            display: block;
            font-size: 14px;
            color: var(--text-secondary);
            font-style: italic;
            margin-top: 10px;
        }
        
        .social-section {
            text-align: center;
            padding: 60px 40px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
        }
        
        .social-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .social-intro {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 40px;
        }
        
        .social-links-grid {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .social-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 30px 40px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
            min-width: 140px;
        }
        
        .social-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 31, 63, 0.15);
        }
        
        .social-link i {
            font-size: 32px;
            transition: transform 0.3s ease;
        }
        
        .social-link:hover i {
            transform: scale(1.2);
        }
        
        .social-link span {
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .social-link.facebook:hover {
            border-color: #1877F2;
            color: #1877F2;
        }
        
        .social-link.instagram:hover {
            border-color: #E4405F;
            color: #E4405F;
        }
        
        .social-link.twitter:hover {
            border-color: #1DA1F2;
            color: #1DA1F2;
        }
        
        .social-link.linkedin:hover {
            border-color: #0077B5;
            color: #0077B5;
        }
        
        .social-link.youtube:hover {
            border-color: #FF0000;
            color: #FF0000;
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 40px;
            }
            
            .contact-info-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .social-links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .social-link {
                min-width: auto;
                flex: 1;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>

