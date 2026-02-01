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

// Verificar se ha dados de checkout
if (!isset($_SESSION['checkout_data'])) {
    echo json_encode(['success' => false, 'error' => 'Dados de checkout nao encontrados']);
    exit;
}

// Atualizar endereco
$_SESSION['checkout_data']['cep'] = trim($_POST['cep'] ?? '');
$_SESSION['checkout_data']['street'] = trim($_POST['street'] ?? '');
$_SESSION['checkout_data']['number'] = trim($_POST['number'] ?? '');
$_SESSION['checkout_data']['complement'] = trim($_POST['complement'] ?? '');
$_SESSION['checkout_data']['neighborhood'] = trim($_POST['neighborhood'] ?? '');
$_SESSION['checkout_data']['city'] = trim($_POST['city'] ?? '');
$_SESSION['checkout_data']['state'] = trim($_POST['state'] ?? '');

echo json_encode(['success' => true, 'message' => 'Endereco atualizado com sucesso']);
