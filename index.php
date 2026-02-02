<?php
// index.php - Vitrine Pública de Perfumes (sem login)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$whatsapp_number = getWhatsAppNumber($pdo);
// Get blur level for product placeholder logo (0-100%)
$logoBlurPercent = intval(getSetting($pdo, 'product_placeholder_logo_blur', 20));
// Convert percentage to pixels (0-100% = 0-10px)
$logoBlurPx = ($logoBlurPercent / 100) * 10;
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
                $showcaseProducts = $productsStmt->fetchAll();
              
                // Garantir que description existe no array mesmo se a coluna não existir
                if (!$hasDescription) {
                    foreach ($showcaseProducts as &$showcaseProduct) {
                        $showcaseProduct['description'] = null;
                    }
                    unset($showcaseProduct);
                }
              
                $showcase['products'] = $showcaseProducts;
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
// Get banners for vitrine (public - no user status needed)
// Exclude carousel banners (they are shown separately above)
$banners = [];
if (db_table_exists($pdo, 'banners')) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM banners
            WHERE is_active = 1
            AND (carousel_type IS NULL OR carousel_type = 'single' OR carousel_type = '')
            AND (target_pages LIKE '%marketplace%' OR display_pages LIKE '%marketplace%' OR target_pages IS NULL OR display_pages IS NULL)
            ORDER BY display_order ASC, created_at DESC
        ");
        $banners = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Banners fetch error: " . $e->getMessage());
    }
}
$pageTitle = 'Vitrine de Perfumes';
$bodyClass = 'vitrine-page';
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
// Get current brand filter
$currentBrandId = !empty($_GET['brand']) ? (int)$_GET['brand'] : null;
// Get carousel banners for current brand (or all if no brand)
$carouselBanners = getCarouselBanners($pdo, $currentBrandId);
?>
<?php
// Buscar configuracoes da faixa rotativa
$faixaEnabled = getSetting($pdo, 'faixa_enabled', '1') === '1';
$faixaBgColor = getSetting($pdo, 'faixa_bg_color', '#b67c90');
$faixaTextColor = getSetting($pdo, 'faixa_text_color', '#ffffff');
$faixaFontSize = getSetting($pdo, 'faixa_font_size', '14');
$faixaFrases = getSetting($pdo, 'faixa_frases', 'PARCELAMENTO EM ATE 6X SEM JUROS|ENTREGA RAPIDA PARA TODO PAIS|5% DE DESCONTO NO PIX|TROCA GRATIS EM ATE 30 DIAS');
$faixaLinks = getSetting($pdo, 'faixa_links', '|||'); // Links para cada frase (separados por |)
$faixaInterval = getSetting($pdo, 'faixa_interval', '4000');
?>

<?php if ($faixaEnabled): ?>
<!-- Faixa Rotativa Promocional -->
<style>
    .faixa-container {
        background-color: <?php echo htmlspecialchars($faixaBgColor); ?>;
        color: <?php echo htmlspecialchars($faixaTextColor); ?>;
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: <?php echo htmlspecialchars($faixaFontSize); ?>px;
        font-weight: bold;
        text-transform: uppercase;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        width: 100%;
        margin: 0;
        box-sizing: border-box;
        position: relative;
        z-index: 100;
    }
    .faixa-btn {
        background: none;
        border: none;
        color: <?php echo htmlspecialchars($faixaTextColor); ?>;
        cursor: pointer;
        font-size: 18px;
        padding: 0 20px;
        transition: opacity 0.2s ease;
        outline: none;
        user-select: none;
    }
    .faixa-btn:hover { opacity: 0.6; }
    .faixa-texto {
        flex-grow: 1;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        user-select: none;
        opacity: 1;
        transition: opacity 0.4s ease-in-out;
    }
    .faixa-texto a {
        color: inherit;
        text-decoration: none;
    }
    .faixa-texto a:hover {
        text-decoration: underline;
    }
    .faixa-texto.escondido { opacity: 0; }
    @media (max-width: 480px) {
        .faixa-texto { font-size: <?php echo max(10, intval($faixaFontSize) - 2); ?>px; }
        .faixa-btn { padding: 0 10px; }
    }
</style>

<div class="faixa-container" id="faixa-topo">
    <button class="faixa-btn" id="btn-voltar" aria-label="Anterior">&#10094;</button>
    <div class="faixa-texto" id="texto-rotativo"></div>
    <button class="faixa-btn" id="btn-avancar" aria-label="Proximo">&#10095;</button>
</div>

<script>
(function() {
    var frasesRaw = <?php echo json_encode(explode('|', $faixaFrases)); ?>;
    var linksRaw = <?php echo json_encode(explode('|', $faixaLinks)); ?>;
    var intervalo = <?php echo intval($faixaInterval); ?>;
    
    var frases = frasesRaw.map(function(f, i) {
        return { texto: f.trim(), link: (linksRaw[i] || '').trim() };
    });
    
    var indiceAtual = 0;
    var textoElemento = document.getElementById("texto-rotativo");
    var btnVoltar = document.getElementById("btn-voltar");
    var btnAvancar = document.getElementById("btn-avancar");
    var animando = false;
    var intervaloAutoplay;
    
    function renderFrase() {
        var item = frases[indiceAtual];
        if (item.link && item.link !== '') {
            textoElemento.innerHTML = '<a href="' + item.link + '">' + item.texto + '</a>';
        } else {
            textoElemento.innerText = item.texto;
        }
    }
    
    function mudarFrase(direcao) {
        if (animando) return;
        animando = true;
        textoElemento.classList.add("escondido");
        setTimeout(function() {
            if (direcao === 'proximo') {
                indiceAtual++;
                if (indiceAtual >= frases.length) indiceAtual = 0;
            } else {
                indiceAtual--;
                if (indiceAtual < 0) indiceAtual = frases.length - 1;
            }
            renderFrase();
            textoElemento.classList.remove("escondido");
            animando = false;
        }, 400);
    }
    
    function reiniciarTimer() {
        clearInterval(intervaloAutoplay);
        intervaloAutoplay = setInterval(function() {
            mudarFrase('proximo');
        }, intervalo);
    }
    
    btnVoltar.addEventListener("click", function() {
        mudarFrase('anterior');
        reiniciarTimer();
    });
    
    btnAvancar.addEventListener("click", function() {
        mudarFrase('proximo');
        reiniciarTimer();
    });
    
    // Inicializar
    renderFrase();
    reiniciarTimer();
})();
</script>
<?php endif; ?>

<style> .vitrine-top-bar{background-color:#fef8d5;}</style>
<div class="vitrine-page" style="background-color:#1a1a1a;">
    <!-- Top Search and Filters Bar (above banner) -->
    <div class="vitrine-top-bar" style="background-color:#1a1a1a;">
        <div class="container" style="background-color:#1a1a1a;">
            <!-- Layout Horizontal Compacto -->
            <div class="vitrine-top-bar-wrapper">
                <!-- Logo - Lado Esquerdo -->
                <a href="<?php echo APP_URL; ?>/" class="logo-link-vitrine">
                    <?php
                    $logoWidth = getSetting($pdo, 'logo_width', defined('LOGO_WIDTH') && LOGO_WIDTH !== 'auto' ? LOGO_WIDTH : 'auto');
                    $logoHeight = getSetting($pdo, 'logo_height', defined('LOGO_HEIGHT') && LOGO_HEIGHT !== 'auto' ? LOGO_HEIGHT : 'auto');
                    ?>
                    <img src="<?php echo LOGO_URL; ?>" alt="<?php echo htmlspecialchars(APP_NAME); ?>" class="header-logo-vitrine"
                         style="width: <?php echo htmlspecialchars($logoWidth); ?> !important;
                                height: <?php echo htmlspecialchars($logoHeight); ?> !important;
                                max-width: <?php echo htmlspecialchars($logoWidth); ?> !important;
                                max-height: <?php echo htmlspecialchars($logoHeight); ?> !important;
                                object-fit: contain;">
                </a>
              
                <!-- Busca e Filtros - Lado Direito -->
                <div class="search-filters-container">
                    <!-- Search Bar -->
    <style>
    /* Container principal - Fundo branco e APENAS a borda visível */
    .search-input-wrapper {
        background-color: #ffffff !important; /* Fundo branco */
        display: flex;
        align-items: center;
        padding: 5px 15px;
        border-radius: 12px; /* Deixa as pontas arredondadas (estilo pílula) */
        border: 1px solid #000000; /* A borda preta que você solicitou */
        box-shadow: none !important;
    }
    /* Campo de entrada de texto */
    .search-input {
        background-color: transparent !important; /* Usa o fundo do wrapper */
        color: #000000 !important; /* Texto digitado em preto */
        border: none !important;
        outline: none !important;
        padding: 12px;
        width: 100%;
        font-size: 14px;
    }
    /* PLACEHOLDER: Texto em Preto */
    .search-input::placeholder {
        color: #000000 !important;
        opacity: 0.7;
    }
    /* LUPA: Em Preto e sem fundo de botão */
    .search-icon,
    .search-submit-btn {
        color: #000000 !important;
        background: transparent !important;
        border: none !important;
        padding: 0;
        margin: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        box-shadow: none !important;
    }
    /* Remove qualquer cor de fundo do formulário pai */
    .vitrine-search-form {
        background-color: transparent !important;
    }
</style>
<form method="GET" action="" class="vitrine-search-form">
    <div class="search-input-wrapper">
   
      
        <input type="text"
               name="search"
               class="search-input"
               placeholder="Oi, o que você procura hoje? ;) "
               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
               id="searchInput">
        <?php if (!empty($_GET['search'])): ?>
            <a href="?" style="color: #000; margin-right: 10px; text-decoration: none;">
                <i class="fas fa-times"></i>
            </a>
        <?php endif; ?>
        <button type="submit" class="search-submit-btn">
            <i class="fas fa-search"></i>
        </button>
    </div>
</form>
                    <!-- Filter Toggle (Mobile) -->
                    <button class="filter-toggle-btn" onclick="toggleFilters()" id="filterToggleBtn">
                        <i class="fas fa-filter"></i>
                        <span>Escolher Marca</span>
                        <?php if (!empty($_GET['brand'])): ?>
                            <span class="filter-badge">1</span>
                        <?php endif; ?>
                    </button>
                    <!-- Filter Toggle (Desktop) -->
                    <button class="filter-toggle-btn-desktop" onclick="toggleDesktopFilters()" id="filterToggleBtnDesktop">
                        <i class="fas fa-filter"></i>
                        <span>Escolher Marca</span>
                        <i class="fas fa-chevron-down" id="filterToggleIcon"></i>
                        <?php if (!empty($_GET['brand'])): ?>
                            <span class="filter-badge">1</span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
            <!-- Quick Filters (Desktop) - Abaixo da barra principal -->
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
                        <button class="filter-chip-desktop <?php echo empty($_GET['brand']) ? 'active' : ''; ?>" onclick="applyBrandFilter('')">
                            Todas
                        </button>
                        <?php foreach ($brands as $brand): ?>
                            <button class="filter-chip-desktop <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'active' : ''; ?>" onclick="applyBrandFilter('<?php echo $brand['id']; ?>')">
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (!empty($_GET['brand'])): ?>
                    <button onclick="clearAllFilters()" class="btn-clear-filters-desktop" title="Ver todos os perfumes">
                        <i class="fas fa-times"></i>
                        <span>Ver Todos os Perfumes</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- CSS AQUI-->
<style>#carousel-1{background-color:#fef8d5;} .products-section{background-color:#fefcee;}</style>
    <!-- Carousel Banner (below menu) -->
    <?php
    $bannersEnabled = defined('FEATURE_BANNERS_ENABLED') ? (int)FEATURE_BANNERS_ENABLED : 1;
    if ($bannersEnabled === 1 && !empty($carouselBanners)):
    ?>
       
<style>
    /* Mobile Menu Optimization */
    @media (max-width: 768px) {
        .vitrine-top-bar-wrapper {
            flex-direction: column !important;
            align-items: center !important;
            gap: 15px !important;
            padding: 15px 0 !important;
        }
        .search-filters-container {
            display: flex !important;
            width: 100% !important;
            gap: 10px !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .vitrine-search-form {
            flex: 1 !important;
            margin: 0 !important;
        }
        .search-input-wrapper {
            width: 100% !important;
        }
        .filter-toggle-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 48px !important;
            height: 48px !important;
            min-width: 48px !important;
            padding: 0 !important;
            border-radius: 12px !important;
            background: #C7A333 !important;
            color: #000 !important;
            margin: 0 !important;
        }
        .filter-toggle-btn span, .filter-toggle-btn .filter-badge {
            display: none !important;
        }
        .filter-toggle-btn i {
            margin: 0 !important;
            font-size: 20px !important;
        }
       
        /* Logo Scroll Animation */
        .header-logo-vitrine {
            transition: all 0.4s ease-in-out !important;
        }
        .logo-scrolled {
            width: 70px !important;
            height: auto !important;
            max-width: 70px !important;
            max-height: none !important;
        }
    }
   
    /* Desktop Logo Transition */
    .header-logo-vitrine {
        transition: all 0.4s ease-in-out !important;
    }
    .logo-scrolled-desktop {
        transform: scale(0.85);
    }
    
    /* Empty State Styles */
    .empty-state-wrapper {
        padding: 3rem 1rem;
    }
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        background: rgba(199, 163, 51, 0.05);
        border: 2px dashed #C7A333;
        border-radius: 20px;
        max-width: 500px;
        margin: 0 auto;
    }
    .empty-state-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #C7A333, #d4af37);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        box-shadow: 0 10px 30px rgba(199, 163, 51, 0.3);
    }
    .empty-state-icon i {
        font-size: 2rem;
        color: #fff;
    }
    .empty-state h3 {
        font-family: 'Sora', sans-serif;
        font-size: 1.5rem;
        font-weight: 800;
        color: #C7A333;
        margin-bottom: 0.75rem;
    }
    .empty-state p {
        color: #C7A333;
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        opacity: 0.85;
    }
    .empty-state-actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        align-items: center;
    }
    .btn-clear-filters-empty,
    .btn-expert-empty {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1.5rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.3s ease;
        min-width: 200px;
        justify-content: center;
    }
    .btn-clear-filters-empty {
        background: #C7A333;
        color: #000;
        border: 2px solid #C7A333;
    }
    .btn-clear-filters-empty:hover {
        background: #000;
        color: #C7A333;
        border-color: #000;
    }
    .btn-expert-empty {
        background: transparent;
        color: #C7A333;
        border: 2px solid #C7A333;
    }
    .btn-expert-empty:hover {
        background: #000;
        color: #C7A333;
        border-color: #000;
    }
</style>
<style>
    @media (max-width: 480px) {
        .btn-whatsapp-modern.btn-responsive {
            padding: 10px 15px 10px 45px !important;
            border-radius: 10px !important;
        }
        .btn-whatsapp-modern.btn-responsive i {
            width: 28px !important;
            height: 28px !important;
            font-size: 16px !important;
            left: 8px !important;
        }
        .btn-whatsapp-modern.btn-responsive span {
            font-size: 11px !important;
            letter-spacing: 0 !important;
        }
    }
</style>
<div class="carousel-banner-wrapper">
        <?php foreach ($carouselBanners as $carouselBanner): ?>
            <?php echo renderCarousel($carouselBanner); ?>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- Banners (Mobile Only) -->
    <?php if (!empty($banners)): ?>
        <div class="banners-mobile-only">
            <?php foreach ($banners as $banner): ?>
                <?php
                $displayType = $banner['type'] ?? $banner['display_type'] ?? 'banner';
                // Skip carousel banners (already shown above)
                if (($banner['carousel_type'] ?? 'single') === 'carousel') {
                    continue;
                }
                if ($displayType === 'banner' || $displayType === 'split'):
                ?>
                    <?php echo renderBanner($banner, 'marketplace'); ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- Carrossel de Marcas -->
    <!-- Updated background color to #fef9e0 -->
    <section class="marcas-carousel-section"><style>/* Configuração da Seção com Degradê Animado */
.marcas-carousel-section {
    position: relative;
    overflow: hidden;
    padding: 20px 0; /* Reduzido para hierarquia visual */
  
    /* Degradê Ouro Naipers (Linear para facilitar a animação de movimento) */
    background: linear-gradient(270deg,
        #4C3B1A 0%,
        #8A6623 25%,
        #D4AF37 50%,
        #F7EF8A 75%,
        #D4AF37 100%
    );
  
    /* Aumentamos o tamanho do fundo para que ele possa "correr" */
    background-size: 400% 400% !important;
  
    /* Animação de movimento do fundo */
    animation: goldFlow 10s ease infinite;
  
    border-bottom: none !important;
}

/* Carrossel de Marcas - Logos menores para hierarquia */
.marcas-carousel-section .swiper-slide img {
    max-height: 35px !important;
    width: auto !important;
    object-fit: contain !important;
}

@media (max-width: 768px) {
    .marcas-carousel-section {
        padding: 12px 0 !important;
    }
    .marcas-carousel-section .swiper-slide img {
        max-height: 25px !important;
    }
}

/* Banner Principal - Maior destaque */
.carousel-banner-wrapper {
    margin-bottom: 0;
}

.carousel-banner-wrapper .carousel-slide img,
.carousel-banner-wrapper .render-carousel img {
    min-height: 280px;
    object-fit: cover;
}

@media (min-width: 769px) {
    .carousel-banner-wrapper .carousel-slide img,
    .carousel-banner-wrapper .render-carousel img {
        min-height: 380px;
    }
}

@media (max-width: 768px) {
    .carousel-banner-wrapper .carousel-slide img,
    .carousel-banner-wrapper .render-carousel img {
        min-height: 200px;
        aspect-ratio: 16/9;
    }
}
/* Efeito de Brilho Extra (Overlay) */
.marcas-carousel-section::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}
/* Keyframes para o movimento do degradê */
@keyframes goldFlow {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}
/* Ajuste opcional para os itens dentro do carrossel */
.marcas-carousel-section .elementor-image,
.marcas-carousel-section img {
    filter: drop-shadow(0px 4px 10px rgba(0,0,0,0.2));
}</style>
        <div class="container-marcas" style="heigth:15vh;">
            <div class="swiper marcasSwiper">
                <div class="swiper-wrapper">
                    <!-- Updated all logo images with new paths -->
                    <div class="swiper-slide"><img src="/logos/AZZARO.png" alt="AZZARO"></div>
                    <div class="swiper-slide"><img src="/logos/BOSS.png" alt="BOSS"></div>
                    <div class="swiper-slide"><img src="/logos/BURBERRY.png" alt="BURBERRY"></div>
                    <div class="swiper-slide"><img src="/logos/BVLGARI.png" alt="BVLGARI"></div>
                    <div class="swiper-slide"><img src="/logos/CH.png" alt="CH"></div>
                    <div class="swiper-slide"><img src="/logos/CHANEL.png" alt="CHANEL"></div>
                    <div class="swiper-slide"><img src="/logos/CK.png" alt="CK"></div>
                    <div class="swiper-slide"><img src="/logos/dolce-gabbana-4096.png" alt="Dolce & Gabbana"></div>
                    <div class="swiper-slide"><img src="/logos/GIVENCHY.png" alt="GIVENCHY"></div>
                    <div class="swiper-slide"><img src="/logos/GUCCI.png" alt="GUCCI"></div>
                    <div class="swiper-slide"><img src="/logos/JIMMY.png" alt="JIMMY CHOO"></div>
                    <div class="swiper-slide"><img src="/logos/JOOP.png" alt="JOOP"></div>
                    <div class="swiper-slide"><img src="/logos/JPG.png" alt="Jean Paul Gaultier"></div>
                    <div class="swiper-slide"><img src="/logos/KENZO.png" alt="KENZO"></div>
                    <div class="swiper-slide"><img src="/logos/LACOSTE.png" alt="LACOSTE"></div>
                    <div class="swiper-slide"><img src="/logos/LANCOME.png" alt="LANCOME"></div>
                    <div class="swiper-slide"><img src="/logos/logo-dior-1024.png" alt="DIOR"></div>
                    <div class="swiper-slide"><img src="/logos/MERCEDES.png" alt="MERCEDES"></div>
                    <div class="swiper-slide"><img src="/logos/montblanc-logo-png_seeklogo-94543.png" alt="MONTBLANC"></div>
                    <div class="swiper-slide"><img src="/logos/MONTBLANC.png" alt="MONTBLANC"></div>
                    <div class="swiper-slide"><img src="/logos/MUGLER.png" alt="MUGLER"></div>
                    <div class="swiper-slide"><img src="/logos/Paco-Rabanne-Logo.png" alt="Paco Rabanne"></div>
                    <div class="swiper-slide"><img src="/logos/PRADA.png" alt="PRADA"></div>
                    <div class="swiper-slide"><img src="/logos/RABANNE.png" alt="RABANNE"></div>
                    <div class="swiper-slide"><img src="/logos/RALPH.png" alt="RALPH LAUREN"></div>
                    <div class="swiper-slide"><img src="/logos/TOMMY.png" alt="TOMMY HILFIGER"></div>
                    <div class="swiper-slide"><img src="/logos/VERSACE.png" alt="VERSACE"></div>
                    <div class="swiper-slide"><img src="/logos/YSL.png" alt="YSL"></div>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
            <!-- Removed button "ESCOLHER MARCA" as requested -->
        </div>
    </section>
    <!-- Mobile Filters Panel -->
    <div class="mobile-filters-panel" id="mobileFiltersPanel">
        <div class="mobile-filters-header">
            <h3>Escolher Marca</h3>
            <button onclick="toggleFilters()" class="close-filters-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-filters-content">
            <div class="filter-section-mobile">
                <label class="filter-label-mobile">Marca</label>
                <div class="filter-options-mobile">
                    <button class="filter-chip <?php echo empty($_GET['brand']) ? 'active' : ''; ?>" onclick="applyBrandFilter('')">
                        Todas
                    </button>
                    <?php foreach ($brands as $brand): ?>
                        <button class="filter-chip <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'active' : ''; ?>" onclick="applyBrandFilter('<?php echo $brand['id']; ?>')">
                            <?php echo htmlspecialchars($brand['name']); ?>
                            <span class="chip-count"><?php echo $brand['product_count']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mobile-filters-actions">
                <button onclick="clearAllFilters()" class="btn-clear-filters">Ver Todos os Perfumes</button>
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
                                            <div class="product-image-placeholder-modern" data-logo-url="<?php echo htmlspecialchars(LOGO_URL); ?>">
                                                <div class="placeholder-icon-wrapper">
                                                    <i class="fas fa-spray-can"></i>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                      
                                        <?php 
                                        $hasDiscountBadge = !empty($sp['original_price']) && floatval($sp['original_price']) > floatval($sp['price']);
                                        $discountPercentBadge = $hasDiscountBadge ? round(((floatval($sp['original_price']) - floatval($sp['price'])) / floatval($sp['original_price'])) * 100) : 0;
                                        ?>
                                        <?php if ($hasDiscountBadge): ?>
                                            <div class="product-discount-badge" style="position: absolute; top: 10px; left: 10px; background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; z-index: 5;">
                                                -<?php echo $discountPercentBadge; ?>% OFF
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
                                        <?php 
                                        $hasDiscount = !empty($sp['original_price']) && floatval($sp['original_price']) > floatval($sp['price']);
                                        $discountPercent = $hasDiscount ? round(((floatval($sp['original_price']) - floatval($sp['price'])) / floatval($sp['original_price'])) * 100) : 0;
                                        ?>
                                        <?php if ($hasDiscount): ?>
                                        <div class="product-price-wrapper" style="display: flex; flex-direction: column; gap: 2px;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span style="background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">-<?php echo $discountPercent; ?>%</span>
                                                <span style="text-decoration: line-through; color: #888; font-size: 0.8rem;"><small style="text-decoration: none; font-weight: 500;">de </small><?php echo formatPrice($sp['original_price']); ?></span>
                                            </div>
                                            <div class="product-price-modern" style="color: #22c55e;"><?php echo formatPrice($sp['price']); ?></div>
                                        </div>
                                        <?php else: ?>
                                        <div class="product-price-modern"><?php echo formatPrice($sp['price']); ?></div>
                                        <?php endif; ?>
                                        <?php 
                                        // Calcular parcela com taxa Mercado Pago (aprox 2.99% ao mes)
                                        $taxaMensal = 0.0299;
                                        $numParcelas = 10;
                                        $precoBase = floatval($sp['price']);
                                        $valorParcela = ($precoBase * (1 + ($taxaMensal * $numParcelas))) / $numParcelas;
                                        ?>
                                        <div class="price-installments" style="font-size: 0.75rem; color: #888; margin-top: 2px; font-weight: 500;">ou 10x de R$ <?php echo number_format($valorParcela, 2, ',', '.'); ?></div>
                                    </div>
                                </a>
                              
                                <div class="product-actions-modern">
                                    <a href="<?php echo $product_url; ?>" class="btn-view-details" style="color: #000 !important;">
                                        <i class="fas fa-eye" style="color: #000 !important;"></i>
                                        <span style="color: #000 !important;">Ver</span>
                                    </a>
                                    <!-- Botao Adicionar ao Carrinho -->
                                    <a href="<?php echo APP_URL; ?>/carrinho.php?add=<?php echo $sp['id']; ?>" class="btn-whatsapp-modern btn-add-cart" data-product-id="<?php echo htmlspecialchars($sp['id']); ?>">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span>Adicionar</span>
                                    </a>
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
    <section class="products-section" style="background-color:#fef9e0;">
        <div class="container" style="background-color:#fef9e0;">
            <div class="products-header" style="background-color:#fef9e0;">
                <?php
                // Definir título dinâmico baseado no filtro de marca
                $sectionTitle = "Todos os Perfumes";
                if (isset($currentBrandId) && $currentBrandId) {
                    // Buscar nome da marca filtrada
                    foreach ($brands as $brand) {
                        if ($brand['id'] == $currentBrandId) {
                            $sectionTitle = "Perfumes da " . htmlspecialchars($brand['name']);
                            break;
                        }
                    }
                }
                ?>
                <h2 class="section-title">
                    <?php echo $sectionTitle; ?>
                    <span class="products-count-badge"><?php echo count($products); ?> produto(s)</span>
                </h2>
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
                                        <div class="product-image-placeholder-modern" style="display: none;" data-logo-url="<?php echo htmlspecialchars(LOGO_URL); ?>">
                                            <div class="placeholder-icon-wrapper">
                                                <i class="fas fa-spray-can"></i>
                                            </div>
                                        </div>
                                        <?php if ($image_count > 1): ?>
                                            <div class="product-images-badge">
                                                <i class="fas fa-images"></i>
                                                <span><?php echo $image_count; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="product-image-placeholder-modern" data-logo-url="<?php echo htmlspecialchars(LOGO_URL); ?>">
                                            <div class="placeholder-icon-wrapper">
                                                <i class="fas fa-spray-can"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                  
                                    <?php if ($p['is_vip'] == 1): ?>
                                        <?php 
                                        $hasDiscountBadge = !empty($p['original_price']) && floatval($p['original_price']) > floatval($p['price']);
                                        $discountPercentBadge = $hasDiscountBadge ? round(((floatval($p['original_price']) - floatval($p['price'])) / floatval($p['original_price'])) * 100) : 0;
                                        ?>
                                        <?php if ($hasDiscountBadge): ?>
                                            <div class="product-discount-badge" style="position: absolute; top: 10px; left: 10px; background: #ef4444; color: #fff; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; z-index: 5;">
                                                -<?php echo $discountPercentBadge; ?>% OFF
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-vip-badge-modern">
                                            <span class="vip-star">⭐</span>
                                            <span>VIP</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                              
                                <div class="product-info-modern">
                                    <p class="product-brand-modern"><?php echo htmlspecialchars($p['brand_name'] ?? 'Naipe da Gringa'); ?></p>
                                    <h3 class="product-name-modern"><?php echo htmlspecialchars($p['name']); ?></h3>
                                    <?php 
                                    $hasDiscount = !empty($p['original_price']) && floatval($p['original_price']) > floatval($p['price']);
                                    $discountPercent = $hasDiscount ? round(((floatval($p['original_price']) - floatval($p['price'])) / floatval($p['original_price'])) * 100) : 0;
                                    ?>
                                    <?php if ($hasDiscount): ?>
                                    <div class="product-price-wrapper" style="display: flex; flex-direction: column; gap: 2px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">-<?php echo $discountPercent; ?>%</span>
                                            <span style="text-decoration: line-through; color: #888; font-size: 0.8rem;"><small style="text-decoration: none; font-weight: 500;">de </small><?php echo formatPrice($p['original_price']); ?></span>
                                        </div>
                                        <div class="product-price-modern" style="color: #22c55e;"><?php echo formatPrice($p['price']); ?></div>
                                    </div>
                                    <?php else: ?>
                                    <div class="product-price-modern"><?php echo formatPrice($p['price']); ?></div>
                                    <?php endif; ?>
                                    <div class="price-installments" style="font-size: 0.75rem; color: #888; margin-top: 2px; font-weight: 500;">10x de R$ <?php echo number_format($p['price'] / 10, 2, ',', '.'); ?> <span style="font-size: 0.65rem; opacity: 0.8;">+ taxas</span></div>
                                </div>
                            </a>
                          
                           <div class="product-actions-modern">
                                    <a href="<?php echo $product_url; ?>" class="btn-view-details">
                                        <i class="fas fa-eye"></i>
                                        <span>Ver</span>
                                    </a>
                                    <!-- Botão Adicionar ao Carrinho -->
                                  <a href="<?php echo APP_URL; ?>/carrinho.php?add=<?php echo $p['id']; ?>"
	   class="cta-wpp-38126317318"
	   data-prod-ref="31376312798-<?= $p['id'] ?>">
	    <i class="icon-wpp-03813791873 fab fa-whatsapp"></i>
	    <span class="label-wpp-38126317318">ADICIONAR <br>AO CARRINHO</span>
	</a>
<style>/* ================= CONTAINER ================= */
.product-actions-modern {
    display: flex;
    gap: 8px;
    width: 100%;
}

/* ================= BOTÕES (BASE) ================= */
.product-actions-modern > a {
    flex: 1 1 50%;
    width: 50%;
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 12px;
    text-decoration: none;
}

/* ================= BOTÃO VER ================= */
.btn-view-details {
    background: #1a1a1a;
    color: #fff;
    border: 1px solid #333;
    font-weight: 600;
}

/* ================= BOTÃO WHATSAPP ================= */
a.cta-wpp-38126317318[data-prod-ref] {
    position: relative;
    overflow: hidden;
    background: #0f0f0f;
    border: 1px solid #b8860b;
    box-shadow:
        0 4px 15px rgba(0,0,0,0.5),
        inset 0 0 8px rgba(184,134,11,0.15);
    transition: all 0.4s ease;
    padding: 10px;
}

/* ÍCONE */
a.cta-wpp-38126317318[data-prod-ref] .icon-wpp-03813791873 {
    background: linear-gradient(145deg, #d4af37, #b8860b);
    color: #0f0f0f;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.4s ease;
}

/* TEXTO */
a.cta-wpp-38126317318[data-prod-ref] .label-wpp-38126317318 {
    color: #d4af37;
    font-weight: 800;
    font-size: 12px;
    line-height: 1.1;
    text-align: center;
    text-transform: uppercase;
}

/* ================= HOVER (DESKTOP) ================= */
@media (min-width: 769px) {
    .product-actions-modern {
        gap: 12px;
    }

    a.cta-wpp-38126317318[data-prod-ref]:hover {
        background: linear-gradient(135deg, #1a1a1a, #0f0f0f);
        border-color: #ffd700;
        transform: translateY(-2px);
    }

    a.cta-wpp-38126317318[data-prod-ref]:hover .icon-wpp-03813791873 {
        transform: rotate(360deg);
        background: linear-gradient(145deg, #ffd700, #d4af37);
    }
}

/* ================= MOBILE BUTTON LAYOUT ================= */
@media (max-width: 768px) {
    .product-actions-modern {
        flex-direction: column !important;
        gap: 8px !important;
        padding: 0 10px 10px !important;
    }
    
    .product-actions-modern .btn-view-details {
        order: 1 !important;
        width: 100% !important;
        padding: 12px !important;
        font-size: 0.85rem !important;
        justify-content: center !important;
    }
    
    .product-actions-modern a.cta-wpp-38126317318[data-prod-ref] {
        order: 2 !important;
        width: 100% !important;
        padding: 12px !important;
        justify-content: center !important;
    }
    
    .product-actions-modern a.cta-wpp-38126317318[data-prod-ref] .label-wpp-38126317318 {
        font-size: 0.8rem !important;
    }
}

</style>
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
            <div class="support-content-wrapper">
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
        </div>
    </footer>
</div>
<!-- Pop-up Banners -->
<?php if (!empty($banners)): ?>
    <?php foreach ($banners as $banner): ?>
        <?php if (($banner['display_type'] ?? 'banner') === 'popup'): ?>
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
/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
::-webkit-scrollbar-track {
    background: #1A1A1A;
}
::-webkit-scrollbar-thumb {
    background: #C7A333;
    border-radius: 5px;
    border: 2px solid #1A1A1A;
}
::-webkit-scrollbar-thumb:hover {
    background: #D4B84A;
}
/* Firefox */
* {
    scrollbar-width: thin;
    scrollbar-color: #C7A333 #1A1A1A;
}
/* Top Bar - Design Compacto */
.vitrine-top-bar {
    background: rgba(31, 31, 31, 0.95);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(42, 42, 42, 0.5);
    padding: 0.75rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
    transition: all 0.3s ease;
}
.vitrine-top-bar-wrapper {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    min-height: 60px;
    max-height: 100px;
}
.logo-link-vitrine {
    display: flex;
    align-items: center;
    flex-shrink: 1;
    flex-basis: auto;
    max-width: 30%;
    min-width: 100px;
    overflow: hidden;
}
.header-logo-vitrine {
    object-fit: contain;
    display: block;
}
.search-filters-container {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1 1 auto;
    min-width: 0;
    justify-content: flex-end;
    flex-shrink: 0;
}
/* Mobile Layout */
@media (max-width: 768px) {
    .vitrine-top-bar {
        padding: 0.5rem 0;
    }
  
    .vitrine-top-bar-wrapper {
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
        min-height: auto;
        max-height: none;
    }
  
    .logo-link-vitrine {
        max-width: 100%;
        justify-content: center;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(42, 42, 42, 0.5);
    }
  
    .header-logo-vitrine {
        /* Tamanho controlado pelo admin via estilo inline */
    }
  
    .search-filters-container {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
}
/* Desktop Layout */
@media (min-width: 769px) {
    .vitrine-top-bar {
        padding: 0.875rem 0;
    }
  
    .vitrine-top-bar-wrapper {
        gap: 2rem;
    }
  
    .logo-link-vitrine {
        max-width: 30%;
        flex-shrink: 1;
    }
  
    .header-logo-vitrine {
        /* Tamanho controlado pelo admin via estilo inline */
    }
  
    .search-filters-container {
        flex: 1 1 60%;
        min-width: 400px;
    }
  
    .search-filters-container {
        flex-wrap: nowrap;
        gap: 1.5rem;
    }
}
/* Tamanho da logo controlado pelo admin */
/* O tamanho configurado no admin é respeitado via estilo inline */
@media (min-width: 769px) {
    .header-logo-vitrine {
        /* Tamanho controlado pelo admin via estilo inline */
    }
  
    .logo-link-vitrine {
        /* Container flexível para a logo */
        overflow: hidden;
    }
}
@media (max-width: 768px) {
    .header-logo-vitrine {
        /* Tamanho controlado pelo admin via estilo inline */
    }
  
    .logo-link-vitrine {
        /* Container flexível para a logo */
        overflow: hidden;
    }
}
.vitrine-search-form {
    flex: 1;
    max-width: 550px; /* Reduzido 5-10% para melhor proporção */
}
@media (min-width: 1200px) {
    .vitrine-search-form {
        max-width: 650px; /* Reduzido proporcionalmente */
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
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.5); /* Branco com 50% opacidade - mais visível */
    font-size: 1.1rem;
    pointer-events: none;
    z-index: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.search-input-wrapper:focus-within .search-icon {
    color: rgba(199, 163, 51, 0.8); /* Dourado quando focado */
    transform: translateY(-50%) scale(1.1);
}
/* Ícone de busca ativa - igual ao do botão de buscar */
.search-active-icon {
    position: absolute;
    right: 150px;
    top: 50%;
    transform: translateY(-50%);
    color: #C7A333;
    font-size: 1rem;
    pointer-events: none;
    z-index: 2;
    animation: searchPulse 2s ease-in-out infinite;
}
@keyframes searchPulse {
    0%, 100% {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }
    50% {
        opacity: 0.7;
        transform: translateY(-50%) scale(1.1);
    }
}
.search-input {
    width: 100%;
    padding: 1rem 1.25rem 1rem 3rem;
    padding-right: 140px; /* Espaço para o botão de busca */
    border: 2px solid #3A3A3A;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #1A1A1A;
    color: #E0E0E0;
    box-sizing: border-box;
    line-height: 1.5;
    position: relative;
    z-index: 1;
}
.search-input::placeholder {
    color: #999999;
    opacity: 1;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.9rem;
}
.search-input:focus {
    outline: none;
    border-color: #C7A333;
    background: #1F1F1F;
    box-shadow: 0 0 0 4px rgba(199, 163, 51, 0.15), 0 2px 8px rgba(0, 0, 0, 0.3);
    transform: translateY(-1px);
    padding-right: 140px;
}
.search-input:focus::placeholder {
    color: #B3B3B3;
}
.clear-search {
    position: absolute;
    right: 150px; /* Ajustado para não sobrepor o botão de busca */
    color: #999999;
    text-decoration: none;
    padding: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
}
.clear-search:hover {
    background: #3A3A3A;
    color: #E0E0E0;
    transform: scale(1.1);
}
/* Botão de Buscar - Visível e Destacado */
.search-submit-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, #C7A333 0%, #D4B84A 100%);
    color: #000000;
    border: none;
    border-radius: 10px;
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 3;
    box-shadow: 0 2px 8px rgba(199, 163, 51, 0.3);
    white-space: nowrap;
}
.search-submit-btn:hover {
    background: linear-gradient(135deg, #D4B84A 0%, #E0C55F 100%);
    transform: translateY(-50%) scale(1.05);
    box-shadow: 0 4px 12px rgba(199, 163, 51, 0.4);
}
.search-submit-btn:active {
    transform: translateY(-50%) scale(0.98);
}
.search-submit-btn i {
    font-size: 1rem;
}
.filter-toggle-btn {
    display: none;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.25rem; /* Padding harmonizado */
    background: #1F1F1F;
    border: 2px solid #3A3A3A; /* Border mais visível */
    border-radius: 12px; /* Harmonizado */
    font-weight: 600;
    color: #E0E0E0; /* Texto mais claro para contraste WCAG */
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}
.filter-toggle-btn:hover {
    border-color: #C7A333;
    color: #C7A333;
    background: #252525;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}
/* Desktop Filter Toggle Button */
.filter-toggle-btn-desktop {
    display: none;
    align-items: center;
    gap: 0.625rem;
    padding: 20px 50px;
    border-radius: 15.623px;
    border: 1.196px solid rgba(212, 175, 55, 0.30) !important;
    background: radial-gradient(67.54% 100.03% at 50% 0%,
        #F7EF8A 0%,
        #D4AF37 25.48%,
        #8A6623 62.5%,
        #4C3B1A 100%) !important;
    box-shadow: 0 5.98px 23.203px 0 rgba(138, 102, 35, 0.20),
                0 14.352px 53.701px 0 rgba(138, 102, 35, 0.50) !important;
    font-weight: 700;
    color: #ffffff !important;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    cursor: pointer;
    transition: 0.5s ease all;
    position: relative;
    overflow: hidden;
    margin-top: 0;
}
/* Added brilho animation effect */
.filter-toggle-btn-desktop::before {
    content: "";
    height: 100%;
    width: 100px;
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    background: #ffffff;
    box-shadow: 0 0 30px 20px rgba(255, 255, 255, 0.4);
    transform: skewX(-20deg);
    mix-blend-mode: plus-lighter;
    pointer-events: none;
    animation: brilho 2.5s linear infinite;
}
@keyframes brilho {
    0% { opacity: 0; left: -20%; }
    25% { opacity: 0.3; }
    50% { opacity: 0.5; left: 50%; }
    75% { opacity: 0.3; }
    100% { opacity: 0; left: 120%; }
}
.filter-toggle-btn-desktop:hover {
    filter: brightness(1.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(138, 102, 35, 0.4);
}
.filter-toggle-btn-desktop:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(138, 102, 35, 0.2);
}
.filter-toggle-btn-desktop span {
    color: #ffffff !important;
    font-weight: 700;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    transition: 0.5s ease all;
}
.filter-toggle-btn-desktop i.fa-chevron-down,
.filter-toggle-btn-desktop i.fa-chevron-up {
    font-size: 0.75rem;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 0.25rem;
    color: #ffffff !important;
}
.filter-toggle-btn-desktop.active i.fa-chevron-down,
.filter-toggle-btn-desktop.active i.fa-chevron-up {
    transform: rotate(180deg); /* Rotação suave da seta */
}
.filter-toggle-btn-desktop.active i.fa-chevron-up {
    transform: rotate(180deg); /* Ícone up já vem rotacionado */
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
    gap: 1.5rem;
    padding: 1.5rem;
    background: #1F1F1F;
    border: 1px solid #2A2A2A;
    border-radius: 12px;
    margin-top: 1rem;
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 0;
    overflow: hidden;
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
    opacity: 1;
    transform: translateY(0);
    max-height: 1000px; /* Permite animação suave de altura */
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
}
.showcase-banner {
    margin-bottom: 2rem;
    border-radius: 16px;
    overflow: hidden;
}
.showcase-banner-img {
    width: 100%;
    height: 100%;
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
/* Desktop Showcase Grid Optimization - 3 produtos por linha a partir de 769px */
@media (min-width: 769px) {
    .products-grid-showcase {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 2rem;
    }
}
@media (min-width: 1200px) {
    .products-grid-showcase {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 2rem;
    }
}
@media (min-width: 1400px) {
    .products-grid-showcase {
        grid-template-columns: repeat(3, 1fr);
        gap: 2.25rem;
    }
}
/* Products Section */
.products-section {
    padding: 2rem 0;
    background: linear-gradient(180deg, #fef9e0 0%, #fef9e0 100%);
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
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.products-count-badge {
    background: rgba(199, 163, 51, 0.1);
    color: #999999;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.8125rem;
    font-weight: 600;
    opacity: 0.7;
    white-space: nowrap;
    margin-left: 0;
}
.products-grid-main {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
/* Desktop Grid Optimization - 4 produtos por linha a partir de 769px */
@media (min-width: 769px) {
    .products-grid-main {
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 2rem;
    }
}
@media (min-width: 1200px) {
    .products-grid-main {
        grid-template-columns: repeat(4, 1fr) !important;
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
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
    }
}
@media (min-width: 1600px) {
    .products-grid-main {
        grid-template-columns: repeat(4, 1fr);
        gap: 2.25rem;
    }
}
/* Modern Product Card */
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
    background: #FFFFFF;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    border: 1px solid #E0E0E0;
}
.product-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(199, 163, 51, 0.4);
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
    position: relative;
    background-color: #0F0F0F;
    overflow: hidden;
}
/* Logo de fundo com blur usando pseudo-elemento */
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
    /* Logo será aplicada via JavaScript do data attribute */
    /* Blur na logo de fundo - valor dinâmico do admin (0-100% = 0-10px) */
    filter: blur(<?php echo number_format($logoBlurPx, 2); ?>px);
    z-index: 0;
    opacity: 0.3;
    pointer-events: none;
}
/* Overlay muito transparente para reduzir opacidade da logo de fundo */
.product-image-placeholder-modern::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 15, 15, 0.5);
    z-index: 0;
    pointer-events: none;
}
/* Wrapper do ícone - sem blur */
.placeholder-icon-wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* Ícone por cima da logo - SEM blur, apenas sombra */
.placeholder-icon-wrapper i {
    color: #666666;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
    font-size: 3rem;
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
    color: #000000;
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
    position: relative;
    overflow: hidden;
    z-index: 1;
}
.btn-view-details::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        120deg,
        #4C3F01 0%,
        #8A6623 20%,
        #D4AF37 40%,
        #F7EF8A 50%,
        #D4AF37 60%,
        #8A6623 80%,
        #4C3F01 100%
    );
    background-size: 300% 300%;
    animation: goldFlow 4s ease-in-out infinite;
    z-index: -1;
}
@keyframes goldFlow {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.btn-view-details:hover {
    background: transparent;
    color: #000000;
    transform: scale(1.02);
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
/* Support Footer */
/* Support Footer - Modern Gradient Card */
.vitrine-support-footer {
    padding: 4rem 2rem;
    margin-top: 4rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.support-content-wrapper {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.1) 0%,
        var(--color-primary) 43%,
        rgba(255, 255, 255, 0.1) 100%);
    padding: 2px;
    border-radius: 1.5rem;
    box-shadow: 0px 1rem 1.5rem -0.9rem rgba(0, 0, 0, 0.88);
    position: relative;
    overflow: hidden;
}
.support-content-wrapper::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle,
        rgba(255, 255, 255, 0.1) 0%,
        transparent 70%);
    animation: shimmer 3s ease-in-out infinite;
    pointer-events: none;
}
@keyframes shimmer {
    0%, 100% { transform: translate(-50%, -50%) rotate(0deg); opacity: 0.3; }
    50% { transform: translate(-50%, -50%) rotate(180deg); opacity: 0.6; }
}
.support-content {
    font-size: 1rem;
    color: var(--color-text);
    background: linear-gradient(135deg,
        var(--color-background) 0%,
        var(--color-surface) 43%,
        var(--color-background) 100%);
    padding: 3rem 2.5rem;
    border-radius: 1.5rem;
    text-align: center;
    position: relative;
    z-index: 1;
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
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 1rem 0;
    background: linear-gradient(135deg,
        var(--color-text) 0%,
        var(--color-primary) 50%,
        var(--color-text) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    line-height: 1.2;
}
.support-text-large {
    font-size: 1.125rem;
    line-height: 1.8;
    margin: 0 0 2.5rem 0;
    color: var(--color-text-muted);
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}
.btn-support-whatsapp-large {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.125rem 2.75rem;
    background: linear-gradient(135deg, #25d366 0%, #20ba5a 100%);
    color: #000000;
    border: none;
    border-radius: 12px;
    font-size: 1.125rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 6px 20px rgba(199, 163, 51, 0.6),
                0 0 30px rgba(199, 163, 51, 0.5);
    position: relative;
    overflow: hidden;
}
.btn-support-whatsapp-large::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}
.btn-support-whatsapp-large:hover::before {
    width: 300px;
    height: 300px;
}
.btn-support-whatsapp-large:hover {
    background: linear-gradient(135deg, #D4B84A 0%, #E0C55F 100%);
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(199, 163, 51, 0.8),
                0 0 50px rgba(199, 163, 51, 0.7),
                0 0 80px rgba(199, 163, 51, 0.4);
}
.btn-support-whatsapp-large:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(199, 163, 51, 0.6),
                0 0 25px rgba(199, 163, 51, 0.5);
}
.btn-support-whatsapp-large i {
    font-size: 1.5rem;
    position: relative;
    z-index: 1;
    color: #000000;
    filter: drop-shadow(0 2px 4px rgba(199, 163, 51, 0.4));
}
.btn-support-whatsapp-large span {
    position: relative;
    z-index: 1;
    color: #000000;
}
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
/* Banners Mobile Only - Esconder no desktop, mostrar no mobile */
.banners-mobile-only {
    display: none;
}
@media (max-width: 768px) {
    .banners-mobile-only {
        display: block;
    }
}
/* Responsive - Mobile e Tablet */
@media (max-width: 768px) {
    .products-grid-main {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
  
    .products-grid-showcase {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}
@media (min-width: 769px) {
    .filter-toggle-btn-desktop {
        display: flex !important;
    }
  
    .filter-toggle-btn {
        display: none !important;
    }
}
@media (max-width: 768px) {
    .vitrine-search-form {
        max-width: 100%;
        width: 100%;
    }
  
    .search-input {
        font-size: 16px; /* Evita zoom no iOS */
        padding-right: 120px; /* Menos espaço no mobile */
    }
  
    .search-submit-btn {
        padding: 0.65rem 1rem;
        font-size: 0.85rem;
    }
  
    .search-submit-btn span {
        display: none; /* Esconder texto no mobile, só ícone */
    }
  
    .clear-search {
        right: 130px; /* Ajustado para mobile */
    }
  
    .search-active-icon {
        right: 120px; /* Ajustado para mobile */
        font-size: 0.9rem;
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
  
    .support-content {
        padding: 2.5rem 2rem;
    }
  
    .support-title-large {
        font-size: 1.75rem;
    }
  
    .support-text-large {
        font-size: 1rem;
    }
  
    .btn-support-whatsapp-large {
        padding: 1rem 2rem;
        font-size: 1rem;
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
  
    .section-title {
        font-size: 1.75rem;
    }
  
    .empty-state-quote {
        font-size: 1rem;
    }
    .product-name-modern {
        font-size: 0.875rem;
        min-height: 2.4em;
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
/* ============================================
   PREMIUM CAROUSEL STYLES
   ============================================ */
.carousel-banner-wrapper {
    width: 100%;
    position: relative;
    overflow: hidden;
    margin-top: 0;
}
/* Mobile: 100% largura, sem padding/margem, sem bordas */
@media (max-width: 768px) {
    .carousel-banner-wrapper {
        padding: 0;
        margin: 0;
        width: 100%;
    }
  
    .carousel-banner-wrapper .mySwiper,
    .carousel-banner-wrapper .swiper-slide,
    .carousel-banner-wrapper picture,
    .carousel-banner-wrapper img {
        width: 100% !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
}
.premium-carousel-container {
    width: 100%;
    position: relative;
    overflow: hidden;
    margin-top: 0;
}
/* Integração menu + banner */
.vitrine-top-bar + .premium-carousel-container {
    margin-top: 0;
}
.carousel-main-container {
    position: relative;
    width: 100%;
    height: 55vh;
    min-height: 450px;
    max-height: 650px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    margin-top: 0;
    border-radius: 0;
}
/* Logo movement based on hover */
.carousel-main-container:has(.carousel-slide:nth-child(1):hover) .moving-logo-container {
    left: 35%;
}
.carousel-main-container:has(.carousel-slide:nth-child(2):hover) .moving-logo-container {
    left: 50%;
}
.carousel-main-container:has(.carousel-slide:nth-child(3):hover) .moving-logo-container {
    left: 65%;
}
.carousel-main-container:has(.carousel-slide:nth-child(4):hover) .moving-logo-container {
    left: 65%;
}
/* Carousel Dots */
.carousel-dots {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 12px;
    z-index: 50;
}
.carousel-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.5);
    background: transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
}
.carousel-dot:hover {
    border-color: rgba(255, 255, 255, 0.8);
    transform: scale(1.2);
}
.carousel-dot.active {
    background: #C7A333;
    border-color: #C7A333;
    width: 24px;
    border-radius: 6px;
}
/* Pause Indicators */
.pause-indicator {
    position: absolute;
    bottom: 25px;
    font-size: 0.7rem;
    opacity: 0.6;
    color: #fff;
    z-index: 10;
    pointer-events: none;
}
.left-pause {
    left: 25px;
}
.right-pause {
    right: 25px;
}
/* Responsive */
@media (max-width: 1024px) {
    .carousel-main-container {
        height: 30vh;
        min-height: 220px;
        max-height: 300px;
    }
  
    .moving-logo-container {
        width: 15vw;
        max-width: 180px;
    }
}
@media (max-width: 768px) {
    .carousel-main-container {
        height: 22vh !important;
        min-height: 160px !important;
        max-height: 220px !important;
    }
  
    .moving-logo-container {
        width: 100px;
        left: 50% !important;
    }
  
    .carousel-dots {
        bottom: 15px;
    }
  
    .pause-indicator {
        bottom: 12px;
        font-size: 0.6rem;
    }
  
    .left-pause {
        left: 12px;
    }
  
    .right-pause {
        right: 12px;
    }
}
@media (max-width: 480px) {
    .carousel-main-container {
        height: 22vh;
        min-height: 180px;
        max-height: 250px;
    }
  
    .moving-logo-container {
        width: 100px;
    }
  
    .carousel-dot {
        width: 10px;
        height: 10px;
    }
  
    .carousel-dot.active {
        width: 20px;
    }
}
/* Carrossel de Marcas */
.marcas-carousel-section {
    padding: 20px 0;
    /* Background color moved to inline style in HTML */
    border-bottom: none !important;
    max-height: 180px;
    overflow: hidden;
}
.container-marcas {
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
}
.marcasSwiper {
    padding: 0 50px !important;
    /* Added min-height for perfect vertical centering */
    min-height: 15vh;
    display: flex;
    align-items: center;
}
.marcasSwiper .swiper-slide {
    display: flex;
    justify-content: center;
    align-items: center;
    /* Increased height for better vertical centering */
    height: 150px;
}
.marcasSwiper .swiper-slide img {
    max-width: 140px;
    max-height: 15vh;
    width: auto;
    height: auto;
    filter: grayscale(100%);
    transition: all 0.3s ease;
    opacity: 0.6;
    display: block;
    margin: auto;
    /* Perfect centering for logos */
    object-fit: contain;
    object-position: center;
}
.marcasSwiper .swiper-slide img:hover {
    filter: grayscale(0%);
    opacity: 1;
    transform: scale(1.05);
}
.marcasSwiper .swiper-button-next,
.marcasSwiper .swiper-button-prev {
    color: #000;
    transform: scale(0.7);
    /* Adjusted to perfectly align with centered logos */
    top: 50%;
    margin-top: 0;
    transform: translateY(-50%) scale(0.7);
}
@media (max-width: 768px) {
    .marcasSwiper {
        padding: 0 30px !important;
        min-height: 120px;
    }
  
    .marcasSwiper .swiper-slide {
        height: 120px;
    }
  
    .marcasSwiper .swiper-slide img {
        max-width: 100px;
        max-height: 80px;
    }
}
/* FORÇAR BOTÃO VERDE - REGRAS ULTRA ESPECÍFICAS */
/* 1. O Botão (Fundo Verde e Formato) */
a.btn-whatsapp-modern[data-product-id] {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
    overflow: hidden !important;
    padding: 12px 25px 12px 55px !important; /* Espaço para a bolinha branca */
    border-radius: 12px !important;
    text-decoration: none !important;
    z-index: 10 !important;
    transition: all 0.5s ease !important;
  
    /* Gradiente Verde WhatsApp Premium */
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%) !important;
    border: 1px solid #075E54 !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
}
/* 2. O Ícone (Bolinha Branca com o símbolo do Whats) */
a.btn-whatsapp-modern[data-product-id] i {
    position: absolute !important;
    left: 10px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    background-color: #ffffff !important; /* Fundo Branco */
    color: #25D366 !important; /* S��mbolo Verde */
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 18px !important;
    z-index: 12 !important;
    transition: all 0.5s ease !important;
}
/* 3. O Texto "Comprar" */
a.btn-whatsapp-modern[data-product-id] span {
    color: #ffffff !important;
    font-weight: 800 !important;
    font-size: 14px !important;
    text-transform: uppercase !important;
    z-index: 11 !important;
    visibility: visible !important;
    display: inline-block !important;
}
/* 4. Efeito de Passar o Mouse (Hover) */
a.btn-whatsapp-modern[data-product-id]:hover {
    padding: 12px 55px 12px 25px !important; /* Inverte o lado */
    filter: brightness(1.1) !important;
}
a.btn-whatsapp-modern[data-product-id]:hover i {
    left: calc(100% - 42px) !important; /* Move a bolinha para a direita */
    transform: translateY(-50%) rotate(360deg) !important;
}
/* 5. Animação do Brilho Passando */
a.btn-whatsapp-modern[data-product-id]::after {
    content: "" !important;
    position: absolute !important;
    top: -50% !important;
    left: -150% !important;
    width: 80px !important;
    height: 200% !important;
    background: rgba(255, 255, 255, 0.3) !important;
    transform: rotate(30deg) !important;
    animation: lightSweep 3s infinite !important;
    z-index: 11 !important;
}
@keyframes lightSweep {
    0% { left: -150%; }
    20% { left: 250%; }
    100% { left: 250%; }
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
    const button = document.getElementById('filterToggleBtnDesktop');
  
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'flex';
        // Pequeno delay para permitir que o display seja aplicado antes da animação
        setTimeout(() => {
            panel.classList.add('show');
        }, 10);
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
        if (button) {
            button.classList.add('active');
        }
    } else {
        panel.classList.remove('show');
        // Aguardar animação antes de esconder
        setTimeout(() => {
            panel.style.display = 'none';
        }, 300);
        if (icon) {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
        if (button) {
            button.classList.remove('active');
        }
    }
}
// Initialize: Keep filters closed if no active filters
document.addEventListener('DOMContentLoaded', function() {
    const hasActiveFilters = <?php echo (!empty($_GET['brand']) || !empty($_GET['price']) || !empty($_GET['sort'])) ? 'true' : 'false'; ?>;
    const panel = document.getElementById('desktopFiltersPanel');
    const icon = document.getElementById('filterToggleIcon');
  
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
function applyBrandFilter(brandId) {
    // Close mobile filters panel if open
    const mobilePanel = document.getElementById('mobileFiltersPanel');
    if (mobilePanel && mobilePanel.classList.contains('active')) {
        toggleFilters();
    }
  
    // Close desktop filters panel if open
    const desktopPanel = document.getElementById('desktopFiltersPanel');
    if (desktopPanel && desktopPanel.style.display !== 'none') {
        toggleDesktopFilters();
    }
  
    // Apply filter
    applyFilter('brand', brandId);
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
// Aplicar logo de fundo nos placeholders de produtos com blur
document.addEventListener('DOMContentLoaded', function() {
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
</script>
<!-- Adicionando JavaScript para inicializar o carrossel de marcas -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
// Inicializar carrossel de marcas
new Swiper(".marcasSwiper", {
    slidesPerView: 2,
    spaceBetween: 30,
    loop: true,
    autoplay: {
        delay: 2000,
        disableOnInteraction: false,
    },
    navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
    },
    breakpoints: {
        768: {
            slidesPerView: 4,
            spaceBetween: 30
        },
        1024: {
            slidesPerView: 6,
            spaceBetween: 30
        }
    }
});
</script>
<!-- CSS do Botão Adicionar ao Carrinho -->
<style>
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
    color: #d4af37;
    font-weight: 800;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
    transition: all 0.5s ease;
    line-height: 1.2;
}
/* ─────────────── HOVER ─────────────── */
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
/* Pulse glow */
@keyframes pulse-glow {
    0% { box-shadow: 0 0 0 0 rgba(212,175,55,0.12); }
    70% { box-shadow: 0 0 0 15px rgba(212,175,55,0); }
    100% { box-shadow: 0 0 0 0 rgba(212,175,55,0); }
}
a.btn-whatsapp-modern[data-product-id]:hover {
    animation: pulse-glow 1.8s infinite ease-in-out;
}
/* Responsivo */
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
</style>
<!-- JavaScript para Adicionar ao Carrinho -->
<script>
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
    // Remover notificação anterior se existir
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
// Estilo para notificações
const style = document.createElement('style');
style.textContent = `
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
`;
document.head.appendChild(style);
</script>
<?php
if (!$is_ajax) {
    echo '</div>'; // Fecha public-main-content
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const logo = document.querySelector('.header-logo-vitrine');
    if (logo) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                logo.classList.add('logo-scrolled');
                if (window.innerWidth > 768) {
                    logo.classList.add('logo-scrolled-desktop');
                }
            } else {
                logo.classList.remove('logo-scrolled');
                logo.classList.remove('logo-scrolled-desktop');
            }
        });
    }
});
</script>
