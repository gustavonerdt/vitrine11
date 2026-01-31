<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in (admin or regular user)
if (!isLoggedIn()) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado. Faça login primeiro.']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Método não permitido']));
}

// Debug logging
error_log("=== UPLOAD IMAGE DEBUG ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("FILES: " . json_encode($_FILES));
error_log("POST: " . json_encode($_POST));
error_log("Folder: " . ($_POST['folder'] ?? 'not set'));

if (!isset($_FILES['image'])) {
    error_log("ERROR: No image file in FILES");
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Nenhum arquivo foi enviado.']));
}

if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Erro no upload do arquivo';
    $errorCode = $_FILES['image']['error'];
    error_log("ERROR: Upload error code: $errorCode");
    
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg = 'Arquivo muito grande. Tamanho máximo: 15MB';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg = 'Upload parcial. Tente novamente.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg = 'Nenhum arquivo foi enviado.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMsg = 'Diretório temporário não encontrado.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMsg = 'Erro ao escrever arquivo. Verifique permissões.';
            break;
        default:
            $errorMsg = 'Erro desconhecido no upload (código: ' . $errorCode . ')';
    }
    error_log("ERROR: $errorMsg");
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => $errorMsg]));
}

$file = $_FILES['image'];
$folder = sanitizeInput($_POST['folder'] ?? 'general');

// Normalize folder path (replace backslashes with forward slashes, remove leading/trailing slashes)
$folder = str_replace('\\', '/', $folder);
$folder = trim($folder, '/');

// Validate file type - images or audio (if folder is 'music')
$isMusicFolder = ($folder === 'music');
$allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml', 'image/avif'];
$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif'];
$allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/x-m4a'];
$allowedAudioExtensions = ['mp3', 'wav', 'ogg', 'webm', 'm4a'];

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($isMusicFolder) {
    // For music folder, accept audio files
    if (!in_array($file['type'], $allowedAudioTypes) && !in_array($extension, $allowedAudioExtensions)) {
        error_log("ERROR: Invalid file type. Type: " . $file['type'] . ", Extension: $extension");
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas arquivos de áudio são aceitos (MP3, WAV, OGG, WEBM, M4A)']));
    }
} else {
    // For other folders, accept images only
    if (!in_array($file['type'], $allowedImageTypes) && !in_array($extension, $allowedImageExtensions)) {
        error_log("ERROR: Invalid file type. Type: " . $file['type'] . ", Extension: $extension");
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas imagens são aceitas (JPG, PNG, GIF, WEBP, BMP, SVG, AVIF)']));
    }
}

// Validate file size - 15MB for all users
$maxSize = 15 * 1024 * 1024; // 15MB
if ($file['size'] > $maxSize) {
    error_log("ERROR: File too large. Size: " . $file['size'] . " bytes");
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => "Arquivo muito grande. Tamanho máximo: 15MB"]));
}

// Create uploads directory structure
$baseUploadDir = __DIR__ . '/../uploads/';
$uploadDir = $baseUploadDir . $folder . '/';

// Normalize path separators for Windows - use realpath to resolve .. and normalize separators
$baseUploadDir = realpath($baseUploadDir);
if ($baseUploadDir === false) {
    // If realpath fails, manually normalize
    $baseUploadDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . '/../uploads/');
    $baseUploadDir = rtrim($baseUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
} else {
    $baseUploadDir = rtrim($baseUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

// Build upload directory path with normalized separators
$folderParts = explode('/', $folder);
$uploadDir = $baseUploadDir . implode(DIRECTORY_SEPARATOR, $folderParts) . DIRECTORY_SEPARATOR;

error_log("Base upload dir: $baseUploadDir");
error_log("Upload dir: $uploadDir");

// Create base uploads directory if it doesn't exist
if (!is_dir($baseUploadDir)) {
    error_log("Creating base upload directory: $baseUploadDir");
    if (!@mkdir($baseUploadDir, 0755, true)) {
        $error = error_get_last();
        error_log("ERROR: Failed to create base directory: " . ($error ? $error['message'] : 'Unknown'));
        http_response_code(500);
        die(json_encode([
            'success' => false, 
            'message' => 'Erro ao criar diretório base de uploads. Verifique as permissões.'
        ]));
    }
}

// Create folder-specific directory
if (!is_dir($uploadDir)) {
    error_log("Creating directory: $uploadDir");
    if (!@mkdir($uploadDir, 0755, true)) {
        $error = error_get_last();
        error_log("ERROR: Failed to create directory: $uploadDir. Error: " . ($error ? $error['message'] : 'Unknown'));
        http_response_code(500);
        die(json_encode([
            'success' => false, 
            'message' => 'Erro ao criar diretório de upload. Verifique as permissões da pasta uploads/' . $folder . '. Erro: ' . ($error ? $error['message'] : 'Desconhecido')
        ]));
    }
    error_log("Directory created successfully: $uploadDir");
}

// Verify directory is writable
if (!is_writable($uploadDir)) {
    error_log("ERROR: Directory not writable: $uploadDir");
    http_response_code(500);
    die(json_encode([
        'success' => false, 
        'message' => 'Diretório de upload não tem permissão de escrita. Verifique as permissões.'
    ]));
}

// Generate unique filename - use only the last part of folder path for filename prefix
$folderParts = explode('/', $folder);
$filenamePrefix = end($folderParts); // Get last part (e.g., 'variants' from 'products/variants')
$filename = $filenamePrefix . '_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

error_log("Generated filename: $filename");
error_log("Full filepath: $filepath");

// Move uploaded file
error_log("Moving file from: " . $file['tmp_name'] . " to: $filepath");
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    $error = error_get_last();
    error_log("ERROR: Failed to move file. Error: " . ($error ? $error['message'] : 'Unknown'));
    error_log("Source exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
    error_log("Destination writable: " . (is_writable($uploadDir) ? 'YES' : 'NO'));
    http_response_code(500);
    die(json_encode([
        'success' => false, 
        'message' => 'Erro ao salvar arquivo. Verifique as permissões do diretório. ' . ($error ? $error['message'] : '')
    ]));
}
error_log("File moved successfully to: $filepath");

// Generate relative path only (not full URL)
// Example: uploads/banners/banners_1766856501_69501735298e8.png
$baseUploadDirNormalized = str_replace('\\', '/', $baseUploadDir);
$filepathNormalized = str_replace('\\', '/', $filepath);

// Extract path relative to uploads directory
if (strpos($filepathNormalized, $baseUploadDirNormalized) === 0) {
    // Remove base upload directory from filepath
    $relativePath = substr($filepathNormalized, strlen($baseUploadDirNormalized));
    // Remove leading slash and add 'uploads/' prefix
    $relativePath = ltrim($relativePath, '/');
    $relativeUrl = 'uploads/' . $relativePath;
} else {
    // Fallback: try to find 'uploads/' in path
    $uploadsPos = strpos($filepathNormalized, 'uploads/');
    if ($uploadsPos !== false) {
        // Get everything after 'uploads/'
        $relativePath = substr($filepathNormalized, $uploadsPos + strlen('uploads/'));
        $relativeUrl = 'uploads/' . ltrim($relativePath, '/');
    } else {
        // Last resort: use folder and filename with uploads/ prefix
        $relativeUrl = 'uploads/' . $folder . '/' . $filename;
    }
}

// Normalize: remove double slashes and ensure format is uploads/folder/filename
$relativeUrl = str_replace('//', '/', $relativeUrl);
$relativeUrl = rtrim($relativeUrl, '/');

error_log("Generated relative URL: $relativeUrl from filepath: $filepath");

// Log activity
if (function_exists('logActivity')) {
    logActivity($pdo, $_SESSION['user_id'] ?? 0, 'image_uploaded', "Imagem enviada: $filename (pasta: $folder)");
}

error_log("Upload successful. Relative URL: $relativeUrl, Filename: $filename");
error_log("=== END UPLOAD IMAGE DEBUG ===");

echo json_encode([
    'success' => true,
    'message' => 'Imagem enviada com sucesso!',
    'url' => $relativeUrl, // Return only relative path
    'filename' => $filename
]);
?>
