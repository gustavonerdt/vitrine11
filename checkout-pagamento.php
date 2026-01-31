<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Pagamento';
$bodyClass = 'checkout-page';

// Verificar se há dados de checkout
if (!isset($_SESSION['checkout_data']) || empty($_SESSION['checkout_data'])) {
    header('Location: ' . APP_URL . '/checkout-entrega.php');
    exit;
}

// Verificar se há itens no carrinho
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: ' . APP_URL . '/carrinho.php');
    exit;
}

$checkout_data = $_SESSION['checkout_data'];

// Buscar itens do carrinho
$cart_items = [];
$subtotal = 0;

foreach ($_SESSION['cart'] as $product_id => $quantity) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image_path, b.name as brand_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ? AND p.is_active = 1
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $item_total = floatval($product['price']) * $quantity;
            $subtotal += $item_total;
            
            $cart_items[] = [
                'product_id' => (int)$product_id,
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'quantity' => $quantity,
                'item_total' => $item_total,
                'image_path' => $product['image_path'] ?? null
            ];
        }
    } catch (PDOException $e) {
        error_log("Cart item fetch error: " . $e->getMessage());
    }
}

$shipping_cost = floatval($checkout_data['shipping_price'] ?? 0);
$total = $subtotal + $shipping_cost;

// Configurações do Mercado Pago
$mp_environment = getSetting($pdo, 'mercado_pago_environment', 'test');
$mp_access_token = $mp_environment === 'production' 
    ? getSetting($pdo, 'mercado_pago_access_token', '')
    : getSetting($pdo, 'mercado_pago_access_token_test', '');
$mp_public_key = $mp_environment === 'production'
    ? getSetting($pdo, 'mercado_pago_public_key', '')
    : getSetting($pdo, 'mercado_pago_public_key_test', '');

include __DIR__ . '/includes/public-header.php';
?>

<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/checkout.css">
<script src="https://sdk.mercadopago.com/js/v2"></script>

<div class="checkout-page-container">
    <div class="container">
        <!-- Progress Bar -->
        <div class="checkout-progress">
            <div class="progress-step completed">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <div class="step-label">Carrinho</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step completed">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <div class="step-label">Entrega</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step active">
                <div class="step-icon">3</div>
                <div class="step-label">Pagamento</div>
            </div>
        </div>
        
        <div class="checkout-content">
            <div class="checkout-form-section">
                <form method="POST" id="paymentForm" class="checkout-form">
                    <!-- Dados de Contato -->
                    <div class="form-section">
                        <div class="form-group">
                            <label>E-mail</label>
                            <input type="email" value="<?php echo htmlspecialchars($checkout_data['email']); ?>" disabled>
                        </div>
                    </div>
                    
                    <!-- Endereco de Entrega -->
                    <div class="form-section">
                        <h2 class="section-title">ENDERECO DE ENTREGA</h2>
                        <p class="address-text">
                            <strong><?php echo htmlspecialchars($checkout_data['street'] ?? ''); ?>, <?php echo htmlspecialchars($checkout_data['number'] ?? ''); ?></strong>
                            <?php if (!empty($checkout_data['complement'] ?? '')): ?>
                                - <?php echo htmlspecialchars($checkout_data['complement']); ?>
                            <?php endif; ?>
                            <br>
                            <?php echo htmlspecialchars($checkout_data['neighborhood'] ?? ''); ?><br>
                            <?php echo htmlspecialchars($checkout_data['city'] ?? ''); ?> - <?php echo htmlspecialchars($checkout_data['state'] ?? ''); ?><br>
                            <strong>CEP:</strong> <?php echo htmlspecialchars($checkout_data['cep'] ?? ''); ?>
                        </p>
                        <a href="<?php echo APP_URL; ?>/checkout-entrega.php" class="link-change">
                            <i class="fas fa-edit"></i> Alterar Endereco
                        </a>
                    </div>
                    
                    <!-- Metodo de Entrega -->
                    <div class="form-section">
                        <h2 class="section-title">FORMA DE ENTREGA</h2>
                        <div class="shipping-selected">
                            <strong><i class="fas fa-truck"></i> <?php echo htmlspecialchars($checkout_data['shipping_method']); ?></strong>
                            <span><?php echo formatPrice($shipping_cost); ?></span>
                        </div>
                        <p class="shipping-estimate">
                            <i class="fas fa-calendar-alt"></i>
                            <?php
                            // Calcular data estimada (aproximadamente 5-7 dias uteis)
                            $estimated_date = date('d/m/Y', strtotime('+5 weekdays'));
                            echo "Previsao de entrega: " . $estimated_date;
                            ?>
                        </p>
                        <a href="<?php echo APP_URL; ?>/checkout-entrega.php" class="link-change">
                            <i class="fas fa-edit"></i> Alterar Frete
                        </a>
                    </div>
                    
                    <!-- Observacoes do Pedido (Opcional) -->
                    <div class="form-section">
                        <h2 class="section-title">OBSERVACOES (OPCIONAL)</h2>
                        <div class="form-group">
                            <label for="seller_name">Alguma observacao para o seu pedido?</label>
                            <textarea id="seller_name" name="seller_name" rows="2" 
                                      placeholder="Ex: Entregar no portao, presente para amigo, etc."></textarea>
                        </div>
                    </div>
                    
                    <!-- Forma de Pagamento -->
                    <div class="form-section">
                        <h2 class="section-title">FORMA DE PAGAMENTO</h2>
                        
                        <div class="payment-methods">
                            <div class="payment-method-tabs">
                                <button type="button" class="payment-tab active" data-method="pix">
                                    <i class="fas fa-qrcode"></i> Pix
                                </button>
                                <button type="button" class="payment-tab" data-method="credit_card">
                                    <i class="fas fa-credit-card"></i> Cartão de Crédito
                                </button>
                            </div>
                            
                            <!-- Pix Payment -->
                            <div id="pixPayment" class="payment-content active">
                                <p class="payment-info">
                                    Ao gerar o Código Pix do pedido você pode pagar escaneando o QR Code ou Copiar e Colar.
                                </p>
                                <div class="pix-recipient">
                                    <div class="pix-info-row">
                                        <span>Nome:</span>
                                        <span>Gustavo Felix</span>
                                    </div>
                                    <div class="pix-info-row">
                                        <span>CPF/CNPJ:</span>
                                        <span>363.923.068-03</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Credit Card Payment -->
                            <div id="creditCardPayment" class="payment-content">
                                <?php if (!empty($mp_public_key)): ?>
                                    <div id="paymentBrick_container" style="max-width: 100%; margin: 20px 0; min-height: 400px;"></div>
                                    <div id="payment-status" style="margin-top: 20px; text-align: center; font-weight: bold; display: none; padding: 1rem; border-radius: 8px; background: var(--admin-bg-secondary);"></div>
                                <?php else: ?>
                                    <div class="alert alert-error" style="padding: 1rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;">
                                        <i class="fas fa-exclamation-circle"></i> Mercado Pago não configurado. Configure as chaves no painel administrativo.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Salvar Dados -->
                    <div class="form-section">
                        <div class="form-checkbox">
                            <input type="checkbox" id="save_data" name="save_data" checked>
                            <label for="save_data">Salvar dados para comprar mais rapido</label>
                        </div>
                        <p class="save-data-info">
                            Nas proximas compras enviaremos um codigo para: <strong><?php echo htmlspecialchars($checkout_data['phone'] ?? ''); ?></strong>
                        </p>
                        <p class="terms-text">
                            Ao salvar, voce aceita os <a href="#">Termos de uso</a> e <a href="#">Politica de Privacidade</a>
                        </p>
                    </div>
                    
                    <input type="hidden" id="payment_method" name="payment_method" value="pix">
                    <input type="hidden" id="mp_token" name="mp_token" value="">
                    
                    <!-- Botao para Pix (Payment Brick tem seu proprio botao) -->
                    <button type="submit" class="btn-make-order" id="btnMakeOrder">
                        FINALIZAR PEDIDO
                    </button>
                    

                </form>
            </div>
            
            <!-- Resumo do Pedido -->
            <div class="checkout-summary">
                <h3 class="summary-title">RESUMO DO PEDIDO</h3>
                <div class="summary-products">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-product-item">
                            <div class="summary-product-image">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="<?php echo APP_URL . '/' . ltrim($item['image_path'], '/'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="summary-placeholder"><i class="fas fa-spray-can"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-product-info">
                                <div class="summary-product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="summary-product-price"><?php echo formatPrice($item['price']); ?> x <?php echo $item['quantity']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-totals">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Custo de frete</span>
                        <span><?php echo formatPrice($shipping_cost); ?></span>
                    </div>
                    <div class="summary-row" style="color: #1a1a1a !important; font-weight: 700 !important;">
                        <span>- Descontos</span>
                        <span>- <?php echo formatPrice(0); ?></span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                <a href="#" class="link-add-coupon">
                    <i class="fas fa-tag"></i> Adicionar cupom de desconto
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    window.APP_URL = '<?php echo APP_URL; ?>';
    window.MP_PUBLIC_KEY = '<?php echo htmlspecialchars($mp_public_key); ?>';
    window.orderTotal = <?php echo $total; ?>;
    window.checkoutEmail = '<?php echo htmlspecialchars($checkout_data['email'] ?? ''); ?>';
    window.checkoutCpf = '<?php echo preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? ''); ?>';
</script>
<script src="<?php echo APP_URL; ?>/assets/js/checkout-pagamento.js"></script>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
