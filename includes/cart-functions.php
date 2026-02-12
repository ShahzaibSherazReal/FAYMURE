<?php
// Cart functions
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function addToCart($product_id, $quantity = 1) {
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

function removeFromCart($product_id) {
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

function updateCartQuantity($product_id, $quantity) {
    if ($quantity <= 0) {
        removeFromCart($product_id);
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

function getCartCount() {
    $count = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $quantity) {
            $count += $quantity;
        }
    }
    return $count;
}

function getCartTotal($conn) {
    $total = 0;
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        $product_ids = array_keys($_SESSION['cart']);
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $conn->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($product = $result->fetch_assoc()) {
                $quantity = $_SESSION['cart'][$product['id']];
                $total += ($product['price'] ?? 0) * $quantity;
            }
            $stmt->close();
        }
    }
    return $total;
}

function getCartItems($conn) {
    $items = [];
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        $product_ids = array_keys($_SESSION['cart']);
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p 
                                    LEFT JOIN categories c ON p.category_id = c.id 
                                    WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL");
            $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($product = $result->fetch_assoc()) {
                $product['cart_quantity'] = $_SESSION['cart'][$product['id']];
                $items[] = $product;
            }
            $stmt->close();
        }
    }
    return $items;
}
?>

