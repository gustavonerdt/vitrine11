<?php
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$coupon_code = strtoupper(trim($input['coupon_code'] ?? ''));
$total = floatval($input['total'] ?? 0);

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'error' => 'Codigo do cupom obrigatorio']);
    exit;
}

// Verificar se a tabela de cupons existe
if (!db_table_exists($pdo, 'coupons')) {
    // Criar tabela de cupons
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `coupons` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `description` VARCHAR(255) DEFAULT NULL,
                `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
                `discount_value` DECIMAL(10, 2) NOT NULL,
                `min_order_value` DECIMAL(10, 2) DEFAULT 0,
                `max_discount` DECIMAL(10, 2) DEFAULT NULL,
                `usage_limit` INT DEFAULT NULL,
                `usage_count` INT DEFAULT 0,
                `valid_from` DATE DEFAULT NULL,
                `valid_until` DATE DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_code` (`code`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_valid_dates` (`valid_from`, `valid_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Error creating coupons table: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao verificar cupom']);
        exit;
    }
}

// Buscar cupom
try {
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE code = ? AND is_active = 1
    ");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'error' => 'Cupom nao encontrado ou inativo']);
        exit;
    }
    
    // Verificar validade por data
    $today = date('Y-m-d');
    if (!empty($coupon['valid_from']) && $today < $coupon['valid_from']) {
        echo json_encode(['success' => false, 'error' => 'Este cupom ainda nao esta valido']);
        exit;
    }
    if (!empty($coupon['valid_until']) && $today > $coupon['valid_until']) {
        echo json_encode(['success' => false, 'error' => 'Este cupom expirou']);
        exit;
    }
    
    // Verificar limite de uso
    if (!empty($coupon['usage_limit']) && $coupon['usage_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'error' => 'Este cupom atingiu o limite de uso']);
        exit;
    }
    
    // Verificar valor minimo do pedido
    if ($coupon['min_order_value'] > 0 && $total < $coupon['min_order_value']) {
        echo json_encode([
            'success' => false, 
            'error' => 'Valor minimo do pedido para este cupom: R$ ' . number_format($coupon['min_order_value'], 2, ',', '.')
        ]);
        exit;
    }
    
    // Calcular desconto
    $discount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount = ($total * $coupon['discount_value']) / 100;
        
        // Aplicar limite maximo se existir
        if (!empty($coupon['max_discount']) && $discount > $coupon['max_discount']) {
            $discount = $coupon['max_discount'];
        }
    } else {
        $discount = $coupon['discount_value'];
    }
    
    // Garantir que o desconto nao seja maior que o total
    if ($discount > $total) {
        $discount = $total;
    }
    
    // Salvar cupom na sessao
    $_SESSION['applied_coupon'] = [
        'code' => $coupon['code'],
        'discount' => $discount,
        'coupon_id' => $coupon['id']
    ];
    
    echo json_encode([
        'success' => true,
        'discount' => $discount,
        'coupon_code' => $coupon['code'],
        'discount_type' => $coupon['discount_type'],
        'message' => 'Cupom aplicado com sucesso!'
    ]);
    
} catch (PDOException $e) {
    error_log("Coupon validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao validar cupom']);
}
