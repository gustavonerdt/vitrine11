<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
startSecureSession();

if (!isAdmin()) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Método não permitido']));
}

if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Erro no upload do arquivo']));
}

$file = $_FILES['banner'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP']));
}

// Validate file size (5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 5MB']));
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../../public/uploads/banners/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'inactive_banner_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo']));
}

// Generate URL
$url = APP_URL . '/public/uploads/banners/' . $filename;

// Delete old banner if exists
$oldBanner = getSetting($pdo, 'inactive_banner_url', '');
if ($oldBanner && strpos($oldBanner, '/public/uploads/banners/') !== false) {
    $oldPath = __DIR__ . '/../../' . str_replace(APP_URL . '/', '', $oldBanner);
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

// Save banner URL to settings
updateSetting($pdo, 'inactive_banner_url', $url);

logActivity($pdo, $_SESSION['user_id'] ?? 0, 'banner_uploaded', "Banner de ativação atualizado");

echo json_encode([
    'success' => true,
    'message' => 'Banner enviado com sucesso!',
    'url' => $url
]);
?>

