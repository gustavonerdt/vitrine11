<?php
/**
 * Public Header - Frontend publico da vitrine
 * Links e CSS corrigidos
 */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}
$appName = function_exists('getSetting') && isset($pdo) ? getSetting($pdo, 'app_name', APP_NAME) : (defined('APP_NAME') ? APP_NAME : 'Vitrine');
$app_url = defined('APP_URL') ? APP_URL : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?><?php echo htmlspecialchars($appName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $app_url; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $app_url; ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Swiper CSS para carrosseis -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <?php 
    $faviconUrl = function_exists('getSetting') && isset($pdo) ? getSetting($pdo, 'favicon_url', (defined('LOGO_URL') ? LOGO_URL : '')) : (defined('LOGO_URL') ? LOGO_URL : '');
    if (!empty($faviconUrl)) {
        if (!preg_match('/^https?:\/\//i', $faviconUrl)) {
            $faviconUrl = rtrim($app_url, '/') . '/' . ltrim($faviconUrl, '/');
        }
    } else {
        $faviconUrl = defined('LOGO_URL') ? LOGO_URL : '';
    }
    ?>
    <?php if (!empty($faviconUrl)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" type="image/png">
    <?php endif; ?>
    <?php if (file_exists(__DIR__ . '/dynamic-colors.php')): ?>
    <?php include __DIR__ . '/dynamic-colors.php'; ?>
    <?php endif; ?>
    <script src="<?php echo $app_url; ?>/assets/js/tracking.js"></script>
</head>
<body class="<?php echo isset($bodyClass) ? htmlspecialchars($bodyClass) : ''; ?>">
    <script>window.APP_URL = '<?php echo $app_url; ?>';</script>
    
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

