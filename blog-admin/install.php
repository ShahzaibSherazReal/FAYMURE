<?php
/**
 * Blog Admin – one-time install: creates tables, adds columns, inserts default user.
 * Run once: /blog-admin/install.php (then delete or protect this file).
 */
require_once dirname(__DIR__) . '/config/database.php';

$conn = getDBConnection();

// Default credentials (hard requirement)
$default_username = 'faymureblogadmin';
$default_password = 'BlogAdmin123';
$password_hash = password_hash($default_password, PASSWORD_DEFAULT);

echo "<h1>Blog Admin Install</h1>";

// 1) blog_admin_users
$conn->query("CREATE TABLE IF NOT EXISTS blog_admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    role ENUM('admin','editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_admin_users</p>";

// 2) audit log
$conn->query("CREATE TABLE IF NOT EXISTS blog_admin_audit_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_admin_audit_log</p>";

// 3) blog_media
$conn->query("CREATE TABLE IF NOT EXISTS blog_media (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_media</p>";

// 4) revisions
$conn->query("CREATE TABLE IF NOT EXISTS blog_post_revisions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_post_revisions</p>";

// 5) view count
$conn->query("CREATE TABLE IF NOT EXISTS blog_post_views (
    post_id INT UNSIGNED NOT NULL PRIMARY KEY,
    view_count INT UNSIGNED DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_post_views</p>";

// 6) settings
$conn->query("CREATE TABLE IF NOT EXISTS blog_admin_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_admin_settings</p>";

// 7) Create blog content tables if missing (IF NOT EXISTS so we never fail when tables exist)
$conn->query("CREATE TABLE IF NOT EXISTS blog_authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    bio TEXT,
    avatar_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_authors</p>";
$conn->query("CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_categories</p>";
$conn->query("CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_tags</p>";

$r = $conn->query("SHOW TABLES LIKE 'blog_posts'");
$blog_posts_exists = $r && $r->num_rows > 0;
if (!$blog_posts_exists) {
    $conn->query("CREATE TABLE blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(200) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        excerpt TEXT,
        featured_image VARCHAR(500) DEFAULT NULL,
        author_id INT DEFAULT NULL,
        reading_time_minutes INT DEFAULT NULL,
        is_featured TINYINT(1) DEFAULT 0,
        status ENUM('draft','published','scheduled') DEFAULT 'draft',
        meta_title VARCHAR(255) DEFAULT NULL,
        meta_description VARCHAR(500) DEFAULT NULL,
        content_blocks JSON DEFAULT NULL,
        published_at TIMESTAMP NULL,
        scheduled_at TIMESTAMP NULL DEFAULT NULL,
        og_title VARCHAR(255) DEFAULT NULL,
        og_description VARCHAR(500) DEFAULT NULL,
        og_image VARCHAR(500) DEFAULT NULL,
        robots_noindex TINYINT(1) DEFAULT 0,
        canonical_url VARCHAR(500) DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES blog_authors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✓ blog_posts</p>";
}
$conn->query("CREATE TABLE IF NOT EXISTS blog_post_categories (
    post_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (post_id, category_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_post_categories</p>";
$conn->query("CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_post_tags</p>";
$conn->query("CREATE TABLE IF NOT EXISTS blog_post_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    caption VARCHAR(500) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ blog_post_images</p>";

$cols = [];
$r = $conn->query("SHOW COLUMNS FROM blog_posts");
if ($r) while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];

// 8) Add columns to blog_posts if missing (when table already existed)
$add = [
    'scheduled_at' => "ALTER TABLE blog_posts ADD COLUMN scheduled_at TIMESTAMP NULL DEFAULT NULL",
    'og_title' => "ALTER TABLE blog_posts ADD COLUMN og_title VARCHAR(255) DEFAULT NULL",
    'og_description' => "ALTER TABLE blog_posts ADD COLUMN og_description VARCHAR(500) DEFAULT NULL",
    'og_image' => "ALTER TABLE blog_posts ADD COLUMN og_image VARCHAR(500) DEFAULT NULL",
    'robots_noindex' => "ALTER TABLE blog_posts ADD COLUMN robots_noindex TINYINT(1) DEFAULT 0",
    'canonical_url' => "ALTER TABLE blog_posts ADD COLUMN canonical_url VARCHAR(500) DEFAULT NULL",
    'created_by' => "ALTER TABLE blog_posts ADD COLUMN created_by INT UNSIGNED DEFAULT NULL",
];
foreach ($add as $col => $sql) {
    if (!in_array($col, $cols, true)) {
        @$conn->query($sql);
        echo "<p>✓ blog_posts.$col</p>";
    }
}
// Status enum: add 'scheduled' if not present (only when blog_posts already had old enum)
$r = $conn->query("SHOW COLUMNS FROM blog_posts LIKE 'status'");
$row = $r ? $r->fetch_assoc() : null;
if ($row !== null && isset($row['Type']) && strpos($row['Type'], 'scheduled') === false) {
    @$conn->query("ALTER TABLE blog_posts MODIFY COLUMN status ENUM('draft','published','scheduled') DEFAULT 'draft'");
    echo "<p>✓ blog_posts.status (scheduled added)</p>";
}

// 8) Default blog admin user
$stmt = $conn->prepare("INSERT INTO blog_admin_users (username, password_hash, role) VALUES (?, ?, 'admin') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin'");
$stmt->bind_param('ss', $default_username, $password_hash);
$stmt->execute();
echo "<p>✓ Default user: <strong>{$default_username}</strong> / <strong>{$default_password}</strong></p>";

$conn->close();
echo "<h2>Done</h2><p><a href='index.php'>Go to Blog Admin</a></p>";
echo "<p><strong>Security:</strong> Delete or protect <code>install.php</code> after first run.</p>";
