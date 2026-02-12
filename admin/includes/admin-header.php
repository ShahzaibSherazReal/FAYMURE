<header class="admin-header">
    <div class="admin-header-content">
        <div class="admin-logo">
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <a href="dashboard.php"><?php echo SITE_NAME; ?> Admin</a>
        </div>
        <nav class="admin-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="shop.php" class="nav-link"><i class="fas fa-store"></i> Shop</a>
            <a href="explore.php" class="nav-link"><i class="fas fa-compass"></i> Explore</a>
            <a href="orders.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> Users</a>
            <a href="site-content.php" class="nav-link"><i class="fas fa-edit"></i> Site Content</a>
            <a href="../index.php" class="nav-link" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
            <a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>
</header>

<!-- Mobile Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h3><?php echo SITE_NAME; ?> Admin</h3>
        <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="shop.php" class="sidebar-link"><i class="fas fa-store"></i> Shop</a>
        <a href="explore.php" class="sidebar-link"><i class="fas fa-compass"></i> Explore</a>
        <a href="orders.php" class="sidebar-link"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> Users</a>
        <a href="site-content.php" class="sidebar-link"><i class="fas fa-edit"></i> Site Content</a>
        <a href="../index.php" class="sidebar-link" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
        <a href="../logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Mobile Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('adminSidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    
    function openSidebar() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (menuToggle) {
        menuToggle.addEventListener('click', openSidebar);
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking a link
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', closeSidebar);
    });
});
</script>

