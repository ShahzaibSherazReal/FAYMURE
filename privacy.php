<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$privacy_content = '';

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='privacy_content'");
    if ($result && $row = $result->fetch_assoc()) {
        $privacy_content = $row['content_value'] ?? '';
    }
}
$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">Privacy Policy</h1>
            <div class="content-section" style="max-width: 800px; margin: 0 auto;">
                <?php if (!empty($privacy_content)): ?>
                    <?php echo $privacy_content; ?>
                <?php else: ?>
                    <p>Your privacy is important to us. We collect and use your personal information only to process orders and provide customer service.</p>
                    <p>We do not sell or share your information with third parties without your consent.</p>
                    <p>For more details, please contact us directly.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

