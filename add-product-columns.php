<?php
/**
 * One-time migration: add sku, key_features, specifications to products table.
 * Run once in browser: http://localhost/FAYMURE/add-product-columns.php
 * Delete this file after running.
 */
require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

$columns_to_add = [
    'sku' => "ALTER TABLE products ADD COLUMN sku VARCHAR(100) DEFAULT NULL",
    'key_features' => "ALTER TABLE products ADD COLUMN key_features TEXT",
    'specifications' => "ALTER TABLE products ADD COLUMN specifications TEXT"
];

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Add product columns</h1><pre>";

foreach ($columns_to_add as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM products LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        if ($conn->query($sql)) {
            echo "✓ Added column: $col\n";
        } else {
            echo "✗ Error adding $col: " . $conn->error . "\n";
        }
    } else {
        echo "- Column '$col' already exists\n";
    }
}

echo "</pre><p>Done. You can delete this file (add-product-columns.php) now.</p>";
$conn->close();
