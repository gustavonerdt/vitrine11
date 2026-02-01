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

// Rate limiting: max 30 adicoes ao carrinho por minuto
if (!applyRateLimit($pdo, 'add_to_cart', 30, 60)) {
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

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
    $stmt = $pdo->prepare("SELECT id, name, price, is_active, stock_quantity FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou inativo']);
        exit;
    }
    
    // Verificar estoque disponivel (se a coluna existir)
    $stock = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : null;
    $current_qty = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
    $new_total_qty = $current_qty + $quantity;
    
    // Validar estoque apenas se stock_quantity estiver definido e >= 0
    if ($stock !== null && $stock >= 0 && $new_total_qty > $stock) {
        $available = max(0, $stock - $current_qty);
        if ($available <= 0) {
            echo json_encode(['success' => false, 'error' => 'Produto sem estoque disponivel']);
            exit;
        }
        echo json_encode(['success' => false, 'error' => "Estoque insuficiente. Disponivel: {$available} unidades"]);
        exit;
    }
    
    // Adicionar ou atualizar quantidade
    $old_qty = $current_qty;
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
