<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Acesso negado']));
}

// Ensure tables exist
try {
    // Create dynamic_showcases table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dynamic_showcases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            banner_url VARCHAR(500),
            banner_link VARCHAR(500),
            keywords TEXT,
            display_type ENUM('carousel', 'grid', 'list') DEFAULT 'carousel',
            max_products INT DEFAULT NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Create dynamic_showcase_products table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dynamic_showcase_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            showcase_id INT NOT NULL,
            product_id INT NOT NULL,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_showcase_product (showcase_id, product_id),
            FOREIGN KEY (showcase_id) REFERENCES dynamic_showcases(id) ON DELETE CASCADE
        )
    ");
    
    // Add display_type column if missing
    if (!db_has_column($pdo, 'dynamic_showcases', 'display_type')) {
        $pdo->exec("ALTER TABLE dynamic_showcases ADD COLUMN display_type ENUM('carousel', 'grid', 'list') DEFAULT 'carousel' AFTER keywords");
    }
} catch (Exception $e) {
    // Tables might already exist or foreign key issue
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $_GET['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = !empty($input['id']) ? (int)$input['id'] : null;
        $name = trim($input['name'] ?? '');
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $banner_url = trim($input['banner_url'] ?? '');
        $banner_link = trim($input['banner_link'] ?? '');
        $keywords = trim($input['keywords'] ?? '');
        $display_type = trim($input['display_type'] ?? 'carousel');
        $max_products = !empty($input['max_products']) ? (int)$input['max_products'] : null;
        $display_order = (int)($input['display_order'] ?? 0);
        $is_active = isset($input['is_active']) && $input['is_active'] ? 1 : 0;

        if (empty($name) || empty($title)) {
            throw new Exception('Nome e título são obrigatórios');
        }

        // Check if display_type column exists
        $hasDisplayType = db_has_column($pdo, 'dynamic_showcases', 'display_type');

        if ($id) {
            // Update
            if ($hasDisplayType) {
                $stmt = $pdo->prepare("
                    UPDATE dynamic_showcases SET 
                        name = ?, title = ?, description = ?, banner_url = ?, banner_link = ?,
                        keywords = ?, display_type = ?, max_products = ?, display_order = ?, is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $title, $description, $banner_url, $banner_link,
                    $keywords, $display_type, $max_products, $display_order, $is_active, $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE dynamic_showcases SET 
                        name = ?, title = ?, description = ?, banner_url = ?, banner_link = ?,
                        keywords = ?, max_products = ?, display_order = ?, is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $title, $description, $banner_url, $banner_link,
                    $keywords, $max_products, $display_order, $is_active, $id
                ]);
            }
            $message = 'Vitrine atualizada com sucesso!';
        } else {
            // Create
            if ($hasDisplayType) {
                $stmt = $pdo->prepare("
                    INSERT INTO dynamic_showcases (name, title, description, banner_url, banner_link,
                        keywords, display_type, max_products, display_order, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name, $title, $description, $banner_url, $banner_link,
                    $keywords, $display_type, $max_products, $display_order, $is_active
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO dynamic_showcases (name, title, description, banner_url, banner_link,
                        keywords, max_products, display_order, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name, $title, $description, $banner_url, $banner_link,
                    $keywords, $max_products, $display_order, $is_active
                ]);
            }
            $id = $pdo->lastInsertId();
            $message = 'Vitrine criada com sucesso!';
        }

        logActivity($pdo, $_SESSION['user_id'], 'showcase_' . ($id ? 'updated' : 'created'), "Vitrine: $name");
        
        echo json_encode(['success' => true, 'message' => $message, 'id' => $id]);

    } elseif ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }

        $stmt = $pdo->prepare('DELETE FROM dynamic_showcases WHERE id = ?');
        $stmt->execute([$id]);

        logActivity($pdo, $_SESSION['user_id'], 'showcase_deleted', "Vitrine ID: $id");
        
        echo json_encode(['success' => true, 'message' => 'Vitrine excluída com sucesso!']);

    } elseif ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }

        $stmt = $pdo->prepare('SELECT * FROM dynamic_showcases WHERE id = ?');
        $stmt->execute([$id]);
        $showcase = $stmt->fetch();

        if (!$showcase) {
            throw new Exception('Vitrine não encontrada');
        }

        echo json_encode(['success' => true, 'showcase' => $showcase]);

    } elseif ($action === 'add_product') {
        $showcase_id = (int)($input['showcase_id'] ?? 0);
        $product_id = (int)($input['product_id'] ?? 0);
        
        if ($showcase_id <= 0 || $product_id <= 0) {
            throw new Exception('IDs inválidos');
        }

        // Check if already exists
        $stmt = $pdo->prepare('SELECT id FROM dynamic_showcase_products WHERE showcase_id = ? AND product_id = ?');
        $stmt->execute([$showcase_id, $product_id]);
        if ($stmt->fetch()) {
            throw new Exception('Produto já está nesta vitrine');
        }

        $stmt = $pdo->prepare('INSERT INTO dynamic_showcase_products (showcase_id, product_id, display_order) VALUES (?, ?, 0)');
        $stmt->execute([$showcase_id, $product_id]);

        echo json_encode(['success' => true, 'message' => 'Produto adicionado com sucesso!']);

    } elseif ($action === 'remove_product') {
        $showcase_id = (int)($input['showcase_id'] ?? 0);
        $product_id = (int)($input['product_id'] ?? 0);
        
        if ($showcase_id <= 0 || $product_id <= 0) {
            throw new Exception('IDs inválidos');
        }

        $stmt = $pdo->prepare('DELETE FROM dynamic_showcase_products WHERE showcase_id = ? AND product_id = ?');
        $stmt->execute([$showcase_id, $product_id]);

        echo json_encode(['success' => true, 'message' => 'Produto removido com sucesso!']);

    } elseif ($action === 'get_products') {
        $showcase_id = (int)($_GET['showcase_id'] ?? 0);
        
        if ($showcase_id <= 0) {
            throw new Exception('ID da vitrine inválido');
        }

        // Verificar se a coluna description existe
        $hasDescription = db_has_column($pdo, 'products', 'description');
        
        // Construir query com colunas específicas
        $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
        if ($hasDescription) {
            $columns .= ", p.description";
        }
        
        $stmt = $pdo->prepare("
            SELECT $columns, b.name as brand_name 
            FROM dynamic_showcase_products dsp
            JOIN products p ON dsp.product_id = p.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE dsp.showcase_id = ?
            ORDER BY dsp.display_order ASC
        ");
        $stmt->execute([$showcase_id]);
        $products = $stmt->fetchAll();

        echo json_encode(['success' => true, 'products' => $products]);

    } else {
        throw new Exception('Ação inválida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
