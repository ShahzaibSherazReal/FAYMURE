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
        'logout' => 'Cerrar Sesión',
        'admin' => 'Administrador'
    ]
];

function t($key) {
    global $translations, $current_lang;
    return $translations[$current_lang][$key] ?? $translations['en'][$key] ?? $key;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Premium Leather Goods</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/animations.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/character.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="assets/js/faymure-font.js"></script>
</head>
<body>
    <header id="siteHeader" class="main-header">
        <div class="header-top">
            <div class="container">
                <a href="shop.php" class="shop-btn">
                    <i class="fas fa-shopping-bag"></i> <?php echo t('shop'); ?>
                </a>
            </div>
        </div>
        <nav class="header-nav">
            <div class="container">
                <div class="nav-left">
                    <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
                </div>
                <div class="nav-center">
                    <a href="index.php" class="nav-underline"><?php echo t('home'); ?></a>
                    <a href="about.php" class="nav-underline"><?php echo t('about'); ?></a>
                    <a href="manufacturing.php" class="nav-underline"><?php echo t('manufacturing'); ?></a>
                    <a href="contact.php" class="nav-underline"><?php echo t('contact'); ?></a>
                </div>
                <div class="nav-right">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="nav-icon" title="<?php echo t('admin'); ?>">
                                <i class="fas fa-user-shield"></i>
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-icon" title="<?php echo t('logout'); ?>">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="nav-icon" title="<?php echo t('login'); ?>">
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
            <form action="search.php" method="GET">
                <input type="text" name="q" placeholder="<?php echo t('search'); ?>..." autofocus>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

