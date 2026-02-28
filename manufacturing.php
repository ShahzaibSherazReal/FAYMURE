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

// Explore options (same as home page)
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
    <main class="page-content manufacturing-page">
        <div class="container">
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

            <!-- Explore Options Section (same as home) -->
            <section class="explore-options-section manufacturing-explore">
                <div class="section-header">
                    <h2 class="section-title">Choose Your Path</h2>
                    <p class="section-subtitle">Select how you'd like to work with us</p>
                </div>
                <div class="explore-options-grid">
                    <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/explore-custom-design" class="explore-option-card">
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
                                <span>Get Started</span>
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/explore" class="explore-option-card">
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
            </section>
        </div>
    </main>

    <style>
        .manufacturing-page {
            padding: 100px 0;
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
            text-align: justify;
        }

        .intro-text p {
            margin-bottom: 20px;
            text-align: justify;
        }

        .intro-text p:last-child {
            margin-bottom: 0;
        }

        .manufacturing-design-hero {
            background: linear-gradient(135deg, #2c1810 0%, #3d2318 50%, #2c1810 100%);
            padding: 50px 0 60px;
            text-align: center;
            margin-top: 40px;
        }
        .manufacturing-design-hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: #c9a962;
            margin: 0;
        }

        .manufacturing-design-workflow {
            background: #2c1810;
            padding: 50px 0 60px;
            border-top: 1px solid rgba(201, 169, 98, 0.2);
        }
        .manufacturing-design-workflow .workflow-steps {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 10px 20px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .manufacturing-design-workflow .workflow-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .manufacturing-design-workflow .workflow-icon {
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
        .manufacturing-design-workflow .workflow-label {
            font-size: 14px;
            font-weight: 500;
            color: #c9a962;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .manufacturing-design-workflow .workflow-arrow {
            color: #c9a962;
            font-size: 18px;
            opacity: 0.8;
        }

        .manufacturing-explore {
            padding: 80px 0 100px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            position: relative;
            overflow: hidden;
            margin-top: 40px;
        }
        .manufacturing-explore::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(0,31,63,0.03)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.5;
            z-index: 0;
        }
        .manufacturing-explore .section-header,
        .manufacturing-explore .explore-options-grid {
            position: relative;
            z-index: 1;
        }
        .manufacturing-explore .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .manufacturing-explore .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary-color);
            margin-bottom: 12px;
            font-weight: 500;
        }
        .manufacturing-explore .section-subtitle {
            font-size: 18px;
            color: var(--text-color);
            font-weight: 300;
        }
        .manufacturing-explore .explore-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .manufacturing-explore .explore-option-card {
            position: relative;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: block;
            min-height: 380px;
        }
        .manufacturing-explore .explore-option-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        .manufacturing-explore .option-background {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
        }
        .manufacturing-explore .option-gradient {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            opacity: 0.05;
        }
        .manufacturing-explore .explore-option-card:hover .option-gradient {
            opacity: 0.1;
        }
        .manufacturing-explore .option-content {
            position: relative;
            z-index: 1;
            padding: 45px 35px;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .manufacturing-explore .option-icon-wrapper {
            margin-bottom: 24px;
        }
        .manufacturing-explore .option-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            transition: all 0.4s ease;
            box-shadow: 0 10px 30px rgba(0, 31, 63, 0.2);
        }
        .manufacturing-explore .explore-option-card:hover .option-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .manufacturing-explore .option-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            color: var(--primary-color);
            margin-bottom: 16px;
            font-weight: 500;
            line-height: 1.3;
        }
        .manufacturing-explore .option-description {
            font-size: 15px;
            color: var(--text-color);
            line-height: 1.7;
            margin-bottom: 28px;
            flex-grow: 1;
        }
        .manufacturing-explore .option-cta {
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
        .manufacturing-explore .explore-option-card:hover .option-cta {
            background: var(--accent-color);
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .manufacturing-page {
                padding: 50px 0;
            }

            .content-section {
                margin-bottom: 40px;
            }

            .intro-text {
                font-size: 16px;
            }
            .manufacturing-design-hero {
                padding: 40px 20px 50px;
                margin-top: 30px;
            }
            .manufacturing-design-hero-title {
                font-size: 26px;
            }
            .manufacturing-design-workflow {
                padding: 35px 20px 45px;
            }
            .manufacturing-design-workflow .workflow-steps {
                flex-direction: column;
                gap: 30px;
            }
            .manufacturing-design-workflow .workflow-arrow {
                transform: rotate(90deg);
            }
            .manufacturing-explore {
                padding: 50px 0 70px;
                margin-top: 30px;
            }
            .manufacturing-explore .section-title {
                font-size: 28px;
            }
            .manufacturing-explore .explore-options-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
                max-width: 100%;
            }
            .manufacturing-explore .explore-option-card {
                min-height: 280px;
            }
            .manufacturing-explore .option-content {
                padding: 24px 16px;
            }
            .manufacturing-explore .option-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            .manufacturing-explore .option-title {
                font-size: 18px;
                margin-bottom: 8px;
            }
            .manufacturing-explore .option-description {
                font-size: 12px;
                margin-bottom: 16px;
            }
            .manufacturing-explore .option-cta {
                padding: 10px 20px;
                font-size: 12px;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>

