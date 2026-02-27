-- Blog Admin Panel - Separate schema (run once)
-- Uses same blog_* content tables; adds admin users, audit, media, revisions, views.

-- 1) Blog admin users (isolated from main site admin)
CREATE TABLE IF NOT EXISTS blog_admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    role ENUM('admin','editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Audit log (who did what, when)
CREATE TABLE IF NOT EXISTS blog_admin_audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    username VARCHAR(80) DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    old_value TEXT,
    new_value TEXT,
    ip VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created (created_at),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Media library (uploads with thumb/medium/large)
CREATE TABLE IF NOT EXISTS blog_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(500) NOT NULL,
    file_name_original VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED DEFAULT 0,
    width INT UNSIGNED DEFAULT NULL,
    height INT UNSIGNED DEFAULT NULL,
    thumb_path VARCHAR(500) DEFAULT NULL,
    medium_path VARCHAR(500) DEFAULT NULL,
    large_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Post revisions (history)
CREATE TABLE IF NOT EXISTS blog_post_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT,
    content_blocks JSON,
    meta_title VARCHAR(255),
    meta_description VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    KEY idx_post (post_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) View count per post (analytics placeholder)
CREATE TABLE IF NOT EXISTS blog_post_views (
    post_id INT UNSIGNED NOT NULL PRIMARY KEY,
    view_count INT UNSIGNED DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Extend blog_posts for SEO and scheduling (run only if columns missing)
-- Add scheduled_at, og_*, robots_noindex; extend status to include 'scheduled'
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS scheduled_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS og_title VARCHAR(255) DEFAULT NULL;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS og_description VARCHAR(500) DEFAULT NULL;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS og_image VARCHAR(500) DEFAULT NULL;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS robots_noindex TINYINT(1) DEFAULT 0;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(500) DEFAULT NULL;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED DEFAULT NULL;

-- MySQL 8.0.12+ supports IF NOT EXISTS for ADD COLUMN; for older MySQL use:
-- Check and add columns (example for one column; repeat for others or use a script):
-- SET @dbname = DATABASE();
-- SET @tablename = 'blog_posts';
-- SET @columnname = 'scheduled_at';
-- SET @preparedStatement = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0, 'SELECT 1', CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TIMESTAMP NULL')));
-- PREPARE alterIfNotExists FROM @preparedStatement;
-- EXECUTE alterIfNotExists;

-- If your MySQL doesn't support ADD COLUMN IF NOT EXISTS, run these manually (skip if column exists):
-- ALTER TABLE blog_posts ADD COLUMN scheduled_at TIMESTAMP NULL DEFAULT NULL;
-- ALTER TABLE blog_posts ADD COLUMN og_title VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE blog_posts ADD COLUMN og_description VARCHAR(500) DEFAULT NULL;
-- ALTER TABLE blog_posts ADD COLUMN og_image VARCHAR(500) DEFAULT NULL;
-- ALTER TABLE blog_posts ADD COLUMN robots_noindex TINYINT(1) DEFAULT 0;
-- ALTER TABLE blog_posts ADD COLUMN canonical_url VARCHAR(500) DEFAULT NULL;
-- ALTER TABLE blog_posts ADD COLUMN created_by INT UNSIGNED DEFAULT NULL;

-- Modify status enum to include 'scheduled' (run once; may need to adjust if you have existing data)
-- ALTER TABLE blog_posts MODIFY COLUMN status ENUM('draft','published','scheduled') DEFAULT 'draft';

-- 7) Homepage settings (featured post IDs, sections config)
CREATE TABLE IF NOT EXISTS blog_admin_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default blog admin user: username faymureblogadmin, password BlogAdmin123
-- Password hash generated with password_hash('BlogAdmin123', PASSWORD_DEFAULT)
-- Run this after creating blog_admin_users (replace hash with result of password_hash in PHP if needed)
INSERT INTO blog_admin_users (username, password_hash, role) VALUES
('faymureblogadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE username = username;
-- Note: The hash above is for 'password'. Generate correct one in PHP: echo password_hash('BlogAdmin123', PASSWORD_DEFAULT);
-- Then run: UPDATE blog_admin_users SET password_hash = 'YOUR_HASH' WHERE username = 'faymureblogadmin';
