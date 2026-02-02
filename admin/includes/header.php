<?php
/**
 * Admin Header Component
 * Links corrigidos para navegacao
 */
$currentUser = function_exists('getCurrentUser') && isset($pdo) ? getCurrentUser($pdo) : ['name' => 'Admin'];
$app_url = defined('APP_URL') ? APP_URL : '';
?>
<header class="admin-header">
    <div class="admin-header-left">
        <button id="mobileMenuToggle" class="btn-icon mobile-only" type="button" aria-label="Abrir menu">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav" id="breadcrumbNav">
            <a href="<?php echo $app_url; ?>/admin/dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
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
                'theme-editor.php' => 'Editor Visual',
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
        <!-- Theme Toggle -->
        <button id="themeToggle" class="theme-toggle" type="button" aria-label="Alternar tema" title="Alternar tema claro/escuro">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        
        <!-- User Dropdown -->
        <div class="dropdown">
            <div class="admin-user" id="userDropdownToggle">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="admin-user-info">
                    <span class="admin-user-name"><?php echo htmlspecialchars($currentUser['name'] ?? 'Admin'); ?></span>
                    <span class="admin-user-role">Administrador</span>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 0.65rem; color: var(--muted-foreground); margin-left: 4px;"></i>
            </div>
            <div class="dropdown-menu" id="userDropdownMenu">
                <a href="<?php echo $app_url; ?>/admin/settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    Configuracoes
                </a>
                <a href="<?php echo $app_url; ?>" target="_blank" class="dropdown-item">
                    <i class="fas fa-external-link-alt"></i>
                    Ver Vitrine
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo $app_url; ?>/api/logout.php" class="dropdown-item" style="color: var(--admin-error);">
                    <i class="fas fa-sign-out-alt"></i>
                    Sair
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (mobileMenuToggle && sidebar && overlay) {
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
        
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        
        overlay.addEventListener('click', closeSidebar);
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                closeSidebar();
            }
        });
        
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024 && sidebar.classList.contains('show')) {
                closeSidebar();
            }
        });
    }
    
    // Theme Toggle (Dark/Light Mode)
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;
    
    // Check saved theme or system preference
    const savedTheme = localStorage.getItem('admin-theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    function setTheme(isDark) {
        if (isDark) {
            html.classList.add('dark');
            html.setAttribute('data-theme', 'dark');
            if (themeIcon) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
            localStorage.setItem('admin-theme', 'dark');
        } else {
            html.classList.remove('dark');
            html.setAttribute('data-theme', 'light');
            if (themeIcon) {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
            localStorage.setItem('admin-theme', 'light');
        }
    }
    
    // Initialize theme
    if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
        setTheme(true);
    } else {
        setTheme(false);
    }
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const isDark = html.classList.contains('dark');
            setTheme(!isDark);
        });
    }
    
    // User Dropdown
    const userDropdownToggle = document.getElementById('userDropdownToggle');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdownToggle && userDropdownMenu) {
        userDropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!userDropdownToggle.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userDropdownMenu.classList.remove('show');
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                userDropdownMenu.classList.remove('show');
            }
        });
    }
});
</script>
