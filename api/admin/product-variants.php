<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado']));
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    try {
        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) {
            throw new Exception('ID do produto é obrigatório');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY display_order ASC, price ASC");
        $stmt->execute([$productId]);
        http_response_code(200);
        echo json_encode(['success' => true, 'variants' => $stmt->fetchAll()]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} elseif ($method === 'POST' && $action === 'create') {
    try {
        $productId = (int)($input['product_id'] ?? 0);
        $name = sanitizeInput($input['name'] ?? '');
        $description = sanitizeInput($input['description'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $points = (int)($input['points'] ?? 0);
        $displayOrder = (int)($input['display_order'] ?? 0);
        
        if (!$productId || !$name || $price <= 0) {
            throw new Exception('Dados inválidos');
        }
        
        $image_path = trim($input['image_path'] ?? '');
        
        $stmt = $pdo->prepare("
            INSERT INTO product_variants (product_id, name, description, price, points, display_order, image_path, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$productId, $name, $description, $price, $points, $displayOrder, $image_path]);
        
        logActivity($pdo, $_SESSION['user_id'] ?? 0, 'create_variant', "Criou variante: $name para produto ID: $productId");
        
        http_response_code(200);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Variante criada com sucesso', 'id' => $pdo->lastInsertId()]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} elseif ($method === 'POST' && $action === 'update') {
    try {
        $id = (int)($input['id'] ?? 0);
        $name = sanitizeInput($input['name'] ?? '');
        $description = sanitizeInput($input['description'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $points = (int)($input['points'] ?? 0);
        $displayOrder = (int)($input['display_order'] ?? 0);
        
        if (!$id || !$name || $price <= 0) {
            throw new Exception('Dados inválidos');
        }
        
        $stmt = $pdo->prepare("
            UPDATE product_variants 
            SET name = ?, description = ?, price = ?, points = ?, display_order = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $points, $displayOrder, $id]);
        
        logActivity($pdo, $_SESSION['user_id'] ?? 0, 'update_variant', "Atualizou variante ID: $id");
        
        http_response_code(200);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Variante atualizada com sucesso']);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} elseif ($method === 'POST' && $action === 'delete') {
    try {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID inválido');
        }
        
        $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($pdo, $_SESSION['user_id'] ?? 0, 'delete_variant', "Excluiu variante ID: $id");
        
        http_response_code(200);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Variante excluída com sucesso']);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
}
?>

