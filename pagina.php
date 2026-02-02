<?php
/**
 * Visualizador de paginas customizadas
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: ' . APP_URL);
    exit;
}

// Fetch page
try {
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $page = null;
}

if (!$page) {
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        include __DIR__ . '/404.php';
    } else {
        echo '<!DOCTYPE html><html><head><title>Pagina nao encontrada</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>404</h1><p>Pagina nao encontrada</p><a href="' . APP_URL . '">Voltar para inicio</a></body></html>';
    }
    exit;
}

// Get site settings
$siteName = getSetting($pdo, 'site_name', APP_NAME);
$siteColors = json_decode(getSetting($pdo, 'site_colors', '{}'), true) ?: [];
$primaryColor = $siteColors['primary'] ?? '#d4af37';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['title']); ?> - <?php echo $siteName; ?></title>
    <?php if (!empty($page['meta_description'])): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page['meta_description']); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $primaryColor; ?>;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #1a1a1a; }
        
        /* Base Page Styles */
        .page-container { min-height: 100vh; }
        
        /* Custom CSS from user */
        <?php echo $page['css_content']; ?>
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <main class="page-container">
        <?php echo $page['html_content']; ?>
    </main>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script>
    <?php echo $page['js_content']; ?>
    </script>
</body>
</html>
