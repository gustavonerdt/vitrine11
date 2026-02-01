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
                            // Buscar opcoes de frete usando a API calculate-shipping.php
                            $cep_destino = preg_replace('/[^0-9]/', '', $checkout_data['cep'] ?? '');
                            
                            // Chamar a API de frete internamente
                            $shipping_options_api = [];
                            
                            // Simular chamada à API
                            $_POST_BACKUP = $_POST;
                            $_POST['cep'] = $cep_destino;
                            
                            ob_start();
                            
                            // Incluir lógica da API
                            $cep_origem = getSetting($pdo, 'correios_cep_origem', '');
                            $cep_origem = preg_replace('/[^0-9]/', '', $cep_origem);
                            $melhor_envio_token = getSetting($pdo, 'melhor_envio_token', '');
                            $frete_gratis_valor_minimo = floatval(getSetting($pdo, 'frete_gratis_valor_minimo', '0'));
                            
                            // Calcular peso total e dimensoes do carrinho
                            $peso_total = 0;
                            $altura_total = 0;
                            $largura_total = 0;
                            $comprimento_total = 0;
                            
                            $peso_padrao = 0.3;
                            $altura_padrao = 15;
                            $largura_padrao = 8;
                            $comprimento_padrao = 8;
                            
                            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                                foreach ($_SESSION['cart'] as $product_id => $quantity) {
                                    try {
                                        $stmtShip = $pdo->prepare("SELECT shipping_weight, shipping_height, shipping_width, shipping_length FROM products WHERE id = ?");
                                        $stmtShip->execute([$product_id]);
                                        $productShip = $stmtShip->fetch();
                                        
                                        if ($productShip) {
                                            $peso_produto = floatval($productShip['shipping_weight'] ?? 0) > 0 ? floatval($productShip['shipping_weight']) : $peso_padrao;
                                            $altura_produto = intval($productShip['shipping_height'] ?? 0) > 0 ? intval($productShip['shipping_height']) : $altura_padrao;
                                            $largura_produto = intval($productShip['shipping_width'] ?? 0) > 0 ? intval($productShip['shipping_width']) : $largura_padrao;
                                            $comprimento_produto = intval($productShip['shipping_length'] ?? 0) > 0 ? intval($productShip['shipping_length']) : $comprimento_padrao;
                                            
                                            $peso_total += $peso_produto * $quantity;
                                            $altura_total = max($altura_total, $altura_produto);
                                            $largura_total = max($largura_total, $largura_produto);
                                            $comprimento_total = max($comprimento_total, $comprimento_produto);
                                        } else {
                                            $peso_total += $peso_padrao * $quantity;
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Shipping product fetch error: " . $e->getMessage());
                                    }
                                }
                            }
                            
                            if ($peso_total == 0) $peso_total = $peso_padrao;
                            if ($altura_total == 0) $altura_total = $altura_padrao;
                            if ($largura_total == 0) $largura_total = $largura_padrao;
                            if ($comprimento_total == 0) $comprimento_total = $comprimento_padrao;
                            
                            // Valores minimos
                            $peso_total = max($peso_total, 0.1);
                            $altura_total = max($altura_total, 2);
                            $largura_total = max($largura_total, 11);
                            $comprimento_total = max($comprimento_total, 16);
                            
                            // Funcao para calcular frete Correios
                            function calcularFreteCheckout($cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento) {
                                $shipping_options = [];
                                $servicos = ['PAC' => '04510', 'SEDEX' => '04014'];
                                
                                foreach ($servicos as $nome => $codigo) {
                                    try {
                                        $url = "https://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
                                        $params = http_build_query([
                                            'nCdEmpresa' => '', 'sDsSenha' => '',
                                            'nCdServico' => $codigo, 'sCepOrigem' => $cep_origem, 'sCepDestino' => $cep_destino,
                                            'nVlPeso' => number_format($peso, 2, ',', ''), 'nCdFormato' => '1',
                                            'nVlComprimento' => $comprimento, 'nVlAltura' => $altura, 'nVlLargura' => $largura,
                                            'nVlDiametro' => '0', 'sCdMaoPropria' => 'N', 'nVlValorDeclarado' => '0',
                                            'sCdAvisoRecebimento' => 'N', 'StrRetorno' => 'xml'
                                        ]);
                                        
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
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
                                                            'code' => $codigo, 'name' => $nome . ' (Melhor Envio)',
                                                            'price' => floatval($valor), 'days' => $prazo > 0 ? $prazo : ($nome === 'PAC' ? 10 : 5)
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    } catch (Exception $e) { error_log("Frete {$nome} error: " . $e->getMessage()); }
                                }
                                
                                if (empty($shipping_options)) {
                                    $shipping_options = [
                                        ['code' => '04510', 'name' => 'PAC (Melhor Envio)', 'price' => 35.00, 'days' => 10],
                                        ['code' => '04014', 'name' => 'SEDEX (Melhor Envio)', 'price' => 55.00, 'days' => 5]
                                    ];
                                }
                                return $shipping_options;
                            }
                            
                            $shipping_options_api = calcularFreteCheckout($cep_origem, $cep_destino, $peso_total, $altura_total, $largura_total, $comprimento_total);
                            
                            // Verificar frete gratis
                            $frete_gratis_aplicado = false;
                            if ($frete_gratis_valor_minimo > 0 && $subtotal >= $frete_gratis_valor_minimo) {
                                $frete_gratis_aplicado = true;
                                array_unshift($shipping_options_api, [
                                    'code' => 'FREE', 'name' => 'Frete Gratis', 'price' => 0.00,
                                    'days' => $shipping_options_api[0]['days'] ?? 10
                                ]);
                            }
                            
                            ob_end_clean();
                            $_POST = $_POST_BACKUP;
                            
                            // Preparar opcoes com status de selecao
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
                                
                                if ($is_selected) {
                                    $shipping_cost = $opt['price'];
                                    $_SESSION['checkout_data']['shipping_price'] = $opt['price'];
                                    $_SESSION['checkout_data']['shipping_method'] = $opt['name'];
                                }
                            }
                            
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
                    <div class="form-section payment-section">
                        <h2 class="section-title"><i class="fas fa-credit-card" style="color: #C7A333; margin-right: 8px;"></i>Forma de Pagamento</h2>
                        
                        <div class="payment-methods-container">
                            <!-- Tabs de Pagamento -->
                            <div class="payment-tabs-wrapper">
                                <button type="button" class="payment-tab-btn active" data-method="pix">
                                    <div class="tab-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9.172 15.172L12 12.343l2.828 2.829 1.414-1.414L12 9.515l-4.243 4.243 1.415 1.414z"/>
                                            <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/>
                                        </svg>
                                    </div>
                                    <span class="tab-label">Pix</span>
                                    <span class="tab-badge">Instantaneo</span>
                                </button>
                                <button type="button" class="payment-tab-btn" data-method="credit_card">
                                    <div class="tab-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <span class="tab-label">Cartao</span>
                                    <span class="tab-badge">Ate 12x</span>
                                </button>
                                <button type="button" class="payment-tab-btn" data-method="boleto">
                                    <div class="tab-icon">
                                        <i class="fas fa-barcode"></i>
                                    </div>
                                    <span class="tab-label">Boleto</span>
                                    <span class="tab-badge">3 dias</span>
                                </button>
                            </div>
                            
                            <!-- Conteudo PIX -->
                            <div id="pixPayment" class="payment-content active">
                                <div class="payment-info-card">
                                    <div class="payment-info-header">
                                        <div class="pix-logo">
                                            <svg width="32" height="32" viewBox="0 0 512 512" fill="#00BCAA">
                                                <path d="M242.4 292.5c-5.3 5.3-14 5.3-19.3 0L112.3 181.6c-23.1-23.1-60.5-23.1-83.6 0-23.1 23.1-23.1 60.5 0 83.6l110.8 110.8c46.2 46.2 121.1 46.2 167.3 0l110.8-110.8c23.1-23.1 23.1-60.5 0-83.6-23.1-23.1-60.5-23.1-83.6 0L223.1 292.5z"/>
                                                <path d="M484.1 181.6L373.3 70.8c-46.2-46.2-121.1-46.2-167.3 0L95.2 181.6c-23.1 23.1-23.1 60.5 0 83.6 23.1 23.1 60.5 23.1 83.6 0l110.8-110.8c5.3-5.3 14-5.3 19.3 0l110.8 110.8c23.1 23.1 60.5 23.1 83.6 0 23.1-23.1 23.1-60.5 0-83.6z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4>Pagamento via Pix</h4>
                                            <p>Escaneie o QR Code ou copie o codigo</p>
                                        </div>
                                    </div>
                                    
                                    <div class="pix-cpf-display">
                                        <label>CPF do Pagador</label>
                                        <input type="text" id="pix_cpf" value="<?php echo htmlspecialchars($checkout_data['cpf_cnpj'] ?? ''); ?>" disabled class="cpf-locked">
                                        <small><i class="fas fa-lock"></i> CPF vinculado ao seu cadastro</small>
                                    </div>
                                    
                                    <div class="pix-advantages">
                                        <div class="advantage-item">
                                            <i class="fas fa-bolt"></i>
                                            <span>Aprovacao imediata</span>
                                        </div>
                                        <div class="advantage-item">
                                            <i class="fas fa-shield-alt"></i>
                                            <span>100% Seguro</span>
                                        </div>
                                        <div class="advantage-item">
                                            <i class="fas fa-percentage"></i>
                                            <span>Sem taxas</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Conteudo Cartao de Credito -->
                            <div id="creditCardPayment" class="payment-content">
                                <div class="payment-info-card">
                                    <div class="card-form-container">
                                        <div class="card-preview">
                                            <div class="card-preview-inner">
                                                <div class="card-chip"></div>
                                                <div class="card-number-preview">**** **** **** ****</div>
                                                <div class="card-details-preview">
                                                    <div class="card-holder-preview">NOME DO TITULAR</div>
                                                    <div class="card-expiry-preview">MM/AA</div>
                                                </div>
                                                <div class="card-brand-preview">
                                                    <i class="fab fa-cc-visa"></i>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card-form-fields">
                                            <div class="form-group">
                                                <label>Numero do Cartao</label>
                                                <div class="input-with-icon">
                                                    <input type="text" id="card_number" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number">
                                                    <div class="card-brand-icon" id="cardBrandIcon"></div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Nome no Cartao</label>
                                                <input type="text" id="card_holder" name="card_holder" placeholder="Como esta no cartao" autocomplete="cc-name" style="text-transform: uppercase;">
                                            </div>
                                            
                                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                                <div class="form-group">
                                                    <label>Validade</label>
                                                    <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/AA" maxlength="5" autocomplete="cc-exp">
                                                </div>
                                                <div class="form-group">
                                                    <label>CVV</label>
                                                    <div class="input-with-icon">
                                                        <input type="text" id="card_cvv" name="card_cvv" placeholder="***" maxlength="4" autocomplete="cc-csc">
                                                        <i class="fas fa-question-circle cvv-help" title="Codigo de 3 ou 4 digitos no verso do cartao"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>CPF do Titular</label>
                                                <input type="text" id="card_cpf" value="<?php echo htmlspecialchars($checkout_data['cpf_cnpj'] ?? ''); ?>" disabled class="cpf-locked">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Parcelas</label>
                                                <select id="card_installments" name="installments">
                                                    <option value="1">1x de <?php echo formatPrice($total); ?> (sem juros)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="security-badges-row">
                                        <span><i class="fas fa-lock"></i> SSL 256-bit</span>
                                        <span><i class="fas fa-shield-alt"></i> Mercado Pago</span>
                                        <span><i class="fas fa-check-circle"></i> PCI DSS</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Conteudo Boleto -->
                            <div id="boletoPayment" class="payment-content">
                                <div class="payment-info-card">
                                    <div class="payment-info-header">
                                        <div class="boleto-icon">
                                            <i class="fas fa-barcode"></i>
                                        </div>
                                        <div>
                                            <h4>Boleto Bancario</h4>
                                            <p>Vencimento em 3 dias uteis</p>
                                        </div>
                                    </div>
                                    
                                    <div class="pix-cpf-display">
                                        <label>CPF do Pagador</label>
                                        <input type="text" id="boleto_cpf" value="<?php echo htmlspecialchars($checkout_data['cpf_cnpj'] ?? ''); ?>" disabled class="cpf-locked">
                                        <small><i class="fas fa-lock"></i> CPF vinculado ao seu cadastro</small>
                                    </div>
                                    
                                    <div class="boleto-info">
                                        <div class="info-item">
                                            <i class="fas fa-clock"></i>
                                            <span>O pedido sera confirmado em ate 3 dias uteis apos o pagamento</span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-file-pdf"></i>
                                            <span>Voce podera baixar o boleto em PDF ou copiar o codigo de barras</span>
                                        </div>
                                    </div>
                                </div>
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
                    
                    <!-- Botao de Pagamento -->
                    <button type="submit" class="btn-make-order" id="btnMakeOrder">
                        <i class="fas fa-qrcode" style="margin-right: 0.5rem;"></i> GERAR PIX
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
            <div class="checkout-summary" style="background: #1a1a1a; border-radius: 16px; padding: 1.5rem;">
                <h3 class="summary-title" style="color: #fff; font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.05em;">RESUMO DO PEDIDO</h3>
                <div class="summary-products" style="margin-bottom: 1.25rem;">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-product-item" style="display: flex; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <div class="summary-product-image" style="flex-shrink: 0;">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2);">
                                <?php else: ?>
                                    <div class="summary-placeholder" style="width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-spray-can" style="color: #C7A333;"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-product-info" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                                <div class="summary-product-name" style="color: #fff; font-weight: 600; font-size: 0.95rem; line-height: 1.3;"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="summary-product-price" style="color: rgba(255,255,255,0.7); font-size: 0.875rem;"><?php echo formatPrice($item['price']); ?> x <?php echo $item['quantity']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-totals">
                    <div class="summary-row">
                        <span style="color: #fff; font-weight: 500; font-size: 1rem;">Subtotal</span>
                        <span style="color: #fff; font-weight: 600; font-size: 1rem;"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span style="color: #fff; font-weight: 500; font-size: 1rem;">Frete</span>
                        <span style="color: #fff; font-weight: 600; font-size: 1rem;" id="shippingCostDisplay"><?php echo formatPrice($shipping_cost); ?></span>
                    </div>
                    <div class="summary-row discount-row" id="discountRow" style="display: none;">
                        <span style="color: #22c55e; font-weight: 500; font-size: 1rem;">Desconto</span>
                        <span style="color: #22c55e; font-weight: 600; font-size: 1rem;" id="discountDisplay">- R$ 0,00</span>
                    </div>
                    <div class="summary-row summary-total" style="border-top: 2px solid #C7A333; padding-top: 1rem; margin-top: 0.5rem;">
                        <span style="color: #fff; font-size: 1.15rem; font-weight: 700;">Total</span>
                        <span style="color: #C7A333; font-size: 1.35rem; font-weight: 700;" id="totalDisplay"><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                <a href="#" class="link-add-coupon" onclick="openCouponModal(); return false;" style="display: block; visibility: visible; opacity: 1; color: #C7A333; text-decoration: none; text-align: center; margin-top: 1.25rem; padding: 0.75rem; border-radius: 8px; transition: all 0.3s; font-weight: 600; background: rgba(199, 163, 51, 0.1); border: 1px dashed rgba(199, 163, 51, 0.5);">
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
/* Modal Styles with Smooth Animations */
.checkout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.35s ease, visibility 0.35s ease, background 0.35s ease;
}

.checkout-modal.show {
    opacity: 1;
    visibility: visible;
    background: rgba(0, 0, 0, 0.7);
}

.checkout-modal-content {
    background: #fff;
    border-radius: 20px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    transform: translateY(-40px) scale(0.9);
    opacity: 0;
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
}

.checkout-modal.show .checkout-modal-content {
    transform: translateY(0) scale(1);
    opacity: 1;
}

/* Animation for closing */
.checkout-modal.closing {
    opacity: 0;
    background: rgba(0, 0, 0, 0);
}

.checkout-modal.closing .checkout-modal-content {
    transform: translateY(20px) scale(0.95);
    opacity: 0;
}

/* Card Security Message */
.card-security-message {
    text-align: center;
    padding: 2rem 1rem;
}

.card-security-message .security-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}

.card-security-message .security-icon i {
    font-size: 2.5rem;
    color: #fff;
}

.card-security-message h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 1rem 0;
}

.card-security-message p {
    color: #555;
    font-size: 1rem;
    line-height: 1.6;
    margin: 0 0 1rem 0;
}

.card-security-message .security-badges {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 1.5rem 0;
}

.card-security-message .badge {
    background: #f0f9f0;
    color: #16a34a;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-security-message .card-info-note {
    background: #fef9e0;
    border: 1px solid #C7A333;
    border-radius: 8px;
    padding: 1rem;
    color: #8B6914;
    font-size: 0.9rem;
    margin-top: 1rem;
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

/* ====================================== */
/* NOVA INTERFACE DE PAGAMENTO PREMIUM    */
/* ====================================== */

/* Tabs de Pagamento */
.payment-tabs-wrapper {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.payment-tab-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.25rem 1rem;
    background: #fff;
    border: 2px solid #e5e5e5;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.payment-tab-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #C7A333, #F7EF8A, #C7A333);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.payment-tab-btn:hover {
    border-color: #C7A333;
    background: #fffef5;
    transform: translateY(-2px);
}

.payment-tab-btn.active {
    border-color: #C7A333;
    background: linear-gradient(135deg, #fef9e0 0%, #fff 100%);
    box-shadow: 0 4px 15px rgba(199, 163, 51, 0.25);
}

.payment-tab-btn.active::before {
    transform: scaleX(1);
}

.payment-tab-btn .tab-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    border-radius: 12px;
    font-size: 1.5rem;
    color: #666;
    transition: all 0.3s ease;
}

.payment-tab-btn.active .tab-icon {
    background: linear-gradient(135deg, #C7A333, #d4b84a);
    color: #fff;
}

.payment-tab-btn .tab-label {
    font-weight: 700;
    font-size: 1rem;
    color: #1a1a1a;
}

.payment-tab-btn .tab-badge {
    font-size: 0.7rem;
    font-weight: 600;
    color: #22c55e;
    background: rgba(34, 197, 94, 0.1);
    padding: 0.2rem 0.6rem;
    border-radius: 10px;
}

/* Payment Content */
.payment-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

.payment-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Payment Info Card */
.payment-info-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 16px;
    padding: 1.5rem;
}

.payment-info-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e5e5;
    margin-bottom: 1.25rem;
}

.payment-info-header .pix-logo,
.payment-info-header .boleto-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #00BCAA20, #00bcaa10);
    border-radius: 12px;
}

.payment-info-header .boleto-icon {
    background: linear-gradient(135deg, #C7A33320, #C7A33310);
    font-size: 1.75rem;
    color: #C7A333;
}

.payment-info-header h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a1a;
}

.payment-info-header p {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
}

/* CPF Display */
.pix-cpf-display {
    margin-bottom: 1.25rem;
}

.pix-cpf-display label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 0.5rem;
}

.pix-cpf-display .cpf-locked {
    width: 100%;
    padding: 0.9rem 1rem;
    background: #f8f8f8;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    color: #1a1a1a;
    letter-spacing: 1px;
}

.pix-cpf-display small {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: #888;
}

.pix-cpf-display small i {
    color: #C7A333;
}

/* Pix Advantages */
.pix-advantages {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.pix-advantages .advantage-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
    text-align: center;
}

.pix-advantages .advantage-item i {
    font-size: 1.5rem;
    color: #00BCAA;
}

.pix-advantages .advantage-item span {
    font-size: 0.8rem;
    font-weight: 600;
    color: #444;
}

/* Card Preview */
.card-form-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.card-preview {
    perspective: 1000px;
    margin-bottom: 0.5rem;
}

.card-preview-inner {
    background: linear-gradient(135deg, #1a1a1a 0%, #333 50%, #1a1a1a 100%);
    border-radius: 16px;
    padding: 1.5rem;
    color: #fff;
    position: relative;
    min-height: 180px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.card-chip {
    width: 45px;
    height: 35px;
    background: linear-gradient(135deg, #d4af37 0%, #f7ef8a 50%, #d4af37 100%);
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.card-number-preview {
    font-size: 1.4rem;
    letter-spacing: 3px;
    font-family: 'Courier New', monospace;
    margin-bottom: 1.5rem;
}

.card-details-preview {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    text-transform: uppercase;
}

.card-holder-preview {
    letter-spacing: 1px;
}

.card-brand-preview {
    position: absolute;
    bottom: 1.5rem;
    right: 1.5rem;
    font-size: 2.5rem;
    opacity: 0.9;
}

/* Card Form Fields */
.card-form-fields .form-group {
    margin-bottom: 1rem;
}

.card-form-fields label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #444;
    margin-bottom: 0.5rem;
}

.card-form-fields input,
.card-form-fields select {
    width: 100%;
    padding: 0.9rem 1rem;
    border: 2px solid #e5e5e5;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #fff;
}

.card-form-fields input:focus,
.card-form-fields select:focus {
    outline: none;
    border-color: #C7A333;
    box-shadow: 0 0 0 3px rgba(199, 163, 51, 0.15);
}

.input-with-icon {
    position: relative;
}

.input-with-icon input {
    padding-right: 50px;
}

.input-with-icon .card-brand-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.5rem;
}

.input-with-icon .cvv-help {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    cursor: help;
}

/* Security Badges */
.security-badges-row {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e5e5e5;
    margin-top: 1.25rem;
}

.security-badges-row span {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #22c55e;
    font-weight: 600;
}

.security-badges-row span i {
    font-size: 1rem;
}

/* Boleto Info */
.boleto-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.boleto-info .info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 10px;
}

.boleto-info .info-item i {
    color: #C7A333;
    font-size: 1.1rem;
    margin-top: 2px;
}

.boleto-info .info-item span {
    font-size: 0.9rem;
    color: #444;
    line-height: 1.4;
}

/* Mobile Responsive */
@media (max-width: 640px) {
    .payment-tabs-wrapper {
        grid-template-columns: 1fr;
    }
    
    .payment-tab-btn {
        flex-direction: row;
        justify-content: flex-start;
        padding: 1rem 1.25rem;
    }
    
    .payment-tab-btn .tab-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }
    
    .payment-tab-btn .tab-label {
        flex: 1;
        text-align: left;
    }
    
    .pix-advantages {
        grid-template-columns: 1fr;
    }
    
    .card-number-preview {
        font-size: 1.1rem;
        letter-spacing: 2px;
    }
    
    .security-badges-row {
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
    }
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
    window.orderSubtotal = <?php echo $subtotal; ?>;
    window.orderShipping = <?php echo $shipping_cost; ?>;
    window.orderTotal = <?php echo $total; ?>;
    window.checkoutEmail = '<?php echo htmlspecialchars($checkout_data['email'] ?? ''); ?>';
    window.checkoutCpf = '<?php echo preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? ''); ?>';
    window.appliedCoupon = null;
    window.couponDiscount = 0;
    
    // Address Modal Functions with Animations
    function openAddressModal() {
        const modal = document.getElementById('addressModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Trigger animation
        setTimeout(() => modal.classList.add('show'), 10);
    }
    
    function closeAddressModal() {
        const modal = document.getElementById('addressModal');
        modal.classList.add('closing');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        // Wait for animation to complete
        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('closing');
        }, 400);
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
    
    // Coupon Modal Functions with Animations
    function openCouponModal() {
        const modal = document.getElementById('couponModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Trigger animation
        setTimeout(() => {
            modal.classList.add('show');
            document.getElementById('coupon_code').focus();
        }, 10);
    }
    
    function closeCouponModal() {
        const modal = document.getElementById('couponModal');
        modal.classList.add('closing');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        // Wait for animation to complete
        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('closing');
        }, 400);
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
        
        // Update coupon discount
        window.couponDiscount = discount;
        
        // Calculate new total: subtotal + shipping - discount
        if (totalElement) {
            const newTotal = window.orderSubtotal + window.orderShipping - discount;
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
        
        // Update display immediately without page reload
        const shippingDisplay = document.getElementById('shippingCostDisplay');
        const totalDisplay = document.getElementById('totalDisplay');
        
        // Use global subtotal for accurate calculation
        const subtotal = window.orderSubtotal;
        
        // Update displays
        if (shippingDisplay) {
            shippingDisplay.textContent = 'R$ ' + price.toFixed(2).replace('.', ',');
        }
        
        // Calculate new total: subtotal + shipping - discount
        const newTotal = subtotal + price - (window.couponDiscount || 0);
        if (totalDisplay) {
            totalDisplay.textContent = 'R$ ' + newTotal.toFixed(2).replace('.', ',');
        }
        
        // Update global variables
        window.orderShipping = price;
        window.orderTotal = newTotal;
        
        // Update session via API
        fetch(window.APP_URL + '/api/update-shipping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ shipping_price: price, shipping_method: method })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showNotification('Erro ao atualizar frete', 'error');
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
