<?php
require_once __DIR__ . '/../config/config.php';

// Language detection
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = sanitize($_GET['lang']);
}

$current_lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';
if (!isset($_SESSION['lang'])) {
    // Detect language from browser
    $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
    $current_lang = in_array($browser_lang, ['en', 'es', 'fr', 'de', 'it', 'pt', 'hi', 'ar', 'zh', 'ja']) ? $browser_lang : 'en';
    $_SESSION['lang'] = $current_lang;
} else {
    $current_lang = $_SESSION['lang'];
}

// Language translations
$translations = [
    'en' => [
        'home' => 'Home',
        'about' => 'About Us',
        'contact' => 'Contact',
        'manufacturing' => 'Manufacturing',
        'shop' => 'Shop',
        'coming_soon' => 'Coming Soon',
        'login' => 'Login',
        'search' => 'Search',
        'explore' => 'Explore',
        'blog' => 'Blog',
        'logout' => 'Logout',
        'admin' => 'Admin'
    ],
    'es' => [
        'home' => 'Inicio',
        'about' => 'Sobre Nosotros',
        'contact' => 'Contacto',
        'manufacturing' => 'Manufactura',
        'shop' => 'Tienda',
        'coming_soon' => 'Próximamente',
        'login' => 'Iniciar Sesión',
        'search' => 'Buscar',
        'explore' => 'Explorar',
        'blog' => 'Blog',
        'logout' => 'Cerrar Sesión',
        'admin' => 'Administrador'
    ]
];

function t($key) {
    global $translations, $current_lang;
    return $translations[$current_lang][$key] ?? $translations['en'][$key] ?? $key;
}

$base = defined('BASE_PATH') ? BASE_PATH : '';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : (SITE_NAME . ' - Premium Leather Goods'); ?></title>
    <?php if (!empty($page_meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page_meta_description); ?>">
    <?php endif; ?>
    <?php if (!empty($page_canonical)): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($page_canonical); ?>">
    <?php endif; ?>
    <?php if (!empty($page_og_title)): ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($page_og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_og_description ?? $page_meta_description ?? ''); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_canonical ?? ''); ?>">
    <meta property="og:type" content="<?php echo isset($page_og_type) ? htmlspecialchars($page_og_type) : 'website'; ?>">
    <?php if (!empty($page_og_image)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($page_og_image); ?>">
    <?php endif; ?>
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <?php if (!empty($page_og_title)): ?>
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_og_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_og_description ?? $page_meta_description ?? ''); ?>">
    <?php endif; ?>
    <?php if (!empty($page_og_image)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_og_image); ?>">
    <?php endif; ?>
    <?php if (!empty($page_extra_head)) echo $page_extra_head; ?>
    <link rel="icon" href="<?php echo $base; ?>/assets/images/favicon.png.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/animations.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/character.css?v=<?php echo time(); ?>">
    <?php if (!empty($page_extra_css)): ?>
    <link rel="stylesheet" href="<?php echo $base . '/' . ltrim($page_extra_css, '/'); ?>?v=<?php echo time(); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="<?php echo $base; ?>/assets/js/faymure-font.js"></script>
</head>
<body>
    <?php
    // Check if shop is in coming soon mode and if blog is hidden
    $conn = getDBConnection();
    $shop_coming_soon = false;
    $blog_hidden = false;
    $result = $conn->query("SELECT content_key, content_value FROM site_content WHERE content_key IN ('shop_coming_soon','blog_hidden')");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($row['content_key']) && $row['content_key'] === 'shop_coming_soon' && !empty($row['content_value']) && $row['content_value'] == '1') {
                $shop_coming_soon = true;
            }
            if (isset($row['content_key']) && $row['content_key'] === 'blog_hidden' && !empty($row['content_value']) && $row['content_value'] == '1') {
                $blog_hidden = true;
            }
        }
    }
    $conn->close();
    ?>
    <header id="siteHeader" class="main-header">
        <div class="header-top">
            <div class="container">
                <?php if ($shop_coming_soon): ?>
                    <div style="display: flex; align-items: center; gap: 12px; justify-content: center;">
                        <span class="shop-btn" style="cursor: not-allowed; opacity: 0.6; pointer-events: none;">
                            <i class="fas fa-shopping-bag"></i> <?php echo t('shop'); ?>
                        </span>
                        <span style="color: var(--accent-color); font-size: 12px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase;">
                            <i class="fas fa-clock"></i> <?php echo t('coming_soon'); ?>
                        </span>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base; ?>/shop" class="shop-btn">
                        <i class="fas fa-shopping-bag"></i> <?php echo t('shop'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <nav class="header-nav">
            <div class="container">
                <div class="nav-left">
                    <a href="<?php echo $base; ?>/" class="logo"><?php echo SITE_NAME; ?></a>
                </div>
                <div class="nav-center">
                    <a href="<?php echo $base; ?>/" class="nav-underline"><?php echo t('home'); ?></a>
                    <a href="<?php echo $base; ?>/about" class="nav-underline"><?php echo t('about'); ?></a>
                    <a href="<?php echo $base; ?>/manufacturing" class="nav-underline"><?php echo t('manufacturing'); ?></a>
                    <?php if (!$blog_hidden): ?>
                    <a href="<?php echo $base; ?>/blog" class="nav-underline"><?php echo t('blog'); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo $base; ?>/contact" class="nav-underline"><?php echo t('contact'); ?></a>
                </div>
                <div class="nav-right">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
<a href="<?php echo $base; ?>/admin/dashboard.php" class="nav-icon" title="<?php echo t('admin'); ?>">
                            <i class="fas fa-user-shield"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo $base; ?>/logout" class="nav-icon" title="<?php echo t('logout'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $base; ?>/login" class="nav-icon" title="<?php echo t('login'); ?>">
                            <i class="fas fa-user"></i>
                        </a>
                    <?php endif; ?>
                    <button class="nav-icon search-btn" title="<?php echo t('search'); ?>">
                        <i class="fas fa-search"></i>
                    </button>
                    <div class="language-toggle">
                        <button class="lang-btn" id="langToggle">
                            <i class="fas fa-globe"></i> <?php echo strtoupper($current_lang); ?>
                        </button>
                        <div class="lang-dropdown" id="langDropdown">
                            <?php 
                            $current_url = $_SERVER['REQUEST_URI'];
                            $url_parts = parse_url($current_url);
                            $base_url = $url_parts['path'];
                            $query_params = [];
                            if (isset($url_parts['query'])) {
                                parse_str($url_parts['query'], $query_params);
                            }
                            $query_params['lang'] = 'en';
                            $en_url = $base_url . '?' . http_build_query($query_params);
                            $query_params['lang'] = 'es';
                            $es_url = $base_url . '?' . http_build_query($query_params);
                            ?>
                            <a href="<?php echo $en_url; ?>">English</a>
                            <a href="<?php echo $es_url; ?>">Español</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Search Modal -->
    <div class="search-modal" id="searchModal">
        <div class="search-content">
            <span class="close-search">&times;</span>
            <form action="<?php echo $base; ?>/search" method="GET">
                <input type="text" name="q" placeholder="<?php echo t('search'); ?>..." autofocus>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

