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

// Get hero video/image before closing connection
$hero_video = 'assets/videos/hero.mp4';
$hero_poster = 'assets/images/hero-poster.png'; // Default to PNG
$hero_image = 'assets/images/hero-poster.png'; // Main hero image
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='hero_video'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $hero_video = 'assets/videos/' . $row['content_value'];
    }
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='hero_poster'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $hero_poster = 'assets/images/' . $row['content_value'];
        $hero_image = 'assets/images/' . $row['content_value'];
    }
}

$conn->close();
?>
    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <?php
            $image_path = __DIR__ . '/' . $hero_image;
            $video_path = __DIR__ . '/' . $hero_video;
            
            // Prioritize image over video
            if (file_exists($image_path)): ?>
                <img src="<?php echo $hero_image; ?>" alt="Hero" class="hero__image" style="position: absolute; width: 100%; height: 100%; object-fit: cover; z-index: 1;">
            <?php elseif (file_exists($video_path)): ?>
                <video class="hero__video" autoplay muted loop playsinline preload="metadata" poster="<?php echo $hero_poster; ?>">
                    <source src="<?php echo $hero_video; ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php else: ?>
                <div class="hero-placeholder" style="position: absolute; width: 100%; height: 100%; background: var(--background-color); z-index: 1;"></div>
            <?php endif; ?>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <div class="hero-logo reveal" data-delay="0">
                    <h1><?php echo SITE_NAME; ?></h1>
                </div>
                <p class="hero-tagline reveal" data-delay="120"><?php echo $hero_tagline; ?></p>
                <a href="categories.php" class="btn-explore btn-press reveal" data-delay="240"><?php echo t('explore'); ?> <i class="fas fa-arrow-right"></i></a>
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
                                    <img src="<?php echo $vision_data['image'] ?? 'assets/images/vision.jpg'; ?>" alt="Vision">
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
                                    <img src="<?php echo $mission_data['image'] ?? 'assets/images/mission.jpg'; ?>" alt="Mission">
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
                                    <img src="<?php echo $services_data['image'] ?? 'assets/images/services.jpg'; ?>" alt="Services">
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
                                        <?php endfor; ?>
                                    </div>
                                    <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                    <p class="review-author">- <?php echo htmlspecialchars($review['customer_name']); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <!-- Duplicate for seamless loop -->
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-stars">
                                        <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                    <p class="review-author">- <?php echo htmlspecialchars($review['customer_name']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php require_once 'includes/footer.php'; ?>

