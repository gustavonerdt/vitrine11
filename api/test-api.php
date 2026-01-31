<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Verificar se é admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$api_type = $_POST['api_type'] ?? '';
$logFile = __DIR__ . '/../.logs/error.log';

function logError($message) {
    global $logFile;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - {$message}\n", FILE_APPEND | LOCK_EX);
}

// Testar Mercado Pago
if (strpos($api_type, 'mercado_pago') !== false) {
    $token = trim($_POST['token'] ?? '');
    
    if (empty($token)) {
        echo json_encode(['success' => false, 'error' => 'Access Token não fornecido']);
        exit;
    }
    
    try {
        $ch = curl_init('https://api.mercadopago.com/v1/payments/search?limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo json_encode(['success' => false, 'error' => 'Erro de conexão: ' . $curlError]);
            exit;
        }
        
        if ($httpCode === 200) {
            echo json_encode(['success' => true, 'message' => 'Credenciais válidas! API funcionando corretamente.']);
        } elseif ($httpCode === 401) {
            echo json_encode(['success' => false, 'error' => 'Token inválido. Verifique suas credenciais.']);
        } else {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['message'] ?? "Erro HTTP {$httpCode}";
            echo json_encode(['success' => false, 'error' => $errorMsg]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao testar API: ' . $e->getMessage()]);
    }
    
// Testar Correios
} elseif ($api_type === 'correios') {
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    
    if (empty($cep) || strlen($cep) !== 8) {
        echo json_encode(['success' => false, 'error' => 'CEP inválido']);
        exit;
    }
    
    try {
        // Testar com um CEP de destino conhecido (Av. Paulista, SP)
        $cep_destino = '01310100';
        $cep_origem = $cep;
        
        // URL da API dos Correios (WebService XML) - USANDO HTTPS
        $url = "https://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
        $params = http_build_query([
            'nCdEmpresa' => '',
            'sDsSenha' => '',
            'nCdServico' => '04510', // PAC
            'sCepOrigem' => $cep_origem,
            'sCepDestino' => $cep_destino,
            'nVlPeso' => '0,5',
            'nCdFormato' => '1',
            'nVlComprimento' => '20',
            'nVlAltura' => '10',
            'nVlLargura' => '15',
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo json_encode(['success' => false, 'error' => 'Erro de conexão: ' . $curlError]);
            exit;
        }
        
        if ($httpCode === 200 && !empty($response)) {
            $xml = @simplexml_load_string($response);
            if ($xml && isset($xml->cServico)) {
                $servico = $xml->cServico;
                $erro = (string)$servico->Erro;
                
                if ($erro === '0' || empty($erro) || $erro === '010' || $erro === '011') {
                    echo json_encode(['success' => true, 'message' => 'API dos Correios funcionando! CEP de origem válido.']);
                } else {
                    $msgErro = (string)$servico->MsgErro;
                    echo json_encode(['success' => false, 'error' => $msgErro ?: 'Erro ao calcular frete']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Resposta inválida da API dos Correios']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => "Erro HTTP {$httpCode} ao conectar com a API dos Correios"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao testar API: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Tipo de API não reconhecido']);
}
?>
