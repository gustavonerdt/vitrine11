<?php
/**
 * API de Tracking de Visitas
 * Registra visitas em páginas da vitrine
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

    $pageType = $input['page_type'] ?? null;
    $pageId = isset($input['page_id']) && $input['page_id'] !== null ? (int)$input['page_id'] : null;

    // Validar page_type
    if (!$pageType || !in_array($pageType, ['vitrine', 'product', 'home'])) {
        throw new Exception('Tipo de página inválido. Use: vitrine, product ou home');
    }

    // Verificar se a tabela existe
    if (!db_table_exists($pdo, 'page_visits')) {
        error_log("Tracking Error: Tabela page_visits não existe");
        throw new Exception('Sistema de tracking não configurado');
    }

    // Obter informações do visitante
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    
    // Limitar tamanho do referer
    if ($referer && strlen($referer) > 500) {
        $referer = substr($referer, 0, 500);
    }
    
    // Inserir visita
    $stmt = $pdo->prepare("
        INSERT INTO page_visits (page_type, page_id, ip_address, user_agent, referer, visited_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$pageType, $pageId, $ip, $userAgent, $referer]);
    
    error_log("Tracking: Visita registrada - Tipo: $pageType, ID: " . ($pageId ?? 'null') . ", IP: $ip");
    
    $response['success'] = true;
    $response['message'] = 'Visita registrada com sucesso';

} catch (PDOException $e) {
    error_log("Track visit error: " . $e->getMessage());
    error_log("Track visit stack: " . $e->getTraceAsString());
    $response['message'] = 'Erro ao registrar visita: ' . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    error_log("Track visit exception: " . $e->getMessage());
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
exit;

