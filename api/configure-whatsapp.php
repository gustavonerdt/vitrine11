<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Verify admin access
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $whatsapp_number = sanitizeInput($_POST['whatsapp_number'] ?? '');
        $whatsapp_default_message = sanitizeInput($_POST['whatsapp_default_message'] ?? '');
        
        if (empty($whatsapp_number)) {
            throw new Exception('Número de WhatsApp é obrigatório');
        }

        // Validate WhatsApp number format (remove special chars)
        $cleaned_number = preg_replace('/[^0-9]/', '', $whatsapp_number);
        
        if (strlen($cleaned_number) < 11) {
            throw new Exception('Número de WhatsApp inválido');
        }

        // Store in settings table (or create if needed)
        $stmt = $pdo->prepare('
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ');
        
        $stmt->execute(['whatsapp_number', $cleaned_number, $cleaned_number]);
        $stmt->execute(['whatsapp_default_message', $whatsapp_default_message, $whatsapp_default_message]);

        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip_address) 
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'], 
            'whatsapp_config_updated',
            json_encode(['number' => substr($cleaned_number, -4)]),
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Configuração do WhatsApp atualizada com sucesso'
        ]);
    } else {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['whatsapp_number']);
        $result = $stmt->fetch();
        $whatsapp_number = $result['setting_value'] ?? WHATSAPP_DEFAULT_NUMBER;

        $stmt->execute(['whatsapp_default_message']);
        $result = $stmt->fetch();
        $whatsapp_message = $result['setting_value'] ?? WHATSAPP_DEFAULT_MESSAGE;

        echo json_encode([
            'success' => true,
            'whatsapp_number' => $whatsapp_number,
            'whatsapp_message' => $whatsapp_message
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
