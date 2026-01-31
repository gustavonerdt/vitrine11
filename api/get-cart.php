<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Inicializar carrinho se não existir
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_items = [];
$subtotal = 0;
$total_items = 0;

try {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image_path, b.name as brand_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ? AND p.is_active = 1
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Buscar imagem de capa se existir
            $image_url = $product['image_path'] ?? '';
            if (db_table_exists($pdo, 'product_images')) {
                $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_cover = 1 LIMIT 1");
                $imgStmt->execute([$product_id]);
                $coverImage = $imgStmt->fetch();
                if ($coverImage && !empty($coverImage['image_url'])) {
                    $image_url = $coverImage['image_url'];
                }
            }
            
            // Normalizar URL da imagem
            if (!empty($image_url)) {
                $image_url = trim($image_url);
                if (!preg_match('/^https?:\/\//i', $image_url)) {
                    $image_url = ltrim($image_url, '/');
                    $image_url = rtrim(APP_URL, '/') . '/' . $image_url;
                }
            }
            
            $item_total = floatval($product['price']) * $quantity;
            $subtotal += $item_total;
            $total_items += $quantity;
            
            $cart_items[] = [
                'product_id' => (int)$product_id,
                'name' => $product['name'],
                'brand_name' => $product['brand_name'] ?? '',
                'price' => floatval($product['price']),
                'quantity' => $quantity,
                'item_total' => $item_total,
                'image_url' => $image_url
            ];
        }
    }
    
    // Buscar valor mínimo para frete grátis
    $frete_gratis_min = floatval(getSetting($pdo, 'frete_gratis_valor_minimo', 0));
    $faltam_frete_gratis = max(0, $frete_gratis_min - $subtotal);
    
    echo json_encode([
        'success' => true,
        'items' => $cart_items,
        'subtotal' => $subtotal,
        'total_items' => $total_items,
        'frete_gratis_min' => $frete_gratis_min,
        'faltam_frete_gratis' => $faltam_frete_gratis,
        'tem_frete_gratis' => $subtotal >= $frete_gratis_min
    ]);
    
} catch (PDOException $e) {
    error_log("Get cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar carrinho',
        'items' => [],
        'subtotal' => 0,
        'total_items' => 0
    ]);
}
?>
