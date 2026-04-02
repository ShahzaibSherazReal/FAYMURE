<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$category_slug = trim($_GET['category'] ?? '');
$filter_gender = $_GET['gender'] ?? 'all';
$filter_subcat = trim($_GET['subcat'] ?? '');

// Get category by slug
$category = null;
if ($category_slug !== '') {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $category_slug);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$category) {
    redirect('categories.php');
}

// Load subcategories for this category
$subcategories = [];
$stmt = $conn->prepare("SELECT id, name, slug FROM subcategories WHERE category_id = ? AND deleted_at IS NULL ORDER BY sort_order, name");
$stmt->bind_param("i", $category['id']);
$stmt->execute();
$subcategories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Resolve selected subcategory slug to id (must belong to this category)
$selected_subcat = null;
$selected_subcat_id = null;
if ($filter_subcat !== '') {
    foreach ($subcategories as $sc) {
        if (($sc['slug'] ?? '') === $filter_subcat) {
            $selected_subcat = $sc;
            $selected_subcat_id = (int)$sc['id'];
            break;
        }
    }
    if (!$selected_subcat) {
        $filter_subcat = '';
    }
}

// Build query: show active products (or status IS NULL for older rows without status column set)
$query = "SELECT * FROM products WHERE category_id = ? AND deleted_at IS NULL AND (status = 'active' OR status IS NULL)";
$params = [$category['id']];
$types = "i";

if ($selected_subcat_id) {
    $query .= " AND subcategory_id = ?";
    $params[] = $selected_subcat_id;
    $types .= "i";
}

if ($filter_gender != 'all') {
    $query .= " AND subcategory = ?";
    $params[] = $filter_gender;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
}
else {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// Ensure every product has a slug for SEO URLs (auto-fill if missing)
$slug_col = $conn->query("SHOW COLUMNS FROM products LIKE 'slug'");
if ($slug_col && $slug_col->num_rows > 0) {
    foreach ($products as $i => $p) {
        if (empty($p['slug']) && !empty($p['name'])) {
            $gen = slugify($p['name']);
            $uniq = $gen;
            $n = 0;
            while ($conn->query("SELECT id FROM products WHERE slug = '" . $conn->real_escape_string($uniq) . "' AND id != " . (int)$p['id'])->num_rows > 0) {
                $n++;
                $uniq = $gen . '-' . $n;
            }
            $conn->query("UPDATE products SET slug = '" . $conn->real_escape_string($uniq) . "' WHERE id = " . (int)$p['id']);
            $products[$i]['slug'] = $uniq;
        }
    }
}
$conn->close();
?>
    <main class="products-page">
        <!-- Filter Sidebar -->
        <aside class="products-sidebar" id="productsSidebar">
            <div class="sidebar-header">
                <h3>Filter by</h3>
                <button class="sidebar-close" id="productsSidebarClose" aria-label="Close filters">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="sidebar-content">
                <?php if (!empty($subcategories)): ?>
                    <div class="filter-group">
                        <h4>Subcategories</h4>
                        <a href="?category=<?php echo urlencode($category_slug); ?><?php echo($filter_gender !== 'all') ? '&gender=' . urlencode($filter_gender) : ''; ?>" class="filter-link <?php echo $filter_subcat === '' ? 'active' : ''; ?>">
                            All
                        </a>
                        <?php foreach ($subcategories as $sc): ?>
                            <a href="?category=<?php echo urlencode($category_slug); ?>&subcat=<?php echo urlencode($sc['slug']); ?><?php echo($filter_gender !== 'all') ? '&gender=' . urlencode($filter_gender) : ''; ?>" class="filter-link <?php echo $filter_subcat === ($sc['slug'] ?? '') ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($sc['name']); ?>
                            </a>
                        <?php
    endforeach; ?>
                    </div>
                <?php
endif; ?>
                <div class="filter-group">
                    <h4>Gender</h4>
                    <a href="?category=<?php echo urlencode($category_slug); ?><?php echo($filter_subcat !== '') ? '&subcat=' . urlencode($filter_subcat) : ''; ?>&gender=all" class="filter-link <?php echo $filter_gender == 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?category=<?php echo urlencode($category_slug); ?><?php echo($filter_subcat !== '') ? '&subcat=' . urlencode($filter_subcat) : ''; ?>&gender=male" class="filter-link <?php echo $filter_gender == 'male' ? 'active' : ''; ?>">
                        Male
                    </a>
                    <a href="?category=<?php echo urlencode($category_slug); ?><?php echo($filter_subcat !== '') ? '&subcat=' . urlencode($filter_subcat) : ''; ?>&gender=female" class="filter-link <?php echo $filter_gender == 'female' ? 'active' : ''; ?>">
                        Female
                    </a>
                    <a href="?category=<?php echo urlencode($category_slug); ?><?php echo($filter_subcat !== '') ? '&subcat=' . urlencode($filter_subcat) : ''; ?>&gender=unisex" class="filter-link <?php echo $filter_gender == 'unisex' ? 'active' : ''; ?>">
                        Unisex
                    </a>
                </div>
            </div>
        </aside>

        <!-- Sidebar Overlay -->
        <div class="products-sidebar-overlay" id="productsSidebarOverlay"></div>

        <div class="container">
            <div class="products-layout">
                <div class="products-main">
                    <div class="products-header">
                        <div class="products-header-top">
                            <h1 class="reveal">
                                <?php echo htmlspecialchars($category['name']); ?>
                                <?php if ($selected_subcat): ?>
                                    <span style="font-weight: 400; opacity: 0.85;"> / <?php echo htmlspecialchars($selected_subcat['name']); ?></span>
                                <?php
endif; ?>
                            </h1>
                            <button class="filter-toggle-btn" id="filterToggleBtn" aria-label="Toggle filters">
                                <i class="fas fa-filter"></i>
                                <span>Filters</span>
                            </button>
                        </div>
                        <?php if (!empty($subcategories)): ?>
                            <div class="reveal" data-delay="60" style="display:flex; flex-wrap:wrap; gap:10px; margin-top: 14px;">
                                <a class="filter-link <?php echo $filter_subcat === '' ? 'active' : ''; ?>" style="display:inline-flex; padding: 8px 12px; border-radius: 999px; border: 1px solid #e6e6e6; text-decoration:none;"
                                   href="?category=<?php echo urlencode($category_slug); ?><?php echo($filter_gender !== 'all') ? '&gender=' . urlencode($filter_gender) : ''; ?>">All</a>
                                <?php foreach ($subcategories as $sc): ?>
                                    <a class="filter-link <?php echo $filter_subcat === ($sc['slug'] ?? '') ? 'active' : ''; ?>" style="display:inline-flex; padding: 8px 12px; border-radius: 999px; border: 1px solid #e6e6e6; text-decoration:none;"
                                       href="?category=<?php echo urlencode($category_slug); ?>&subcat=<?php echo urlencode($sc['slug']); ?><?php echo($filter_gender !== 'all') ? '&gender=' . urlencode($filter_gender) : ''; ?>">
                                        <?php echo htmlspecialchars($sc['name']); ?>
                                    </a>
                                <?php
    endforeach; ?>
                            </div>
                        <?php
endif; ?>
                        <p class="products-count reveal" data-delay="100"><?php echo count($products); ?> products found</p>
                    </div>
                    <div class="products-grid-modern stagger">
                        <?php if (empty($products)): ?>
                            <p class="no-products reveal">No products found in this category.</p>
                        <?php
else: ?>
                            <?php foreach ($products as $product): ?>
                                <div class="product-card-modern reveal">
                                    <a href="<?php echo(defined('BASE_PATH') ? BASE_PATH : ''); ?>/product-detail/<?php echo rawurlencode(!empty($product['slug']) ? $product['slug'] : slugify($product['name'] ?? 'product')); ?>" class="product-card-link">
                                        <div class="product-image-wrapper">
                                            <?php if ($product['image']): ?>
                                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-main">
                                            <?php
        else: ?>
                                                <div class="product-image-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php
        endif; ?>
                                            <div class="quick-view-icon" onclick="event.preventDefault(); window.location.href='<?php echo(defined('BASE_PATH') ? BASE_PATH : ''); ?>/product-detail/<?php echo rawurlencode(!empty($product['slug']) ? $product['slug'] : slugify($product['name'] ?? 'product')); ?>'">
                                                <i class="fas fa-search"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="product-card-body">
                                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                            
                                            <div class="product-price-section">
                                                <?php if ($product['price'] && $product['price'] > 0): ?>
                                                    <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                                                <?php
        else: ?>
                                                    <span class="product-price">Contact for Price</span>
                                                <?php
        endif; ?>
                                            </div>
                                            
                                            <div class="product-moq">
                                                <span class="moq-label">Min. order:</span>
                                                <span class="moq-value"><?php echo number_format($product['moq'] ?? 1); ?> <?php echo($product['moq'] ?? 1) > 1 ? 'pieces' : 'piece'; ?></span>
                                            </div>
                                            
                                            <div class="product-seller-info">
                                                <div class="seller-name-section">
                                                    <span class="seller-name"><?php echo SITE_NAME; ?></span>
                                                    <span class="verified-badge" title="Verified Supplier">
                                                        <i class="fas fa-check-circle"></i>
                                                    </span>
                                                </div>
                                                <div class="seller-meta">
                                                    <span class="seller-country">
                                                        <i class="fas fa-flag"></i> PK
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="product-rating">
                                                <div class="stars">
                                                    <?php
        $rating = 4.5; // Default rating, can be fetched from reviews table if available
        $fullStars = floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
        for ($i = 0; $i < $fullStars; $i++) {
            echo '<i class="fas fa-star"></i>';
        }
        if ($hasHalfStar) {
            echo '<i class="fas fa-star-half-alt"></i>';
        }
        for ($i = $fullStars + ($hasHalfStar ? 1 : 0); $i < 5; $i++) {
            echo '<i class="far fa-star"></i>';
        }
?>
                                                </div>
                                                <span class="rating-value"><?php echo number_format($rating, 1); ?>/5.0</span>
                                                <span class="rating-count">(<?php echo rand(5, 50); ?>)</span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php
    endforeach; ?>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .products-grid-modern {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .product-card-modern {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e8e8e8;
        }
        
        .product-card-modern:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .product-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-image-wrapper {
            position: relative;
            width: 100%;
            height: 280px;
            background: #f8f9fa;
            overflow: hidden;
        }
        
        .product-image-main {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card-modern:hover .product-image-main {
            transform: scale(1.05);
        }
        
        .product-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #999;
            font-size: 48px;
        }
        
        .quick-view-icon {
            position: absolute;
            bottom: 12px;
            left: 12px;
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 2;
        }
        
        .product-card-modern:hover .quick-view-icon {
            opacity: 1;
        }
        
        .quick-view-icon:hover {
            background: var(--primary-color);
            color: #fff;
        }
        
        .quick-view-icon i {
            font-size: 16px;
            color: var(--primary-color);
        }
        
        .quick-view-icon:hover i {
            color: #fff;
        }
        
        .product-card-body {
            padding: 10px 14px 12px;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
            margin: 0 0 8px 0;
            /* Let the title wrap to as many lines as needed so it never hides behind the price row */
            height: auto;
            overflow: visible;
            display: block;
        }
        
        .product-price-section {
            margin-bottom: 2px;
        }
        
        .product-price {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .product-moq {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            font-size: 18px;
            color: #666;
        }
        
        .moq-label {
            font-weight: 400;
        }
        
        .moq-value {
            font-weight: 500;
            color: #333;
        }
        
        .product-seller-info {
            padding-top: 4px;
            border-top: 1px solid #f0f0f0;
            margin-bottom: 4px;
        }
        
        .seller-name-section {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }
        
        .seller-name {
            font-size: 13px;
            font-weight: 500;
            color: #333;
        }
        
        .verified-badge {
            color: #1890ff;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
        }
        
        .seller-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #999;
        }
        
        .seller-experience {
            font-weight: 400;
        }
        
        .seller-country {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .seller-country i {
            font-size: 11px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }
        
        .stars {
            display: flex;
            gap: 2px;
            color: #ffc107;
        }
        
        .stars i {
            font-size: 12px;
        }
        
        .rating-value {
            font-weight: 500;
            color: #333;
        }
        
        .rating-count {
            color: #999;
        }
        
        @media (max-width: 768px) {
            .products-grid-modern {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 20px;
            }
            
            .product-image-wrapper {
                height: 240px;
            }
            
            .product-card-body {
                padding: 12px 12px 14px;
            }
            
            .product-title {
                font-size: 15px;
                line-height: 1.3;
            }
            
            .product-price {
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid-modern {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .product-image-wrapper {
                height: 200px;
            }
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('productsSidebar');
            const sidebarToggle = document.getElementById('filterToggleBtn');
            const sidebarClose = document.getElementById('productsSidebarClose');
            const sidebarOverlay = document.getElementById('productsSidebarOverlay');

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

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', openSidebar);
            }
            if (sidebarClose) {
                sidebarClose.addEventListener('click', closeSidebar);
            }
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });
    </script>

<?php require_once 'includes/footer.php'; ?>

