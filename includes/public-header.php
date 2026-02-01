<?php
// includes/public-header.php - Header público (sem login)
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}
$appName = getSetting($pdo, 'app_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo htmlspecialchars($appName); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php 
    $faviconUrl = getSetting($pdo, 'favicon_url', LOGO_URL);
    if (!empty($faviconUrl)) {
        if (!preg_match('/^https?:\/\//i', $faviconUrl)) {
            $faviconUrl = rtrim(APP_URL, '/') . '/' . ltrim($faviconUrl, '/');
        }
    } else {
        $faviconUrl = LOGO_URL;
    }
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" type="image/png">
    <?php include __DIR__ . '/dynamic-colors.php'; ?>
    <script src="<?php echo APP_URL; ?>/assets/js/tracking.js"></script>
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">
    <script>window.APP_URL = '<?php echo APP_URL; ?>';</script>
    
    <?php 
    // Incluir popup de recuperacao de carrinho abandonado
    include __DIR__ . '/abandoned-cart-popup.php';
    ?>
	    
	    <?php 
	    // Verificar se música está ativada - ler diretamente do .env
	    $musicEnabledEnv = function_exists('env') ? env('FEATURE_MUSIC_ENABLED', '1') : '1';
	    $musicEnabled = in_array(strtolower($musicEnabledEnv), ['1', 'true', 'yes', 'on']) ? 1 : 0;
	    $musicIsActive = (int)getSetting($pdo, 'music_is_active', 1);
	    $musicFilePath = getSetting($pdo, 'music_file_path', APP_URL . '/assets/musica.mp3');
	    
	    // Só carregar música se estiver ativada
	    if ($musicEnabled === 1 && $musicIsActive === 1): 
	    ?>
	    <!-- ===== PLAYER DE MÃSICA SIMPLES - FUNCIONA 100% ===== -->
	    <script>
	        // Definir URL ANTES de carregar o player
	        window.MUSIC_URL = '<?php echo htmlspecialchars($musicFilePath); ?>';
	    </script>
	    <script src="<?php echo APP_URL; ?>/assets/js/music-player-simples.js"></script>
	    <!-- ===== FIM PLAYER ===== -->
	    <?php endif; ?>
	    
	    <main class="public-main">

<style>
.public-header {
    background: #1F1F1F;
    border-bottom: 1px solid #2A2A2A;
    padding: 1rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo-link {
    display: flex;
    align-items: center;
}

.header-logo {
    max-width: 200px;
    max-height: 60px;
    object-fit: contain;
}

.public-main {
    min-height: calc(100vh - 80px);
}
</style>

