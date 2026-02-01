<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Obrigado pela Compra';
$bodyClass = 'thank-you-page';

// Buscar dados do pedido se tiver order_id
$order = null;
$order_items = [];
if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    try {
        $orderId = (int)$_GET['order_id'];
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Buscar itens do pedido
            $itemsStmt = $pdo->prepare("
                SELECT oi.*, p.name as product_name, p.image_path
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemsStmt->execute([$orderId]);
            $order_items = $itemsStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching order: " . $e->getMessage());
    }
}

// Verificar status do pagamento
$payment_pending = isset($_GET['payment']) && $_GET['payment'] === 'pending';
$payment_status = $_GET['status'] ?? null;

// Buscar configuracoes da loja
$appName = getSetting($pdo, 'app_name', APP_NAME);
$whatsappNumber = getSetting($pdo, 'whatsapp_float_number', '');
$whatsappMessage = getSetting($pdo, 'whatsapp_float_message', 'Olá! Tenho uma dúvida sobre meu pedido.');

// Limpar numero de WhatsApp
$whatsappNumberClean = preg_replace('/[^0-9]/', '', $whatsappNumber);
$whatsappLink = 'https://wa.me/' . $whatsappNumberClean . '?text=' . urlencode($whatsappMessage);

// Buscar produtos para upsell (compra rapida)
$upsell_products = [];
try {
    // Excluir produtos ja comprados neste pedido
    $purchasedIds = [];
    if (!empty($order_items)) {
        foreach ($order_items as $item) {
            $purchasedIds[] = $item['product_id'];
        }
    }
    
    $excludeClause = '';
    if (!empty($purchasedIds)) {
        $excludeClause = 'AND p.id NOT IN (' . implode(',', array_map('intval', $purchasedIds)) . ')';
    }
    
    $upsellStmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.image_path, b.name as brand_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.is_active = 1 $excludeClause
        ORDER BY RAND()
        LIMIT 6
    ");
    $upsellStmt->execute();
    $upsell_products = $upsellStmt->fetchAll();
    
    foreach ($upsell_products as &$product) {
        $image_url = $product['image_path'] ?? '';
        if (db_table_exists($pdo, 'product_images')) {
            $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_cover = 1 LIMIT 1");
            $imgStmt->execute([$product['id']]);
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
        $product['image_url'] = $image_url;
    }
    unset($product);
} catch (PDOException $e) {
    error_log("Upsell products fetch error: " . $e->getMessage());
}

// Buscar produtos para carrossel (produtos recomendados)
$recommended_products = [];
try {
    $recStmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.image_path, b.name as brand_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.is_active = 1
        ORDER BY RAND()
        LIMIT 12
    ");
    $recStmt->execute();
    $recommended_products = $recStmt->fetchAll();
    
    foreach ($recommended_products as &$product) {
        $image_url = $product['image_path'] ?? '';
        if (db_table_exists($pdo, 'product_images')) {
            $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_cover = 1 LIMIT 1");
            $imgStmt->execute([$product['id']]);
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
        $product['image_url'] = $image_url;
    }
    unset($product);
} catch (PDOException $e) {
    error_log("Recommended products fetch error: " . $e->getMessage());
}

// Limpar carrinho apos compra finalizada
if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}
if (isset($_SESSION['checkout'])) {
    unset($_SESSION['checkout']);
}

include __DIR__ . '/includes/public-header.php';
?>

<style>
    /* ESTILOS DA PAGINA DE OBRIGADO - IGUAL AO CARRINHO */
    .thank-you-container { background: #fef9e0; padding: 2rem 0; min-height: 80vh; overflow-x: hidden; }
    .thank-you-title { font-family: 'Sora', sans-serif; font-weight: 800; margin-bottom: 2rem; color: #1a1a1a; text-transform: uppercase; text-align: center; }
    
    /* Alerta de Pagamento Pendente */
    .payment-pending-alert {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.1));
        border: 2px solid #f59e0b;
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        text-align: left;
    }
    .payment-pending-alert i { color: #f59e0b; font-size: 1.5rem; }
    .payment-pending-alert h4 { color: #d97706; font-weight: 700; }
    .payment-pending-alert p { color: #92400e; font-size: 0.95rem; line-height: 1.5; }
    
    /* BOX PRINCIPAL */
    .thank-you-box { 
        background: #fff; 
        border-radius: 24px; 
        padding: 3rem; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.03); 
        max-width: 700px; 
        margin: 0 auto 3rem;
        text-align: center;
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        box-shadow: 0 15px 30px rgba(34, 197, 94, 0.3);
    }
    .success-icon i {
        font-size: 3rem;
        color: #fff;
    }
    
    .thank-you-message {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1a1a1a;
        margin-bottom: 1rem;
        line-height: 1.3;
    }
    
    .store-name {
        color: #C7A333;
        font-weight: 900;
    }
    
    .thank-you-subtext {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    
    .order-info {
        background: #f9f9f9;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid #f0f0f0;
    }
    .order-info-title {
        font-weight: 800;
        color: #1a1a1a;
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    .order-info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #eee;
        font-size: 0.95rem;
    }
    .order-info-row:last-child {
        border-bottom: none;
    }
    .order-info-label {
        color: #666;
    }
    .order-info-value {
        font-weight: 700;
        color: #1a1a1a;
    }
    
    /* BOTAO WHATSAPP */
    .btn-whatsapp {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 1.25rem 2rem;
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: #fff;
        border-radius: 16px;
        font-weight: 800;
        text-transform: uppercase;
        text-decoration: none;
        box-shadow: 0 10px 20px rgba(37, 211, 102, 0.3);
        transition: 0.3s;
        margin-bottom: 1.5rem;
    }
    .btn-whatsapp:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(37, 211, 102, 0.4);
    }
    .btn-whatsapp i {
        font-size: 1.5rem;
    }
    
    .btn-continue-shopping {
        display: inline-block;
        padding: 1rem 2rem;
        background: #1a1a1a;
        color: #fff;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: 0.3s;
    }
    .btn-continue-shopping:hover {
        background: #C7A333;
    }
    
    /* CARROSSEL DE PRODUTOS RECOMENDADOS */
    .recommended-section { margin-top: 3rem; position: relative; padding: 0 10px; }
    .recommended-title { font-family: 'Sora', sans-serif; font-weight: 800; color: #000; margin-bottom: 1.5rem; font-size: 1.8rem; text-align: center; }
    
    .recommended-carousel-container { position: relative; padding: 10px 0; }
    .recommended-track { 
        display: flex; 
        gap: 20px; 
        transition: transform 0.5s ease; 
        scroll-behavior: smooth; 
        overflow-x: auto; 
        scrollbar-width: none; 
        -ms-overflow-style: none; 
        padding: 10px 5px 20px; 
    }
    .recommended-track::-webkit-scrollbar { display: none; }
    
    .recommended-card {
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
    .recommended-card:hover { 
        transform: translateY(-5px); 
        border-color: #C7A333; 
        box-shadow: 0 10px 30px rgba(199, 163, 51, 0.1); 
    }
    
    .recommended-img-wrapper { 
        aspect-ratio: 1; 
        border-radius: 15px; 
        overflow: hidden; 
        background: #f9f9f9; 
        margin-bottom: 12px; 
        display: block; 
    }
    .recommended-img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
    
    .recommended-info { flex-grow: 1; display: block; }
    .recommended-brand { font-size: 0.7rem; color: #999; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
    .recommended-name { 
        font-size: 0.95rem; 
        font-weight: 700; 
        color: #1a1a1a; 
        margin-bottom: 8px; 
        line-height: 1.3; 
        height: 2.6em; 
        overflow: hidden; 
        display: -webkit-box; 
        -webkit-line-clamp: 2; 
        -webkit-box-orient: vertical; 
        text-decoration: none; 
    }
    .recommended-price { font-size: 1.15rem; font-weight: 800; color: #1a1a1a; margin-bottom: 12px; }
    
    .btn-add-recommended { 
        width: 100%; 
        padding: 10px; 
        background: #1a1a1a; 
        color: #fff; 
        border: none; 
        border-radius: 12px; 
        font-weight: 700; 
        cursor: pointer; 
        transition: 0.2s; 
        text-decoration: none;
        text-align: center;
        display: block;
    }
    .btn-add-recommended:hover { background: #C7A333; }

    /* SETAS DO CARROSSEL */
    .carousel-nav-btn { 
        position: absolute; 
        top: 50%; 
        transform: translateY(-50%); 
        width: 50px; 
        height: 50px; 
        background: #fff; 
        border: 1px solid #eee; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer; 
        z-index: 100; 
        box-shadow: 0 6px 15px rgba(0,0,0,0.1); 
        transition: 0.3s; 
        color: #333; 
    }
    .carousel-nav-btn:hover { 
        background: #C7A333; 
        color: #fff; 
        border-color: #C7A333; 
        transform: translateY(-50%) scale(1.1); 
    }
    .nav-prev { left: -15px; }
    .nav-next { right: -15px; }
    @media (max-width: 768px) { 
        .nav-prev, .nav-next { display: none; } 
        .thank-you-box { padding: 2rem 1.5rem; margin: 0 1rem 2rem; }
        .thank-you-message { font-size: 1.4rem; }
    }
    
    /* SECAO DE COMPRA RAPIDA (UPSELL) */
    .upsell-section {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
        border-radius: 24px;
        padding: 2.5rem;
        margin: 0 auto 3rem;
        max-width: 900px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .upsell-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #C7A333, #f0d76a, #C7A333);
    }
    .upsell-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #C7A333, #f0d76a);
        color: #1a1a1a;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 800;
        font-size: 0.85rem;
        text-transform: uppercase;
        margin-bottom: 1.5rem;
    }
    .upsell-title {
        font-family: 'Sora', sans-serif;
        font-weight: 800;
        color: #fff;
        font-size: 1.8rem;
        margin-bottom: 0.75rem;
        line-height: 1.2;
    }
    .upsell-subtitle {
        color: #aaa;
        font-size: 1.05rem;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    .upsell-highlight {
        color: #C7A333;
        font-weight: 700;
    }
    
    .upsell-products {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 768px) {
        .upsell-products {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
    }
    @media (max-width: 480px) {
        .upsell-products {
            grid-template-columns: 1fr;
        }
        .upsell-section { padding: 1.5rem; }
        .upsell-title { font-size: 1.4rem; }
    }
    
    .upsell-card {
        background: #fff;
        border-radius: 16px;
        padding: 15px;
        text-align: left;
        transition: 0.3s;
        cursor: pointer;
        position: relative;
        border: 2px solid transparent;
    }
    .upsell-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(199, 163, 51, 0.2);
        border-color: #C7A333;
    }
    .upsell-card.selected {
        border-color: #C7A333;
        background: #fffef5;
    }
    .upsell-card.selected::after {
        content: '';
        position: absolute;
        top: 12px;
        right: 12px;
        width: 24px;
        height: 24px;
        background: #C7A333;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .upsell-card.selected::before {
        content: '\\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        top: 15px;
        right: 17px;
        color: #fff;
        font-size: 12px;
        z-index: 2;
    }
    
    .upsell-card-img {
        width: 100%;
        aspect-ratio: 1;
        border-radius: 12px;
        overflow: hidden;
        background: #f5f5f5;
        margin-bottom: 12px;
    }
    .upsell-card-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .upsell-card-brand {
        font-size: 0.7rem;
        color: #999;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .upsell-card-name {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 8px;
        line-height: 1.3;
        height: 2.6em;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .upsell-card-price {
        font-size: 1.1rem;
        font-weight: 800;
        color: #C7A333;
    }
    
    .upsell-total {
        background: rgba(199, 163, 51, 0.15);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .upsell-total-label {
        color: #aaa;
        font-size: 0.95rem;
    }
    .upsell-total-value {
        color: #fff;
        font-size: 1.4rem;
        font-weight: 800;
    }
    
    .btn-upsell-buy {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
        max-width: 400px;
        padding: 1.25rem 2rem;
        background: linear-gradient(135deg, #C7A333, #f0d76a);
        color: #1a1a1a;
        border: none;
        border-radius: 16px;
        font-weight: 800;
        font-size: 1.1rem;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        box-shadow: 0 10px 30px rgba(199, 163, 51, 0.4);
    }
    .btn-upsell-buy:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(199, 163, 51, 0.5);
    }
    .btn-upsell-buy:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .upsell-skip {
        display: block;
        color: #666;
        font-size: 0.9rem;
        margin-top: 1rem;
        text-decoration: underline;
        cursor: pointer;
    }
    .upsell-skip:hover {
        color: #999;
    }
    
    /* Modal de confirmacao upsell */
    .upsell-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .upsell-modal.show {
        display: flex;
    }
    .upsell-modal-content {
        background: #fff;
        border-radius: 24px;
        padding: 2.5rem;
        max-width: 500px;
        width: 100%;
        text-align: center;
        position: relative;
    }
    .upsell-modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 36px;
        height: 36px;
        border: none;
        background: #f5f5f5;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #666;
        transition: 0.2s;
    }
    .upsell-modal-close:hover {
        background: #eee;
    }
    .upsell-modal-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #C7A333, #f0d76a);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
    }
    .upsell-modal-icon i {
        font-size: 2.5rem;
        color: #fff;
    }
    .upsell-modal-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1a1a1a;
        margin-bottom: 0.5rem;
    }
    .upsell-modal-text {
        color: #666;
        margin-bottom: 1.5rem;
        line-height: 1.5;
    }
    .upsell-modal-product {
        background: #f9f9f9;
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        gap: 15px;
        align-items: center;
        text-align: left;
        margin-bottom: 1.5rem;
    }
    .upsell-modal-product-img {
        width: 70px;
        height: 70px;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
    }
    .upsell-modal-product-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .upsell-modal-product-name {
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 4px;
    }
    .upsell-modal-product-price {
        font-weight: 800;
        color: #C7A333;
        font-size: 1.1rem;
    }
    .btn-upsell-confirm {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, #C7A333, #f0d76a);
        color: #1a1a1a;
        border: none;
        border-radius: 12px;
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        transition: 0.2s;
        margin-bottom: 0.75rem;
    }
    .btn-upsell-confirm:hover {
        transform: translateY(-2px);
    }
    .btn-upsell-cancel {
        width: 100%;
        padding: 0.75rem;
        background: transparent;
        color: #666;
        border: 1px solid #ddd;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-upsell-cancel:hover {
        background: #f5f5f5;
    }
    
    .upsell-loading {
        display: none;
        align-items: center;
        justify-content: center;
        gap: 10px;
        color: #666;
        padding: 1rem;
    }
    .upsell-loading.show {
        display: flex;
    }
    .upsell-loading i {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<div class="thank-you-container">
    <div class="container">
        <h1 class="thank-you-title">PEDIDO CONFIRMADO</h1>
        
        <div class="thank-you-box">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h2 class="thank-you-message">
                Obrigado por comprar com a <span class="store-name"><?php echo htmlspecialchars($appName); ?></span>!
            </h2>
            
            <?php if ($payment_pending || ($order && $order['status'] === 'pending')): ?>
            <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.1)); border: 2px solid #f59e0b; border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; text-align: left;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-clock" style="color: #f59e0b; font-size: 1.5rem; margin-top: 2px;"></i>
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: #d97706; font-weight: 700;">Pagamento Pendente</h4>
                        <p style="margin: 0; color: #92400e; font-size: 0.95rem; line-height: 1.5;">
                            Seu pedido foi registrado, mas o pagamento ainda nao foi confirmado. 
                            Entre em contato com a loja pelo WhatsApp para combinar a forma de pagamento.
                        </p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <p class="thank-you-subtext">
                Seu pedido foi recebido com sucesso e esta sendo processado. 
                Voce recebera atualizacoes sobre o status da entrega.
            </p>
            <?php endif; ?>
            
            <?php if ($order): ?>
            <div class="order-info">
                <div class="order-info-title">DETALHES DO PEDIDO</div>
                <div class="order-info-row">
                    <span class="order-info-label">Numero do Pedido:</span>
                    <span class="order-info-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-info-label">Total:</span>
                    <span class="order-info-value"><?php echo formatPrice($order['total_amount']); ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-info-label">Status:</span>
                    <span class="order-info-value" style="color: #22c55e;">
                        <?php 
                        $statusLabels = [
                            'pending' => 'Pendente',
                            'paid' => 'Pago',
                            'processing' => 'Processando',
                            'shipped' => 'Enviado',
                            'delivered' => 'Entregue',
                            'cancelled' => 'Cancelado'
                        ];
                        echo $statusLabels[$order['status']] ?? ucfirst($order['status']);
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <p class="thank-you-subtext" style="margin-bottom: 1.5rem;">
                Caso tenha duvidas sobre a entrega, entre em contato clicando no botao abaixo:
            </p>
            
            <?php if (!empty($whatsappNumberClean)): ?>
            <a href="<?php echo htmlspecialchars($whatsappLink); ?>" target="_blank" class="btn-whatsapp">
                <i class="fab fa-whatsapp"></i>
                FALAR COM A LOJA
            </a>
            <br>
            <?php endif; ?>
            
            <a href="<?php echo APP_URL; ?>/" class="btn-continue-shopping">
                CONTINUAR COMPRANDO
            </a>
        </div>

        <!-- SECAO DE COMPRA RAPIDA (UPSELL) -->
        <?php if ($order && !empty($upsell_products)): ?>
        <div class="upsell-section" id="upsellSection">
            <div class="upsell-badge">
                <i class="fas fa-bolt"></i>
                OFERTA EXCLUSIVA
            </div>
            
            <h2 class="upsell-title">Adicione mais um produto ao seu pedido!</h2>
            <p class="upsell-subtitle">
                Aproveite para incluir outro item <span class="upsell-highlight">usando o mesmo endereco de entrega</span>.
                E rapido, simples e voce economiza no frete!
            </p>
            
            <div class="upsell-products">
                <?php foreach (array_slice($upsell_products, 0, 3) as $product): ?>
                <div class="upsell-card" 
                     data-product-id="<?php echo $product['id']; ?>"
                     data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                     data-product-price="<?php echo $product['price']; ?>"
                     data-product-image="<?php echo htmlspecialchars($product['image_url']); ?>"
                     onclick="selectUpsellProduct(this)">
                    <div class="upsell-card-img">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="upsell-card-brand"><?php echo htmlspecialchars($product['brand_name'] ?? 'Premium'); ?></div>
                    <div class="upsell-card-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="upsell-card-price"><?php echo formatPrice($product['price']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="upsell-total" id="upsellTotal" style="display: none;">
                <span class="upsell-total-label">Valor adicional:</span>
                <span class="upsell-total-value" id="upsellTotalValue">R$ 0,00</span>
            </div>
            
            <button type="button" class="btn-upsell-buy" id="btnUpsellBuy" onclick="openUpsellModal()" disabled>
                <i class="fas fa-shopping-bag"></i>
                ADICIONAR AO PEDIDO
            </button>
            
            <span class="upsell-skip" onclick="hideUpsellSection()">Nao, obrigado. Continuar sem adicionar</span>
        </div>
        
        <!-- Modal de Confirmacao Upsell -->
        <div class="upsell-modal" id="upsellModal">
            <div class="upsell-modal-content">
                <button class="upsell-modal-close" onclick="closeUpsellModal()">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="upsell-modal-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                
                <h3 class="upsell-modal-title">Confirmar Adicao?</h3>
                <p class="upsell-modal-text">
                    Este produto sera adicionado ao seu pedido atual e enviado para o mesmo endereco.
                </p>
                
                <div class="upsell-modal-product" id="upsellModalProduct">
                    <div class="upsell-modal-product-img">
                        <img id="upsellModalImg" src="" alt="">
                    </div>
                    <div>
                        <div class="upsell-modal-product-name" id="upsellModalName"></div>
                        <div class="upsell-modal-product-price" id="upsellModalPrice"></div>
                    </div>
                </div>
                
                <div class="upsell-loading" id="upsellLoading">
                    <i class="fas fa-spinner"></i>
                    Processando seu pedido...
                </div>
                
                <button type="button" class="btn-upsell-confirm" id="btnUpsellConfirm" onclick="confirmUpsellPurchase()">
                    <i class="fas fa-check"></i> CONFIRMAR COMPRA
                </button>
                <button type="button" class="btn-upsell-cancel" onclick="closeUpsellModal()">
                    Cancelar
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- CARROSSEL DE PRODUTOS RECOMENDADOS -->
        <?php if (!empty($recommended_products)): ?>
        <div class="recommended-section">
            <h2 class="recommended-title">TEMOS TAMBEM DISPONIVEL</h2>
            
            <div class="recommended-carousel-container">
                <button class="carousel-nav-btn nav-prev" onclick="scrollRecommended(-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="carousel-nav-btn nav-next" onclick="scrollRecommended(1)"><i class="fas fa-chevron-right"></i></button>
                
                <div class="recommended-track" id="recommendedTrack">
                    <?php foreach ($recommended_products as $product): ?>
                    <div class="recommended-card">
                        <a href="<?php echo APP_URL; ?>/product.php?id=<?php echo $product['id']; ?>" class="recommended-img-wrapper">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="recommended-info">
                            <div class="recommended-brand"><?php echo htmlspecialchars($product['brand_name'] ?? 'Premium'); ?></div>
                            <div class="recommended-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="recommended-price"><?php echo formatPrice($product['price']); ?></div>
                        </div>
                        <a href="<?php echo APP_URL; ?>/carrinho.php?add=<?php echo $product['id']; ?>" class="btn-add-recommended">ADICIONAR</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Carrossel de produtos recomendados
function scrollRecommended(direction) {
    const track = document.getElementById('recommendedTrack');
    const scrollAmount = 260; // largura do card + gap
    track.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

// Touch/swipe support para mobile
let touchStartX = 0;
let touchEndX = 0;

const track = document.getElementById('recommendedTrack');
if (track) {
    track.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    track.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);
}

function handleSwipe() {
    const swipeThreshold = 50;
    if (touchEndX < touchStartX - swipeThreshold) {
        scrollRecommended(1);
    }
    if (touchEndX > touchStartX + swipeThreshold) {
        scrollRecommended(-1);
    }
}

// ===== FUNCIONALIDADE DE COMPRA RAPIDA (UPSELL) =====
let selectedUpsellProduct = null;
const orderId = <?php echo $order ? (int)$order['id'] : 'null'; ?>;

function selectUpsellProduct(card) {
    // Remove selecao anterior
    document.querySelectorAll('.upsell-card').forEach(c => c.classList.remove('selected'));
    
    // Seleciona o novo card
    card.classList.add('selected');
    
    // Armazena dados do produto
    selectedUpsellProduct = {
        id: card.dataset.productId,
        name: card.dataset.productName,
        price: parseFloat(card.dataset.productPrice),
        image: card.dataset.productImage
    };
    
    // Atualiza total
    const totalEl = document.getElementById('upsellTotal');
    const totalValueEl = document.getElementById('upsellTotalValue');
    if (totalEl && totalValueEl) {
        totalEl.style.display = 'flex';
        totalValueEl.textContent = formatPrice(selectedUpsellProduct.price);
    }
    
    // Habilita botao
    const btn = document.getElementById('btnUpsellBuy');
    if (btn) btn.disabled = false;
}

function formatPrice(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',');
}

function openUpsellModal() {
    if (!selectedUpsellProduct) {
        alert('Selecione um produto primeiro');
        return;
    }
    
    // Preenche modal com dados do produto
    document.getElementById('upsellModalImg').src = selectedUpsellProduct.image;
    document.getElementById('upsellModalName').textContent = selectedUpsellProduct.name;
    document.getElementById('upsellModalPrice').textContent = formatPrice(selectedUpsellProduct.price);
    
    // Mostra modal
    document.getElementById('upsellModal').classList.add('show');
}

function closeUpsellModal() {
    document.getElementById('upsellModal').classList.remove('show');
    document.getElementById('upsellLoading').classList.remove('show');
    document.getElementById('btnUpsellConfirm').style.display = 'block';
}

function hideUpsellSection() {
    const section = document.getElementById('upsellSection');
    if (section) {
        section.style.transition = 'opacity 0.3s, transform 0.3s';
        section.style.opacity = '0';
        section.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            section.style.display = 'none';
        }, 300);
    }
}

async function confirmUpsellPurchase() {
    if (!selectedUpsellProduct || !orderId) {
        alert('Erro: Dados incompletos');
        return;
    }
    
    const loading = document.getElementById('upsellLoading');
    const btnConfirm = document.getElementById('btnUpsellConfirm');
    
    // Mostra loading
    loading.classList.add('show');
    btnConfirm.style.display = 'none';
    
    try {
        const response = await fetch('<?php echo APP_URL; ?>/api/upsell-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                product_id: selectedUpsellProduct.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Sucesso - mostra mensagem e recarrega
            document.querySelector('.upsell-modal-content').innerHTML = `
                <div class="upsell-modal-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="upsell-modal-title">Produto Adicionado!</h3>
                <p class="upsell-modal-text">
                    ${selectedUpsellProduct.name} foi adicionado ao seu pedido com sucesso!
                </p>
                <button onclick="location.reload()" class="btn-upsell-confirm" style="background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff;">
                    <i class="fas fa-check"></i> ENTENDIDO
                </button>
            `;
        } else {
            throw new Error(data.message || 'Erro ao processar');
        }
    } catch (err) {
        loading.classList.remove('show');
        btnConfirm.style.display = 'block';
        alert('Erro ao adicionar produto: ' + (err.message || 'Tente novamente'));
    }
}
</script>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
