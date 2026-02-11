<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$product_id = $_GET['id'] ?? 0;
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.id = ? AND p.deleted_at IS NULL");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    redirect('categories.php');
}

$images = [];
if ($product['images']) {
    $images = json_decode($product['images'], true) ?: [];
}
if ($product['image']) {
    array_unshift($images, $product['image']);
}

$conn->close();
?>
    <main class="product-detail-page">
        <div class="container">
            <div class="product-detail-layout">
                <div class="product-images reveal">
                    <div class="main-image img-zoom">
                        <img src="<?php echo htmlspecialchars($images[0] ?? 'assets/images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainImage">
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($images as $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Thumbnail" onclick="changeImage('<?php echo htmlspecialchars($img); ?>')" class="img-zoom">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h1 class="reveal"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="product-category">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
                    <p class="product-subcategory">Type: <?php echo ucfirst($product['subcategory']); ?></p>
                    
                    <?php if ($product['description']): ?>
                        <div class="product-description">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($product['product_details']): ?>
                        <div class="product-details">
                            <h3>Product Details</h3>
                            <div class="details-content">
                                <?php echo nl2br(htmlspecialchars($product['product_details'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="product-moq">
                        <h3>Minimum Order Quantity (MOQ)</h3>
                        <p class="moq-value"><?php echo $product['moq']; ?> units</p>
                    </div>

                    <?php if ($product['price']): ?>
                        <div class="product-price">
                            <h3>Price</h3>
                            <p class="price-value">$<?php echo number_format($product['price'], 2); ?></p>
                        </div>
                    <?php endif; ?>

                    <a href="contact-form.php?product_id=<?php echo $product['id']; ?>" class="btn-contact btn-press">
                        <i class="fas fa-envelope"></i> Get in Contact / Get Quote
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        function changeImage(src) {
            document.getElementById('mainImage').src = src;
        }
    </script>

<?php require_once 'includes/footer.php'; ?>

