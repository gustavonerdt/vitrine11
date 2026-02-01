<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Configurações';
$page_subtitle = 'Ajustes básicos do sistema';

$success = '';
$error = '';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token inválido.";
    } else {
        try {
            // Calcular tamanho da logo a partir do slider
            $logoSize = intval($_POST['logo_size'] ?? 40);
            $logoSizePx = $logoSize . 'px';
            
            $settingsToSave = [
                // Sistema Básico
                'app_name' => trim($_POST['app_name'] ?? ''),
                'logo_url' => trim($_POST['logo_url'] ?? ''),
                'logo_width' => $logoSizePx,
                'logo_height' => $logoSizePx,
                // WhatsApp
                'whatsapp_number' => $_POST['whatsapp_number'] ?? '',
                'default_message' => $_POST['whatsapp_message'] ?? '',
                // Cores do Sistema
                'color_primary' => trim($_POST['color_primary'] ?? '#C7A333'),
                'color_secondary' => trim($_POST['color_secondary'] ?? '#D4B84A'),
                'color_accent' => trim($_POST['color_accent'] ?? '#E0C966'),
                'color_background' => trim($_POST['color_background'] ?? '#0F0F0F'),
                'color_surface' => trim($_POST['color_surface'] ?? '#1F1F1F'),
                'color_text' => trim($_POST['color_text'] ?? '#E0E0E0'),
                'color_text_muted' => trim($_POST['color_text_muted'] ?? '#B3B3B3'),
                'color_sidebar_logo_text' => trim($_POST['color_sidebar_logo_text'] ?? '#FFFFFF'),
                // Cores do Carrossel
                'carousel_pagination_color' => trim($_POST['carousel_pagination_color'] ?? '#FFFFFF'),
                'carousel_arrow_color' => trim($_POST['carousel_arrow_color'] ?? '#FFFFFF'),
                // Blur da Logo no Placeholder
                'product_placeholder_logo_blur' => intval($_POST['product_placeholder_logo_blur'] ?? 20),
                'favicon_url' => trim($_POST['favicon_url'] ?? ''),
                // Mercado Pago
                'mercado_pago_access_token' => trim($_POST['mercado_pago_access_token'] ?? ''),
                'mercado_pago_public_key' => trim($_POST['mercado_pago_public_key'] ?? ''),
                'mercado_pago_access_token_test' => trim($_POST['mercado_pago_access_token_test'] ?? ''),
                'mercado_pago_public_key_test' => trim($_POST['mercado_pago_public_key_test'] ?? ''),
                'mercado_pago_environment' => trim($_POST['mercado_pago_environment'] ?? 'test'),
                // Frete
                'correios_cep_origem' => trim($_POST['correios_cep_origem'] ?? ''),
                'melhor_envio_token' => trim($_POST['melhor_envio_token'] ?? ''),
                'frete_gratis_valor_minimo' => trim($_POST['frete_gratis_valor_minimo'] ?? '0'),
                // WhatsApp Flutuante
                'whatsapp_float_enabled' => isset($_POST['whatsapp_float_enabled']) ? '1' : '0',
                'whatsapp_float_number' => trim($_POST['whatsapp_float_number'] ?? ''),
                'whatsapp_float_message' => trim($_POST['whatsapp_float_message'] ?? 'Olá! Preciso de ajuda.'),
            ];

            foreach ($settingsToSave as $key => $value) {
                updateSetting($pdo, $key, $value);
            }

            $success = "Configurações salvas com sucesso!";
            logActivity($pdo, $_SESSION['user_id'], 'Settings Updated', "Atualizou configurações globais");

        } catch (Exception $e) {
            $error = "Erro ao salvar: " . $e->getMessage();
            error_log("Settings save error: " . $e->getMessage());
        }
    }
}

// Load current settings
// Extrair valor numérico do tamanho da logo para o slider
$logoWidth = getSetting($pdo, 'logo_width', defined('LOGO_WIDTH') && LOGO_WIDTH !== 'auto' ? LOGO_WIDTH : '40px');
$logoSizeValue = 40; // padrão
if (preg_match('/(\d+)/', $logoWidth, $matches)) {
    $logoSizeValue = intval($matches[1]);
}

$currentSettings = [
    'app_name' => getSetting($pdo, 'app_name', defined('APP_NAME') ? APP_NAME : 'Vitrine Independente'),
    'logo_url' => getSetting($pdo, 'logo_url', defined('LOGO_URL') ? LOGO_URL : ''),
    'logo_size' => $logoSizeValue,
    'whatsapp_number' => getSetting($pdo, 'whatsapp_number', '5511999999999'),
    'whatsapp_message' => getSetting($pdo, 'default_message', 'Olá! Quero reservar: '),
    'color_primary' => getSetting($pdo, 'color_primary', '#C7A333'),
    'color_secondary' => getSetting($pdo, 'color_secondary', '#D4B84A'),
    'color_accent' => getSetting($pdo, 'color_accent', '#E0C966'),
    'color_background' => getSetting($pdo, 'color_background', '#0F0F0F'),
    'color_surface' => getSetting($pdo, 'color_surface', '#1F1F1F'),
    'color_text' => getSetting($pdo, 'color_text', '#E0E0E0'),
    'color_text_muted' => getSetting($pdo, 'color_text_muted', '#B3B3B3'),
    'color_sidebar_logo_text' => getSetting($pdo, 'color_sidebar_logo_text', '#FFFFFF'),
    'carousel_pagination_color' => getSetting($pdo, 'carousel_pagination_color', '#FFFFFF'),
    'carousel_arrow_color' => getSetting($pdo, 'carousel_arrow_color', '#FFFFFF'),
    'product_placeholder_logo_blur' => intval(getSetting($pdo, 'product_placeholder_logo_blur', 20)),
    'favicon_url' => getSetting($pdo, 'favicon_url', defined('LOGO_URL') ? LOGO_URL : ''),
    // Mercado Pago
    'mercado_pago_access_token' => getSetting($pdo, 'mercado_pago_access_token', ''),
    'mercado_pago_public_key' => getSetting($pdo, 'mercado_pago_public_key', ''),
    'mercado_pago_access_token_test' => getSetting($pdo, 'mercado_pago_access_token_test', ''),
    'mercado_pago_public_key_test' => getSetting($pdo, 'mercado_pago_public_key_test', ''),
    'mercado_pago_environment' => getSetting($pdo, 'mercado_pago_environment', 'test'),
    // Frete
    'correios_cep_origem' => getSetting($pdo, 'correios_cep_origem', ''),
    'melhor_envio_token' => getSetting($pdo, 'melhor_envio_token', ''),
    'frete_gratis_valor_minimo' => getSetting($pdo, 'frete_gratis_valor_minimo', '0'),
    // WhatsApp Flutuante
    'whatsapp_float_enabled' => getSetting($pdo, 'whatsapp_float_enabled', '1'),
    'whatsapp_float_number' => getSetting($pdo, 'whatsapp_float_number', ''),
    'whatsapp_float_message' => getSetting($pdo, 'whatsapp_float_message', 'Olá! Preciso de ajuda.'),
];

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/image-upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../includes/dynamic-colors.php'; ?>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-cog"></i> Configurações</h1>
                        <p>Ajustes básicos do sistema</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="settingsForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                    <!-- Seção: Identidade Visual -->
                    <div class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-paint-brush"></i> Identidade Visual</h2>
                            <p class="section-description">Configure o nome e a logo da sua vitrine. Essas informações aparecem em toda a vitrine pública.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <!-- Sistema Básico -->
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-info-circle"></i> Nome e Logo</h3>
                                    <span class="card-badge">Essencial</span>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-signature"></i> Nome do Sistema
                                        </label>
                                        <input type="text" name="app_name"
                                               value="<?php echo htmlspecialchars($currentSettings['app_name']); ?>"
                                               placeholder="Ex: Minha Loja de Perfumes">
                                        <small class="form-hint">
                                            <i class="fas fa-lightbulb"></i> <strong>Onde aparece:</strong> Título das páginas, menu superior, rodapé e em todos os lugares onde o nome da sua vitrine é exibido.
                                            <br><strong>Exemplo:</strong> Se você colocar "Perfumes Premium", esse será o nome que os visitantes verão.
                                        </small>
                                    </div>
                                    
<div class="form-group">
	                                        <label>
	                                            <i class="fas fa-image"></i> Logo do Sistema
	                                        </label>
	                                        <div id="logoUploadContainer"></div>
	                                        <small class="form-hint">
	                                            <i class="fas fa-lightbulb"></i> <strong>Como adicionar:</strong>
	                                            <br>• <strong>Arraste e solte</strong> uma imagem do seu computador
	                                            <br>• <strong>Ou clique</strong> para escolher um arquivo
	                                            <br>• <strong>Ou cole o link</strong> de uma imagem que já está na internet
	                                            <br><strong>Dica:</strong> Use imagens PNG ou JPG com fundo transparente para melhor resultado.
	                                        </small>
	                                    </div>

	                                    <div class="form-group">
	                                        <label>
	                                            <i class="fas fa-star"></i> Favicon do Sistema (Ícone da Aba)
	                                        </label>
	                                        <div id="faviconUploadContainer"></div>
	                                        <small class="form-hint">
	                                            <i class="fas fa-lightbulb"></i> <strong>O que é:</strong> O pequeno ícone que aparece na aba do navegador.
	                                            <br><strong>Dica:</strong> Use uma imagem quadrada (ex: 32x32 ou 64x64 pixels) para melhor resultado.
	                                        </small>
	                                    </div>
                                
                                <div class="form-group">
                                    <label for="logoSizeSlider">
                                        <i class="fas fa-arrows-alt"></i> Tamanho da Logo: <span id="logoSizeValue" style="color: var(--admin-accent); font-weight: 700;"><?php echo $currentSettings['logo_size']; ?>px</span>
                                    </label>
                                    <input type="range" 
                                           name="logo_size" 
                                           id="logoSizeSlider"
                                           min="20" 
                                           max="2000" 
                                           value="<?php echo $currentSettings['logo_size']; ?>"
                                           class="logo-size-slider">
                                    <div class="slider-labels">
                                        <span>20px (Pequeno)</span>
                                        <span>2000px (Muito Grande)</span>
                                    </div>
                                    <small class="form-hint">
                                        <i class="fas fa-lightbulb"></i> <strong>Como usar:</strong> Mova o controle deslizante para ajustar o tamanho. O preview abaixo mostra como ficará.
                                        <br><strong>Recomendação:</strong> Entre 100px e 300px para a maioria dos casos. Valores muito grandes podem ocupar muito espaço no menu.
                                    </small>
                                    
                                    <!-- Preview Area -->
                                    <div class="logo-preview-container" style="margin-top: 1.5rem; padding: 1.5rem; background: var(--admin-bg-secondary); border-radius: 12px; border: 1px solid var(--admin-border);">
                                        <label style="display: block; margin-bottom: 1rem; font-weight: 600; color: var(--admin-text-primary);">
                                            <i class="fas fa-eye"></i> Preview em Tempo Real
                                        </label>
                                        <div style="display: flex; align-items: center; justify-content: center; min-height: 200px; background: var(--admin-bg-primary); border-radius: 8px; padding: 2rem; border: 2px dashed var(--admin-border); position: relative; overflow: auto;">
                                            <div style="display: flex; align-items: center; justify-content: center; width: 100%; min-height: 150px;">
                                                <img src="<?php echo htmlspecialchars($currentSettings['logo_url'] ?: LOGO_URL); ?>" 
                                                     alt="Logo Preview" 
                                                     id="logoPreview"
                                                     style="width: <?php echo min($currentSettings['logo_size'], 600); ?>px; 
                                                            height: <?php echo min($currentSettings['logo_size'], 600); ?>px; 
                                                            max-width: 100%; 
                                                            max-height: 100%; 
                                                            object-fit: contain; 
                                                            transition: width 0.3s ease, height 0.3s ease;
                                                            display: block;">
                                            </div>
                                            <div style="position: absolute; bottom: 0.5rem; right: 0.5rem; background: rgba(0,0,0,0.7); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                                <?php echo $currentSettings['logo_size']; ?>px
                                            </div>
                                        </div>
                                        <small style="display: block; margin-top: 0.75rem; color: var(--admin-text-muted); text-align: center;">
                                            Esta é uma prévia de como a logo aparecerá no site
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                            </div>
                        </div>
                    </div>
                    </div>

                    <!-- Seção: WhatsApp -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fab fa-whatsapp"></i> Configurações do WhatsApp</h2>
                            <p class="section-description">Configure seu número do WhatsApp e a mensagem que será enviada quando alguém clicar em "Comprar".</p>
                        </div>
                        
                        <div class="settings-grid">
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fab fa-whatsapp"></i> WhatsApp</h3>
                                    <span class="card-badge">Essencial</span>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-phone"></i> Número do WhatsApp
                                        </label>
                                        <input type="text" name="whatsapp_number"
                                               value="<?php echo htmlspecialchars($currentSettings['whatsapp_number']); ?>"
                                               placeholder="5511999999999">
                                        <small class="form-hint">
                                            <i class="fas fa-lightbulb"></i> <strong>Formato correto:</strong> Apenas números, sem espaços, parênteses ou hífens.
                                            <br><strong>Exemplos práticos:</strong>
                                            <br>• Brasil: <code>5511999999999</code> (para (11) 99999-9999)
                                            <br>• Brasil: <code>5521987654321</code> (para (21) 98765-4321)
                                            <br>• Formato: <code>Código do País + DDD + Número</code>
                                            <br><strong>Dica:</strong> O código do Brasil é 55. Se seu número é (11) 99999-9999, digite: 5511999999999
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-comment"></i> Mensagem Padrão
                                        </label>
                                        <textarea name="whatsapp_message" rows="4" placeholder="Olá! Quero reservar: "><?php echo htmlspecialchars($currentSettings['whatsapp_message']); ?></textarea>
                                        <small class="form-hint">
                                            <i class="fas fa-lightbulb"></i> <strong>Como funciona:</strong> Esta mensagem é enviada automaticamente quando alguém clica no botão "Comprar" de um produto.
                                            <br><strong>Exemplo prático:</strong>
                                            <br>Se você escrever: <code>"Olá! Quero reservar: "</code>
                                            <br>E o cliente clicar em "Comprar" no produto "Perfume X 100ml"
                                            <br>A mensagem final será: <code>"Olá! Quero reservar: Perfume X 100ml"</code>
                                            <br><strong>Dica:</strong> Seja claro e objetivo. O nome do produto será adicionado automaticamente no final.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Personalização de Cores -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fas fa-palette"></i> Personalização de Cores</h2>
                            <p class="section-description">Personalize as cores da sua vitrine. Escolha as cores que melhor representam sua marca. As mudanças aparecem em toda a vitrine e no painel administrativo.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <!-- Cores do Sistema -->
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-palette"></i> Cores Principais</h3>
                                    <span class="card-badge">Personalização</span>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Onde essas cores aparecem:</strong> Botões "Comprar", botão WhatsApp "Falar com Suporte", filtros de marca, badges de produtos, links ativos, destaques e muito mais. Após salvar, recarregue a página para ver as mudanças.
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-star"></i> Cor Principal
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_primary" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_primary']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_primary']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Botões principais (Comprar, Salvar), botão WhatsApp, filtros, links ativos, badges de destaque.
                                                <br><strong>Exemplo:</strong> Se você escolher dourado (#C7A333), todos os botões principais ficarão dourados.
                                            </small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-adjust"></i> Cor Secundária
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_secondary" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_secondary']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_secondary']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Efeito ao passar o mouse nos botões, transições suaves, gradientes.
                                                <br><strong>Exemplo:</strong> Quando você passa o mouse sobre um botão, ele muda para esta cor.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-tag"></i> Cor de Destaque
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_accent" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_accent']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_accent']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Badges de produtos, tags de marca, indicadores de status, bordas de foco.
                                                <br><strong>Exemplo:</strong> As etiquetas "Novo" ou "Destaque" nos produtos usarão esta cor.
                                            </small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-square"></i> Cor de Fundo
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_background" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_background']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_background']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Fundo de todas as páginas (vitrine e painel admin), área ao redor dos cards.
                                                <br><strong>Exemplo:</strong> Se você escolher preto (#0F0F0F), toda a página terá fundo preto.
                                            </small>
                                        </div>
                                    </div>
                                
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-layer-group"></i> Cor de Superfície
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_surface" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_surface']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_surface']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Cards de produtos, cards do painel admin, campos de formulário, painéis laterais.
                                                <br><strong>Exemplo:</strong> Os cards onde aparecem os produtos na vitrine terão esta cor de fundo.
                                            </small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-font"></i> Cor do Texto Principal
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_text']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_text']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Títulos de produtos, nomes de marcas, textos principais, títulos de seções.
                                                <br><strong>Dica:</strong> Escolha uma cor que tenha bom contraste com a cor de superfície para facilitar a leitura.
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-font"></i> Cor do Texto Secundário
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_text_muted" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_text_muted']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_text_muted']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Descrições de produtos, textos de ajuda, placeholders, rodapé.
                                                <br><strong>Exemplo:</strong> As descrições dos produtos aparecerão nesta cor (geralmente mais clara que o texto principal).
                                            </small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-bars"></i> Cor do Texto do Logo (Menu)
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="color_sidebar_logo_text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_sidebar_logo_text'] ?? '#FFFFFF'); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['color_sidebar_logo_text'] ?? '#FFFFFF'); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Cor do texto do logo no menu lateral do painel administrativo.
                                                <br><strong>Dica:</strong> Use uma cor clara (como branco) se o fundo do menu for escuro.
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-warning" style="margin-top: 1.5rem;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Lembrete:</strong> Após salvar, pressione F5 ou recarregue a página para ver as mudanças. Certifique-se de que as cores escolhidas tenham bom contraste para facilitar a leitura.
                                    </div>
                                </div>
                            </div>

                            <!-- Cores do Carrossel -->
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-images"></i> Cores do Carrossel de Banners</h3>
                                    <span class="card-badge">Opcional</span>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>O que é o carrossel:</strong> O carrossel é a área de banners que aparece no topo da vitrine. Aqui você pode personalizar as cores dos controles de navegação.
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-circle"></i> Cor dos Círculos Indicadores
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="carousel_pagination_color" 
                                                       value="<?php echo htmlspecialchars($currentSettings['carousel_pagination_color']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['carousel_pagination_color']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> Os pequenos círculos na parte inferior do carrossel que mostram qual banner está sendo exibido.
                                                <br><strong>Exemplo:</strong> Se você tiver 4 banners, verá 4 círculos. O círculo do banner ativo ficará nesta cor.
                                            </small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <i class="fas fa-chevron-left"></i> Cor das Setas de Navegação
                                            </label>
                                            <div class="color-input-wrapper">
                                                <input type="color" name="carousel_arrow_color" 
                                                       value="<?php echo htmlspecialchars($currentSettings['carousel_arrow_color']); ?>"
                                                       class="color-picker">
                                                <input type="text" 
                                                       value="<?php echo htmlspecialchars($currentSettings['carousel_arrow_color']); ?>"
                                                       class="color-text"
                                                       readonly>
                                            </div>
                                            <small class="form-hint">
                                                <strong>Onde aparece:</strong> As setas (← →) nas laterais do carrossel que permitem navegar entre os banners.
                                                <br><strong>Dica:</strong> Use uma cor clara (como branco) para que as setas sejam visíveis sobre as imagens dos banners.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Configurações Avançadas -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fas fa-cogs"></i> Configurações Avançadas</h2>
                            <p class="section-description">Ajustes opcionais para personalizar ainda mais sua vitrine.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <!-- Blur da Logo no Placeholder -->
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-image"></i> Efeito de Desfoque da Logo</h3>
                                    <span class="card-badge">Avançado</span>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>O que é isso:</strong> Quando um produto não tem imagem, a logo do sistema aparece como fundo. Você pode controlar o quanto essa logo fica desfocada (blur).
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            <i class="fas fa-adjust"></i> Nível de Desfoque: <span id="blurValue" style="color: var(--admin-accent); font-weight: 700;"><?php echo $currentSettings['product_placeholder_logo_blur']; ?>%</span>
                                        </label>
                                        <input type="range" 
                                               name="product_placeholder_logo_blur" 
                                               min="0" 
                                               max="100" 
                                               value="<?php echo $currentSettings['product_placeholder_logo_blur']; ?>"
                                               class="slider-input"
                                               id="blurSlider"
                                               oninput="document.getElementById('blurValue').textContent = this.value + '%'">
                                        <div class="slider-labels">
                                            <span>0% (Nítido)</span>
                                            <span>100% (Muito Desfocado)</span>
                                        </div>
                                        <small class="form-hint">
                                            <i class="fas fa-lightbulb"></i> <strong>Como funciona:</strong>
                                            <br>• <strong>0%</strong> = Logo totalmente nítida (aparece claramente)
                                            <br>• <strong>50%</strong> = Logo moderadamente desfocada (recomendado)
                                            <br>• <strong>100%</strong> = Logo muito desfocada (quase imperceptível)
                                            <br><strong>Recomendação:</strong> Entre 20% e 40% para um efeito sutil e elegante.
                                            <br><strong>Valor atual:</strong> <?php echo $currentSettings['product_placeholder_logo_blur']; ?>%
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Informações do Sistema -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fas fa-info-circle"></i> Informações do Sistema</h2>
                            <p class="section-description">Informações técnicas sobre sua instalação. Essas informações são apenas para referência.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-server"></i> Detalhes Técnicos</h3>
                                    <span class="card-badge">Somente Leitura</span>
                                </div>
                                <div class="card-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Nome do Sistema</span>
                                        <span class="info-value"><?php echo htmlspecialchars($currentSettings['app_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">URL do Site</span>
                                        <span class="info-value" style="word-break: break-all;"><?php echo APP_URL; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Versão do PHP</span>
                                        <span class="info-value"><?php echo phpversion(); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Banco de Dados</span>
                                        <span class="info-value"><?php echo DB_NAME; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Usuário Logado</span>
                                        <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status</span>
                                        <span class="info-value">
                                            <span class="badge badge-success">Sistema Funcionando</span>
                                        </span>
                                    </div>
                                    </div>
                                
                                    <div class="alert alert-info" style="margin-top: 1.5rem;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Explicação rápida:</strong>
                                        <ul style="margin: 10px 0; padding-left: 20px; font-size: 0.9rem; line-height: 1.8;">
                                            <li><strong>Nome do Sistema:</strong> O nome que você configurou para sua vitrine (pode ser alterado acima)</li>
                                            <li><strong>URL do Site:</strong> O endereço completo onde sua vitrine está hospedada (ex: https://seusite.com.br)</li>
                                            <li><strong>Versão do PHP:</strong> A versão da linguagem PHP que seu servidor está usando (informação técnica)</li>
                                            <li><strong>Banco de Dados:</strong> O nome do banco de dados onde todas as informações estão armazenadas</li>
                                            <li><strong>Usuário Logado:</strong> Seu email de administrador atual</li>
                                            <li><strong>Status:</strong> Indica se o sistema está funcionando corretamente</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Mercado Pago -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fas fa-credit-card"></i> Configurações do Mercado Pago</h2>
                            <p class="section-description">Configure as credenciais do Mercado Pago para processar pagamentos.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-key"></i> Credenciais de Produção</h3>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Como obter as chaves:</strong>
                                        <ol style="margin: 0.5rem 0 0 1.5rem;">
                                            <li>Acesse <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank">Suas integrações</a> no Mercado Pago</li>
                                            <li>Crie uma aplicação ou selecione uma existente</li>
                                            <li>Copie o <strong>Access Token</strong> e a <strong>Public Key</strong> de produção</li>
                                        </ol>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Access Token (Produção)</label>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <input type="text" name="mercado_pago_access_token" 
                                                   value="<?php echo htmlspecialchars($currentSettings['mercado_pago_access_token']); ?>"
                                                   placeholder="APP_USR-..."
                                                   style="flex: 1;">
                                            <button type="button" class="btn-test-api" data-api="mercado_pago_prod" style="padding: 0.75rem 1.5rem; background: var(--admin-accent); color: #000; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                                                <i class="fas fa-vial"></i> Testar
                                            </button>
                                        </div>
                                        <div id="mp_prod_test_result" style="margin-top: 0.5rem; display: none;"></div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Public Key (Produção)</label>
                                        <input type="text" name="mercado_pago_public_key" 
                                               value="<?php echo htmlspecialchars($currentSettings['mercado_pago_public_key']); ?>"
                                               placeholder="APP_USR-...">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="admin-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-vial"></i> Credenciais de Teste</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Access Token (Teste)</label>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <input type="text" name="mercado_pago_access_token_test" 
                                                   value="<?php echo htmlspecialchars($currentSettings['mercado_pago_access_token_test']); ?>"
                                                   placeholder="TEST-..."
                                                   style="flex: 1;">
                                            <button type="button" class="btn-test-api" data-api="mercado_pago_test" style="padding: 0.75rem 1.5rem; background: var(--admin-accent); color: #000; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                                                <i class="fas fa-vial"></i> Testar
                                            </button>
                                        </div>
                                        <div id="mp_test_test_result" style="margin-top: 0.5rem; display: none;"></div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Public Key (Teste)</label>
                                        <input type="text" name="mercado_pago_public_key_test" 
                                               value="<?php echo htmlspecialchars($currentSettings['mercado_pago_public_key_test']); ?>"
                                               placeholder="TEST-...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Ambiente</label>
                                        <select name="mercado_pago_environment">
                                            <option value="test" <?php echo $currentSettings['mercado_pago_environment'] === 'test' ? 'selected' : ''; ?>>Teste</option>
                                            <option value="production" <?php echo $currentSettings['mercado_pago_environment'] === 'production' ? 'selected' : ''; ?>>Produção</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Frete (Melhor Envio) -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fas fa-truck"></i> Configuracoes de Frete</h2>
                            <p class="section-description">Configure o calculo de frete usando a API do Melhor Envio.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <div class="admin-card">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>CEP de Origem</label>
                                        <input type="text" name="correios_cep_origem" 
                                               value="<?php echo htmlspecialchars($currentSettings['correios_cep_origem']); ?>"
                                               placeholder="00000-000"
                                               maxlength="9"
                                               id="correios_cep_origem">
                                        <small class="form-hint">CEP de onde os produtos serao enviados</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Token Melhor Envio (Opcional)</label>
                                        <input type="text" name="melhor_envio_token" 
                                               value="<?php echo htmlspecialchars($currentSettings['melhor_envio_token'] ?? ''); ?>"
                                               placeholder="Seu token do Melhor Envio"
                                               id="melhor_envio_token">
                                        <small class="form-hint">Se nao tiver token, o sistema usara calculo simulado. <a href="https://melhorenvio.com.br" target="_blank" style="color: var(--admin-accent);">Obter token</a></small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Valor Minimo para Frete Gratis (R$)</label>
                                        <input type="number" name="frete_gratis_valor_minimo" 
                                               value="<?php echo htmlspecialchars($currentSettings['frete_gratis_valor_minimo']); ?>"
                                               step="0.01"
                                               min="0"
                                               placeholder="0.00">
                                        <small class="form-hint">Deixe 0 para desativar frete gratis</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Calculadora de Frete -->
                            <div class="admin-card">
                                <div class="card-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--admin-border);">
                                    <h3 style="margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-calculator"></i> Calculadora de Frete
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <p style="font-size: 0.85rem; color: var(--admin-text-secondary); margin-bottom: 1rem;">
                                        Faca uma cotacao de frete para testar os valores.
                                    </p>
                                    
                                    <div class="form-group">
                                        <label>CEP de Destino</label>
                                        <input type="text" id="calc_cep_destino" placeholder="00000-000" maxlength="9">
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label style="font-size: 0.8rem;">Peso (kg)</label>
                                            <input type="number" id="calc_peso" value="0.3" step="0.01" min="0.01">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label style="font-size: 0.8rem;">Altura (cm)</label>
                                            <input type="number" id="calc_altura" value="10" step="1" min="1">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label style="font-size: 0.8rem;">Largura (cm)</label>
                                            <input type="number" id="calc_largura" value="10" step="1" min="1">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label style="font-size: 0.8rem;">Comprimento (cm)</label>
                                            <input type="number" id="calc_comprimento" value="10" step="1" min="1">
                                        </div>
                                    </div>
                                    
                                    <button type="button" id="btnCalcularFrete" style="width: 100%; margin-top: 1rem; padding: 0.75rem; background: var(--admin-accent); color: #000; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                        <i class="fas fa-calculator"></i> Calcular Frete
                                    </button>
                                    
                                    <div id="frete_calc_result" style="margin-top: 1rem; display: none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: WhatsApp Flutuante -->
                    <div class="settings-section" style="margin-top: 3rem;">
                        <div class="section-header">
                            <h2><i class="fab fa-whatsapp"></i> Botão WhatsApp Flutuante</h2>
                            <p class="section-description">Configure o botão flutuante do WhatsApp que aparece no canto inferior direito.</p>
                        </div>
                        
                        <div class="settings-grid">
                            <div class="admin-card">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="whatsapp_float_enabled" 
                                                   <?php echo $currentSettings['whatsapp_float_enabled'] == '1' ? 'checked' : ''; ?>>
                                            Ativar botão WhatsApp flutuante
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Número do WhatsApp</label>
                                        <input type="text" name="whatsapp_float_number" 
                                               value="<?php echo htmlspecialchars($currentSettings['whatsapp_float_number']); ?>"
                                               placeholder="5521983050061">
                                        <small class="form-hint">Apenas números, com código do país (ex: 5521983050061)</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Mensagem Padrão</label>
                                        <textarea name="whatsapp_float_message" rows="3"
                                                  placeholder="Olá! Preciso de ajuda."><?php echo htmlspecialchars($currentSettings['whatsapp_float_message']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
        
        <!-- Fixed Save Button -->
        <div class="save-button-container">
            <button type="submit" form="settingsForm" class="btn-save">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
        </div>
    </div>

    <style>
    /* Settings Page Additional Styles */
    .color-input-wrapper {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .color-picker {
        width: 60px;
        height: 44px;
        border: 2px solid var(--admin-border);
        border-radius: 8px;
        cursor: pointer;
        padding: 2px;
    }
    
    .color-text {
        flex: 1;
        padding: 12px;
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        background: var(--admin-bg-input);
        font-family: monospace;
        font-size: 0.9rem;
        text-align: center;
        color: var(--admin-text-primary);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: var(--admin-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .info-value {
        font-weight: 600;
        word-break: break-word;
        color: var(--admin-text-primary);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    /* Fixed Save Button */
    .save-button-container {
        position: fixed;
        bottom: 0;
        left: var(--sidebar-width-collapsed, 72px);
        right: 0;
        background: linear-gradient(180deg, var(--admin-bg-card) 0%, #0d0d0d 100%);
        padding: 20px;
        border-top: 2px solid var(--admin-accent);
        box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .sidebar.expanded ~ .main-content .save-button-container {
        left: var(--sidebar-width-expanded, 260px);
    }
    
    .admin-layout .main-content {
        padding-bottom: 100px;
    }
    
    @media (max-width: 1024px) {
        .save-button-container {
            left: 0 !important;
        }
    }
    
    .btn-save {
        background: linear-gradient(135deg, var(--admin-accent), var(--admin-accent-dark, #b8962e));
        color: #000;
        border: none;
        padding: 16px 48px;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(212, 175, 55, 0.45);
    }
    
    .btn-save:active {
        transform: translateY(0);
    }
    
    @media (max-width: 768px) {
        .form-row,
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .btn-save {
            width: 100%;
            justify-content: center;
        }
    }
    </style>

    <script src="<?php echo APP_URL; ?>/assets/js/image-upload.js"></script>
    <script>
    // Sincronizar color picker com text input
    document.addEventListener('DOMContentLoaded', function() {
        const colorPickers = document.querySelectorAll('.color-picker');
        colorPickers.forEach(picker => {
            const textInput = picker.parentElement.querySelector('.color-text');
            
            picker.addEventListener('input', function() {
                textInput.value = this.value.toUpperCase();
            });
            
            textInput.addEventListener('input', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    picker.value = this.value;
                }
            });
        });
        
// Inicializar componente de upload de logo
	        if (typeof ImageUpload !== 'undefined') {
	            window.logoUpload = new ImageUpload('logoUploadContainer', {
	                uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
	                currentImage: '<?php echo htmlspecialchars($currentSettings['logo_url']); ?>',
	                inputName: 'logo_url',
	                folder: 'logos',
	                onUploadSuccess: function(url) {
	                    // Atualizar o campo hidden do formulário
	                    const hiddenInput = document.getElementById('logoUploadContainer_hiddenInput');
	                    if (hiddenInput) {
	                        hiddenInput.value = url;
	                    }
	                }
	            });

	            window.faviconUpload = new ImageUpload('faviconUploadContainer', {
	                uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
	                currentImage: '<?php echo htmlspecialchars($currentSettings['favicon_url']); ?>',
	                inputName: 'favicon_url',
	                folder: 'logos',
	                onUploadSuccess: function(url) {
	                    const hiddenInput = document.getElementById('faviconUploadContainer_hiddenInput');
	                    if (hiddenInput) {
	                        hiddenInput.value = url;
	                    }
	                }
	            });
	        }
        
        // Atualizar valor do slider de tamanho da logo
        const logoSizeSlider = document.getElementById('logoSizeSlider');
        const logoSizeValue = document.getElementById('logoSizeValue');
        const logoPreview = document.getElementById('logoPreview');
        
        if (logoSizeSlider && logoSizeValue) {
            // Atualizar valor ao mover o slider
            logoSizeSlider.addEventListener('input', function() {
                const newSize = this.value;
                logoSizeValue.textContent = newSize;
                
                // Atualizar preview em tempo real
                if (logoPreview) {
                    const maxSize = 600; // Max display size in preview
                    const displaySize = Math.min(newSize, maxSize);
                    logoPreview.style.width = displaySize + 'px';
                    logoPreview.style.height = displaySize + 'px';
                }
                
                // Atualizar badge de tamanho
                const sizeBadge = document.querySelector('.logo-preview-container [style*="position: absolute"]');
                if (sizeBadge && sizeBadge.textContent.includes('px')) {
                    sizeBadge.textContent = newSize + 'px';
                }
                
                // Atualizar progresso visual do slider
                const progress = ((this.value - this.min) / (this.max - this.min)) * 100;
                this.style.setProperty('--slider-progress', progress + '%');
            });
            
            // Inicializar progresso visual
            const progress = ((logoSizeSlider.value - logoSizeSlider.min) / (logoSizeSlider.max - logoSizeSlider.min)) * 100;
            logoSizeSlider.style.setProperty('--slider-progress', progress + '%');
        }
    });
    </script>

    <script>
    // Testar APIs
    document.querySelectorAll('.btn-test-api').forEach(btn => {
        btn.addEventListener('click', function() {
            const apiType = this.getAttribute('data-api');
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
            
            let resultDiv;
            if (apiType === 'mercado_pago_prod') {
                resultDiv = document.getElementById('mp_prod_test_result');
            } else if (apiType === 'mercado_pago_test') {
                resultDiv = document.getElementById('mp_test_test_result');
            } else if (apiType === 'correios') {
                resultDiv = document.getElementById('correios_test_result');
            }
            
            if (!resultDiv) return;
            
            const formData = new FormData();
            formData.append('api_type', apiType);
            
            if (apiType === 'correios') {
                const cep = document.getElementById('correios_cep_origem').value.replace(/\D/g, '');
                if (!cep || cep.length !== 8) {
                    resultDiv.innerHTML = '<div class="alert alert-error" style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> CEP inválido. Digite um CEP com 8 dígitos.</div>';
                    resultDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    return;
                }
                formData.append('cep', cep);
            } else {
                const tokenInput = apiType === 'mercado_pago_prod' 
                    ? document.querySelector('input[name="mercado_pago_access_token"]')
                    : document.querySelector('input[name="mercado_pago_access_token_test"]');
                const token = tokenInput ? tokenInput.value.trim() : '';
                if (!token) {
                    resultDiv.innerHTML = '<div class="alert alert-error" style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> Preencha o Access Token primeiro.</div>';
                    resultDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    return;
                }
                formData.append('token', token);
            }
            
            fetch('<?php echo APP_URL; ?>/api/test-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success" style="padding: 0.75rem; border-radius: 8px; background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid #22c55e;"><i class="fas fa-check-circle"></i> ' + (data.message || 'API funcionando corretamente!') + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-error" style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> ' + (data.error || 'Erro ao testar API') + '</div>';
                }
                resultDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                setTimeout(() => {
                    resultDiv.style.opacity = '0';
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                        resultDiv.style.opacity = '1';
                    }, 300);
                }, 5000);
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-error" style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> Erro ao testar API: ' + error.message + '</div>';
                resultDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
    </script>
    
    <script>
    // Calculadora de Frete
    document.getElementById('btnCalcularFrete')?.addEventListener('click', async function() {
        const btn = this;
        const originalText = btn.innerHTML;
        const resultDiv = document.getElementById('frete_calc_result');
        
        const cepOrigem = document.getElementById('correios_cep_origem')?.value.replace(/\D/g, '') || '';
        const cepDestino = document.getElementById('calc_cep_destino')?.value.replace(/\D/g, '') || '';
        const peso = parseFloat(document.getElementById('calc_peso')?.value) || 0.3;
        const altura = parseInt(document.getElementById('calc_altura')?.value) || 10;
        const largura = parseInt(document.getElementById('calc_largura')?.value) || 10;
        const comprimento = parseInt(document.getElementById('calc_comprimento')?.value) || 10;
        
        if (!cepOrigem || cepOrigem.length !== 8) {
            resultDiv.innerHTML = '<div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> Configure o CEP de origem primeiro.</div>';
            resultDiv.style.display = 'block';
            return;
        }
        
        if (!cepDestino || cepDestino.length !== 8) {
            resultDiv.innerHTML = '<div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> Digite um CEP de destino valido.</div>';
            resultDiv.style.display = 'block';
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando...';
        resultDiv.style.display = 'none';
        
        try {
            const response = await fetch('<?php echo APP_URL; ?>/api/calculate-shipping.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cep_destino: cepDestino,
                    peso: peso,
                    altura: altura,
                    largura: largura,
                    comprimento: comprimento
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.options && data.options.length > 0) {
                let html = '<div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; border-radius: 8px; padding: 1rem;">';
                html += '<h4 style="margin: 0 0 0.75rem 0; color: #22c55e; font-size: 0.9rem;"><i class="fas fa-check-circle"></i> Opcoes de Frete Encontradas</h4>';
                html += '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
                
                data.options.forEach(opt => {
                    html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 6px;">';
                    html += '<span style="font-size: 0.85rem; color: #ccc;">' + opt.name + ' (' + opt.delivery_time + ' dias uteis)</span>';
                    html += '<strong style="color: #22c55e;">R$ ' + parseFloat(opt.price).toFixed(2).replace('.', ',') + '</strong>';
                    html += '</div>';
                });
                
                html += '</div></div>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> ' + (data.error || 'Nao foi possivel calcular o frete') + '</div>';
            }
            
            resultDiv.style.display = 'block';
        } catch (error) {
            resultDiv.innerHTML = '<div style="padding: 0.75rem; border-radius: 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;"><i class="fas fa-times-circle"></i> Erro: ' + error.message + '</div>';
            resultDiv.style.display = 'block';
        }
        
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
    
    // Mascara CEP
    document.getElementById('calc_cep_destino')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 5) {
            value = value.substring(0, 5) + '-' + value.substring(5, 8);
        }
        e.target.value = value;
    });
    
    document.getElementById('correios_cep_origem')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 5) {
            value = value.substring(0, 5) + '-' + value.substring(5, 8);
        }
        e.target.value = value;
    });
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
