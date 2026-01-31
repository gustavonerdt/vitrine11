<?php
// user/includes/header.php
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../../config.php';
}
$currentUser = getCurrentUser($pdo);
$csrfToken = generateCsrfToken();

// Get activation banner if office is inactive
$activationBanner = null;
if (($currentUser['office_status'] ?? 'inactive') !== 'active') {
    $userStatus = 'inactive';
    // Try to get banner from dashboard first
    $banners = getBannersForPage($pdo, 'dashboard', $userStatus);
    // Find popup banner for activation
    foreach ($banners as $banner) {
        if ($banner['display_type'] === 'popup' && !empty($banner['image_url'])) {
            $activationBanner = $banner;
            break;
        }
    }
    // If no popup, check for regular banner
    if (!$activationBanner && !empty($banners)) {
        foreach ($banners as $banner) {
            if ($banner['display_type'] === 'banner' && !empty($banner['image_url'])) {
                $activationBanner = $banner;
                break;
            }
        }
    }
    // If still no banner, try to get any banner for inactive users
    if (!$activationBanner && db_table_exists($pdo, 'banners')) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM banners 
                WHERE is_active = 1 
                AND (target_user_status = 'all' OR target_user_status = ?)
                AND image_url IS NOT NULL
                AND image_url != ''
                ORDER BY display_order ASC, created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userStatus]);
            $banner = $stmt->fetch();
            if ($banner) {
                $activationBanner = $banner;
            }
        } catch (PDOException $e) {
            error_log("Banner fetch error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo LOGO_URL; ?>" type="image/png">
</head>
<body>
    <div class="user-layout">
        <!-- Sidebar -->
        <aside class="user-sidebar">
            <div class="sidebar-header">
                <a href="<?php echo APP_URL; ?>" class="sidebar-brand">
                    <?php 
                    $logoWidth = getSetting($pdo, 'logo_width', defined('LOGO_WIDTH') && LOGO_WIDTH !== 'auto' ? LOGO_WIDTH : 'auto');
                    $logoHeight = getSetting($pdo, 'logo_height', defined('LOGO_HEIGHT') && LOGO_HEIGHT !== 'auto' ? LOGO_HEIGHT : 'auto');
                    ?>
                    <img src="<?php echo LOGO_URL; ?>" alt="<?php echo APP_NAME; ?>" class="sidebar-logo"
                         style="width: <?php echo htmlspecialchars($logoWidth); ?>; 
                                height: <?php echo htmlspecialchars($logoHeight); ?>; 
                                max-width: 200px; 
                                max-height: 60px; 
                                object-fit: contain;">
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="<?php echo APP_URL; ?>/user/index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Painel</span>
                </a>
                <a href="<?php echo APP_URL; ?>/user/packages.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'packages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box-open"></i>
                    <span>Pacotes</span>
                </a>
                <div class="nav-item-with-submenu" id="productsSubmenu">
                    <a href="<?php echo APP_URL; ?>/user/products.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" onclick="event.preventDefault(); toggleBrandSubmenu();">
                        <i class="fas fa-store"></i>
                        <span>Vitrine de Perfumes</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <div class="nav-submenu" id="brandSubmenu" style="max-height: 0;">
                        <a href="<?php echo APP_URL; ?>/user/products.php" class="nav-submenu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php' && empty($_GET['brand'])) ? 'active' : ''; ?>">
                            <span>Todas as Marcas</span>
                        </a>
                        <?php
                        // Get brands for submenu - SEM CONTADOR
                        $submenuBrands = [];
                        try {
                            if (db_table_exists($pdo, 'brands')) {
                                $stmt = $pdo->query("
                                    SELECT DISTINCT b.*
                                    FROM brands b
                                    INNER JOIN products p ON b.id = p.brand_id
                                    WHERE b.is_active = 1 
                                    AND p.is_active = 1
                                    GROUP BY b.id
                                    HAVING COUNT(p.id) > 0
                                    ORDER BY b.name ASC
                                    LIMIT 10
                                ");
                                $submenuBrands = $stmt->fetchAll();
                            }
                        } catch (PDOException $e) {}
                        foreach ($submenuBrands as $brand): ?>
                            <a href="<?php echo APP_URL; ?>/user/products.php?brand=<?php echo $brand['id']; ?>" class="nav-submenu-item <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'active' : ''; ?>">
                                <span><?php echo htmlspecialchars($brand['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="<?php echo APP_URL; ?>/user/associates.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'associates.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Meus Associados</span>
                </a>
                <!-- Adicionado link para Comissões -->
                <a href="<?php echo APP_URL; ?>/user/commissions.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Minhas Comissões</span>
                </a>
                <a href="<?php echo APP_URL; ?>/user/points.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'points.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Controle de Pontos</span>
                </a>
                <a href="<?php echo APP_URL; ?>/user/financial.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'financial.php' ? 'active' : ''; ?>">
                    <i class="fas fa-university"></i>
                    <span>Financeiro</span>
                </a>
                <a href="<?php echo APP_URL; ?>/user/account.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'account.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Minha Conta</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="<?php echo APP_URL; ?>/api/logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="user-main">
            <!-- Top Bar -->
            <header class="user-topbar">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="topbar-user">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                        <?php if (($currentUser['office_status'] ?? 'inactive') === 'active'): ?>
                        <span class="office-badge active">Escritório Ativo</span>
                        <?php else: ?>
                        <span class="office-badge inactive <?php echo $activationBanner ? 'clickable' : ''; ?>" 
                              <?php if ($activationBanner): ?>onclick="openActivationBannerModal()" style="cursor: pointer;"<?php endif; ?>>
                            Escritório Inativo
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($currentUser['google_id'])): ?>
                        <span class="badge badge-info" style="margin-left: 8px; font-size: 0.75rem;">
                            <i class="fab fa-google"></i> Google
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="user-content">
