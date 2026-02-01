<?php
/**
 * API para restaurar versao do tema
 */
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticacao
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

// Ler dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['version_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID da versao nao fornecido']);
    exit;
}

$versionId = intval($input['version_id']);

try {
    // Buscar versao
    $stmt = $pdo->prepare("SELECT * FROM theme_versions WHERE id = ?");
    $stmt->execute([$versionId]);
    $version = $stmt->fetch();
    
    if (!$version) {
        echo json_encode(['success' => false, 'error' => 'Versao nao encontrada']);
        exit;
    }
    
    // Decodificar configuracoes
    $settings = json_decode($version['settings_data'], true);
    
    if (!is_array($settings)) {
        echo json_encode(['success' => false, 'error' => 'Dados da versao invalidos']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Salvar versao atual antes de restaurar
    $currentSettings = [];
    $allowedSettings = [
        'color_primary', 'color_secondary', 'color_accent', 'color_background',
        'color_text', 'color_text_muted', 'header_bg', 'header_text', 'nav_bg',
        'card_bg', 'card_border', 'card_hover_bg', 'button_primary_bg',
        'button_primary_text', 'button_secondary_bg', 'button_secondary_text',
        'footer_bg', 'footer_text', 'font_heading', 'font_body', 'font_size_base',
        'border_radius', 'spacing_unit', 'container_max_width', 'products_per_row'
    ];
    
    foreach ($allowedSettings as $key) {
        $currentSettings[$key] = getSetting($pdo, $key, '');
    }
    
    // Criar backup da versao atual
    $backupName = 'Antes de restaurar ' . date('d/m/Y H:i');
    $stmt = $pdo->prepare("INSERT INTO theme_versions (name, settings_data) VALUES (?, ?)");
    $stmt->execute([$backupName, json_encode($currentSettings)]);
    
    // Restaurar configuracoes
    foreach ($settings as $key => $value) {
        if (in_array($key, $allowedSettings)) {
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Versao restaurada com sucesso'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error restoring theme version: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao restaurar versao'
    ]);
}
