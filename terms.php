<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$terms_content = '';

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='terms_content'");
    if ($result && $row = $result->fetch_assoc()) {
        $terms_content = $row['content_value'] ?? '';
    }
}
$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">Terms & Conditions</h1>
            <div class="content-section" style="max-width: 800px; margin: 0 auto;">
                <?php if (!empty($terms_content)): ?>
                    <?php echo $terms_content; ?>
                <?php else: ?>
                    <p>By using our website and placing orders, you agree to our terms and conditions.</p>
                    <p>All products are subject to availability. Prices are subject to change without notice.</p>
                    <p>For custom orders, a deposit may be required before production begins.</p>
                    <p>Please review all order details carefully before confirming your purchase.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

