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
        'deleted_at' => "ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL",
        'sku' => "ALTER TABLE products ADD COLUMN sku VARCHAR(100) DEFAULT NULL",
        'key_features' => "ALTER TABLE products ADD COLUMN key_features TEXT",
        'specifications' => "ALTER TABLE products ADD COLUMN specifications TEXT",
        'color_swatches' => "ALTER TABLE products ADD COLUMN color_swatches TEXT DEFAULT NULL"
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
        sku VARCHAR(100) DEFAULT NULL,
        key_features TEXT,
        specifications TEXT,
        color_swatches TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
    )");
    echo "<p>✓ Created 'products' table</p>";
}

// ----- Visitor activity tracking -----
$result = $conn->query("SHOW TABLES LIKE 'visitor_profiles'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE visitor_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guest_id VARCHAR(64) NOT NULL UNIQUE,
        user_id INT NULL,
        first_seen_at DATETIME NULL,
        last_seen_at DATETIME NULL,
        first_ip VARCHAR(45) NULL,
        last_ip VARCHAR(45) NULL,
        first_user_agent VARCHAR(500) NULL,
        last_user_agent VARCHAR(500) NULL,
        device_type VARCHAR(20) NULL,
        browser VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        referrer VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_user_id (user_id),
        CONSTRAINT fk_visitor_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'visitor_profiles' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'visitor_sessions'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE visitor_sessions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        session_id CHAR(36) NOT NULL UNIQUE,
        guest_id VARCHAR(64) NOT NULL,
        user_id INT NULL,
        started_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        referrer VARCHAR(500) NULL,
        landing_url VARCHAR(500) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        device_type VARCHAR(20) NULL,
        browser VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_guest (guest_id),
        KEY idx_user (user_id),
        KEY idx_last_seen (last_seen_at),
        CONSTRAINT fk_visitor_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'visitor_sessions' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'visitor_events'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE visitor_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        guest_id VARCHAR(64) NOT NULL,
        user_id INT NULL,
        session_id CHAR(36) NULL,
        event_type VARCHAR(50) NOT NULL,
        page_url VARCHAR(500) NULL,
        page_path VARCHAR(500) NULL,
        product_id INT NULL,
        category_id INT NULL,
        search_term VARCHAR(255) NULL,
        button_name VARCHAR(100) NULL,
        referrer VARCHAR(500) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        device_type VARCHAR(20) NULL,
        browser VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        duration_seconds INT NOT NULL DEFAULT 0,
        metadata_json JSON NULL,
        created_at DATETIME NOT NULL,
        KEY idx_created_at (created_at),
        KEY idx_event_type (event_type),
        KEY idx_guest_id (guest_id),
        KEY idx_user_id (user_id),
        KEY idx_session_id (session_id),
        KEY idx_product_id (product_id),
        KEY idx_category_id (category_id),
        CONSTRAINT fk_visitor_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'visitor_events' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'daily_analytics_summary'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE daily_analytics_summary (
        day DATE NOT NULL PRIMARY KEY,
        total_visitors INT NOT NULL DEFAULT 0,
        anonymous_visitors INT NOT NULL DEFAULT 0,
        logged_in_visitors INT NOT NULL DEFAULT 0,
        page_views INT NOT NULL DEFAULT 0,
        product_views INT NOT NULL DEFAULT 0,
        add_to_cart INT NOT NULL DEFAULT 0,
        searches INT NOT NULL DEFAULT 0,
        checkout_started INT NOT NULL DEFAULT 0,
        checkout_completed INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'daily_analytics_summary' table</p>";
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
        ('footer_email', 'info@faymure.com', 'text'),
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
        ['footer_email', 'info@faymure.com', 'text'],
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

// Catalog tables (Explore, Manufacturing, Home - title, tagline, image per item)
foreach (['catalog_explore', 'catalog_manufacturing', 'catalog_home'] as $tbl) {
    $result = $conn->query("SHOW TABLES LIKE '$tbl'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE $tbl (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            tagline VARCHAR(500) DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            link_url VARCHAR(500) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL
        )");
        echo "<p>✓ Created '$tbl' table</p>";
    }
}

// ----- Blog module tables -----
$result = $conn->query("SHOW TABLES LIKE 'blog_authors'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_authors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        bio TEXT,
        avatar_url VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_authors' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'blog_categories'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_categories' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'blog_tags'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_tags' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'blog_posts'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(200) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        excerpt TEXT,
        featured_image VARCHAR(500) DEFAULT NULL,
        author_id INT DEFAULT NULL,
        reading_time_minutes INT DEFAULT NULL,
        is_featured TINYINT(1) DEFAULT 0,
        status ENUM('draft','published') DEFAULT 'published',
        meta_title VARCHAR(255) DEFAULT NULL,
        meta_description VARCHAR(500) DEFAULT NULL,
        content_blocks JSON DEFAULT NULL,
        published_at TIMESTAMP NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES blog_authors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_posts' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'blog_post_categories'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_post_categories (
        post_id INT NOT NULL,
        category_id INT NOT NULL,
        PRIMARY KEY (post_id, category_id),
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_post_categories' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'blog_post_tags'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_post_tags (
        post_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (post_id, tag_id),
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_post_tags' table</p>";
}

$result = $conn->query("SHOW TABLES LIKE 'blog_post_images'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE blog_post_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        caption VARCHAR(500) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'blog_post_images' table</p>";
}

// Optional: insert one sample blog author/category/tag/post if blog is empty
$check = $conn->query("SELECT 1 FROM blog_posts LIMIT 1");
if ($check && $check->num_rows == 0) {
    $conn->query("INSERT IGNORE INTO blog_authors (name, slug, bio) VALUES ('FAYMURE Team', 'faymure-team', 'The FAYMURE editorial team.')");
    $r = $conn->query("SELECT id FROM blog_authors WHERE slug = 'faymure-team' LIMIT 1");
    $aid = ($r && $row = $r->fetch_assoc()) ? (int)$row['id'] : 0;
    $conn->query("INSERT IGNORE INTO blog_categories (slug, name, description) VALUES ('leather-care', 'Leather Care', 'Tips and guides for caring for leather goods.')");
    $r = $conn->query("SELECT id FROM blog_categories WHERE slug = 'leather-care' LIMIT 1");
    $cid = ($r && $row = $r->fetch_assoc()) ? (int)$row['id'] : 0;
    $conn->query("INSERT IGNORE INTO blog_tags (slug, name) VALUES ('aniline', 'Aniline')");
    $r = $conn->query("SELECT id FROM blog_tags WHERE slug = 'aniline' LIMIT 1");
    $tid = ($r && $row = $r->fetch_assoc()) ? (int)$row['id'] : 0;
    if ($aid && $cid && $tid) {
        $blocks = '[{"type":"paragraph","content":"Leather is a durable material that lasts for years when cared for properly. Here are essential tips to keep your leather goods looking their best."},{"type":"heading","level":2,"content":"Conditioning"},{"type":"paragraph","content":"Use a quality leather conditioner every few months. Apply in a circular motion and wipe off excess."},{"type":"quote","content":"A well-maintained leather piece ages beautifully."},{"type":"list","items":["Avoid direct sunlight","Keep away from moisture","Store in a cool, dry place"]}]';
        $blocks_esc = $conn->real_escape_string($blocks);
        $conn->query("INSERT IGNORE INTO blog_posts (slug, title, excerpt, author_id, reading_time_minutes, is_featured, status, meta_title, meta_description, content_blocks, published_at) VALUES (
            'how-to-care-for-leather',
            'How to Care for Leather',
            'Essential tips to maintain and protect your leather goods.',
            $aid, 3, 1, 'published',
            'How to Care for Leather | FAYMURE Blog',
            'Essential tips to maintain and protect your leather goods. Conditioning, storage, and daily care.',
            '" . $blocks_esc . "',
            NOW()
        )");
        $r = $conn->query("SELECT id FROM blog_posts WHERE slug = 'how-to-care-for-leather' LIMIT 1");
        $pid = ($r && $row = $r->fetch_assoc()) ? (int)$row['id'] : 0;
        if ($pid) {
            $conn->query("INSERT IGNORE INTO blog_post_categories (post_id, category_id) VALUES ($pid, $cid)");
            $conn->query("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES ($pid, $tid)");
        }
    }
    echo "<p>✓ Checked sample blog data (author, category, tag, post)</p>";
}

// Visitor logs (for user activity in admin)
$result = $conn->query("SHOW TABLES LIKE 'visitor_logs'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE visitor_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_code VARCHAR(64) NOT NULL,
        user_id INT NULL,
        user_name VARCHAR(100) NULL,
        country VARCHAR(100) DEFAULT NULL,
        page_path VARCHAR(500) NOT NULL,
        visit_count INT NOT NULL DEFAULT 0,
        total_time_seconds INT NOT NULL DEFAULT 0,
        last_duration_seconds INT NOT NULL DEFAULT 0,
        first_visited_at DATETIME DEFAULT NULL,
        last_visited_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_visitor_page (visitor_code, page_path(191)),
        KEY idx_user_id (user_id),
        CONSTRAINT fk_visitor_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ Created 'visitor_logs' table</p>";
}

$conn->close();

echo "<h3>✓ Database setup complete!</h3>";
echo "<p><a href='index.php'>Go to Homepage</a> | <a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
?>

