-- FAYMURE Database Schema

CREATE DATABASE IF NOT EXISTS faymure_db;
USE faymure_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, is_admin) VALUES 
('admin', 'admin@faymure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Insert default categories
INSERT INTO categories (name, slug, description) VALUES
('Jackets', 'jackets', 'Premium leather jackets'),
('Wallets', 'wallets', 'Quality leather wallets'),
('Purses', 'purses', 'Elegant leather purses'),
('Travel Bags', 'travel-bags', 'Durable travel bags'),
('Gloves', 'gloves', 'Leather gloves'),
('Bags', 'bags', 'Various leather bags');

-- Products table
CREATE TABLE IF NOT EXISTS products (
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
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Site content table
CREATE TABLE IF NOT EXISTS site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) UNIQUE NOT NULL,
    content_value TEXT,
    content_type VARCHAR(50) DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

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

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    review_text TEXT NOT NULL,
    rating INT DEFAULT 5,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Insert sample reviews
INSERT INTO reviews (customer_name, review_text, rating) VALUES
('John Doe', 'Excellent quality leather jacket! Very satisfied with my purchase.', 5),
('Jane Smith', 'Beautiful wallet, great craftsmanship. Highly recommend!', 5),
('Mike Johnson', 'The travel bag exceeded my expectations. Durable and stylish.', 5),
('Sarah Williams', 'Amazing customer service and premium products.', 5);

