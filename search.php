<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$query = sanitize($_GET['q'] ?? '');
$products = [];

if ($query) {
    $conn = getDBConnection();
    $search_term = "%$query%";
    $stmt = $conn->prepare("SELECT * FROM products WHERE (name LIKE ? OR description LIKE ?) AND deleted_at IS NULL AND status='active'");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
}
?>
    <main class="products-page">
        <div class="container">
            <h1 class="page-title">Search Results</h1>
            <?php if ($query): ?>
                <p>Search results for: "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>
            <?php endif; ?>
            
            <div class="products-grid" style="margin-top: 30px;">
                <?php if (empty($products)): ?>
                    <p class="no-products">No products found matching your search.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="product-card">
                            <div class="product-image">
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
                                <?php if ($product['price']): ?>
                                    <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

