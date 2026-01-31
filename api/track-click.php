<?php
/**
 * API de Tracking de Cliques
 * Registra cliques em botões e links
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }

    $clickType = $input['click_type'] ?? null;
    $productId = isset($input['product_id']) && $input['product_id'] !== null ? (int)$input['product_id'] : null;
    $variantId = isset($input['variant_id']) && $input['variant_id'] !== null ? (int)$input['variant_id'] : null;

    // Validar click_type
    if (!$clickType || !in_array($clickType, ['buy_button', 'whatsapp', 'product_view', 'variant_select'])) {
        throw new Exception('Tipo de clique inválido. Use: buy_button, whatsapp, product_view ou variant_select');
    }

    // Verificar se a tabela existe
    if (!db_table_exists($pdo, 'click_tracking')) {
        error_log("Tracking Error: Tabela click_tracking não existe");
        throw new Exception('Sistema de tracking não configurado');
    }

    // Obter informações do visitante
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Inserir clique
    $stmt = $pdo->prepare("
        INSERT INTO click_tracking (click_type, product_id, variant_id, ip_address, user_agent, clicked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$clickType, $productId, $variantId, $ip, $userAgent]);
    
    error_log("Tracking: Clique registrado - Tipo: $clickType, Produto: " . ($productId ?? 'null') . ", Variante: " . ($variantId ?? 'null') . ", IP: $ip");
    
    $response['success'] = true;
    $response['message'] = 'Clique registrado com sucesso';

} catch (PDOException $e) {
    error_log("Track click error: " . $e->getMessage());
    error_log("Track click stack: " . $e->getTraceAsString());
    $response['message'] = 'Erro ao registrar clique: ' . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    error_log("Track click exception: " . $e->getMessage());
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
exit;

