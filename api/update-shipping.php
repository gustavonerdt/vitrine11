<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['checkout_data'])) {
    echo json_encode(['success' => false, 'error' => 'Dados de checkout nao encontrados']);
    exit;
}

$shipping_price = floatval($input['shipping_price'] ?? 0);
$shipping_method = trim($input['shipping_method'] ?? '');

$_SESSION['checkout_data']['shipping_price'] = $shipping_price;
$_SESSION['checkout_data']['shipping_method'] = $shipping_method;

echo json_encode([
    'success' => true, 
    'message' => 'Frete atualizado',
    'shipping_price' => $shipping_price,
    'shipping_method' => $shipping_method
]);
