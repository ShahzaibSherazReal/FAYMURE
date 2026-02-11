<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$returns_content = '';

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='returns_content'");
    if ($result && $row = $result->fetch_assoc()) {
        $returns_content = $row['content_value'] ?? '';
    }
}
$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">Return Policy</h1>
            <div class="content-section" style="max-width: 800px; margin: 0 auto;">
                <?php if (!empty($returns_content)): ?>
                    <?php echo $returns_content; ?>
                <?php else: ?>
                    <p>We accept returns within 30 days of delivery for items in original condition.</p>
                    <p>Custom orders and items made to specifications are not eligible for return unless there is a manufacturing defect.</p>
                    <p>Please contact us before returning any items to receive a return authorization.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

