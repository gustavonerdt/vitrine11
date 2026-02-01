<?php
// Vitrine Independente - Main Configuration
// Configuração simplificada para vitrine de produtos

// ============================================
// ENVIRONMENT VARIABLES HELPER
// ============================================
// Load .env file if it exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            if (!empty($key)) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        // Try to get from $_ENV first
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        // Try to get from $_SERVER
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        // Return default
        return $default;
    }
}

// ============================================
// ERROR HANDLING - Set first for debugging
// ============================================
// IMPORTANTE: Em producao, SHOW_ERRORS deve ser false
// Configure via .env: SHOW_ERRORS=false
$showErrors = filter_var(env('SHOW_ERRORS', 'false'), FILTER_VALIDATE_BOOLEAN);
define('SHOW_ERRORS', $showErrors);

if (SHOW_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ============================================
// DYNAMIC DOMAIN DETECTION
// ============================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$domain = $_SERVER['HTTP_HOST'] ?? 'vitrine.w15imports.com.br';

// ============================================
// ALLOWED DOMAIN VALIDATION
// ============================================
define('ALLOWED_DOMAIN', 'vitrine.w15imports.com.br');
// Permitir desabilitar validação de domínio via variável de ambiente (útil para desenvolvimento local)
// Defina DISABLE_DOMAIN_CHECK=true no .env para desabilitar a validação
$disableDomainCheck = filter_var(env('DISABLE_DOMAIN_CHECK', 'false'), FILTER_VALIDATE_BOOLEAN);

// Verificar se o domínio é permitido (a menos que esteja desabilitado)
if (!$disableDomainCheck && $domain !== ALLOWED_DOMAIN) {
    http_response_code(403);
    die('<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .domain {
            color: #667eea;
            font-weight: bold;
            font-family: monospace;
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin: 10px 0;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>Acesso Negado</h1>
        <p>Este sistema está configurado para funcionar apenas no domínio:</p>
        <div class="domain">' . htmlspecialchars(ALLOWED_DOMAIN) . '</div>
        <p style="margin-top: 20px; font-size: 14px; color: #999;">
            Domínio atual: <strong>' . htmlspecialchars($domain) . '</strong>
        </p>
    </div>
</body>
</html>');
}

if (!defined('APP_BASE_URL_OVERRIDE')) {
    define('APP_BASE_URL_OVERRIDE', '');
}

if (!empty(APP_BASE_URL_OVERRIDE)) {
    $base_url = rtrim(APP_BASE_URL_OVERRIDE, '/');
} else {
    $base_url = $protocol . '://' . $domain;
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
// IMPORTANTE: As credenciais do banco de dados são carregadas do arquivo .env
// Configure as variáveis DB_HOST, DB_NAME, DB_USER e DB_PASS no arquivo .env
// Os valores padrão abaixo são usados apenas como fallback se não estiverem no .env
// Se estiver usando XAMPP, geralmente é: host=localhost, user=root, pass=''
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'naipersidependente')); // Nome do banco de dados
define('DB_USER', env('DB_USER', 'root')); // Usuário do banco de dados
define('DB_PASS', env('DB_PASS', '')); // Senha do banco de dados

// Application - Load from .env file first, then database
$appName = env('APP_NAME', null);
$logoUrl = env('LOGO_URL', null);
$logoWidth = env('LOGO_WIDTH', null);
$logoHeight = env('LOGO_HEIGHT', null);

// Logo & Branding - Load from .env file
define('APP_KEY', env('APP_KEY', 'naipers_club_2024_luxury_perfume_marketplace'));

// ============================================
// GOOGLE OAUTH CONFIGURATION (Opcional)
// ============================================
$googleClientId = env('GOOGLE_OAUTH_CLIENT_ID', null);
$googleClientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET', null);

// WhatsApp Integration (será carregado do banco de dados)
define('WHATSAPP_DEFAULT_NUMBER', '5511987654321');
define('WHATSAPP_DEFAULT_MESSAGE', 'Olá! Quero reservar: ');

// VIP Configuration
define('VIP_FEATURED_PRODUCTS_COUNT', 10);
define('ALL_PRODUCTS_COUNT', 200);

// ============================================
// FUNCIONALIDADES UPSELL
// ============================================
// Banners/Carrossel: Configure no .env com FEATURE_BANNERS_ENABLED=1 (ativado) ou FEATURE_BANNERS_ENABLED=0 (desativado)
$bannersEnabledEnv = env('FEATURE_BANNERS_ENABLED', '1');
// Aceita: 1, true, yes, on (ativado) ou 0, false, no, off (desativado)
$bannersEnabled = in_array(strtolower($bannersEnabledEnv), ['1', 'true', 'yes', 'on']) ? 1 : 0;
define('FEATURE_BANNERS_ENABLED', $bannersEnabled);

// ============================================
// Música de Fundo: Configure no .env com FEATURE_MUSIC_ENABLED=1 (ativado) ou FEATURE_MUSIC_ENABLED=0 (desativado)
$musicEnabledEnv = env('FEATURE_MUSIC_ENABLED', '1');
// Aceita: 1, true, yes, on (ativado) ou 0, false, no, off (desativado)
$musicEnabled = in_array(strtolower($musicEnabledEnv), ['1', 'true', 'yes', 'on']) ? 1 : 0;
define('FEATURE_MUSIC_ENABLED', $musicEnabled);

// ============================================
// LINKS DE COMPRAS
// ============================================
// Link do checkout para adquirir funcionalidade de Banners
// Configure no .env: BANNERS_CHECKOUT_LINK=https://seu-link-de-checkout.com
define('BANNERS_CHECKOUT_LINK', env('BANNERS_CHECKOUT_LINK', ''));

// Link do checkout para adquirir funcionalidade de Música
// Configure no .env: MUSIC_CHECKOUT_LINK=https://seu-link-de-checkout.com
define('MUSIC_CHECKOUT_LINK', env('MUSIC_CHECKOUT_LINK', ''));

// Security
define('BCRYPT_COST', 12);
define('SESSION_TIMEOUT', 1800);
define('MAX_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900);

// Shipping defaults (para calculos de frete)
define('SHIPPING_DEFAULT_WEIGHT', 0.3);     // Peso padrao em kg
define('SHIPPING_DEFAULT_HEIGHT', 15);       // Altura padrao em cm
define('SHIPPING_DEFAULT_WIDTH', 8);         // Largura padrao em cm
define('SHIPPING_DEFAULT_LENGTH', 8);        // Comprimento padrao em cm
define('SHIPPING_MIN_WEIGHT', 0.1);          // Peso minimo em kg
define('SHIPPING_MIN_HEIGHT', 2);            // Altura minima em cm
define('SHIPPING_MIN_WIDTH', 11);            // Largura minima em cm
define('SHIPPING_MIN_LENGTH', 16);           // Comprimento minimo em cm
define('SHIPPING_FALLBACK_PRICE', 25.00);    // Preco de frete padrao quando APIs falham
define('SHIPPING_FALLBACK_DAYS', 10);        // Prazo padrao em dias uteis

// Admin Settings - Load from .env file
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@naipersclub.com'));

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Load settings from database
    // App Name and Logo
    if ($appName === null || $logoUrl === null || $logoWidth === null || $logoHeight === null) {
        try {
            if ($appName === null) {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt->execute(['app_name']);
                $result = $stmt->fetch();
                if ($result && !empty($result['setting_value'])) {
                    $appName = $result['setting_value'];
                }
            }
            
            if ($logoUrl === null) {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt->execute(['logo_url']);
                $result = $stmt->fetch();
                if ($result && !empty($result['setting_value'])) {
                    $logoUrl = $result['setting_value'];
                }
            }
            
            if ($logoWidth === null) {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt->execute(['logo_width']);
                $result = $stmt->fetch();
                if ($result && !empty($result['setting_value'])) {
                    $logoWidth = $result['setting_value'];
                }
            }
            
            if ($logoHeight === null) {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt->execute(['logo_height']);
                $result = $stmt->fetch();
                if ($result && !empty($result['setting_value'])) {
                    $logoHeight = $result['setting_value'];
                }
            }
        } catch (PDOException $e) {
            // Settings table might not exist yet, use defaults
        }
    }
} catch (PDOException $e) {
    if (SHOW_ERRORS) {
        die("Erro de conexão ao banco de dados: " . $e->getMessage());
    } else {
        die("Erro de conexão ao banco de dados. Por favor, tente novamente mais tarde.");
    }
}

// Define constants with final values (priority: .env > database > default)
define('APP_NAME', $appName ?? 'Vitrine Independente');
define('APP_URL', $base_url);
define('APP_ENV', env('APP_ENV', 'production'));
define('LOGO_URL', $logoUrl ?? '/assets/logo-vitrine-independente.png');
define('LOGO_WIDTH', $logoWidth ?? 'auto');
define('LOGO_HEIGHT', $logoHeight ?? 'auto');
// Google OAuth (opcional - não necessário para vitrine simples)
define('GOOGLE_OAUTH_CLIENT_ID', $googleClientId ?? '');
define('GOOGLE_OAUTH_CLIENT_SECRET', $googleClientSecret ?? '');

// ============================================
// SESSION CONFIGURATION
// ============================================
if (session_status() == PHP_SESSION_NONE) {
    $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'secure' => $isHTTPS,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ============================================
// Error logging setup
// ============================================
ini_set('log_errors', 1);
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$errorLogPath = $logsDir . '/error.log';
if (!file_exists($errorLogPath)) {
    @touch($errorLogPath);
}
ini_set('error_log', $errorLogPath);
?>
