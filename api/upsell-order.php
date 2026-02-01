<?php
/**
 * API de Upsell - Adiciona produto ao pedido existente
 * POST: { order_id, product_id }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo nao permitido', 405);
    }
    
    // Pegar dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    
    if (!$orderId || !$productId) {
        throw new Exception('Dados incompletos: order_id e product_id sao obrigatorios');
    }
    
    // Verificar se o pedido existe
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    
    if (!$order) {
        throw new Exception('Pedido nao encontrado');
    }
    
    // Verificar se o pedido nao foi cancelado
    if ($order['status'] === 'cancelled') {
        throw new Exception('Nao e possivel adicionar produtos a um pedido cancelado');
    }
    
    // Buscar produto
    $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch();
    
    if (!$product) {
        throw new Exception('Produto nao encontrado ou indisponivel');
    }
    
    // Verificar se produto ja esta no pedido
    $existsStmt = $pdo->prepare("SELECT id FROM order_items WHERE order_id = ? AND product_id = ?");
    $existsStmt->execute([$orderId, $productId]);
    if ($existsStmt->fetch()) {
        throw new Exception('Este produto ja esta no seu pedido');
    }
    
    // Iniciar transacao
    $pdo->beginTransaction();
    
    try {
        // Adicionar item ao pedido
        $insertStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
            VALUES (?, ?, 1, ?, ?)
        ");
        $insertStmt->execute([$orderId, $productId, $product['price'], $product['price']]);
        
        // Atualizar total do pedido
        $updateStmt = $pdo->prepare("
            UPDATE orders 
            SET total_amount = total_amount + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$product['price'], $orderId]);
        
        // Registrar log/nota
        if (db_column_exists($pdo, 'orders', 'notes')) {
            $noteStmt = $pdo->prepare("
                UPDATE orders 
                SET notes = CONCAT(COALESCE(notes, ''), '\n[UPSELL] Produto adicionado: " . addslashes($product['name']) . " - R$ " . number_format($product['price'], 2, ',', '.') . " em ', NOW())
                WHERE id = ?
            ");
            $noteStmt->execute([$orderId]);
        }
        
        $pdo->commit();
        
        // Buscar novo total
        $newTotalStmt = $pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
        $newTotalStmt->execute([$orderId]);
        $newTotal = $newTotalStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produto adicionado ao pedido com sucesso!',
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price']
            ],
            'new_total' => $newTotal
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 400;
    http_response_code($statusCode);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
