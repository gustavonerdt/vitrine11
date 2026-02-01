<?php
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

// Rate limiting: max 20 calculos de frete por minuto
if (!applyRateLimit($pdo, 'calculate_shipping', 20, 60)) {
    exit;
}

// Aceitar tanto POST normal quanto JSON
$input_json = file_get_contents('php://input');
$json_data = json_decode($input_json, true);

// Pegar CEP de destino
$cep_destino = preg_replace('/[^0-9]/', '', 
    $json_data['cep_destino'] ?? 
    $json_data['cep'] ?? 
    $_POST['cep_destino'] ?? 
    $_POST['cep'] ?? ''
);

// Pegar dimensoes se fornecidas (para calculadora do admin)
$peso_fornecido = isset($json_data['peso']) ? floatval($json_data['peso']) : null;
$altura_fornecida = isset($json_data['altura']) ? intval($json_data['altura']) : null;
$largura_fornecida = isset($json_data['largura']) ? intval($json_data['largura']) : null;
$comprimento_fornecido = isset($json_data['comprimento']) ? intval($json_data['comprimento']) : null;

$cep_origem = getSetting($pdo, 'correios_cep_origem', '');
$cep_origem = preg_replace('/[^0-9]/', '', $cep_origem);

// Token do Melhor Envio
$melhor_envio_token = getSetting($pdo, 'melhor_envio_token', '');
$melhor_envio_sandbox = getSetting($pdo, 'melhor_envio_sandbox', '1') === '1';

// Valor mínimo para frete grátis
$frete_gratis_valor_minimo = floatval(getSetting($pdo, 'frete_gratis_valor_minimo', '0'));

// Validar CEP (8 digitos e formato valido)
if (empty($cep_destino) || strlen($cep_destino) !== 8 || !preg_match('/^[0-9]{8}$/', $cep_destino)) {
    echo json_encode(['success' => false, 'error' => 'CEP invalido. Informe os 8 digitos.']);
    exit;
}

// Validar se CEP comeca com digito valido (01-99)
$cep_prefix = substr($cep_destino, 0, 2);
if ((int)$cep_prefix < 1 || (int)$cep_prefix > 99) {
    echo json_encode(['success' => false, 'error' => 'CEP invalido. Prefixo nao reconhecido.']);
    exit;
}

if (empty($cep_origem)) {
    echo json_encode(['success' => false, 'error' => 'CEP de origem não configurado no painel administrativo']);
    exit;
}

// Calcular peso total e dimensões do carrinho
$peso_total = 0;
$altura_total = 0;
$largura_total = 0;
$comprimento_total = 0;
$valor_total = 0;

// Valores padrão para perfumes (usando constantes do config.php)
$peso_padrao = defined('SHIPPING_DEFAULT_WEIGHT') ? SHIPPING_DEFAULT_WEIGHT : 0.3;
$altura_padrao = defined('SHIPPING_DEFAULT_HEIGHT') ? SHIPPING_DEFAULT_HEIGHT : 15;
$largura_padrao = defined('SHIPPING_DEFAULT_WIDTH') ? SHIPPING_DEFAULT_WIDTH : 8;
$comprimento_padrao = defined('SHIPPING_DEFAULT_LENGTH') ? SHIPPING_DEFAULT_LENGTH : 8;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        try {
            // Buscar dados do produto incluindo peso e dimensões
            $stmt = $pdo->prepare("SELECT price, shipping_weight, shipping_height, shipping_width, shipping_length FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $valor_total += floatval($product['price']) * $quantity;
                
                // Usar valores do produto ou valores padrão
                $peso_produto = floatval($product['shipping_weight'] ?? 0) > 0 ? floatval($product['shipping_weight']) : $peso_padrao;
                $altura_produto = intval($product['shipping_height'] ?? 0) > 0 ? intval($product['shipping_height']) : $altura_padrao;
                $largura_produto = intval($product['shipping_width'] ?? 0) > 0 ? intval($product['shipping_width']) : $largura_padrao;
                $comprimento_produto = intval($product['shipping_length'] ?? 0) > 0 ? intval($product['shipping_length']) : $comprimento_padrao;
                
                $peso_total += $peso_produto * $quantity;
                $altura_total = max($altura_total, $altura_produto);
                $largura_total = max($largura_total, $largura_produto);
                $comprimento_total = max($comprimento_total, $comprimento_produto);
            }
        } catch (PDOException $e) {
            error_log("Error fetching product for shipping: " . $e->getMessage());
        }
    }
} else {
    // Se não tem carrinho, verificar se foram fornecidas dimensoes (calculadora admin)
    if ($peso_fornecido !== null) {
        $peso_total = $peso_fornecido;
        $altura_total = $altura_fornecida ?? $altura_padrao;
        $largura_total = $largura_fornecida ?? $largura_padrao;
        $comprimento_total = $comprimento_fornecido ?? $comprimento_padrao;
    } else {
        // Usar valores padrão de 1 produto
        $peso_total = $peso_padrao;
        $altura_total = $altura_padrao;
        $largura_total = $largura_padrao;
        $comprimento_total = $comprimento_padrao;
    }
    $valor_total = 100; // Valor padrão para seguro
}

// Valores mínimos exigidos pelo Melhor Envio (usando constantes)
$peso_total = max($peso_total, defined('SHIPPING_MIN_WEIGHT') ? SHIPPING_MIN_WEIGHT : 0.1);
$altura_total = max($altura_total, defined('SHIPPING_MIN_HEIGHT') ? SHIPPING_MIN_HEIGHT : 2);
$largura_total = max($largura_total, defined('SHIPPING_MIN_WIDTH') ? SHIPPING_MIN_WIDTH : 11);
$comprimento_total = max($comprimento_total, defined('SHIPPING_MIN_LENGTH') ? SHIPPING_MIN_LENGTH : 16);

$shipping_options = [];

// Verificar se deve usar Melhor Envio ou Correios direto
if (!empty($melhor_envio_token)) {
    // Usar API do Melhor Envio
    $shipping_options = calcularFreteMelhorEnvio(
        $cep_origem, 
        $cep_destino, 
        $peso_total, 
        $altura_total, 
        $largura_total, 
        $comprimento_total, 
        $valor_total,
        $melhor_envio_token,
        $melhor_envio_sandbox
    );
}

// Fallback para API dos Correios se Melhor Envio falhar ou não estiver configurado
if (empty($shipping_options)) {
    $shipping_options = calcularFreteCorreiosDireto(
        $cep_origem, 
        $cep_destino, 
        $peso_total, 
        $altura_total, 
        $largura_total, 
        $comprimento_total
    );
}

// Se ainda vazio, usar fallback fixo (usando constantes)
if (empty($shipping_options)) {
    $fallback_price = defined('SHIPPING_FALLBACK_PRICE') ? SHIPPING_FALLBACK_PRICE : 25.00;
    $fallback_days = defined('SHIPPING_FALLBACK_DAYS') ? SHIPPING_FALLBACK_DAYS : 10;
    
    $shipping_options = [
        [
            'code' => '99999',
            'name' => 'Entrega Padrão',
            'price' => $fallback_price,
            'days' => $fallback_days,
            'delivery_date' => date('d/m/Y', strtotime("+{$fallback_days} weekdays")),
            'delivery_text' => "Chega em até {$fallback_days} dias úteis",
            'company' => 'Padrão'
        ]
    ];
}

// Aplicar frete grátis se valor do carrinho >= valor mínimo
$frete_gratis_aplicado = false;
if ($frete_gratis_valor_minimo > 0 && $valor_total >= $frete_gratis_valor_minimo) {
    $frete_gratis_aplicado = true;
    // Adicionar opção de frete grátis no topo
    array_unshift($shipping_options, [
        'code' => 'FREE',
        'name' => 'Frete Grátis',
        'price' => 0,
        'days' => $shipping_options[0]['days'] ?? 10,
        'delivery_date' => $shipping_options[0]['delivery_date'] ?? date('d/m/Y', strtotime("+10 weekdays")),
        'delivery_text' => "Frete grátis! Chega em até " . ($shipping_options[0]['days'] ?? 10) . " dias úteis",
        'company' => 'Promoção'
    ]);
}

// Ordenar por preço
usort($shipping_options, function($a, $b) {
    return $a['price'] <=> $b['price'];
});

echo json_encode([
    'success' => true,
    'options' => $shipping_options,
    'frete_gratis' => $frete_gratis_aplicado,
    'frete_gratis_minimo' => $frete_gratis_valor_minimo,
    'valor_carrinho' => $valor_total
]);

/**
 * Calcular frete usando API do Melhor Envio
 */
function calcularFreteMelhorEnvio($cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento, $valor, $token, $sandbox = true) {
    $shipping_options = [];
    
    // URL da API
    $base_url = $sandbox 
        ? "https://sandbox.melhorenvio.com.br/api/v2/me/shipment/calculate"
        : "https://www.melhorenvio.com.br/api/v2/me/shipment/calculate";
    
    $data = [
        'from' => ['postal_code' => $cep_origem],
        'to' => ['postal_code' => $cep_destino],
        'products' => [
            [
                'id' => '1',
                'width' => (int)$largura,
                'height' => (int)$altura,
                'length' => (int)$comprimento,
                'weight' => (float)$peso,
                'insurance_value' => (float)$valor,
                'quantity' => 1
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: Vitrine Independente (suporte@vitrineindependente.com)"
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("Melhor Envio cURL Error: " . $curlError);
        return [];
    }
    
    if ($httpCode !== 200) {
        error_log("Melhor Envio HTTP Error: " . $httpCode . " - " . $response);
        return [];
    }
    
    $result = json_decode($response, true);
    
    if (!is_array($result)) {
        error_log("Melhor Envio invalid response: " . $response);
        return [];
    }
    
    foreach ($result as $servico) {
        // Pular serviços com erro ou sem preço
        if (isset($servico['error']) || empty($servico['price'])) {
            continue;
        }
        
        $price = floatval($servico['price']);
        if ($price <= 0) continue;
        
        $days = intval($servico['delivery_time'] ?? 10);
        $delivery_date = date('d/m/Y', strtotime("+{$days} weekdays"));
        
        // Mapear nomes das transportadoras
        $company_name = $servico['company']['name'] ?? 'Transportadora';
        $service_name = $servico['name'] ?? 'Envio';
        
        $shipping_options[] = [
            'code' => $servico['id'] ?? $servico['name'],
            'name' => $service_name . ' (' . $company_name . ')',
            'price' => $price,
            'days' => $days,
            'delivery_date' => $delivery_date,
            'delivery_text' => getDeliveryDayText($days),
            'company' => $company_name
        ];
    }
    
    return $shipping_options;
}

/**
 * Calcular frete usando API direta dos Correios (fallback)
 */
function calcularFreteCorreiosDireto($cep_origem, $cep_destino, $peso, $altura, $largura, $comprimento) {
    $shipping_options = [];
    
    $servicos = [
        'PAC' => '04510',
        'SEDEX' => '04014'
    ];
    
    foreach ($servicos as $nome => $codigo) {
        try {
            $url = "https://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
            $params = http_build_query([
                'nCdEmpresa' => '',
                'sDsSenha' => '',
                'nCdServico' => $codigo,
                'sCepOrigem' => $cep_origem,
                'sCepDestino' => $cep_destino,
                'nVlPeso' => number_format($peso, 2, ',', ''),
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Seguranca: validar certificado SSL
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
                            $prazo = $prazo > 0 ? $prazo : ($nome === 'PAC' ? 10 : 5);
                            $delivery_date = date('d/m/Y', strtotime("+{$prazo} weekdays"));
                            
                            $shipping_options[] = [
                                'code' => $codigo,
                                'name' => $nome . ' (Correios)',
                                'price' => floatval($valor),
                                'days' => $prazo,
                                'delivery_date' => $delivery_date,
                                'delivery_text' => getDeliveryDayText($prazo),
                                'company' => 'Correios'
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao calcular frete Correios {$nome}: " . $e->getMessage());
        }
    }
    
    return $shipping_options;
}

/**
 * Gerar texto de previsão de entrega
 */
function getDeliveryDayText($days) {
    if ($days <= 1) return 'Chega hoje';
    if ($days <= 2) return 'Chega amanhã';
    $date = date('d/m', strtotime("+{$days} weekdays"));
    return "Chega até {$date}";
}
?>
