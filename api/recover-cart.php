<?php
/**
 * API para marcar carrinho como recuperado ou popup como mostrado
 */
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'dismiss_popup':
            // Apenas marcar que o popup foi mostrado nessa sessao
            $_SESSION['abandoned_popup_shown'] = true;
            echo json_encode(['success' => true, 'message' => 'Popup dismissed']);
            break;
            
        case 'recover':
            // Marcar lead como recuperado
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $email = $_SESSION['abandoned_cart_email'] ?? '';
            
            if ($lead_id > 0) {
                $stmt = $pdo->prepare("UPDATE leads SET recovered = 1 WHERE id = ?");
                $stmt->execute([$lead_id]);
            } elseif (!empty($email)) {
                $stmt = $pdo->prepare("UPDATE leads SET recovered = 1 WHERE email = ?");
                $stmt->execute([$email]);
            }
            
            $_SESSION['abandoned_popup_shown'] = true;
            
            echo json_encode(['success' => true, 'message' => 'Carrinho recuperado']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acao invalida']);
    }
    
} catch (PDOException $e) {
    error_log("Recover cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar']);
}
