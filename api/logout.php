<?php
/**
 * API de Logout
 * Encerra a sessão do usuário
 */

require_once __DIR__ . '/../config.php';

// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log do logout
if (isset($_SESSION['user_email'])) {
    error_log("Logout - Usuário: " . $_SESSION['user_email'] . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - Time: " . date('Y-m-d H:i:s'));
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se é desejado matar a sessão, também delete o cookie de sessão.
// Nota: Isto destruirá a sessão, e não apenas os dados da sessão!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão
session_destroy();

// Redirecionar para a página de login do admin ou home
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/admin/login.php';
header('Location: ' . APP_URL . $redirect);
exit;
