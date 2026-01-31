<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Configurar log de erros
$logFile = __DIR__ . '/../.logs/error.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$cep_destino = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
$cep_origem = getSetting($pdo, 'correios_cep_origem', '');

if (empty($cep_destino) || strlen($cep_destino) !== 8) {
    echo json_encode(['success' => false, 'error' => 'CEP inválido']);
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

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        // Valores padrão por produto (pode ser configurável no futuro)
        $peso_produto = 0.5; // kg por produto
        $altura_produto = 20; // cm
        $largura_produto = 15; // cm
        $comprimento_produto = 15; // cm
        
        $peso_total += $peso_produto * $quantity;
        $altura_total = max($altura_total, $altura_produto);
        $largura_total = max($largura_total, $largura_produto);
        $comprimento_total = max($comprimento_total, $comprimento_produto);
    }
}

// Valores mínimos exigidos pelos Correios
$peso_total = max($peso_total, 0.3);
$altura_total = max($altura_total, 2);
$largura_total = max($largura_total, 11);
$comprimento_total = max($comprimento_total, 16);

// Formato para API dos Correios
$formato = '1'; // 1 = Caixa/Pacote

// Códigos de serviços dos Correios (WebService Antigo)
// Nota: 04510 e 04014 são os códigos para PAC e SEDEX sem contrato
$servicos = [
    'PAC' => '04510',
    'SEDEX' => '04014'
];

$shipping_options = [];

foreach ($servicos as $nome => $codigo) {
    try {
        // URL da API dos Correios (WebService XML) - USANDO HTTPS para evitar bloqueios
        $url = "https://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
        $params = http_build_query([
            'nCdEmpresa' => '',
            'sDsSenha' => '',
            'nCdServico' => $codigo,
            'sCepOrigem' => $cep_origem,
            'sCepDestino' => $cep_destino,
            'nVlPeso' => number_format($peso_total, 2, ',', ''),
            'nCdFormato' => $formato,
            'nVlComprimento' => $comprimento_total,
            'nVlAltura' => $altura_total,
            'nVlLargura' => $largura_total,
            'nVlDiametro' => '0',
            'sCdMaoPropria' => 'N',
            'nVlValorDeclarado' => '0',
            'sCdAvisoRecebimento' => 'N',
            'StrRetorno' => 'xml'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evitar problemas de certificado em alguns servidores
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($response)) {
            $xml = @simplexml_load_string($response);
            if ($xml && isset($xml->cServico)) {
                $servico = $xml->cServico;
                $erro = (string)$servico->Erro;
                
                if ($erro === '0' || empty($erro) || $erro === '010' || $erro === '011') {
                    // Erros 010 e 011 são avisos, o valor ainda vem
                    $valor = (string)$servico->Valor;
                    $prazo = (int)$servico->PrazoEntrega;
                    
                    if (!empty($valor)) {
                        $valor = str_replace(',', '.', $valor);
                        $valor = floatval($valor);
                        
                        if ($valor > 0) {
                            $prazo = $prazo > 0 ? $prazo : 10;
                            $data_entrega = date('d/m/Y', strtotime("+{$prazo} business days"));
                            
                            $shipping_options[] = [
                                'code' => $codigo,
                                'name' => $nome,
                                'price' => $valor,
                                'days' => $prazo,
                                'delivery_date' => $data_entrega,
                                'delivery_text' => "Chega " . getDeliveryDayText($prazo)
                            ];
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Erro ao calcular {$nome}: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// FALLBACK: Se a API dos Correios falhar (Timeout), fornecer um frete fixo para não travar o checkout
if (empty($shipping_options)) {
    // Tentar simular um frete baseado na região (simplificado)
    $estado = ''; // Poderíamos pegar o estado do banco se tivéssemos salvo
    $shipping_options[] = [
        'code' => '99999',
        'name' => 'Entrega Padrão (Correios)',
        'price' => 25.00,
        'days' => 10,
        'delivery_date' => date('d/m/Y', strtotime("+10 business days")),
        'delivery_text' => "Chega em até 10 dias úteis"
    ];
    
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - API Correios Offline/Timeout. Usando frete de fallback para CEP {$cep_destino}.\n", FILE_APPEND);
}

// Ordenar por preço
usort($shipping_options, function($a, $b) {
    return $a['price'] <=> $b['price'];
});

echo json_encode([
    'success' => true,
    'options' => $shipping_options,
    'source' => empty($shipping_options[0]['code'] === '99999') ? 'correios' : 'fallback'
]);

function getDeliveryDayText($days) {
    if ($days <= 1) return 'hoje';
    if ($days <= 2) return 'amanhã';
    $date = date('d/m', strtotime("+{$days} business days"));
    return "até {$date}";
}
?>
