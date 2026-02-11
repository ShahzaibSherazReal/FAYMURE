<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
    <main class="categories-page">
        <div class="container">
            <h1 class="page-title reveal">Browse Our Categories</h1>
            <div class="categories-grid stagger">
                <?php foreach ($categories as $category): ?>
                    <a href="products.php?category=<?php echo $category['slug']; ?>" class="category-card hover-lift reveal">
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
    </main>

<?php require_once 'includes/footer.php'; ?>

