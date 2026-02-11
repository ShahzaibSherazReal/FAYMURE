<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$email = 'contact@faymure.com';
$phone = '+1 (555) 123-4567';
$address = '';

// Check if content_value column exists
$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='contact_email'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $email = $row['content_value'];
    } else {
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_email'");
        if ($result && $row = $result->fetch_assoc()) {
            $email = $row['content_value'] ?? $email;
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='contact_phone'");
    if ($result && $row = $result->fetch_assoc() && !empty($row['content_value'])) {
        $phone = $row['content_value'];
    } else {
        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_phone'");
        if ($result && $row = $result->fetch_assoc()) {
            $phone = $row['content_value'] ?? $phone;
        }
    }
    
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='contact_address'");
    if ($result && $row = $result->fetch_assoc()) {
        $address = $row['content_value'] ?? '';
    }
}
$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">Contact Us</h1>
            <div class="contact-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin: 40px 0;">
                <div class="contact-card" style="background: var(--light-color); padding: 30px; border-radius: 10px;">
                    <h3><i class="fas fa-envelope"></i> Email</h3>
                    <p><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></p>
                </div>
                <div class="contact-card" style="background: var(--light-color); padding: 30px; border-radius: 10px;">
                    <h3><i class="fas fa-phone"></i> Phone</h3>
                    <p><a href="tel:<?php echo $phone; ?>"><?php echo $phone; ?></a></p>
                </div>
                <?php if (!empty($address)): ?>
                <div class="contact-card" style="background: var(--light-color); padding: 30px; border-radius: 10px;">
                    <h3><i class="fas fa-map-marker-alt"></i> Address</h3>
                    <p><?php echo nl2br(htmlspecialchars($address)); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

