<?php
/**
 * API de Login Administrativo
 * Processa autenticação de administradores
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

    // Rate limiting: max 5 tentativas de login por minuto (protecao contra brute force)
    $rateLimitResult = checkRateLimit($pdo, 'admin_login', 5, 60);
    if (!$rateLimitResult['allowed']) {
        throw new Exception('Muitas tentativas de login. Aguarde ' . 60 . ' segundos e tente novamente.');
    }

    // Log da requisição
    error_log("Admin Login Attempt - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - Time: " . date('Y-m-d H:i:s'));

    // Obter dados do formulário
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $csrf_token = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

    // Validar CSRF
    if (!verifyCsrfToken($csrf_token)) {
        error_log("Admin Login - CSRF Token inválido - Email: " . $email);
        throw new Exception('Token de segurança inválido. Recarregue a página e tente novamente.');
    }

    // Validar campos obrigatórios
    if (empty($email)) {
        error_log("Admin Login - Email vazio");
        throw new Exception('Email é obrigatório');
    }

    if (empty($password)) {
        error_log("Admin Login - Senha vazia - Email: " . $email);
        throw new Exception('Senha é obrigatória');
    }

    // Validar formato do email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Admin Login - Email inválido: " . $email);
        throw new Exception('Email inválido');
    }

    // Buscar usuário no banco
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("Admin Login - Usuário não encontrado: " . $email);
            throw new Exception('Email ou senha incorretos');
        }

        // Verificar se está ativo
        if (!$user['is_active']) {
            error_log("Admin Login - Usuário inativo: " . $email);
            throw new Exception('Sua conta está desativada. Entre em contato com o administrador.');
        }

        // Verificar se é admin
        if ($user['role'] !== 'admin') {
            error_log("Admin Login - Tentativa de login não-admin: " . $email . " (Role: " . $user['role'] . ")");
            throw new Exception('Acesso negado. Apenas administradores podem acessar este painel.');
        }

        // Verificar senha
        if (!verifyPassword($password, $user['password'])) {
            error_log("Admin Login - Senha incorreta: " . $email);
            throw new Exception('Email ou senha incorretos');
        }

        // Login bem-sucedido
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['is_active'] = $user['is_active'];
        $_SESSION['logged_in_at'] = time();

        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);

        error_log("Admin Login - Sucesso: " . $email . " (ID: " . $user['id'] . ")");

        $response['success'] = true;
        $response['message'] = 'Login realizado com sucesso';
        $response['redirect'] = 'dashboard.php';

    } catch (PDOException $e) {
        error_log("Admin Login - Erro no banco de dados: " . $e->getMessage());
        error_log("Admin Login - Stack trace: " . $e->getTraceAsString());
        throw new Exception('Erro ao conectar com o banco de dados. Tente novamente mais tarde.');
    }

} catch (Exception $e) {
    error_log("Admin Login - Exception: " . $e->getMessage());
    error_log("Admin Login - Stack trace: " . $e->getTraceAsString());
    $response['message'] = $e->getMessage();
} catch (Error $e) {
    error_log("Admin Login - Fatal Error: " . $e->getMessage());
    error_log("Admin Login - File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Admin Login - Stack trace: " . $e->getTraceAsString());
    $response['message'] = 'Erro interno do servidor. Verifique os logs para mais detalhes.';
}

echo json_encode($response);
exit;
