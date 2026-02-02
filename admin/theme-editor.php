<?php
/**
 * Editor Visual de Tema - Vitrine
 * Permite personalizar todas as partes do site visualmente
 */
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar autenticacao usando as funcoes padrao do sistema
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Editor Visual';

// Buscar configuracoes atuais do tema
$themeSettings = [
    // Faixa Rotativa
    'faixa_enabled' => getSetting($pdo, 'faixa_enabled', '1'),
    'faixa_bg_color' => getSetting($pdo, 'faixa_bg_color', '#b67c90'),
    'faixa_text_color' => getSetting($pdo, 'faixa_text_color', '#ffffff'),
    'faixa_font_size' => getSetting($pdo, 'faixa_font_size', '14'),
    'faixa_frases' => getSetting($pdo, 'faixa_frases', 'PARCELAMENTO EM ATE 6X SEM JUROS|ENTREGA RAPIDA PARA TODO PAIS|5% DE DESCONTO NO PIX|TROCA GRATIS EM ATE 30 DIAS'),
    'faixa_links' => getSetting($pdo, 'faixa_links', '|||'),
    'faixa_interval' => getSetting($pdo, 'faixa_interval', '4000'),
    
    // Cores principais
    'color_primary' => getSetting($pdo, 'color_primary', '#C7A333'),
    'color_secondary' => getSetting($pdo, 'color_secondary', '#1a1a1a'),
    'color_accent' => getSetting($pdo, 'color_accent', '#d4af37'),
    'color_background' => getSetting($pdo, 'color_background', '#0a0a0a'),
    'color_text' => getSetting($pdo, 'color_text', '#ffffff'),
    'color_text_muted' => getSetting($pdo, 'color_text_muted', '#888888'),
    
    // Header/Navigation
    'header_bg' => getSetting($pdo, 'header_bg', '#0a0a0a'),
    'header_text' => getSetting($pdo, 'header_text', '#ffffff'),
    'nav_bg' => getSetting($pdo, 'nav_bg', '#111111'),
    
    // Cards e Produtos
    'card_bg' => getSetting($pdo, 'card_bg', '#141414'),
    'card_border' => getSetting($pdo, 'card_border', '#2a2a2a'),
    'card_hover_bg' => getSetting($pdo, 'card_hover_bg', '#1a1a1a'),
    
    // Botoes
    'button_primary_bg' => getSetting($pdo, 'button_primary_bg', '#d4af37'),
    'button_primary_text' => getSetting($pdo, 'button_primary_text', '#000000'),
    'button_secondary_bg' => getSetting($pdo, 'button_secondary_bg', 'transparent'),
    'button_secondary_text' => getSetting($pdo, 'button_secondary_text', '#d4af37'),
    
    // Footer
    'footer_bg' => getSetting($pdo, 'footer_bg', '#0a0a0a'),
    'footer_text' => getSetting($pdo, 'footer_text', '#888888'),
    
    // Tipografia
    'font_heading' => getSetting($pdo, 'font_heading', 'Sora'),
    'font_body' => getSetting($pdo, 'font_body', 'Inter'),
    'font_size_base' => getSetting($pdo, 'font_size_base', '16'),
    
    // Espacamento e Bordas
    'border_radius' => getSetting($pdo, 'border_radius', '12'),
    'spacing_unit' => getSetting($pdo, 'spacing_unit', '16'),
    
    // Layout
    'container_max_width' => getSetting($pdo, 'container_max_width', '1400'),
    'products_per_row' => getSetting($pdo, 'products_per_row', '4'),
];

// Buscar versoes do tema
$themeVersions = [];
try {
    if (db_table_exists($pdo, 'theme_versions')) {
        $stmt = $pdo->query("SELECT * FROM theme_versions ORDER BY created_at DESC LIMIT 20");
        $themeVersions = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching theme versions: " . $e->getMessage());
}

// Lista de fontes Google disponiveis
$googleFonts = [
    'Inter', 'Sora', 'Poppins', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 
    'Oswald', 'Raleway', 'Playfair Display', 'Merriweather', 'Nunito', 
    'Work Sans', 'DM Sans', 'Space Grotesk', 'Plus Jakarta Sans', 'Outfit',
    'Cormorant Garamond', 'Libre Baskerville', 'Crimson Pro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/../includes/dynamic-colors.php'; ?>
    <style>
        /* Editor Visual Styles */
        .editor-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
            background: #050505;
        }
        
        /* Sidebar do Editor */
        .editor-sidebar {
            width: 380px;
            min-width: 380px;
            background: linear-gradient(180deg, #0d0d0d 0%, #080808 100%);
            border-right: 1px solid #1a1a1a;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .editor-header {
            padding: 20px 24px;
            border-bottom: 1px solid #1a1a1a;
            background: rgba(212, 175, 55, 0.03);
        }
        
        .editor-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 4px;
        }
        
        .editor-title h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }
        
        .editor-title .pro-badge {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .editor-subtitle {
            color: #666;
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* Tabs do Editor */
        .editor-tabs {
            display: flex;
            border-bottom: 1px solid #1a1a1a;
            background: #0a0a0a;
            padding: 0 12px;
        }
        
        .editor-tab {
            flex: 1;
            padding: 14px 12px;
            background: transparent;
            border: none;
            color: #666;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        
        .editor-tab:hover {
            color: #999;
        }
        
        .editor-tab.active {
            color: #d4af37;
            border-bottom-color: #d4af37;
        }
        
        .editor-tab i {
            font-size: 0.9rem;
        }
        
        /* Conteudo do Editor */
        .editor-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .editor-panel {
            display: none;
        }
        
        .editor-panel.active {
            display: block;
        }
        
        /* Grupos de Opcoes */
        .option-group {
            margin-bottom: 24px;
        }
        
        .option-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            cursor: pointer;
        }
        
        .option-group-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #d4af37;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .option-group-toggle {
            color: #666;
            font-size: 0.8rem;
            transition: transform 0.2s;
        }
        
        .option-group.collapsed .option-group-toggle {
            transform: rotate(-90deg);
        }
        
        .option-group.collapsed .option-group-content {
            display: none;
        }
        
        .option-group-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        /* Input Fields */
        .option-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .option-label {
            font-size: 0.8rem;
            color: #999;
            font-weight: 500;
        }
        
        .option-input {
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 12px 16px;
            color: #fff;
            font-size: 0.9rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .option-input:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15);
            transform: translateY(-1px);
        }
        
        .option-input::placeholder {
            color: #555;
        }
        
        /* Color Picker */
        .color-picker-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .color-preview {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            border: 2px solid #2a2a2a;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .color-preview:hover {
            border-color: #d4af37;
            transform: scale(1.05);
        }
        
        .color-preview input[type="color"] {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 64px;
            height: 64px;
            border: none;
            cursor: pointer;
        }
        
        .color-hex {
            flex: 1;
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            padding: 12px 16px;
            color: #fff;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 0.85rem;
            text-transform: uppercase;
            transition: all 0.3s;
        }
        
        .color-hex:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15);
        }
        
        /* Select/Dropdown */
        .option-select {
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 14px;
            padding: 12px 16px;
            color: #fff;
            font-size: 0.9rem;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .option-select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15);
        }
        
        /* Range Slider */
        .range-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .range-slider {
            flex: 1;
            height: 6px;
            border-radius: 3px;
            background: #2a2a2a;
            appearance: none;
            cursor: pointer;
        }
        
        .range-slider::-webkit-slider-thumb {
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #d4af37;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
        }
        
        .range-value {
            min-width: 50px;
            text-align: right;
            font-size: 0.85rem;
            color: #d4af37;
            font-weight: 600;
        }
        
        /* Preview Area */
        .editor-preview {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #111;
        }
        
        .preview-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: #0d0d0d;
            border-bottom: 1px solid #1a1a1a;
        }
        
        .preview-devices {
            display: flex;
            gap: 4px;
            background: #141414;
            padding: 4px;
            border-radius: 8px;
        }
        
        .device-btn {
            padding: 8px 14px;
            background: transparent;
            border: none;
            color: #666;
            font-size: 0.85rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .device-btn:hover {
            color: #999;
        }
        
        .device-btn.active {
            background: #d4af37;
            color: #000;
        }
        
        .preview-actions {
            display: flex;
            gap: 10px;
        }
        
        .preview-url {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #141414;
            padding: 8px 14px;
            border-radius: 8px;
            color: #666;
            font-size: 0.8rem;
        }
        
        .preview-url i {
            color: #22c55e;
        }
        
        /* Iframe Container */
        .preview-frame-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #0a0a0a;
            overflow: hidden;
        }
        
        .preview-frame-wrapper {
            width: 100%;
            height: 100%;
            max-width: 100%;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }
        
        .preview-frame-wrapper.tablet {
            max-width: 768px;
        }
        
        .preview-frame-wrapper.mobile {
            max-width: 375px;
        }
        
        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Action Buttons */
        .editor-actions {
            display: flex;
            gap: 10px;
            padding: 16px 20px;
            background: #0d0d0d;
            border-top: 1px solid #1a1a1a;
        }
        
        .btn-editor {
            flex: 1;
            padding: 14px 24px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #d4af37, #b8962e);
            color: #000;
            border: none;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.3);
        }
        
        .btn-reset {
            background: transparent;
            color: #666;
            border: 1px solid #2a2a2a;
        }
        
        .btn-reset:hover {
            border-color: #ef4444;
            color: #ef4444;
        }
        
        /* Version History */
        .version-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .version-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .version-item:hover {
            border-color: #d4af37;
            background: #1a1a1a;
        }
        
        .version-item.current {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.05);
        }
        
        .version-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #d4af37, #b8962e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 1rem;
        }
        
        .version-item.current .version-icon {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff;
        }
        
        .version-info {
            flex: 1;
        }
        
        .version-name {
            font-weight: 600;
            color: #fff;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .version-date {
            color: #666;
            font-size: 0.8rem;
        }
        
        .version-actions {
            display: flex;
            gap: 6px;
        }
        
        .version-btn {
            padding: 6px 10px;
            background: #2a2a2a;
            border: none;
            border-radius: 6px;
            color: #999;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .version-btn:hover {
            background: #d4af37;
            color: #000;
        }
        
        .version-btn.restore:hover {
            background: #3b82f6;
            color: #fff;
        }
        
        /* Presets */
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .preset-card {
            background: #141414;
            border: 2px solid #2a2a2a;
            border-radius: 18px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .preset-card:hover {
            border-color: #d4af37;
            transform: translateY(-2px);
        }
        
        .preset-preview {
            height: 80px;
            display: flex;
        }
        
        .preset-preview > div {
            flex: 1;
        }
        
        .preset-info {
            padding: 12px;
            text-align: center;
        }
        
        .preset-name {
            font-weight: 600;
            color: #fff;
            font-size: 0.85rem;
        }
        
        /* Notification Toast */
        .editor-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #22c55e;
            color: #fff;
            padding: 16px 28px;
            border-radius: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 12px 40px rgba(34, 197, 94, 0.4);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 9999;
        }
        
        .editor-toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .editor-toast.error {
            background: #ef4444;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }
        
        /* Back Button */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 8px 0;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: #d4af37;
        }
        
        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .editor-layout {
                flex-direction: column;
            }
            
            .editor-sidebar {
                width: 100%;
                min-width: 100%;
                max-height: 50vh;
            }
            
            .editor-preview {
                min-height: 50vh;
            }
        }
        
        /* Loading State */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #2a2a2a;
            border-top-color: #d4af37;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="editor-layout">
        <!-- Sidebar do Editor -->
        <aside class="editor-sidebar">
            <div class="editor-header">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Voltar ao Admin
                </a>
                <div class="editor-title">
                    <h1>Editor Visual</h1>
                    <span class="pro-badge">PRO</span>
                </div>
                <p class="editor-subtitle">Personalize cada detalhe da sua vitrine</p>
            </div>
            
            <!-- Tabs -->
            <div class="editor-tabs" style="flex-wrap: wrap; gap: 2px;">
                <button class="editor-tab active" data-tab="colors">
                    <i class="fas fa-palette"></i>
                    Cores
                </button>
                <button class="editor-tab" data-tab="content">
                    <i class="fas fa-edit"></i>
                    Textos
                </button>
                <button class="editor-tab" data-tab="checkout">
                    <i class="fas fa-credit-card"></i>
                    Checkout
                </button>
                <button class="editor-tab" data-tab="cart">
                    <i class="fas fa-shopping-cart"></i>
                    Carrinho
                </button>
                <button class="editor-tab" data-tab="products">
                    <i class="fas fa-box"></i>
                    Produtos
                </button>
                <button class="editor-tab" data-tab="typography">
                    <i class="fas fa-font"></i>
                    Fontes
                </button>
                <button class="editor-tab" data-tab="layout">
                    <i class="fas fa-th-large"></i>
                    Layout
                </button>
                <button class="editor-tab" data-tab="mobile">
                    <i class="fas fa-mobile-alt"></i>
                    Mobile
                </button>
                <button class="editor-tab" data-tab="history">
                    <i class="fas fa-history"></i>
                    Historico
                </button>
            </div>
            
            <!-- Conteudo das Tabs -->
            <div class="editor-content">
                <!-- Tab: Cores -->
                <div class="editor-panel active" id="panel-colors">
                    <!-- Presets de Cores -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-swatchbook"></i> Presets de Cores
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="preset-grid">
                                <div class="preset-card" data-preset="gold-dark">
                                    <div class="preset-preview">
                                        <div style="background: #0a0a0a;"></div>
                                        <div style="background: #d4af37;"></div>
                                        <div style="background: #1a1a1a;"></div>
                                    </div>
                                    <div class="preset-info">
                                        <span class="preset-name">Ouro Escuro</span>
                                    </div>
                                </div>
                                <div class="preset-card" data-preset="modern-white">
                                    <div class="preset-preview">
                                        <div style="background: #ffffff;"></div>
                                        <div style="background: #000000;"></div>
                                        <div style="background: #f5f5f5;"></div>
                                    </div>
                                    <div class="preset-info">
                                        <span class="preset-name">Moderno Claro</span>
                                    </div>
                                </div>
                                <div class="preset-card" data-preset="rose-gold">
                                    <div class="preset-preview">
                                        <div style="background: #1a1a1a;"></div>
                                        <div style="background: #b76e79;"></div>
                                        <div style="background: #2a2a2a;"></div>
                                    </div>
                                    <div class="preset-info">
                                        <span class="preset-name">Rose Gold</span>
                                    </div>
                                </div>
                                <div class="preset-card" data-preset="emerald">
                                    <div class="preset-preview">
                                        <div style="background: #0d1f17;"></div>
                                        <div style="background: #10b981;"></div>
                                        <div style="background: #1a2e23;"></div>
                                    </div>
                                    <div class="preset-info">
                                        <span class="preset-name">Esmeralda</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cores Principais -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-tint"></i> Cores Principais
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Cor Primaria (Destaque)</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['color_primary']; ?>">
                                        <input type="color" name="color_primary" value="<?php echo $themeSettings['color_primary']; ?>" data-target="color_primary">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['color_primary']; ?>" data-color="color_primary">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Cor de Fundo</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['color_background']; ?>">
                                        <input type="color" name="color_background" value="<?php echo $themeSettings['color_background']; ?>" data-target="color_background">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['color_background']; ?>" data-color="color_background">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Cor do Texto</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['color_text']; ?>">
                                        <input type="color" name="color_text" value="<?php echo $themeSettings['color_text']; ?>" data-target="color_text">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['color_text']; ?>" data-color="color_text">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Texto Secundario</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['color_text_muted']; ?>">
                                        <input type="color" name="color_text_muted" value="<?php echo $themeSettings['color_text_muted']; ?>" data-target="color_text_muted">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['color_text_muted']; ?>" data-color="color_text_muted">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cores de Componentes -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-layer-group"></i> Componentes
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Fundo dos Cards</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['card_bg']; ?>">
                                        <input type="color" name="card_bg" value="<?php echo $themeSettings['card_bg']; ?>" data-target="card_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['card_bg']; ?>" data-color="card_bg">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Borda dos Cards</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['card_border']; ?>">
                                        <input type="color" name="card_border" value="<?php echo $themeSettings['card_border']; ?>" data-target="card_border">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['card_border']; ?>" data-color="card_border">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Botao Primario</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['button_primary_bg']; ?>">
                                        <input type="color" name="button_primary_bg" value="<?php echo $themeSettings['button_primary_bg']; ?>" data-target="button_primary_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['button_primary_bg']; ?>" data-color="button_primary_bg">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Header</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['header_bg']; ?>">
                                        <input type="color" name="header_bg" value="<?php echo $themeSettings['header_bg']; ?>" data-target="header_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['header_bg']; ?>" data-color="header_bg">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Footer</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['footer_bg']; ?>">
                                        <input type="color" name="footer_bg" value="<?php echo $themeSettings['footer_bg']; ?>" data-target="footer_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['footer_bg']; ?>" data-color="footer_bg">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Conteudo -->
                <div class="editor-panel" id="panel-content">
                    <!-- Faixa Rotativa -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-bullhorn"></i> Faixa Promocional
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Ativar Faixa</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="faixa_enabled" value="1" <?php echo $themeSettings['faixa_enabled'] === '1' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Sim
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="faixa_enabled" value="0" <?php echo $themeSettings['faixa_enabled'] === '0' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Nao
                                    </label>
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Cor de Fundo</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['faixa_bg_color']; ?>">
                                        <input type="color" name="faixa_bg_color" value="<?php echo $themeSettings['faixa_bg_color']; ?>" data-target="faixa_bg_color">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['faixa_bg_color']; ?>" data-color="faixa_bg_color">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Cor do Texto</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo $themeSettings['faixa_text_color']; ?>">
                                        <input type="color" name="faixa_text_color" value="<?php echo $themeSettings['faixa_text_color']; ?>" data-target="faixa_text_color">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo $themeSettings['faixa_text_color']; ?>" data-color="faixa_text_color">
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Tamanho da Fonte</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="faixa_font_size" min="10" max="20" value="<?php echo $themeSettings['faixa_font_size']; ?>" data-target="faixa_font_size">
                                    <span class="range-value"><?php echo $themeSettings['faixa_font_size']; ?>px</span>
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Intervalo de Troca (ms)</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="faixa_interval" min="2000" max="10000" step="500" value="<?php echo $themeSettings['faixa_interval']; ?>" data-target="faixa_interval">
                                    <span class="range-value"><?php echo number_format(intval($themeSettings['faixa_interval']) / 1000, 1); ?>s</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Frases da Faixa -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-quote-right"></i> Frases Rotativas
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <p style="color: #888; font-size: 0.8rem; margin-bottom: 1rem;">Adicione ate 6 frases promocionais. Deixe o link vazio para nao redirecionar.</p>
                            
                            <?php 
                            $frases = explode('|', $themeSettings['faixa_frases']);
                            $links = explode('|', $themeSettings['faixa_links']);
                            for ($i = 0; $i < 6; $i++): 
                                $frase = isset($frases[$i]) ? trim($frases[$i]) : '';
                                $link = isset($links[$i]) ? trim($links[$i]) : '';
                            ?>
                            <div class="frase-item" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 10px; padding: 1rem; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                                    <span style="background: #d4af37; color: #000; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;"><?php echo $i + 1; ?></span>
                                    <span style="color: #666; font-size: 0.8rem;">Frase <?php echo $i + 1; ?></span>
                                </div>
                                <input type="text" class="option-input frase-text" name="frase_<?php echo $i; ?>" value="<?php echo htmlspecialchars($frase); ?>" placeholder="Ex: FRETE GRATIS ACIMA DE R$299" style="margin-bottom: 0.5rem; text-transform: uppercase;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-link" style="color: #666;"></i>
                                    <input type="text" class="option-input frase-link" name="link_<?php echo $i; ?>" value="<?php echo htmlspecialchars($link); ?>" placeholder="https://... (opcional)" style="font-size: 0.85rem;">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Textos do Site -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-file-alt"></i> Textos Gerais
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Placeholder da Busca</label>
                                <input type="text" class="option-input" name="search_placeholder" value="<?php echo htmlspecialchars(getSetting($pdo, 'search_placeholder', 'Oi, o que você procura hoje? ;)')); ?>" data-target="search_placeholder">
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Botao de Filtro</label>
                                <input type="text" class="option-input" name="filter_button_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'filter_button_text', 'Escolher Marca')); ?>" data-target="filter_button_text">
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Texto do Botao Comprar</label>
                                <input type="text" class="option-input" name="buy_button_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'buy_button_text', 'COMPRAR AGORA')); ?>" data-target="buy_button_text" style="text-transform: uppercase;">
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Texto do Carrinho Vazio</label>
                                <input type="text" class="option-input" name="empty_cart_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'empty_cart_text', 'Seu carrinho esta vazio')); ?>" data-target="empty_cart_text">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Redirecionamentos -->
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-external-link-alt"></i> Redirecionamentos
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Link do Logo (ao clicar)</label>
                                <input type="text" class="option-input" name="logo_redirect_url" value="<?php echo htmlspecialchars(getSetting($pdo, 'logo_redirect_url', '/')); ?>" data-target="logo_redirect_url" placeholder="/">
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Pagina Apos Adicionar ao Carrinho</label>
                                <select class="option-select" name="after_add_cart_redirect" data-target="after_add_cart_redirect">
                                    <?php $afterCartRedirect = getSetting($pdo, 'after_add_cart_redirect', 'stay'); ?>
                                    <option value="stay" <?php echo $afterCartRedirect === 'stay' ? 'selected' : ''; ?>>Permanecer na pagina</option>
                                    <option value="cart" <?php echo $afterCartRedirect === 'cart' ? 'selected' : ''; ?>>Ir para o carrinho</option>
                                    <option value="checkout" <?php echo $afterCartRedirect === 'checkout' ? 'selected' : ''; ?>>Ir direto para checkout</option>
                                </select>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Pagina Apos Finalizar Compra</label>
                                <input type="text" class="option-input" name="after_purchase_url" value="<?php echo htmlspecialchars(getSetting($pdo, 'after_purchase_url', '/obrigado.php')); ?>" data-target="after_purchase_url" placeholder="/obrigado.php">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Tipografia -->
                <div class="editor-panel" id="panel-typography">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-heading"></i> Fontes
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Fonte dos Titulos</label>
                                <select class="option-select" name="font_heading" data-target="font_heading">
                                    <?php foreach ($googleFonts as $font): ?>
                                    <option value="<?php echo $font; ?>" <?php echo $themeSettings['font_heading'] === $font ? 'selected' : ''; ?>><?php echo $font; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Fonte do Corpo</label>
                                <select class="option-select" name="font_body" data-target="font_body">
                                    <?php foreach ($googleFonts as $font): ?>
                                    <option value="<?php echo $font; ?>" <?php echo $themeSettings['font_body'] === $font ? 'selected' : ''; ?>><?php echo $font; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-text-height"></i> Tamanhos
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Tamanho Base do Texto</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="font_size_base" min="12" max="20" value="<?php echo $themeSettings['font_size_base']; ?>" data-target="font_size_base">
                                    <span class="range-value"><?php echo $themeSettings['font_size_base']; ?>px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Layout -->
                <div class="editor-panel" id="panel-layout">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-expand-arrows-alt"></i> Espacamento
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Arredondamento das Bordas</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="border_radius" min="0" max="24" value="<?php echo $themeSettings['border_radius']; ?>" data-target="border_radius">
                                    <span class="range-value"><?php echo $themeSettings['border_radius']; ?>px</span>
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Espacamento Base</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="spacing_unit" min="8" max="32" value="<?php echo $themeSettings['spacing_unit']; ?>" data-target="spacing_unit">
                                    <span class="range-value"><?php echo $themeSettings['spacing_unit']; ?>px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-columns"></i> Grid de Produtos
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Largura Maxima do Container</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="container_max_width" min="1000" max="1800" step="100" value="<?php echo $themeSettings['container_max_width']; ?>" data-target="container_max_width">
                                    <span class="range-value"><?php echo $themeSettings['container_max_width']; ?>px</span>
                                </div>
                            </div>
                            
                            <div class="option-item">
                                <label class="option-label">Produtos por Linha</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="products_per_row" min="2" max="6" value="<?php echo $themeSettings['products_per_row']; ?>" data-target="products_per_row">
                                    <span class="range-value"><?php echo $themeSettings['products_per_row']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Checkout -->
                <div class="editor-panel" id="panel-checkout">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-palette"></i> Cores do Checkout
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Fundo do Checkout</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'checkout_bg', '#f5f5f5'); ?>">
                                        <input type="color" name="checkout_bg" value="<?php echo getSetting($pdo, 'checkout_bg', '#f5f5f5'); ?>" data-target="checkout_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'checkout_bg', '#f5f5f5'); ?>" data-color="checkout_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Fundo dos Cards</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'checkout_card_bg', '#ffffff'); ?>">
                                        <input type="color" name="checkout_card_bg" value="<?php echo getSetting($pdo, 'checkout_card_bg', '#ffffff'); ?>" data-target="checkout_card_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'checkout_card_bg', '#ffffff'); ?>" data-color="checkout_card_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Botao de Pagamento</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'checkout_button_bg', '#C7A333'); ?>">
                                        <input type="color" name="checkout_button_bg" value="<?php echo getSetting($pdo, 'checkout_button_bg', '#C7A333'); ?>" data-target="checkout_button_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'checkout_button_bg', '#C7A333'); ?>" data-color="checkout_button_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Progresso Ativo</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'checkout_progress_active', '#C7A333'); ?>">
                                        <input type="color" name="checkout_progress_active" value="<?php echo getSetting($pdo, 'checkout_progress_active', '#C7A333'); ?>" data-target="checkout_progress_active">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'checkout_progress_active', '#C7A333'); ?>" data-color="checkout_progress_active">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-keyboard"></i> Placeholders do Checkout
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Placeholder do E-mail</label>
                                <input type="text" class="option-input" name="checkout_email_placeholder" value="<?php echo htmlspecialchars(getSetting($pdo, 'checkout_email_placeholder', 'seuemail@exemplo.com')); ?>" data-target="checkout_email_placeholder">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Placeholder do CEP</label>
                                <input type="text" class="option-input" name="checkout_cep_placeholder" value="<?php echo htmlspecialchars(getSetting($pdo, 'checkout_cep_placeholder', 'Digite seu CEP')); ?>" data-target="checkout_cep_placeholder">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Placeholder do Telefone</label>
                                <input type="text" class="option-input" name="checkout_phone_placeholder" value="<?php echo htmlspecialchars(getSetting($pdo, 'checkout_phone_placeholder', '(00) 00000-0000')); ?>" data-target="checkout_phone_placeholder">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Texto do Botao</label>
                                <input type="text" class="option-input" name="checkout_button_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'checkout_button_text', 'FINALIZAR COMPRA')); ?>" data-target="checkout_button_text" style="text-transform: uppercase;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Carrinho -->
                <div class="editor-panel" id="panel-cart">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-palette"></i> Cores do Carrinho
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Fundo do Carrinho</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'cart_bg', '#fef9e0'); ?>">
                                        <input type="color" name="cart_bg" value="<?php echo getSetting($pdo, 'cart_bg', '#fef9e0'); ?>" data-target="cart_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'cart_bg', '#fef9e0'); ?>" data-color="cart_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Fundo dos Itens</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'cart_item_bg', '#ffffff'); ?>">
                                        <input type="color" name="cart_item_bg" value="<?php echo getSetting($pdo, 'cart_item_bg', '#ffffff'); ?>" data-target="cart_item_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'cart_item_bg', '#ffffff'); ?>" data-color="cart_item_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Menu Fixo do Carrinho</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'cart_sticky_bg', '#1a1a1a'); ?>">
                                        <input type="color" name="cart_sticky_bg" value="<?php echo getSetting($pdo, 'cart_sticky_bg', '#1a1a1a'); ?>" data-target="cart_sticky_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'cart_sticky_bg', '#1a1a1a'); ?>" data-color="cart_sticky_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Botao do Carrinho</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'cart_button_bg', '#C7A333'); ?>">
                                        <input type="color" name="cart_button_bg" value="<?php echo getSetting($pdo, 'cart_button_bg', '#C7A333'); ?>" data-target="cart_button_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'cart_button_bg', '#C7A333'); ?>" data-color="cart_button_bg">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-file-alt"></i> Textos do Carrinho
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Titulo do Carrinho</label>
                                <input type="text" class="option-input" name="cart_title" value="<?php echo htmlspecialchars(getSetting($pdo, 'cart_title', 'MINHA SACOLA')); ?>" data-target="cart_title" style="text-transform: uppercase;">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Mensagem Carrinho Vazio</label>
                                <input type="text" class="option-input" name="cart_empty_message" value="<?php echo htmlspecialchars(getSetting($pdo, 'cart_empty_message', 'Sua sacola esta vazia')); ?>" data-target="cart_empty_message">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Texto Continuar Comprando</label>
                                <input type="text" class="option-input" name="cart_continue_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'cart_continue_text', 'Continuar Comprando')); ?>" data-target="cart_continue_text">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Texto Botao Checkout</label>
                                <input type="text" class="option-input" name="cart_checkout_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'cart_checkout_text', 'FINALIZAR PEDIDO')); ?>" data-target="cart_checkout_text" style="text-transform: uppercase;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Produtos -->
                <div class="editor-panel" id="panel-products">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-palette"></i> Cores dos Produtos
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Fundo do Card</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'product_card_bg', '#ffffff'); ?>">
                                        <input type="color" name="product_card_bg" value="<?php echo getSetting($pdo, 'product_card_bg', '#ffffff'); ?>" data-target="product_card_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'product_card_bg', '#ffffff'); ?>" data-color="product_card_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Cor do Preco</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'product_price_color', '#1a1a1a'); ?>">
                                        <input type="color" name="product_price_color" value="<?php echo getSetting($pdo, 'product_price_color', '#1a1a1a'); ?>" data-target="product_price_color">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'product_price_color', '#1a1a1a'); ?>" data-color="product_price_color">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Cor Preco Riscado</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'product_original_price_color', '#999999'); ?>">
                                        <input type="color" name="product_original_price_color" value="<?php echo getSetting($pdo, 'product_original_price_color', '#999999'); ?>" data-target="product_original_price_color">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'product_original_price_color', '#999999'); ?>" data-color="product_original_price_color">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Badge de Desconto</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'product_discount_bg', '#22c55e'); ?>">
                                        <input type="color" name="product_discount_bg" value="<?php echo getSetting($pdo, 'product_discount_bg', '#22c55e'); ?>" data-target="product_discount_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'product_discount_bg', '#22c55e'); ?>" data-color="product_discount_bg">
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Badge VIP</label>
                                <div class="color-picker-wrapper">
                                    <div class="color-preview" style="background: <?php echo getSetting($pdo, 'product_badge_vip_bg', '#C7A333'); ?>">
                                        <input type="color" name="product_badge_vip_bg" value="<?php echo getSetting($pdo, 'product_badge_vip_bg', '#C7A333'); ?>" data-target="product_badge_vip_bg">
                                    </div>
                                    <input type="text" class="color-hex" value="<?php echo getSetting($pdo, 'product_badge_vip_bg', '#C7A333'); ?>" data-color="product_badge_vip_bg">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-tags"></i> Textos e Labels
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Label VIP</label>
                                <input type="text" class="option-input" name="product_vip_label" value="<?php echo htmlspecialchars(getSetting($pdo, 'product_vip_label', 'VIP')); ?>" data-target="product_vip_label" style="text-transform: uppercase;">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Label Promocao</label>
                                <input type="text" class="option-input" name="product_sale_label" value="<?php echo htmlspecialchars(getSetting($pdo, 'product_sale_label', 'OFERTA')); ?>" data-target="product_sale_label" style="text-transform: uppercase;">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Texto Adicionar ao Carrinho</label>
                                <input type="text" class="option-input" name="product_add_to_cart_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'product_add_to_cart_text', 'ADICIONAR')); ?>" data-target="product_add_to_cart_text" style="text-transform: uppercase;">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Texto Esgotado</label>
                                <input type="text" class="option-input" name="product_out_of_stock_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'product_out_of_stock_text', 'ESGOTADO')); ?>" data-target="product_out_of_stock_text" style="text-transform: uppercase;">
                            </div>
                        </div>
                    </div>
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-toggle-on"></i> Exibicao
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Mostrar Descricao</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="show_product_description" value="1" <?php echo getSetting($pdo, 'show_product_description', '1') === '1' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Sim
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="show_product_description" value="0" <?php echo getSetting($pdo, 'show_product_description', '1') === '0' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Nao
                                    </label>
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Mostrar Marca</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="show_product_brand" value="1" <?php echo getSetting($pdo, 'show_product_brand', '1') === '1' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Sim
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="show_product_brand" value="0" <?php echo getSetting($pdo, 'show_product_brand', '1') === '0' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Nao
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Mobile -->
                <div class="editor-panel" id="panel-mobile">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-mobile-alt"></i> Layout Mobile
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Produtos por Linha (Mobile)</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="mobile_products_per_row" min="1" max="3" value="<?php echo getSetting($pdo, 'mobile_products_per_row', '2'); ?>" data-target="mobile_products_per_row">
                                    <span class="range-value"><?php echo getSetting($pdo, 'mobile_products_per_row', '2'); ?></span>
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Reducao do Tamanho da Fonte (%)</label>
                                <div class="range-wrapper">
                                    <input type="range" class="range-slider" name="mobile_font_size_reduction" min="0" max="30" value="<?php echo getSetting($pdo, 'mobile_font_size_reduction', '10'); ?>" data-target="mobile_font_size_reduction">
                                    <span class="range-value"><?php echo getSetting($pdo, 'mobile_font_size_reduction', '10'); ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-toggle-on"></i> Recursos Mobile
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Header Fixo (Sticky)</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="enable_sticky_header" value="1" <?php echo getSetting($pdo, 'enable_sticky_header', '1') === '1' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Sim
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="enable_sticky_header" value="0" <?php echo getSetting($pdo, 'enable_sticky_header', '1') === '0' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Nao
                                    </label>
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Botao Voltar ao Topo</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="enable_back_to_top" value="1" <?php echo getSetting($pdo, 'enable_back_to_top', '1') === '1' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Sim
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="enable_back_to_top" value="0" <?php echo getSetting($pdo, 'enable_back_to_top', '1') === '0' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Nao
                                    </label>
                                </div>
                            </div>
                            <div class="option-item">
                                <label class="option-label">Zoom em Imagens de Produto</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="enable_product_zoom" value="1" <?php echo getSetting($pdo, 'enable_product_zoom', '1') === '1' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Sim
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #fff;">
                                        <input type="radio" name="enable_product_zoom" value="0" <?php echo getSetting($pdo, 'enable_product_zoom', '1') === '0' ? 'checked' : ''; ?> style="accent-color: #d4af37;"> Nao
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </span>
                            <i class="fas fa-chevron-down option-group-toggle"></i>
                        </div>
                        <div class="option-group-content">
                            <div class="option-item">
                                <label class="option-label">Texto do Botao WhatsApp</label>
                                <input type="text" class="option-input" name="whatsapp_button_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'whatsapp_button_text', 'Falar com Especialista')); ?>" data-target="whatsapp_button_text">
                            </div>
                            <div class="option-item">
                                <label class="option-label">Mensagem Padrao WhatsApp</label>
                                <textarea class="option-input" name="whatsapp_message_template" data-target="whatsapp_message_template" rows="3" style="resize: vertical;"><?php echo htmlspecialchars(getSetting($pdo, 'whatsapp_message_template', 'Ola! Vim pelo site e gostaria de mais informacoes.')); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Historico -->
                <div class="editor-panel" id="panel-history">
                    <div class="option-group">
                        <div class="option-group-header">
                            <span class="option-group-title">
                                <i class="fas fa-clock"></i> Versoes Salvas
                            </span>
                        </div>
                        <div class="option-group-content">
                            <?php if (empty($themeVersions)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: #666;">
                                <i class="fas fa-history" style="font-size: 2.5rem; margin-bottom: 16px; opacity: 0.5;"></i>
                                <p style="margin: 0;">Nenhuma versao salva ainda.</p>
                                <p style="margin: 8px 0 0 0; font-size: 0.85rem;">Salve suas alteracoes para criar um ponto de restauracao.</p>
                            </div>
                            <?php else: ?>
                            <div class="version-list">
                                <div class="version-item current">
                                    <div class="version-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="version-info">
                                        <div class="version-name">Versao Atual</div>
                                        <div class="version-date">Agora</div>
                                    </div>
                                </div>
                                <?php foreach ($themeVersions as $version): ?>
                                <div class="version-item" data-version-id="<?php echo $version['id']; ?>">
                                    <div class="version-icon">
                                        <i class="fas fa-code-branch"></i>
                                    </div>
                                    <div class="version-info">
                                        <div class="version-name"><?php echo htmlspecialchars($version['name'] ?? 'Versao ' . $version['id']); ?></div>
                                        <div class="version-date"><?php echo date('d/m/Y H:i', strtotime($version['created_at'])); ?></div>
                                    </div>
                                    <div class="version-actions">
                                        <button class="version-btn restore" onclick="restoreVersion(<?php echo $version['id']; ?>)">
                                            <i class="fas fa-undo"></i> Restaurar
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Acoes -->
            <div class="editor-actions">
                <button class="btn-editor btn-reset" onclick="resetTheme()">
                    <i class="fas fa-undo"></i> Resetar
                </button>
                <button class="btn-editor btn-save" onclick="saveTheme()">
                    <i class="fas fa-save"></i> Publicar Alteracoes
                </button>
            </div>
        </aside>
        
        <!-- Area de Preview -->
        <main class="editor-preview">
            <div class="preview-toolbar">
                <div class="preview-devices">
                    <button class="device-btn active" data-device="desktop">
                        <i class="fas fa-desktop"></i> Desktop
                    </button>
                    <button class="device-btn" data-device="tablet">
                        <i class="fas fa-tablet-alt"></i> Tablet
                    </button>
                    <button class="device-btn" data-device="mobile">
                        <i class="fas fa-mobile-alt"></i> Mobile
                    </button>
                </div>
                
                <div class="preview-url">
                    <i class="fas fa-circle"></i>
                    <?php echo parse_url(APP_URL, PHP_URL_HOST) ?: 'vitrine.local'; ?>
                </div>
                
                <div class="preview-actions">
                    <button class="device-btn" onclick="refreshPreview()" title="Atualizar Preview">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <a href="<?php echo APP_URL; ?>" target="_blank" class="device-btn" title="Abrir em Nova Aba">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
            
            <div class="preview-frame-container">
                <div class="preview-frame-wrapper" id="previewWrapper">
                    <iframe src="<?php echo APP_URL; ?>?preview=1" class="preview-frame" id="previewFrame"></iframe>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Toast Notification -->
    <div class="editor-toast" id="editorToast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Alteracoes salvas com sucesso!</span>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <script>
    // Estado do tema
    let themeState = <?php echo json_encode($themeSettings); ?>;
    let originalState = JSON.parse(JSON.stringify(themeState));
    let hasChanges = false;
    
    // Presets de cores
    const colorPresets = {
        'gold-dark': {
            color_primary: '#d4af37',
            color_background: '#0a0a0a',
            color_text: '#ffffff',
            color_text_muted: '#888888',
            card_bg: '#141414',
            card_border: '#2a2a2a',
            button_primary_bg: '#d4af37',
            header_bg: '#0a0a0a',
            footer_bg: '#0a0a0a'
        },
        'modern-white': {
            color_primary: '#000000',
            color_background: '#ffffff',
            color_text: '#1a1a1a',
            color_text_muted: '#666666',
            card_bg: '#f5f5f5',
            card_border: '#e5e5e5',
            button_primary_bg: '#000000',
            header_bg: '#ffffff',
            footer_bg: '#f5f5f5'
        },
        'rose-gold': {
            color_primary: '#b76e79',
            color_background: '#1a1a1a',
            color_text: '#ffffff',
            color_text_muted: '#999999',
            card_bg: '#242424',
            card_border: '#333333',
            button_primary_bg: '#b76e79',
            header_bg: '#1a1a1a',
            footer_bg: '#141414'
        },
        'emerald': {
            color_primary: '#10b981',
            color_background: '#0d1f17',
            color_text: '#ffffff',
            color_text_muted: '#6ee7b7',
            card_bg: '#1a2e23',
            card_border: '#2a4033',
            button_primary_bg: '#10b981',
            header_bg: '#0d1f17',
            footer_bg: '#0a1810'
        }
    };
    
    // Inicializacao
    document.addEventListener('DOMContentLoaded', function() {
        initTabs();
        initColorPickers();
        initRangeSliders();
        initDeviceSwitcher();
        initPresets();
        initOptionGroups();
    });
    
    // Tabs
    function initTabs() {
        document.querySelectorAll('.editor-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.editor-panel').forEach(p => p.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById('panel-' + tabId).classList.add('active');
            });
        });
    }
    
    // Color Pickers
    function initColorPickers() {
        document.querySelectorAll('input[type="color"]').forEach(picker => {
            picker.addEventListener('input', function() {
                const target = this.dataset.target;
                const color = this.value;
                
                this.closest('.color-preview').style.background = color;
                this.closest('.color-picker-wrapper').querySelector('.color-hex').value = color;
                
                updateThemeValue(target, color);
            });
        });
        
        document.querySelectorAll('.color-hex').forEach(input => {
            input.addEventListener('input', function() {
                const colorKey = this.dataset.color;
                let color = this.value;
                
                if (!color.startsWith('#')) color = '#' + color;
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    this.closest('.color-picker-wrapper').querySelector('.color-preview').style.background = color;
                    this.closest('.color-picker-wrapper').querySelector('input[type="color"]').value = color;
                    updateThemeValue(colorKey, color);
                }
            });
        });
    }
    
    // Range Sliders
    function initRangeSliders() {
        document.querySelectorAll('.range-slider').forEach(slider => {
            slider.addEventListener('input', function() {
                const target = this.dataset.target;
                const value = this.value;
                let displayValue = value;
                
                if (target === 'products_per_row') {
                    displayValue = value;
                } else if (target === 'faixa_interval') {
                    displayValue = (parseInt(value) / 1000).toFixed(1) + 's';
                } else {
                    displayValue = value + 'px';
                }
                
                this.closest('.range-wrapper').querySelector('.range-value').textContent = displayValue;
                updateThemeValue(target, value);
            });
        });
    }
    
    // Selects
    document.querySelectorAll('.option-select').forEach(select => {
        select.addEventListener('change', function() {
            const target = this.dataset.target;
            const value = this.value;
            updateThemeValue(target, value);
        });
    });
    
    // Text Inputs
    document.querySelectorAll('.option-input[data-target]').forEach(input => {
        input.addEventListener('input', function() {
            const target = this.dataset.target;
            const value = this.value;
            updateThemeValue(target, value);
        });
    });
    
    // Radio buttons (faixa_enabled)
    document.querySelectorAll('input[name="faixa_enabled"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateThemeValue('faixa_enabled', this.value);
        });
    });
    
    // Frase/Link inputs
    document.querySelectorAll('.frase-text, .frase-link').forEach(input => {
        input.addEventListener('input', function() {
            hasChanges = true;
        });
    });
    
    // Funcao para coletar frases e links
    function collectFaixaData() {
        const frases = [];
        const links = [];
        
        for (let i = 0; i < 6; i++) {
            const fraseInput = document.querySelector(`input[name="frase_${i}"]`);
            const linkInput = document.querySelector(`input[name="link_${i}"]`);
            
            if (fraseInput) frases.push(fraseInput.value.trim());
            if (linkInput) links.push(linkInput.value.trim());
        }
        
        themeState.faixa_frases = frases.filter(f => f !== '').join('|');
        themeState.faixa_links = links.join('|');
    }
    
    // Funcao para coletar inputs de texto
    function collectTextInputs() {
        document.querySelectorAll('.option-input[name]').forEach(input => {
            const name = input.name;
            if (name && !name.startsWith('frase_') && !name.startsWith('link_')) {
                if (input.dataset.target) {
                    themeState[name] = input.value;
                }
            }
        });
    }
    
    // Device Switcher
    function initDeviceSwitcher() {
        document.querySelectorAll('.device-btn[data-device]').forEach(btn => {
            btn.addEventListener('click', function() {
                const device = this.dataset.device;
                
                document.querySelectorAll('.device-btn[data-device]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const wrapper = document.getElementById('previewWrapper');
                wrapper.className = 'preview-frame-wrapper';
                if (device !== 'desktop') {
                    wrapper.classList.add(device);
                }
            });
        });
    }
    
    // Presets
    function initPresets() {
        document.querySelectorAll('.preset-card').forEach(card => {
            card.addEventListener('click', function() {
                const preset = this.dataset.preset;
                if (colorPresets[preset]) {
                    applyPreset(colorPresets[preset]);
                    showToast('Preset aplicado! Clique em Publicar para salvar.');
                }
            });
        });
    }
    
    function applyPreset(preset) {
        Object.entries(preset).forEach(([key, value]) => {
            themeState[key] = value;
            
            // Atualizar UI
            const colorPicker = document.querySelector(`input[type="color"][data-target="${key}"]`);
            if (colorPicker) {
                colorPicker.value = value;
                colorPicker.closest('.color-preview').style.background = value;
                colorPicker.closest('.color-picker-wrapper').querySelector('.color-hex').value = value;
            }
        });
        
        hasChanges = true;
        applyPreviewStyles();
    }
    
    // Option Groups Toggle
    function initOptionGroups() {
        document.querySelectorAll('.option-group-header').forEach(header => {
            header.addEventListener('click', function() {
                this.closest('.option-group').classList.toggle('collapsed');
            });
        });
    }
    
    // Update Theme Value
    function updateThemeValue(key, value) {
        themeState[key] = value;
        hasChanges = true;
        applyPreviewStyles();
    }
    
    // Apply Preview Styles
    function applyPreviewStyles() {
        const iframe = document.getElementById('previewFrame');
        if (!iframe.contentWindow) return;
        
        try {
            const iframeDoc = iframe.contentWindow.document;
            let styleEl = iframeDoc.getElementById('theme-preview-styles');
            
            if (!styleEl) {
                styleEl = iframeDoc.createElement('style');
                styleEl.id = 'theme-preview-styles';
                iframeDoc.head.appendChild(styleEl);
            }
            
            styleEl.textContent = `
                :root {
                    --color-primary: ${themeState.color_primary} !important;
                    --color-background: ${themeState.color_background} !important;
                    --color-text: ${themeState.color_text} !important;
                    --color-text-muted: ${themeState.color_text_muted} !important;
                    --card-bg: ${themeState.card_bg} !important;
                    --card-border: ${themeState.card_border} !important;
                    --button-primary-bg: ${themeState.button_primary_bg} !important;
                    --header-bg: ${themeState.header_bg} !important;
                    --footer-bg: ${themeState.footer_bg} !important;
                    --border-radius: ${themeState.border_radius}px !important;
                    --spacing-unit: ${themeState.spacing_unit}px !important;
                    --font-size-base: ${themeState.font_size_base}px !important;
                }
                body { 
                    background-color: ${themeState.color_background} !important;
                    color: ${themeState.color_text} !important;
                    font-family: '${themeState.font_body}', sans-serif !important;
                }
                h1, h2, h3, h4, h5, h6 {
                    font-family: '${themeState.font_heading}', sans-serif !important;
                }
            `;
        } catch (e) {
            console.log('Could not apply preview styles (cross-origin)');
        }
    }
    
    // Save Theme
async function saveTheme() {
  showLoading(true);
  
  // Coletar frases e links da faixa rotativa
  collectFaixaData();
  
  // Coletar inputs de texto
  collectTextInputs();
  
  try {
  const response = await fetch('<?php echo APP_URL; ?>/api/admin/save-theme.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
  theme: themeState,
  create_version: true
  })
  });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Tema publicado com sucesso!');
                hasChanges = false;
                originalState = JSON.parse(JSON.stringify(themeState));
                refreshPreview();
            } else {
                showToast(data.error || 'Erro ao salvar tema', true);
            }
        } catch (error) {
            showToast('Erro de conexao', true);
            console.error(error);
        }
        
        showLoading(false);
    }
    
    // Reset Theme
    function resetTheme() {
        if (!confirm('Tem certeza que deseja resetar todas as alteracoes?')) return;
        
        themeState = JSON.parse(JSON.stringify(originalState));
        
        // Atualizar UI
        Object.entries(themeState).forEach(([key, value]) => {
            const colorPicker = document.querySelector(`input[type="color"][data-target="${key}"]`);
            if (colorPicker) {
                colorPicker.value = value;
                colorPicker.closest('.color-preview').style.background = value;
                colorPicker.closest('.color-picker-wrapper').querySelector('.color-hex').value = value;
            }
            
            const rangeSlider = document.querySelector(`.range-slider[data-target="${key}"]`);
            if (rangeSlider) {
                rangeSlider.value = value;
                const unit = key === 'products_per_row' ? '' : 'px';
                rangeSlider.closest('.range-wrapper').querySelector('.range-value').textContent = value + unit;
            }
            
            const select = document.querySelector(`.option-select[data-target="${key}"]`);
            if (select) {
                select.value = value;
            }
        });
        
        hasChanges = false;
        applyPreviewStyles();
        showToast('Alteracoes resetadas');
    }
    
    // Restore Version
    async function restoreVersion(versionId) {
        if (!confirm('Restaurar esta versao? As alteracoes atuais serao perdidas.')) return;
        
        showLoading(true);
        
        try {
            const response = await fetch('<?php echo APP_URL; ?>/api/admin/restore-theme-version.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ version_id: versionId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Versao restaurada!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.error || 'Erro ao restaurar', true);
            }
        } catch (error) {
            showToast('Erro de conexao', true);
        }
        
        showLoading(false);
    }
    
    // Refresh Preview
    function refreshPreview() {
        const iframe = document.getElementById('previewFrame');
        iframe.src = iframe.src;
    }
    
    // Show Toast
    function showToast(message, isError = false) {
        const toast = document.getElementById('editorToast');
        const msgEl = document.getElementById('toastMessage');
        
        msgEl.textContent = message;
        toast.classList.toggle('error', isError);
        toast.classList.add('show');
        
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
    
    // Show Loading
    function showLoading(show) {
        document.getElementById('loadingOverlay').classList.toggle('show', show);
    }
    
    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    </script>
</body>
</html>
