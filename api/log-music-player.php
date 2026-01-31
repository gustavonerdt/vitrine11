<?php
// api/log-music-player.php
// Recebe logs do player de música e salva em arquivo

header('Content-Type: application/json');

// Permitir CORS se necessário
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Apenas aceitar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Ler dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Preparar log
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/music-player.log';

$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
$level = $data['level'] ?? 'INFO';
$message = $data['message'] ?? 'No message';
$logData = $data['data'] ?? null;
$userAgent = $data['userAgent'] ?? 'Unknown';
$url = $data['url'] ?? 'Unknown';

// Formatar log
$logEntry = sprintf(
    "[%s] [%s] %s | URL: %s | UserAgent: %s",
    $timestamp,
    $level,
    $message,
    $url,
    substr($userAgent, 0, 100)
);

if ($logData) {
    $logEntry .= " | Data: " . json_encode($logData, JSON_UNESCAPED_UNICODE);
}

$logEntry .= PHP_EOL;

// Salvar no arquivo
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Resposta de sucesso
echo json_encode([
    'success' => true,
    'logged' => true
]);

