<?php
// session_start() ja e chamado em config.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Pedido Confirmado';
$bodyClass = 'order-confirmation-page';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_SESSION['last_order_id']) ? (int)$_SESSION['last_order_id'] : 0);

if (!$order_id) {
    header('Location: ' . APP_URL . '/');
    exit;
}

// Buscar pedido
$order = null;
try {
    $stmt = $pdo->prepare("
        SELECT o.*, sa.*
        FROM orders o
        LEFT JOIN shipping_addresses sa ON o.id = sa.order_id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Order fetch error: " . $e->getMessage());
}

if (!$order) {
    header('Location: ' . APP_URL . '/');
    exit;
}

// Buscar itens do pedido
$order_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image_path
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Order items fetch error: " . $e->getMessage());
}

// Buscar dados do pagamento do Mercado Pago se existir
$payment_data = null;
if (!empty($order['mercado_pago_payment_id'])) {
    $mp_environment = getSetting($pdo, 'mercado_pago_environment', 'test');
    $mp_access_token = $mp_environment === 'production' 
        ? getSetting($pdo, 'mercado_pago_access_token', '')
        : getSetting($pdo, 'mercado_pago_access_token_test', '');
    
    if (!empty($mp_access_token)) {
        $ch = curl_init("https://api.mercadopago.com/v1/payments/{$order['mercado_pago_payment_id']}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $mp_access_token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $payment_data = json_decode($response, true);
        }
    }
}

include __DIR__ . '/includes/public-header.php';
?>

<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/checkout.css">

<div class="order-confirmation-container">
    <div class="container">
        <div class="confirmation-header">
            <div class="confirmation-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="confirmation-title">PEDIDO CONFIRMADO!</h1>
            <p class="confirmation-subtitle">Número do pedido: #<?php echo $order_id; ?></p>
        </div>
        
        <div class="confirmation-content">
            <div class="confirmation-main">
                <?php if ($order['payment_method'] === 'pix' && $payment_data): ?>
                    <!-- Pix Payment Instructions -->
                    <div class="payment-instructions">
                        <h2 class="instructions-title">PAGUE COM PIX</h2>
                        <p class="instructions-text">
                            Escaneie o QR Code ou copie o código Pix para pagar
                        </p>
                        
                        <?php if (isset($payment_data['point_of_interaction']['transaction_data']['qr_code'])): ?>
                            <div class="pix-qr-code">
                                <img src="data:image/png;base64,<?php echo $payment_data['point_of_interaction']['transaction_data']['qr_code_base64'] ?? ''; ?>" 
                                     alt="QR Code Pix">
                            </div>
                            
                            <div class="pix-code-section">
                                <label class="pix-code-label">Código Pix (Copiar e Colar):</label>
                                <div class="pix-code-input-group">
                                    <input type="text" id="pixCode" 
                                           value="<?php echo htmlspecialchars($payment_data['point_of_interaction']['transaction_data']['qr_code'] ?? ''); ?>" 
                                           readonly>
                                    <button class="btn-copy-pix" onclick="copyPixCode()">
                                        <i class="fas fa-copy"></i> Copiar
                                    </button>
                                </div>
                            </div>
                            
                            <div class="pix-amount">
                                <strong>Valor: <?php echo formatPrice($order['total_amount']); ?></strong>
                            </div>
                            
                            <div class="pix-expiry">
                                <p>Este código Pix expira em 30 minutos</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Aguardando geração do código Pix. Atualize a página em alguns instantes.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($order['payment_method'] === 'credit_card'): ?>
                    <!-- Credit Card Payment Status -->
                    <div class="payment-status">
                        <h2 class="instructions-title">STATUS DO PAGAMENTO</h2>
                        <?php if ($order['status'] === 'paid'): ?>
                            <div class="status-badge success">
                                <i class="fas fa-check-circle"></i> Pagamento Aprovado
                            </div>
                        <?php elseif ($order['status'] === 'pending'): ?>
                            <div class="status-badge pending">
                                <i class="fas fa-clock"></i> Aguardando Pagamento
                            </div>
                        <?php else: ?>
                            <div class="status-badge error">
                                <i class="fas fa-times-circle"></i> Pagamento Recusado
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Order Details -->
                <div class="order-details">
                    <h2 class="section-title">DETALHES DO PEDIDO</h2>
                    
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Número do Pedido:</span>
                            <span class="detail-value">#<?php echo $order_id; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Data:</span>
                            <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <?php
                                $status_labels = [
                                    'pending' => 'Pendente',
                                    'paid' => 'Pago',
                                    'processing' => 'Processando',
                                    'shipped' => 'Enviado',
                                    'delivered' => 'Entregue',
                                    'cancelled' => 'Cancelado'
                                ];
                                echo $status_labels[$order['status']] ?? 'Desconhecido';
                                ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total:</span>
                            <span class="detail-value"><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Address -->
                <div class="shipping-info">
                    <h2 class="section-title">ENDEREÇO DE ENTREGA</h2>
                    <div class="address-block">
                        <p><strong><?php echo htmlspecialchars($order['recipient_name']); ?></strong></p>
                        <p>
                            <?php 
                            $address_parts = array_filter([
                                $order['street'],
                                $order['number'] ? 'Nº ' . $order['number'] : '',
                                $order['complement'],
                                $order['neighborhood'],
                                $order['city'] . ' - ' . $order['state'],
                                'CEP ' . $order['cep']
                            ]);
                            echo htmlspecialchars(implode(', ', $address_parts));
                            ?>
                        </p>
                        <?php if (!empty($order['phone'])): ?>
                            <p>Telefone: <?php echo htmlspecialchars($order['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="order-items">
                    <h2 class="section-title">ITENS DO PEDIDO</h2>
                    <div class="items-list">
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="item-details">
                                    <span>Qtd: <?php echo $item['quantity']; ?></span>
                                    <span><?php echo formatPrice($item['price']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="confirmation-actions">
            <a href="<?php echo APP_URL; ?>/" class="btn-continue-shopping">CONTINUAR COMPRANDO</a>
        </div>
    </div>
</div>

<script>
function copyPixCode() {
    const pixCode = document.getElementById('pixCode');
    pixCode.select();
    document.execCommand('copy');
    
    const btn = event.target.closest('.btn-copy-pix');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
    btn.style.background = '#22c55e';
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = '';
    }, 2000);
}
</script>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
