<?php
// user/products.php - Vitrine de Perfumes
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user = getCurrentUser($pdo);
$is_vip_user = isVip() || isAdmin();
$whatsapp_number = getWhatsAppNumber($pdo);

// Check if user is inactive
$isInactive = ($user['office_status'] ?? 'inactive') !== 'active';
$userHasActiveOffice = !$isInactive;
$userStatus = $isInactive ? 'inactive' : 'active';

// Fetch Brands for filter - ONLY brands that have active products
$brands = [];
try {
    if (db_table_exists($pdo, 'brands')) {
        $stmt = $pdo->query("
            SELECT DISTINCT b.*, COUNT(p.id) as product_count
            FROM brands b
            INNER JOIN products p ON b.id = p.brand_id
            WHERE b.is_active = 1 
            AND p.is_active = 1
            GROUP BY b.id
            HAVING product_count > 0
            ORDER BY b.name ASC
        ");
        $brands = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Brands fetch error: " . $e->getMessage());
}

// Fetch Dynamic Showcases
$showcases = [];
if (db_table_exists($pdo, 'dynamic_showcases')) {
    try {
        $stmt = $pdo->query("
            SELECT ds.*, 
                   (SELECT COUNT(*) FROM dynamic_showcase_products dsp WHERE dsp.showcase_id = ds.id) as product_count
            FROM dynamic_showcases ds 
            WHERE ds.is_active = 1 
            ORDER BY ds.display_order ASC, ds.created_at DESC
        ");
        $showcases = $stmt->fetchAll();
        
        // Load products for each showcase
        foreach ($showcases as &$showcase) {
            if (db_table_exists($pdo, 'dynamic_showcase_products')) {
                // Verificar se a coluna description existe
                $hasDescription = db_has_column($pdo, 'products', 'description');
                
                // Construir query com colunas específicas
                $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
                if ($hasDescription) {
                    $columns .= ", p.description";
                }
                
                $productsStmt = $pdo->prepare("
                    SELECT $columns, b.name as brand_name 
                    FROM dynamic_showcase_products dsp
                    JOIN products p ON dsp.product_id = p.id
                    LEFT JOIN brands b ON p.brand_id = b.id
                    WHERE dsp.showcase_id = ? AND p.is_active = 1
                    ORDER BY dsp.display_order ASC
                    LIMIT " . ($showcase['max_products'] ?? 10) . "
                ");
                $productsStmt->execute([$showcase['id']]);
                $showcase['products'] = $productsStmt->fetchAll();
            } else {
                $showcase['products'] = [];
            }
        }
    } catch (PDOException $e) {
        error_log("Showcases fetch error: " . $e->getMessage());
    }
}

// Build query
$where = [];
$params = [];

// Check if is_active column exists
if (db_has_column($pdo, 'products', 'is_active')) {
    $where[] = "p.is_active = 1";
}

// Brand filter
if (!empty($_GET['brand'])) {
    $where[] = "p.brand_id = ?";
    $params[] = (int)$_GET['brand'];
}

// Search filter - busca em perfumes e marcas
if (!empty($_GET['search'])) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR b.name LIKE ?)";
    $term = "%" . $_GET['search'] . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

// Price filter
if (!empty($_GET['price'])) {
    $priceFilter = $_GET['price'];
    if ($priceFilter === '0-100') {
        $where[] = "p.price BETWEEN 0 AND 100";
    } elseif ($priceFilter === '100-200') {
        $where[] = "p.price BETWEEN 100 AND 200";
    } elseif ($priceFilter === '200-500') {
        $where[] = "p.price BETWEEN 200 AND 500";
    } elseif ($priceFilter === '500+') {
        $where[] = "p.price >= 500";
    }
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Sort order
$orderBy = "p.is_vip DESC, p.created_at DESC";
if (!empty($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'price_asc':
            $orderBy = "p.price ASC";
            break;
        case 'price_desc':
            $orderBy = "p.price DESC";
            break;
        case 'name':
            $orderBy = "p.name ASC";
            break;
        case 'newest':
        default:
            $orderBy = "p.created_at DESC";
            break;
    }
}

// Fetch Products
$products = [];
try {
    // Verificar se a coluna description existe
    $hasDescription = db_has_column($pdo, 'products', 'description');
    
    // Construir query com colunas específicas
    $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
    if ($hasDescription) {
        $columns .= ", p.description";
    }
    
    $sql = "SELECT $columns, b.name as brand_name 
            FROM products p 
            LEFT JOIN brands b ON p.brand_id = b.id 
            $whereClause 
            ORDER BY $orderBy";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Garantir que description existe no array mesmo se a coluna não existir
    if (!$hasDescription) {
        foreach ($products as &$product) {
            $product['description'] = null;
        }
        unset($product);
    }
} catch (PDOException $e) {
    error_log("Products fetch error: " . $e->getMessage());
}

// Get banners for vitrine
$banners = getBannersForPage($pdo, 'marketplace', $userStatus);

$pageTitle = 'Vitrine de Perfumes';
include __DIR__ . '/includes/header.php';
?>

<div class="vitrine-page">
    <!-- Banners -->
    <?php if (!empty($banners)): ?>
        <?php foreach ($banners as $banner): ?>
            <?php 
            $displayType = $banner['type'] ?? $banner['display_type'] ?? 'banner';
            if ($displayType === 'banner' || $displayType === 'split'): 
            ?>
                <?php echo renderBanner($banner, 'marketplace'); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Top Search and Filters Bar -->
    <div class="vitrine-top-bar">
        <div class="container">
            <div class="vitrine-top-bar-wrapper">
                <a href="<?php echo APP_URL; ?>/" class="logo-link-vitrine">
                    <?php 
                    $logoWidth = getSetting($pdo, 'logo_width', defined('LOGO_WIDTH') && LOGO_WIDTH !== 'auto' ? LOGO_WIDTH : 'auto');
                    $logoHeight = getSetting($pdo, 'logo_height', defined('LOGO_HEIGHT') && LOGO_HEIGHT !== 'auto' ? LOGO_HEIGHT : 'auto');
                    ?>
                    <img src="<?php echo LOGO_URL; ?>" alt="<?php echo APP_NAME; ?>" class="header-logo-vitrine"
                         style="width: <?php echo htmlspecialchars($logoWidth); ?>; 
                                height: <?php echo htmlspecialchars($logoHeight); ?>; 
                                max-width: 150px; 
                                max-height: 50px; 
                                object-fit: contain;">
                </a>
                <div class="search-filters-container">
                    <!-- Search Bar -->
                    <form method="GET" action="" class="vitrine-search-form">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Buscar perfumes ou marcas..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <?php if (!empty($_GET['search'])): ?>
                                <a href="?" class="clear-search" title="Limpar busca">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Filter Toggle (Mobile) -->
                    <button class="filter-toggle-btn" onclick="toggleFilters()" id="filterToggleBtn">
                        <i class="fas fa-filter"></i>
                        <span>Filtros</span>
                        <?php if (!empty($_GET['brand'])): ?>
                            <span class="filter-badge">1</span>
                        <?php endif; ?>
                    </button>

                    <!-- Filter Toggle (Desktop) -->
                    <button class="filter-toggle-btn-desktop" onclick="toggleDesktopFilters()" id="filterToggleBtnDesktop">
                        <i class="fas fa-filter"></i>
                        <span>Filtros</span>
                        <i class="fas fa-chevron-down" id="filterToggleIcon"></i>
                        <?php if (!empty($_GET['brand'])): ?>
                            <span class="filter-badge">1</span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>

            <!-- Quick Filters (Desktop) -->
            <div class="quick-filters-desktop" id="desktopFiltersPanel" style="display: none;">
                <button class="close-filters-btn" onclick="toggleDesktopFilters()" title="Fechar filtros">
                    <i class="fas fa-times"></i>
                </button>
                <div class="filter-section-desktop">
                    <label class="filter-label-desktop">
                        <i class="fas fa-tag"></i>
                        <span>Marca</span>
                    </label>
                    <div class="filter-chips-desktop">
                        <button class="filter-chip-desktop <?php echo empty($_GET['brand']) ? 'active' : ''; ?>" onclick="applyFilter('brand', '')">
                            Todas
                        </button>
                        <?php foreach ($brands as $brand): ?>
                            <button class="filter-chip-desktop <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'active' : ''; ?>" onclick="applyFilter('brand', '<?php echo $brand['id']; ?>')">
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($_GET['brand'])): ?>
                    <button onclick="clearAllFilters()" class="btn-clear-filters-desktop" title="Limpar todos os filtros">
                        <i class="fas fa-times"></i>
                        <span>Limpar Filtros</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Filters Panel -->
    <div class="mobile-filters-panel" id="mobileFiltersPanel">
        <div class="mobile-filters-header">
            <h3>Filtros</h3>
            <button onclick="toggleFilters()" class="close-filters-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-filters-content">
            <div class="filter-section-mobile">
                <label class="filter-label-mobile">Marca</label>
                <div class="filter-options-mobile">
                    <button class="filter-chip <?php echo empty($_GET['brand']) ? 'active' : ''; ?>" onclick="applyFilter('brand', '')">
                        Todas
                    </button>
                    <?php foreach ($brands as $brand): ?>
                        <button class="filter-chip <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'active' : ''; ?>" onclick="applyFilter('brand', '<?php echo $brand['id']; ?>')">
                            <?php echo htmlspecialchars($brand['name']); ?>
                            <span class="chip-count"><?php echo $brand['product_count']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="filter-section-mobile">
                <label class="filter-label-mobile">Preço</label>
                <div class="filter-options-mobile">
                    <button class="filter-chip <?php echo empty($_GET['price']) ? 'active' : ''; ?>" onclick="applyFilter('price', '')">
                        Todos
                    </button>
                    <button class="filter-chip <?php echo (isset($_GET['price']) && $_GET['price'] === '0-100') ? 'active' : ''; ?>" onclick="applyFilter('price', '0-100')">
                        R$ 0 - R$ 100
                    </button>
                    <button class="filter-chip <?php echo (isset($_GET['price']) && $_GET['price'] === '100-200') ? 'active' : ''; ?>" onclick="applyFilter('price', '100-200')">
                        R$ 100 - R$ 200
                    </button>
                    <button class="filter-chip <?php echo (isset($_GET['price']) && $_GET['price'] === '200-500') ? 'active' : ''; ?>" onclick="applyFilter('price', '200-500')">
                        R$ 200 - R$ 500
                    </button>
                    <button class="filter-chip <?php echo (isset($_GET['price']) && $_GET['price'] === '500+') ? 'active' : ''; ?>" onclick="applyFilter('price', '500+')">
                        R$ 500+
                    </button>
                </div>
            </div>

            <div class="filter-section-mobile">
                <label class="filter-label-mobile">Ordenar por</label>
                <select class="filter-select-mobile" onchange="applyFilter('sort', this.value)">
                    <option value="newest" <?php echo (empty($_GET['sort']) || $_GET['sort'] === 'newest') ? 'selected' : ''; ?>>Mais Recentes</option>
                    <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_asc') ? 'selected' : ''; ?>>Menor Preço</option>
                    <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_desc') ? 'selected' : ''; ?>>Maior Preço</option>
                    <option value="name" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'name') ? 'selected' : ''; ?>>Nome A-Z</option>
                </select>
            </div>

            <div class="mobile-filters-actions">
                <button onclick="clearAllFilters()" class="btn-clear-filters">Limpar Filtros</button>
                <button onclick="toggleFilters()" class="btn-apply-filters">Aplicar</button>
            </div>
        </div>
    </div>

    <!-- Dynamic Showcases -->
    <?php if (!empty($showcases)): ?>
        <?php foreach ($showcases as $showcase): ?>
            <?php if (!empty($showcase['products'])): ?>
            <section class="showcase-section">
                <div class="container">
                    <?php if (!empty($showcase['banner_url'])): ?>
                    <div class="showcase-banner">
                        <?php if (!empty($showcase['banner_link'])): ?>
                        <a href="<?php echo htmlspecialchars($showcase['banner_link']); ?>" target="_blank">
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars($showcase['banner_url']); ?>" 
                             alt="<?php echo htmlspecialchars($showcase['title']); ?>"
                             class="showcase-banner-img">
                        <?php if (!empty($showcase['banner_link'])): ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="showcase-header">
                        <h2 class="showcase-title"><?php echo htmlspecialchars($showcase['title']); ?></h2>
                        <?php if (!empty($showcase['description'])): ?>
                        <p class="showcase-description"><?php echo htmlspecialchars($showcase['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="products-grid-showcase">
                        <?php foreach ($showcase['products'] as $sp): ?>
                            <?php 
                                $whatsapp_link = generateWhatsAppLink($pdo, $sp['name']);
                                $product_url = APP_URL . '/product.php?id=' . $sp['id'];
                                
                                // Get product images (multiple images support)
                                $product_images = [];
                                if (db_table_exists($pdo, 'product_images')) {
                                    $imgStmt = $pdo->prepare("SELECT image_url, is_cover FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, display_order ASC");
                                    $imgStmt->execute([$sp['id']]);
                                    $product_images = $imgStmt->fetchAll();
                                }
                                
                                // Fallback to single image_path if no multiple images
                                $image_url = '';
                                if (!empty($product_images)) {
                                    $cover_image = null;
                                    foreach ($product_images as $img) {
                                        if ($img['is_cover'] == 1) {
                                            $cover_image = $img;
                                            break;
                                        }
                                    }
                                    $first_image = $cover_image ?? $product_images[0];
                                    $image_path = $first_image['image_url'] ?? '';
                                } else {
                                $image_path = $sp['image_path'] ?? '';
                                }
                                
                                if (!empty($image_path)) {
                                    $image_path = trim($image_path);
                                    if (preg_match('/^https?:\/\//i', $image_path)) {
                                        $image_url = $image_path;
                                    } else {
                                        $image_path = ltrim($image_path, '/');
                                        $image_url = rtrim(APP_URL, '/') . '/' . $image_path;
                                    }
                                }
                                
                                // Count total images for badge
                                $image_count = count($product_images);
                            ?>
                            <article class="product-card-modern">
                                <a href="<?php echo $product_url; ?>" class="product-card-link">
                                    <div class="product-image-modern">
                                        <?php if ($image_url): ?>
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($sp['name']); ?>"
                                                 loading="lazy"
                                                 class="product-img-modern"
                                                 onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                            <div class="product-image-placeholder-modern" style="display: none;">
                                                <i class="fas fa-spray-can"></i>
                                            </div>
                                            <?php if ($image_count > 1): ?>
                                                <div class="product-images-badge">
                                                    <i class="fas fa-images"></i>
                                                    <span><?php echo $image_count; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="product-image-placeholder-modern">
                                                <i class="fas fa-spray-can"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($sp['is_vip'] == 1): ?>
                                            <div class="product-vip-badge-modern">
                                                <span class="vip-star">⭐</span>
                                                <span>VIP</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-info-modern">
                                        <p class="product-brand-modern"><?php echo htmlspecialchars($sp['brand_name'] ?? 'Naipe da Gringa'); ?></p>
                                        <h3 class="product-name-modern"><?php echo htmlspecialchars($sp['name']); ?></h3>
                                        <div class="product-price-modern"><?php echo formatPrice($sp['price']); ?></div>
                                    </div>
                                </a>
                                
                                <div class="product-actions-modern">
                                    <a href="<?php echo $product_url; ?>" class="btn-view-details">
                                        <i class="fas fa-eye"></i>
                                        <span>Ver</span>
                                    </a>
                                    <?php if ($sp['is_vip'] == 1 && !$userHasActiveOffice): ?>
                                        <!-- VIP Product - User without active office -->
                                        <a href="<?php echo APP_URL; ?>/user/packages.php" class="btn-activate-office-modern">
                                            <i class="fas fa-briefcase"></i>
                                            <span>Ativar Escritório</span>
                                        </a>
                                    <?php else: ?>
                                        <!-- Regular product or user with active office -->
                                    <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="btn-whatsapp-modern">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>Comprar</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Main Products Section -->
    <section class="products-section">
        <div class="container">
            <div class="products-header">
                <h2 class="section-title">Todos os Perfumes</h2>
                <span class="products-count-badge"><?php echo count($products); ?> produto(s)</span>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="empty-state-wrapper">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-spray-can"></i>
                        </div>
                        <h3>Nada por aqui… ainda</h3>
                        <p>Os filtros podem estar limitando sua busca. Ajuste ou fale com um especialista para achar o perfume ideal pra você.</p>
                        <div class="empty-state-actions">
                            <a href="?" class="btn-clear-filters-empty">
                                <i class="fas fa-redo"></i>
                                <span>Limpar filtros</span>
                            </a>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>?text=<?php echo urlencode('Olá! Preciso de ajuda para encontrar um perfume.'); ?>" 
                               target="_blank" 
                               class="btn-expert-empty">
                                <i class="fas fa-comments"></i>
                                <span>Falar com especialista</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="products-grid-main">
                    <?php foreach ($products as $p): ?>
                        <?php 
                            $whatsapp_link = generateWhatsAppLink($pdo, $p['name']);
                            $product_url = APP_URL . '/product.php?id=' . $p['id'];
                            
                            // Get product images (multiple images support)
                            $product_images = [];
                            if (db_table_exists($pdo, 'product_images')) {
                                $imgStmt = $pdo->prepare("SELECT image_url, is_cover FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, display_order ASC");
                                $imgStmt->execute([$p['id']]);
                                $product_images = $imgStmt->fetchAll();
                            }
                            
                            // Fallback to single image_path if no multiple images
                            $image_url = '';
                            if (!empty($product_images)) {
                                $cover_image = null;
                                foreach ($product_images as $img) {
                                    if ($img['is_cover'] == 1) {
                                        $cover_image = $img;
                                        break;
                                    }
                                }
                                $first_image = $cover_image ?? $product_images[0];
                                $image_path = $first_image['image_url'] ?? '';
                            } else {
                            $image_path = $p['image_path'] ?? '';
                            }
                            
                            if (!empty($image_path)) {
                                $image_path = trim($image_path);
                                if (preg_match('/^https?:\/\//i', $image_path)) {
                                    $image_url = $image_path;
                                } else {
                                    $image_path = ltrim($image_path, '/');
                                    $image_url = rtrim(APP_URL, '/') . '/' . $image_path;
                                }
                            }
                            
                            // Count total images for badge
                            $image_count = count($product_images);
                        ?>
                        <article class="product-card-modern">
                            <a href="<?php echo $product_url; ?>" class="product-card-link">
                                <div class="product-image-modern">
                                    <?php if ($image_url): ?>
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                             alt="<?php echo htmlspecialchars($p['name']); ?>"
                                             loading="lazy"
                                             class="product-img-modern"
                                             onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                        <div class="product-image-placeholder-modern" style="display: none;">
                                            <i class="fas fa-spray-can"></i>
                                        </div>
                                        <?php if ($image_count > 1): ?>
                                            <div class="product-images-badge">
                                                <i class="fas fa-images"></i>
                                                <span><?php echo $image_count; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="product-image-placeholder-modern">
                                            <i class="fas fa-spray-can"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($p['is_vip'] == 1): ?>
                                        <div class="product-vip-badge-modern">
                                            <span class="vip-star">⭐</span>
                                            <span>VIP</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-info-modern">
                                    <p class="product-brand-modern"><?php echo htmlspecialchars($p['brand_name'] ?? 'Naipe da Gringa'); ?></p>
                                    <h3 class="product-name-modern"><?php echo htmlspecialchars($p['name']); ?></h3>
                                    <div class="product-price-modern"><?php echo formatPrice($p['price']); ?></div>
                                </div>
                            </a>
                            
                            <div class="product-actions-modern">
                                <a href="<?php echo $product_url; ?>" class="btn-view-details">
                                    <i class="fas fa-eye"></i>
                                    <span>Ver</span>
                                </a>
                                <?php if ($p['is_vip'] == 1 && !$userHasActiveOffice): ?>
                                    <!-- VIP Product - User without active office -->
                                    <a href="<?php echo APP_URL; ?>/user/packages.php" class="btn-activate-office-modern">
                                        <i class="fas fa-briefcase"></i>
                                        <span>Ativar Escritório</span>
                                    </a>
                                <?php else: ?>
                                    <!-- Regular product or user with active office -->
                                <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="btn-whatsapp-modern">
                                    <i class="fab fa-whatsapp"></i>
                                    <span>Comprar</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Support Footer -->
    <footer class="vitrine-support-footer">
        <div class="container">
            <div class="support-content">
                <div class="support-icon-large">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h2 class="support-title-large">Não achou o que procurava?</h2>
                <p class="support-text-large">
                    Nosso time encontra o perfume perfeito pra você em minutos.<br>
                    Sem erro. Sem enrolação.
                </p>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>?text=<?php echo urlencode('Olá! Preciso de ajuda para encontrar um perfume.'); ?>" 
                   target="_blank" 
                   class="btn-support-whatsapp-large">
                    <i class="fab fa-whatsapp"></i>
                    <span>Falar com Suporte</span>
                </a>
            </div>
        </div>
    </footer>
</div>

<!-- Pop-up Banners -->
<?php if (!empty($banners)): ?>
    <?php foreach ($banners as $banner): ?>
        <?php if ($banner['display_type'] === 'popup'): ?>
            <?php echo renderBanner($banner, 'marketplace'); ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<style>
/* Vitrine Page */
.vitrine-page {
    min-height: 100vh;
    background: #0F0F0F;
    padding-bottom: 0;
}

/* Top Bar */
.vitrine-top-bar {
    background: #1F1F1F;
    border-bottom: 1px solid #2A2A2A;
    padding: 1.5rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
}

@media (min-width: 769px) {
    .vitrine-top-bar {
        padding: 1.75rem 0;
    }
}

.vitrine-top-bar-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
}

.logo-link-vitrine {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    justify-content: center;
}

.header-logo-vitrine {
    max-width: 120px;
    max-height: 40px;
    object-fit: contain;
}

.search-filters-container {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 0;
    flex-wrap: wrap;
    justify-content: center;
}

/* Desktop Layout */
@media (min-width: 769px) {
    .vitrine-top-bar-wrapper {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        gap: 2rem;
    }
    
    .logo-link-vitrine {
        justify-content: flex-start;
    }
    
    .header-logo-vitrine {
        max-width: 150px;
        max-height: 50px;
    }
    
    .search-filters-container {
        justify-content: flex-end;
        flex-wrap: nowrap;
        gap: 1.5rem;
    }
}

@media (min-width: 769px) {
    .search-filters-container {
        margin-bottom: 1rem;
    }
}

.vitrine-search-form {
    flex: 1;
    max-width: 600px;
}

@media (min-width: 1200px) {
    .vitrine-search-form {
        max-width: 700px;
    }
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #666666;
    font-size: 1rem;
    pointer-events: none;
    z-index: 1;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid #2A2A2A;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.2s;
    background: #1F1F1F;
    color: #B3B3B3;
    box-sizing: border-box;
    line-height: 1.5;
}

.search-input:focus {
    outline: none;
    border-color: #C7A333;
    background: #1F1F1F;
    box-shadow: 0 0 0 3px rgba(199, 163, 51, 0.1);
}

.clear-search {
    position: absolute;
    right: 0.75rem;
    color: #666666;
    text-decoration: none;
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    transition: all 0.2s;
}

.clear-search:hover {
    background: #2A2A2A;
    color: #B3B3B3;
}

.filter-toggle-btn {
    display: none;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: #1F1F1F;
    border: 2px solid #2A2A2A;
    border-radius: 12px;
    font-weight: 600;
    color: #B3B3B3;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.filter-toggle-btn:hover {
    border-color: #C7A333;
    color: #C7A333;
}

/* Desktop Filter Toggle Button */
.filter-toggle-btn-desktop {
    display: none;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: #C7A333;
    border: 2px solid #C7A333;
    border-radius: 12px;
    font-weight: 600;
    color: #000000;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    margin-top: 1rem;
}

.filter-toggle-btn-desktop:hover {
    background: #D4B84A;
    border-color: #D4B84A;
    transform: translateY(-1px);
}

.filter-toggle-btn-desktop i.fa-chevron-down,
.filter-toggle-btn-desktop i.fa-chevron-up {
    font-size: 0.75rem;
    transition: transform 0.3s ease;
}

.filter-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

/* Quick Filters Desktop */
.quick-filters-desktop {
    display: none;
    flex-direction: column;
    gap: 1.25rem;
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid #2A2A2A;
    animation: slideDown 0.3s ease;
    position: relative;
}

.quick-filters-desktop .close-filters-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 10;
}

.quick-filters-desktop.show {
    display: flex;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.filter-section-desktop {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.filter-label-desktop {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #B3B3B3;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.filter-label-desktop i {
    color: #C7A333;
    font-size: 0.875rem;
}

.filter-chips-desktop {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.filter-chip-desktop {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.125rem;
    border: 2px solid #2A2A2A;
    border-radius: 24px;
    background: #1F1F1F;
    color: #B3B3B3;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    font-family: 'Inter', sans-serif;
}

.filter-chip-desktop:hover {
    border-color: #C7A333;
    color: #C7A333;
    background: rgba(199, 163, 51, 0.05);
    transform: translateY(-1px);
}

.filter-chip-desktop.active {
    background: #C7A333;
    border-color: #C7A333;
    color: #000000;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(199, 163, 51, 0.3);
}

.filter-chip-desktop.active:hover {
    background: #D4B84A;
    border-color: #D4B84A;
    transform: translateY(-1px);
}

.filter-chip-desktop i {
    font-size: 0.75rem;
}

/* Removed chip-count-desktop - não mostrar contadores */

.btn-clear-filters-desktop {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    background: #2A2A2A;
    border: 2px solid #2A2A2A;
    border-radius: 24px;
    color: #B3B3B3;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 0.5rem;
    align-self: flex-start;
}

.btn-clear-filters-desktop:hover {
    background: #333333;
    border-color: #C7A333;
    color: #C7A333;
    transform: translateY(-1px);
}

/* Mobile Filters Panel */
.mobile-filters-panel {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow-y: auto;
}

.mobile-filters-panel.active {
    display: block;
}

.mobile-filters-header {
    background: #1F1F1F;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #2A2A2A;
    position: sticky;
    top: 0;
    z-index: 10;
}

.mobile-filters-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #B3B3B3;
}

.close-filters-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #666666;
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    transition: all 0.2s;
}

.close-filters-btn:hover {
    background: #2A2A2A;
    color: #B3B3B3;
}

.mobile-filters-content {
    background: #1F1F1F;
    padding: 1.5rem;
    min-height: calc(100vh - 80px);
}

.filter-section-mobile {
    margin-bottom: 2rem;
}

.filter-label-mobile {
    display: block;
    font-weight: 600;
    color: #B3B3B3;
    margin-bottom: 0.75rem;
    font-size: 0.9375rem;
}

.filter-options-mobile {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-chip {
    padding: 0.625rem 1rem;
    border: 2px solid #2A2A2A;
    border-radius: 20px;
    background: #1F1F1F;
    color: #B3B3B3;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-chip:hover {
    border-color: #C7A333;
    color: #C7A333;
}

.filter-chip.active {
    background: #C7A333;
    border-color: #C7A333;
    color: #000000;
}

.chip-count {
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

.filter-chip.active .chip-count {
    background: rgba(255, 255, 255, 0.3);
}

.filter-select-mobile {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #2A2A2A;
    border-radius: 12px;
    font-size: 1rem;
    background: #1F1F1F;
    color: #B3B3B3;
    cursor: pointer;
}

.mobile-filters-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #2A2A2A;
}

.btn-clear-filters,
.btn-apply-filters {
    flex: 1;
    padding: 1rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-clear-filters {
    background: #2A2A2A;
    color: #B3B3B3;
}

.btn-clear-filters:hover {
    background: #333333;
}

.btn-apply-filters {
    background: #C7A333;
    color: #000000;
}

.btn-apply-filters:hover {
    background: #D4B84A;
}

/* Showcase Section */
.showcase-section {
    padding: 3rem 0;
    background: #1F1F1F;
    margin-bottom: 2rem;
}

.showcase-banner {
    margin-bottom: 2rem;
    border-radius: 16px;
    overflow: hidden;
}

.showcase-banner-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 16px;
}

.showcase-header {
    text-align: center;
    margin-bottom: 2rem;
}

.showcase-title {
    font-size: 2rem;
    font-weight: 700;
    color: #B3B3B3;
    margin: 0 0 0.5rem 0;
}

.showcase-description {
    font-size: 1.125rem;
    color: #B3B3B3;
    margin: 0;
}

.products-grid-showcase {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 2rem;
}

/* Desktop Showcase Grid Optimization */
@media (min-width: 1200px) {
    .products-grid-showcase {
        grid-template-columns: repeat(5, 1fr);
        gap: 2rem;
    }
}

@media (min-width: 1400px) {
    .products-grid-showcase {
        grid-template-columns: repeat(6, 1fr);
        gap: 2.25rem;
    }
}

/* Products Section */
.products-section {
    padding: 2rem 0;
    background: linear-gradient(180deg, #FFFFFF 0%, #FAFAFA 100%);
    min-height: 60vh;
}

.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #000000;
    margin: 0;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.products-count-badge {
    background: rgba(199, 163, 51, 0.1);
    color: #999999;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.8125rem;
    font-weight: 500;
    opacity: 0.7;
}

.products-grid-main {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 2rem;
}

/* Desktop Grid Optimization - Better spacing and sizing */
@media (min-width: 1200px) {
    .products-grid-main {
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
    }
    
    .product-card-modern {
        min-height: 480px;
    }
    
    .product-actions-modern {
        padding: 1rem 1.25rem;
        gap: 0.75rem;
    }
    
    .btn-view-details,
    .btn-whatsapp-modern {
        padding: 0.875rem 1rem;
        font-size: 0.9375rem;
    }
}

@media (min-width: 1400px) {
    .products-grid-main {
        grid-template-columns: repeat(5, 1fr);
        gap: 2rem;
    }
}

@media (min-width: 1600px) {
    .products-grid-main {
        grid-template-columns: repeat(5, 1fr);
        gap: 2.25rem;
    }
}

/* Modern Product Card */
/* Product Card - Desktop Improvements */
@media (min-width: 769px) {
    .product-card-modern {
        display: flex;
        flex-direction: column;
        min-height: 450px;
    }
    
    .product-info-modern {
        flex: 1;
        padding: 1.25rem;
    }
    
    .product-actions-modern {
        margin-top: auto;
        padding: 1rem 1.25rem;
        border-top: 1px solid #2A2A2A;
        display: flex;
        gap: 0.75rem;
    }
    
    .btn-view-details,
    .btn-whatsapp-modern {
        flex: 1;
        padding: 0.875rem 1rem;
        font-size: 0.9375rem;
        font-weight: 600;
    }
}

.product-card-modern {
    background: #1F1F1F;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    border: 1px solid #2A2A2A;
}

.product-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    border-color: #C7A333;
}

.product-card-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.product-image-modern {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    background: #0F0F0F;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-img-modern {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card-modern:hover .product-img-modern {
    transform: scale(1.05);
}

.product-image-placeholder-modern {
    font-size: 3rem;
    color: #666666;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.product-vip-badge-modern {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: linear-gradient(135deg, #C7A333 0%, #B8962E 100%);
    color: #000000;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 2px 8px rgba(199, 163, 51, 0.3);
}

.vip-star {
    font-size: 0.875rem;
}

.product-images-badge {
    position: absolute;
    bottom: 0.75rem;
    left: 0.75rem;
    background: rgba(0, 0, 0, 0.7);
    color: #ffffff;
    padding: 0.375rem 0.625rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    backdrop-filter: blur(4px);
    z-index: 2;
}

.product-images-badge i {
    font-size: 0.875rem;
}

.product-info-modern {
    padding: 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.product-brand-modern {
    font-size: 0.75rem;
    font-weight: 600;
    color: #C7A333;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
}

.product-name-modern {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #B3B3B3;
    margin: 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.8em;
}

.product-price-modern {
    font-size: 1.25rem;
    font-weight: 700;
    color: #C7A333;
    margin-top: auto;
}

.product-actions-modern {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    margin-top: auto;
    border-top: 1px solid #2A2A2A;
}

.btn-view-details,
.btn-whatsapp-modern {
    flex: 1;
    padding: 0.75rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-view-details {
    background: #C7A333;
    color: #000000;
}

.btn-view-details:hover {
    background: #D4B84A;
    color: #000000;
}

.btn-whatsapp-modern {
    background: #25d366;
    color: #ffffff;
}

.btn-whatsapp-modern:hover {
    background: #20ba5a;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
}

/* Activate Office Button (for VIP products without active office) */
.btn-activate-office-modern {
    flex: 1;
    padding: 0.75rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    background: linear-gradient(135deg, #C7A333 0%, #B8962E 100%);
    color: #000000;
}

.btn-activate-office-modern:hover {
    background: linear-gradient(135deg, #D4B84A 0%, #C7A333 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.3);
}

/* Support Footer */
.vitrine-support-footer {
    background: rgba(199, 163, 51, 0.15);
    border: 1px solid rgba(199, 163, 51, 0.3);
    padding: 4rem 2rem;
    margin-top: 4rem;
    color: #B3B3B3;
    border-radius: 14px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.support-content {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
}

.support-icon-large {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.1) 0%, 
        var(--color-primary) 50%, 
        rgba(255, 255, 255, 0.1) 100%);
    border-radius: 50%;
    border: 2px solid var(--color-primary);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4),
                0 0 0 8px rgba(199, 163, 51, 0.1),
                0 0 30px rgba(199, 163, 51, 0.3);
    position: relative;
    animation: iconPulse 2s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { 
        transform: scale(1);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4),
                    0 0 0 8px rgba(199, 163, 51, 0.1),
                    0 0 30px rgba(199, 163, 51, 0.3);
    }
    50% { 
        transform: scale(1.05);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4),
                    0 0 0 8px rgba(199, 163, 51, 0.2),
                    0 0 40px rgba(199, 163, 51, 0.5);
    }
}

.support-icon-large i {
    font-size: 2.5rem;
    color: #000000 !important;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.3));
    animation: iconGlow 2s ease-in-out infinite;
}

@keyframes iconGlow {
    0%, 100% { 
        filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.3)) 
                drop-shadow(0 0 10px rgba(199, 163, 51, 0.3));
    }
    50% { 
        filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.3)) 
                drop-shadow(0 0 20px rgba(199, 163, 51, 0.6));
    }
}

.support-title-large {
    font-size: 2.25rem;
    font-weight: 700;
    margin: 0 0 1rem 0;
    color: #B3B3B3;
}

.support-text-large {
    font-size: 1.125rem;
    line-height: 1.7;
    margin: 0 0 2rem 0;
    opacity: 0.95;
    color: #B3B3B3;
}

.btn-support-whatsapp-large {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2.5rem;
    background: #25d366;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    font-size: 1.125rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 6px 20px rgba(199, 163, 51, 0.6),
                0 0 30px rgba(199, 163, 51, 0.5);
}

.btn-support-whatsapp-large:hover {
    background: linear-gradient(135deg, #D4B84A 0%, #E0C55F 100%);
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(199, 163, 51, 0.8),
                0 0 50px rgba(199, 163, 51, 0.7),
                0 0 80px rgba(199, 163, 51, 0.4);
}

.btn-support-whatsapp-large i {
    font-size: 1.5rem;
}

/* Empty State */
/* Empty State */
.empty-state-wrapper {
    max-width: 600px;
    margin: 0 auto;
}

.empty-state-quote {
    text-align: center;
    font-size: 1.25rem;
    font-style: italic;
    color: #C7A333;
    font-weight: 500;
    margin: 0 0 2rem 0;
    letter-spacing: 0.01em;
    opacity: 0.9;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #FFFFFF 0%, #F8F8F8 100%);
    border-radius: 20px;
    border: 2px solid rgba(199, 163, 51, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08),
                0 0 0 1px rgba(199, 163, 51, 0.1);
    position: relative;
    overflow: hidden;
}

.empty-state::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(199, 163, 51, 0.05) 0%, transparent 70%);
    animation: pulse-glow 4s ease-in-out infinite;
}

@keyframes pulse-glow {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.1); }
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    color: #C7A333;
    position: relative;
    z-index: 1;
    animation: icon-float 3s ease-in-out infinite;
    filter: drop-shadow(0 4px 12px rgba(199, 163, 51, 0.3));
}

@keyframes icon-float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.empty-state-icon i {
    border: 3px solid #C7A333;
    border-radius: 50%;
    padding: 1.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 120px;
    height: 120px;
    background: rgba(199, 163, 51, 0.05);
}

.empty-state h3 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #000000;
    margin: 0 0 1rem 0;
    position: relative;
    z-index: 1;
    letter-spacing: -0.01em;
}

.empty-state p {
    color: #666666;
    margin: 0 0 2rem 0;
    font-size: 1.0625rem;
    line-height: 1.6;
    position: relative;
    z-index: 1;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.empty-state-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.btn-clear-filters-empty,
.btn-expert-empty {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid;
}

.btn-clear-filters-empty {
    background: transparent;
    border-color: #C7A333;
    color: #C7A333;
}

.btn-clear-filters-empty:hover {
    background: #C7A333;
    color: #FFFFFF !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.3);
}

.btn-clear-filters-empty:hover span,
.btn-clear-filters-empty:hover i {
    color: #FFFFFF !important;
}

.btn-expert-empty {
    background: linear-gradient(135deg, #C7A333 0%, #D4B84A 100%);
    border-color: #C7A333;
    color: #000000 !important;
}

.btn-expert-empty span,
.btn-expert-empty i {
    color: #000000 !important;
}

.btn-expert-empty:hover {
    background: linear-gradient(135deg, #D4B84A 0%, #E0C55F 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(199, 163, 51, 0.4);
    color: #000000 !important;
}

.btn-expert-empty:hover span,
.btn-expert-empty:hover i {
    color: #000000 !important;
}

/* Responsive */
@media (max-width: 1024px) {
    .products-grid-main {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    
    .products-grid-showcase {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem;
    }
}

/* Desktop - Show filter toggle button */
@media (min-width: 769px) {
    .filter-toggle-btn-desktop {
        display: flex;
    }
    
    .filter-toggle-btn {
        display: none;
    }
}

/* Desktop - Show filter toggle button */
@media (min-width: 769px) {
    .filter-toggle-btn-desktop {
        display: flex !important;
    }
    
    .filter-toggle-btn {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .vitrine-top-bar {
        padding: 1rem 0;
    }
    
    .vitrine-top-bar-wrapper {
        gap: 1rem;
    }
    
    .logo-link-vitrine {
        justify-content: center;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #2A2A2A;
    }
    
    .header-logo-vitrine {
        max-width: 100px;
        max-height: 35px;
    }
    
    .search-filters-container {
        flex-direction: column;
        gap: 0.75rem;
        width: 100%;
    }

    .vitrine-search-form {
        max-width: 100%;
        width: 100%;
    }
    
    .search-input {
        font-size: 16px; /* Evita zoom no iOS */
    }

    .filter-toggle-btn {
        display: flex;
        width: 100%;
        justify-content: center;
        padding: 1rem;
        font-size: 1rem;
        background: #C7A333;
        color: #000000;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(199, 163, 51, 0.3);
    }
    
    .filter-toggle-btn:active {
        transform: scale(0.98);
        background: #D4B84A;
    }
    
    .filter-toggle-btn-desktop {
        display: none !important;
    }

    .quick-filters-desktop {
        display: none !important;
    }

    .products-grid-main,
    .products-grid-showcase {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .products-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .section-title {
        font-size: 1.5rem;
    }

    .showcase-title {
        font-size: 1.5rem;
    }

    .support-title-large {
        font-size: 1.75rem;
    }

    .support-text-large {
        font-size: 1rem;
    }

    .support-icon-large {
        font-size: 3rem;
    }

    .vitrine-support-footer {
        padding: 3rem 1.5rem;
    }
}

@media (max-width: 768px) {
    .section-title {
        font-size: 2rem;
    }
    
    .empty-state-quote {
        font-size: 1.125rem;
        margin-bottom: 1.5rem;
    }
    
    .empty-state {
        padding: 2.5rem 1.5rem;
    }
    
    .empty-state-icon i {
        width: 100px;
        height: 100px;
        padding: 1.25rem;
    }
    
    .empty-state-actions {
        flex-direction: column;
    }
    
    .btn-clear-filters-empty,
    .btn-expert-empty {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .products-grid-main,
    .products-grid-showcase {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    .product-info-modern {
        padding: 0.75rem;
    }

    .product-name-modern {
        font-size: 0.875rem;
        min-height: 2.4em;
    }
    
    .section-title {
        font-size: 1.75rem;
    }
    
    .empty-state-quote {
        font-size: 1rem;
    }

    .product-price-modern {
        font-size: 1.125rem;
    }

    .product-actions-modern {
        flex-direction: column;
        gap: 0.5rem;
        padding: 0 0.75rem 0.75rem 0.75rem;
    }

    .btn-view-details,
    .btn-whatsapp-modern {
        width: 100%;
    }

    .support-title-large {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Mobile filters toggle
function toggleFilters() {
    const panel = document.getElementById('mobileFiltersPanel');
    panel.classList.toggle('active');
    document.body.style.overflow = panel.classList.contains('active') ? 'hidden' : '';
}

// Desktop filters toggle
function toggleDesktopFilters() {
    const panel = document.getElementById('desktopFiltersPanel');
    const icon = document.getElementById('filterToggleIcon');
    
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'flex';
        panel.classList.add('show');
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    } else {
        panel.style.display = 'none';
        panel.classList.remove('show');
        if (icon) {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }
}

// Initialize: Keep filters closed if no active filters
document.addEventListener('DOMContentLoaded', function() {
    const hasActiveFilters = <?php echo (!empty($_GET['brand']) || !empty($_GET['price']) || !empty($_GET['sort'])) ? 'true' : 'false'; ?>;
    const panel = document.getElementById('desktopFiltersPanel');
    const icon = document.getElementById('filterToggleIcon');
    
    // If there are active filters, show panel; otherwise keep it closed
    if (!hasActiveFilters && panel) {
        panel.style.display = 'none';
        if (icon) {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    } else if (hasActiveFilters && panel) {
        panel.style.display = 'flex';
        panel.classList.add('show');
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    }
});

function applyFilter(type, value) {
    const url = new URL(window.location.href);
    if (value) {
        url.searchParams.set(type, value);
    } else {
        url.searchParams.delete(type);
    }
    window.location.href = url.toString();
}

function clearAllFilters() {
    window.location.href = window.location.pathname;
}

// Auto-submit search on Enter
document.querySelector('.search-input')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.closest('form').submit();
    }
});

// Close mobile filters on outside click
document.getElementById('mobileFiltersPanel')?.addEventListener('click', function(e) {
    if (e.target === this) {
        toggleFilters();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
