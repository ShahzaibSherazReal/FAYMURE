<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$faqs = [];

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='faq_items'");
    if ($result && $row = $result->fetch_assoc()) {
        $faqs = json_decode($row['content_value'], true) ?: [];
    }
}

// Default FAQs if none in database
if (empty($faqs)) {
    $faqs = [
        ['question' => 'What is the minimum order quantity?', 'answer' => 'The minimum order quantity (MOQ) varies by product. Please check the product detail page for specific MOQ information.'],
        ['question' => 'Do you offer custom designs?', 'answer' => 'Yes, we offer custom leather goods tailored to your specifications. Please contact us to discuss your requirements.'],
        ['question' => 'What is your production time?', 'answer' => 'Production time depends on the order quantity and complexity. Typically, orders are completed within 2-4 weeks.']
    ];
}

$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">Frequently Asked Questions</h1>
            <div class="faq-section" style="max-width: 800px; margin: 0 auto;">
                <?php foreach ($faqs as $faq): ?>
                    <div class="faq-item" style="margin-bottom: 20px; padding: 20px; background: var(--light-color); border-radius: 10px;">
                        <h3><?php echo htmlspecialchars($faq['question']); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
<?php require_once 'includes/footer.php'; ?>

