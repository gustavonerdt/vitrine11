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
                    <div class="form-section address-section">
                        <h2 class="section-title">ENDERECO DE ENTREGA</h2>
                        <div class="address-card">
                            <div class="address-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="address-details">
                                <p class="address-street"><?php echo htmlspecialchars($checkout_data['street'] ?? ''); ?>, <?php echo htmlspecialchars($checkout_data['number'] ?? ''); ?><?php if (!empty($checkout_data['complement'] ?? '')): ?> - <?php echo htmlspecialchars($checkout_data['complement']); ?><?php endif; ?></p>
                                <p class="address-neighborhood"><?php echo htmlspecialchars($checkout_data['neighborhood'] ?? ''); ?></p>
                                <p class="address-city"><?php echo htmlspecialchars($checkout_data['city'] ?? ''); ?> - <?php echo htmlspecialchars($checkout_data['state'] ?? ''); ?></p>
                                <p class="address-cep">CEP: <?php echo htmlspecialchars($checkout_data['cep'] ?? ''); ?></p>
                            </div>
                            <a href="<?php echo APP_URL; ?>/checkout-entrega.php" class="btn-change-address">
                                Alterar
                            </a>
                        </div>
                    </div>
                    
                    <!-- Metodo de Entrega -->
                    <div class="form-section shipping-section">
                        <h2 class="section-title">FORMA DE ENTREGA</h2>
                        <div class="shipping-card">
                            <div class="shipping-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="shipping-details">
                                <p class="shipping-method-name"><?php echo htmlspecialchars($checkout_data['shipping_method'] ?? 'Frete Padrao'); ?></p>
                                <p class="shipping-estimate-text">
                                    <?php
                                    $estimated_date = date('d/m/Y', strtotime('+5 weekdays'));
                                    echo "Previsao: " . $estimated_date;
                                    ?>
                                </p>
                            </div>
                            <div class="shipping-price-tag"><?php echo formatPrice($shipping_cost); ?></div>
                        </div>
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
                    <div class="form-section payment-section">
                        <h2 class="section-title">Forma de pagamento</h2>
                        
                        <div class="payment-methods-new">
                            <div class="payment-option selected" data-method="credit_card">
                                <div class="payment-option-icon">
                                    <i class="far fa-credit-card"></i>
                                </div>
                                <span class="payment-option-label">Cartao de credito</span>
                            </div>
                            <div class="payment-option" data-method="pix">
                                <div class="payment-option-icon">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <span class="payment-option-label">Pix</span>
                            </div>
                        </div>
                        
                        <!-- Credit Card Payment -->
                        <div id="creditCardPayment" class="payment-content-new active">
                            <?php if (!empty($mp_public_key)): ?>
                                <div id="paymentBrick_container"></div>
                                <div id="payment-status"></div>
                            <?php else: ?>
                                <div class="alert-payment-error">
                                    <i class="fas fa-exclamation-circle"></i> Mercado Pago nao configurado.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pix Payment -->
                        <div id="pixPayment" class="payment-content-new">
                            <div class="pix-info-box">
                                <p class="pix-description">Ao gerar o Codigo Pix voce pode pagar escaneando o QR Code ou copiando o codigo.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Salvar Dados -->
                    <div class="form-section save-data-section">
                        <label class="custom-checkbox">
                            <input type="checkbox" id="save_data" name="save_data" checked>
                            <span class="checkmark"></span>
                            <span class="checkbox-label">Salvar dados para compras futuras</span>
                        </label>
                        <p class="terms-text-new">
                            Ao continuar, voce concorda com nossos <a href="#">Termos de Uso</a> e <a href="#">Politica de Privacidade</a>
                        </p>
                    </div>
                    
                    <input type="hidden" id="payment_method" name="payment_method" value="credit_card">
                    <input type="hidden" id="mp_token" name="mp_token" value="">
                    
                    <button type="submit" class="btn-finalizar-pedido" id="btnMakeOrder">
                        FINALIZAR PEDIDO
                    </button>
                    

                </form>
            </div>
            
            <!-- Resumo do Pedido -->
            <div class="checkout-summary-side">
                <h3 class="summary-title">RESUMO DO PEDIDO</h3>
                <div class="summary-products">
                    <?php foreach ($cart_items as $item): 
                        $img_path = $item['image_path'] ?? '';
                        $img_url = '';
                        if (!empty($img_path)) {
                            if (strpos($img_path, 'http') === 0) {
                                $img_url = $img_path;
                            } else {
                                $img_url = APP_URL . '/' . ltrim($img_path, '/');
                            }
                        }
                    ?>
                        <div class="summary-product-item">
                            <div class="summary-product-image">
                                <?php if (!empty($img_url)): ?>
                                    <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="summary-placeholder"><i class="fas fa-box"></i></div>
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
                        <span>Frete</span>
                        <span><?php echo formatPrice($shipping_cost); ?></span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                <a href="#" class="link-add-coupon">
                    <i class="fas fa-tag"></i> Adicionar cupom
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
