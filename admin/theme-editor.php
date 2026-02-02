<?php
/**
 * Visual Theme Editor - Estilo Elementor
 * Selecione elementos diretamente no preview e edite ao vivo
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar autenticacao
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Buscar configuracoes atuais
function getThemeSettings($pdo) {
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

$themeSettings = getThemeSettings($pdo);

// Valores padrao
$defaults = [
    'color_primary' => '#C7A333',
    'color_secondary' => '#1a1a1a',
    'color_background' => '#fef9e0',
    'color_text' => '#1a1a1a',
    'color_text_muted' => '#666666',
    'header_bg' => '#1a1a1a',
    'header_text' => '#ffffff',
    'card_bg' => '#ffffff',
    'card_border' => '#e5e5e5',
    'button_primary_bg' => '#C7A333',
    'button_primary_text' => '#000000',
    'footer_bg' => '#1a1a1a',
    'footer_text' => '#ffffff',
    'font_heading' => 'Poppins',
    'font_body' => 'Inter',
    'font_size_base' => '16',
    'border_radius' => '12',
    'products_per_row' => '4'
];

foreach ($defaults as $key => $value) {
    if (!isset($themeSettings[$key])) {
        $themeSettings[$key] = $value;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor Visual - Naipers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --editor-bg: #0f0f0f;
            --editor-sidebar: #1a1a1a;
            --editor-border: #2a2a2a;
            --editor-text: #ffffff;
            --editor-muted: #888888;
            --editor-accent: #C7A333;
            --editor-accent-hover: #d4af37;
            --editor-success: #22c55e;
            --editor-danger: #ef4444;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--editor-bg);
            color: var(--editor-text);
            overflow: hidden;
            height: 100vh;
        }
        
        /* Layout Principal */
        .editor-layout {
            display: flex;
            height: 100vh;
        }
        
        /* Sidebar Esquerda - Elementos */
        .editor-sidebar {
            width: 320px;
            background: var(--editor-sidebar);
            border-right: 1px solid var(--editor-border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        
        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--editor-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .sidebar-logo i {
            color: var(--editor-accent);
            font-size: 1.3rem;
        }
        
        .btn-back {
            padding: 8px 12px;
            background: transparent;
            border: 1px solid var(--editor-border);
            color: var(--editor-muted);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            border-color: var(--editor-accent);
            color: var(--editor-accent);
        }
        
        /* Tabs de Navegacao */
        .sidebar-tabs {
            display: flex;
            border-bottom: 1px solid var(--editor-border);
        }
        
        .sidebar-tab {
            flex: 1;
            padding: 14px 10px;
            background: transparent;
            border: none;
            color: var(--editor-muted);
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        
        .sidebar-tab i { font-size: 1.1rem; }
        
        .sidebar-tab:hover { color: var(--editor-text); background: rgba(255,255,255,0.03); }
        
        .sidebar-tab.active {
            color: var(--editor-accent);
            background: rgba(199, 163, 51, 0.1);
            border-bottom: 2px solid var(--editor-accent);
        }
        
        /* Conteudo da Sidebar */
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        .sidebar-panel { display: none; }
        .sidebar-panel.active { display: block; }
        
        /* Grupos de Opcoes */
        .option-section {
            margin-bottom: 20px;
        }
        
        .option-section-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--editor-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--editor-border);
        }
        
        .option-row {
            margin-bottom: 14px;
        }
        
        .option-label {
            display: block;
            font-size: 0.8rem;
            color: var(--editor-text);
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        /* Color Picker Melhorado */
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            border-radius: 8px;
            padding: 4px;
        }
        
        .color-swatch {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            border: 2px solid var(--editor-border);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .color-swatch input[type="color"] {
            position: absolute;
            width: 150%;
            height: 150%;
            top: -25%;
            left: -25%;
            cursor: pointer;
            border: none;
        }
        
        .color-hex-input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--editor-text);
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.85rem;
            padding: 8px;
            outline: none;
        }
        
        /* Text Input */
        .text-input {
            width: 100%;
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--editor-text);
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .text-input:focus {
            border-color: var(--editor-accent);
        }
        
        /* Select */
        .select-input {
            width: 100%;
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--editor-text);
            font-size: 0.85rem;
            outline: none;
            cursor: pointer;
        }
        
        /* Range Slider */
        .range-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .range-slider {
            flex: 1;
            -webkit-appearance: none;
            height: 6px;
            background: var(--editor-border);
            border-radius: 3px;
            outline: none;
        }
        
        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: var(--editor-accent);
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }
        
        .range-value {
            min-width: 45px;
            text-align: right;
            font-size: 0.85rem;
            color: var(--editor-muted);
            font-family: 'Monaco', monospace;
        }
        
        /* Elemento Selecionado Info */
        .selected-element-info {
            background: linear-gradient(135deg, rgba(199,163,51,0.15), rgba(199,163,51,0.05));
            border: 1px solid var(--editor-accent);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 16px;
        }
        
        .selected-element-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--editor-accent);
            color: #000;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .selected-element-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--editor-text);
        }
        
        .no-selection-msg {
            text-align: center;
            padding: 40px 20px;
            color: var(--editor-muted);
        }
        
        .no-selection-msg i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.4;
        }
        
        .no-selection-msg p {
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Area de Preview */
        .editor-preview {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #0a0a0a;
        }
        
        /* Toolbar Superior */
        .preview-toolbar {
            padding: 10px 20px;
            background: var(--editor-sidebar);
            border-bottom: 1px solid var(--editor-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .device-switcher {
            display: flex;
            background: var(--editor-bg);
            border-radius: 8px;
            padding: 4px;
        }
        
        .device-btn {
            padding: 8px 14px;
            background: transparent;
            border: none;
            color: var(--editor-muted);
            cursor: pointer;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .device-btn:hover { color: var(--editor-text); }
        
        .device-btn.active {
            background: var(--editor-accent);
            color: #000;
        }
        
        .page-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-selector select {
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            color: var(--editor-text);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .toolbar-center {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .selection-mode-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .selection-mode-toggle.active {
            background: var(--editor-accent);
            border-color: var(--editor-accent);
            color: #000;
        }
        
        .selection-mode-toggle input {
            display: none;
        }
        
        .selection-mode-toggle span {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-secondary {
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            color: var(--editor-text);
        }
        
        .btn-secondary:hover {
            border-color: var(--editor-accent);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--editor-accent), var(--editor-accent-hover));
            color: #000;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(199, 163, 51, 0.4);
        }
        
        /* Frame do Preview */
        .preview-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }
        
        .preview-frame-wrapper {
            width: 100%;
            height: 100%;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        
        .preview-frame-wrapper.tablet {
            width: 768px;
            max-width: 100%;
        }
        
        .preview-frame-wrapper.mobile {
            width: 375px;
            max-width: 100%;
        }
        
        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--editor-sidebar);
            border: 1px solid var(--editor-border);
            padding: 14px 24px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        .toast.success { border-color: var(--editor-success); }
        .toast.success i { color: var(--editor-success); }
        
        .toast.error { border-color: var(--editor-danger); }
        .toast.error i { color: var(--editor-danger); }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10001;
        }
        
        .loading-overlay.show { display: flex; }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--editor-border);
            border-top-color: var(--editor-accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Presets Grid */
        .presets-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .preset-card {
            background: var(--editor-bg);
            border: 2px solid var(--editor-border);
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .preset-card:hover {
            border-color: var(--editor-accent);
            transform: translateY(-2px);
        }
        
        .preset-colors {
            display: flex;
            gap: 4px;
            margin-bottom: 8px;
        }
        
        .preset-color {
            width: 24px;
            height: 24px;
            border-radius: 4px;
        }
        
        .preset-name {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--editor-text);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 16px;
        }
        
        .quick-action-btn {
            padding: 12px;
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            border-radius: 8px;
            color: var(--editor-text);
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            border-color: var(--editor-accent);
            background: rgba(199,163,51,0.1);
        }
        
        .quick-action-btn i {
            font-size: 1.2rem;
            color: var(--editor-accent);
        }

        /* Scrollbar */
        .sidebar-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-content::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar-content::-webkit-scrollbar-thumb {
            background: var(--editor-border);
            border-radius: 3px;
        }
        
        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: var(--editor-muted);
        }
        
        /* Undo/Redo */
        .history-controls {
            display: flex;
            gap: 4px;
        }
        
        .history-btn {
            padding: 8px 10px;
            background: var(--editor-bg);
            border: 1px solid var(--editor-border);
            color: var(--editor-muted);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .history-btn:hover:not(:disabled) {
            color: var(--editor-text);
            border-color: var(--editor-accent);
        }
        
        .history-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="editor-layout">
        <!-- Sidebar Esquerda -->
        <aside class="editor-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <span>Editor Visual</span>
                </div>
                <a href="<?php echo APP_URL; ?>/admin/" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
            </div>
            
            <!-- Tabs -->
            <div class="sidebar-tabs">
                <button class="sidebar-tab active" data-tab="styles">
                    <i class="fas fa-palette"></i>
                    Estilos
                </button>
                <button class="sidebar-tab" data-tab="elements">
                    <i class="fas fa-mouse-pointer"></i>
                    Elemento
                </button>
                <button class="sidebar-tab" data-tab="presets">
                    <i class="fas fa-swatchbook"></i>
                    Presets
                </button>
            </div>
            
            <!-- Conteudo -->
            <div class="sidebar-content">
                <!-- Panel: Estilos Globais -->
                <div class="sidebar-panel active" id="panel-styles">
                    <div class="option-section">
                        <div class="option-section-title">Cores Principais</div>
                        
                        <div class="option-row">
                            <label class="option-label">Cor Primaria (Dourado)</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['color_primary']; ?>">
                                    <input type="color" data-key="color_primary" value="<?php echo $themeSettings['color_primary']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="color_primary" value="<?php echo $themeSettings['color_primary']; ?>">
                            </div>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Cor de Fundo</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['color_background']; ?>">
                                    <input type="color" data-key="color_background" value="<?php echo $themeSettings['color_background']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="color_background" value="<?php echo $themeSettings['color_background']; ?>">
                            </div>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Cor do Texto</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['color_text']; ?>">
                                    <input type="color" data-key="color_text" value="<?php echo $themeSettings['color_text']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="color_text" value="<?php echo $themeSettings['color_text']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="option-section">
                        <div class="option-section-title">Header & Footer</div>
                        
                        <div class="option-row">
                            <label class="option-label">Fundo do Header</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['header_bg']; ?>">
                                    <input type="color" data-key="header_bg" value="<?php echo $themeSettings['header_bg']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="header_bg" value="<?php echo $themeSettings['header_bg']; ?>">
                            </div>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Fundo do Footer</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['footer_bg']; ?>">
                                    <input type="color" data-key="footer_bg" value="<?php echo $themeSettings['footer_bg']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="footer_bg" value="<?php echo $themeSettings['footer_bg']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="option-section">
                        <div class="option-section-title">Cards & Botoes</div>
                        
                        <div class="option-row">
                            <label class="option-label">Fundo dos Cards</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['card_bg']; ?>">
                                    <input type="color" data-key="card_bg" value="<?php echo $themeSettings['card_bg']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="card_bg" value="<?php echo $themeSettings['card_bg']; ?>">
                            </div>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Cor do Botao Primario</label>
                            <div class="color-input-group">
                                <div class="color-swatch" style="background: <?php echo $themeSettings['button_primary_bg']; ?>">
                                    <input type="color" data-key="button_primary_bg" value="<?php echo $themeSettings['button_primary_bg']; ?>">
                                </div>
                                <input type="text" class="color-hex-input" data-key="button_primary_bg" value="<?php echo $themeSettings['button_primary_bg']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="option-section">
                        <div class="option-section-title">Tipografia</div>
                        
                        <div class="option-row">
                            <label class="option-label">Fonte dos Titulos</label>
                            <select class="select-input" data-key="font_heading">
                                <option value="Poppins" <?php echo $themeSettings['font_heading'] === 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                <option value="Inter" <?php echo $themeSettings['font_heading'] === 'Inter' ? 'selected' : ''; ?>>Inter</option>
                                <option value="Montserrat" <?php echo $themeSettings['font_heading'] === 'Montserrat' ? 'selected' : ''; ?>>Montserrat</option>
                                <option value="Roboto" <?php echo $themeSettings['font_heading'] === 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                <option value="Playfair Display" <?php echo $themeSettings['font_heading'] === 'Playfair Display' ? 'selected' : ''; ?>>Playfair Display</option>
                            </select>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Fonte do Corpo</label>
                            <select class="select-input" data-key="font_body">
                                <option value="Inter" <?php echo $themeSettings['font_body'] === 'Inter' ? 'selected' : ''; ?>>Inter</option>
                                <option value="Poppins" <?php echo $themeSettings['font_body'] === 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                <option value="Open Sans" <?php echo $themeSettings['font_body'] === 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                                <option value="Roboto" <?php echo $themeSettings['font_body'] === 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                <option value="Lato" <?php echo $themeSettings['font_body'] === 'Lato' ? 'selected' : ''; ?>>Lato</option>
                            </select>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Tamanho Base</label>
                            <div class="range-group">
                                <input type="range" class="range-slider" data-key="font_size_base" min="12" max="20" value="<?php echo $themeSettings['font_size_base']; ?>">
                                <span class="range-value"><?php echo $themeSettings['font_size_base']; ?>px</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="option-section">
                        <div class="option-section-title">Layout</div>
                        
                        <div class="option-row">
                            <label class="option-label">Arredondamento</label>
                            <div class="range-group">
                                <input type="range" class="range-slider" data-key="border_radius" min="0" max="24" value="<?php echo $themeSettings['border_radius']; ?>">
                                <span class="range-value"><?php echo $themeSettings['border_radius']; ?>px</span>
                            </div>
                        </div>
                        
                        <div class="option-row">
                            <label class="option-label">Produtos por Linha</label>
                            <div class="range-group">
                                <input type="range" class="range-slider" data-key="products_per_row" min="2" max="6" value="<?php echo $themeSettings['products_per_row']; ?>">
                                <span class="range-value"><?php echo $themeSettings['products_per_row']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Panel: Elemento Selecionado -->
                <div class="sidebar-panel" id="panel-elements">
                    <div id="elementEditor">
                        <div class="no-selection-msg">
                            <i class="fas fa-mouse-pointer"></i>
                            <p>Ative o modo de selecao na barra superior e clique em um elemento no preview para edita-lo.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Panel: Presets -->
                <div class="sidebar-panel" id="panel-presets">
                    <div class="option-section">
                        <div class="option-section-title">Temas Prontos</div>
                        
                        <div class="presets-grid">
                            <div class="preset-card" data-preset="gold-dark">
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #C7A333"></div>
                                    <div class="preset-color" style="background: #1a1a1a"></div>
                                    <div class="preset-color" style="background: #fef9e0"></div>
                                </div>
                                <div class="preset-name">Ouro Classico</div>
                            </div>
                            
                            <div class="preset-card" data-preset="modern-light">
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #3b82f6"></div>
                                    <div class="preset-color" style="background: #1e293b"></div>
                                    <div class="preset-color" style="background: #f8fafc"></div>
                                </div>
                                <div class="preset-name">Moderno Azul</div>
                            </div>
                            
                            <div class="preset-card" data-preset="rose-gold">
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #f43f5e"></div>
                                    <div class="preset-color" style="background: #1a1a1a"></div>
                                    <div class="preset-color" style="background: #fff1f2"></div>
                                </div>
                                <div class="preset-name">Rose Gold</div>
                            </div>
                            
                            <div class="preset-card" data-preset="emerald">
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #10b981"></div>
                                    <div class="preset-color" style="background: #064e3b"></div>
                                    <div class="preset-color" style="background: #ecfdf5"></div>
                                </div>
                                <div class="preset-name">Esmeralda</div>
                            </div>
                            
                            <div class="preset-card" data-preset="dark-mode">
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #d4af37"></div>
                                    <div class="preset-color" style="background: #0a0a0a"></div>
                                    <div class="preset-color" style="background: #171717"></div>
                                </div>
                                <div class="preset-name">Modo Escuro</div>
                            </div>
                            
                            <div class="preset-card" data-preset="minimal">
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #000000"></div>
                                    <div class="preset-color" style="background: #ffffff"></div>
                                    <div class="preset-color" style="background: #f5f5f5"></div>
                                </div>
                                <div class="preset-name">Minimalista</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="resetToDefaults()">
                            <i class="fas fa-undo"></i>
                            <span>Resetar</span>
                        </button>
                        <button class="quick-action-btn" onclick="exportTheme()">
                            <i class="fas fa-download"></i>
                            <span>Exportar</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Area de Preview -->
        <main class="editor-preview">
            <div class="preview-toolbar">
                <div class="toolbar-left">
                    <div class="device-switcher">
                        <button class="device-btn active" data-device="desktop" title="Desktop">
                            <i class="fas fa-desktop"></i>
                        </button>
                        <button class="device-btn" data-device="tablet" title="Tablet">
                            <i class="fas fa-tablet-alt"></i>
                        </button>
                        <button class="device-btn" data-device="mobile" title="Mobile">
                            <i class="fas fa-mobile-alt"></i>
                        </button>
                    </div>
                    
                    <div class="page-selector">
                        <i class="fas fa-file" style="color: var(--editor-muted);"></i>
                        <select id="pageSelector">
                            <option value="<?php echo APP_URL; ?>/">Pagina Inicial</option>
                            <option value="<?php echo APP_URL; ?>/carrinho.php">Carrinho</option>
                            <option value="<?php echo APP_URL; ?>/checkout-entrega.php">Checkout</option>
                        </select>
                    </div>
                </div>
                
                <div class="toolbar-center">
                    <label class="selection-mode-toggle" id="selectionModeToggle">
                        <input type="checkbox" id="selectionModeCheckbox">
                        <i class="fas fa-crosshairs"></i>
                        <span>Modo Selecao</span>
                    </label>
                </div>
                
                <div class="toolbar-right">
                    <div class="history-controls">
                        <button class="history-btn" id="undoBtn" disabled title="Desfazer">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button class="history-btn" id="redoBtn" disabled title="Refazer">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                    
                    <button class="btn-action btn-secondary" onclick="refreshPreview()">
                        <i class="fas fa-sync-alt"></i>
                        Atualizar
                    </button>
                    
                    <button class="btn-action btn-primary" onclick="saveTheme()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Publicar
                    </button>
                </div>
            </div>
            
            <div class="preview-container">
                <div class="preview-frame-wrapper" id="previewWrapper">
                    <iframe src="<?php echo APP_URL; ?>/" class="preview-frame" id="previewFrame"></iframe>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Toast -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Salvo com sucesso!</span>
    </div>
    
    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <script>
    // Estado do tema
    let themeState = <?php echo json_encode($themeSettings); ?>;
    let originalState = JSON.parse(JSON.stringify(themeState));
    let historyStack = [];
    let historyIndex = -1;
    let selectionMode = false;
    let selectedElement = null;
    
    // Presets
    const presets = {
        'gold-dark': {
            color_primary: '#C7A333',
            color_background: '#fef9e0',
            color_text: '#1a1a1a',
            header_bg: '#1a1a1a',
            footer_bg: '#1a1a1a',
            card_bg: '#ffffff',
            button_primary_bg: '#C7A333'
        },
        'modern-light': {
            color_primary: '#3b82f6',
            color_background: '#f8fafc',
            color_text: '#1e293b',
            header_bg: '#1e293b',
            footer_bg: '#1e293b',
            card_bg: '#ffffff',
            button_primary_bg: '#3b82f6'
        },
        'rose-gold': {
            color_primary: '#f43f5e',
            color_background: '#fff1f2',
            color_text: '#1a1a1a',
            header_bg: '#1a1a1a',
            footer_bg: '#1a1a1a',
            card_bg: '#ffffff',
            button_primary_bg: '#f43f5e'
        },
        'emerald': {
            color_primary: '#10b981',
            color_background: '#ecfdf5',
            color_text: '#064e3b',
            header_bg: '#064e3b',
            footer_bg: '#064e3b',
            card_bg: '#ffffff',
            button_primary_bg: '#10b981'
        },
        'dark-mode': {
            color_primary: '#d4af37',
            color_background: '#171717',
            color_text: '#ffffff',
            header_bg: '#0a0a0a',
            footer_bg: '#0a0a0a',
            card_bg: '#262626',
            button_primary_bg: '#d4af37'
        },
        'minimal': {
            color_primary: '#000000',
            color_background: '#ffffff',
            color_text: '#000000',
            header_bg: '#ffffff',
            footer_bg: '#000000',
            card_bg: '#f5f5f5',
            button_primary_bg: '#000000'
        }
    };
    
    // Inicializacao
    document.addEventListener('DOMContentLoaded', function() {
        initTabs();
        initColorPickers();
        initRangeSliders();
        initSelects();
        initDeviceSwitcher();
        initPresets();
        initSelectionMode();
        initPageSelector();
        saveHistory();
    });
    
    // Tabs
    function initTabs() {
        document.querySelectorAll('.sidebar-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.sidebar-panel').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('panel-' + tabId).classList.add('active');
            });
        });
    }
    
    // Color Pickers
    function initColorPickers() {
        document.querySelectorAll('.color-swatch input[type="color"]').forEach(picker => {
            picker.addEventListener('input', function() {
                const key = this.dataset.key;
                const color = this.value;
                updateColor(key, color);
            });
        });
        
        document.querySelectorAll('.color-hex-input').forEach(input => {
            input.addEventListener('input', function() {
                const key = this.dataset.key;
                let color = this.value;
                if (!color.startsWith('#')) color = '#' + color;
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    updateColor(key, color);
                }
            });
        });
    }
    
    function updateColor(key, color) {
        themeState[key] = color;
        
        // Atualizar UI
        const swatch = document.querySelector(`.color-swatch input[data-key="${key}"]`);
        const hexInput = document.querySelector(`.color-hex-input[data-key="${key}"]`);
        
        if (swatch) {
            swatch.value = color;
            swatch.parentElement.style.background = color;
        }
        if (hexInput) hexInput.value = color;
        
        applyPreviewStyles();
        saveHistory();
    }
    
    // Range Sliders
    function initRangeSliders() {
        document.querySelectorAll('.range-slider').forEach(slider => {
            slider.addEventListener('input', function() {
                const key = this.dataset.key;
                const value = this.value;
                themeState[key] = value;
                
                const unit = key === 'products_per_row' ? '' : 'px';
                this.closest('.range-group').querySelector('.range-value').textContent = value + unit;
                
                applyPreviewStyles();
                saveHistory();
            });
        });
    }
    
    // Selects
    function initSelects() {
        document.querySelectorAll('.select-input').forEach(select => {
            select.addEventListener('change', function() {
                const key = this.dataset.key;
                themeState[key] = this.value;
                applyPreviewStyles();
                saveHistory();
            });
        });
    }
    
    // Device Switcher
    function initDeviceSwitcher() {
        document.querySelectorAll('.device-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const device = this.dataset.device;
                document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const wrapper = document.getElementById('previewWrapper');
                wrapper.className = 'preview-frame-wrapper';
                if (device !== 'desktop') wrapper.classList.add(device);
            });
        });
    }
    
    // Presets
    function initPresets() {
        document.querySelectorAll('.preset-card').forEach(card => {
            card.addEventListener('click', function() {
                const presetName = this.dataset.preset;
                if (presets[presetName]) {
                    applyPreset(presets[presetName]);
                    showToast('Preset aplicado!', 'success');
                }
            });
        });
    }
    
    function applyPreset(preset) {
        Object.entries(preset).forEach(([key, value]) => {
            themeState[key] = value;
            
            // Atualizar UI
            const swatch = document.querySelector(`.color-swatch input[data-key="${key}"]`);
            const hexInput = document.querySelector(`.color-hex-input[data-key="${key}"]`);
            
            if (swatch) {
                swatch.value = value;
                swatch.parentElement.style.background = value;
            }
            if (hexInput) hexInput.value = value;
        });
        
        applyPreviewStyles();
        saveHistory();
    }
    
    // Selection Mode
    function initSelectionMode() {
        const toggle = document.getElementById('selectionModeToggle');
        const checkbox = document.getElementById('selectionModeCheckbox');
        
        toggle.addEventListener('click', function() {
            checkbox.checked = !checkbox.checked;
            selectionMode = checkbox.checked;
            toggle.classList.toggle('active', selectionMode);
            
            updateIframeSelectionMode();
        });
    }
    
    function updateIframeSelectionMode() {
        const iframe = document.getElementById('previewFrame');
        try {
            const iframeDoc = iframe.contentWindow.document;
            
            if (selectionMode) {
                // Injetar estilos de selecao
                let styleEl = iframeDoc.getElementById('selection-mode-styles');
                if (!styleEl) {
                    styleEl = iframeDoc.createElement('style');
                    styleEl.id = 'selection-mode-styles';
                    iframeDoc.head.appendChild(styleEl);
                }
                styleEl.textContent = `
                    * { cursor: crosshair !important; }
                    .v0-hover-highlight {
                        outline: 2px dashed #C7A333 !important;
                        outline-offset: 2px !important;
                    }
                    .v0-selected {
                        outline: 3px solid #C7A333 !important;
                        outline-offset: 2px !important;
                    }
                `;
                
                // Adicionar listeners
                iframeDoc.body.addEventListener('mouseover', handleElementHover);
                iframeDoc.body.addEventListener('mouseout', handleElementOut);
                iframeDoc.body.addEventListener('click', handleElementClick);
            } else {
                // Remover estilos
                const styleEl = iframeDoc.getElementById('selection-mode-styles');
                if (styleEl) styleEl.remove();
                
                // Remover listeners
                iframeDoc.body.removeEventListener('mouseover', handleElementHover);
                iframeDoc.body.removeEventListener('mouseout', handleElementOut);
                iframeDoc.body.removeEventListener('click', handleElementClick);
                
                // Limpar highlights
                iframeDoc.querySelectorAll('.v0-hover-highlight, .v0-selected').forEach(el => {
                    el.classList.remove('v0-hover-highlight', 'v0-selected');
                });
            }
        } catch (e) {
            console.log('[v0] Cross-origin iframe, selection mode limited');
        }
    }
    
    function handleElementHover(e) {
        e.target.classList.add('v0-hover-highlight');
    }
    
    function handleElementOut(e) {
        e.target.classList.remove('v0-hover-highlight');
    }
    
    function handleElementClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const iframe = document.getElementById('previewFrame');
        const iframeDoc = iframe.contentWindow.document;
        
        // Remover selecao anterior
        iframeDoc.querySelectorAll('.v0-selected').forEach(el => {
            el.classList.remove('v0-selected');
        });
        
        // Selecionar elemento
        e.target.classList.add('v0-selected');
        selectedElement = e.target;
        
        // Mostrar editor do elemento
        showElementEditor(e.target);
        
        // Mudar para aba de elementos
        document.querySelector('.sidebar-tab[data-tab="elements"]').click();
    }
    
    function showElementEditor(element) {
        const tagName = element.tagName.toLowerCase();
        const classes = element.className.replace('v0-hover-highlight', '').replace('v0-selected', '').trim();
        const computedStyle = window.getComputedStyle(element);
        
        let editorHTML = `
            <div class="selected-element-info">
                <div class="selected-element-tag">
                    <i class="fas fa-code"></i>
                    ${tagName}
                </div>
                <div class="selected-element-name">${classes || 'Elemento sem classe'}</div>
            </div>
            
            <div class="option-section">
                <div class="option-section-title">Estilos do Elemento</div>
                
                <div class="option-row">
                    <label class="option-label">Cor de Fundo</label>
                    <div class="color-input-group">
                        <div class="color-swatch" style="background: ${rgbToHex(computedStyle.backgroundColor)}">
                            <input type="color" data-style="backgroundColor" value="${rgbToHex(computedStyle.backgroundColor)}">
                        </div>
                        <input type="text" class="color-hex-input" data-style="backgroundColor" value="${rgbToHex(computedStyle.backgroundColor)}">
                    </div>
                </div>
                
                <div class="option-row">
                    <label class="option-label">Cor do Texto</label>
                    <div class="color-input-group">
                        <div class="color-swatch" style="background: ${rgbToHex(computedStyle.color)}">
                            <input type="color" data-style="color" value="${rgbToHex(computedStyle.color)}">
                        </div>
                        <input type="text" class="color-hex-input" data-style="color" value="${rgbToHex(computedStyle.color)}">
                    </div>
                </div>
                
                <div class="option-row">
                    <label class="option-label">Tamanho da Fonte</label>
                    <div class="range-group">
                        <input type="range" class="range-slider" data-style="fontSize" min="8" max="48" value="${parseInt(computedStyle.fontSize)}">
                        <span class="range-value">${parseInt(computedStyle.fontSize)}px</span>
                    </div>
                </div>
                
                <div class="option-row">
                    <label class="option-label">Padding</label>
                    <div class="range-group">
                        <input type="range" class="range-slider" data-style="padding" min="0" max="60" value="${parseInt(computedStyle.padding) || 0}">
                        <span class="range-value">${parseInt(computedStyle.padding) || 0}px</span>
                    </div>
                </div>
                
                <div class="option-row">
                    <label class="option-label">Border Radius</label>
                    <div class="range-group">
                        <input type="range" class="range-slider" data-style="borderRadius" min="0" max="30" value="${parseInt(computedStyle.borderRadius) || 0}">
                        <span class="range-value">${parseInt(computedStyle.borderRadius) || 0}px</span>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('elementEditor').innerHTML = editorHTML;
        
        // Adicionar listeners para os novos inputs
        initElementEditorListeners();
    }
    
    function initElementEditorListeners() {
        // Color pickers do elemento
        document.querySelectorAll('#elementEditor .color-swatch input[type="color"]').forEach(picker => {
            picker.addEventListener('input', function() {
                const style = this.dataset.style;
                const color = this.value;
                if (selectedElement) {
                    selectedElement.style[style] = color;
                    this.parentElement.style.background = color;
                    this.closest('.color-input-group').querySelector('.color-hex-input').value = color;
                }
            });
        });
        
        document.querySelectorAll('#elementEditor .color-hex-input').forEach(input => {
            input.addEventListener('input', function() {
                const style = this.dataset.style;
                let color = this.value;
                if (!color.startsWith('#')) color = '#' + color;
                if (/^#[0-9A-Fa-f]{6}$/.test(color) && selectedElement) {
                    selectedElement.style[style] = color;
                    this.closest('.color-input-group').querySelector('.color-swatch').style.background = color;
                    this.closest('.color-input-group').querySelector('input[type="color"]').value = color;
                }
            });
        });
        
        // Range sliders do elemento
        document.querySelectorAll('#elementEditor .range-slider').forEach(slider => {
            slider.addEventListener('input', function() {
                const style = this.dataset.style;
                const value = this.value + 'px';
                if (selectedElement) {
                    selectedElement.style[style] = value;
                    this.closest('.range-group').querySelector('.range-value').textContent = value;
                }
            });
        });
    }
    
    function rgbToHex(rgb) {
        if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') return '#ffffff';
        
        const match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) return '#ffffff';
        
        const r = parseInt(match[1]).toString(16).padStart(2, '0');
        const g = parseInt(match[2]).toString(16).padStart(2, '0');
        const b = parseInt(match[3]).toString(16).padStart(2, '0');
        
        return '#' + r + g + b;
    }
    
    // Page Selector
    function initPageSelector() {
        document.getElementById('pageSelector').addEventListener('change', function() {
            document.getElementById('previewFrame').src = this.value;
            setTimeout(() => {
                applyPreviewStyles();
                if (selectionMode) updateIframeSelectionMode();
            }, 1000);
        });
    }
    
    // Apply Preview Styles
    function applyPreviewStyles() {
        const iframe = document.getElementById('previewFrame');
        try {
            const iframeDoc = iframe.contentWindow.document;
            let styleEl = iframeDoc.getElementById('theme-live-styles');
            
            if (!styleEl) {
                styleEl = iframeDoc.createElement('style');
                styleEl.id = 'theme-live-styles';
                iframeDoc.head.appendChild(styleEl);
            }
            
            styleEl.textContent = `
                :root {
                    --color-primary: ${themeState.color_primary} !important;
                    --color-background: ${themeState.color_background} !important;
                    --color-text: ${themeState.color_text} !important;
                    --header-bg: ${themeState.header_bg} !important;
                    --footer-bg: ${themeState.footer_bg} !important;
                    --card-bg: ${themeState.card_bg} !important;
                    --button-primary-bg: ${themeState.button_primary_bg} !important;
                    --border-radius: ${themeState.border_radius}px !important;
                }
                body { 
                    background-color: ${themeState.color_background} !important;
                    color: ${themeState.color_text} !important;
                    font-family: '${themeState.font_body}', sans-serif !important;
                    font-size: ${themeState.font_size_base}px !important;
                }
                h1, h2, h3, h4, h5, h6 {
                    font-family: '${themeState.font_heading}', sans-serif !important;
                }
                .public-header, header {
                    background: ${themeState.header_bg} !important;
                }
                footer, .footer {
                    background: ${themeState.footer_bg} !important;
                }
                .product-card-modern, .card {
                    background: ${themeState.card_bg} !important;
                    border-radius: ${themeState.border_radius}px !important;
                }
                .btn-add-cart, .btn-primary, .cta-button, button[type="submit"] {
                    background: ${themeState.button_primary_bg} !important;
                }
            `;
        } catch (e) {
            console.log('[v0] Cross-origin, using postMessage');
        }
    }
    
    // Iframe load handler
    document.getElementById('previewFrame').addEventListener('load', function() {
        applyPreviewStyles();
        if (selectionMode) updateIframeSelectionMode();
    });
    
    // History
    function saveHistory() {
        const state = JSON.parse(JSON.stringify(themeState));
        historyStack = historyStack.slice(0, historyIndex + 1);
        historyStack.push(state);
        historyIndex = historyStack.length - 1;
        updateHistoryButtons();
    }
    
    function undo() {
        if (historyIndex > 0) {
            historyIndex--;
            themeState = JSON.parse(JSON.stringify(historyStack[historyIndex]));
            updateUIFromState();
            applyPreviewStyles();
            updateHistoryButtons();
        }
    }
    
    function redo() {
        if (historyIndex < historyStack.length - 1) {
            historyIndex++;
            themeState = JSON.parse(JSON.stringify(historyStack[historyIndex]));
            updateUIFromState();
            applyPreviewStyles();
            updateHistoryButtons();
        }
    }
    
    function updateHistoryButtons() {
        document.getElementById('undoBtn').disabled = historyIndex <= 0;
        document.getElementById('redoBtn').disabled = historyIndex >= historyStack.length - 1;
    }
    
    document.getElementById('undoBtn').addEventListener('click', undo);
    document.getElementById('redoBtn').addEventListener('click', redo);
    
    function updateUIFromState() {
        Object.entries(themeState).forEach(([key, value]) => {
            const swatch = document.querySelector(`.color-swatch input[data-key="${key}"]`);
            const hexInput = document.querySelector(`.color-hex-input[data-key="${key}"]`);
            const rangeSlider = document.querySelector(`.range-slider[data-key="${key}"]`);
            const selectInput = document.querySelector(`.select-input[data-key="${key}"]`);
            
            if (swatch) {
                swatch.value = value;
                swatch.parentElement.style.background = value;
            }
            if (hexInput) hexInput.value = value;
            if (rangeSlider) {
                rangeSlider.value = value;
                const unit = key === 'products_per_row' ? '' : 'px';
                rangeSlider.closest('.range-group').querySelector('.range-value').textContent = value + unit;
            }
            if (selectInput) selectInput.value = value;
        });
    }
    
    // Save Theme
    async function saveTheme() {
        showLoading(true);
        
        try {
            const response = await fetch('<?php echo APP_URL; ?>/api/admin/save-theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    theme: themeState,
                    create_version: true
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Tema publicado com sucesso!', 'success');
                originalState = JSON.parse(JSON.stringify(themeState));
            } else {
                showToast(data.error || 'Erro ao salvar', 'error');
            }
        } catch (error) {
            console.error('[v0] Save error:', error);
            showToast('Erro de conexao. Verifique se esta logado.', 'error');
        }
        
        showLoading(false);
    }
    
    // Refresh Preview
    function refreshPreview() {
        const iframe = document.getElementById('previewFrame');
        iframe.src = iframe.src;
    }
    
    // Reset to Defaults
    function resetToDefaults() {
        if (!confirm('Resetar todas as cores para o padrao?')) return;
        applyPreset(presets['gold-dark']);
        showToast('Cores resetadas', 'success');
    }
    
    // Export Theme
    function exportTheme() {
        const dataStr = JSON.stringify(themeState, null, 2);
        const blob = new Blob([dataStr], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'theme-backup.json';
        a.click();
        showToast('Tema exportado!', 'success');
    }
    
    // Toast
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const msgEl = document.getElementById('toastMessage');
        const icon = toast.querySelector('i');
        
        msgEl.textContent = message;
        toast.className = 'toast show ' + type;
        icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
    
    // Loading
    function showLoading(show) {
        document.getElementById('loadingOverlay').classList.toggle('show', show);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 's') {
                e.preventDefault();
                saveTheme();
            } else if (e.key === 'z') {
                e.preventDefault();
                if (e.shiftKey) redo();
                else undo();
            }
        }
    });
    </script>
</body>
</html>
