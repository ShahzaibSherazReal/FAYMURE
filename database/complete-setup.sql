-- FAYMURE Complete Database Setup
-- This script will DROP all existing tables and create fresh ones
-- Run this in phpMyAdmin SQL section

USE faymure;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop all existing tables (in reverse order of dependencies)
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS quote_requests;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS subcategories;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS site_content;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS faqs;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- CREATE TABLES
-- ============================================

-- Users table (for both admin and regular users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Products table
CREATE TABLE products (
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
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_category (category_id),
    INDEX idx_slug (slug),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Orders/Quote Requests table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    message TEXT,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_product (product_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Site content table (for editable content)
CREATE TABLE site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) UNIQUE NOT NULL,
    content_value TEXT,
    content_type VARCHAR(50) DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reviews table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    review_text TEXT NOT NULL,
    rating INT DEFAULT 5,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Insert default admin user (password: admin123)
-- Note: The password hash below is a placeholder. 
-- Run fix-admin.php after importing this SQL to set the correct password hash.
-- Or use: UPDATE users SET password = '$2y$10$YourHashHere' WHERE username = 'admin';
INSERT INTO users (username, email, password, is_admin) VALUES 
('admin', 'admin@faymure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE password = VALUES(password), is_admin = 1;

-- Insert default categories
INSERT INTO categories (name, slug, description, sort_order) VALUES
('Jackets', 'jackets', 'Premium leather jackets for all occasions', 1),
('Wallets', 'wallets', 'Quality leather wallets in various styles', 2),
('Purses', 'purses', 'Elegant leather purses and handbags', 3),
('Travel Bags', 'travel-bags', 'Durable and stylish travel bags', 4),
('Gloves', 'gloves', 'Premium leather gloves', 5),
('Bags', 'bags', 'Various leather bags and accessories', 6);

-- Insert default site content
INSERT INTO site_content (content_key, content_value, content_type) VALUES
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
('footer_twitter', 'https://twitter.com/faymure', 'url');

-- Insert sample reviews
INSERT INTO reviews (customer_name, review_text, rating, status) VALUES
('John Doe', 'Excellent quality leather jacket! Very satisfied with my purchase. The craftsmanship is outstanding.', 5, 'active'),
('Jane Smith', 'Beautiful wallet, great craftsmanship. Highly recommend! The leather feels premium and durable.', 5, 'active'),
('Mike Johnson', 'The travel bag exceeded my expectations. Durable and stylish. Perfect for my business trips.', 5, 'active'),
('Sarah Williams', 'Amazing customer service and premium products. Will definitely order again!', 5, 'active'),
('David Brown', 'Great quality and fast delivery. The leather products are exactly as described.', 5, 'active'),
('Emily Davis', 'Love my new purse! The design is elegant and the quality is top-notch.', 5, 'active');

-- ============================================
-- VERIFICATION QUERIES (Optional - to check setup)
-- ============================================

-- Check tables created
-- SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'faymure';

-- Check admin user
-- SELECT id, username, email, is_admin FROM users WHERE username = 'admin';

-- Check categories
-- SELECT id, name, slug FROM categories;

-- Check site content
-- SELECT content_key, content_value FROM site_content LIMIT 5;

-- Check reviews
-- SELECT COUNT(*) as review_count FROM reviews WHERE status = 'active';

