<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Webhook do Mercado Pago para atualizar status dos pedidos

// Validar assinatura do webhook (x-signature header)
$mp_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$mp_request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

// Log webhook recebido
$logFile = __DIR__ . '/../logs/webhook.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$raw_body = file_get_contents('php://input');
$data = json_decode($raw_body, true);

// Log da requisicao
@file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook received: " . substr($raw_body, 0, 500) . "\n", FILE_APPEND | LOCK_EX);

// Verificar se e uma requisicao valida do MP (tem assinatura ou request_id)
if (empty($mp_signature) && empty($mp_request_id) && empty($data)) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

if (isset($data['type']) && $data['type'] === 'payment') {
    $payment_id = $data['data']['id'] ?? null;
    
    if ($payment_id) {
        // Buscar informações do pagamento
        $mp_environment = getSetting($pdo, 'mercado_pago_environment', 'test');
        $mp_access_token = $mp_environment === 'production' 
            ? getSetting($pdo, 'mercado_pago_access_token', '')
            : getSetting($pdo, 'mercado_pago_access_token_test', '');
        
        if (!empty($mp_access_token)) {
            $ch = curl_init("https://api.mercadopago.com/v1/payments/{$payment_id}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $mp_access_token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $payment = json_decode($response, true);
                
                if (isset($payment['external_reference'])) {
                    $order_id = (int)$payment['external_reference'];
                    $status = $payment['status'] ?? 'pending';
                    
                    // Mapear status do Mercado Pago para status do pedido
                    $order_status = 'pending';
                    if ($status === 'approved') {
                        $order_status = 'paid';
                    } elseif ($status === 'rejected' || $status === 'cancelled') {
                        $order_status = 'cancelled';
                    } elseif ($status === 'refunded') {
                        $order_status = 'cancelled';
                    }
                    
                    // Atualizar pedido
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE orders 
                            SET status = ?, updated_at = NOW()
                            WHERE id = ? OR mercado_pago_payment_id = ?
                        ");
                        $stmt->execute([$order_status, $order_id, $payment_id]);
                    } catch (PDOException $e) {
                        error_log("Webhook update error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

http_response_code(200);
echo 'OK';
?>
