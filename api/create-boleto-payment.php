<?php
/**
 * API para criar pagamento via Boleto Bancario - Mercado Pago
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

// Verificar dados
if (!isset($_SESSION['checkout_data']) || empty($_SESSION['checkout_data'])) {
    echo json_encode(['success' => false, 'error' => 'Dados de checkout nao encontrados']);
    exit;
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'error' => 'Carrinho vazio']);
    exit;
}

$checkout_data = $_SESSION['checkout_data'];

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

// Configuracoes do Mercado Pago
$mp_environment = getSetting($pdo, 'mercado_pago_environment', 'test');
$mp_access_token = $mp_environment === 'production' 
    ? getSetting($pdo, 'mercado_pago_access_token', '')
    : getSetting($pdo, 'mercado_pago_access_token_test', '');

if (empty($mp_access_token)) {
    echo json_encode(['success' => false, 'error' => 'Mercado Pago nao configurado. Configure no painel admin.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $session_id = session_id();
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    
    // Criar pedido
    $stmt = $pdo->prepare("
        INSERT INTO orders (session_id, user_id, status, total_amount, shipping_cost, discount_amount, payment_method, created_at)
        VALUES (?, ?, 'pending', ?, ?, 0, 'boleto', NOW())
    ");
    $stmt->execute([$session_id, $user_id, $total, $shipping_cost]);
    $order_id = $pdo->lastInsertId();
    
    // Adicionar itens
    foreach ($cart_items as $item) {
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
    }
    
    // Salvar endereco
    $stmt = $pdo->prepare("
        INSERT INTO shipping_addresses (session_id, order_id, cep, street, number, complement, neighborhood, city, state, recipient_name, phone, email, cpf_cnpj, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $session_id, $order_id,
        $checkout_data['cep'], $checkout_data['street'], $checkout_data['number'] ?? '',
        $checkout_data['complement'] ?? '', $checkout_data['neighborhood'], $checkout_data['city'],
        $checkout_data['state'], $checkout_data['recipient_name'], $checkout_data['phone'] ?? '',
        $checkout_data['email'], $checkout_data['cpf_cnpj'] ?? ''
    ]);
    
    // Preparar CPF
    $cpf = preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? '');
    
    // Data de vencimento (3 dias)
    $due_date = date('Y-m-d', strtotime('+3 days'));
    
    // Criar pagamento Boleto no Mercado Pago
    $payment_data = [
        'transaction_amount' => (float)$total,
        'description' => 'Pedido #' . $order_id . ' - ' . APP_NAME,
        'payment_method_id' => 'bolbradesco', // Boleto Bradesco
        'payer' => [
            'email' => $checkout_data['email'],
            'first_name' => explode(' ', $checkout_data['recipient_name'])[0] ?? '',
            'last_name' => implode(' ', array_slice(explode(' ', $checkout_data['recipient_name']), 1)) ?: '',
            'identification' => [
                'type' => 'CPF',
                'number' => $cpf
            ],
            'address' => [
                'zip_code' => preg_replace('/[^0-9]/', '', $checkout_data['cep']),
                'street_name' => $checkout_data['street'],
                'street_number' => $checkout_data['number'] ?? 'S/N',
                'neighborhood' => $checkout_data['neighborhood'],
                'city' => $checkout_data['city'],
                'federal_unit' => $checkout_data['state']
            ]
        ],
        'date_of_expiration' => $due_date . 'T23:59:59.000-03:00',
        'external_reference' => (string)$order_id,
        'notification_url' => APP_URL . '/api/mercado-pago-webhook.php'
    ];
    
    $ch = curl_init('https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $mp_access_token,
        'X-Idempotency-Key' => uniqid('boleto_' . $order_id . '_')
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Erro de conexao: ' . $curlError);
    }
    
    $payment = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($payment['id'])) {
        // Atualizar pedido com ID do pagamento
        $stmt = $pdo->prepare("UPDATE orders SET mercado_pago_order_id = ? WHERE id = ?");
        $stmt->execute([$payment['id'], $order_id]);
        
        $pdo->commit();
        
        // Pegar dados do Boleto
        $barcode = $payment['barcode']['content'] ?? '';
        $external_resource_url = $payment['transaction_details']['external_resource_url'] ?? '';
        
        $_SESSION['pending_payment_order_id'] = $order_id;
        $_SESSION['pending_payment_id'] = $payment['id'];
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'payment_id' => $payment['id'],
            'barcode' => $barcode,
            'boleto_url' => $external_resource_url,
            'due_date' => $due_date,
            'total' => $total
        ]);
    } else {
        $pdo->rollBack();
        $error_msg = $payment['message'] ?? ($payment['cause'][0]['description'] ?? 'Erro ao criar boleto');
        error_log("Boleto Payment Error: " . json_encode($payment));
        echo json_encode(['success' => false, 'error' => $error_msg]);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Boleto Payment Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
