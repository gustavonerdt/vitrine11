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

// Buscar configuracoes da loja
$appName = getSetting($pdo, 'app_name', APP_NAME);
$whatsappNumber = getSetting($pdo, 'whatsapp_float_number', '');
$whatsappMessage = getSetting($pdo, 'whatsapp_float_message', 'Olá! Tenho uma dúvida sobre meu pedido.');

// Limpar numero de WhatsApp
$whatsappNumberClean = preg_replace('/[^0-9]/', '', $whatsappNumber);
$whatsappLink = 'https://wa.me/' . $whatsappNumberClean . '?text=' . urlencode($whatsappMessage);

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
            
            <p class="thank-you-subtext">
                Seu pedido foi recebido com sucesso e esta sendo processado. 
                Voce recebera atualizacoes sobre o status da entrega.
            </p>
            
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
</script>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
