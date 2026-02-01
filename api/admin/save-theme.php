<?php
/**
 * API para salvar configuracoes do tema
 */
session_start();
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

if (!isset($input['theme']) || !is_array($input['theme'])) {
    echo json_encode(['success' => false, 'error' => 'Dados do tema invalidos']);
    exit;
}

$themeData = $input['theme'];
$createVersion = isset($input['create_version']) && $input['create_version'] === true;

// Lista de configuracoes permitidas
$allowedSettings = [
    'color_primary', 'color_secondary', 'color_accent', 'color_background',
    'color_text', 'color_text_muted', 'header_bg', 'header_text', 'nav_bg',
    'card_bg', 'card_border', 'card_hover_bg', 'button_primary_bg',
    'button_primary_text', 'button_secondary_bg', 'button_secondary_text',
    'footer_bg', 'footer_text', 'font_heading', 'font_body', 'font_size_base',
    'border_radius', 'spacing_unit', 'container_max_width', 'products_per_row'
];

try {
    $pdo->beginTransaction();
    
    // Criar versao antes de salvar (se solicitado)
    if ($createVersion) {
        // Criar tabela de versoes se nao existir
        $pdo->exec("CREATE TABLE IF NOT EXISTS theme_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            settings_data LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Buscar configuracoes atuais para backup
        $currentSettings = [];
        foreach ($allowedSettings as $key) {
            $currentSettings[$key] = getSetting($pdo, $key, '');
        }
        
        // Inserir versao
        $versionName = 'Backup ' . date('d/m/Y H:i');
        $stmt = $pdo->prepare("INSERT INTO theme_versions (name, settings_data) VALUES (?, ?)");
        $stmt->execute([$versionName, json_encode($currentSettings)]);
        
        // Manter apenas as ultimas 20 versoes
        $pdo->exec("DELETE FROM theme_versions WHERE id NOT IN (
            SELECT id FROM (SELECT id FROM theme_versions ORDER BY created_at DESC LIMIT 20) AS t
        )");
    }
    
    // Salvar novas configuracoes
    foreach ($themeData as $key => $value) {
        if (in_array($key, $allowedSettings)) {
            $value = is_string($value) ? trim($value) : $value;
            
            // Verificar se existe
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
        'message' => 'Tema salvo com sucesso'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error saving theme: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar tema: ' . $e->getMessage()
    ]);
}
