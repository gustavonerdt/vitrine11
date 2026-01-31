<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Entrega';
$bodyClass = 'checkout-page';

// Verificar se há itens no carrinho
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: ' . APP_URL . '/carrinho.php');
    exit;
}

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
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'quantity' => $quantity,
                'image_url' => $product['image_path']
            ];
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    
    if (!empty($email) && !empty($cep) && !empty($shipping_method)) {
        $_SESSION['checkout_data'] = $_POST;
        header('Location: ' . APP_URL . '/checkout-pagamento.php');
        exit;
    }
    $error = "Por favor, preencha todos os campos e selecione o frete.";
}

$checkout_data = $_SESSION['checkout_data'] ?? [];
include __DIR__ . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/checkout.css">
<div class="checkout-page-container">
    <div class="container">
        <div class="checkout-progress">
            <div class="progress-step completed"><div class="step-icon"><i class="fas fa-check"></i></div><div class="step-label">Carrinho</div></div>
            <div class="progress-line"></div>
            <div class="progress-step active"><div class="step-icon">2</div><div class="step-label">Entrega</div></div>
            <div class="progress-line"></div>
            <div class="progress-step"><div class="step-icon">3</div><div class="step-label">Pagamento</div></div>
        </div>
       
        <div class="checkout-content">
            <div class="checkout-form-section">
                <form method="POST" id="checkoutForm" class="checkout-form">
                    <?php if (isset($error)): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
                   
                    <div class="form-section">
                        <h2 class="section-title">DADOS DE CONTATO</h2>
                        <div class="form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($checkout_data['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                   
                    <div class="form-section">
                        <h2 class="section-title">ENTREGA</h2>
                        <div class="form-group">
                            <label for="cep">CEP *</label>
                            <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($checkout_data['cep'] ?? ''); ?>" placeholder="00000-000" maxlength="9" required>
                            <div id="cepLoading" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>
                        </div>
                       
                        <div id="shippingOptions" style="display: none;">
                            <h3 class="shipping-options-title">Opções de Entrega</h3>
                            <div id="shippingOptionsList"></div>
                        </div>
                       
                        <div id="addressForm" style="display: none;">
                            <div class="form-group">
                                <label for="street">Rua/Logradouro *</label>
                                <input type="text" id="street" name="street" value="<?php echo htmlspecialchars($checkout_data['street'] ?? ''); ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="number">Número *</label><input type="text" id="number" name="number" value="<?php echo htmlspecialchars($checkout_data['number'] ?? ''); ?>" required></div>
                                <div class="form-group"><label for="neighborhood">Bairro *</label><input type="text" id="neighborhood" name="neighborhood" value="<?php echo htmlspecialchars($checkout_data['neighborhood'] ?? ''); ?>" required></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="city">Cidade *</label><input type="text" id="city" name="city" value="<?php echo htmlspecialchars($checkout_data['city'] ?? ''); ?>" required></div>
                                <div class="form-group"><label for="state">Estado *</label><input type="text" id="state" name="state" value="<?php echo htmlspecialchars($checkout_data['state'] ?? ''); ?>" required></div>
                            </div>
                        </div>
                    </div>
                   
                    <div class="form-section" id="deliveryDataSection" style="display: none;">
                        <h2 class="section-title">DADOS PARA ENTREGA</h2>
                        <div class="form-row">
                            <div class="form-group"><label for="recipient_name">Nome *</label><input type="text" id="recipient_name" name="recipient_name" value="<?php echo htmlspecialchars($checkout_data['recipient_name'] ?? ''); ?>" required></div>
                            <div class="form-group"><label for="phone">Telefone *</label><input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($checkout_data['phone'] ?? ''); ?>" required></div>
                        </div>
                    </div>

                    <div class="form-section" id="invoiceDataSection" style="display: none;">
                        <h2 class="section-title">DADOS PARA NOTA FISCAL</h2>
                        <div class="form-group">
                            <label for="cpf_cnpj">CPF ou CNPJ *</label>
                            <input type="text" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo htmlspecialchars($checkout_data['cpf_cnpj'] ?? ''); ?>" required>
                        </div>
                    </div>
                   
                    <input type="hidden" id="shipping_method" name="shipping_method" value="">
                    <input type="hidden" id="shipping_price" name="shipping_price" value="0">
                   
                    <button type="submit" class="btn-continue-payment" id="btnContinue" style="background: #C7A333; color: #000; border: none; padding: 1rem; border-radius: 8px; font-weight: 700; width: 100%; cursor: pointer;">
                        CONTINUAR PARA PAGAMENTO
                    </button>
                </form>
            </div>
           
            <div class="checkout-summary-new">
                <h3 class="summary-title-new">RESUMO DO PEDIDO</h3>
                <div class="summary-products-new">
                    <?php foreach ($cart_items as $item): 
                        $img_path = $item['image_url'] ?? '';
                        $img_url = '';
                        if (!empty($img_path)) {
                            if (strpos($img_path, 'http') === 0) {
                                $img_url = $img_path;
                            } else {
                                $img_url = APP_URL . '/' . ltrim($img_path, '/');
                            }
                        }
                    ?>
                        <div class="summary-product-row">
                            <div class="summary-product-img">
                                <?php if (!empty($img_url)): ?>
                                    <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div class="img-placeholder" style="display:none;"><i class="fas fa-box"></i></div>
                                <?php else: ?>
                                    <div class="img-placeholder"><i class="fas fa-box"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-product-details">
                                <span class="product-name-text"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="product-price-text">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?> x <?php echo $item['quantity']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-totals-new">
                    <div class="total-row"><span>Subtotal</span><span>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span></div>
                    <div class="total-row" id="shippingRow" style="display: none;"><span>Frete</span><span id="shippingCost">R$ 0,00</span></div>
                    <div class="total-row total-final"><span>Total</span><span id="totalAmount" class="total-value">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.APP_URL = '<?php echo APP_URL; ?>';
    window.cartSubtotal = <?php echo $subtotal; ?>;
</script>
<script src="<?php echo APP_URL; ?>/assets/js/checkout-entrega.js"></script>
<?php include __DIR__ . '/includes/public-footer.php'; ?>
