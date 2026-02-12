<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $reviewer_name = sanitize($_POST['reviewer_name'] ?? '');
    $reviewer_email = sanitize($_POST['reviewer_email'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = sanitize($_POST['review_text'] ?? '');
    
    if ($product_id && $reviewer_name && $reviewer_email && $rating >= 1 && $rating <= 5 && $review_text) {
        $conn = getDBConnection();
        
        // Check if product_reviews table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'product_reviews'");
        if (!$table_check || $table_check->num_rows == 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS product_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                reviewer_name VARCHAR(100) NOT NULL,
                reviewer_email VARCHAR(100) NOT NULL,
                rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                review_text TEXT NOT NULL,
                approved TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )");
        }
        
        $stmt = $conn->prepare("INSERT INTO product_reviews (product_id, reviewer_name, reviewer_email, rating, review_text, approved) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("issis", $product_id, $reviewer_name, $reviewer_email, $rating, $review_text);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to submit review. Please try again.";
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

if ($success) {
    $product_id = intval($_POST['product_id'] ?? 0);
    header('Location: product-detail.php?id=' . $product_id . '&review_submitted=1');
    exit;
}

if ($error) {
    $product_id = intval($_POST['product_id'] ?? 0);
    header('Location: product-detail.php?id=' . $product_id . '&error=' . urlencode($error));
    exit;
}
?>

