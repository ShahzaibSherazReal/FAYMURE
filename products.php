<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$category_slug = $_GET['category'] ?? '';
$filter_gender = $_GET['gender'] ?? 'all';

// Get category
$category = null;
if ($category_slug) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $category_slug);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$category) {
    redirect('categories.php');
}

// Build query
$query = "SELECT * FROM products WHERE category_id = ? AND deleted_at IS NULL AND status = 'active'";
$params = [$category['id']];
$types = "i";

if ($filter_gender != 'all') {
    $query .= " AND subcategory = ?";
    $params[] = $filter_gender;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
                <div class="filter-group">
                    <h4>Gender</h4>
                    <a href="?category=<?php echo $category_slug; ?>&gender=all" class="filter-link <?php echo $filter_gender == 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?category=<?php echo $category_slug; ?>&gender=male" class="filter-link <?php echo $filter_gender == 'male' ? 'active' : ''; ?>">
                        Male
                    </a>
                    <a href="?category=<?php echo $category_slug; ?>&gender=female" class="filter-link <?php echo $filter_gender == 'female' ? 'active' : ''; ?>">
                        Female
                    </a>
                    <a href="?category=<?php echo $category_slug; ?>&gender=unisex" class="filter-link <?php echo $filter_gender == 'unisex' ? 'active' : ''; ?>">
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
                            <h1 class="reveal"><?php echo htmlspecialchars($category['name']); ?></h1>
                            <button class="filter-toggle-btn" id="filterToggleBtn" aria-label="Toggle filters">
                                <i class="fas fa-filter"></i>
                                <span>Filters</span>
                            </button>
                        </div>
                        <p class="products-count reveal" data-delay="100"><?php echo count($products); ?> products found</p>
                    </div>
                    <div class="products-grid stagger">
                        <?php if (empty($products)): ?>
                            <p class="no-products reveal">No products found in this category.</p>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="product-card hover-lift reveal">
                                    <div class="product-image img-zoom">
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="placeholder-image">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <p class="product-subcategory"><?php echo ucfirst($product['subcategory']); ?></p>
                                        <?php if ($product['price']): ?>
                                            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

