<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

// Verificar se ha dados de checkout e carrinho
if (!isset($_SESSION['checkout_data']) || empty($_SESSION['checkout_data'])) {
    echo json_encode(['success' => false, 'error' => 'Dados de checkout nao encontrados']);
    exit;
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'error' => 'Carrinho vazio']);
    exit;
}

$checkout_data = $_SESSION['checkout_data'];
$payment_method = $_POST['payment_method'] ?? 'credit_card';
$seller_name = trim($_POST['seller_name'] ?? '');

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
    // Se nao tem Mercado Pago configurado, criar pedido direto
    try {
        $pdo->beginTransaction();
        
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
        
        // Salvar endereco de entrega
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
        
        $pdo->commit();
        
        // Limpar carrinho
        $_SESSION['cart'] = [];
        $_SESSION['last_order_id'] = $order_id;
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'message' => 'Pedido criado com sucesso'
        ]);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create order error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao criar pedido']);
        exit;
    }
}

// Criar preferencia do Mercado Pago (Checkout Pro)
try {
    $pdo->beginTransaction();
    
    $session_id = session_id();
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    
    // Criar pedido primeiro
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
    
    // Salvar endereco de entrega
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
    
    // Criar preferencia no Mercado Pago
    $items = [];
    foreach ($cart_items as $item) {
        $items[] = [
            'title' => $item['name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'currency_id' => 'BRL'
        ];
    }
    
    // Adicionar frete como item se houver
    if ($shipping_cost > 0) {
        $items[] = [
            'title' => 'Frete',
            'quantity' => 1,
            'unit_price' => $shipping_cost,
            'currency_id' => 'BRL'
        ];
    }
    
    $preference_data = [
        'items' => $items,
        'payer' => [
            'name' => $checkout_data['recipient_name'] ?? '',
            'email' => $checkout_data['email'] ?? '',
            'identification' => [
                'type' => 'CPF',
                'number' => preg_replace('/[^0-9]/', '', $checkout_data['cpf_cnpj'] ?? '')
            ]
        ],
        'back_urls' => [
            'success' => APP_URL . '/obrigado.php?order_id=' . $order_id,
            'failure' => APP_URL . '/checkout-pagamento.php?error=payment_failed',
            'pending' => APP_URL . '/obrigado.php?order_id=' . $order_id . '&status=pending'
        ],
        'auto_return' => 'approved',
        'external_reference' => (string)$order_id,
        'notification_url' => APP_URL . '/api/mercado-pago-webhook.php'
    ];
    
    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $mp_access_token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Erro de conexao: ' . $curlError);
    }
    
    $preference = json_decode($response, true);
    
    if ($httpCode === 201 && isset($preference['id'])) {
        // Atualizar pedido com ID da preferencia
        $stmt = $pdo->prepare("UPDATE orders SET mercado_pago_order_id = ? WHERE id = ?");
        $stmt->execute([$preference['id'], $order_id]);
        
        $pdo->commit();
        
        // Limpar carrinho
        $_SESSION['cart'] = [];
        $_SESSION['last_order_id'] = $order_id;
        
        // Retornar URL do checkout
        $init_point = $mp_environment === 'production' 
            ? $preference['init_point'] 
            : $preference['sandbox_init_point'];
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'init_point' => $init_point,
            'preference_id' => $preference['id']
        ]);
    } else {
        throw new Exception('Erro ao criar preferencia: ' . ($preference['message'] ?? 'Erro desconhecido'));
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Create preference error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
