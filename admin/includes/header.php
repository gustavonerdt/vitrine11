<?php
// Admin Header Component
$currentUser = getCurrentUser($pdo);
?>
<header class="admin-header">
    <div class="admin-header-left">
        <button id="mobileMenuToggle" class="btn-icon mobile-only" type="button" aria-label="Abrir menu">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav" id="breadcrumbNav">
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <?php 
            $currentPage = basename($_SERVER['PHP_SELF']);
            $pageNames = [
                'dashboard.php' => 'Dashboard',
                'users.php' => 'Usuarios',
                'products.php' => 'Produtos',
                'packages.php' => 'Pacotes',
                'brands.php' => 'Marcas',
                'invoices.php' => 'Faturas',
                'withdrawals.php' => 'Saques',
                'commissions.php' => 'Comissoes',
                'logs.php' => 'Logs',
                'settings.php' => 'Configuracoes',
                'banners.php' => 'Banners',
                'dynamic-showcases.php' => 'Vitrine Dinamica',
                'faq.php' => 'FAQ e Duvidas',
                'orders.php' => 'Pedidos',
                'leads.php' => 'Leads',
                'cupons.php' => 'Cupons',
                'music.php' => 'Musica',
                'shipping-calculator.php' => 'Calculadora de Frete'
            ];
            if (isset($pageNames[$currentPage]) && $currentPage !== 'dashboard.php'):
            ?>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current"><?php echo $pageNames[$currentPage]; ?></span>
            <?php endif; ?>
        </nav>
    </div>
    <div class="admin-header-right">
        <div class="admin-user">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="admin-user-info">
                <span class="admin-user-name"><?php echo htmlspecialchars($currentUser['name'] ?? 'Admin'); ?></span>
                <span class="admin-user-role">Administrador</span>
            </div>
        </div>
    </div>
</header>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (!mobileMenuToggle || !sidebar || !overlay) {
        console.error('[v0] Mobile menu elements not found');
        return;
    }
    
    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        mobileMenuToggle.innerHTML = '<i class="fas fa-times"></i>';
    }
    
    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    }
    
    function toggleSidebar() {
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    // Toggle button click
    mobileMenuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });
    
    // Close on overlay click
    overlay.addEventListener('click', function() {
        closeSidebar();
    });
    
    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    
    // Close on link click (mobile)
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                closeSidebar();
            }
        });
    });
    
    // Handle resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024 && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
});
</script>
