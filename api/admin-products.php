<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');
startSecureSession();

// Validar acesso admin
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    // Listar produtos
    try {
        // Verificar se a coluna description existe
        $hasDescription = function_exists('db_has_column') && db_has_column($pdo, 'products', 'description');
        
        // Construir query com colunas específicas
        $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
        if ($hasDescription) {
            $columns .= ", p.description";
        }
        
        $stmt = $pdo->query("
            SELECT $columns, b.name as brand_name FROM products p
            JOIN brands b ON p.brand_id = b.id
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
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($method === 'POST' && $action === 'create') {
    // Criar produto
    try {
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $brand_id = (int)($_POST['brand_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if (!$name || !$description || !$brand_id || $price <= 0) {
            throw new Exception('Dados inválidos');
        }

        $imageFilename = 'placeholder.jpg';
        if (!empty($_FILES['image'])) {
            $file = $_FILES['image'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $imageFilename = md5(time()) . '.' . $ext;
            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $imageFilename);
        }

        $stmt = $pdo->prepare("
            INSERT INTO products (name, description, brand_id, price, image_filename, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $brand_id, $price, $imageFilename, $_SESSION['user_id']]);

        logActivity($pdo, 'product_created', null, $_SESSION['user_id'], 'product', $pdo->lastInsertId());

        echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
