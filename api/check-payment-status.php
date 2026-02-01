<?php
/**
 * API para verificar status do pagamento no Mercado Pago
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
    echo json_encode(['success' => false, 'error' => 'Payment ID nao fornecido']);
    exit;
}

// Configuracoes do Mercado Pago
$mp_environment = getSetting($pdo, 'mercado_pago_environment', 'test');
$mp_access_token = $mp_environment === 'production' 
    ? getSetting($pdo, 'mercado_pago_access_token', '')
    : getSetting($pdo, 'mercado_pago_access_token_test', '');

if (empty($mp_access_token)) {
    echo json_encode(['success' => false, 'error' => 'Mercado Pago nao configurado']);
    exit;
}

try {
    $ch = curl_init('https://api.mercadopago.com/v1/payments/' . $payment_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $mp_access_token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $payment = json_decode($response, true);
        $status = $payment['status'] ?? 'unknown';
        
        // Se aprovado, atualizar pedido
        if ($status === 'approved') {
            $order_id = $payment['external_reference'] ?? null;
            if ($order_id) {
                try {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$order_id]);
                    
                    // Limpar carrinho se ainda existir
                    $_SESSION['cart'] = [];
                    $_SESSION['last_order_id'] = $order_id;
                } catch (PDOException $e) {
                    error_log("Update order status error: " . $e->getMessage());
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'status' => $status,
            'status_detail' => $payment['status_detail'] ?? null
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao consultar pagamento']);
    }
    
} catch (Exception $e) {
    error_log("Check payment status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}
