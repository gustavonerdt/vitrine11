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
    'id' => 'log_' . time() . '_addcart',
    'timestamp' => time() * 1000,
    'location' => 'add-to-cart.php:8',
    'message' => 'Add to cart API called',
    'data' => ['method' => $_SERVER['REQUEST_METHOD'], 'has_post' => !empty($_POST), 'session_id' => session_id()],
    'sessionId' => 'debug-session',
    'runId' => 'run1',
    'hypothesisId' => 'A'
]) . "\n";
@file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
// #endregion

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// #region agent log
$logData = json_encode([
    'id' => 'log_' . time() . '_addcart_params',
    'timestamp' => time() * 1000,
    'location' => 'add-to-cart.php:17',
    'message' => 'Product params received',
    'data' => ['product_id' => $product_id, 'quantity' => $quantity, 'cart_exists' => isset($_SESSION['cart']), 'cart_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0],
    'sessionId' => 'debug-session',
    'runId' => 'run1',
    'hypothesisId' => 'A'
]) . "\n";
@file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
// #endregion

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do produto inválido']);
    exit;
}

if ($quantity <= 0) {
    $quantity = 1;
}

// Inicializar carrinho se não existir
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Verificar se produto existe e está ativo
try {
    $stmt = $pdo->prepare("SELECT id, name, price, is_active FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou inativo']);
        exit;
    }
    
    // Adicionar ou atualizar quantidade
    $old_qty = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    
    // Calcular total de itens
    $total_items = 0;
    foreach ($_SESSION['cart'] as $qty) {
        $total_items += $qty;
    }
    
    // #region agent log
    $logData = json_encode([
        'id' => 'log_' . time() . '_addcart_success',
        'timestamp' => time() * 1000,
        'location' => 'add-to-cart.php:48',
        'message' => 'Product added to cart successfully',
        'data' => ['product_id' => $product_id, 'old_qty' => $old_qty, 'new_qty' => $_SESSION['cart'][$product_id], 'total_items' => $total_items, 'cart_items' => $_SESSION['cart']],
        'sessionId' => 'debug-session',
        'runId' => 'run1',
        'hypothesisId' => 'A'
    ]) . "\n";
    @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    // #endregion
    
    echo json_encode([
        'success' => true,
        'message' => 'Produto adicionado ao carrinho',
        'cart_count' => $total_items,
        'product_name' => $product['name']
    ]);
    
} catch (PDOException $e) {
    error_log("Add to cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao adicionar produto ao carrinho']);
}
?>
