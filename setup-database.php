<?php
/**
 * Database Setup Script
 * This script will check your existing database structure and create missing tables
 * Run this once to set up your database properly
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "<h2>FAYMURE Database Setup</h2>";
echo "<p>Checking and creating missing tables...</p>";

// Check if admins table exists, if not create users table
$result = $conn->query("SHOW TABLES LIKE 'admins'");
if ($result->num_rows == 0) {
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL
        )");
        echo "<p>✓ Created 'users' table</p>";
        
        // Insert default admin
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT IGNORE INTO users (username, email, password, is_admin) VALUES 
            ('admin', 'admin@faymure.com', '$hashed_password', 1)");
        echo "<p>✓ Created default admin user (username: admin, password: admin123)</p>";
    }
}

// Check and update categories table
$result = $conn->query("SHOW TABLES LIKE 'categories'");
if ($result->num_rows > 0) {
    // Check if slug column exists
    $columns = $conn->query("SHOW COLUMNS FROM categories LIKE 'slug'");
    if ($columns->num_rows == 0) {
        $conn->query("ALTER TABLE categories ADD COLUMN slug VARCHAR(100) UNIQUE");
        echo "<p>✓ Added 'slug' column to categories</p>";
    }
} else {
    $conn->query("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        description TEXT,
        image VARCHAR(255),
        parent_id INT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL
    )");
    echo "<p>✓ Created 'categories' table</p>";
    
    // Insert default categories
    $conn->query("INSERT INTO categories (name, slug, description) VALUES
        ('Jackets', 'jackets', 'Premium leather jackets'),
        ('Wallets', 'wallets', 'Quality leather wallets'),
        ('Purses', 'purses', 'Elegant leather purses'),
        ('Travel Bags', 'travel-bags', 'Durable travel bags'),
        ('Gloves', 'gloves', 'Leather gloves'),
        ('Bags', 'bags', 'Various leather bags')");
    echo "<p>✓ Inserted default categories</p>";
}

// Check products table
$result = $conn->query("SHOW TABLES LIKE 'products'");
if ($result->num_rows > 0) {
    // Add missing columns if needed
    $columns_to_add = [
        'slug' => "ALTER TABLE products ADD COLUMN slug VARCHAR(255) UNIQUE",
        'product_details' => "ALTER TABLE products ADD COLUMN product_details TEXT",
        'subcategory' => "ALTER TABLE products ADD COLUMN subcategory VARCHAR(50) DEFAULT 'unisex'",
        'moq' => "ALTER TABLE products ADD COLUMN moq INT DEFAULT 1",
        'images' => "ALTER TABLE products ADD COLUMN images TEXT",
        'status' => "ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'",
        'deleted_at' => "ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL"
    ];
    
    foreach ($columns_to_add as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM products LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query($sql);
            echo "<p>✓ Added '$col' column to products</p>";
        }
    }
} else {
    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        description TEXT,
        product_details TEXT,
        category_id INT NOT NULL,
        subcategory VARCHAR(50) DEFAULT 'unisex',
        moq INT DEFAULT 1,
        price DECIMAL(10,2),
        image VARCHAR(255),
        images TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
    )");
    echo "<p>✓ Created 'products' table</p>";
}

// Check orders/quote_requests table
$result = $conn->query("SHOW TABLES LIKE 'quote_requests'");
if ($result->num_rows > 0) {
    // Use existing quote_requests table
    echo "<p>✓ Found 'quote_requests' table (will be used for orders)</p>";
} else {
    $result = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            product_id INT,
            quantity INT NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_email VARCHAR(100) NOT NULL,
            customer_phone VARCHAR(20),
            message TEXT,
            status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "<p>✓ Created 'orders' table</p>";
    }
}

// Check site_content table
$result = $conn->query("SHOW TABLES LIKE 'site_content'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS site_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content_key VARCHAR(100) UNIQUE NOT NULL,
        content_value TEXT,
        content_type VARCHAR(50) DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Created 'site_content' table</p>";
} else {
    // Check and add missing columns
    $columns_to_add = [
        'content_value' => "ALTER TABLE site_content ADD COLUMN content_value TEXT",
        'content_type' => "ALTER TABLE site_content ADD COLUMN content_type VARCHAR(50) DEFAULT 'text'",
        'content_key' => "ALTER TABLE site_content ADD COLUMN content_key VARCHAR(100) UNIQUE",
        'created_at' => "ALTER TABLE site_content ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE site_content ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    foreach ($columns_to_add as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM site_content LIKE '$col'");
        if ($check->num_rows == 0) {
            try {
                $conn->query($sql);
                echo "<p>✓ Added '$col' column to site_content table</p>";
            } catch (Exception $e) {
                echo "<p>⚠ Could not add '$col' column: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// Insert default content (only if table is empty or missing keys)
$existing_content = $conn->query("SELECT COUNT(*) as count FROM site_content")->fetch_assoc()['count'];
if ($existing_content == 0) {
    $conn->query("INSERT INTO site_content (content_key, content_value, content_type) VALUES
        ('hero_tagline', 'Premium Leather Goods for Every Lifestyle', 'text'),
        ('vision_title', 'Our Vision', 'text'),
        ('vision_text', 'To be the leading provider of premium leather goods worldwide, combining traditional craftsmanship with modern design.', 'text'),
        ('vision_image', 'assets/images/vision.jpg', 'image'),
        ('mission_title', 'Our Mission', 'text'),
        ('mission_text', 'To deliver exceptional quality leather products that exceed customer expectations while maintaining sustainable practices.', 'text'),
        ('mission_image', 'assets/images/mission.jpg', 'image'),
        ('services_title', 'Our Services', 'text'),
        ('services_text', 'We offer custom leather goods, bulk orders, and personalized products tailored to your needs.', 'text'),
        ('services_image', 'assets/images/services.jpg', 'image'),
        ('footer_email', 'contact@faymure.com', 'text'),
        ('footer_phone', '+1 (555) 123-4567', 'text'),
        ('footer_facebook', 'https://facebook.com/faymure', 'url'),
        ('footer_instagram', 'https://instagram.com/faymure', 'url'),
        ('footer_twitter', 'https://twitter.com/faymure', 'url')");
    echo "<p>✓ Inserted default site content</p>";
} else {
    // Insert missing keys only
    $default_content = [
        ['hero_tagline', 'Premium Leather Goods for Every Lifestyle', 'text'],
        ['vision_title', 'Our Vision', 'text'],
        ['vision_text', 'To be the leading provider of premium leather goods worldwide, combining traditional craftsmanship with modern design.', 'text'],
        ['vision_image', 'assets/images/vision.jpg', 'image'],
        ['mission_title', 'Our Mission', 'text'],
        ['mission_text', 'To deliver exceptional quality leather products that exceed customer expectations while maintaining sustainable practices.', 'text'],
        ['mission_image', 'assets/images/mission.jpg', 'image'],
        ['services_title', 'Our Services', 'text'],
        ['services_text', 'We offer custom leather goods, bulk orders, and personalized products tailored to your needs.', 'text'],
        ['services_image', 'assets/images/services.jpg', 'image'],
        ['footer_email', 'contact@faymure.com', 'text'],
        ['footer_phone', '+1 (555) 123-4567', 'text'],
        ['footer_facebook', 'https://facebook.com/faymure', 'url'],
        ['footer_instagram', 'https://instagram.com/faymure', 'url'],
        ['footer_twitter', 'https://twitter.com/faymure', 'url']
    ];
    
    foreach ($default_content as $content) {
        $check = $conn->query("SELECT id FROM site_content WHERE content_key = '" . $conn->real_escape_string($content[0]) . "'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value, content_type) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $content[0], $content[1], $content[2]);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo "<p>✓ Checked and added missing site content keys</p>";
}

// Check reviews table
$result = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(100) NOT NULL,
        review_text TEXT NOT NULL,
        rating INT DEFAULT 5,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL
    )");
    echo "<p>✓ Created 'reviews' table</p>";
    
    // Insert sample reviews
    $conn->query("INSERT INTO reviews (customer_name, review_text, rating) VALUES
        ('John Doe', 'Excellent quality leather jacket! Very satisfied with my purchase.', 5),
        ('Jane Smith', 'Beautiful wallet, great craftsmanship. Highly recommend!', 5),
        ('Mike Johnson', 'The travel bag exceeded my expectations. Durable and stylish.', 5),
        ('Sarah Williams', 'Amazing customer service and premium products.', 5)");
    echo "<p>✓ Inserted sample reviews</p>";
}

$conn->close();

echo "<h3>✓ Database setup complete!</h3>";
echo "<p><a href='index.php'>Go to Homepage</a> | <a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
?>

