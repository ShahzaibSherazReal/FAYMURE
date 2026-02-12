<?php
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/cart-functions.php';

$conn = getDBConnection();

// Check if shop is in coming soon mode
$shop_coming_soon = false;
$result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_coming_soon'");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row && is_array($row) && !empty($row['content_value']) && $row['content_value'] == '1') {
        $shop_coming_soon = true;
    }
}

// If coming soon, show message and exit
if ($shop_coming_soon) {
    ?>
    <main style="min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 100px 20px;">
        <div style="text-align: center; max-width: 600px;">
            <div style="font-size: 80px; color: var(--accent-color); margin-bottom: 30px;">
                <i class="fas fa-clock"></i>
            </div>
            <h1 style="font-family: 'Playfair Display', serif; font-size: 48px; color: var(--primary-color); margin-bottom: 20px; font-weight: 500;">
                Shop Coming Soon
            </h1>
            <p style="font-size: 18px; color: var(--text-color); line-height: 1.8; margin-bottom: 40px;">
                We're working hard to bring you an amazing shopping experience. Our shop will be available soon!
            </p>
            <a href="index.php" class="btn-primary" style="display: inline-block;">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </main>
    <?php
    require_once 'includes/footer.php';
    exit;
}

$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);

// Get shop hero content
$shop_hero_title = 'Shop Premium Leather Goods';
$shop_hero_subtitle = 'Discover our exquisite collection of handcrafted leather products';
$shop_hero_image = '';
$shop_hero_video = '';

// Check if site_content table exists and has content_value column
$table_check = $conn->query("SHOW TABLES LIKE 'site_content'");
if ($table_check && $table_check->num_rows > 0) {
    $columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
    if ($columns_check && $columns_check->num_rows > 0) {
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_hero_title'");
        if ($result && $row = $result->fetch_assoc()) {
            $shop_hero_title = !empty($row['content_value']) ? $row['content_value'] : $shop_hero_title;
        }
        
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_hero_subtitle'");
        if ($result && $row = $result->fetch_assoc()) {
            $shop_hero_subtitle = !empty($row['content_value']) ? $row['content_value'] : $shop_hero_subtitle;
        }
        
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_hero_image'");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && is_array($row) && !empty($row['content_value'])) {
                $shop_hero_image = $row['content_value'];
            }
        }
        
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shop_hero_video'");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && is_array($row) && !empty($row['content_value'])) {
                $shop_hero_video = $row['content_value'];
            }
        }
    }
}

$conn->close();
?>
    <main class="shop-page">
        <!-- Sidebar -->
        <aside class="shop-sidebar" id="shopSidebar">
            <div class="sidebar-header">
                <h2>Shop</h2>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="sidebar-content">
                <div class="sidebar-section">
                    <h3>Categories</h3>
                    <ul class="sidebar-categories">
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="products.php?category=<?php echo htmlspecialchars($category['slug']); ?>" class="sidebar-link">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <div class="shop-main">
            <!-- Sidebar Toggle Button -->
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
                <span>Filters</span>
            </button>

            <!-- Hero Section -->
            <section class="shop-hero">
                <?php
                // Prioritize image over video
                if (!empty($shop_hero_image)): 
                    $image_path = __DIR__ . '/' . $shop_hero_image;
                    if (file_exists($image_path)): ?>
                        <img src="<?php echo htmlspecialchars($shop_hero_image); ?>" alt="Shop Hero" class="shop-hero-image">
                    <?php endif;
                elseif (!empty($shop_hero_video)): 
                    $video_path = __DIR__ . '/assets/videos/' . $shop_hero_video;
                    if (file_exists($video_path)): ?>
                        <video class="shop-hero-video" autoplay muted loop playsinline>
                            <source src="assets/videos/<?php echo htmlspecialchars($shop_hero_video); ?>" type="video/<?php echo pathinfo($shop_hero_video, PATHINFO_EXTENSION); ?>">
                            Your browser does not support the video tag.
                        </video>
                    <?php endif;
                endif; ?>
                <div class="shop-hero-overlay"></div>
                <div class="shop-hero-content">
                    <h1 class="shop-hero-title reveal"><?php echo htmlspecialchars($shop_hero_title); ?></h1>
                    <p class="shop-hero-subtitle reveal" data-delay="100"><?php echo htmlspecialchars($shop_hero_subtitle); ?></p>
                </div>
            </section>

            <!-- Categories Section -->
            <section class="shop-categories">
                <div class="container">
                    <h2 class="section-title reveal">Browse by Category</h2>
                    <div class="categories-grid stagger">
                        <?php foreach ($categories as $category): ?>
                            <a href="products.php?category=<?php echo htmlspecialchars($category['slug']); ?>" class="category-card hover-lift reveal">
                                <div class="category-image">
                                    <?php if ($category['image']): ?>
                                        <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php else: ?>
                                        <div class="placeholder-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="category-info">
                                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                    <?php if ($category['description']): ?>
                                        <p><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?>...</p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- Cart Icon -->
        <a href="cart.php" class="cart-icon-link" title="View Cart">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count"><?php echo getCartCount(); ?></span>
        </a>
    </main>

    <style>
        .shop-page {
            position: relative;
            min-height: 100vh;
        }
        
        .cart-icon-link {
            position: fixed;
            top: 100px;
            right: 30px;
            z-index: 1000;
            background: var(--primary-color);
            color: #fff;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(0, 31, 63, 0.3);
            transition: all 0.3s ease;
            font-size: 24px;
        }
        
        .cart-icon-link:hover {
            background: var(--dark-color);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 31, 63, 0.4);
        }
        
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--accent-color);
            color: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        /* Sidebar Styles */
        .shop-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 300px;
            height: 100vh;
            background: var(--background-color);
            border-right: 1px solid var(--border-color);
            z-index: 1001;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .shop-sidebar.active {
            transform: translateX(0);
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 20px;
            border-bottom: 1px solid var(--border-color);
            background: var(--primary-color);
            color: #fff;
        }

        .sidebar-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 500;
            margin: 0;
        }

        .sidebar-close {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-close:hover {
            opacity: 0.7;
        }

        .sidebar-content {
            padding: 20px;
        }

        .sidebar-section {
            margin-bottom: 30px;
        }

        .sidebar-section h3 {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            color: var(--primary-color);
            margin-bottom: 16px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-categories {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-categories li {
            margin-bottom: 8px;
        }

        .sidebar-link {
            display: block;
            padding: 12px 16px;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 2px solid transparent;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .sidebar-link:hover {
            background: rgba(0, 31, 63, 0.05);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
            padding-left: 20px;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 50%;
            left: 20px;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 16px 20px;
            cursor: pointer;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-toggle:hover {
            background: var(--dark-color);
            transform: translateY(-50%) translateX(5px);
        }

        .sidebar-toggle i {
            font-size: 18px;
        }

        /* Main Content */
        .shop-main {
            width: 100%;
            transition: margin-left 0.3s ease;
        }

        /* Hero Section */
        .shop-hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            color: #fff;
            padding: 120px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .shop-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
            z-index: 1;
        }
        
        .shop-hero-image,
        .shop-hero-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }
        
        .shop-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 31, 63, 0.6);
            z-index: 2;
        }

        .shop-hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 40px;
        }

        .shop-hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            font-weight: 500;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .shop-hero-subtitle {
            font-size: 18px;
            font-weight: 300;
            opacity: 0.9;
            letter-spacing: 0.5px;
        }

        /* Categories Section */
        .shop-categories {
            padding: 80px 0;
            background: var(--background-color);
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 50px;
            text-align: center;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .shop-sidebar {
                width: 280px;
            }

            .sidebar-toggle {
                left: 10px;
                padding: 12px 16px;
                font-size: 12px;
            }

            .sidebar-toggle span {
                display: none;
            }

            .shop-hero {
                padding: 80px 0 60px;
            }

            .shop-hero-title {
                font-size: 32px;
            }

            .shop-hero-subtitle {
                font-size: 16px;
            }

            .shop-categories {
                padding: 60px 0;
            }

            .section-title {
                font-size: 28px;
                margin-bottom: 40px;
            }
        }

        @media (max-width: 480px) {
            .shop-sidebar {
                width: 100%;
            }

            .shop-hero-title {
                font-size: 24px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('shopSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            sidebarToggle.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            sidebarOverlay.addEventListener('click', closeSidebar);

            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });
    </script>

<?php require_once 'includes/footer.php'; ?>

