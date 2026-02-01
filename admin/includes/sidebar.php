<?php
// Admin Sidebar Component - Vitrine Independente
$current_page = basename($_SERVER['PHP_SELF']);

// Obter cor do texto do logo do sidebar (se disponível)
$sidebarLogoTextColor = '#FFFFFF'; // Cor padrão branca
if (function_exists('getSetting')) {
    global $pdo;
    if (isset($pdo)) {
        $sidebarLogoTextColor = getSetting($pdo, 'color_sidebar_logo_text', '#FFFFFF');
    }
}

// Verificar status dos banners
$bannersStatus = 'inactive'; // 'active' ou 'inactive'
$bannersStatusIcon = 'fa-times-circle';
$bannersStatusColor = '#ef4444';

if (defined('FEATURE_BANNERS_ENABLED') && FEATURE_BANNERS_ENABLED == 1) {
    // Feature está habilitada, verificar se há banners ativos
    if (isset($pdo) && db_table_exists($pdo, 'banners')) {
        try {
            $stmt = $pdo->prepare("
                SELECT is_active, carousel_images 
                FROM banners 
                WHERE carousel_type = 'carousel' 
                LIMIT 1
            ");
            $stmt->execute();
            $banner = $stmt->fetch();
            
            if ($banner && isset($banner['is_active']) && (int)$banner['is_active'] == 1) {
                // Verificar se tem imagens
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

// Verificar status da música
$musicStatus = 'inactive'; // 'active' ou 'inactive'
$musicStatusIcon = 'fa-times-circle';
$musicStatusColor = '#ef4444';
$musicStatusTitle = 'Música Desativada';

// Ler diretamente do .env
$musicEnabledEnv = function_exists('env') ? env('FEATURE_MUSIC_ENABLED', '1') : '1';
$musicEnabled = in_array(strtolower($musicEnabledEnv), ['1', 'true', 'yes', 'on']) ? 1 : 0;

if ($musicEnabled == 1) {
    // Feature está habilitada, verificar se a música está ativa
    if (isset($pdo)) {
        try {
            $musicIsActive = getSetting($pdo, 'music_is_active', 0);
            $musicFilePath = getSetting($pdo, 'music_file_path', '');
            
            if ((int)$musicIsActive == 1 && !empty($musicFilePath)) {
                $musicStatus = 'active';
                $musicStatusIcon = 'fa-check-circle';
                $musicStatusColor = '#22c55e';
                $musicStatusTitle = 'Status Ativo (✓ Verde): A música está tocando na vitrine pública. Os visitantes conseguem ouvir a trilha sonora de fundo.';
            } else {
                $musicStatusTitle = 'Status Desativado (✗ Vermelho): A música está oculta na vitrine pública. Os visitantes não ouvem a trilha sonora. Você ainda pode editar e configurar a música, mas ela não toca para os visitantes.';
            }
        } catch (Exception $e) {
            error_log("Error checking music status: " . $e->getMessage());
        }
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo APP_URL; ?>" class="sidebar-logo">
            <span class="sidebar-app-name"><?php echo htmlspecialchars(APP_NAME); ?></span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
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

        <div class="sidebar-section">
            <span class="sidebar-section-title">Catálogo</span>
            <ul class="sidebar-menu">
                <li>
                    <a href="products.php" class="<?php echo $current_page == 'products.php' ? 'active' : ''; ?>" data-title="Produtos">
                        <i class="fas fa-spray-can"></i>
                        <span>Produtos</span>
                    </a>
                </li>
                <li>
                    <a href="brands.php" class="<?php echo $current_page == 'brands.php' ? 'active' : ''; ?>" data-title="Marcas">
                        <i class="fas fa-tags"></i>
                        <span>Marcas</span>
                    </a>
                </li>
                <li>
                    <a href="dynamic-showcases.php" class="<?php echo $current_page == 'dynamic-showcases.php' ? 'active' : ''; ?>" data-title="Vitrine Independente">
                        <i class="fas fa-magic"></i>
                        <span>Vitrine Independente</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <span class="sidebar-section-title">Vendas</span>
            <ul class="sidebar-menu">
                <?php
                // Contar pedidos não vistos
                $unseenOrdersCount = 0;
                if (db_table_exists($pdo, 'orders')) {
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE viewed_at IS NULL");
                        $result = $stmt->fetch();
                        $unseenOrdersCount = (int)($result['count'] ?? 0);
                    } catch (PDOException $e) {
                        error_log("Error counting unseen orders: " . $e->getMessage());
                    }
                }
                ?>
                <li>
                    <a href="orders.php" class="<?php echo $current_page == 'orders.php' ? 'active' : ''; ?>" data-title="Pedidos">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Pedidos</span>
                        <?php if ($unseenOrdersCount > 0): ?>
                            <span class="badge badge-danger" style="margin-left: auto; background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 0.75rem; font-weight: 700;">
                                <?php echo $unseenOrdersCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="leads.php" class="<?php echo $current_page == 'leads.php' ? 'active' : ''; ?>" data-title="Leads">
                        <i class="fas fa-envelope"></i>
                        <span>Leads</span>
                    </a>
                </li>
                <li>
                    <a href="cupons.php" class="<?php echo $current_page == 'cupons.php' ? 'active' : ''; ?>" data-title="Cupons">
                        <i class="fas fa-tag"></i>
                        <span>Cupons</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <span class="sidebar-section-title">Sistema</span>
            <ul class="sidebar-menu">
                <li>
                    <a href="banners.php" class="<?php echo $current_page == 'banners.php' ? 'active' : ''; ?>" data-title="Banners">
                        <i class="fas fa-image"></i>
                        <span>Banners</span>
                        <i class="fas <?php echo $bannersStatusIcon; ?> banner-status-icon" 
                           style="color: <?php echo $bannersStatusColor; ?>; margin-left: auto; font-size: 0.85rem; opacity: 0.8;" 
                           title="<?php echo $bannersStatus == 'active' ? 'Banners Ativos' : 'Banners Desativados'; ?>"></i>
                    </a>
                </li>
                <?php if ($musicEnabled == 1): ?>
                <li>
                    <a href="music.php" class="<?php echo $current_page == 'music.php' ? 'active' : ''; ?>" data-title="Música">
                        <i class="fas fa-music"></i>
                        <span>Música</span>
                        <i class="fas <?php echo $musicStatusIcon; ?> music-status-icon" 
                           style="color: <?php echo $musicStatusColor; ?>; margin-left: auto; font-size: 0.85rem; opacity: 0.8;" 
                           title="<?php echo htmlspecialchars($musicStatusTitle); ?>"></i>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" data-title="Configurações">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                    </a>
                </li>
                <li>
                    <a href="faq.php" class="<?php echo $current_page == 'faq.php' ? 'active' : ''; ?>" data-title="FAQ e Dúvidas">
                        <i class="fas fa-question-circle"></i>
                        <span>FAQ e Dúvidas</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-divider"></div>
        
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
