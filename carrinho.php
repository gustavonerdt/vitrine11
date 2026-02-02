<?php
// session_start() ja e chamado em config.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// 1. PROCESSAR ADIÇÃO VIA GET (Vindo da Index ou outras páginas)
if (isset($_GET['add']) && !empty($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    if ($product_id > 0) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
        // Redirecionar para a mesma página sem o parâmetro GET para limpar a URL
        header('Location: ' . APP_URL . '/carrinho.php');
        exit;
    }
}

$pageTitle = 'Minha Sacola';
$bodyClass = 'cart-page';

// 2. LIMPAR PRODUTOS INATIVOS DO CARRINHO
$removed_products = [];
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($product_ids);
        $active_ids = array_column($stmt->fetchAll(), 'id');
        
        // Remover produtos inativos
        foreach ($product_ids as $pid) {
            if (!in_array($pid, $active_ids)) {
                $removed_products[] = $pid;
                unset($_SESSION['cart'][$pid]);
            }
        }
    }
}

// 3. BUSCAR ITENS DO CARRINHO
$cart_items = [];
$subtotal = 0;
$total_items = 0;
$frete_gratis_min = floatval(getSetting($pdo, 'frete_gratis_valor_minimo', 0));

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.price, p.original_price, p.image_path, b.name as brand_name
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.id = ? AND p.is_active = 1
            ");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $image_url = $product['image_path'] ?? '';
                if (db_table_exists($pdo, 'product_images')) {
                    $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_cover = 1 LIMIT 1");
                    $imgStmt->execute([$product_id]);
                    $coverImage = $imgStmt->fetch();
                    if ($coverImage && !empty($coverImage['image_url'])) {
                        $image_url = $coverImage['image_url'];
                    }
                }
                
                if (!empty($image_url)) {
                    $image_url = trim($image_url);
                    if (!preg_match('/^https?:\/\//i', $image_url)) {
                        $image_url = ltrim($image_url, '/');
                        $image_url = rtrim(APP_URL, '/') . '/' . $image_url;
                    }
                }
                
                $item_total = floatval($product['price']) * $quantity;
                $subtotal += $item_total;
                $total_items += $quantity;
                
                $cart_items[] = [
                    'product_id' => (int)$product_id,
                    'name' => $product['name'],
                    'brand_name' => $product['brand_name'] ?? '',
                    'price' => floatval($product['price']),
                    'original_price' => !empty($product['original_price']) ? floatval($product['original_price']) : null,
                    'quantity' => $quantity,
                    'item_total' => $item_total,
                    'image_url' => $image_url
                ];
            }
        } catch (PDOException $e) {
            error_log("Cart item fetch error: " . $e->getMessage());
        }
    }
}

$faltam_frete_gratis = max(0, $frete_gratis_min - $subtotal);
$tem_frete_gratis = $subtotal >= $frete_gratis_min;

// 3. BUSCAR ORDER BUMPS (ESTÁVEL)
$order_bumps = [];
try {
    $bumpStmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.original_price, p.image_path, b.name as brand_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.is_active = 1
        AND p.id NOT IN (" . (!empty($cart_items) ? implode(',', array_column($cart_items, 'product_id')) : '0') . ")
        ORDER BY p.id DESC
        LIMIT 12
    ");
    $bumpStmt->execute();
    $order_bumps = $bumpStmt->fetchAll();
    
    foreach ($order_bumps as &$bump) {
        $image_url = $bump['image_path'] ?? '';
        if (db_table_exists($pdo, 'product_images')) {
            $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_cover = 1 LIMIT 1");
            $imgStmt->execute([$bump['id']]);
            $coverImage = $imgStmt->fetch();
            if ($coverImage && !empty($coverImage['image_url'])) {
                $image_url = $coverImage['image_url'];
            }
        }
        
        if (!empty($image_url)) {
            $image_url = trim($image_url);
            if (!preg_match('/^https?:\/\//i', $image_url)) {
                $image_url = ltrim($image_url, '/');
                $image_url = rtrim(APP_URL, '/') . '/' . $image_url;
            }
        }
        $bump['image_url'] = $image_url;
    }
    unset($bump);
} catch (PDOException $e) {
    error_log("Order bumps fetch error: " . $e->getMessage());
}

include __DIR__ . '/includes/public-header.php';
?>

<style>
    /* ESTILOS GERAIS */
    .cart-page-container { background: #fef9e0; padding: 2rem 0; min-height: 80vh; overflow-x: hidden; }
    .cart-title { font-family: 'Sora', sans-serif; font-weight: 800; margin-bottom: 2rem; color: #1a1a1a; text-transform: uppercase; }
    
    .cart-content { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; }
    @media (max-width: 992px) { .cart-content { grid-template-columns: 1fr; } }

    .cart-items-section { background: #fff; border-radius: 24px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.03); }
    .cart-item { display: grid; grid-template-columns: 100px 1fr auto; gap: 1.5rem; padding: 1.5rem 0; border-bottom: 1px solid #f0f0f0; align-items: center; }
    .cart-item:last-child { border-bottom: none; }
    .cart-item-image { width: 100px; height: 100px; border-radius: 16px; overflow: hidden; background: #f9f9f9; }
    .cart-item-image img { width: 100%; height: 100%; object-fit: cover; }
    
    .cart-item-brand { font-size: 0.75rem; color: #C7A333; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
    .cart-item-name { font-weight: 700; font-size: 1.1rem; color: #1a1a1a; margin-bottom: 8px; }
    .cart-item-price { font-weight: 800; font-size: 1.2rem; color: #1a1a1a; }
    
    .qty-display { width: 30px; text-align: center; font-weight: 900; color: #000; font-size: 1.1rem; }

    .cart-summary { background: #fff; border-radius: 24px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.03); height: fit-content; position: sticky; top: 100px; border: 1px solid #f0f0f0; }
    .btn-checkout { display: block; width: 100%; padding: 1.25rem; background: linear-gradient(135deg, #C7A333, #B8962E); color: #fff !important; text-align: center; border-radius: 24px; font-weight: 800; text-transform: uppercase; margin-top: 1.5rem; box-shadow: 0 10px 20px rgba(199, 163, 51, 0.3); transition: 0.3s; text-decoration: none; }
    .btn-checkout:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3); }

    /* ORDER BUMPS - CARROSSEL */
    .order-bumps-section { margin-top: 4rem; position: relative; padding: 0 10px; }
    .order-bumps-title { font-family: 'Sora', sans-serif; font-weight: 800; color: #000; margin-bottom: 1.5rem; font-size: 1.8rem; }
    
    .bumps-carousel-container { position: relative; padding: 10px 0; }
    .bumps-track { display: flex; gap: 20px; transition: transform 0.5s ease; scroll-behavior: smooth; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; padding: 10px 5px 20px; }
    .bumps-track::-webkit-scrollbar { display: none; }
    
    .order-bump-card {
        min-width: 240px;
        max-width: 240px;
        background: #fff;
        border-radius: 20px;
        padding: 12px;
        border: 1px solid #f0f0f0;
        transition: 0.3s;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .order-bump-card:hover { transform: translateY(-5px); border-color: #C7A333; box-shadow: 0 10px 30px rgba(199, 163, 51, 0.1); }
    
    .bump-img-wrapper { aspect-ratio: 1; border-radius: 15px; overflow: hidden; background: #f9f9f9; margin-bottom: 12px; display: block; }
    .bump-img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
    
    /* NOMES FIXOS E VISÍVEIS */
    .bump-info { flex-grow: 1; display: block !important; opacity: 1 !important; visibility: visible !important; pointer-events: auto !important; }
    .bump-brand { font-size: 0.7rem; color: #999; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; display: block !important; }
    .bump-title { font-size: 0.95rem; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; line-height: 1.3; height: 2.6em; overflow: hidden; display: -webkit-box !important; -webkit-line-clamp: 2; -webkit-box-orient: vertical; text-decoration: none; visibility: visible !important; opacity: 1 !important; }
    .bump-price { font-size: 1.15rem; font-weight: 800; color: #1a1a1a; margin-bottom: 12px; display: block !important; }
    
    .btn-add-bump-direct { width: 100%; padding: 10px; background: #1a1a1a; color: #fff; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-add-bump-direct:hover { background: #C7A333; }

    /* SETAS DO CARROSSEL */
    .carousel-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 50px; height: 50px; background: #fff; border: 1px solid #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 100; box-shadow: 0 6px 15px rgba(0,0,0,0.1); transition: 0.3s; color: #333; }
    .carousel-nav-btn:hover { background: #C7A333; color: #fff; border-color: #C7A333; transform: translateY(-50%) scale(1.1); }
    .nav-prev { left: -15px; }
    .nav-next { right: -15px; }
    @media (max-width: 768px) { .nav-prev, .nav-next { display: none; } }
    
    /* MOBILE OPTIMIZATIONS */
    @media (max-width: 768px) {
        .cart-page-container { padding: 1rem 0; }
        .cart-title { font-size: 1.3rem; margin-bottom: 1rem; }
        
        .cart-items-section { padding: 1rem; border-radius: 16px; }
        .cart-item { grid-template-columns: 70px 1fr; gap: 0.75rem; padding: 1rem 0; }
        .cart-item-image { width: 70px; height: 70px; border-radius: 12px; }
        .cart-item-name { font-size: 0.95rem; margin-bottom: 4px; }
        .cart-item-price { font-size: 1rem; }
        .cart-item-brand { font-size: 0.7rem; }
        
        .cart-item-actions { 
            grid-column: 1 / -1; 
            justify-content: space-between; 
            margin-top: 0.75rem; 
            padding-top: 0.75rem; 
            border-top: 1px dashed #eee;
        }
        .quantity-selector { padding: 3px; }
        .quantity-selector button { width: 32px; height: 32px; font-size: 1.2rem; }
        .qty-display { font-size: 1rem; }
        
        /* ORDER BUMPS MOBILE - 2 cards visible */
        .order-bumps-section { margin-top: 2rem; padding: 0 5px; }
        .order-bumps-title { font-size: 1.3rem; margin-bottom: 1rem; }
        .bumps-track { gap: 10px; padding: 5px 2px 15px; }
        .order-bump-card { 
            min-width: calc(50% - 10px); 
            max-width: calc(50% - 10px); 
            padding: 8px; 
            border-radius: 14px; 
        }
        .bump-img-wrapper { border-radius: 10px; margin-bottom: 8px; }
        .bump-brand { font-size: 0.6rem; }
        .bump-title { font-size: 0.8rem; height: 2.4em; margin-bottom: 4px; }
        .bump-price { font-size: 0.95rem; margin-bottom: 8px; }
        .btn-add-bump-direct { padding: 8px; font-size: 0.75rem; border-radius: 10px; }
        
        .cart-summary { border-radius: 16px; padding: 1.5rem; margin-top: 1rem; }
        .btn-checkout { padding: 1rem; font-size: 0.95rem; border-radius: 12px; }
    }

    /* MODAL DE RETENÇÃO - DOURADO */
    .retention-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(8px); }
    .retention-modal.show { display: flex; }
    .retention-content { background: #fff; width: 90%; max-width: 420px; border-radius: 30px; padding: 2.5rem; text-align: center; position: relative; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes popIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    
    .modal-close { position: absolute; top: 1.5rem; right: 1.5rem; font-size: 1.5rem; color: #ccc; cursor: pointer; }
    .retention-icon { width: 90px; height: 90px; background: #C7A333; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 10px 20px rgba(199, 163, 51, 0.2); }
    .retention-icon i { font-size: 2.8rem; color: #fff; }
    
    .retention-title { font-family: 'Sora', sans-serif; font-size: 1.6rem; font-weight: 800; color: #1a1a1a; margin-bottom: 1rem; }
    .retention-text { font-size: 1rem; color: #666; margin-bottom: 0.5rem; line-height: 1.5; }
    .retention-highlight { color: #C7A333; font-weight: 800; margin-bottom: 1.5rem; font-size: 1rem; }
    .offer-badge { background: #fef9e0; border: 2px dashed #C7A333; padding: 12px; border-radius: 15px; color: #8a6d1d; font-weight: 800; margin-bottom: 2rem; font-size: 0.95rem; }
    
    .btn-keep-cart { display: block; width: 100%; padding: 1.25rem; background: #1a1a1a; color: #fff; border: none; border-radius: 18px; font-weight: 800; font-size: 1.1rem; cursor: pointer; margin-bottom: 1.2rem; box-shadow: 0 10px 20px rgba(0,0,0,0.1); transition: 0.3s; }
    .btn-keep-cart:hover { background: #C7A333; transform: translateY(-3px); }
    .btn-remove-anyway { background: none; border: none; color: #bbb; font-weight: 600; text-decoration: underline; cursor: pointer; }
</style>

<div class="cart-page-container">
    <div class="container">
        <h1 class="cart-title">MINHA SACOLA</h1>
        
        <?php if (empty($cart_items)): ?>
            <div class="cart-items-section" style="text-align: center; padding: 4rem;">
                <i class="fas fa-shopping-bag" style="font-size: 4rem; color: #ddd; margin-bottom: 2rem;"></i>
                <h2 style="font-weight: 800; color: #000;">SUA SACOLA ESTÁ VAZIA</h2>
                <p>Que tal adicionar alguns perfumes incríveis?</p>
                <a href="<?php echo APP_URL; ?>/" class="btn-checkout" style="max-width: 300px; margin: 2rem auto;">IR ÀS COMPRAS</a>
            </div>
        <?php else: ?>
            <div class="cart-content">
                <div class="cart-items-section">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                            <div class="cart-item-image">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="cart-item-details">
                                <div class="cart-item-brand"><?php echo htmlspecialchars($item['brand_name']); ?></div>
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <?php 
                                $hasDiscount = !empty($item['original_price']) && $item['original_price'] > $item['price'];
                                $discountPercent = $hasDiscount ? round((($item['original_price'] - $item['price']) / $item['original_price']) * 100) : 0;
                                ?>
                                <?php if ($hasDiscount): ?>
                                <div class="cart-item-price-wrapper">
                                    <span style="background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; margin-right: 6px;">-<?php echo $discountPercent; ?>%</span>
                                    <span style="text-decoration: line-through; color: #888; font-size: 0.85rem; margin-right: 6px;"><?php echo formatPrice($item['original_price']); ?></span>
                                    <span class="cart-item-price" style="color: #22c55e;"><?php echo formatPrice($item['price']); ?></span>
                                </div>
                                <?php else: ?>
                                <div class="cart-item-price"><?php echo formatPrice($item['price']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-actions" style="display: flex; align-items: center; gap: 1rem;">
                                <div class="quantity-selector" style="display: flex; align-items: center; background: #f5f5f5; border-radius: 12px; padding: 5px;">
                                    <button onclick="handleQtyChange(<?php echo $item['product_id']; ?>, -1)" style="width: 35px; height: 35px; border: none; background: none; cursor: pointer; font-weight: 900; color: #000; font-size: 1.4rem;">-</button>
                                    <span class="qty-display" id="qty-<?php echo $item['product_id']; ?>"><?php echo $item['quantity']; ?></span>
                                    <button onclick="handleQtyChange(<?php echo $item['product_id']; ?>, 1)" style="width: 35px; height: 35px; border: none; background: none; cursor: pointer; font-weight: 900; color: #000; font-size: 1.4rem;">+</button>
                                </div>
                                <button onclick="triggerRetention(<?php echo $item['product_id']; ?>)" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.9rem; text-decoration: underline; font-weight: 600;">Remover</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <h3 style="font-weight: 800; margin-bottom: 1.5rem; border-bottom: 2px solid #f9f9f9; padding-bottom: 1rem; color: #000;">RESUMO</h3>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-weight: 700; color: #000;">
                        <span>Produtos (<?php echo $total_items; ?>)</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 900; color: #000;">
                        <span>TOTAL</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <a href="<?php echo APP_URL; ?>/checkout-entrega.php" class="btn-checkout">FECHAR PEDIDO</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ORDER BUMPS -->
        <?php if (!empty($order_bumps)): ?>
            <div class="order-bumps-section">
                <h2 class="order-bumps-title">ACHADINHOS PRA VOCÊ</h2>
                
                <div class="bumps-carousel-container">
                    <button class="carousel-nav-btn nav-prev" onclick="scrollBumps(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="carousel-nav-btn nav-next" onclick="scrollBumps(1)"><i class="fas fa-chevron-right"></i></button>
                    
                    <div class="bumps-track" id="bumpsTrack">
                        <?php foreach ($order_bumps as $bump): 
                            $bumpHasDiscount = !empty($bump['original_price']) && floatval($bump['original_price']) > floatval($bump['price']);
                            $bumpDiscountPercent = $bumpHasDiscount ? round(((floatval($bump['original_price']) - floatval($bump['price'])) / floatval($bump['original_price'])) * 100) : 0;
                        ?>
                            <div class="order-bump-card">
                                <a href="<?php echo APP_URL; ?>/product.php?id=<?php echo $bump['id']; ?>" class="bump-img-wrapper">
                                    <img src="<?php echo htmlspecialchars($bump['image_url']); ?>" alt="<?php echo htmlspecialchars($bump['name']); ?>">
                                    <?php if ($bumpHasDiscount): ?>
                                    <span style="position: absolute; top: 8px; right: 8px; background: #ef4444; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">-<?php echo $bumpDiscountPercent; ?>%</span>
                                    <?php endif; ?>
                                </a>
                                <div class="bump-info">
                                    <div class="bump-brand"><?php echo htmlspecialchars($bump['brand_name'] ?? 'Premium'); ?></div>
                                    <div class="bump-title"><?php echo htmlspecialchars($bump['name']); ?></div>
                                    <?php if ($bumpHasDiscount): ?>
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <span style="text-decoration: line-through; color: #888; font-size: 0.85rem;"><?php echo formatPrice($bump['original_price']); ?></span>
                                        <span class="bump-price" style="color: #22c55e; margin: 0;"><?php echo formatPrice($bump['price']); ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div class="bump-price"><?php echo formatPrice($bump['price']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn-add-bump-direct" onclick="addToCartAndScroll(<?php echo $bump['id']; ?>, 1)">ADICIONAR</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE RETENÇÃO -->
<div id="retentionModal" class="retention-modal">
    <div class="retention-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div class="retention-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2 class="retention-title" id="m-title">Espere! Não perca essa oferta!</h2>
        <p class="retention-text" id="m-text">A promoção deste produto vai acabar em breve</p>
        <p class="retention-highlight" id="m-high">e o preço pode aumentar a qualquer momento!</p>
        <div class="offer-badge" id="m-badge">! Oferta por tempo limitado !</div>
        <button class="btn-keep-cart" onclick="closeModal()">
            <i class="fas fa-shopping-cart"></i> MANTER NO CARRINHO
        </button>
        <button class="btn-remove-anyway" onclick="finalRemove()">Remover mesmo assim</button>
    </div>
</div>

<script>
let pendingId = null;
const variations = [
    { t: "Espere! Não perca essa oferta!", x: "A promoção deste produto vai acabar em breve", h: "e o preço pode aumentar a qualquer momento!", b: "! Oferta por tempo limitado !" },
    { t: "Tem certeza disso?", x: "Este item é um dos nossos mais vendidos", h: "Garanta o seu antes que o estoque acabe!", b: "🔥 Restam poucas unidades" },
    { t: "Não vá embora ainda!", x: "Ao remover este item, você perde benefícios", h: "Aproveite as condições exclusivas de hoje.", b: "💎 Condição Especial Ativada" }
];

function triggerRetention(id) {
    pendingId = id;
    const v = variations[Math.floor(Math.random() * variations.length)];
    document.getElementById('m-title').innerText = v.t;
    document.getElementById('m-text').innerText = v.x;
    document.getElementById('m-high').innerText = v.h;
    document.getElementById('m-badge').innerText = v.b;
    document.getElementById('retentionModal').classList.add('show');
}

function closeModal() {
    document.getElementById('retentionModal').classList.remove('show');
}

function handleQtyChange(id, delta) {
    const el = document.getElementById('qty-'+id);
    let current = parseInt(el.innerText);
    if (current + delta <= 0) {
        triggerRetention(id);
    } else {
        updateCart(id, current + delta);
    }
}

function finalRemove() {
    if (pendingId) updateCart(pendingId, 0);
    closeModal();
}

function updateCart(id, qty) {
    const fd = new FormData();
    fd.append('product_id', id);
    fd.append('quantity', qty);
    fetch('<?php echo APP_URL; ?>/api/update-cart.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { 
        if(d.success) {
            location.reload(); 
        } else {
            console.error('Erro ao atualizar:', d.error);
        }
    })
    .catch(err => console.error('Erro de rede:', err));
}

function addToCart(id, qty) {
    const fd = new FormData();
    fd.append('product_id', id);
    fd.append('quantity', qty);
    fetch('<?php echo APP_URL; ?>/api/add-to-cart.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { 
        if(d.success) {
            location.reload(); 
        } else {
            alert('Erro ao adicionar: ' + (d.error || 'Erro desconhecido'));
        }
    })
    .catch(err => console.error('Erro de rede:', err));
}

function scrollBumps(direction) {
    const track = document.getElementById('bumpsTrack');
    const scrollAmount = 260 * 2; 
    track.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

// Autoplay do carrossel de achadinhos - a cada 8 segundos
let bumpsAutoplay;
function startBumpsAutoplay() {
    bumpsAutoplay = setInterval(function() {
        const track = document.getElementById('bumpsTrack');
        if (track) {
            // Verifica se chegou ao final
            if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 10) {
                // Volta ao inicio
                track.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                scrollBumps(1);
            }
        }
    }, 8000);
}

// Pausar autoplay ao interagir com o carrossel
document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('bumpsTrack');
    if (track) {
        startBumpsAutoplay();
        
        track.addEventListener('mouseenter', function() {
            clearInterval(bumpsAutoplay);
        });
        
        track.addEventListener('mouseleave', function() {
            startBumpsAutoplay();
        });
        
        track.addEventListener('touchstart', function() {
            clearInterval(bumpsAutoplay);
        });
        
        track.addEventListener('touchend', function() {
            setTimeout(startBumpsAutoplay, 3000);
        });
    }
});

// Scroll para o topo ao adicionar produto do carrossel
function addToCartAndScroll(id, qty) {
    const fd = new FormData();
    fd.append('product_id', id);
    fd.append('quantity', qty);
    
    // Scroll suave para o topo
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    fetch('<?php echo APP_URL; ?>/api/add-to-cart.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { 
        if(d.success) {
            location.reload(); 
        } else {
            alert('Erro ao adicionar: ' + (d.error || 'Erro desconhecido'));
        }
    })
    .catch(err => console.error('Erro de rede:', err));
}
</script>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
