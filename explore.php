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

$explore_title = getContent('explore_title', 'Catalog');
$explore_subtitle = getContent('explore_subtitle', 'Browse our product categories');

// Get all categories
$categories = [];
$categories_result = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name");
if ($categories_result) {
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
    <main class="explore-page">
        <div class="container">
            <div class="page-header reveal">
                <h1 class="page-title"><?php echo htmlspecialchars($explore_title); ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($explore_subtitle); ?></p>
            </div>
            
            <?php if (empty($categories)): ?>
                <div class="no-categories reveal" data-delay="100">
                    <i class="fas fa-inbox"></i>
                    <p>No categories available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid stagger">
                    <?php foreach ($categories as $index => $category): ?>
                        <a href="products.php?category=<?php echo $category['slug']; ?>" class="category-card hover-lift reveal" data-delay="<?php echo ($index * 50) + 100; ?>">
                            <div class="category-image">
                                <?php if ($category['image']): ?>
                                    <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                <?php else: ?>
                                    <div class="placeholder-image">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="category-overlay"></div>
                            </div>
                            <div class="category-info">
                                <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                <?php if ($category['description']): ?>
                                    <p><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?><?php echo strlen($category['description']) > 100 ? '...' : ''; ?></p>
                                <?php endif; ?>
                                <div class="category-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <style>
        .explore-page {
            padding: 120px 0;
            background: var(--background-color);
            min-height: 70vh;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 80px;
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
            font-weight: 300;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .category-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: block;
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .category-image {
            position: relative;
            width: 100%;
            height: 250px;
            overflow: hidden;
            background: var(--background-color);
        }
        
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .category-card:hover .category-image img {
            transform: scale(1.1);
        }
        
        .category-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent 0%, rgba(0, 31, 63, 0.3) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .category-card:hover .category-overlay {
            opacity: 1;
        }
        
        .placeholder-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: #fff;
            font-size: 48px;
            opacity: 0.1;
        }
        
        .category-info {
            padding: 30px;
        }
        
        .category-info h3 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .category-info p {
            font-size: 15px;
            color: var(--text-color);
            line-height: 1.6;
            margin-bottom: 15px;
            font-weight: 300;
        }
        
        .category-arrow {
            color: var(--primary-color);
            font-size: 20px;
            transition: transform 0.3s ease;
            display: inline-block;
        }
        
        .category-card:hover .category-arrow {
            transform: translateX(8px);
        }
        
        .no-categories {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }
        
        .no-categories i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .explore-page {
                padding: 80px 0;
            }
            
            .page-title {
                font-size: 36px;
            }
            
            .page-subtitle {
                font-size: 16px;
            }
            
            .page-header {
                margin-bottom: 50px;
            }
            
            .categories-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .category-image {
                height: 200px;
            }
            
            .category-info {
                padding: 20px;
            }
            
            .category-info h3 {
                font-size: 20px;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>
