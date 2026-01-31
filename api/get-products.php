<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');
startSecureSession();

$response = ['success' => false, 'items' => [], 'totalItems' => 0, 'totalPages' => 0, 'page' => 1];

try {
    // Filters
    $minPrice = isset($_GET['minPrice']) ? (float)$_GET['minPrice'] : 0.0;
    $maxPrice = isset($_GET['maxPrice']) ? (float)$_GET['maxPrice'] : 1000000.0;
    $brands = isset($_GET['brands']) ? $_GET['brands'] : ''; // comma-separated ids
    $sort = $_GET['sort'] ?? 'popular';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;

    // Base where clause: use is_active if exists, otherwise don't filter by is_active
    $params = [$minPrice, $maxPrice];
    if (function_exists('db_has_column') && db_has_column($pdo, 'products', 'is_active')) {
        $where = ' WHERE p.is_active = 1 AND p.price BETWEEN ? AND ? ';
    } else {
        $where = ' WHERE p.price BETWEEN ? AND ? ';
    }

    if (!empty($brands)) {
        $brandIds = array_filter(array_map('intval', explode(',', $brands)));
        if (count($brandIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($brandIds), '?'));
            $where .= " AND p.brand_id IN ($placeholders) ";
            foreach ($brandIds as $id) $params[] = $id;
        }
    }

    // Sorting
    switch ($sort) {
        case 'price_asc':
            $order = ' ORDER BY p.price ASC';
            break;
        case 'price_desc':
            $order = ' ORDER BY p.price DESC';
            break;
        case 'new':
            $order = ' ORDER BY p.created_at DESC';
            break;
        default:
            // popularity or rating fallback to created_at
            $order = ' ORDER BY p.created_at DESC';
            break;
    }

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM products p JOIN brands b ON p.brand_id = b.id" . $where;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();

    // Verificar se a coluna description existe
    $hasDescription = function_exists('db_has_column') && db_has_column($pdo, 'products', 'description');
    
    // Construir query com colunas específicas
    $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
    if ($hasDescription) {
        $columns .= ", p.description";
    }
    
    $sql = "SELECT $columns, b.name as brand_name FROM products p JOIN brands b ON p.brand_id = b.id" . $where . $order . " LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $execParams = $params;
    $execParams[] = $limit;
    $execParams[] = $offset;
    $stmt->execute($execParams);
    $items = $stmt->fetchAll();
    
    // Garantir que description existe no array mesmo se a coluna não existir
    if (!$hasDescription) {
        foreach ($items as &$item) {
            $item['description'] = null;
        }
        unset($item);
    }

    $response['success'] = true;
    $response['items'] = $items;
    $response['totalItems'] = $totalItems;
    $response['totalPages'] = (int)ceil($totalItems / $limit);
    $response['page'] = $page;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
