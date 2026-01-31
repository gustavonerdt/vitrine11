<?php
// Admin Header Component
$currentUser = getCurrentUser($pdo);
?>
<header class="admin-header">
    <div class="admin-header-left">
        <button id="sidebarToggle" class="btn-icon mobile-only">
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
                'users.php' => 'Usuários',
                'products.php' => 'Produtos',
                'packages.php' => 'Pacotes',
                'brands.php' => 'Marcas',
                'invoices.php' => 'Faturas',
                'withdrawals.php' => 'Saques',
                'commissions.php' => 'Comissões',
                'logs.php' => 'Logs',
                'settings.php' => 'Configurações',
                'banners.php' => 'Banners',
                'dynamic-showcases.php' => 'Vitrine Dinâmica',
                'faq.php' => 'FAQ e Dúvidas',
                'orders.php' => 'Pedidos',
                'leads.php' => 'Leads'
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

<script>
// Global Search Toggle
document.addEventListener('DOMContentLoaded', function() {
    const searchToggle = document.getElementById('searchToggle');
    const globalSearch = document.getElementById('globalSearch');
    
    if (searchToggle && globalSearch) {
        searchToggle.addEventListener('click', function() {
            if (globalSearch.style.display === 'none' || !globalSearch.style.display) {
                globalSearch.style.display = 'block';
                globalSearch.focus();
                globalSearch.style.width = '300px';
            } else {
                globalSearch.style.display = 'none';
                globalSearch.style.width = '0';
            }
        });
        
        globalSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    // Redirect to users page with search
                    window.location.href = '<?php echo APP_URL; ?>/admin/users.php?search=' + encodeURIComponent(query);
                }
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    // Create overlay if it doesn't exist
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    function openSidebar() {
        // Force reflow to ensure animation starts
        sidebar.style.display = 'flex';
        overlay.style.display = 'block';
        
        // Small delay to trigger animation
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                sidebar.classList.add('show');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        });
    }
    
    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        
        // Wait for animation to complete before hiding
        setTimeout(() => {
            if (!sidebar.classList.contains('show')) {
                sidebar.style.display = '';
                overlay.style.display = 'none';
            }
            document.body.style.overflow = '';
        }, 400); // Match animation duration
    }
    
    function toggleSidebar() {
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    // Toggle button
    toggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });
    
    // Close on overlay click
    overlay.addEventListener('click', () => {
        closeSidebar();
    });
    
    // Close on sidebar link click (mobile only)
    if (sidebar) {
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });
    }
    
    // Close on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        }, 250);
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024 && 
            sidebar.classList.contains('show') &&
            !sidebar.contains(e.target) && 
            !toggle.contains(e.target) &&
            !overlay.contains(e.target)) {
            closeSidebar();
        }
    });
});
</script>
