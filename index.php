<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();

// Get site content - with error handling
$hero_tagline = 'Premium Leather Goods for Every Lifestyle';
$vision = [];
$mission = [];
$services = [];

// Check if site_content table has content_value column
$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='hero_tagline'");
    if ($result && $row = $result->fetch_assoc()) {
        $hero_tagline = $row['content_value'] ?? $hero_tagline;
    }

    $vision = $conn->query("SELECT * FROM site_content WHERE content_key LIKE 'vision_%'")->fetch_all(MYSQLI_ASSOC);
    $mission = $conn->query("SELECT * FROM site_content WHERE content_key LIKE 'mission_%'")->fetch_all(MYSQLI_ASSOC);
    $services = $conn->query("SELECT * FROM site_content WHERE content_key LIKE 'services_%'")->fetch_all(MYSQLI_ASSOC);
}

// Get reviews (check if table exists)
$reviews = [];
$reviews_check = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($reviews_check && $reviews_check->num_rows > 0) {
    $reviews_result = $conn->query("SELECT * FROM reviews WHERE status='active' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10");
    if ($reviews_result) {
        $reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Organize content
$vision_data = [];
$mission_data = [];
$services_data = [];

foreach ($vision as $item) {
    $key = str_replace('vision_', '', $item['content_key']);
    $vision_data[$key] = $item['content_value'];
}

foreach ($mission as $item) {
    $key = str_replace('mission_', '', $item['content_key']);
    $mission_data[$key] = $item['content_value'];
}

foreach ($services as $item) {
    $key = str_replace('services_', '', $item['content_key']);
    $services_data[$key] = $item['content_value'];
}

// Get hero video/image before closing connection (only use video when set in dashboard)
$hero_video = '';
$hero_poster = 'assets/images/hero-poster.png'; // Default to PNG
$hero_image = '';
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='hero_video'");
    if ($result) {
        $row = $result->fetch_assoc();
        if (is_array($row) && isset($row['content_value']) && $row['content_value'] !== '') {
            $hero_video = 'assets/videos/' . $row['content_value'];
        }
    }
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='hero_poster'");
    if ($result) {
        $row = $result->fetch_assoc();
        if (is_array($row) && isset($row['content_value']) && $row['content_value'] !== '') {
            $hero_poster = 'assets/images/' . $row['content_value'];
        }
    }
    // When no video in DB or video file missing, show poster image instead
    if ($hero_video === '' || !file_exists(__DIR__ . '/' . $hero_video)) {
        $hero_video = '';
        $hero_image = $hero_poster;
    }
}

// Get explore page content
$option1_title = 'Design Your Own Product';
$option1_description = 'Create a unique product from scratch. Share your vision, upload inspiration images, and let us bring your design to life.';
$option2_title = 'Browse & Customize';
$option2_description = 'Browse our product categories and request quotes for bulk orders. You can also request customizations to our existing products.';

if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='explore_option1_title'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $option1_title = $row['content_value'];
    }
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='explore_option1_description'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $option1_description = $row['content_value'];
    }
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='explore_option2_title'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $option2_title = $row['content_value'];
    }
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='explore_option2_description'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $option2_description = $row['content_value'];
    }
}

$conn->close();
?>
    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <?php
$video_path = $hero_video !== '' ? __DIR__ . '/' . $hero_video : '';
$hero_poster_src = (isset($base) && $base !== '') ? rtrim($base, '/') . '/' . $hero_poster : '/' . ltrim($hero_poster, '/');
$hero_video_src = (isset($base) && $base !== '') ? rtrim($base, '/') . '/' . $hero_video : '/' . ltrim($hero_video, '/');
$hero_video_type = $hero_video !== '' ? 'video/' . (pathinfo($hero_video, PATHINFO_EXTENSION) ?: 'mp4') : 'video/mp4';
// Prefer video when we have a path and file exists, else image, else placeholder
if ($hero_image !== '') {
    $image_path = __DIR__ . '/' . $hero_image;
    if (!file_exists($image_path) && preg_match('/^(.+?)\.(webp|jpe?g|png|gif)$/i', $hero_image, $m)) {
        $hero_base = $m[1];
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            $try = $hero_base . '.' . $ext;
            if (file_exists(__DIR__ . '/' . $try)) {
                $hero_image = $try;
                break;
            }
        }
    }
    $hero_src = (isset($base) && $base !== '') ? rtrim($base, '/') . '/' . $hero_image : '/' . ltrim($hero_image, '/');
}
if ($hero_image !== ''): ?>
                <img src="<?php echo htmlspecialchars($hero_src); ?>" alt="Hero" class="hero__image" style="position: absolute; width: 100%; height: 100%; object-fit: cover; z-index: 1;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div class="hero-placeholder" style="position: absolute; width: 100%; height: 100%; background: var(--background-color); z-index: 1; display: none;"></div>
            <?php
elseif ($hero_video !== '' && $video_path !== '' && file_exists($video_path)): ?>
                <video class="hero__video" autoplay muted loop playsinline preload="metadata" poster="<?php echo htmlspecialchars($hero_poster_src); ?>">
                    <source src="<?php echo htmlspecialchars($hero_video_src); ?>" type="<?php echo htmlspecialchars($hero_video_type); ?>">
                    Your browser does not support the video tag.
                </video>
            <?php
else: ?>
                <div class="hero-placeholder" style="position: absolute; width: 100%; height: 100%; background: var(--background-color); z-index: 1;"></div>
            <?php
endif; ?>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <div class="hero-logo reveal" data-delay="0">
                    <h1><?php echo SITE_NAME; ?></h1>
                </div>
                <p class="hero-tagline reveal" data-delay="120"><?php echo str_replace('Vission', 'Vision', $hero_tagline); ?></p>
                <a href="<?php echo $base; ?>/explore" class="btn-explore btn-press reveal" data-delay="240"><?php echo t('explore'); ?> <i class="fas fa-arrow-right"></i></a>
            </div>
        </section>

        <!-- Explore Options Section -->
        <section class="explore-options-section">
            <div class="container">
                <div class="section-header reveal">
                    <h2 class="section-title">Choose Your Path</h2>
                    <p class="section-subtitle">Select how you'd like to work with us</p>
                </div>
                
                <div class="explore-options-grid">
                    <!-- Design Your Own Product -->
                    <a href="<?php echo $base; ?>/explore-custom-design" class="explore-option-card reveal" data-delay="100">
                        <div class="option-background">
                            <div class="option-gradient"></div>
                        </div>
                        <div class="option-content">
                            <div class="option-icon-wrapper">
                                <div class="option-icon">
                                    <i class="fas fa-palette"></i>
                                </div>
                            </div>
                            <h3 class="option-title"><?php echo htmlspecialchars($option1_title); ?></h3>
                            <p class="option-description"><?php echo htmlspecialchars($option1_description); ?></p>
                            <div class="option-cta">
                                <span>Design</span>
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Browse & Customize -->
                    <a href="<?php echo $base; ?>/explore" class="explore-option-card reveal" data-delay="200">
                        <div class="option-background">
                            <div class="option-gradient"></div>
                        </div>
                        <div class="option-content">
                            <div class="option-icon-wrapper">
                                <div class="option-icon">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            </div>
                            <h3 class="option-title"><?php echo htmlspecialchars($option2_title); ?></h3>
                            <p class="option-description"><?php echo htmlspecialchars($option2_description); ?></p>
                            <div class="option-cta">
                                <span>Browse</span>
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Vision, Mission, Services Carousel -->
        <section class="info-carousel-section">
            <div class="container">
                <div class="carousel-wrapper">
                    <div class="carousel-container">
                        <div class="carousel-slide active reveal" data-delay="0">
                            <div class="slide-content">
                                <div class="slide-image img-zoom">
                                    <img src="<?php echo $base . '/' . ltrim($vision_data['image'] ?? 'assets/images/vision.jpg', '/'); ?>" alt="Vision">
                                </div>
                                <div class="slide-text">
                                    <h2><?php echo $vision_data['title'] ?? 'Our Vision'; ?></h2>
                                    <p><?php echo $vision_data['text'] ?? 'To be the leading provider of premium leather goods worldwide, combining traditional craftsmanship with modern design.'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-slide reveal" data-delay="100">
                            <div class="slide-content">
                                <div class="slide-image img-zoom">
                                    <img src="<?php echo $base . '/' . ltrim($mission_data['image'] ?? 'assets/images/mission.jpg', '/'); ?>" alt="Mission">
                                </div>
                                <div class="slide-text">
                                    <h2><?php echo $mission_data['title'] ?? 'Our Mission'; ?></h2>
                                    <p><?php echo $mission_data['text'] ?? 'To deliver exceptional quality leather products that exceed customer expectations while maintaining sustainable practices.'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-slide reveal" data-delay="200">
                            <div class="slide-content">
                                <div class="slide-image img-zoom">
                                    <img src="<?php echo $base . '/' . ltrim($services_data['image'] ?? 'assets/images/services.jpg', '/'); ?>" alt="Services">
                                </div>
                                <div class="slide-text">
                                    <h2><?php echo $services_data['title'] ?? 'Our Services'; ?></h2>
                                    <p><?php echo $services_data['text'] ?? 'We offer custom leather goods, bulk orders, and personalized products tailored to your needs.'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-btn prev" onclick="moveCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="carousel-btn next" onclick="moveCarousel(1)"><i class="fas fa-chevron-right"></i></button>
                    <div class="carousel-dots">
                        <span class="dot active" onclick="currentSlide(1)"></span>
                        <span class="dot" onclick="currentSlide(2)"></span>
                        <span class="dot" onclick="currentSlide(3)"></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reviews Section -->
        <section class="reviews-section">
            <div class="container">
                <h2 class="section-title reveal">What Our Customers Say</h2>
                <div class="reviews-container">
                    <div class="marquee reveal">
                        <div class="marquee__track">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-stars">
                                        <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php
    endfor; ?>
                                    </div>
                                    <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                    <p class="review-author">- <?php echo htmlspecialchars($review['customer_name']); ?></p>
                                </div>
                            <?php
endforeach; ?>
                            <!-- Duplicate for seamless loop -->
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-stars">
                                        <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php
    endfor; ?>
                                    </div>
                                    <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                    <p class="review-author">- <?php echo htmlspecialchars($review['customer_name']); ?></p>
                                </div>
                            <?php
endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <style>
        .explore-options-section {
            padding: 56px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            position: relative;
            overflow: hidden;
        }
        
        .explore-options-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(0,31,63,0.03)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.5;
            z-index: 0;
        }
        
        .explore-options-section .container {
            position: relative;
            z-index: 1;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 38px;
            color: var(--primary-color);
            margin-bottom: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .section-subtitle {
            font-size: 16px;
            color: var(--text-color);
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .explore-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 28px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .explore-option-card {
            position: relative;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: block;
            height: 100%;
            min-height: 380px;
        }
        
        .explore-option-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .option-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        .option-gradient {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            opacity: 0.05;
            transition: opacity 0.4s ease;
        }
        
        .explore-option-card:hover .option-gradient {
            opacity: 0.1;
        }
        
        .option-content {
            position: relative;
            z-index: 1;
            padding: 44px 36px;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .option-icon-wrapper {
            margin-bottom: 24px;
        }
        
        .option-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(0, 31, 63, 0.2);
        }
        
        .explore-option-card:hover .option-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 40px rgba(0, 31, 63, 0.3);
        }
        
        .option-title {
            font-family: 'TT DRUGS TRIAL REGULAR', sans-serif;
            font-size: 26px;
            color: var(--primary-color);
            margin-bottom: 14px;
            font-weight: 500;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        
        .option-description {
            font-size: 15px;
            color: var(--text-color);
            line-height: 1.7;
            margin-bottom: 24px;
            font-weight: 300;
            flex-grow: 1;
        }
        
        .option-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: var(--primary-color);
            color: #fff;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 31, 63, 0.2);
        }
        
        .explore-option-card:hover .option-cta {
            background: var(--accent-color);
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(0, 31, 63, 0.3);
        }
        
        .explore-option-card:hover .option-cta i {
            transform: translateX(5px);
        }
        
        .option-cta i {
            transition: transform 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .explore-options-section {
                padding: 44px 0;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .section-subtitle {
                font-size: 14px;
            }
            
            .explore-options-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
                max-width: 100%;
            }
            
            .explore-option-card {
                min-height: 280px;
            }
            
            .option-content {
                padding: 24px 16px;
            }
            
            .option-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            
            .option-title {
                font-size: 18px;
                margin-bottom: 8px;
            }
            
            .option-description {
                font-size: 12px;
                margin-bottom: 16px;
            }
            
            .option-cta {
                padding: 10px 20px;
                font-size: 12px;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>

