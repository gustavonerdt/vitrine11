<?php
/**
 * API para salvar dados de lead em tempo real
 * Usado para capturar dados do checkout-entrega mesmo sem finalizar
 */
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

try {
    // Verificar se a tabela tem as novas colunas
    $checkColumns = $pdo->query("SHOW COLUMNS FROM leads LIKE 'cart_data'")->rowCount();
    if ($checkColumns === 0) {
        // Adicionar novas colunas se nao existirem
        $pdo->exec("ALTER TABLE leads 
            ADD COLUMN IF NOT EXISTS `cpf_cnpj` VARCHAR(20) DEFAULT NULL AFTER `phone`,
            ADD COLUMN IF NOT EXISTS `cep` VARCHAR(10) DEFAULT NULL AFTER `cpf_cnpj`,
            ADD COLUMN IF NOT EXISTS `street` VARCHAR(255) DEFAULT NULL AFTER `cep`,
            ADD COLUMN IF NOT EXISTS `number` VARCHAR(20) DEFAULT NULL AFTER `street`,
            ADD COLUMN IF NOT EXISTS `neighborhood` VARCHAR(100) DEFAULT NULL AFTER `number`,
            ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) DEFAULT NULL AFTER `neighborhood`,
            ADD COLUMN IF NOT EXISTS `state` VARCHAR(2) DEFAULT NULL AFTER `city`,
            ADD COLUMN IF NOT EXISTS `cart_data` LONGTEXT DEFAULT NULL AFTER `state`,
            ADD COLUMN IF NOT EXISTS `cart_total` DECIMAL(10,2) DEFAULT 0.00 AFTER `cart_data`,
            ADD COLUMN IF NOT EXISTS `checkout_step` ENUM('cart','delivery','payment') DEFAULT 'cart' AFTER `cart_total`,
            ADD COLUMN IF NOT EXISTS `recovered` TINYINT(1) DEFAULT 0 AFTER `opted_in`,
            ADD COLUMN IF NOT EXISTS `session_id` VARCHAR(255) DEFAULT NULL AFTER `notes`,
            ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`
        ");
    }
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'E-mail invalido']);
        exit;
    }
    
    // Dados do lead
    $name = trim($_POST['recipient_name'] ?? $_POST['name'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $_POST['cpf_cnpj'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $checkout_step = $_POST['checkout_step'] ?? 'delivery';
    $session_id = session_id();
    
    // Dados do carrinho
    $cart_data = null;
    $cart_total = 0;
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        $cart_items = [];
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                $cart_items[] = [
                    'product_id' => $product_id,
                    'name' => $product['name'],
                    'price' => floatval($product['price']),
                    'quantity' => $quantity
                ];
                $cart_total += floatval($product['price']) * $quantity;
            }
        }
        $cart_data = json_encode($cart_items);
    }
    
    // Verificar se lead ja existe
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Atualizar lead existente
        $stmt = $pdo->prepare("
            UPDATE leads SET
                name = COALESCE(NULLIF(?, ''), name),
                phone = COALESCE(NULLIF(?, ''), phone),
                cpf_cnpj = COALESCE(NULLIF(?, ''), cpf_cnpj),
                cep = COALESCE(NULLIF(?, ''), cep),
                street = COALESCE(NULLIF(?, ''), street),
                number = COALESCE(NULLIF(?, ''), number),
                neighborhood = COALESCE(NULLIF(?, ''), neighborhood),
                city = COALESCE(NULLIF(?, ''), city),
                state = COALESCE(NULLIF(?, ''), state),
                cart_data = COALESCE(?, cart_data),
                cart_total = COALESCE(?, cart_total),
                checkout_step = ?,
                session_id = ?,
                updated_at = NOW()
            WHERE email = ?
        ");
        $stmt->execute([
            $name, $phone, $cpf_cnpj, $cep, $street, $number, 
            $neighborhood, $city, $state, $cart_data, $cart_total,
            $checkout_step, $session_id, $email
        ]);
        $lead_id = $existing['id'];
    } else {
        // Criar novo lead
        $stmt = $pdo->prepare("
            INSERT INTO leads (name, email, phone, cpf_cnpj, cep, street, number, neighborhood, city, state, cart_data, cart_total, checkout_step, source, session_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'checkout_abandonado', ?)
        ");
        $stmt->execute([
            $name, $email, $phone, $cpf_cnpj, $cep, $street, $number, 
            $neighborhood, $city, $state, $cart_data, $cart_total, $checkout_step, $session_id
        ]);
        $lead_id = $pdo->lastInsertId();
    }
    
    // Salvar na sessao para recuperacao
    $_SESSION['abandoned_cart_email'] = $email;
    $_SESSION['checkout_data'] = [
        'email' => $email,
        'recipient_name' => $name,
        'phone' => $phone,
        'cpf_cnpj' => $cpf_cnpj,
        'cep' => $cep,
        'street' => $street,
        'number' => $number,
        'neighborhood' => $neighborhood,
        'city' => $city,
        'state' => $state
    ];
    
    echo json_encode([
        'success' => true,
        'lead_id' => $lead_id,
        'message' => 'Dados salvos com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log("Save lead error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar dados']);
}
