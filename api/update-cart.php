<?php
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do produto inválido']);
    exit;
}

// Inicializar carrinho se não existir
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Remover se quantidade for 0 ou negativa
if ($quantity <= 0) {
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
} else {
    // Atualizar quantidade
    $_SESSION['cart'][$product_id] = $quantity;
}

// Calcular total de itens
$total_items = 0;
foreach ($_SESSION['cart'] as $qty) {
    $total_items += $qty;
}

echo json_encode([
    'success' => true,
    'message' => 'Carrinho atualizado',
    'cart_count' => $total_items
]);
?>
