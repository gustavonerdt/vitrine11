<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get input data (can be JSON or form-data)
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if ($method === 'POST' && strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
} else {
    $input = $_POST;
}

if ($method === 'GET' && $action === 'list') {
    try {
        // Verificar se a coluna description existe
        $hasDescription = db_has_column($pdo, 'products', 'description');
        
        // Construir query com colunas específicas
        $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
        if ($hasDescription) {
            $columns .= ", p.description";
        }
        
        $stmt = $pdo->query("
            SELECT $columns, b.name as brand_name 
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            ORDER BY p.created_at DESC
        ");
        
        $products = $stmt->fetchAll();
        
        // Garantir que description existe no array mesmo se a coluna não existir
        if (!$hasDescription) {
            foreach ($products as &$product) {
                $product['description'] = null;
            }
            unset($product);
        }
        
        echo json_encode(['success' => true, 'products' => $products]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} elseif ($method === 'GET' && $action === 'get') {
    $productId = (int)($_GET['id'] ?? 0);
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do produto não fornecido']);
        exit;
    }
    
    try {
        // Verificar se a coluna description existe
        $hasDescription = db_has_column($pdo, 'products', 'description');
        
        // Construir query com colunas específicas
        $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
        if ($hasDescription) {
            $columns .= ", p.description";
        }
        
        $stmt = $pdo->prepare("
            SELECT $columns, b.name as brand_name 
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        // Garantir que description existe no array mesmo se a coluna não existir
        if (!$hasDescription) {
            $product['description'] = null;
        }
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            exit;
        }
        
        // Get product images
        $images = [];
        if (db_table_exists($pdo, 'product_images')) {
            $imgStmt = $pdo->prepare("SELECT image_url, is_cover, display_order FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, display_order ASC");
            $imgStmt->execute([$productId]);
            $images = $imgStmt->fetchAll();
        }
        
        // If no images in product_images table, use image_path from products table
        if (empty($images) && !empty($product['image_path'])) {
            $images[] = [
                'image_url' => $product['image_path'],
                'is_cover' => 1,
                'display_order' => 0
            ];
        }
        
        // Get product variants
        $variants = [];
        if (db_table_exists($pdo, 'product_variants')) {
            // Verificar se a coluna description existe em product_variants
            $hasVariantDescription = db_has_column($pdo, 'product_variants', 'description');
            
            $variantColumns = "name, price, points, image_path, display_order";
            if ($hasVariantDescription) {
                $variantColumns .= ", description";
            }
            
            $variantStmt = $pdo->prepare("SELECT $variantColumns FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY display_order ASC");
            $variantStmt->execute([$productId]);
            $variants = $variantStmt->fetchAll();
            
            // Garantir que description existe no array mesmo se a coluna não existir
            if (!$hasVariantDescription) {
                foreach ($variants as &$variant) {
                    $variant['description'] = null;
                }
                unset($variant);
            }
        }
        
        error_log("Product API - Product ID: $productId, Images found: " . count($images) . ", Variants found: " . count($variants));
        
        echo json_encode(['success' => true, 'product' => $product, 'images' => $images, 'variants' => $variants]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} elseif ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $input['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
        exit;
    }
    
    $action = $input['action'] ?? $_POST['action'] ?? '';
    
    if ($action === 'bulk_delete') {
        $productIds = $input['product_ids'] ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            
            $pdo->commit();
            
            logActivity($pdo, $_SESSION['user_id'], 'Bulk Delete Products', "Excluiu " . count($productIds) . " produto(s)");
            
            echo json_encode(['success' => true, 'message' => count($productIds) . ' produto(s) excluído(s) com sucesso!']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'bulk_public') {
        $productIds = $input['product_ids'] ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        try {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("UPDATE products SET is_vip = 0 WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            
            logActivity($pdo, $_SESSION['user_id'], 'Bulk Public Products', "Tornou " . count($productIds) . " produto(s) públicos");
            
            echo json_encode(['success' => true, 'message' => count($productIds) . ' produto(s) tornados públicos!']);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'bulk_vip') {
        $productIds = $input['product_ids'] ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        try {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("UPDATE products SET is_vip = 1 WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            
            logActivity($pdo, $_SESSION['user_id'], 'Bulk VIP Products', "Tornou " . count($productIds) . " produto(s) VIP");
            
            echo json_encode(['success' => true, 'message' => count($productIds) . ' produto(s) tornados VIP!']);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'bulk_activate') {
        $productIds = $input['product_ids'] ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        try {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("UPDATE products SET is_active = 1 WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            
            logActivity($pdo, $_SESSION['user_id'], 'Bulk Activate Products', "Ativou " . count($productIds) . " produto(s)");
            
            echo json_encode(['success' => true, 'message' => count($productIds) . ' produto(s) ativado(s)!']);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'bulk_deactivate') {
        $productIds = $input['product_ids'] ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado']);
            exit;
        }
        
        try {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id IN ($placeholders)");
            $stmt->execute($productIds);
            
            logActivity($pdo, $_SESSION['user_id'], 'Bulk Deactivate Products', "Desativou " . count($productIds) . " produto(s)");
            
            echo json_encode(['success' => true, 'message' => count($productIds) . ' produto(s) desativado(s)!']);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            exit;
        }
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
?>
