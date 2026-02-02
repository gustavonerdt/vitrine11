<?php
// Admin Sidebar Component - Modern Rounded Design with Submenus
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar status dos banners
$bannersStatus = 'inactive';
$bannersStatusIcon = 'fa-times-circle';
$bannersStatusColor = '#ef4444';

if (defined('FEATURE_BANNERS_ENABLED') && FEATURE_BANNERS_ENABLED == 1) {
    if (isset($pdo) && db_table_exists($pdo, 'banners')) {
        try {
            $stmt = $pdo->prepare("SELECT is_active, carousel_images FROM banners WHERE carousel_type = 'carousel' LIMIT 1");
            $stmt->execute();
            $banner = $stmt->fetch();
            
            if ($banner && isset($banner['is_active']) && (int)$banner['is_active'] == 1) {
                if (!empty($banner['carousel_images'])) {
                    $images = json_decode($banner['carousel_images'], true) ?: [];
                    if (!empty($images) && count($images) > 0) {
                        $bannersStatus = 'active';
                        $bannersStatusIcon = 'fa-check-circle';
                        $bannersStatusColor = '#22c55e';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking banners status: " . $e->getMessage());
        }
    }
}

// Verificar status da musica
$musicEnabled = function_exists('env') ? env('FEATURE_MUSIC_ENABLED', '1') : '1';
$musicEnabled = in_array(strtolower($musicEnabled), ['1', 'true', 'yes', 'on']) ? 1 : 0;

$musicStatus = 'inactive';
$musicStatusIcon = 'fa-times-circle';
$musicStatusColor = '#ef4444';

if ($musicEnabled == 1 && isset($pdo)) {
    try {
        $musicIsActive = getSetting($pdo, 'music_is_active', 0);
        $musicFilePath = getSetting($pdo, 'music_file_path', '');
        
        if ((int)$musicIsActive == 1 && !empty($musicFilePath)) {
            $musicStatus = 'active';
            $musicStatusIcon = 'fa-check-circle';
            $musicStatusColor = '#22c55e';
        }
    } catch (Exception $e) {
        error_log("Error checking music status: " . $e->getMessage());
    }
}

// Contar pedidos nao vistos
$unseenOrdersCount = 0;
if (isset($pdo) && db_table_exists($pdo, 'orders')) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE viewed_at IS NULL");
        $result = $stmt->fetch();
        $unseenOrdersCount = (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error counting unseen orders: " . $e->getMessage());
    }
}

// Contar leads nao lidos
$unreadLeadsCount = 0;
if (isset($pdo) && db_table_exists($pdo, 'leads')) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM leads WHERE is_read = 0 OR is_read IS NULL");
        $result = $stmt->fetch();
        $unreadLeadsCount = (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error counting unread leads: " . $e->getMessage());
    }
}

// Definir quais paginas pertencem a cada submenu
$catalogPages = ['products.php', 'brands.php', 'dynamic-showcases.php'];
$salesPages = ['orders.php', 'leads.php', 'cupons.php'];
$marketingPages = ['banners.php', 'banners-upsell.php', 'logos-carousel.php'];
$settingsPages = ['settings.php', 'theme-editor.php', 'faq.php'];
$mediaPages = ['music.php', 'music-upsell.php'];
?>
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="<?php echo APP_URL; ?>" class="sidebar-logo" target="_blank">
            <img src="<?php echo defined('LOGO_URL') ? LOGO_URL : ''; ?>" alt="Logo" class="sidebar-logo-img">
            <span class="sidebar-app-name"><?php echo htmlspecialchars(APP_NAME); ?></span>
        </a>
    </div>
    
    <button class="sidebar-toggle" id="sidebarToggle" title="Expandir/Recolher menu">
        <i class="fas fa-chevron-right"></i>
    </button>
    
    <nav class="sidebar-nav">
        <!-- PRINCIPAL -->
        <div class="sidebar-section">
            <span class="sidebar-section-title">Principal</span>
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-title="Dashboard">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- CATALOGO (Submenu) -->
        <div class="sidebar-section">
            <span class="sidebar-section-title">Loja</span>
            <ul class="sidebar-menu">
                <li class="has-submenu <?php echo in_array($current_page, $catalogPages) ? 'open' : ''; ?>">
                    <a href="javascript:void(0)" class="submenu-parent <?php echo in_array($current_page, $catalogPages) ? 'active-parent' : ''; ?>" data-title="Catalogo" onclick="toggleSubmenu(this)">
                        <i class="fas fa-box-open"></i>
                        <span>Catalogo</span>
                        <i class="fas fa-chevron-down submenu-toggle"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li>
                            <a href="products.php" class="<?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                                <i class="fas fa-spray-can"></i>
                                <span>Produtos</span>
                            </a>
                        </li>
                        <li>
                            <a href="brands.php" class="<?php echo $current_page == 'brands.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tags"></i>
                                <span>Marcas</span>
                            </a>
                        </li>
                        <li>
                            <a href="dynamic-showcases.php" class="<?php echo $current_page == 'dynamic-showcases.php' ? 'active' : ''; ?>">
                                <i class="fas fa-magic"></i>
                                <span>Vitrines Dinamicas</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- VENDAS (Submenu) -->
                <li class="has-submenu <?php echo in_array($current_page, $salesPages) ? 'open' : ''; ?>">
                    <a href="javascript:void(0)" class="submenu-parent <?php echo in_array($current_page, $salesPages) ? 'active-parent' : ''; ?>" data-title="Vendas" onclick="toggleSubmenu(this)">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Vendas</span>
                        <?php if ($unseenOrdersCount > 0): ?>
                            <span class="badge"><?php echo $unseenOrdersCount; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down submenu-toggle"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li>
                            <a href="orders.php" class="<?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">
                                <i class="fas fa-receipt"></i>
                                <span>Pedidos</span>
                                <?php if ($unseenOrdersCount > 0): ?>
                                    <span class="badge"><?php echo $unseenOrdersCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="leads.php" class="<?php echo $current_page == 'leads.php' ? 'active' : ''; ?>">
                                <i class="fas fa-envelope"></i>
                                <span>Leads / Contatos</span>
                                <?php if ($unreadLeadsCount > 0): ?>
                                    <span class="badge"><?php echo $unreadLeadsCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="cupons.php" class="<?php echo $current_page == 'cupons.php' ? 'active' : ''; ?>">
                                <i class="fas fa-ticket-alt"></i>
                                <span>Cupons de Desconto</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- MARKETING -->
        <div class="sidebar-section">
            <span class="sidebar-section-title">Marketing</span>
            <ul class="sidebar-menu">
                <li class="has-submenu <?php echo in_array($current_page, $marketingPages) ? 'open' : ''; ?>">
                    <a href="javascript:void(0)" class="submenu-parent <?php echo in_array($current_page, $marketingPages) ? 'active-parent' : ''; ?>" data-title="Banners" onclick="toggleSubmenu(this)">
                        <i class="fas fa-images"></i>
                        <span>Banners e Midias</span>
                        <i class="fas <?php echo $bannersStatusIcon; ?>" style="color: <?php echo $bannersStatusColor; ?>; font-size: 0.7rem;"></i>
                        <i class="fas fa-chevron-down submenu-toggle"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li>
                            <a href="banners.php" class="<?php echo $current_page == 'banners.php' ? 'active' : ''; ?>">
                                <i class="fas fa-image"></i>
                                <span>Carrossel Principal</span>
                            </a>
                        </li>
                        <li>
                            <a href="banners-upsell.php" class="<?php echo $current_page == 'banners-upsell.php' ? 'active' : ''; ?>">
                                <i class="fas fa-bullhorn"></i>
                                <span>Banners de Upsell</span>
                            </a>
                        </li>
                        <li>
                            <a href="logos-carousel.php" class="<?php echo $current_page == 'logos-carousel.php' ? 'active' : ''; ?>">
                                <i class="fas fa-grip-horizontal"></i>
                                <span>Logos / Marcas</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <?php if ($musicEnabled == 1): ?>
                <li class="has-submenu <?php echo in_array($current_page, $mediaPages) ? 'open' : ''; ?>">
                    <a href="javascript:void(0)" class="submenu-parent <?php echo in_array($current_page, $mediaPages) ? 'active-parent' : ''; ?>" data-title="Audio" onclick="toggleSubmenu(this)">
                        <i class="fas fa-music"></i>
                        <span>Audio</span>
                        <i class="fas <?php echo $musicStatusIcon; ?>" style="color: <?php echo $musicStatusColor; ?>; font-size: 0.7rem;"></i>
                        <i class="fas fa-chevron-down submenu-toggle"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li>
                            <a href="music.php" class="<?php echo $current_page == 'music.php' ? 'active' : ''; ?>">
                                <i class="fas fa-play-circle"></i>
                                <span>Musica de Fundo</span>
                            </a>
                        </li>
                        <li>
                            <a href="music-upsell.php" class="<?php echo $current_page == 'music-upsell.php' ? 'active' : ''; ?>">
                                <i class="fas fa-volume-up"></i>
                                <span>Audio Promocional</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- SISTEMA -->
        <div class="sidebar-section">
            <span class="sidebar-section-title">Sistema</span>
            <ul class="sidebar-menu">
                <li class="has-submenu <?php echo in_array($current_page, $settingsPages) ? 'open' : ''; ?>">
                    <a href="javascript:void(0)" class="submenu-parent <?php echo in_array($current_page, $settingsPages) ? 'active-parent' : ''; ?>" data-title="Configuracoes" onclick="toggleSubmenu(this)">
                        <i class="fas fa-cogs"></i>
                        <span>Configuracoes</span>
                        <i class="fas fa-chevron-down submenu-toggle"></i>
                    </a>
                    <ul class="sidebar-submenu">
                        <li>
                            <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                                <i class="fas fa-sliders-h"></i>
                                <span>Geral</span>
                            </a>
                        </li>
                        <li>
                            <a href="theme-editor.php" class="<?php echo $current_page == 'theme-editor.php' ? 'active' : ''; ?>">
                                <i class="fas fa-palette"></i>
                                <span>Editor Visual</span>
                                <span class="badge" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); margin-left: auto;">PRO</span>
                            </a>
                        </li>
                        <li>
                            <a href="faq.php" class="<?php echo $current_page == 'faq.php' ? 'active' : ''; ?>">
                                <i class="fas fa-question-circle"></i>
                                <span>FAQ e Ajuda</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-divider"></div>
        
        <!-- LINKS RAPIDOS -->
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo APP_URL; ?>" target="_blank" data-title="Ver Vitrine">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Ver Vitrine</span>
                </a>
            </li>
            <li>
                <a href="<?php echo APP_URL; ?>/api/logout.php" class="logout-link" data-title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<script>
// Toggle Submenu
function toggleSubmenu(element) {
    const parentLi = element.closest('.has-submenu');
    if (parentLi) {
        parentLi.classList.toggle('open');
        
        // Salvar estado no localStorage
        const menuId = element.getAttribute('data-title');
        const isOpen = parentLi.classList.contains('open');
        localStorage.setItem('submenu_' + menuId, isOpen ? 'open' : 'closed');
    }
}

// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    // Restaurar estado do sidebar
    const savedState = localStorage.getItem('admin_sidebar_expanded');
    if (savedState === 'true') {
        sidebar.classList.add('expanded');
    }
    
    // Toggle sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
            localStorage.setItem('admin_sidebar_expanded', sidebar.classList.contains('expanded'));
        });
    }
    
    // Restaurar estado dos submenus
    document.querySelectorAll('.has-submenu').forEach(function(item) {
        const link = item.querySelector('.submenu-parent');
        if (link) {
            const menuId = link.getAttribute('data-title');
            const savedSubState = localStorage.getItem('submenu_' + menuId);
            if (savedSubState === 'open') {
                item.classList.add('open');
            }
        }
    });
});
</script>
