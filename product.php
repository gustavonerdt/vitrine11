<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Public product page - no login required

$productId = (int)($_GET['id'] ?? 0);

if (!$productId) {
    header('Location: ' . APP_URL . '/');
    exit;
}

// Fetch product
$product = null;
try {
    // Verificar se a coluna description existe
    $hasDescription = db_has_column($pdo, 'products', 'description');
    
    // Construir query com colunas específicas
    $columns = "p.id, p.brand_id, p.name, p.price, p.original_price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
    if ($hasDescription) {
        $columns .= ", p.description";
    }
    
    $stmt = $pdo->prepare("
        SELECT $columns, b.name as brand_name, b.description as brand_description
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    // Garantir que description existe no array mesmo se a coluna não existir
    if (!$hasDescription && $product) {
        $product['description'] = null;
    }
} catch (PDOException $e) {
    error_log("Product fetch error: " . $e->getMessage());
}

if (!$product) {
    header('Location: ' . APP_URL . '/');
    exit;
}

// Fetch product variants if table exists
$productVariants = [];
if (db_table_exists($pdo, 'product_variants')) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY display_order ASC, price ASC");
        $stmt->execute([$productId]);
        $productVariants = $stmt->fetchAll();
        
        // Normalize variant image paths
        foreach ($productVariants as &$variant) {
            if (!empty($variant['image_path'])) {
                $img_path = trim($variant['image_path']);
                if (preg_match('/^https?:\/\//i', $img_path)) {
                    $variant['image_url'] = $img_path;
                } else {
                    $img_path = ltrim($img_path, '/');
                    $variant['image_url'] = rtrim(APP_URL, '/') . '/' . $img_path;
                }
            } else {
                $variant['image_url'] = '';
            }
        }
        unset($variant); // Break reference
    } catch (PDOException $e) {
        error_log("Product variants fetch error: " . $e->getMessage());
    }
}

// Get recommended products (same brand or random)
$recommendedProducts = [];
try {
    // Verificar se a coluna description existe
    $hasDescription = db_has_column($pdo, 'products', 'description');
    
    // Construir query com colunas específicas
    $columns = "p.id, p.brand_id, p.name, p.price, p.original_price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
    if ($hasDescription) {
        $columns .= ", p.description";
    }
    
    $stmt = $pdo->prepare("
        SELECT $columns, b.name as brand_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id != ? AND p.is_active = 1
        " . ($product['brand_id'] ? "AND (p.brand_id = ? OR p.brand_id IS NOT NULL)" : "") . "
        ORDER BY " . ($product['brand_id'] ? "p.brand_id = ? DESC, " : "") . "RAND()
        LIMIT 10
    ");
    $params = [$productId];
    if ($product['brand_id']) {
        $params[] = $product['brand_id'];
        $params[] = $product['brand_id'];
    }
    $stmt->execute($params);
    $recommendedProducts = $stmt->fetchAll();
    
    // Garantir que description existe no array mesmo se a coluna não existir
    if (!$hasDescription) {
        foreach ($recommendedProducts as &$recProduct) {
            $recProduct['description'] = null;
        }
        unset($recProduct);
    }
} catch (PDOException $e) {
    error_log("Recommended products error: " . $e->getMessage());
}

// Get product images (multiple images support)
$product_images = [];
$normalized_images = [];

// Try to get images from product_images table
if (db_table_exists($pdo, 'product_images')) {
    try {
        $imgStmt = $pdo->prepare("SELECT image_url, is_cover, display_order FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, display_order ASC, id ASC LIMIT 5");
        $imgStmt->execute([$productId]);
        $product_images = $imgStmt->fetchAll();
        error_log("Product images found: " . count($product_images) . " for product ID: $productId");
        
        // Debug: log each image
        foreach ($product_images as $idx => $img) {
            error_log("Image $idx: " . ($img['image_url'] ?? 'empty'));
        }
    } catch (PDOException $e) {
        error_log("Product images fetch error: " . $e->getMessage());
    }
}

// Normalize image paths from product_images table
foreach ($product_images as $img) {
    $img_path = $img['image_url'] ?? '';
    if (!empty($img_path)) {
        $img_path = trim($img_path);
        if (preg_match('/^https?:\/\//i', $img_path)) {
            $normalized_images[] = $img_path;
        } else {
            $img_path = ltrim($img_path, '/');
            $normalized_images[] = rtrim(APP_URL, '/') . '/' . $img_path;
        }
    }
}

// Fallback to single image_path if no multiple images
if (empty($normalized_images)) {
    $image_path = $product['image_path'] ?? '';
    if (!empty($image_path)) {
        $image_path = trim($image_path);
        if (preg_match('/^https?:\/\//i', $image_path)) {
            $normalized_images[] = $image_path;
        } else {
            $image_path = ltrim($image_path, '/');
            $normalized_images[] = rtrim(APP_URL, '/') . '/' . $image_path;
        }
    }
}

$image_url = !empty($normalized_images) ? $normalized_images[0] : '';
error_log("Total normalized images: " . count($normalized_images) . " for product ID: $productId");

$whatsapp_number = getWhatsAppNumber($pdo);
$whatsapp_link = generateWhatsAppLink($pdo, $product['name']);

$pageTitle = htmlspecialchars($product['name']);
$bodyClass = 'product-page';
include __DIR__ . '/includes/public-header.php';

// Se for uma requisição AJAX, apenas retorna o conteúdo principal
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $is_ajax = true;
} else {
    $is_ajax = false;
}

if (!$is_ajax) {
    echo '<div class="public-main-content">';
}
?>



<div class="product-page-wrapper">
    <!-- Breadcrumb -->
    <nav class="product-breadcrumb">
        <div class="container">
            <a  href="<?php echo APP_URL; ?>/"><b>Inicío</b> / <f style="color:gray;">Vitrine de Perfumes</f></a>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current"><?php echo htmlspecialchars($product['name']); ?></span>
        </div>
    </nav>

<style> .products-section{background-color:#fef9e0;}</style>

    <!-- Main Product Section -->
    <div class="container">
        <div class="product-detail-container">
            <!-- Left: Product Images Gallery - Layout com miniaturas na lateral -->
            <div class="product-image-container">
                <div class="product-image-gallery product-image-gallery-lateral">
                    <!-- Thumbnail Gallery na Lateral (esquerda) -->
                    <?php 
                    $image_count = count($normalized_images);
                    if (!empty($normalized_images) && $image_count > 1): 
                    ?>
                        <div class="product-thumbnails product-thumbnails-lateral">
                            <?php foreach ($normalized_images as $index => $thumb_url): ?>
                                <?php if (!empty($thumb_url)): ?>
                                <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     data-image-index="<?php echo $index; ?>"
                                     onclick="changeMainImage('<?php echo htmlspecialchars($thumb_url, ENT_QUOTES); ?>', <?php echo $index; ?>)">
                                    <img src="<?php echo htmlspecialchars($thumb_url); ?>" 
                                         alt="Imagem <?php echo $index + 1; ?>"
                                         loading="lazy"
                                         onerror="console.error('Erro ao carregar thumbnail:', this.src); this.onerror=null; this.parentElement.style.display='none';">
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Main Image -->
                    <div class="product-main-image-wrapper">
                        <?php if (!empty($normalized_images)): ?>
                            <img src="<?php echo htmlspecialchars($normalized_images[0]); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="product-main-image"
                                 id="mainProductImage"
                                 onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                            <div class="product-image-placeholder-modern" style="display: none;" data-logo-url="<?php echo htmlspecialchars(LOGO_URL); ?>">
                                <div class="placeholder-icon-wrapper">
                                    <i class="fas fa-spray-can"></i>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="product-image-placeholder-modern" data-logo-url="<?php echo htmlspecialchars(LOGO_URL); ?>">
                                <div class="placeholder-icon-wrapper">
                                    <i class="fas fa-spray-can"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $hasDiscountBadge = !empty($product['original_price']) && floatval($product['original_price']) > floatval($product['price']);
                        $discountPercentBadge = $hasDiscountBadge ? round(((floatval($product['original_price']) - floatval($product['price'])) / floatval($product['original_price'])) * 100) : 0;
                        ?>
                        <?php if ($hasDiscountBadge): ?>
                            <div class="product-discount-badge" style="position: absolute; top: 15px; left: 15px; background: #ef4444; color: #fff; padding: 6px 12px; border-radius: 8px; font-size: 0.9rem; font-weight: 700; z-index: 5; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);">
                                -<?php echo $discountPercentBadge; ?>% OFF
                            </div>
                        <?php endif; ?>
                        <?php if ($product['is_vip'] == 1): ?>
                            <div class="product-vip-badge">
                                <span class="vip-badge">⭐ VIP</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Product Info -->
            <div class="product-info-container">
                <div class="product-header-section">
                    <?php if (!empty($product['brand_name'])): ?>
                        <div class="product-brand-badge">
                            <?php echo htmlspecialchars($product['brand_name']); ?>
                        </div>
                    <?php endif; ?>
                    <h1 class="product-title-main"><?php echo htmlspecialchars($product['name']); ?></h1>
                </div>

                <!-- Price Section -->
                <?php 
                $hasDiscount = !empty($product['original_price']) && floatval($product['original_price']) > floatval($product['price']);
                $discountPercent = $hasDiscount ? round(((floatval($product['original_price']) - floatval($product['price'])) / floatval($product['original_price'])) * 100) : 0;
                $economy = $hasDiscount ? floatval($product['original_price']) - floatval($product['price']) : 0;
                ?>
                <div class="product-price-section">
                    <div class="price-main">
                        <?php if ($hasDiscount): ?>
                        <div class="price-discount-wrapper">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <span style="background: #ef4444; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">-<?php echo $discountPercent; ?>% OFF</span>
                                <span style="text-decoration: line-through; color: #888; font-size: 1rem;">R$ <?php echo number_format($product['original_price'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="price-range">
                                <span class="price-label" style="color: #22c55e;">Por apenas</span>
                                <span class="price-value" style="color: #22c55e; font-size: 2rem;">R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                            </div>
                            <div style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(34, 197, 94, 0.05)); border: 1px solid #22c55e; border-radius: 8px; padding: 8px 12px; margin-top: 8px; display: inline-block;">
                                <span style="color: #22c55e; font-weight: 600; font-size: 0.9rem;">Voce economiza R$ <?php echo number_format($economy, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="price-range">
                            <span class="price-label">Preco</span>
                            <span class="price-value">R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Variants Section -->
                <?php if (!empty($productVariants)): ?>
                    <div class="product-variants-section">
                        <h3 class="section-title">Escolha uma Opção</h3>
                        <div class="variants-grid">
                            <?php foreach ($productVariants as $variant): ?>
                                <div class="variant-option" 
                                     data-variant-id="<?php echo htmlspecialchars($variant['id']); ?>"
                                     data-price="<?php echo htmlspecialchars($variant['price']); ?>"
                                     data-points="<?php echo htmlspecialchars($variant['points'] ?? 0); ?>"
                                     data-image-url="<?php echo htmlspecialchars($variant['image_url'] ?? ''); ?>">
                                    <input type="radio" 
                                           id="variant_<?php echo htmlspecialchars($variant['id']); ?>" 
                                           name="product_variant" 
                                           value="<?php echo htmlspecialchars($variant['id']); ?>"
                                           <?php echo $loop->first ? 'checked' : ''; ?>>
                                    <label for="variant_<?php echo htmlspecialchars($variant['id']); ?>" class="variant-label">
                                        <span class="variant-name"><?php echo htmlspecialchars($variant['name']); ?></span>
                                        <span class="variant-price-display">R$ <?php echo number_format($variant['price'], 2, ',', '.'); ?></span>
                                        <?php if (!empty($variant['points'])): ?>
                                            <span class="variant-points"><?php echo htmlspecialchars($variant['points']); ?> pontos</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Actions Section -->
                <div class="product-actions-section">
                    <a href="#" class="btn-whatsapp-modern btn-add-cart" data-product-id="<?php echo htmlspecialchars($productId); ?>" onclick="addToCart(<?php echo $productId; ?>); return false;">
                        <i class="fas fa-shopping-cart"></i>
                        <span>ADICIONAR <br>AO CARRINHO</span>
                    </a>
                </div>

                <!-- Calculadora de Frete -->
                <div class="shipping-calculator-section">
                    <h4 class="shipping-calculator-title">
                        <i class="fas fa-truck"></i>
                        Calcular Frete e Prazo
                    </h4>
                    <div class="shipping-calculator-form">
                        <input type="text" id="shipping-cep" placeholder="Digite seu CEP" maxlength="9">
                        <button type="button" id="calc-shipping-btn" onclick="calculateShipping()">
                            Calcular
                        </button>
                    </div>
                    <div id="shipping-results" class="shipping-results">
                        <!-- Resultados aparecem aqui -->
                    </div>
                    
                    <!-- Opções de Frete (aparecem após calcular) -->
                    <div id="shipping-options-container" class="shipping-options-product" style="display: none;">
                        <h5 class="shipping-options-title">Escolha uma Opcao</h5>
                        <div class="shipping-option-card" data-code="04510" data-price="37.50" data-days="10">
                            <div class="shipping-option-radio">
                                <input type="radio" name="shipping_option_product" id="ship_pac" value="04510">
                            </div>
                            <div class="shipping-option-info">
                                <strong class="shipping-option-name">PAC (Correios)</strong>
                                <span class="shipping-option-days">Chega em ate 10 dias uteis</span>
                            </div>
                            <span class="shipping-option-price">R$ 37,50</span>
                        </div>
                        <div class="shipping-option-card" data-code="04014" data-price="56.25" data-days="5">
                            <div class="shipping-option-radio">
                                <input type="radio" name="shipping_option_product" id="ship_sedex" value="04014">
                            </div>
                            <div class="shipping-option-info">
                                <strong class="shipping-option-name">SEDEX (Correios)</strong>
                                <span class="shipping-option-days">Chega em ate 5 dias uteis</span>
                            </div>
                            <span class="shipping-option-price">R$ 56,25</span>
                        </div>
                    </div>
                </div>
                
                <style>
                .shipping-options-product {
                    margin-top: 1rem;
                }
                
                .shipping-options-product .shipping-options-title {
                    font-size: 0.95rem;
                    font-weight: 700;
                    color: #1a1a1a;
                    margin: 0 0 0.75rem 0;
                    text-transform: uppercase;
                }
                
                .shipping-options-product .shipping-option-card {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem;
                    background: #f8f8f8;
                    border: 2px solid #e5e5e5;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    margin-bottom: 0.5rem;
                }
                
                .shipping-options-product .shipping-option-card:hover {
                    border-color: #C7A333;
                    background: #fef9e0;
                }
                
                .shipping-options-product .shipping-option-card.selected {
                    border-color: #C7A333;
                    background: #fef9e0;
                }
                
                .shipping-options-product .shipping-option-radio {
                    flex-shrink: 0;
                }
                
                .shipping-options-product .shipping-option-radio input[type="radio"] {
                    width: 20px;
                    height: 20px;
                    accent-color: #C7A333;
                    cursor: pointer;
                }
                
                .shipping-options-product .shipping-option-info {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 0.25rem;
                }
                
                .shipping-options-product .shipping-option-name {
                    font-size: 0.95rem;
                    color: #1a1a1a;
                }
                
                .shipping-options-product .shipping-option-days {
                    font-size: 0.8rem;
                    color: #666;
                }
                
                .shipping-options-product .shipping-option-price {
                    font-weight: 700;
                    font-size: 1rem;
                    color: #C7A333;
                }
                </style>

<style> @keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(212, 175, 55, 0.7);
    }
    70% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(212, 175, 55, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(212, 175, 55, 0);
    }
}

a.btn-whatsapp-modern.btn-add-cart {
    animation: pulse 6s infinite ease-in-out;
}</style>

                <!-- Product Details -->
                <div class="product-details-section">
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">Disponível</span>
                    </div>
                    <?php if (!empty($product['brand_name'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Marca</span>
                            <span class="detail-value"><?php echo htmlspecialchars($product['brand_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommended Products Section - CARROSSEL -->
    <?php if (!empty($recommendedProducts)): ?>
        <div class="recommended-products-section">
            <div class="container">
                <h2 class="section-heading">Produtos Recomendados</h2>
                <div class="carousel-wrapper">
                    <button class="carousel-nav carousel-prev" id="carouselPrev" aria-label="Anterior">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="carousel-container">
                        <div class="recommended-products-carousel" id="recommendedCarousel">
                            <?php foreach ($recommendedProducts as $recProduct): ?>
                                <div class="carousel-item">
                                    <div class="recommended-product-card">
                                        <a href="<?php echo APP_URL; ?>/product.php?id=<?php echo htmlspecialchars($recProduct['id']); ?>" class="recommended-product-link">
                                            <div class="recommended-product-image">
                                                <?php 
                                                $rec_image_path = $recProduct['image_path'] ?? '';
                                                if (!empty($rec_image_path)) {
                                                    $rec_image_path = trim($rec_image_path);
                                                    if (preg_match('/^https?:\/\//i', $rec_image_path)) {
                                                        $rec_image_url = $rec_image_path;
                                                    } else {
                                                        $rec_image_path = ltrim($rec_image_path, '/');
                                                        $rec_image_url = rtrim(APP_URL, '/') . '/' . $rec_image_path;
                                                    }
                                                }
                                                ?>
                                                <?php if (!empty($rec_image_url)): ?>
                                                    <img src="<?php echo htmlspecialchars($rec_image_url); ?>" alt="<?php echo htmlspecialchars($recProduct['name']); ?>" loading="lazy">
                                                <?php else: ?>
                                                    <div class="recommended-product-placeholder">
                                                        <i class="fas fa-spray-can"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($recProduct['is_vip'] == 1): ?>
                                                    <div class="recommended-vip-badge">⭐ VIP</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="recommended-product-info">
                                                <h3 class="recommended-product-name"><?php echo htmlspecialchars($recProduct['name']); ?></h3>
                                                <?php if (!empty($recProduct['brand_name'])): ?>
                                                    <p class="recommended-product-brand"><?php echo htmlspecialchars($recProduct['brand_name']); ?></p>
                                                <?php endif; ?>
                                                <p class="recommended-product-price">R$ <?php echo number_format($recProduct['price'], 2, ',', '.'); ?></p>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="carousel-nav carousel-next" id="carouselNext" aria-label="Próximo">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Support Footer -->
    <div class="vitrine-support-footer">
        <div class="support-content-wrapper">
            <div class="support-content">
                <div class="support-icon-large">
                    <i class="fas fa-headset"></i>
                </div>
                <h2 class="support-title-large">Dúvidas?</h2>
                <p class="support-text-large">Entre em contato conosco via WhatsApp. Nossa equipe está pronta para ajudar!</p>
                <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" target="_blank" class="btn-support-whatsapp-large">
                    <i class="fab fa-whatsapp"></i>
                    Fale Conosco
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== IMPROVED CSS - NOTA 10 ===== */

/* Root Variables */
:root {
    --color-primary: #C7A333;
    --color-primary-light: #D4B84A;
    --color-primary-dark: #B8962E;
    --color-beige: #E8DCC8;
    --color-beige-light: #F5F1E8;
    --color-beige-dark: #D9CFBC;
    --color-text-light: #E0E0E0;
    --color-text-muted: #B3B3B3;
    --color-background: #fef9e0;
    --color-surface: #FFFFFF;
    --color-border: rgba(199, 163, 51, 0.2);
    --color-shadow: rgba(0, 0, 0, 0.15);
    --color-shadow-dark: rgba(0, 0, 0, 0.3);
}

/* Scrollbar Customizada da Pagina */
::-webkit-scrollbar {
    width: 14px;
}

::-webkit-scrollbar-track {
    background: #f5f5f5;
}

::-webkit-scrollbar-thumb {
    background: #000000;
    border-radius: 10px;
    cursor: grab;
}

::-webkit-scrollbar-thumb:hover {
    background: #333333;
    cursor: grab;
}

/* Firefox */
html {
    scrollbar-color: #000000 #f5f5f5;
    scrollbar-width: thin;
}

/* Product Breadcrumb */
.product-breadcrumb {
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(135deg, rgba(248, 245, 235, 0.5) 0%, rgba(255, 255, 255, 0.3) 100%);
    margin-bottom: 2rem;
}

.product-breadcrumb a,
.product-breadcrumb span {
    color: var(--color-text-muted);
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    transition: color 0.3s ease;
}

.product-breadcrumb a:hover {
    color: var(--color-primary);
}

.breadcrumb-separator {
    margin: 0 0.75rem;
    color: var(--color-primary);
}

/* Product Detail Container */
.product-detail-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    padding: 2rem 0;
    align-items: start;
}

.product-image-container {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-image-gallery {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-main-image-wrapper {
    position: relative;
    background: #1F1F1F;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    border: 1px solid #2A2A2A;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    will-change: transform;
}

.product-main-image {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
    background: #1F1F1F;
    cursor: zoom-in;
    transition: transform 0.3s ease;
    will-change: transform;
}

.product-main-image:hover {
    transform: scale(1.02);
}

/* Thumbnails Gallery */
.product-thumbnails {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.75rem;
    max-width: 100%;
}

@media (max-width: 768px) {
    .product-thumbnails {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .product-thumbnails {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }
}

.thumbnail-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid var(--color-beige-dark);
    background: linear-gradient(135deg, var(--color-beige-light) 0%, var(--color-beige) 100%);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0.7;
}

.thumbnail-item:hover {
    opacity: 1;
    border-color: var(--color-primary);
    transform: translateY(-3px);
    box-shadow: 0 6px 16px var(--color-shadow-dark);
}

.thumbnail-item.active {
    opacity: 1;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(199, 163, 51, 0.3);
}

/* Galeria com Miniaturas na Lateral */
.product-image-gallery-lateral {
    display: flex;
    flex-direction: row;
    gap: 1rem;
}

.product-thumbnails-lateral {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 80px;
    flex-shrink: 0;
    max-height: 500px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--color-primary) transparent;
}

.product-thumbnails-lateral::-webkit-scrollbar {
    width: 4px;
}

.product-thumbnails-lateral::-webkit-scrollbar-track {
    background: transparent;
}

.product-thumbnails-lateral::-webkit-scrollbar-thumb {
    background: var(--color-primary);
    border-radius: 4px;
}

.product-image-gallery-lateral .product-main-image-wrapper {
    flex: 1;
    min-width: 0;
}

.product-thumbnails-lateral .thumbnail-item {
    width: 100%;
    aspect-ratio: 1;
}

@media (max-width: 768px) {
    .product-image-gallery-lateral {
        flex-direction: column-reverse;
    }
    
    .product-thumbnails-lateral {
        flex-direction: row;
        width: 100%;
        max-height: none;
        overflow-x: auto;
        overflow-y: hidden;
        gap: 0.5rem;
    }
    
    .product-thumbnails-lateral .thumbnail-item {
        width: 60px;
        flex-shrink: 0;
    }
}

/* Calculadora de Frete */
.shipping-calculator-section {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(248, 245, 235, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
    border-radius: 14px;
    border: 1.5px solid var(--color-border);
}

.shipping-calculator-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2C2C2C;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Inter', sans-serif;
}

.shipping-calculator-title i {
    color: var(--color-primary);
}

.shipping-calculator-form {
    display: flex;
    gap: 0.75rem;
    align-items: stretch;
}

.shipping-calculator-form input[type="text"] {
    flex: 1;
    padding: 0.875rem 1rem;
    border: 2px solid var(--color-beige-dark);
    border-radius: 10px;
    font-size: 1rem;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s;
    background: #fff;
}

.shipping-calculator-form input[type="text"]:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(199, 163, 51, 0.15);
}

.shipping-calculator-form button {
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: #000;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Inter', sans-serif;
    white-space: nowrap;
}

.shipping-calculator-form button:hover {
    background: linear-gradient(135deg, var(--color-primary-light) 0%, var(--color-primary) 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.3);
}

.shipping-calculator-form button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.shipping-results {
    margin-top: 1rem;
    display: none;
}

.shipping-results.show {
    display: block;
}

.shipping-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #fff;
    border: 1px solid var(--color-beige-dark);
    border-radius: 10px;
    margin-bottom: 0.5rem;
    transition: all 0.3s;
}

.shipping-option:hover {
    border-color: var(--color-primary);
    box-shadow: 0 2px 8px rgba(199, 163, 51, 0.15);
}

.shipping-option-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.shipping-option-name {
    font-weight: 600;
    color: #2C2C2C;
    font-size: 0.95rem;
}

.shipping-option-days {
    font-size: 0.85rem;
    color: #666;
}

.shipping-option-price {
    font-weight: 700;
    color: var(--color-primary);
    font-size: 1.1rem;
}

.shipping-error {
    padding: 1rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 10px;
    color: #dc2626;
    font-size: 0.9rem;
}

.shipping-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    color: #666;
}

.shipping-loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Product Image Placeholder */
.product-image-placeholder-modern {
    font-size: 3rem;
    color: #999;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    position: relative;
    background: linear-gradient(135deg, var(--color-beige-light) 0%, var(--color-beige) 100%);
    overflow: hidden;
    background-size: 60% auto;
    background-repeat: no-repeat;
    background-position: center;
}

.product-image-placeholder-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: 60% auto;
    background-repeat: no-repeat;
    background-position: center;
    filter: blur(<?php 
        $logoBlurPercent = intval(getSetting($pdo, 'product_placeholder_logo_blur', 20));
        $logoBlurPx = ($logoBlurPercent / 100) * 10;
        echo number_format($logoBlurPx, 2); 
    ?>px);
    z-index: 0;
    opacity: 0.15;
    pointer-events: none;
}

.product-image-placeholder-modern::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(248, 245, 235, 0.3);
    z-index: 0;
    pointer-events: none;
}

.placeholder-icon-wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.placeholder-icon-wrapper i {
    color: #B8A080;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    font-size: 3rem;
}

.product-vip-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 10;
}

.vip-badge {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: #000000;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.875rem;
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.4);
    font-family: 'Inter', sans-serif;
}

/* Product Info Container */
.product-info-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    padding: 2.5rem;
    background: linear-gradient(135deg, rgba(248, 245, 235, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
    border-radius: 16px;
    box-shadow: 0 12px 40px var(--color-shadow-dark);
    border: 1px solid var(--color-beige-dark);
    backdrop-filter: blur(10px);
}

.product-header-section {
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--color-border);
    position: relative;
}

.product-header-section::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 80px;
    height: 2px;
    background: linear-gradient(90deg, var(--color-primary) 0%, transparent 100%);
}

.product-brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.15) 0%, rgba(199, 163, 51, 0.08) 100%);
    color: var(--color-primary);
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    font-family: 'Inter', sans-serif;
    border: 1.5px solid rgba(199, 163, 51, 0.4);
    box-shadow: 0 3px 10px rgba(199, 163, 51, 0.15);
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.product-brand-badge::before {
    content: '🏷️';
    font-size: 1rem;
}

.product-title-main {
    font-size: 2.75rem;
    font-weight: 800;
    color: #2C2C2C;
    margin: 0;
    line-height: 1.2;
    font-family: 'Sora', sans-serif;
    letter-spacing: -0.02em;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

/* Price Section */
.product-price-section {
    padding: 2rem;
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.12) 0%, rgba(199, 163, 51, 0.06) 100%);
    border-radius: 14px;
    border: 1.5px solid var(--color-border);
    box-shadow: 0 6px 20px rgba(199, 163, 51, 0.15);
}

.price-main {
    display: flex;
    align-items: baseline;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.price-range {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.price-label {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.price-value {
    font-size: 3rem;
    font-weight: 800;
    color: var(--color-primary);
    font-family: 'Sora', sans-serif;
    text-shadow: 0 2px 6px rgba(199, 163, 51, 0.25);
    line-height: 1;
}

.points-indicator {
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.2) 0%, rgba(199, 163, 51, 0.1) 100%);
    color: var(--color-primary);
    padding: 0.6rem 1.2rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 700;
    font-family: 'Inter', sans-serif;
    border: 1px solid rgba(199, 163, 51, 0.3);
    box-shadow: 0 2px 8px rgba(199, 163, 51, 0.15);
}

/* Variants Section */
.product-variants-section {
    padding: 2rem;
    background: linear-gradient(135deg, rgba(232, 220, 200, 0.5) 0%, rgba(245, 241, 232, 0.5) 100%);
    border-radius: 14px;
    border: 1.5px solid var(--color-beige-dark);
}

.section-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: #2C2C2C;
    margin-bottom: 1.5rem;
    font-family: 'Sora', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title::before {
    content: '';
    width: 4px;
    height: 24px;
    background: linear-gradient(180deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
    border-radius: 2px;
}

.variants-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.variant-option {
    position: relative;
}

.variant-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.variant-label {
    display: block;
    padding: 1.25rem 1rem;
    border: 2px solid var(--color-beige-dark);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(135deg, rgba(248, 245, 235, 0.8) 0%, rgba(255, 255, 255, 0.9) 100%);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.variant-label::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(199, 163, 51, 0.15), transparent);
    transition: left 0.5s;
}

.variant-label:hover::before {
    left: 100%;
}

.variant-label:hover {
    border-color: var(--color-primary);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px var(--color-shadow);
}

.variant-option input[type="radio"]:checked + .variant-label {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.15) 0%, rgba(199, 163, 51, 0.08) 100%);
    box-shadow: 0 0 0 3px rgba(199, 163, 51, 0.2), 0 6px 20px rgba(199, 163, 51, 0.25);
    transform: translateY(-2px);
}

.variant-name {
    font-weight: 600;
    color: #2C2C2C;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
}

.variant-price-display {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 0.25rem;
    font-family: 'Sora', sans-serif;
}

.variant-points {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    font-family: 'Inter', sans-serif;
}

/* Actions Section */
.product-actions-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0;
}

/* Botão Adicionar ao Carrinho */
a.btn-whatsapp-modern[data-product-id] {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    padding: 12px 25px 12px 55px;
    border-radius: 12px;
    text-decoration: none;
    background: #0f0f0f;
    border: 1px solid #b8860b;
    box-shadow:
        0 4px 15px rgba(0,0,0,0.5),
        inset 0 0 8px rgba(184,134,11,0.15);
    transition: all 0.4s ease;
    width: 100%;
}

a.btn-whatsapp-modern[data-product-id] i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(145deg, #d4af37, #b8860b);
    color: #0f0f0f;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    transition: all 0.5s ease;
}

a.btn-whatsapp-modern[data-product-id] span {
    color: black;
    font-weight: 800;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
    transition: all 0.5s ease;
    line-height: 1.2;
}

a.btn-whatsapp-modern[data-product-id]:hover {
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    border-color: #ffd700;
    box-shadow:
        0 8px 25px rgba(184,134,11,0.35),
        0 0 20px rgba(212,175,55,0.15);
    transform: translateY(-2px);
}

a.btn-whatsapp-modern[data-product-id]:hover i {
    left: calc(100% - 46px);
    transform: translateY(-50%) rotate(360deg);
    background: linear-gradient(145deg, #ffd700, #d4af37);
    box-shadow: 0 4px 15px rgba(212,175,55,0.5);
}

a.btn-whatsapp-modern[data-product-id]:hover span {
    color: #ffd700;
    text-shadow: 0 0 10px rgba(212,175,55,0.4);
    transform: translateX(-34px);
}

@keyframes pulse-glow {
    0%   { box-shadow: 0 0 0 0 rgba(212,175,55,0.12); }
    70%  { box-shadow: 0 0 0 15px rgba(212,175,55,0); }
    100% { box-shadow: 0 0 0 0 rgba(212,175,55,0); }
}

a.btn-whatsapp-modern[data-product-id]:hover {
    animation: pulse-glow 1.8s infinite ease-in-out;
}

@media (max-width: 768px) {
    a.btn-whatsapp-modern[data-product-id] {
        padding: 10px 20px 10px 50px;
        font-size: 12px;
    }
    
    a.btn-whatsapp-modern[data-product-id] i {
        width: 30px;
        height: 30px;
        font-size: 18px;
        left: 10px;
    }
    
    a.btn-whatsapp-modern[data-product-id] span {
        font-size: 12px;
    }
}

/* Notificações */
.custom-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    z-index: 10000;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.custom-notification.show {
    transform: translateX(0);
}

.custom-notification.success {
    background: #25d366;
}

.custom-notification.error {
    background: #e74c3c;
}

.custom-notification.info {
    background: #3498db;
}

.btn-buy-now {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1.25rem 2rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
    color: #000000;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 6px 20px rgba(199, 163, 51, 0.3);
    font-family: 'Inter', sans-serif;
    letter-spacing: 0.05em;
}

.btn-buy-now:hover {
    background: linear-gradient(135deg, var(--color-primary-light) 0%, #E0C55F 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(199, 163, 51, 0.4);
}

.btn-buy-now:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.3);
}

/* Product Details Section */
.product-details-section {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--color-border);
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-label {
    font-size: 0.85rem;
    color: var(--color-text-muted);
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.detail-value {
    font-size: 1rem;
    color: #2C2C2C;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
}

/* ===== RECOMMENDED PRODUCTS GRID RESPONSIVO ===== */
.recommended-products-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, rgba(248, 245, 235, 0.5) 0%, rgba(255, 255, 255, 0.3) 100%);
    margin-top: 4rem;
    border-top: 2px solid var(--color-border);
}

.section-heading {
    font-size: 2rem;
    font-weight: 800;
    color: #2C2C2C;
    margin-bottom: 3rem;
    text-align: center;
    font-family: 'Sora', sans-serif;
    letter-spacing: -0.02em;
    position: relative;
    padding-bottom: 1.5rem;
}

.section-heading::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--color-primary), transparent);
    border-radius: 2px;
}

/* Grid Responsivo */
.recommended-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 2rem;
    width: 100%;
}

.recommended-product-card {
    background: #FFFFFF;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #E8E8E8;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.recommended-product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.1) 0%, transparent 100%);
    opacity: 0;
    transition: opacity 0.4s ease;
    pointer-events: none;
    z-index: 5;
}

.recommended-product-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 50px rgba(199, 163, 51, 0.25);
    border-color: var(--color-primary);
}

.recommended-product-card:hover::before {
    opacity: 1;
}

.recommended-product-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.recommended-product-image {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    background: linear-gradient(135deg, var(--color-beige-light) 0%, var(--color-beige) 100%);
    overflow: hidden;
}

.recommended-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.recommended-product-card:hover .recommended-product-image img {
    transform: scale(1.1);
}

.recommended-product-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #B8A080;
}

.recommended-vip-badge {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: #000000;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    font-family: 'Inter', sans-serif;
    z-index: 10;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(8px);
}

.recommended-product-info {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.recommended-product-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #2C2C2C;
    margin: 0 0 0.5rem 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-family: 'Inter', sans-serif;
    transition: color 0.3s ease;
}

.recommended-product-card:hover .recommended-product-name {
    color: var(--color-primary);
}

.recommended-product-brand {
    font-size: 0.9rem;
    color: #999999;
    margin: 0 0 0.75rem 0;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
}

.recommended-product-price {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--color-primary);
    font-family: 'Sora', sans-serif;
    margin: 0;
    text-shadow: 0 2px 4px rgba(199, 163, 51, 0.15);
}

/* Carousel Styles */
.carousel-wrapper {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    width: 100%;
    position: relative;
}

.carousel-container {
    flex: 1;
    overflow: hidden;
    border-radius: 12px;
}

.recommended-products-carousel {
    display: flex;
    gap: 1.5rem;
    scroll-behavior: smooth;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    padding: 0.5rem 0;
    scrollbar-width: none;
}

.recommended-products-carousel::-webkit-scrollbar {
    display: none;
}

.carousel-item {
    flex: 0 0 calc(25% - 1.125rem);
    scroll-snap-align: start;
    scroll-snap-stop: always;
}

.carousel-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid var(--color-primary);
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.1) 0%, rgba(199, 163, 51, 0.05) 100%);
    color: var(--color-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.carousel-nav:hover {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: #FFFFFF;
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(199, 163, 51, 0.3);
}

.carousel-nav:active {
    transform: scale(0.95);
}

/* Responsive Carousel */
@media (max-width: 1200px) {
    .carousel-item {
        flex: 0 0 calc(33.333% - 1rem);
    }
}

@media (max-width: 768px) {
    .carousel-item {
        flex: 0 0 calc(50% - 0.75rem);
    }
    
    .carousel-wrapper {
        gap: 1rem;
    }
    
    .carousel-nav {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .carousel-item {
        flex: 0 0 calc(100% - 0.5rem);
    }
    
    .carousel-wrapper {
        gap: 0.75rem;
    }
    
    .carousel-nav {
        width: 36px;
        height: 36px;
        font-size: 0.9rem;
    }
    
    .recommended-products-carousel {
        gap: 1rem;
    }
}

/* Support Footer */
.vitrine-support-footer {
    padding: 4rem 2rem;
    margin-top: 4rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.support-content-wrapper {
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.15) 0%, 
        var(--color-primary) 43%, 
        rgba(255, 255, 255, 0.15) 100%);
    padding: 2px;
    border-radius: 1.5rem;
    box-shadow: 0px 1rem 1.5rem -0.9rem rgba(0, 0, 0, 0.88);
    position: relative;
    overflow: hidden;
}

.support-content-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 100%);
    pointer-events: none;
}

.support-content {
    background: linear-gradient(135deg, rgba(248, 245, 235, 0.95) 0%, rgba(255, 255, 255, 0.98) 100%);
    border-radius: 1.5rem;
    padding: 3rem 2.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    position: relative;
    z-index: 1;
}

.support-icon-large {
    font-size: 3rem;
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, rgba(199, 163, 51, 0.15) 0%, rgba(199, 163, 51, 0.08) 100%);
    border-radius: 50%;
    border: 2px solid rgba(199, 163, 51, 0.3);
}

.support-title-large {
    font-size: 2rem;
    font-weight: 800;
    color: #2C2C2C;
    margin: 0;
    font-family: 'Sora', sans-serif;
    letter-spacing: -0.02em;
}

.support-text-large {
    font-size: 1.1rem;
    color: var(--color-text-muted);
    margin: 0;
    line-height: 1.6;
    font-family: 'Inter', sans-serif;
}

.btn-support-whatsapp-large {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
    color: #000000;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 6px 20px rgba(199, 163, 51, 0.3);
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    letter-spacing: 0.05em;
}

.btn-support-whatsapp-large:hover {
    background: linear-gradient(135deg, var(--color-primary-light) 0%, #E0C55F 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(199, 163, 51, 0.4);
}

.btn-support-whatsapp-large:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.3);
}

/* ===== MEDIA QUERIES RESPONSIVAS ===== */

/* Tablet (768px - 1024px) */
@media (max-width: 1024px) {
    .product-detail-container {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .product-image-container {
        position: static;
    }
    
    .product-main-image-wrapper {
        min-height: 350px;
    }
    
    .product-thumbnails {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
    }
    
    .product-title-main {
        font-size: 1.75rem;
    }
    
    .price-value {
        font-size: 1.75rem;
    }
    
    .variants-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .section-heading {
        font-size: 1.75rem;
        margin-bottom: 2.5rem;
    }
    
    .recommended-products-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1.5rem;
    }
    
    .recommended-product-card {
        border-radius: 14px;
    }
    
    .recommended-product-info {
        padding: 1.25rem;
    }
    
    .recommended-product-name {
        font-size: 1rem;
    }
    
    .recommended-product-price {
        font-size: 1.25rem;
    }
    
    .support-title-large {
        font-size: 1.75rem;
    }
    
    .support-text-large {
        font-size: 1rem;
    }
}

/* Mobile (até 480px) */
@media (max-width: 480px) {
    .product-breadcrumb {
        font-size: 0.75rem;
    }
    
    .product-title-main {
        font-size: 1.5rem;
    }
    
    .price-value {
        font-size: 1.5rem;
    }
    
    .product-thumbnails {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }
    
    .variants-grid {
        grid-template-columns: 1fr;
    }
    
    .section-heading {
        font-size: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .recommended-products-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    
    .recommended-product-card {
        border-radius: 12px;
    }
    
    .recommended-product-info {
        padding: 1rem;
    }
    
    .recommended-product-name {
        font-size: 0.95rem;
        margin-bottom: 0.35rem;
    }
    
    .recommended-product-brand {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }
    
    .recommended-product-price {
        font-size: 1.15rem;
    }
    
    .vitrine-support-footer {
        padding: 3rem 1.5rem;
    }
    
    .support-content {
        padding: 2.5rem 2rem;
    }
    
    .support-title-large {
        font-size: 1.5rem;
    }
    
    .support-text-large {
        font-size: 0.95rem;
    }
    
    .btn-support-whatsapp-large {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Store product images for fallback
const productImages = <?php echo json_encode($normalized_images); ?>;
const defaultProductImage = productImages.length > 0 ? productImages[0] : '';

// Parallax effect na imagem do produto
function initParallax() {
    const productDetailContainer = document.querySelector('.product-detail-container');
    const mainImageWrapper = document.querySelector('.product-main-image-wrapper');
    
    if (!productDetailContainer || !mainImageWrapper) return;
    
    window.addEventListener('scroll', function() {
        const containerRect = productDetailContainer.getBoundingClientRect();
        const containerTop = containerRect.top;
        const containerHeight = containerRect.height;
        const windowHeight = window.innerHeight;
        
        // Calcular o progresso do scroll dentro da seção (0 a 1)
        const progress = Math.max(0, Math.min(1, (windowHeight - containerTop) / (windowHeight + containerHeight)));
        
        // Aplicar transform baseado no progresso (movimento suave)
        const translateY = progress * 30; // Máximo de 30px de movimento
        mainImageWrapper.style.transform = `translateY(${translateY}px)`;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initParallax();
    
    const placeholders = document.querySelectorAll('.product-image-placeholder-modern[data-logo-url]');
    placeholders.forEach(function(placeholder) {
        const logoUrl = placeholder.getAttribute('data-logo-url');
        if (logoUrl) {
            // Criar um estilo dinâmico para aplicar ao ::before
            const styleId = 'placeholder-logo-style-' + placeholder.getAttribute('data-logo-url').replace(/[^a-zA-Z0-9]/g, '');
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.textContent = `
                    .product-image-placeholder-modern[data-logo-url="${logoUrl.replace(/"/g, '\\"')}"]::before {
                        background-image: url("${logoUrl.replace(/"/g, '\\"')}");
                    }
                `;
                document.head.appendChild(style);
            }
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const variantOptions = document.querySelectorAll('.variant-option input[type="radio"]');
    const buyNowBtn = document.getElementById('buyNowBtn');
    const mainImage = document.getElementById('mainProductImage');
    
    function updateMainImage(variantImageUrl) {
        if (!mainImage) return;
        
        if (variantImageUrl && variantImageUrl.trim() !== '') {
            // Show variant image in main area
            mainImage.src = variantImageUrl;
            mainImage.style.display = 'block';
            
            // Hide placeholder if exists
            const placeholder = mainImage.parentElement.querySelector('.product-image-placeholder-modern');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        } else {
            // Show default product image
            if (defaultProductImage) {
                mainImage.src = defaultProductImage;
                mainImage.style.display = 'block';
            } else {
                mainImage.style.display = 'none';
                const placeholder = mainImage.parentElement.querySelector('.product-image-placeholder-modern');
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
            }
        }
    }
    
    variantOptions.forEach(radio => {
        radio.addEventListener('change', function() {
            const option = this.closest('.variant-option');
            const variantId = option.dataset.variantId;
            const price = option.dataset.price;
            const points = option.dataset.points;
            const variantImageUrl = option.dataset.imageUrl || '';
            
            // Update buy button with variant info
            if (buyNowBtn) {
                buyNowBtn.dataset.variantId = variantId;
                buyNowBtn.dataset.price = price;
                buyNowBtn.dataset.points = points;
            }
            
            // Update main image if variant has image
            updateMainImage(variantImageUrl);
        });
    });
    
    // Set initial variant
    const firstVariant = document.querySelector('.variant-option input[type="radio"]:checked');
    if (firstVariant) {
        const option = firstVariant.closest('.variant-option');
        if (option) {
            if (buyNowBtn) {
                buyNowBtn.dataset.variantId = option.dataset.variantId;
                buyNowBtn.dataset.price = option.dataset.price;
                buyNowBtn.dataset.points = option.dataset.points;
            }
            
            // Update main image for initial variant
            const variantImageUrl = option.dataset.imageUrl || '';
            updateMainImage(variantImageUrl);
        }
    }
});

function changeMainImage(imageUrl, index) {
    const mainImage = document.getElementById('mainProductImage');
    if (mainImage) {
        mainImage.src = imageUrl;
    }
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
        if (i === index) {
            thumb.classList.add('active');
        } else {
            thumb.classList.remove('active');
        }
    });
}

function addToCart(productId) {
    const btn = event.target.closest('.btn-add-cart');
    if (btn) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.7';
    }
    
    fetch('<?php echo APP_URL; ?>/api/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_id=' + productId + '&quantity=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar badge do carrinho
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.style.display = data.cart_count > 0 ? 'flex' : 'none';
            }
            
            // Mostrar notificação
            showNotification('Produto adicionado ao carrinho!', 'success');
            
            // Redirecionar para carrinho após 1 segundo
            setTimeout(() => {
                window.location.href = '<?php echo APP_URL; ?>/carrinho.php';
            }, 1000);
        } else {
            showNotification(data.error || 'Erro ao adicionar produto', 'error');
            if (btn) {
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao adicionar produto ao carrinho', 'error');
        if (btn) {
            btn.style.pointerEvents = 'auto';
            btn.style.opacity = '1';
        }
    });
}

function showNotification(message, type = 'info') {
    const existing = document.querySelector('.custom-notification');
    if (existing) {
        existing.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = 'custom-notification ' + type;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function handleBuyNow(e) {
    e.preventDefault();
    
    const buyBtn = e.currentTarget;
    const variantId = buyBtn.dataset.variantId;
    
    // Get product name
    const productName = '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>';
    
    // Build WhatsApp message
    let message = '<?php echo htmlspecialchars(getSetting($pdo, "default_message", "Olá! Quero reservar: "), ENT_QUOTES); ?>' + productName;
    
    if (variantId) {
        // Get variant name
        const variantLabel = document.querySelector(`label[for="variant_${variantId}"] .variant-name`);
        if (variantLabel) {
            message += ' - ' + variantLabel.textContent.trim();
        }
    }
    
    // Open WhatsApp
    const whatsappNumber = '<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>';
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank');
}

// Calculadora de Frete
function calculateShipping() {
    const cepInput = document.getElementById('shipping-cep');
    const resultsDiv = document.getElementById('shipping-results');
    const btn = document.getElementById('calc-shipping-btn');
    const optionsContainer = document.getElementById('shipping-options-container');
    
    let cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        resultsDiv.innerHTML = '<div class="shipping-error">Por favor, digite um CEP valido com 8 digitos.</div>';
        resultsDiv.classList.add('show');
        if (optionsContainer) optionsContainer.style.display = 'none';
        return;
    }
    
    // Mostrar loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    resultsDiv.innerHTML = '<div class="shipping-loading"><i class="fas fa-spinner"></i> Calculando frete...</div>';
    resultsDiv.classList.add('show');
    
    fetch('<?php echo APP_URL; ?>/api/calculate-shipping.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'cep=' + encodeURIComponent(cep)
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Calcular';
        
        if (data.success && data.options && data.options.length > 0) {
            // Esconder o resultado de texto e mostrar as opções de seleção
            resultsDiv.innerHTML = '<div class="shipping-success"><i class="fas fa-check-circle"></i> Frete calculado! Escolha uma opcao abaixo:</div>';
            
            // Mostrar opções de frete
            if (optionsContainer) {
                optionsContainer.style.display = 'block';
                
                // Atualizar preços das opções com os valores reais
                const pacCard = optionsContainer.querySelector('[data-code="04510"]');
                const sedexCard = optionsContainer.querySelector('[data-code="04014"]');
                
                data.options.forEach(option => {
                    if (option.code === '04510' && pacCard) {
                        pacCard.dataset.price = option.price;
                        pacCard.dataset.days = option.days;
                        pacCard.querySelector('.shipping-option-price').textContent = 'R$ ' + parseFloat(option.price).toFixed(2).replace('.', ',');
                        pacCard.querySelector('.shipping-option-days').textContent = 'Chega em ate ' + option.days + ' dias uteis';
                    } else if (option.code === '04014' && sedexCard) {
                        sedexCard.dataset.price = option.price;
                        sedexCard.dataset.days = option.days;
                        sedexCard.querySelector('.shipping-option-price').textContent = 'R$ ' + parseFloat(option.price).toFixed(2).replace('.', ',');
                        sedexCard.querySelector('.shipping-option-days').textContent = 'Chega em ate ' + option.days + ' dias uteis';
                    }
                });
                
                // Adicionar event listeners para seleção
                optionsContainer.querySelectorAll('.shipping-option-card').forEach(card => {
                    card.onclick = function() {
                        // Remover seleção de todos
                        optionsContainer.querySelectorAll('.shipping-option-card').forEach(c => c.classList.remove('selected'));
                        optionsContainer.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
                        
                        // Selecionar este
                        this.classList.add('selected');
                        this.querySelector('input[type="radio"]').checked = true;
                    };
                });
            }
        } else {
            resultsDiv.innerHTML = '<div class="shipping-error">' + (data.error || 'Nao foi possivel calcular o frete para este CEP.') + '</div>';
            if (optionsContainer) optionsContainer.style.display = 'none';
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = 'Calcular';
        console.error('Error:', error);
        resultsDiv.innerHTML = '<div class="shipping-error">Erro ao calcular frete. Tente novamente.</div>';
        if (optionsContainer) optionsContainer.style.display = 'none';
    });
}

// Estilo para mensagem de sucesso
document.head.insertAdjacentHTML('beforeend', `
<style>
.shipping-success {
    color: #22c55e;
    font-size: 0.9rem;
    padding: 0.5rem 0;
}
.shipping-success i {
    margin-right: 0.5rem;
}
</style>
`);

// Mascara CEP
document.getElementById('shipping-cep')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 5) {
        value = value.substring(0, 5) + '-' + value.substring(5, 8);
    }
    e.target.value = value;
});

// Calcular ao pressionar Enter
document.getElementById('shipping-cep')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        calculateShipping();
    }
});

// Carousel Navigation
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.getElementById('recommendedCarousel');
    const prevBtn = document.getElementById('carouselPrev');
    const nextBtn = document.getElementById('carouselNext');
    
    if (carousel && prevBtn && nextBtn) {
        const itemWidth = carousel.querySelector('.carousel-item')?.offsetWidth || 0;
        const gap = 24;
        
        prevBtn.addEventListener('click', function() {
            carousel.scrollBy({
                left: -(itemWidth + gap),
                behavior: 'smooth'
            });
        });
        
        nextBtn.addEventListener('click', function() {
            carousel.scrollBy({
                left: itemWidth + gap,
                behavior: 'smooth'
            });
        });
        
        function updateButtonStates() {
            const isAtStart = carousel.scrollLeft <= 0;
            const isAtEnd = carousel.scrollLeft >= (carousel.scrollWidth - carousel.clientWidth - 10);
            
            prevBtn.style.opacity = isAtStart ? '0.5' : '1';
            prevBtn.style.pointerEvents = isAtStart ? 'none' : 'auto';
            nextBtn.style.opacity = isAtEnd ? '0.5' : '1';
            nextBtn.style.pointerEvents = isAtEnd ? 'none' : 'auto';
        }
        
        carousel.addEventListener('scroll', updateButtonStates);
        window.addEventListener('resize', updateButtonStates);
        updateButtonStates();
    }
});
</script>

<?php 
if (!$is_ajax) {
    echo '</div>'; // Fecha public-main-content
}
include __DIR__ . '/includes/public-footer.php'; 
?>
