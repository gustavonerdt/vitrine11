<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Check if banners feature is enabled
$bannersEnabled = defined('FEATURE_BANNERS_ENABLED') ? (int)FEATURE_BANNERS_ENABLED : 1;
$checkoutLink = defined('BANNERS_CHECKOUT_LINK') ? BANNERS_CHECKOUT_LINK : '';

// If feature is disabled, show upsell page
if ($bannersEnabled === 0) {
    include __DIR__ . '/banners-upsell.php';
    exit;
}

$page_title = 'Banners do Carrossel';
$page_subtitle = 'Configure as imagens que aparecem no topo da sua vitrine';
$csrf = generateCsrfToken();

// Ensure table exists and has carousel columns
if (!db_table_exists($pdo, 'banners')) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `banners` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(255) NOT NULL COMMENT 'Nome interno do banner',
              `carousel_type` ENUM('single', 'carousel') DEFAULT 'carousel' COMMENT 'Tipo: banner simples ou carrossel',
              `target_brand_id` INT UNSIGNED DEFAULT NULL COMMENT 'Marca específica para exibição do banner',
              `target_product_id` INT UNSIGNED DEFAULT NULL COMMENT 'Produto de destino ao clicar no banner',
              `carousel_images` JSON DEFAULT NULL COMMENT 'Array de até 4 URLs de imagens do carrossel',
              `is_active` TINYINT(1) DEFAULT 1,
              `display_order` INT DEFAULT 0,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_carousel_type` (`carousel_type`),
              KEY `idx_target_brand` (`target_brand_id`),
              KEY `idx_target_product` (`target_product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Error creating banners table: " . $e->getMessage());
    }
} else {
    // Add carousel columns if they don't exist
    try {
        if (!db_has_column($pdo, 'banners', 'carousel_type')) {
            $pdo->exec("ALTER TABLE `banners` 
                ADD COLUMN `carousel_type` ENUM('single', 'carousel') DEFAULT 'single' COMMENT 'Tipo: banner simples ou carrossel',
                ADD COLUMN `target_brand_id` INT UNSIGNED DEFAULT NULL COMMENT 'Marca específica para exibição do banner',
                ADD COLUMN `target_product_id` INT UNSIGNED DEFAULT NULL COMMENT 'Produto de destino ao clicar no banner',
                ADD COLUMN `carousel_images` JSON DEFAULT NULL COMMENT 'Array de até 4 URLs de imagens do carrossel'");
            
            // Add indexes
            try {
                $pdo->exec("ALTER TABLE `banners` 
                    ADD KEY `idx_carousel_type` (`carousel_type`),
                    ADD KEY `idx_target_brand` (`target_brand_id`),
                    ADD KEY `idx_target_product` (`target_product_id`)");
            } catch (PDOException $e) {
                error_log("Error adding carousel indexes: " . $e->getMessage());
            }
            
            // Add foreign keys if tables exist
            try {
                if (db_table_exists($pdo, 'brands')) {
                $pdo->exec("ALTER TABLE `banners` 
                        ADD CONSTRAINT `fk_banners_brand` FOREIGN KEY (`target_brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL");
            }
                if (db_table_exists($pdo, 'products')) {
                    $pdo->exec("ALTER TABLE `banners` 
                        ADD CONSTRAINT `fk_banners_product` FOREIGN KEY (`target_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL");
        }
            } catch (PDOException $e) {
                error_log("Error adding carousel foreign keys: " . $e->getMessage());
            }
        }
            } catch (PDOException $e) {
        error_log("Error adding carousel columns: " . $e->getMessage());
    }
}

// Get or create carousel banner
$carouselBanner = null;
$hasBanners = false;
if (db_table_exists($pdo, 'banners')) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM banners WHERE carousel_type = 'carousel' LIMIT 1");
        $stmt->execute();
        $carouselBanner = $stmt->fetch();
        
        // Check if banner exists and has images
        if ($carouselBanner) {
            // Check if banner is active
            if (isset($carouselBanner['is_active']) && $carouselBanner['is_active'] == 1) {
                // Check if has images
                if (!empty($carouselBanner['carousel_images'])) {
                    $images = json_decode($carouselBanner['carousel_images'], true) ?: [];
                    if (!empty($images) && count($images) > 0) {
                        $hasBanners = true;
                    }
                }
            }
        }
        
        // If no carousel exists, create one (but don't show upsell yet - allow configuration)
        if (!$carouselBanner) {
            $stmt = $pdo->prepare("
                INSERT INTO banners (name, carousel_type, is_active, display_order)
                VALUES (?, 'carousel', 1, 0)
            ");
            $stmt->execute(['Carrossel da Vitrine']);
            $carouselBannerId = $pdo->lastInsertId();
            
            // Fetch the newly created banner
            $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
            $stmt->execute([$carouselBannerId]);
            $carouselBanner = $stmt->fetch();
        }
        
        // Parse carousel_images JSON
        if (!empty($carouselBanner['carousel_images'])) {
            $carouselBanner['carousel_images'] = json_decode($carouselBanner['carousel_images'], true) ?: [];
        } else {
            $carouselBanner['carousel_images'] = [];
        }
    } catch (PDOException $e) {
        error_log("Error fetching carousel banner: " . $e->getMessage());
    }
}

// Show upsell if:
// 1. Feature is disabled (always show upsell)
// 2. OR (no banners configured OR banner is inactive) AND checkout link is set
if ($bannersEnabled === 0) {
    // Feature disabled - always show upsell
    include __DIR__ . '/banners-upsell.php';
    exit;
} elseif ((!$hasBanners || (isset($carouselBanner['is_active']) && (int)$carouselBanner['is_active'] == 0)) && !empty($checkoutLink)) {
    // No banners or banner inactive - show upsell if checkout link is configured
    include __DIR__ . '/banners-upsell.php';
    exit;
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de segurança inválido.";
    } else {
        try {
                $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            
            if (!$id && $carouselBanner) {
                $id = $carouselBanner['id'];
            }
            
            // Carousel fields
            $target_brand_id = !empty($_POST['target_brand_id']) ? (int)$_POST['target_brand_id'] : null;
            $target_product_id = !empty($_POST['target_product_id']) ? (int)$_POST['target_product_id'] : null;
            $carousel_images = [];
            for ($i = 1; $i <= 8; $i++) {
                // Imagem desktop (recomendada 390x153)
                $imgDesktop = trim($_POST['carousel_image_' . $i] ?? '');
                // Imagem mobile (recomendada 600x717)
                $imgMobile = trim($_POST['carousel_image_mobile_' . $i] ?? '');

                $prod_id = !empty($_POST['carousel_product_' . $i]) ? (int)$_POST['carousel_product_' . $i] : null;
                // Mantém compatibilidade: se só uma imagem vier, usa como base
                if (!empty($imgDesktop) || !empty($imgMobile)) {
                    $carousel_images[] = [
                        'image_url' => $imgDesktop ?: $imgMobile,
                        'image_url_desktop' => $imgDesktop ?: null,
                        'mobile_image_url' => $imgMobile ?: null,
                        'product_id' => $prod_id,
                        'display_order' => $i
                    ];
                }
            }
            $carousel_images_json = !empty($carousel_images) ? json_encode($carousel_images) : null;
                
                // Check if deactivating or activating
            // If deactivate_banners button was clicked, set to 0
            // If banner_active button was clicked (from "Ativar" button), set to 1
            // Otherwise, use the checkbox value or default to current state
            if (isset($_POST['deactivate_banners'])) {
                $is_active = 0;
            } elseif (isset($_POST['banner_active']) && $_POST['banner_active'] == '1' && !isset($_POST['deactivate_banners'])) {
                // Button "Ativar Banners" was clicked
                $is_active = 1;
            } else {
                // Use checkbox value or keep current state
                $is_active = isset($_POST['banner_active']) ? 1 : (isset($carouselBanner['is_active']) ? (int)$carouselBanner['is_active'] : 1);
            }
            
            if ($id) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE banners SET 
                        carousel_type = 'carousel',
                        target_brand_id = ?, 
                        target_product_id = ?, 
                        carousel_images = ?,
                        is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                    $target_brand_id, 
                    $target_product_id, 
                    $carousel_images_json,
                    $is_active,
                    $id
                    ]);
                // Determine success message based on action
                if (isset($_POST['deactivate_banners'])) {
                    $success = "Carrossel desativado com sucesso!";
                } elseif (isset($_POST['banner_active']) && $_POST['banner_active'] == '1' && !isset($_POST['deactivate_banners'])) {
                    $success = "Carrossel ativado com sucesso!";
                } else {
                    $success = $is_active ? "Carrossel atualizado e ativado com sucesso!" : "Carrossel atualizado com sucesso!";
                }
                
                // Refresh carousel banner
                $stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
                $stmt->execute([$id]);
                $carouselBanner = $stmt->fetch();
                if (!empty($carouselBanner['carousel_images'])) {
                    $carouselBanner['carousel_images'] = json_decode($carouselBanner['carousel_images'], true) ?: [];
                } else {
                    $carouselBanner['carousel_images'] = [];
                }
                
                logActivity($pdo, $_SESSION['user_id'], 'carousel_updated', "Carrossel da vitrine atualizado");
            } else {
                $error = "Erro: ID do carrossel não encontrado.";
            }
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
            error_log("Carousel save error: " . $e->getMessage());
        }
    }
}

// Fetch brands and products for dropdowns
$brandsList = [];
$products = [];
if (db_table_exists($pdo, 'brands')) {
    try {
        $brandStmt = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $brandsList = $brandStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Brands fetch error: " . $e->getMessage());
    }
}
if (db_table_exists($pdo, 'products')) {
    try {
        $prodStmt = $pdo->query("SELECT id, name, brand_id FROM products WHERE is_active = 1 ORDER BY name ASC");
        $products = $prodStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Products fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/image-upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .simple-container {
            max-width: 900px;
            margin: 0 auto;
            padding-bottom: 100px; /* Espaço para o botão fixo */
        }
        
        .status-card {
            background: linear-gradient(135deg, var(--admin-accent) 0%, rgba(199, 163, 51, 0.8) 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            color: var(--admin-text-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid var(--admin-border);
        }
        
        .status-card h2 {
            margin: 0 0 15px 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-card .status-controls {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--admin-bg-card);
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid var(--admin-border);
        }
        
        .status-toggle:hover {
            background: var(--admin-bg-secondary);
            border-color: var(--admin-accent);
        }
        
        .status-toggle input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid var(--admin-border);
        }
        
        .status-badge.active {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border-color: rgba(34, 197, 94, 0.3);
        }
        
        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .simple-section {
            background: var(--admin-bg-card);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--admin-border);
        }
        
        .simple-section h3 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            color: var(--admin-text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .simple-section .step-number {
            background: var(--admin-accent);
            color: var(--admin-text-primary);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .simple-section p {
            color: var(--admin-text-secondary);
            line-height: 1.6;
            margin: 15px 0;
        }
        
        .image-card {
            border: 2px dashed var(--admin-border);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            background: var(--admin-bg-secondary);
            transition: all 0.3s;
        }
        
        .image-card:hover {
            border-color: var(--admin-accent);
            background: var(--admin-bg-card);
        }
        
        .image-card.has-image {
            border: 2px solid var(--admin-accent);
            background: var(--admin-bg-card);
        }
        
        .image-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .image-card-header h4 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--admin-text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .required-badge {
            background: rgba(239, 68, 68, 0.9);
            color: var(--admin-text-primary);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .optional-badge {
            background: var(--admin-bg-secondary);
            color: var(--admin-text-secondary);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--admin-border);
        }
        
        .upload-area {
            min-height: 200px;
            margin-bottom: 15px;
            overflow: visible;
            position: relative;
        }
        
        @media (max-width: 768px) {
            .upload-area {
                min-height: 250px !important;
                overflow: visible !important;
                max-height: none !important;
                padding: 20px !important;
            }
            
            #carouselImageUpload1,
            #carouselImageUpload2,
            #carouselImageUpload3,
            #carouselImageUpload4,
            #carouselImageUpload1_mobile,
            #carouselImageUpload2_mobile,
            #carouselImageUpload3_mobile,
            #carouselImageUpload4_mobile {
                min-height: 250px !important;
                overflow: visible !important;
                max-height: none !important;
                padding: 20px !important;
            }
            
            .image-card {
                margin-bottom: 25px;
            }
        }
        
        .help-box {
            background: var(--admin-bg-secondary);
            border-left: 4px solid var(--admin-accent);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid var(--admin-border);
        }
        
        .help-box p {
            margin: 5px 0;
            color: var(--admin-text-secondary);
            font-size: 0.9rem;
        }
        
        .help-box strong {
            color: var(--admin-accent);
        }
        
        .product-select {
            margin-top: 15px;
        }
        
        .product-select label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--admin-text-primary);
        }
        
        .advanced-section {
            margin-top: 30px;
            border-top: 2px solid var(--admin-border);
            padding-top: 25px;
        }
        
        .advanced-toggle {
            background: var(--admin-bg-secondary);
            border: 1px solid var(--admin-border);
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-weight: 600;
            color: var(--admin-text-secondary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }
        
        .advanced-toggle:hover {
            background: var(--admin-bg-card);
            border-color: var(--admin-accent);
            color: var(--admin-text-primary);
        }
        
        .advanced-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .advanced-content.open {
            max-height: 2000px;
        }
        
        .save-button-container {
            position: fixed;
            bottom: 0;
            left: 80px; /* Largura do sidebar colapsado */
            right: 0;
            background: var(--admin-bg-card);
            padding: 20px;
            border-top: 3px solid var(--admin-accent);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            border-left: 1px solid var(--admin-border);
            border-right: 1px solid var(--admin-border);
        }
        
        /* Ajustar para considerar o sidebar */
        .admin-layout .main-content {
            padding-bottom: 100px; /* Espaço para o botão fixo */
        }
        
        /* Quando sidebar está colapsado ou em mobile */
        @media (max-width: 1024px) {
            .save-button-container {
                left: 0;
            }
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--admin-accent), rgba(199, 163, 51, 0.9));
            color: var(--admin-text-primary);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
        }
        
        .btn-save:active {
            transform: translateY(0);
        }
        
        .btn-toggle {
            background: var(--admin-bg-card);
            color: var(--admin-text-primary);
            border: 2px solid var(--admin-border);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-toggle:hover {
            border-color: var(--admin-accent);
            background: var(--admin-accent);
            color: var(--admin-text-primary);
        }
        
        @media (max-width: 768px) {
            .simple-container {
                padding: 0 15px;
            }
            
            .simple-section {
                padding: 20px;
            }
            
            .status-card {
                padding: 20px;
            }
            
            .status-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body class="page-enter">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-images"></i> <?php echo $page_title; ?></h1>
                        <p class="page-description">
                            <?php echo $page_subtitle; ?>
                        </p>
                    </div>
                </div>
                
                <div class="simple-container">
                    <form method="POST" id="carouselForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="id" value="<?php echo $carouselBanner['id'] ?? ''; ?>">

                        <!-- Status Card -->
                        <div class="status-card">
                            <h2><i class="fas fa-power-off"></i> Status dos Banners</h2>
                            <div class="status-controls">
                                <label class="status-toggle">
                                    <input type="checkbox" 
                                           id="bannerActivationToggle" 
                                           name="banner_active" 
                                           value="1"
                                           <?php echo (isset($carouselBanner['is_active']) && (int)$carouselBanner['is_active'] == 1) ? 'checked' : ''; ?>>
                                    <span>Banners Ligados</span>
                                </label>
                                <?php 
                                $isBannerActive = isset($carouselBanner['is_active']) && (int)$carouselBanner['is_active'] == 1;
                                ?>
                                <span class="status-badge <?php echo $isBannerActive ? 'active' : 'inactive'; ?>">
                                    <?php echo $isBannerActive ? '✓ ATIVO' : '✗ INATIVO'; ?>
                                </span>
                                <?php if ($isBannerActive): ?>
                                    <button type="submit" 
                                            name="deactivate_banners" 
                                            value="1"
                                            form="carouselForm"
                                            class="btn-toggle">
                                        <i class="fas fa-ban"></i> Desligar
                                    </button>
                                <?php else: ?>
                                    <button type="submit" 
                                            name="banner_active" 
                                            value="1"
                                            form="carouselForm"
                                            class="btn-toggle">
                                        <i class="fas fa-check-circle"></i> Ligar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Step 1: Add Images -->
                        <div class="simple-section">
                            <h3>
                                <span class="step-number">1</span>
                                Adicione suas Imagens
                            </h3>
                            <p>Você pode adicionar até 8 imagens. A primeira é obrigatória, as outras são opcionais.</p>
                            
                            <?php 
                            $carouselImages = $carouselBanner['carousel_images'] ?? [];
                            for ($i = 1; $i <= 8; $i++): 
                                $carouselImg = $carouselImages[$i-1] ?? null;
                                $desktopUrl = $carouselImg['image_url'] ?? $carouselImg['image_url_desktop'] ?? '';
                                $mobileUrl = $carouselImg['mobile_image_url'] ?? $carouselImg['image_url_mobile'] ?? '';
                                $hasImage = !empty($desktopUrl) || !empty($mobileUrl);
                            ?>
                            <div class="image-card <?php echo $hasImage ? 'has-image' : ''; ?>">
                                <div class="image-card-header">
                                    <h4>
                                        <i class="fas fa-image"></i>
                                        Imagem <?php echo $i; ?>
                                        <?php if ($i === 1): ?>
                                            <span class="required-badge">OBRIGATÓRIA</span>
                                        <?php else: ?>
                                            <span class="optional-badge">OPCIONAL</span>
                                        <?php endif; ?>
                                    </h4>
                                </div>
                                
                                <div class="upload-area">
                                    <!-- Desktop -->
                                    <div style="margin-bottom: 16px;">
                                        <strong style="display:block; margin-bottom:6px;">Desktop (recomendado 390x153)</strong>
                                        <div id="carouselImageUpload<?php echo $i; ?>" style="min-height: 180px;">
                                            <div style="padding: 30px; text-align: center; color: var(--admin-text-secondary); border: 2px dashed var(--admin-border); border-radius: 8px; background: var(--admin-bg-secondary);">
                                                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 8px;"></i>
                                            <p>Carregando...</p>
                                        </div>
                                    </div>
                                    <input type="hidden" name="carousel_image_<?php echo $i; ?>" 
                                           id="carouselImageUrl<?php echo $i; ?>" 
                                               value="<?php echo htmlspecialchars($desktopUrl); ?>">
                                    </div>

                                    <!-- Mobile -->
                                    <div>
                                        <strong style="display:block; margin-bottom:6px;">Mobile (recomendado 600x717)</strong>
                                        <div id="carouselImageUpload<?php echo $i; ?>_mobile" style="min-height: 180px;">
                                            <div style="padding: 30px; text-align: center; color: var(--admin-text-secondary); border: 2px dashed var(--admin-border); border-radius: 8px; background: var(--admin-bg-secondary);">
                                                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 8px;"></i>
                                                <p>Carregando...</p>
                                            </div>
                                        </div>
                                        <input type="hidden" name="carousel_image_mobile_<?php echo $i; ?>" 
                                               id="carouselImageUrl<?php echo $i; ?>_mobile" 
                                               value="<?php echo htmlspecialchars($mobileUrl); ?>">
                                    </div>
                                </div>
                                
                                <div class="help-box">
                                    <p><strong>Como adicionar:</strong></p>
                                    <p>• Arraste uma imagem do seu computador e solte em cada área acima</p>
                                    <p>• Ou clique nas áreas para escolher um arquivo</p>
                                    <p>• Ou cole o link de uma imagem da internet</p>
                                </div>
                                
                                <?php if ($i <= 4): ?>
                                <div class="product-select">
                                    <label>
                                        <i class="fas fa-hand-pointer"></i> 
                                        Quando alguém clicar nesta imagem, levar para qual produto? (opcional)
                                    </label>
                                    <select name="carousel_product_<?php echo $i; ?>" 
                                            id="carouselProduct<?php echo $i; ?>" 
                                            class="form-control-modern">
                                        <option value="">Nenhum produto específico</option>
                                        <?php foreach ($products as $prod): ?>
                                        <option value="<?php echo $prod['id']; ?>" 
                                                <?php echo ($carouselImg['product_id'] ?? null) == $prod['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="help-box" style="margin-top: 10px;">
                                        <p><strong>Dica:</strong> Se você escolher um produto aqui, quando alguém clicar nesta imagem do carrossel, será levado direto para a página desse produto.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Advanced Options (Collapsed by default) -->
                        <div class="simple-section">
                            <button type="button" class="advanced-toggle" onclick="toggleAdvanced()">
                                <span><i class="fas fa-cog"></i> Opções Avançadas (opcional)</span>
                                <i class="fas fa-chevron-down" id="advancedIcon"></i>
                            </button>
                            <div class="advanced-content" id="advancedContent">
                                <div style="padding-top: 20px;">
                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-tag"></i> Mostrar banners apenas para uma marca específica?
                                        </label>
                                        <select name="target_brand_id" id="targetBrandId" class="form-control-modern">
                                            <option value="">Todas as marcas (aparece sempre)</option>
                                            <?php foreach ($brandsList as $brand): ?>
                                            <option value="<?php echo $brand['id']; ?>" 
                                                    <?php echo ($carouselBanner['target_brand_id'] ?? null) == $brand['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($brand['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="help-box" style="margin-top: 10px;">
                                            <p><strong>Como funciona:</strong></p>
                                            <p>• Se você não escolher nenhuma marca, os banners aparecerão sempre na vitrine</p>
                                            <p>• Se você escolher uma marca, os banners só aparecerão quando o visitante filtrar por essa marca</p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group" style="margin-top: 20px;">
                                        <label>
                                            <i class="fas fa-box"></i> Produto padrão para quando não houver produto específico
                                        </label>
                                        <select name="target_product_id" id="targetProductId" class="form-control-modern">
                                            <option value="">Nenhum produto padrão</option>
                                            <?php foreach ($products as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>" 
                                                    <?php echo ($carouselBanner['target_product_id'] ?? null) == $prod['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prod['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="help-box" style="margin-top: 10px;">
                                            <p><strong>Dica:</strong> Este produto será usado quando alguém clicar em uma imagem que não tenha um produto específico definido.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </main>
        
        <!-- Fixed Save Button -->
        <div class="save-button-container">
            <button type="submit" form="carouselForm" class="btn-save">
                <i class="fas fa-save"></i> Salvar Tudo
            </button>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/image-upload.js"></script>
    <script>
        // Store carousel upload instances
        const carouselUploads = {};
        
        function initializeCarouselUploads() {
            if (typeof ImageUpload === 'undefined') {
                setTimeout(initializeCarouselUploads, 300);
                return;
            }
            
            <?php for ($i = 1; $i <= 8; $i++): ?>
            (function(index) {
                // Desktop
                const containerIdDesktop = 'carouselImageUpload' + index;
                const carouselContainerDesktop = document.getElementById(containerIdDesktop);
                const hiddenInputDesktop = document.getElementById('carouselImageUrl' + index);

                // Mobile
                const containerIdMobile = 'carouselImageUpload' + index + '_mobile';
                const carouselContainerMobile = document.getElementById(containerIdMobile);
                const hiddenInputMobile = document.getElementById('carouselImageUrl' + index + '_mobile');
                
                try {
                    if (carouselContainerDesktop && !carouselUploads['uploadDesktop' + index]) {
                        const desktopImage = hiddenInputDesktop ? hiddenInputDesktop.value : '';
                        carouselContainerDesktop.innerHTML = '';
                        const uploadInstanceDesktop = new ImageUpload(containerIdDesktop, {
                        uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
                        inputName: 'carousel_image_' + index,
                        folder: 'banners',
                            currentImage: desktopImage,
                        showLinkOption: true,
                        onUploadSuccess: function(url) {
                                if (hiddenInputDesktop) {
                                    hiddenInputDesktop.value = url;
                            }
                                const card = carouselContainerDesktop.closest('.image-card');
                            if (card) {
                                card.classList.add('has-image');
                            }
                        },
                        onUploadError: function(error) {
                            alert('Erro ao fazer upload: ' + (error.message || error));
                        }
                    });
                        carouselUploads['uploadDesktop' + index] = uploadInstanceDesktop;
                    
                    setTimeout(() => {
                            const linkInput = document.getElementById(containerIdDesktop + '_linkInput');
                            if (linkInput && hiddenInputDesktop) {
                            linkInput.addEventListener('input', function() {
                                    hiddenInputDesktop.value = this.value;
                                    const card = carouselContainerDesktop.closest('.image-card');
                                if (card && this.value) {
                                    card.classList.add('has-image');
                                }
                            });
                        }
                    }, 500);
                    }

                    if (carouselContainerMobile && !carouselUploads['uploadMobile' + index]) {
                        const mobileImage = hiddenInputMobile ? hiddenInputMobile.value : '';
                        carouselContainerMobile.innerHTML = '';
                        const uploadInstanceMobile = new ImageUpload(containerIdMobile, {
                            uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
                            inputName: 'carousel_image_mobile_' + index,
                            folder: 'banners',
                            currentImage: mobileImage,
                            showLinkOption: true,
                            onUploadSuccess: function(url) {
                                if (hiddenInputMobile) {
                                    hiddenInputMobile.value = url;
                                }
                                const card = carouselContainerMobile.closest('.image-card');
                                if (card) {
                                    card.classList.add('has-image');
                                }
                            },
                            onUploadError: function(error) {
                                alert('Erro ao fazer upload: ' + (error.message || error));
                            }
                        });
                        carouselUploads['uploadMobile' + index] = uploadInstanceMobile;

                        setTimeout(() => {
                            const linkInputMobile = document.getElementById(containerIdMobile + '_linkInput');
                            if (linkInputMobile && hiddenInputMobile) {
                                linkInputMobile.addEventListener('input', function() {
                                    hiddenInputMobile.value = this.value;
                                    const card = carouselContainerMobile.closest('.image-card');
                                    if (card && this.value) {
                                        card.classList.add('has-image');
                                    }
                                });
                            }
                        }, 500);
                    }
                } catch (error) {
                    console.error('Error initializing upload ' + index + ':', error);
                }
            })(<?php echo $i; ?>);
            <?php endfor; ?>
        }
        
        function initWhenReady() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(initializeCarouselUploads, 500);
                });
            } else {
                setTimeout(initializeCarouselUploads, 500);
            }
        }
        
        initWhenReady();
        
        // Advanced toggle
        function toggleAdvanced() {
            const content = document.getElementById('advancedContent');
            const icon = document.getElementById('advancedIcon');
            
            if (content.classList.contains('open')) {
                content.classList.remove('open');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                content.classList.add('open');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
        
        // Retry ImageUpload initialization
        let retryCount = 0;
        const maxRetries = 20;
        const retryInterval = setInterval(function() {
            retryCount++;
            if (typeof ImageUpload !== 'undefined') {
                clearInterval(retryInterval);
                if (Object.keys(carouselUploads).length === 0) {
                    initializeCarouselUploads();
                }
            } else if (retryCount >= maxRetries) {
                clearInterval(retryInterval);
            }
        }, 500);
    </script>
</body>
</html>
