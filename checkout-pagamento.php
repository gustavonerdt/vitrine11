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
            SELECT p.id, p.name, p.price, b.name as brand_name,
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
                'product_id' => (int)$product_id,
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'quantity' => $quantity,
                'item_total' => $item_total,
                'image_url' => $imageUrl
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
                    
                    <!-- Endereço de Entrega -->
                    <div class="form-section address-section">
                        <h2 class="section-title"><i class="fas fa-map-marker-alt" style="color: #C7A333; margin-right: 8px;"></i>Endereco de Entrega</h2>
                        <div class="address-display">
                            <p class="address-text" style="color: #1a1a1a; font-size: 1.05rem; font-weight: 500; line-height: 1.6; margin: 0;">
                                <?php 
                                $address_parts = array_filter([
                                    $checkout_data['street'] ?? '',
                                    isset($checkout_data['number']) && $checkout_data['number'] ? 'Nº ' . $checkout_data['number'] : '',
                                    $checkout_data['complement'] ?? '',
                                    'CEP ' . ($checkout_data['cep'] ?? ''),
                                    $checkout_data['neighborhood'] ?? '',
                                    ($checkout_data['city'] ?? '') . ' - ' . ($checkout_data['state'] ?? '')
                                ]);
                                echo htmlspecialchars(implode(', ', $address_parts));
                                ?>
                            </p>
                        </div>
                        <a href="#" class="link-change" onclick="openAddressModal(); return false;" style="color: #C7A333; font-weight: 600; font-size: 0.95rem; margin-top: 0.75rem; display: inline-block;"><i class="fas fa-edit"></i> Alterar endereco</a>
                    </div>
                    
                    <!-- Método de Entrega -->
                    <div class="form-section">
                        <h2 class="section-title"><i class="fas fa-truck" style="color: #C7A333; margin-right: 8px;"></i>Escolha uma Opcao</h2>
                        <div class="shipping-options-container" id="shippingOptionsContainer">
                            <?php
                            // Buscar opcoes de frete da API dos Correios
                            $cep_destino = preg_replace('/[^0-9]/', '', $checkout_data['cep'] ?? '');
                            $cep_origem = getSetting($pdo, 'correios_cep_origem', '01310100');
                            $cep_origem = preg_replace('/[^0-9]/', '', $cep_origem);
                            
                            // Função para calcular frete via API dos Correios
                            function calcularFreteCorreios($cep_origem, $cep_destino, $pdo) {
                                $shipping_options = [];
                                $servicos = [
                                    'PAC' => '04510',
                                    'SEDEX' => '04014'
                                ];
                                
                                // Peso e dimensões padrão
                                $peso_total = 0.5;
                                $altura = 20;
                                $largura = 15;
                                $comprimento = 16;
                                
                                if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                                    $peso_total = count($_SESSION['cart']) * 0.5;
                                    $peso_total = max($peso_total, 0.3);
                                }
                                
                                foreach ($servicos as $nome => $codigo) {
                                    try {
                                        $url = "https://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
                                        $params = http_build_query([
                                            'nCdEmpresa' => '',
                                            'sDsSenha' => '',
                                            'nCdServico' => $codigo,
                                            'sCepOrigem' => $cep_origem,
                                            'sCepDestino' => $cep_destino,
                                            'nVlPeso' => number_format($peso_total, 2, ',', ''),
                                            'nCdFormato' => '1',
                                            'nVlComprimento' => $comprimento,
                                            'nVlAltura' => $altura,
                                            'nVlLargura' => $largura,
                                            'nVlDiametro' => '0',
                                            'sCdMaoPropria' => 'N',
                                            'nVlValorDeclarado' => '0',
                                            'sCdAvisoRecebimento' => 'N',
                                            'StrRetorno' => 'xml'
                                        ]);
                                        
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
                                        $response = curl_exec($ch);
                                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                        curl_close($ch);
                                        
                                        if ($httpCode === 200 && !empty($response)) {
                                            $xml = @simplexml_load_string($response);
                                            if ($xml && isset($xml->cServico)) {
                                                $servico = $xml->cServico;
                                                $erro = (string)$servico->Erro;
                                                
                                                if ($erro === '0' || empty($erro) || $erro === '010' || $erro === '011') {
                                                    $valor = str_replace(',', '.', (string)$servico->Valor);
                                                    $prazo = (int)$servico->PrazoEntrega;
                                                    
                                                    if (floatval($valor) > 0) {
                                                        $shipping_options[] = [
                                                            'code' => $codigo,
                                                            'name' => $nome . ' (Correios)',
                                                            'price' => floatval($valor),
                                                            'days' => $prazo > 0 ? $prazo : ($nome === 'PAC' ? 10 : 5)
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log("Erro ao calcular frete {$nome}: " . $e->getMessage());
                                    }
                                }
                                
                                // Fallback se API falhar
                                if (empty($shipping_options)) {
                                    $shipping_options = [
                                        ['code' => '04510', 'name' => 'PAC (Correios)', 'price' => 35.00, 'days' => 10],
                                        ['code' => '04014', 'name' => 'SEDEX (Correios)', 'price' => 55.00, 'days' => 5]
                                    ];
                                }
                                
                                return $shipping_options;
                            }
                            
                            // Buscar fretes da API
                            $shipping_options_api = calcularFreteCorreios($cep_origem, $cep_destino, $pdo);
                            
                            // Preparar opções com status de seleção
                            $shipping_options = [];
                            $current_method = $checkout_data['shipping_method'] ?? '';
                            $first_selected = false;
                            
                            foreach ($shipping_options_api as $opt) {
                                $is_selected = ($current_method === $opt['name']);
                                if (!$first_selected && empty($current_method)) {
                                    $is_selected = true;
                                    $first_selected = true;
                                }
                                
                                $shipping_options[] = array_merge($opt, ['selected' => $is_selected]);
                                
                                // Atualizar shipping_cost se este for o selecionado
                                if ($is_selected) {
                                    $shipping_cost = $opt['price'];
                                    $_SESSION['checkout_data']['shipping_price'] = $opt['price'];
                                    $_SESSION['checkout_data']['shipping_method'] = $opt['name'];
                                }
                            }
                            
                            // Recalcular total com o frete
                            $total = $subtotal + $shipping_cost;
                            ?>
                            
                            <?php foreach ($shipping_options as $index => $option): ?>
                            <div class="shipping-option-card <?php echo $option['selected'] ? 'selected' : ''; ?>" 
                                 data-price="<?php echo $option['price']; ?>"
                                 data-method="<?php echo htmlspecialchars($option['name']); ?>"
                                 onclick="selectShippingOption(this)">
                                <input type="radio" 
                                       name="shipping_option" 
                                       id="shipping_<?php echo $index; ?>"
                                       value="<?php echo htmlspecialchars($option['name']); ?>"
                                       data-price="<?php echo $option['price']; ?>"
                                       <?php echo $option['selected'] ? 'checked' : ''; ?>
                                       style="display: none;">
                                <div class="shipping-option-info">
                                    <strong class="shipping-option-name"><?php echo htmlspecialchars($option['name']); ?></strong>
                                    <span class="shipping-option-days">Chega em ate <?php echo $option['days']; ?> dias uteis</span>
                                </div>
                                <span class="shipping-option-price"><?php echo formatPrice($option['price']); ?></span>
                            </div>
                            <?php endforeach; ?>
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
                    <div class="form-section">
                        <h2 class="section-title">Forma de pagamento</h2>
                        
                        <div class="payment-methods">
                            <div class="payment-method-tabs">
                                <button type="button" class="payment-tab active" data-method="pix">
                                    <i class="fas fa-qrcode"></i> Pix
                                </button>
                                <button type="button" class="payment-tab" data-method="credit_card">
                                    <i class="fas fa-credit-card"></i> Cartao de credito
                                </button>
                            </div>
                            
                            <!-- Pix Payment -->
                            <div id="pixPayment" class="payment-content active">
                                <p class="pix-description" style="color: #1a1a1a; font-size: 1rem; font-weight: 500; margin-bottom: 1rem; line-height: 1.5;">
                                    Ao gerar o Codigo Pix do pedido voce pode pagar escaneando o QR Code ou Copiar e Colar.
                                </p>
                                <div class="pix-recipient">
                                    <div class="pix-info-row">
                                        <span style="color: #666; font-weight: 500;">Nome:</span>
                                        <span style="color: #1a1a1a; font-weight: 700; font-size: 1.05rem;">Gustavo Felix</span>
                                    </div>
                                    <div class="pix-info-row">
                                        <span style="color: #666; font-weight: 500;">CPF/CNPJ:</span>
                                        <span style="color: #1a1a1a; font-weight: 700; font-size: 1.05rem;">363.923.068-03</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Credit Card Payment -->
                            <div id="creditCardPayment" class="payment-content">
                                <?php if (!empty($mp_public_key)): ?>
                                    <p class="card-description" style="color: #1a1a1a; font-size: 1rem; font-weight: 500; margin-bottom: 1rem;">
                                        Preencha os dados do seu cartao de credito abaixo:
                                    </p>
                                    <div id="paymentBrick_container" style="max-width: 100%; margin: 10px 0; min-height: 350px;"></div>
                                    <div id="payment-status" style="margin-top: 20px; text-align: center; font-weight: bold; display: none; padding: 1rem; border-radius: 8px; background: #f5f5f5;"></div>
                                <?php else: ?>
                                    <div class="alert alert-error" style="padding: 1rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;">
                                        <i class="fas fa-exclamation-circle"></i> Mercado Pago nao configurado. Configure as chaves no painel administrativo.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Termos -->
                    <div class="form-section">
                        <p class="terms-text" style="color: #333 !important; font-size: 0.9rem;">
                            Ao continuar, voce concorda com nossos <a href="#" style="color: #333 !important; text-decoration: underline;">Termos de Uso</a> e <a href="#" style="color: #333 !important; text-decoration: underline;">Politica de Privacidade</a>
                        </p>
                    </div>
                    
                    <input type="hidden" id="payment_method" name="payment_method" value="pix">
                    <input type="hidden" id="mp_token" name="mp_token" value="">
                    
                    <!-- Botao para Pix (Payment Brick tem seu proprio botao) -->
                    <button type="submit" class="btn-make-order" id="btnMakeOrder">
                        FINALIZAR PEDIDO
                    </button>
                    
                    <style>
                    .btn-make-order {
                        background: #C7A333;
                        color: #000;
                        border: none;
                        padding: 1rem;
                        border-radius: 8px;
                        font-weight: 700;
                        font-size: 1.125rem;
                        text-transform: uppercase;
                        cursor: pointer;
                        transition: all 0.3s;
                        width: 100%;
                        position: relative;
                        overflow: hidden;
                        z-index: 1;
                        margin-top: 1.5rem;
                    }
                    
                    .btn-make-order::before {
                        content: "";
                        position: absolute;
                        inset: 0;
                        background: linear-gradient(120deg, #4C3F01 0%, #8A6623 20%, #D4AF37 40%, #F7EF8A 50%, #D4AF37 60%, #8A6623 80%, #4C3F01 100%);
                        background-size: 300% 300%;
                        animation: goldFlow 4s ease-in-out infinite;
                        z-index: -1;
                    }
                    
                    .btn-make-order:hover:not(:disabled) {
                        background: transparent;
                        color: #000;
                        transform: scale(1.02);
                    }
                    
                    .btn-make-order:disabled {
                        background: #ccc !important;
                        cursor: not-allowed;
                        opacity: 0.6;
                    }
                    
                    .btn-make-order:disabled::before {
                        display: none;
                    }
                    

                    
                    /* Notificações */
                    .checkout-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 1rem 1.5rem;
                        border-radius: 8px;
                        color: white;
                        font-weight: 600;
                        z-index: 10000;
                        transform: translateX(400px);
                        transition: transform 0.3s ease, opacity 0.3s ease;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                        opacity: 0;
                    }
                    
                    .checkout-notification.show {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    
                    .checkout-notification-success {
                        background: #22c55e;
                    }
                    
                    .checkout-notification-error {
                        background: #ef4444;
                    }
                    
                    .checkout-notification-info {
                        background: #3b82f6;
                    }
                    
                    .notification-content {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                    }
                    </style>
                </form>
            </div>
            
            <!-- Resumo do Pedido -->
            <div class="checkout-summary">
                <h3 class="summary-title">RESUMO DO PEDIDO</h3>
                <div class="summary-products">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-product-item">
                            <div class="summary-product-image">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div class="summary-placeholder"><i class="fas fa-spray-can"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-product-info">
                                <div class="summary-product-name" style="color: #1a1a1a; font-weight: 600;"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="summary-product-price" style="color: #333;"><?php echo formatPrice($item['price']); ?> x <?php echo $item['quantity']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-totals">
                    <div class="summary-row">
                        <span style="color: #1a1a1a; font-weight: 500;">Subtotal</span>
                        <span style="color: #1a1a1a; font-weight: 600;"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span style="color: #1a1a1a; font-weight: 500;">Frete</span>
                        <span style="color: #1a1a1a; font-weight: 600;" id="shippingCostDisplay"><?php echo formatPrice($shipping_cost); ?></span>
                    </div>
                    <div class="summary-row discount-row" id="discountRow" style="display: none;">
                        <span style="color: #22c55e; font-weight: 500;">Desconto</span>
                        <span style="color: #22c55e; font-weight: 600;" id="discountDisplay">- R$ 0,00</span>
                    </div>
                    <div class="summary-row summary-total" style="border-top: 2px solid #C7A333; padding-top: 1rem; margin-top: 0.5rem;">
                        <span style="color: #1a1a1a; font-size: 1.1rem; font-weight: 700;">Total</span>
                        <span style="color: #C7A333; font-size: 1.25rem; font-weight: 700;" id="totalDisplay"><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                <a href="#" class="link-add-coupon" style="display: block; visibility: visible; opacity: 1; color: #C7A333; text-decoration: none; text-align: center; margin-top: 1rem; padding: 0.5rem; border-radius: 6px; transition: all 0.3s; font-weight: 600;">
                    <i class="fas fa-tag"></i> Adicionar cupom de desconto
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Endereco -->
<div id="addressModal" class="checkout-modal" style="display: none;">
    <div class="checkout-modal-content">
        <div class="checkout-modal-header">
            <h3>Editar Endereco de Entrega</h3>
            <button type="button" class="checkout-modal-close" onclick="closeAddressModal()">&times;</button>
        </div>
        <div class="checkout-modal-body">
            <form id="addressEditForm">
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" id="edit_cep" name="cep" value="<?php echo htmlspecialchars($checkout_data['cep'] ?? ''); ?>" maxlength="9" placeholder="00000-000">
                </div>
                <div class="form-group">
                    <label>Rua</label>
                    <input type="text" id="edit_street" name="street" value="<?php echo htmlspecialchars($checkout_data['street'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Numero</label>
                        <input type="text" id="edit_number" name="number" value="<?php echo htmlspecialchars($checkout_data['number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" id="edit_complement" name="complement" value="<?php echo htmlspecialchars($checkout_data['complement'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" id="edit_neighborhood" name="neighborhood" value="<?php echo htmlspecialchars($checkout_data['neighborhood'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" id="edit_city" name="city" value="<?php echo htmlspecialchars($checkout_data['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <input type="text" id="edit_state" name="state" value="<?php echo htmlspecialchars($checkout_data['state'] ?? ''); ?>" maxlength="2">
                    </div>
                </div>
            </form>
        </div>
        <div class="checkout-modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeAddressModal()">Cancelar</button>
            <button type="button" class="btn-modal-save" onclick="saveAddressChanges()">Salvar Alteracoes</button>
        </div>
    </div>
</div>

<!-- Modal Cupom de Desconto -->
<div id="couponModal" class="checkout-modal" style="display: none;">
    <div class="checkout-modal-content" style="max-width: 400px;">
        <div class="checkout-modal-header">
            <h3><i class="fas fa-tag"></i> Cupom de Desconto</h3>
            <button type="button" class="checkout-modal-close" onclick="closeCouponModal()">&times;</button>
        </div>
        <div class="checkout-modal-body">
            <div class="form-group">
                <label>Digite seu cupom</label>
                <input type="text" id="coupon_code" name="coupon_code" placeholder="CODIGO DO CUPOM" style="text-transform: uppercase;">
            </div>
            <div id="coupon_message" style="display: none; padding: 0.75rem; border-radius: 8px; margin-top: 1rem;"></div>
        </div>
        <div class="checkout-modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeCouponModal()">Cancelar</button>
            <button type="button" class="btn-modal-save" onclick="applyCoupon()">Aplicar Cupom</button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.checkout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.checkout-modal-content {
    background: #fff;
    border-radius: 16px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.checkout-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e5e5e5;
}

.checkout-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #000;
}

.checkout-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.checkout-modal-close:hover {
    color: #000;
}

.checkout-modal-body {
    padding: 1.5rem;
}

.checkout-modal-footer {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid #e5e5e5;
    justify-content: flex-end;
}

.btn-modal-cancel {
    padding: 0.75rem 1.5rem;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-modal-cancel:hover {
    background: #e5e5e5;
}

.btn-modal-save {
    padding: 0.75rem 1.5rem;
    background: #c7a333;
    color: #000;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-modal-save:hover {
    background: #b8962e;
}

/* Shipping Options Cards */
.shipping-options-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.shipping-option-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    background: #fff;
    border: 2px solid #e5e5e5;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.shipping-option-card:hover {
    border-color: #C7A333;
    background: #fef9e0;
}

.shipping-option-card.selected {
    border-color: #C7A333;
    background: #fef9e0;
    box-shadow: 0 0 0 1px #C7A333;
}

.shipping-option-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.shipping-option-name {
    color: #1a1a1a;
    font-size: 1.05rem;
    font-weight: 700;
}

.shipping-option-days {
    color: #666;
    font-size: 0.9rem;
}

.shipping-option-price {
    color: #1a1a1a;
    font-weight: 700;
    font-size: 1.15rem;
}

.shipping-option-card.selected .shipping-option-price {
    color: #C7A333;
}

/* Payment method tabs */
.payment-method-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.payment-tab {
    flex: 1;
    padding: 1rem 1.5rem;
    background: #fff;
    border: 2px solid #e5e5e5;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    color: #666;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.payment-tab i {
    font-size: 1.25rem;
}

.payment-tab:hover {
    border-color: #C7A333;
    color: #1a1a1a;
}

.payment-tab.active {
    border-color: #C7A333;
    background: #fef9e0;
    color: #1a1a1a;
}

.payment-tab.active i {
    color: #C7A333;
}

.payment-content {
    display: none;
    padding: 1rem 0;
}

.payment-content.active {
    display: block;
}

/* Pix recipient info */
.pix-recipient {
    background: #f8f8f8;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}

.pix-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e5e5;
}

.pix-info-row:last-child {
    border-bottom: none;
}

/* Address section styling */
.address-section .address-display {
    background: #f8f8f8;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-top: 0.75rem;
}
</style>

<script>
    window.APP_URL = '<?php echo APP_URL; ?>';
    window.MP_PUBLIC_KEY = '<?php echo htmlspecialchars($mp_public_key); ?>';
    window.orderTotal = <?php echo $total; ?>;
    window.checkoutEmail = '<?php echo htmlspecialchars($checkout_data['email'] ?? ''); ?>';
    window.checkoutCpf = '<?php echo preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? ''); ?>';
    window.appliedCoupon = null;
    window.couponDiscount = 0;
    
    // Address Modal Functions
    function openAddressModal() {
        document.getElementById('addressModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeAddressModal() {
        document.getElementById('addressModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    function saveAddressChanges() {
        const formData = new FormData();
        formData.append('cep', document.getElementById('edit_cep').value);
        formData.append('street', document.getElementById('edit_street').value);
        formData.append('number', document.getElementById('edit_number').value);
        formData.append('complement', document.getElementById('edit_complement').value);
        formData.append('neighborhood', document.getElementById('edit_neighborhood').value);
        formData.append('city', document.getElementById('edit_city').value);
        formData.append('state', document.getElementById('edit_state').value);
        
        fetch(window.APP_URL + '/api/update-checkout-address.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Endereco atualizado com sucesso!', 'success');
                closeAddressModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.error || 'Erro ao atualizar endereco', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Erro ao atualizar endereco', 'error');
        });
    }
    
    // Coupon Modal Functions
    function openCouponModal() {
        document.getElementById('couponModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.getElementById('coupon_code').focus();
    }
    
    function closeCouponModal() {
        document.getElementById('couponModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    function applyCoupon() {
        const couponCode = document.getElementById('coupon_code').value.trim().toUpperCase();
        const messageDiv = document.getElementById('coupon_message');
        
        if (!couponCode) {
            messageDiv.style.display = 'block';
            messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            messageDiv.style.color = '#ef4444';
            messageDiv.textContent = 'Digite um codigo de cupom';
            return;
        }
        
        fetch(window.APP_URL + '/api/validate-coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coupon_code: couponCode, total: window.orderTotal })
        })
        .then(response => response.json())
        .then(data => {
            messageDiv.style.display = 'block';
            if (data.success) {
                messageDiv.style.background = 'rgba(34, 197, 94, 0.1)';
                messageDiv.style.color = '#22c55e';
                messageDiv.textContent = 'Cupom aplicado! Desconto: R$ ' + data.discount.toFixed(2).replace('.', ',');
                
                window.appliedCoupon = couponCode;
                window.couponDiscount = data.discount;
                
                // Update total display
                updateTotalWithDiscount(data.discount);
                
                setTimeout(() => {
                    closeCouponModal();
                    showNotification('Cupom aplicado com sucesso!', 'success');
                }, 1500);
            } else {
                messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                messageDiv.style.color = '#ef4444';
                messageDiv.textContent = data.error || 'Cupom invalido';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.style.display = 'block';
            messageDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            messageDiv.style.color = '#ef4444';
            messageDiv.textContent = 'Erro ao validar cupom';
        });
    }
    
    function updateTotalWithDiscount(discount) {
        const totalElement = document.getElementById('totalDisplay');
        const discountRow = document.getElementById('discountRow');
        const discountDisplay = document.getElementById('discountDisplay');
        
        if (discount > 0) {
            // Mostrar linha de desconto
            if (discountRow) {
                discountRow.style.display = 'flex';
            }
            if (discountDisplay) {
                discountDisplay.textContent = '- R$ ' + discount.toFixed(2).replace('.', ',');
            }
        }
        
        if (totalElement) {
            const newTotal = window.orderTotal - discount;
            totalElement.textContent = 'R$ ' + newTotal.toFixed(2).replace('.', ',');
            window.orderTotal = newTotal;
        }
    }
    
    // Shipping Option Selection
    function selectShippingOption(element) {
        // Remove selected from all
        document.querySelectorAll('.shipping-option-card').forEach(card => {
            card.classList.remove('selected');
            card.querySelector('input[type="radio"]').checked = false;
        });
        
        // Add selected to clicked
        element.classList.add('selected');
        element.querySelector('input[type="radio"]').checked = true;
        
        // Update shipping cost in session
        const price = parseFloat(element.dataset.price);
        const method = element.dataset.method;
        
        fetch(window.APP_URL + '/api/update-shipping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ shipping_price: price, shipping_method: method })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update display
                location.reload();
            }
        });
    }
    
    // CEP mask
    document.getElementById('edit_cep')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 5) {
            value = value.substring(0, 5) + '-' + value.substring(5, 8);
        }
        e.target.value = value;
        
        // Auto-fill address
        if (value.replace(/\D/g, '').length === 8) {
            fetch('https://viacep.com.br/ws/' + value.replace(/\D/g, '') + '/json/')
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('edit_street').value = data.logradouro || '';
                    document.getElementById('edit_neighborhood').value = data.bairro || '';
                    document.getElementById('edit_city').value = data.localidade || '';
                    document.getElementById('edit_state').value = data.uf || '';
                }
            });
        }
    });
    
    // Coupon link handler
    document.querySelector('.link-add-coupon')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCouponModal();
    });
</script>
<script src="<?php echo APP_URL; ?>/assets/js/checkout-pagamento.js"></script>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
