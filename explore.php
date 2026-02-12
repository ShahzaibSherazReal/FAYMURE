<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();

// Get explore page content
function getContent($key, $default = '') {
    global $conn;
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='$key'");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && is_array($row) && !empty($row['content_value'])) {
            return $row['content_value'];
        }
    }
    return $default;
}

$explore_title = getContent('explore_title', 'Explore Our Services');
$explore_subtitle = getContent('explore_subtitle', 'Choose how you\'d like to work with us');
$option1_title = getContent('explore_option1_title', 'Design Your Own Product');
$option1_description = getContent('explore_option1_description', 'Create a unique product from scratch. Share your vision, upload inspiration images, and let us bring your design to life.');
$option2_title = getContent('explore_option2_title', 'Browse & Customize');
$option2_description = getContent('explore_option2_description', 'Browse our product categories and request quotes for bulk orders. You can also request customizations to our existing products.');

$conn->close();
?>
    <main class="explore-page">
        <div class="container">
            <h1 class="page-title reveal"><?php echo htmlspecialchars($explore_title); ?></h1>
            <p class="page-subtitle reveal" data-delay="100"><?php echo htmlspecialchars($explore_subtitle); ?></p>
            
            <div class="explore-options">
                <!-- Option 1: Design Your Own -->
                <a href="explore-custom-design.php" class="explore-option-card hover-lift reveal" data-delay="200">
                    <div class="option-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h2 class="option-title"><?php echo htmlspecialchars($option1_title); ?></h2>
                    <p class="option-description"><?php echo htmlspecialchars($option1_description); ?></p>
                    <div class="option-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                
                <!-- Option 2: Browse & Customize -->
                <a href="explore-browse.php" class="explore-option-card hover-lift reveal" data-delay="300">
                    <div class="option-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h2 class="option-title"><?php echo htmlspecialchars($option2_title); ?></h2>
                    <p class="option-description"><?php echo htmlspecialchars($option2_description); ?></p>
                    <div class="option-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>
        </div>
    </main>
    
    <style>
        .explore-page {
            padding: 120px 0;
            background: var(--background-color);
            min-height: 70vh;
            display: flex;
            align-items: center;
        }
        
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .page-subtitle {
            text-align: center;
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 80px;
            font-weight: 300;
        }
        
        .explore-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .explore-option-card {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            padding: 60px 40px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .explore-option-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 8px 30px var(--shadow);
            transform: translateY(-8px);
        }
        
        .option-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .explore-option-card:hover .option-icon {
            background: var(--accent-color);
            transform: scale(1.1);
        }
        
        .option-title {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .option-description {
            font-size: 16px;
            color: var(--text-color);
            line-height: 1.8;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .option-arrow {
            color: var(--primary-color);
            font-size: 24px;
            transition: transform 0.3s ease;
        }
        
        .explore-option-card:hover .option-arrow {
            transform: translateX(10px);
        }
        
        @media (max-width: 768px) {
            .explore-page {
                padding: 80px 0;
            }
            
            .page-title {
                font-size: 32px;
            }
            
            .page-subtitle {
                font-size: 16px;
                margin-bottom: 50px;
            }
            
            .explore-options {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .explore-option-card {
                padding: 40px 30px;
            }
            
            .option-title {
                font-size: 24px;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>
