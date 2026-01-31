<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'brands' => []];

try {
    // Try to use status filter if column exists, otherwise fallback
    if (function_exists('db_has_column') && db_has_column($pdo, 'brands', 'status')) {
        $stmt = $pdo->prepare("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name");
        $stmt->execute();
        $brands = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM brands ORDER BY name");
        $stmt->execute();
        $brands = $stmt->fetchAll();
    }

    $response['success'] = true;
    $response['brands'] = $brands;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
