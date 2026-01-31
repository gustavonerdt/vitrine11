<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// #region agent log
$logFile = __DIR__ . '/../.cursor/debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logData = json_encode([
    'id' => 'log_' . time() . '_payment',
    'timestamp' => time() * 1000,
    'location' => 'create-payment.php:8',
    'message' => 'Create payment API called',
    'data' => ['method' => $_SERVER['REQUEST_METHOD'], 'has_checkout_data' => isset($_SESSION['checkout_data']), 'has_cart' => isset($_SESSION['cart']), 'cart_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0],
    'sessionId' => 'debug-session',
    'runId' => 'run1',
    'hypothesisId' => 'C'
]) . "\n";
@file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
// #endregion

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Verificar se há dados de checkout e carrinho
if (!isset($_SESSION['checkout_data']) || empty($_SESSION['checkout_data'])) {
    echo json_encode(['success' => false, 'error' => 'Dados de checkout não encontrados']);
    exit;
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'error' => 'Carrinho vazio']);
    exit;
}

$checkout_data = $_SESSION['checkout_data'];

// Aceitar JSON (Payment Brick) ou FormData (Pix)
$input = json_decode(file_get_contents('php://input'), true);

// Log de erro
$logFile = __DIR__ . '/../.logs/error.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

if (json_last_error() !== JSON_ERROR_NONE && !$input) {
    // FormData (Pix)
    $payment_method = $_POST['payment_method'] ?? 'pix';
    $seller_name = trim($_POST['seller_name'] ?? '');
    $token = $_POST['token'] ?? '';
    $installments = (int)($_POST['installments'] ?? 1);
    $payment_method_id = $_POST['payment_method_id'] ?? '';
    $issuer_id = $_POST['issuer_id'] ?? null;
} else {
    // Payment Brick (JSON) - Validar campos obrigatórios
    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dados JSON inválidos ou ausentes']);
        exit;
    }
    
    $required = ['transaction_amount', 'token', 'installments', 'payment_method_id', 'payer'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Campo obrigatório ausente: {$field}"]);
            exit;
        }
    }
    
    if (!is_numeric($input['transaction_amount']) || $input['transaction_amount'] <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Valor da transação inválido']);
        exit;
    }
    
    if (!filter_var($input['payer']['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'E-mail do pagador inválido']);
        exit;
    }
    
    $payment_method = 'credit_card';
    $token = $input['token'];
    $installments = (int)($input['installments']);
    $payment_method_id = $input['payment_method_id'];
    $issuer_id = $input['issuer_id'] ?? null;
    $seller_name = trim($input['seller_name'] ?? '');
}

// seller_name agora é opcional - usa nome da loja como padrão
if (empty($seller_name)) {
    $seller_name = getSetting($pdo, 'app_name', APP_NAME);
}

// Calcular totais
$cart_items = [];
$subtotal = 0;

foreach ($_SESSION['cart'] as $product_id => $quantity) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $item_total = floatval($product['price']) * $quantity;
            $subtotal += $item_total;
            
            $cart_items[] = [
                'product_id' => (int)$product_id,
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'quantity' => $quantity
            ];
        }
    } catch (PDOException $e) {
        error_log("Cart item fetch error: " . $e->getMessage());
    }
}

$shipping_cost = floatval($checkout_data['shipping_price'] ?? 0);
$total = $subtotal + $shipping_cost;

try {
    $pdo->beginTransaction();
    
    // Capturar IP e localização
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $location_data = null;
    
    // Tentar obter localização via IP (usando serviço gratuito)
    if ($ip_address && $ip_address !== '127.0.0.1') {
        try {
            $ch = curl_init("http://ip-api.com/json/{$ip_address}?fields=status,country,regionName,city,lat,lon");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $location_response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $location_data = $location_response;
            }
        } catch (Exception $e) {
            error_log("Location fetch error: " . $e->getMessage());
        }
    }
    
    // Criar pedido
    $session_id = session_id();
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO orders (session_id, user_id, status, total_amount, shipping_cost, discount_amount, payment_method, created_at)
        VALUES (?, ?, 'pending', ?, ?, 0, ?, NOW())
    ");
    $stmt->execute([$session_id, $user_id, $total, $shipping_cost, $payment_method]);
    $order_id = $pdo->lastInsertId();
    
    // Adicionar itens do pedido
    foreach ($cart_items as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
    }
    
    // Salvar endereço de entrega
    $stmt = $pdo->prepare("
        INSERT INTO shipping_addresses (session_id, order_id, cep, street, number, complement, neighborhood, city, state, recipient_name, phone, email, cpf_cnpj, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $session_id,
        $order_id,
        $checkout_data['cep'],
        $checkout_data['street'],
        $checkout_data['number'] ?? '',
        $checkout_data['complement'] ?? '',
        $checkout_data['neighborhood'],
        $checkout_data['city'],
        $checkout_data['state'],
        $checkout_data['recipient_name'],
        $checkout_data['phone'] ?? '',
        $checkout_data['email'],
        $checkout_data['cpf_cnpj'] ?? ''
    ]);
    
    // Processar pagamento via Mercado Pago
    $mp_environment = getSetting($pdo, 'mercado_pago_environment', 'test');
    $mp_access_token = $mp_environment === 'production' 
        ? getSetting($pdo, 'mercado_pago_access_token', '')
        : getSetting($pdo, 'mercado_pago_access_token_test', '');
    
    $payment_result = null;
    
    // Log de erro
    $logFile = __DIR__ . '/../.logs/error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    if (empty($mp_access_token)) {
        $errorMsg = 'Mercado Pago Access Token não configurado';
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - {$errorMsg}\n", FILE_APPEND | LOCK_EX);
        error_log($errorMsg);
    } elseif ($payment_method === 'credit_card' && !empty($token)) {
        try {
            $payment_result = createMercadoPagoCardPayment($mp_access_token, $order_id, $total, $checkout_data, $token, $installments, $payment_method_id, $issuer_id);
            
            if ($payment_result && isset($payment_result['id'])) {
                // Atualizar pedido com ID do pagamento
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET mercado_pago_payment_id = ?, mercado_pago_order_id = ?, status = ?
                    WHERE id = ?
                ");
                $status = ($payment_result['status'] === 'approved') ? 'paid' : 'pending';
                $stmt->execute([$payment_result['id'], $payment_result['id'], $status, $order_id]);
            }
        } catch (Exception $e) {
            $errorMsg = "Erro ao criar pagamento Mercado Pago: " . $e->getMessage();
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - {$errorMsg}\n", FILE_APPEND | LOCK_EX);
            error_log($errorMsg);
            throw $e;
        }
    } elseif ($payment_method === 'pix') {
        $payment_result = createMercadoPagoPixPayment($mp_access_token, $order_id, $total, $checkout_data);
        
        if ($payment_result && isset($payment_result['id'])) {
            // Atualizar pedido com ID do pagamento
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET mercado_pago_payment_id = ?, mercado_pago_order_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$payment_result['id'], $payment_result['id'], $order_id]);
        }
    }
    
    // Salvar metadata do pedido (IP, localização)
    if ($ip_address) {
        try {
            $metaStmt = $pdo->prepare("
                INSERT INTO order_metadata (order_id, ip_address, user_agent, location_data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $metaStmt->execute([$order_id, $ip_address, $user_agent, $location_data]);
        } catch (PDOException $e) {
            error_log("Order metadata save error: " . $e->getMessage());
        }
    }
    
    $pdo->commit();
    
    // Limpar carrinho
    $_SESSION['cart'] = [];
    
    // Salvar order_id na sessão para confirmação
    $_SESSION['last_order_id'] = $order_id;
    
    // Retornar resposta compatível com Payment Brick
    if ($input && $payment_method === 'credit_card') {
        // Resposta para Payment Brick
        if ($payment_result && isset($payment_result['status'])) {
            echo json_encode([
                'status' => $payment_result['status'],
                'status_detail' => $payment_result['status_detail'] ?? '',
                'payment_id' => $payment_result['id'] ?? null,
                'order_id' => $order_id,
                'message' => $payment_result['status'] === 'approved' ? 'Pagamento aprovado!' : 'Pagamento processado',
                'success' => true
            ]);
        } else {
            http_response_code(402);
            echo json_encode([
                'status' => 'error',
                'status_detail' => 'payment_failed',
                'message' => 'Erro ao processar pagamento. Verifique as credenciais do Mercado Pago.',
                'success' => false,
                'error' => 'Erro ao processar pagamento'
            ]);
        }
    } else {
        // Resposta padrao (Pix)
        echo json_encode([
            'success' => true,
            'status' => $payment_result ? ($payment_result['status'] ?? 'pending') : 'pending',
            'order_id' => $order_id,
            'payment_data' => $payment_result,
            'message' => 'Pedido criado com sucesso!'
        ]);
    }
    
} catch (\MercadoPago\Exception\ApiException $e) {
    $pdo->rollBack();
    $errorBody = $e->getApiResponse()->getContent();
    $message = $errorBody['message'] ?? 'Erro na API do Mercado Pago';
    
    // Log erro
    $logFile = __DIR__ . '/../.logs/error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Mercado Pago API Exception: {$message}\n", FILE_APPEND | LOCK_EX);
    
    error_log("Mercado Pago API Exception: " . $message);
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
        'cause' => $errorBody['cause'] ?? null
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Log erro
    $logFile = __DIR__ . '/../.logs/error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Create payment error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    
    error_log("Create payment error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno no servidor. Tente novamente.',
        'error' => $e->getMessage()
    ]);
}

function createMercadoPagoPixPayment($access_token, $order_id, $amount, $checkout_data) {
    $webhook_url = APP_URL . '/api/mercado-pago-webhook.php';
    
    $payment_data = [
        'transaction_amount' => floatval($amount),
        'description' => 'Pedido #' . $order_id,
        'payment_method_id' => 'pix',
        'payer' => [
            'email' => $checkout_data['email'],
            'first_name' => explode(' ', $checkout_data['recipient_name'] ?? '')[0] ?? 'Cliente',
            'last_name' => implode(' ', array_slice(explode(' ', $checkout_data['recipient_name'] ?? ''), 1)) ?: 'Loja',
            'identification' => [
                'type' => 'CPF',
                'number' => preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? '00000000000')
            ]
        ],
        'notification_url' => $webhook_url,
        'external_reference' => (string)$order_id
    ];
    
    try {
        $ch = curl_init('https://api.mercadopago.com/v1/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Mercado Pago cURL error: " . $curlError);
            return null;
        }
        
        if ($httpCode === 201 || $httpCode === 200) {
            return json_decode($response, true);
        } else {
            error_log("Mercado Pago API error (HTTP $httpCode): " . $response);
            return null;
        }
    } catch (Exception $e) {
        error_log("Mercado Pago Error: " . $e->getMessage());
        return null;
    }
}

function createMercadoPagoCardPayment($access_token, $order_id, $amount, $checkout_data, $token, $installments, $payment_method_id = '', $issuer_id = null) {
    // Log de erro
    $logFile = __DIR__ . '/../.logs/error.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    try {
        // Usar SDK do Mercado Pago se disponível
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            MercadoPago\SDK::setAccessToken($access_token);
            
            $paymentClient = new MercadoPago\Client\Payment\PaymentClient();
            
            $payment = $paymentClient->create([
                "transaction_amount" => floatval($amount),
                "token" => $token,
                "description" => 'Pedido #' . $order_id,
                "installments" => intval($installments),
                "payment_method_id" => $payment_method_id,
                "issuer_id" => $issuer_id ? (string)$issuer_id : null,
                "payer" => [
                    "email" => $checkout_data['email'],
                    "identification" => [
                        "type" => "CPF",
                        "number" => preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? '00000000000')
                    ]
                ],
                "notification_url" => APP_URL . '/api/mercado-pago-webhook.php',
                "external_reference" => (string)$order_id
            ]);
            
            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail ?? ''
            ];
        } catch (\MercadoPago\Exception\ApiException $e) {
            $errorBody = $e->getApiResponse()->getContent();
            $message = $errorBody['message'] ?? 'Erro na API do Mercado Pago';
            
            // Log erro
            $logFile = __DIR__ . '/../.logs/error.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Mercado Pago API Exception: {$message}\n", FILE_APPEND | LOCK_EX);
            
            error_log("Mercado Pago API Exception: " . $message);
            throw new Exception($message);
        } catch (Exception $e) {
            $logFile = __DIR__ . '/../.logs/error.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Mercado Pago Error: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            
            error_log("Mercado Pago Error: " . $e->getMessage());
            throw $e;
        }
    } else {
        // Fallback para cURL se SDK não estiver disponível
            // Fallback para cURL se SDK não estiver disponível
            $webhook_url = APP_URL . '/api/mercado-pago-webhook.php';
            
            $payment_data = [
                'transaction_amount' => floatval($amount),
                'token' => $token,
                'description' => 'Pedido #' . $order_id,
                'installments' => $installments,
                'payment_method_id' => $payment_method_id ?: 'visa',
                'issuer_id' => $issuer_id,
                'payer' => [
                    'email' => $checkout_data['email'],
                    'identification' => [
                        'type' => 'CPF',
                        'number' => preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? '00000000000')
                    ]
                ],
                'notification_url' => $webhook_url,
                'external_reference' => (string)$order_id
            ];
    
    $logData = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'create_card_payment',
        'order_id' => $order_id,
        'amount' => $amount,
        'payment_method_id' => $payment_method_id
    ]) . "\n";
    @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
    $ch = curl_init('https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        $errorLog = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => 'Curl error: ' . $curlError,
            'order_id' => $order_id
        ]) . "\n";
        @file_put_contents($logFile, $errorLog, FILE_APPEND | LOCK_EX);
        error_log("Mercado Pago cURL error: " . $curlError);
        return null;
    }
    
    if ($httpCode === 201) {
        $result = json_decode($response, true);
        $successLog = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'payment_created',
            'order_id' => $order_id,
            'payment_id' => $result['id'] ?? null,
            'status' => $result['status'] ?? null
        ]) . "\n";
        @file_put_contents($logFile, $successLog, FILE_APPEND | LOCK_EX);
        return $result;
    } else {
        $errorData = json_decode($response, true);
        $errorLog = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => 'API error',
            'http_code' => $httpCode,
            'response' => $errorData,
            'order_id' => $order_id
        ]) . "\n";
        @file_put_contents($logFile, $errorLog, FILE_APPEND | LOCK_EX);
        error_log("Mercado Pago API error (HTTP $httpCode): " . $response);
        
        // Log também no sistema de logs padrão
        $systemLogFile = __DIR__ . '/../.logs/error.log';
        $systemLogDir = dirname($systemLogFile);
        if (!is_dir($systemLogDir)) {
            @mkdir($systemLogDir, 0755, true);
        }
        @file_put_contents($systemLogFile, date('Y-m-d H:i:s') . " - Mercado Pago API error (HTTP $httpCode): " . substr($response, 0, 500) . "\n", FILE_APPEND | LOCK_EX);
        
        return null;
    }
}
?>
