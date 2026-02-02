<?php
// session_start() ja e chamado em config.php
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
            SELECT p.id, p.name, p.price, p.original_price, b.name as brand_name,
                   (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) as image_url
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ? AND p.is_active = 1
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        if ($product) {
            $item_total = floatval($product['price']) * $quantity;
            $subtotal += $item_total;
            
            // Processar URL da imagem
            $imageUrl = $product['image_url'] ?? '';
            if (!empty($imageUrl) && !preg_match('/^https?:\/\//i', $imageUrl)) {
                $imageUrl = rtrim(APP_URL, '/') . '/' . ltrim($imageUrl, '/');
            }
            
            $cart_items[] = [
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'original_price' => !empty($product['original_price']) ? floatval($product['original_price']) : null,
                'quantity' => $quantity,
                'image_url' => $imageUrl
            ];
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    
    if (!empty($email) && !empty($cep)) {
        $_SESSION['checkout_data'] = $_POST;
        // Frete será selecionado na página de pagamento
        $_SESSION['checkout_data']['shipping_method'] = '';
        $_SESSION['checkout_data']['shipping_price'] = 0;
        header('Location: ' . APP_URL . '/checkout-pagamento.php');
        exit;
    }
    $error = "Por favor, preencha todos os campos obrigatórios.";
}

$checkout_data = $_SESSION['checkout_data'] ?? [];
include __DIR__ . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/checkout.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/responsive.css">
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
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($checkout_data['email'] ?? ''); ?>" placeholder="seuemail@exemplo.com" required>
                        </div>
                    </div>
                   
                    <div class="form-section">
                        <h2 class="section-title">ENTREGA</h2>
                        <div class="form-group">
                            <label for="cep">CEP *</label>
                            <input type="tel" inputmode="numeric" pattern="[0-9\-]*" id="cep" name="cep" value="<?php echo htmlspecialchars($checkout_data['cep'] ?? ''); ?>" placeholder="Digite seu CEP (ex: 01310-100)" maxlength="9" required>
                            <div id="cepLoading" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>
                        </div>
                       
                        <!-- Opcoes de frete serao selecionadas na tela de pagamento -->
                       
                        <div id="addressForm" style="display: none;">
                            <div class="form-group">
                                <label for="street">Rua/Logradouro *</label>
                                <input type="text" id="street" name="street" value="<?php echo htmlspecialchars($checkout_data['street'] ?? ''); ?>" placeholder="Digite o nome da sua rua" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="number">Numero *</label><input type="tel" inputmode="numeric" id="number" name="number" value="<?php echo htmlspecialchars($checkout_data['number'] ?? ''); ?>" placeholder="Numero da casa/apto" required></div>
                                <div class="form-group"><label for="neighborhood">Bairro *</label><input type="text" id="neighborhood" name="neighborhood" value="<?php echo htmlspecialchars($checkout_data['neighborhood'] ?? ''); ?>" placeholder="Nome do bairro" required></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="city">Cidade *</label><input type="text" id="city" name="city" value="<?php echo htmlspecialchars($checkout_data['city'] ?? ''); ?>" placeholder="Nome da cidade" required></div>
                                <div class="form-group"><label for="state">Estado *</label><input type="text" id="state" name="state" value="<?php echo htmlspecialchars($checkout_data['state'] ?? ''); ?>" placeholder="Sigla (ex: SP)" required></div>
                            </div>
                            <div class="form-group">
                                <label for="complement">Complemento (opcional)</label>
                                <input type="text" id="complement" name="complement" value="<?php echo htmlspecialchars($checkout_data['complement'] ?? ''); ?>" placeholder="Apto, bloco, referencia...">
                            </div>
                        </div>
                    </div>
                   
                    <div class="form-section" id="deliveryDataSection" style="display: none;">
                        <h2 class="section-title">DADOS PARA ENTREGA</h2>
                        <div class="form-row">
                            <div class="form-group"><label for="recipient_name">Nome Completo *</label><input type="text" id="recipient_name" name="recipient_name" value="<?php echo htmlspecialchars($checkout_data['recipient_name'] ?? ''); ?>" placeholder="Digite seu nome completo" required></div>
                            <div class="form-group"><label for="phone">Telefone/WhatsApp *</label><input type="tel" inputmode="numeric" pattern="[0-9\(\)\-\s]*" id="phone" name="phone" value="<?php echo htmlspecialchars($checkout_data['phone'] ?? ''); ?>" placeholder="(00) 00000-0000" required></div>
                        </div>
                    </div>

                    <div class="form-section" id="invoiceDataSection" style="display: none;">
                        <h2 class="section-title">DADOS PARA NOTA FISCAL</h2>
                        <div class="form-group">
                            <label for="cpf_cnpj">CPF ou CNPJ *</label>
                            <input type="tel" inputmode="numeric" pattern="[0-9\.\-\/]*" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo htmlspecialchars($checkout_data['cpf_cnpj'] ?? ''); ?>" placeholder="Digite seu CPF ou CNPJ" required>
                        </div>
                    </div>
                   
                    <input type="hidden" id="shipping_method" name="shipping_method" value="">
                    <input type="hidden" id="shipping_price" name="shipping_price" value="0">
                   
                    <button type="submit" class="btn-continue-payment" id="btnContinue" style="background: #C7A333; color: #000; border: none; padding: 1rem; border-radius: 8px; font-weight: 700; width: 100%; cursor: pointer;">
                        CONTINUAR PARA PAGAMENTO
                    </button>
                </form>
            </div>
           
            <div class="checkout-summary">
                <h3 class="summary-title">RESUMO DO PEDIDO</h3>
                <div class="summary-products">
                    <?php 
                    $totalOriginal = 0;
                    $totalFinal = 0;
                    foreach ($cart_items as $item): 
                        $hasDiscount = !empty($item['original_price']) && $item['original_price'] > $item['price'];
                        $discountPercent = $hasDiscount ? round((($item['original_price'] - $item['price']) / $item['original_price']) * 100) : 0;
                        $totalOriginal += ($hasDiscount ? $item['original_price'] : $item['price']) * $item['quantity'];
                        $totalFinal += $item['price'] * $item['quantity'];
                    ?>
                        <div class="summary-product-item">
                            <div class="summary-product-image">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div class="summary-placeholder"><i class="fas fa-spray-can"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-product-info">
                                <div style="color: #1a1a1a; font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div style="margin-top: 4px;">
                                    <?php if ($hasDiscount): ?>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span style="text-decoration: line-through; color: #999; font-size: 0.75rem;">R$ <?php echo number_format($item['original_price'], 2, ',', '.'); ?></span>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span style="color: #1a1a1a; font-weight: 700; font-size: 0.95rem;">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></span>
                                            <span style="background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 700;">-<?php echo $discountPercent; ?>%</span>
                                        </div>
                                        <span style="color: #666; font-size: 0.75rem;">Qtd: <?php echo $item['quantity']; ?></span>
                                    </div>
                                    <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span style="color: #1a1a1a; font-weight: 700; font-size: 0.95rem;">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></span>
                                        <span style="color: #666; font-size: 0.75rem;">Qtd: <?php echo $item['quantity']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-totals">
                    <?php 
                    $hasAnyDiscount = $totalOriginal > $totalFinal;
                    $totalSavings = $totalOriginal - $totalFinal;
                    ?>
                    <?php if ($hasAnyDiscount): ?>
                    <div class="summary-row" style="color: #999;">
                        <span>Subtotal Original</span>
                        <span style="text-decoration: line-through; font-size: 0.85rem;">R$ <?php echo number_format($totalOriginal, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-row" style="color: #22c55e; font-weight: 600;">
                        <span>Voce economiza</span>
                        <span>- R$ <?php echo number_format($totalSavings, 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row"><span>Subtotal</span><span style="font-weight: 600;">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span></div>
                    <div class="summary-row"><span>Frete</span><span style="color: #666;">Calculado no pagamento</span></div>
                    <div class="summary-row summary-total" style="border-top: 2px solid #C7A333; padding-top: 12px; margin-top: 8px;">
                        <?php if ($hasAnyDiscount): ?>
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 0.75rem; color: #999; text-decoration: line-through;">R$ <?php echo number_format($totalOriginal, 2, ',', '.'); ?></span>
                            <span style="font-weight: 800; color: #1a1a1a;">Total</span>
                        </div>
                        <?php else: ?>
                        <span style="font-weight: 800; color: #1a1a1a;">Total</span>
                        <?php endif; ?>
                        <span id="totalAmount" style="font-weight: 800; font-size: 1.25rem; color: #1a1a1a;">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.APP_URL = '<?php echo APP_URL; ?>';
    window.cartSubtotal = <?php echo $subtotal; ?>;
</script>
<script src="<?php echo APP_URL; ?>/assets/js/checkout-entrega.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ . '/includes/public-footer.php'; ?>
