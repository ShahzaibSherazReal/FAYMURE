<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$shipping_content = '';

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='shipping_content'");
    if ($result && $row = $result->fetch_assoc()) {
        $shipping_content = $row['content_value'] ?? '';
    }
}
$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">Shipping Information</h1>
            <div class="content-section" style="max-width: 800px; margin: 0 auto;">
                <?php if (!empty($shipping_content)): ?>
                    <?php echo $shipping_content; ?>
                <?php else: ?>
                    <p>We ship worldwide. Shipping costs and delivery times vary by location and order size.</p>
                    <p>Standard shipping typically takes 5-10 business days. Express shipping options are available upon request.</p>
                    <p>For bulk orders, please contact us for custom shipping arrangements.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

