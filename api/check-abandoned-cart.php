<?php
/**
 * API para verificar se o usuario tem carrinho abandonado
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $session_id = session_id();
    $email = $_SESSION['abandoned_cart_email'] ?? null;
    
    // Verificar se ja mostrou o popup nessa sessao
    $popup_shown = $_SESSION['abandoned_popup_shown'] ?? false;
    
    // Se ja mostrou ou nao tem email salvo, verificar pelo session_id
    $lead = null;
    
    if ($email) {
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, cpf_cnpj, cep, street, number, neighborhood, city, state, 
                   cart_data, cart_total, checkout_step, recovered
            FROM leads 
            WHERE email = ? AND recovered = 0
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $lead = $stmt->fetch();
    }
    
    if (!$lead) {
        // Tentar pelo session_id
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, cpf_cnpj, cep, street, number, neighborhood, city, state, 
                   cart_data, cart_total, checkout_step, recovered
            FROM leads 
            WHERE session_id = ? AND recovered = 0
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$session_id]);
        $lead = $stmt->fetch();
    }
    
    if ($lead && !$popup_shown) {
        // Verificar se o checkout step permite mostrar o popup
        $show_popup = in_array($lead['checkout_step'], ['delivery', 'payment']);
        
        // Verificar se tem dados suficientes para ir direto ao pagamento
        $has_delivery_data = !empty($lead['cep']) && !empty($lead['street']) && 
                            !empty($lead['number']) && !empty($lead['city']) && 
                            !empty($lead['state']) && !empty($lead['name']) && 
                            !empty($lead['phone']);
        
        // Restaurar dados na sessao
        if ($show_popup) {
            $_SESSION['checkout_data'] = [
                'email' => $lead['email'],
                'recipient_name' => $lead['name'],
                'phone' => $lead['phone'],
                'cpf_cnpj' => $lead['cpf_cnpj'],
                'cep' => $lead['cep'],
                'street' => $lead['street'],
                'number' => $lead['number'],
                'neighborhood' => $lead['neighborhood'],
                'city' => $lead['city'],
                'state' => $lead['state']
            ];
            
            // Restaurar carrinho se disponivel
            if (!empty($lead['cart_data'])) {
                $cart_items = json_decode($lead['cart_data'], true);
                if (is_array($cart_items)) {
                    $_SESSION['cart'] = [];
                    foreach ($cart_items as $item) {
                        $_SESSION['cart'][$item['product_id']] = $item['quantity'];
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'has_abandoned_cart' => $show_popup,
            'lead_id' => $lead['id'],
            'checkout_step' => $lead['checkout_step'],
            'has_delivery_data' => $has_delivery_data,
            'cart_total' => floatval($lead['cart_total']),
            'redirect_url' => $has_delivery_data ? APP_URL . '/checkout-pagamento.php' : APP_URL . '/checkout-entrega.php'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_abandoned_cart' => false
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Check abandoned cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao verificar carrinho']);
}
