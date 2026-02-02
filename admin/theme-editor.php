<?php
/**
 * EDITOR VISUAL PRO - Estilo Elementor/Shopify
 * Sistema completo de edicao visual ao vivo
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar autenticacao
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$appUrl = APP_URL;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor Visual Pro - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0a0a0a;
            --sidebar: #141414;
            --panel: #1a1a1a;
            --border: #2a2a2a;
            --text: #ffffff;
            --muted: #888;
            --accent: #d4af37;
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); overflow: hidden; height: 100vh; }
        
        /* HEADER */
        .header { height: 52px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; }
        .header-left, .header-center, .header-right { display: flex; align-items: center; gap: 12px; }
        .back-btn { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .back-btn:hover { border-color: var(--accent); color: var(--accent); }
        .logo { font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .logo i { color: var(--accent); }
        
        .page-tabs { display: flex; background: var(--panel); border-radius: 8px; padding: 3px; }
        .page-tab { padding: 7px 14px; border: none; background: transparent; color: var(--muted); border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .page-tab:hover { color: var(--text); }
        .page-tab.active { background: var(--accent); color: #000; }
        
        .device-tabs { display: flex; background: var(--panel); border-radius: 8px; padding: 3px; }
        .device-tab { width: 34px; height: 30px; border: none; background: transparent; color: var(--muted); border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .device-tab:hover { color: var(--text); }
        .device-tab.active { background: var(--accent); color: #000; }
        
        .status { display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--panel); border-radius: 6px; font-size: 11px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); }
        .status-dot.saving { background: var(--warning); animation: pulse 1s infinite; }
        .status-dot.error { background: var(--error); }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
        
        .btn { padding: 8px 16px; border-radius: 8px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: var(--accent); }
        .btn-primary { background: linear-gradient(135deg, var(--accent), #e5c04b); color: #000; }
        .btn-primary:hover { box-shadow: 0 4px 15px rgba(212,175,55,0.4); }
        
        /* MAIN */
        .main { display: flex; height: calc(100vh - 52px); margin-top: 52px; }
        
        /* LEFT TOOLS */
        .tools { width: 56px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; padding: 12px 0; gap: 4px; }
        .tool { width: 40px; height: 40px; border-radius: 10px; border: none; background: transparent; color: var(--muted); cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 9px; position: relative; }
        .tool i { font-size: 16px; }
        .tool:hover { background: var(--panel); color: var(--text); }
        .tool.active { background: var(--accent); color: #000; }
        .tool-divider { width: 28px; height: 1px; background: var(--border); margin: 8px 0; }
        
        /* RIGHT PANEL */
        .panel { width: 320px; background: var(--sidebar); border-left: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
        .panel-header { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .panel-title i { color: var(--accent); }
        
        .help { width: 18px; height: 18px; border-radius: 50%; background: var(--panel); border: 1px solid var(--border); color: var(--muted); font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: help; position: relative; }
        .help:hover { color: var(--accent); border-color: var(--accent); }
        .help .tip { position: absolute; right: calc(100% + 8px); top: 50%; transform: translateY(-50%); background: #000; color: #fff; padding: 8px 12px; border-radius: 6px; font-size: 11px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.2s; z-index: 1000; line-height: 1.4; text-align: left; font-weight: 400; }
        .help:hover .tip { opacity: 1; visibility: visible; }
        
        .panel-body { flex: 1; overflow-y: auto; padding: 16px; }
        .panel-body::-webkit-scrollbar { width: 5px; }
        .panel-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        
        /* OPTION GROUPS */
        .group { background: var(--panel); border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
        .group-header { padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
        .group-header:hover { background: rgba(255,255,255,0.02); }
        .group-title { font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .group-title i { color: var(--accent); font-size: 13px; }
        .group-toggle { color: var(--muted); font-size: 11px; transition: transform 0.2s; }
        .group.closed .group-toggle { transform: rotate(-90deg); }
        .group.closed .group-content { display: none; }
        .group-content { padding: 0 14px 14px; }
        
        /* INPUTS */
        .field { margin-bottom: 14px; }
        .field:last-child { margin-bottom: 0; }
        .label { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted); margin-bottom: 6px; }
        .label .help { width: 14px; height: 14px; font-size: 8px; }
        
        .input { width: 100%; padding: 9px 11px; background: var(--bg); border: 1px solid var(--border); border-radius: 7px; color: var(--text); font-size: 12px; }
        .input:focus { outline: none; border-color: var(--accent); }
        .input::placeholder { color: #555; }
        
        .color-wrap { display: flex; gap: 8px; }
        .color-box { width: 38px; height: 34px; border-radius: 7px; border: 2px solid var(--border); overflow: hidden; position: relative; cursor: pointer; }
        .color-box:hover { border-color: var(--accent); }
        .color-box input { position: absolute; top: -8px; left: -8px; width: 60px; height: 50px; cursor: pointer; border: none; }
        .color-hex { flex: 1; }
        
        .range-wrap { display: flex; align-items: center; gap: 10px; }
        .range { flex: 1; -webkit-appearance: none; height: 5px; background: var(--border); border-radius: 3px; }
        .range::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; background: var(--accent); border-radius: 50%; cursor: pointer; }
        .range-val { min-width: 40px; padding: 5px 8px; background: var(--bg); border-radius: 5px; font-size: 11px; text-align: center; font-family: monospace; }
        
        .select { width: 100%; padding: 9px 11px; background: var(--bg); border: 1px solid var(--border); border-radius: 7px; color: var(--text); font-size: 12px; cursor: pointer; }
        .select:focus { outline: none; border-color: var(--accent); }
        
        .toggle-wrap { display: flex; align-items: center; justify-content: space-between; }
        .toggle { position: relative; width: 44px; height: 24px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--border); border-radius: 24px; transition: 0.3s; }
        .toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: var(--muted); border-radius: 50%; transition: 0.3s; }
        .toggle input:checked + .toggle-slider { background: var(--accent); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(20px); background: #000; }
        
        /* SPACING CONTROL */
        .spacing { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; background: var(--bg); padding: 12px; border-radius: 8px; }
        .spacing-input { position: relative; }
        .spacing-input input { width: 100%; padding: 6px; background: var(--panel); border: 1px solid var(--border); border-radius: 5px; color: var(--text); font-size: 11px; text-align: center; }
        .spacing-input input:focus { outline: none; border-color: var(--accent); }
        .spacing-input label { position: absolute; top: -5px; left: 50%; transform: translateX(-50%); background: var(--bg); padding: 0 3px; font-size: 8px; color: var(--muted); text-transform: uppercase; }
        .spacing-center { grid-column: 2; grid-row: 2; display: flex; align-items: center; justify-content: center; font-size: 9px; color: var(--muted); background: var(--panel); border-radius: 5px; }
        
        /* IMAGE UPLOAD */
        .upload { border: 2px dashed var(--border); border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .upload:hover { border-color: var(--accent); background: rgba(212,175,55,0.05); }
        .upload i { font-size: 28px; color: var(--muted); margin-bottom: 8px; }
        .upload p { font-size: 11px; color: var(--muted); }
        .upload input { display: none; }
        
        /* ELEMENT INFO */
        .elem-info { background: linear-gradient(135deg, rgba(212,175,55,0.1), rgba(212,175,55,0.02)); border: 1px solid var(--accent); border-radius: 10px; padding: 12px; margin-bottom: 14px; }
        .elem-tag { display: inline-flex; align-items: center; gap: 5px; background: var(--accent); color: #000; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; }
        .elem-path { font-size: 10px; color: var(--muted); word-break: break-all; }
        
        /* PRESETS */
        .presets { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .preset { background: var(--bg); border: 2px solid var(--border); border-radius: 10px; padding: 10px; cursor: pointer; transition: all 0.2s; }
        .preset:hover { border-color: var(--accent); transform: translateY(-2px); }
        .preset-colors { display: flex; gap: 3px; margin-bottom: 6px; }
        .preset-color { width: 18px; height: 18px; border-radius: 4px; }
        .preset-name { font-size: 10px; font-weight: 500; }
        
        /* NO SELECTION */
        .empty { text-align: center; padding: 40px 20px; color: var(--muted); }
        .empty i { font-size: 40px; margin-bottom: 14px; opacity: 0.3; }
        .empty h3 { font-size: 14px; color: var(--text); margin-bottom: 8px; }
        .empty p { font-size: 12px; line-height: 1.5; }
        .quick-btns { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 16px; }
        .quick-btn { padding: 8px 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .quick-btn:hover { border-color: var(--accent); color: var(--accent); }
        
        /* PREVIEW */
        .preview { flex: 1; background: #1a1a1a; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .frame-wrap { position: relative; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.5); transition: all 0.3s; }
        .frame-wrap.desktop { width: 100%; height: 100%; max-width: 1400px; }
        .frame-wrap.tablet { width: 768px; height: 90%; }
        .frame-wrap.mobile { width: 375px; height: 90%; border-radius: 30px; border: 6px solid #000; }
        .frame { width: 100%; height: 100%; border: none; }
        
        /* SELECTION BANNER */
        .sel-banner { position: fixed; top: 62px; left: 50%; transform: translateX(-50%); background: var(--accent); color: #000; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; display: none; align-items: center; gap: 8px; z-index: 99; box-shadow: 0 4px 15px rgba(212,175,55,0.4); }
        .sel-banner.show { display: flex; }
        .sel-banner button { background: rgba(0,0,0,0.2); border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; color: #000; margin-left: 8px; }
        
        /* TOAST */
        .toast-wrap { position: fixed; bottom: 20px; right: 20px; z-index: 1000; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 18px; border-radius: 10px; display: flex; align-items: center; gap: 10px; font-size: 12px; animation: slideIn 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .toast.success { background: var(--success); color: #fff; }
        .toast.error { background: var(--error); color: #fff; }
        .toast.warning { background: var(--warning); color: #000; }
        .toast.info { background: #3b82f6; color: #fff; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        /* MODAL */
        .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 200; }
        .modal-bg.show { display: flex; }
        .modal { background: var(--sidebar); border-radius: 14px; width: 90%; max-width: 700px; max-height: 80vh; overflow: hidden; border: 1px solid var(--border); }
        .modal-head { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-head h3 { font-size: 16px; font-weight: 600; }
        .modal-close { width: 32px; height: 32px; border-radius: 8px; border: none; background: var(--panel); color: var(--text); cursor: pointer; }
        .modal-close:hover { background: var(--error); }
        .modal-body { padding: 20px; overflow-y: auto; max-height: calc(80vh - 60px); }
        .sections-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .section-item { background: var(--panel); border: 2px solid var(--border); border-radius: 12px; padding: 16px; cursor: pointer; text-align: center; transition: all 0.2s; }
        .section-item:hover { border-color: var(--accent); transform: translateY(-3px); }
        .section-item i { font-size: 24px; color: var(--accent); margin-bottom: 10px; }
        .section-item h4 { font-size: 13px; margin-bottom: 4px; }
        .section-item p { font-size: 10px; color: var(--muted); }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <a href="index.php" class="back-btn" title="Voltar ao painel"><i class="fas fa-arrow-left"></i></a>
            <div class="logo"><i class="fas fa-wand-magic-sparkles"></i> Editor Visual Pro</div>
        </div>
        <div class="header-center">
            <div class="page-tabs">
                <button class="page-tab active" data-page="index"><i class="fas fa-home"></i> Home</button>
                <button class="page-tab" data-page="carrinho"><i class="fas fa-shopping-cart"></i> Carrinho</button>
                <button class="page-tab" data-page="checkout"><i class="fas fa-credit-card"></i> Checkout</button>
                <button class="page-tab" data-page="produto"><i class="fas fa-box"></i> Produto</button>
            </div>
            <div class="device-tabs">
                <button class="device-tab active" data-device="desktop" title="Desktop"><i class="fas fa-desktop"></i></button>
                <button class="device-tab" data-device="tablet" title="Tablet"><i class="fas fa-tablet-alt"></i></button>
                <button class="device-tab" data-device="mobile" title="Mobile"><i class="fas fa-mobile-alt"></i></button>
            </div>
        </div>
        <div class="header-right">
            <div class="status"><span class="status-dot" id="statusDot"></span><span id="statusText">Pronto</span></div>
            <button class="btn btn-outline" onclick="undo()" title="Desfazer (Ctrl+Z)"><i class="fas fa-undo"></i></button>
            <button class="btn btn-outline" onclick="redo()" title="Refazer (Ctrl+Y)"><i class="fas fa-redo"></i></button>
            <button class="btn btn-outline" onclick="window.open('<?php echo $appUrl; ?>', '_blank')"><i class="fas fa-external-link-alt"></i> Preview</button>
            <button class="btn btn-primary" onclick="publish()"><i class="fas fa-rocket"></i> Publicar</button>
        </div>
    </header>
    
    <!-- SELECTION BANNER -->
    <div class="sel-banner" id="selBanner">
        <i class="fas fa-mouse-pointer"></i> Modo Selecao - Clique em um elemento
        <button onclick="toggleSelect()"><i class="fas fa-times"></i></button>
    </div>
    
    <!-- MAIN -->
    <main class="main">
        <!-- LEFT TOOLS -->
        <aside class="tools">
            <button class="tool active" data-tool="select" onclick="setTool('select')" title="Selecionar elemento"><i class="fas fa-mouse-pointer"></i></button>
            <button class="tool" data-tool="move" onclick="setTool('move')" title="Mover"><i class="fas fa-arrows-alt"></i></button>
            <div class="tool-divider"></div>
            <button class="tool" data-tool="text" onclick="setTool('text')" title="Editar texto"><i class="fas fa-font"></i></button>
            <button class="tool" data-tool="image" onclick="setTool('image')" title="Imagem"><i class="fas fa-image"></i></button>
            <button class="tool" data-tool="section" onclick="openSectionModal()" title="Adicionar secao"><i class="fas fa-plus-square"></i></button>
            <div class="tool-divider"></div>
            <button class="tool" data-tool="colors" onclick="showPanel('colors')" title="Cores globais"><i class="fas fa-palette"></i></button>
            <button class="tool" data-tool="fonts" onclick="showPanel('fonts')" title="Tipografia"><i class="fas fa-text-height"></i></button>
            <button class="tool" data-tool="presets" onclick="showPanel('presets')" title="Temas prontos"><i class="fas fa-swatchbook"></i></button>
            <div class="tool-divider"></div>
            <button class="tool" data-tool="layers" onclick="showPanel('layers')" title="Camadas"><i class="fas fa-layer-group"></i></button>
            <button class="tool" data-tool="settings" onclick="showPanel('settings')" title="Configuracoes"><i class="fas fa-cog"></i></button>
        </aside>
        
        <!-- PREVIEW -->
        <section class="preview">
            <div class="frame-wrap desktop" id="frameWrap">
                <iframe src="<?php echo $appUrl; ?>?editor=1" class="frame" id="frame"></iframe>
            </div>
        </section>
        
        <!-- RIGHT PANEL -->
        <aside class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-sliders-h"></i> <span id="panelTitle">Propriedades</span></div>
                <div class="help"><i class="fas fa-question"></i><span class="tip">Selecione um elemento no preview para editar suas propriedades ou use as ferramentas a esquerda.</span></div>
            </div>
            <div class="panel-body" id="panelBody">
                <!-- EMPTY STATE -->
                <div class="empty" id="emptyState">
                    <i class="fas fa-mouse-pointer"></i>
                    <h3>Nenhum elemento selecionado</h3>
                    <p>Clique em um elemento no preview ou use os atalhos abaixo</p>
                    <div class="quick-btns">
                        <button class="quick-btn" onclick="showPanel('colors')"><i class="fas fa-palette"></i> Cores</button>
                        <button class="quick-btn" onclick="showPanel('fonts')"><i class="fas fa-font"></i> Fontes</button>
                        <button class="quick-btn" onclick="showPanel('presets')"><i class="fas fa-swatchbook"></i> Temas</button>
                        <button class="quick-btn" onclick="openSectionModal()"><i class="fas fa-plus"></i> Secao</button>
                    </div>
                </div>
                
                <!-- ELEMENT PROPS -->
                <div id="elemProps" style="display:none;"></div>
                
                <!-- COLORS PANEL -->
                <div id="colorsPanel" style="display:none;">
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-fill-drip"></i> Cores Principais</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <label class="label">Cor Primaria <div class="help"><i class="fas fa-question"></i><span class="tip">Cor principal da marca usada em botoes e destaques</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'color_primary', '#C7A333'); ?>">
                                        <input type="color" data-key="color_primary" value="<?php echo getSetting($pdo, 'color_primary', '#C7A333'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'color_primary', '#C7A333'); ?>" data-key="color_primary" onchange="updateHex(this)">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Cor de Fundo <div class="help"><i class="fas fa-question"></i><span class="tip">Cor de fundo principal do site</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'color_background', '#fef9e0'); ?>">
                                        <input type="color" data-key="color_background" value="<?php echo getSetting($pdo, 'color_background', '#fef9e0'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'color_background', '#fef9e0'); ?>" data-key="color_background" onchange="updateHex(this)">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Cor do Texto <div class="help"><i class="fas fa-question"></i><span class="tip">Cor principal dos textos</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'color_text', '#1a1a1a'); ?>">
                                        <input type="color" data-key="color_text" value="<?php echo getSetting($pdo, 'color_text', '#1a1a1a'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'color_text', '#1a1a1a'); ?>" data-key="color_text" onchange="updateHex(this)">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-square"></i> Botoes</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <label class="label">Fundo do Botao <div class="help"><i class="fas fa-question"></i><span class="tip">Cor de fundo dos botoes principais</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'button_primary_bg', '#C7A333'); ?>">
                                        <input type="color" data-key="button_primary_bg" value="<?php echo getSetting($pdo, 'button_primary_bg', '#C7A333'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'button_primary_bg', '#C7A333'); ?>" data-key="button_primary_bg" onchange="updateHex(this)">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Texto do Botao <div class="help"><i class="fas fa-question"></i><span class="tip">Cor do texto nos botoes</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'button_primary_text', '#000000'); ?>">
                                        <input type="color" data-key="button_primary_text" value="<?php echo getSetting($pdo, 'button_primary_text', '#000000'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'button_primary_text', '#000000'); ?>" data-key="button_primary_text" onchange="updateHex(this)">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-shopping-bag"></i> Cards</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <label class="label">Fundo do Card <div class="help"><i class="fas fa-question"></i><span class="tip">Cor de fundo dos cards de produto</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'card_bg', '#ffffff'); ?>">
                                        <input type="color" data-key="card_bg" value="<?php echo getSetting($pdo, 'card_bg', '#ffffff'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'card_bg', '#ffffff'); ?>" data-key="card_bg" onchange="updateHex(this)">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Badge Desconto <div class="help"><i class="fas fa-question"></i><span class="tip">Cor do badge de desconto</span></div></label>
                                <div class="color-wrap">
                                    <div class="color-box" style="background:<?php echo getSetting($pdo, 'discount_badge_bg', '#22c55e'); ?>">
                                        <input type="color" data-key="discount_badge_bg" value="<?php echo getSetting($pdo, 'discount_badge_bg', '#22c55e'); ?>" onchange="updateColor(this)">
                                    </div>
                                    <input type="text" class="input color-hex" value="<?php echo getSetting($pdo, 'discount_badge_bg', '#22c55e'); ?>" data-key="discount_badge_bg" onchange="updateHex(this)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FONTS PANEL -->
                <div id="fontsPanel" style="display:none;">
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-heading"></i> Fontes</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <label class="label">Fonte Titulos <div class="help"><i class="fas fa-question"></i><span class="tip">Fonte usada em titulos H1, H2, H3...</span></div></label>
                                <select class="select" data-key="font_heading" onchange="updateSetting(this)">
                                    <option value="Inter" <?php echo getSetting($pdo, 'font_heading', 'Inter')==='Inter'?'selected':''; ?>>Inter</option>
                                    <option value="Poppins" <?php echo getSetting($pdo, 'font_heading')==='Poppins'?'selected':''; ?>>Poppins</option>
                                    <option value="Montserrat" <?php echo getSetting($pdo, 'font_heading')==='Montserrat'?'selected':''; ?>>Montserrat</option>
                                    <option value="Roboto" <?php echo getSetting($pdo, 'font_heading')==='Roboto'?'selected':''; ?>>Roboto</option>
                                    <option value="Playfair Display" <?php echo getSetting($pdo, 'font_heading')==='Playfair Display'?'selected':''; ?>>Playfair Display</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label">Fonte Corpo <div class="help"><i class="fas fa-question"></i><span class="tip">Fonte usada em textos normais</span></div></label>
                                <select class="select" data-key="font_body" onchange="updateSetting(this)">
                                    <option value="Inter" <?php echo getSetting($pdo, 'font_body', 'Inter')==='Inter'?'selected':''; ?>>Inter</option>
                                    <option value="Poppins" <?php echo getSetting($pdo, 'font_body')==='Poppins'?'selected':''; ?>>Poppins</option>
                                    <option value="Roboto" <?php echo getSetting($pdo, 'font_body')==='Roboto'?'selected':''; ?>>Roboto</option>
                                    <option value="Open Sans" <?php echo getSetting($pdo, 'font_body')==='Open Sans'?'selected':''; ?>>Open Sans</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label">Tamanho Base <div class="help"><i class="fas fa-question"></i><span class="tip">Tamanho base da fonte em pixels</span></div></label>
                                <div class="range-wrap">
                                    <input type="range" class="range" min="12" max="20" value="<?php echo getSetting($pdo, 'font_size_base', '16'); ?>" data-key="font_size_base" onchange="updateSetting(this)" oninput="this.nextElementSibling.textContent=this.value+'px'">
                                    <span class="range-val"><?php echo getSetting($pdo, 'font_size_base', '16'); ?>px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PRESETS PANEL -->
                <div id="presetsPanel" style="display:none;">
                    <div class="group">
                        <div class="group-header">
                            <div class="group-title"><i class="fas fa-swatchbook"></i> Temas Prontos</div>
                            <div class="help"><i class="fas fa-question"></i><span class="tip">Clique para aplicar todas as cores automaticamente</span></div>
                        </div>
                        <div class="group-content">
                            <div class="presets">
                                <div class="preset" onclick="applyPreset('gold')">
                                    <div class="preset-colors">
                                        <div class="preset-color" style="background:#C7A333"></div>
                                        <div class="preset-color" style="background:#fef9e0"></div>
                                        <div class="preset-color" style="background:#1a1a1a"></div>
                                    </div>
                                    <div class="preset-name">Ouro Classico</div>
                                </div>
                                <div class="preset" onclick="applyPreset('modern')">
                                    <div class="preset-colors">
                                        <div class="preset-color" style="background:#3b82f6"></div>
                                        <div class="preset-color" style="background:#f8fafc"></div>
                                        <div class="preset-color" style="background:#0f172a"></div>
                                    </div>
                                    <div class="preset-name">Moderno Azul</div>
                                </div>
                                <div class="preset" onclick="applyPreset('rose')">
                                    <div class="preset-colors">
                                        <div class="preset-color" style="background:#e11d48"></div>
                                        <div class="preset-color" style="background:#fff1f2"></div>
                                        <div class="preset-color" style="background:#1a1a1a"></div>
                                    </div>
                                    <div class="preset-name">Rose Gold</div>
                                </div>
                                <div class="preset" onclick="applyPreset('emerald')">
                                    <div class="preset-colors">
                                        <div class="preset-color" style="background:#10b981"></div>
                                        <div class="preset-color" style="background:#f0fdf4"></div>
                                        <div class="preset-color" style="background:#1a1a1a"></div>
                                    </div>
                                    <div class="preset-name">Esmeralda</div>
                                </div>
                                <div class="preset" onclick="applyPreset('purple')">
                                    <div class="preset-colors">
                                        <div class="preset-color" style="background:#8b5cf6"></div>
                                        <div class="preset-color" style="background:#f5f3ff"></div>
                                        <div class="preset-color" style="background:#1e1b4b"></div>
                                    </div>
                                    <div class="preset-name">Roxo Premium</div>
                                </div>
                                <div class="preset" onclick="applyPreset('dark')">
                                    <div class="preset-colors">
                                        <div class="preset-color" style="background:#f59e0b"></div>
                                        <div class="preset-color" style="background:#1a1a1a"></div>
                                        <div class="preset-color" style="background:#ffffff"></div>
                                    </div>
                                    <div class="preset-name">Modo Escuro</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- LAYERS PANEL -->
                <div id="layersPanel" style="display:none;">
                    <div class="group">
                        <div class="group-header">
                            <div class="group-title"><i class="fas fa-layer-group"></i> Estrutura</div>
                        </div>
                        <div class="group-content" id="layersList"></div>
                    </div>
                </div>
                
                <!-- SETTINGS PANEL -->
                <div id="settingsPanel" style="display:none;">
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-th-large"></i> Layout</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <label class="label">Produtos por Linha <div class="help"><i class="fas fa-question"></i><span class="tip">Quantidade de produtos em cada linha da grade</span></div></label>
                                <div class="range-wrap">
                                    <input type="range" class="range" min="2" max="6" value="<?php echo getSetting($pdo, 'products_per_row', '4'); ?>" data-key="products_per_row" onchange="updateSetting(this)" oninput="this.nextElementSibling.textContent=this.value">
                                    <span class="range-val"><?php echo getSetting($pdo, 'products_per_row', '4'); ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Arredondamento <div class="help"><i class="fas fa-question"></i><span class="tip">Raio das bordas arredondadas</span></div></label>
                                <div class="range-wrap">
                                    <input type="range" class="range" min="0" max="24" value="<?php echo getSetting($pdo, 'border_radius', '12'); ?>" data-key="border_radius" onchange="updateSetting(this)" oninput="this.nextElementSibling.textContent=this.value+'px'">
                                    <span class="range-val"><?php echo getSetting($pdo, 'border_radius', '12'); ?>px</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-bullhorn"></i> Faixa Rotativa</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <div class="toggle-wrap">
                                    <label class="label" style="margin:0">Ativar Faixa <div class="help"><i class="fas fa-question"></i><span class="tip">Liga/desliga a faixa promocional</span></div></label>
                                    <label class="toggle">
                                        <input type="checkbox" data-key="faixa_enabled" <?php echo getSetting($pdo, 'faixa_enabled', '1')==='1'?'checked':''; ?> onchange="updateToggle(this)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Frases (uma por linha)</label>
                                <textarea class="input" rows="4" data-key="faixa_frases" onchange="updateSetting(this)" placeholder="FRETE GRATIS acima de R$199"><?php echo htmlspecialchars(getSetting($pdo, 'faixa_frases', '')); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <div class="group-title"><i class="fas fa-file-alt"></i> Textos</div>
                            <i class="fas fa-chevron-down group-toggle"></i>
                        </div>
                        <div class="group-content">
                            <div class="field">
                                <label class="label">Placeholder Pesquisa <div class="help"><i class="fas fa-question"></i><span class="tip">Texto no campo de pesquisa vazio</span></div></label>
                                <input type="text" class="input" data-key="search_placeholder" value="<?php echo htmlspecialchars(getSetting($pdo, 'search_placeholder', 'O que voce procura?')); ?>" onchange="updateSetting(this)">
                            </div>
                            <div class="field">
                                <label class="label">Texto Botao Comprar <div class="help"><i class="fas fa-question"></i><span class="tip">Texto no botao de adicionar ao carrinho</span></div></label>
                                <input type="text" class="input" data-key="buy_button_text" value="<?php echo htmlspecialchars(getSetting($pdo, 'buy_button_text', 'ADICIONAR')); ?>" onchange="updateSetting(this)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </main>
    
    <!-- ADD SECTION MODAL -->
    <div class="modal-bg" id="sectionModal">
        <div class="modal">
            <div class="modal-head">
                <h3><i class="fas fa-plus-square" style="color:var(--accent);margin-right:8px;"></i> Adicionar Secao</h3>
                <button class="modal-close" onclick="closeSectionModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="sections-grid">
                    <div class="section-item" onclick="addSection('banner')"><i class="fas fa-image"></i><h4>Banner</h4><p>Imagem em destaque</p></div>
                    <div class="section-item" onclick="addSection('carousel')"><i class="fas fa-images"></i><h4>Carrossel</h4><p>Slider de imagens</p></div>
                    <div class="section-item" onclick="addSection('products')"><i class="fas fa-th"></i><h4>Produtos</h4><p>Grade de produtos</p></div>
                    <div class="section-item" onclick="addSection('text')"><i class="fas fa-align-left"></i><h4>Texto</h4><p>Bloco de texto</p></div>
                    <div class="section-item" onclick="addSection('cta')"><i class="fas fa-bullhorn"></i><h4>CTA</h4><p>Chamada para acao</p></div>
                    <div class="section-item" onclick="addSection('video')"><i class="fas fa-play"></i><h4>Video</h4><p>Embed de video</p></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TOASTS -->
    <div class="toast-wrap" id="toasts"></div>

    <script>
    const APP = '<?php echo $appUrl; ?>';
    let changes = {};
    let undoStack = [];
    let redoStack = [];
    let selectedEl = null;
    let selectMode = false;
    
    // INIT
    document.addEventListener('DOMContentLoaded', () => {
        setupFrame();
        setupKeys();
    });
    
    function setupFrame() {
        const frame = document.getElementById('frame');
        frame.onload = () => {
            try {
                const doc = frame.contentDocument || frame.contentWindow.document;
                const style = doc.createElement('style');
                style.textContent = `
                    .v0-hover { outline: 2px dashed #3b82f6 !important; outline-offset: 2px !important; cursor: pointer !important; }
                    .v0-selected { outline: 3px solid #d4af37 !important; outline-offset: 2px !important; }
                `;
                doc.head.appendChild(style);
                
                doc.body.addEventListener('click', e => {
                    if (selectMode) { e.preventDefault(); e.stopPropagation(); selectElement(e.target); }
                }, true);
                
                doc.body.addEventListener('mouseover', e => { if (selectMode) e.target.classList.add('v0-hover'); });
                doc.body.addEventListener('mouseout', e => e.target.classList.remove('v0-hover'));
                
                buildLayers(doc);
            } catch(e) { console.log('[v0] Frame setup error:', e); }
        };
    }
    
    function setupKeys() {
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); publish(); }
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
        });
    }
    
    // TOOLS
    function setTool(tool) {
        document.querySelectorAll('.tool').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tool="${tool}"]`).classList.add('active');
        if (tool === 'select') toggleSelect();
    }
    
    function toggleSelect() {
        selectMode = !selectMode;
        document.getElementById('selBanner').classList.toggle('show', selectMode);
    }
    
    function selectElement(el) {
        const frame = document.getElementById('frame');
        const doc = frame.contentDocument || frame.contentWindow.document;
        doc.querySelectorAll('.v0-selected').forEach(e => e.classList.remove('v0-selected'));
        el.classList.add('v0-selected');
        selectedEl = el;
        showElementProps(el);
    }
    
    function showElementProps(el) {
        document.getElementById('emptyState').style.display = 'none';
        hideAllPanels();
        
        const tag = el.tagName.toLowerCase();
        const style = getComputedStyle(el);
        const container = document.getElementById('elemProps');
        container.style.display = 'block';
        document.getElementById('panelTitle').textContent = 'Propriedades';
        
        let html = `
            <div class="elem-info">
                <span class="elem-tag"><i class="fas fa-code"></i> ${tag.toUpperCase()}</span>
                <div class="elem-path">${getPath(el)}</div>
            </div>
        `;
        
        // DIMENSIONS
        html += `<div class="group"><div class="group-header" onclick="toggleGroup(this)"><div class="group-title"><i class="fas fa-ruler-combined"></i> Dimensoes</div><i class="fas fa-chevron-down group-toggle"></i></div><div class="group-content">
            <div class="field"><label class="label">Largura <div class="help"><i class="fas fa-question"></i><span class="tip">Largura do elemento (px, %, auto)</span></div></label><input type="text" class="input" value="${el.style.width||'auto'}" onchange="setStyle('width',this.value)"></div>
            <div class="field"><label class="label">Altura <div class="help"><i class="fas fa-question"></i><span class="tip">Altura do elemento (px, %, auto)</span></div></label><input type="text" class="input" value="${el.style.height||'auto'}" onchange="setStyle('height',this.value)"></div>
            <div class="field"><label class="label">Max Width <div class="help"><i class="fas fa-question"></i><span class="tip">Largura maxima</span></div></label><input type="text" class="input" value="${el.style.maxWidth||'none'}" onchange="setStyle('maxWidth',this.value)"></div>
        </div></div>`;
        
        // COLORS
        html += `<div class="group"><div class="group-header" onclick="toggleGroup(this)"><div class="group-title"><i class="fas fa-palette"></i> Cores</div><i class="fas fa-chevron-down group-toggle"></i></div><div class="group-content">
            <div class="field"><label class="label">Fundo <div class="help"><i class="fas fa-question"></i><span class="tip">Cor de fundo do elemento</span></div></label>
                <div class="color-wrap"><div class="color-box" style="background:${style.backgroundColor}"><input type="color" value="${rgbHex(style.backgroundColor)}" onchange="setStyle('backgroundColor',this.value)"></div><input type="text" class="input color-hex" value="${rgbHex(style.backgroundColor)}" onchange="setStyle('backgroundColor',this.value)"></div>
            </div>
            <div class="field"><label class="label">Texto <div class="help"><i class="fas fa-question"></i><span class="tip">Cor do texto</span></div></label>
                <div class="color-wrap"><div class="color-box" style="background:${style.color}"><input type="color" value="${rgbHex(style.color)}" onchange="setStyle('color',this.value)"></div><input type="text" class="input color-hex" value="${rgbHex(style.color)}" onchange="setStyle('color',this.value)"></div>
            </div>
        </div></div>`;
        
        // SPACING
        html += `<div class="group"><div class="group-header" onclick="toggleGroup(this)"><div class="group-title"><i class="fas fa-expand-arrows-alt"></i> Espacamento</div><i class="fas fa-chevron-down group-toggle"></i></div><div class="group-content">
            <div class="field"><label class="label">Padding</label>
                <div class="spacing">
                    <div></div><div class="spacing-input"><label>T</label><input value="${parseInt(style.paddingTop)||0}" onchange="setStyle('paddingTop',this.value+'px')"></div><div></div>
                    <div class="spacing-input"><label>L</label><input value="${parseInt(style.paddingLeft)||0}" onchange="setStyle('paddingLeft',this.value+'px')"></div>
                    <div class="spacing-center">px</div>
                    <div class="spacing-input"><label>R</label><input value="${parseInt(style.paddingRight)||0}" onchange="setStyle('paddingRight',this.value+'px')"></div>
                    <div></div><div class="spacing-input"><label>B</label><input value="${parseInt(style.paddingBottom)||0}" onchange="setStyle('paddingBottom',this.value+'px')"></div><div></div>
                </div>
            </div>
            <div class="field"><label class="label">Margin</label>
                <div class="spacing">
                    <div></div><div class="spacing-input"><label>T</label><input value="${parseInt(style.marginTop)||0}" onchange="setStyle('marginTop',this.value+'px')"></div><div></div>
                    <div class="spacing-input"><label>L</label><input value="${parseInt(style.marginLeft)||0}" onchange="setStyle('marginLeft',this.value+'px')"></div>
                    <div class="spacing-center">px</div>
                    <div class="spacing-input"><label>R</label><input value="${parseInt(style.marginRight)||0}" onchange="setStyle('marginRight',this.value+'px')"></div>
                    <div></div><div class="spacing-input"><label>B</label><input value="${parseInt(style.marginBottom)||0}" onchange="setStyle('marginBottom',this.value+'px')"></div><div></div>
                </div>
            </div>
        </div></div>`;
        
        // TEXT (for text elements)
        if (['h1','h2','h3','h4','h5','h6','p','span','a','label','button','div'].includes(tag)) {
            html += `<div class="group"><div class="group-header" onclick="toggleGroup(this)"><div class="group-title"><i class="fas fa-font"></i> Tipografia</div><i class="fas fa-chevron-down group-toggle"></i></div><div class="group-content">
                <div class="field"><label class="label">Texto <div class="help"><i class="fas fa-question"></i><span class="tip">Edite o conteudo de texto</span></div></label><textarea class="input" rows="3" onchange="setText(this.value)">${el.textContent.trim()}</textarea></div>
                <div class="field"><label class="label">Tamanho <div class="help"><i class="fas fa-question"></i><span class="tip">Tamanho da fonte</span></div></label>
                    <div class="range-wrap"><input type="range" class="range" min="10" max="72" value="${parseInt(style.fontSize)||16}" onchange="setStyle('fontSize',this.value+'px')" oninput="this.nextElementSibling.textContent=this.value+'px'"><span class="range-val">${style.fontSize}</span></div>
                </div>
                <div class="field"><label class="label">Peso</label>
                    <select class="select" onchange="setStyle('fontWeight',this.value)">
                        <option value="300" ${style.fontWeight=='300'?'selected':''}>Light</option>
                        <option value="400" ${style.fontWeight=='400'?'selected':''}>Normal</option>
                        <option value="500" ${style.fontWeight=='500'?'selected':''}>Medium</option>
                        <option value="600" ${style.fontWeight=='600'?'selected':''}>Semibold</option>
                        <option value="700" ${style.fontWeight=='700'?'selected':''}>Bold</option>
                    </select>
                </div>
            </div></div>`;
        }
        
        // IMAGE
        if (tag === 'img') {
            html += `<div class="group"><div class="group-header" onclick="toggleGroup(this)"><div class="group-title"><i class="fas fa-image"></i> Imagem</div><i class="fas fa-chevron-down group-toggle"></i></div><div class="group-content">
                <div class="field"><label class="label">URL <div class="help"><i class="fas fa-question"></i><span class="tip">URL da imagem</span></div></label><input type="text" class="input" value="${el.src}" onchange="setAttr('src',this.value)"></div>
                <div class="field"><label class="label">Alt <div class="help"><i class="fas fa-question"></i><span class="tip">Texto alternativo para acessibilidade</span></div></label><input type="text" class="input" value="${el.alt||''}" onchange="setAttr('alt',this.value)"></div>
                <div class="field"><label class="label">Object Fit</label>
                    <select class="select" onchange="setStyle('objectFit',this.value)">
                        <option value="cover" ${style.objectFit==='cover'?'selected':''}>Cover</option>
                        <option value="contain" ${style.objectFit==='contain'?'selected':''}>Contain</option>
                        <option value="fill" ${style.objectFit==='fill'?'selected':''}>Fill</option>
                    </select>
                </div>
                <div class="field"><label class="label">Upload</label>
                    <div class="upload" onclick="document.getElementById('imgUp').click()"><i class="fas fa-cloud-upload-alt"></i><p>Clique para upload</p><input type="file" id="imgUp" accept="image/*" onchange="uploadImg(this)"></div>
                </div>
            </div></div>`;
        }
        
        // BORDER
        html += `<div class="group closed"><div class="group-header" onclick="toggleGroup(this)"><div class="group-title"><i class="fas fa-border-style"></i> Borda</div><i class="fas fa-chevron-down group-toggle"></i></div><div class="group-content">
            <div class="field"><label class="label">Arredondamento</label>
                <div class="range-wrap"><input type="range" class="range" min="0" max="50" value="${parseInt(style.borderRadius)||0}" onchange="setStyle('borderRadius',this.value+'px')" oninput="this.nextElementSibling.textContent=this.value+'px'"><span class="range-val">${style.borderRadius||'0px'}</span></div>
            </div>
            <div class="field"><label class="label">Largura</label>
                <div class="range-wrap"><input type="range" class="range" min="0" max="10" value="${parseInt(style.borderWidth)||0}" onchange="setStyle('borderWidth',this.value+'px');setStyle('borderStyle','solid')" oninput="this.nextElementSibling.textContent=this.value+'px'"><span class="range-val">${style.borderWidth||'0px'}</span></div>
            </div>
            <div class="field"><label class="label">Cor</label>
                <div class="color-wrap"><div class="color-box" style="background:${style.borderColor}"><input type="color" value="${rgbHex(style.borderColor)}" onchange="setStyle('borderColor',this.value)"></div><input type="text" class="input color-hex" value="${rgbHex(style.borderColor)}" onchange="setStyle('borderColor',this.value)"></div>
            </div>
        </div></div>`;
        
        container.innerHTML = html;
    }
    
    function setStyle(prop, val) { if (selectedEl) { saveUndo(); selectedEl.style[prop] = val; status('saving','Aplicando...'); setTimeout(()=>status('ready','OK'),300); }}
    function setText(val) { if (selectedEl) { saveUndo(); selectedEl.textContent = val; }}
    function setAttr(attr, val) { if (selectedEl) { saveUndo(); selectedEl.setAttribute(attr, val); }}
    function uploadImg(input) {
        const file = input.files[0];
        if (file && selectedEl && selectedEl.tagName === 'IMG') {
            const reader = new FileReader();
            reader.onload = e => { selectedEl.src = e.target.result; toast('success','Imagem carregada!'); };
            reader.readAsDataURL(file);
        }
    }
    
    function getPath(el) {
        let path = [];
        let cur = el;
        while (cur && cur.tagName) {
            let s = cur.tagName.toLowerCase();
            if (cur.id) s += '#' + cur.id;
            else if (cur.className && typeof cur.className === 'string') s += '.' + cur.className.split(' ')[0];
            path.unshift(s);
            cur = cur.parentElement;
        }
        return path.slice(-4).join(' > ');
    }
    
    function rgbHex(rgb) {
        if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') return '#ffffff';
        const m = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!m) return rgb;
        return '#' + [m[1],m[2],m[3]].map(x => parseInt(x).toString(16).padStart(2,'0')).join('');
    }
    
    // PANELS
    function showPanel(panel) {
        hideAllPanels();
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('elemProps').style.display = 'none';
        document.getElementById(panel + 'Panel').style.display = 'block';
        document.getElementById('panelTitle').textContent = {colors:'Cores',fonts:'Fontes',presets:'Temas',layers:'Camadas',settings:'Config'}[panel] || 'Propriedades';
        document.querySelectorAll('.tool').forEach(t => t.classList.remove('active'));
        const btn = document.querySelector(`[data-tool="${panel}"]`);
        if (btn) btn.classList.add('active');
    }
    
    function hideAllPanels() { ['colors','fonts','presets','layers','settings'].forEach(p => { const el = document.getElementById(p+'Panel'); if(el) el.style.display = 'none'; }); }
    function toggleGroup(header) { header.parentElement.classList.toggle('closed'); }
    
    // COLORS
    function updateColor(input) {
        const key = input.dataset.key;
        const val = input.value;
        input.closest('.color-wrap').querySelector('.color-hex').value = val;
        input.closest('.color-box').style.background = val;
        changes[key] = val;
        applyToFrame(key, val);
        status('saving','Aplicando...');
    }
    
    function updateHex(input) {
        let val = input.value;
        if (!val.startsWith('#')) val = '#' + val;
        if (!/^#[0-9A-Fa-f]{6}$/.test(val)) return;
        const key = input.dataset.key;
        const colorInput = input.closest('.color-wrap').querySelector('input[type="color"]');
        const box = input.closest('.color-wrap').querySelector('.color-box');
        if (colorInput) colorInput.value = val;
        if (box) box.style.background = val;
        changes[key] = val;
        applyToFrame(key, val);
    }
    
    function updateSetting(input) { changes[input.dataset.key] = input.value; status('saving','...'); setTimeout(()=>status('ready','OK'),200); }
    function updateToggle(input) { changes[input.dataset.key] = input.checked ? '1' : '0'; }
    
    function applyToFrame(key, val) {
        try {
            const doc = document.getElementById('frame').contentDocument;
            const map = { color_primary:'--primary-color', color_background:'--bg-color', color_text:'--text-color', button_primary_bg:'--btn-bg', card_bg:'--card-bg' };
            if (map[key]) doc.documentElement.style.setProperty(map[key], val);
            setTimeout(()=>status('ready','OK'),200);
        } catch(e) {}
    }
    
    // PRESETS
    function applyPreset(name) {
        const presets = {
            gold: { color_primary:'#C7A333', color_background:'#fef9e0', color_text:'#1a1a1a', button_primary_bg:'#C7A333', card_bg:'#ffffff' },
            modern: { color_primary:'#3b82f6', color_background:'#f8fafc', color_text:'#0f172a', button_primary_bg:'#3b82f6', card_bg:'#ffffff' },
            rose: { color_primary:'#e11d48', color_background:'#fff1f2', color_text:'#1a1a1a', button_primary_bg:'#e11d48', card_bg:'#ffffff' },
            emerald: { color_primary:'#10b981', color_background:'#f0fdf4', color_text:'#1a1a1a', button_primary_bg:'#10b981', card_bg:'#ffffff' },
            purple: { color_primary:'#8b5cf6', color_background:'#f5f3ff', color_text:'#1e1b4b', button_primary_bg:'#8b5cf6', card_bg:'#ffffff' },
            dark: { color_primary:'#f59e0b', color_background:'#1a1a1a', color_text:'#ffffff', button_primary_bg:'#f59e0b', card_bg:'#2a2a2a' }
        };
        if (presets[name]) {
            Object.assign(changes, presets[name]);
            Object.entries(presets[name]).forEach(([k,v]) => {
                applyToFrame(k, v);
                document.querySelectorAll(`[data-key="${k}"]`).forEach(inp => {
                    if (inp.type === 'color') { inp.value = v; inp.closest('.color-box').style.background = v; }
                    else inp.value = v;
                });
            });
            toast('success', 'Tema aplicado! Clique Publicar para salvar.');
        }
    }
    
    // LAYERS
    function buildLayers(doc) {
        const list = document.getElementById('layersList');
        const items = [
            { sel: 'header', icon: 'bars', name: 'Header' },
            { sel: '.announcement-bar', icon: 'bullhorn', name: 'Faixa' },
            { sel: '.vitrine-search', icon: 'search', name: 'Pesquisa' },
            { sel: '.vitrine-products', icon: 'th', name: 'Produtos' },
            { sel: 'footer', icon: 'shoe-prints', name: 'Footer' }
        ];
        list.innerHTML = items.map(i => `<div class="quick-btn" style="width:100%;justify-content:flex-start;" onclick="scrollToLayer('${i.sel}')"><i class="fas fa-${i.icon}"></i> ${i.name}</div>`).join('');
    }
    
    function scrollToLayer(sel) {
        try {
            const doc = document.getElementById('frame').contentDocument;
            const el = doc.querySelector(sel);
            if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); selectElement(el); }
        } catch(e) {}
    }
    
    // PAGES & DEVICES
    document.querySelectorAll('.page-tab').forEach(btn => {
        btn.onclick = function() {
            document.querySelectorAll('.page-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const pages = { index: APP+'?editor=1', carrinho: APP+'/carrinho.php?editor=1', checkout: APP+'/checkout-entrega.php?editor=1', produto: APP+'/product.php?id=1&editor=1' };
            document.getElementById('frame').src = pages[this.dataset.page];
        };
    });
    
    document.querySelectorAll('.device-tab').forEach(btn => {
        btn.onclick = function() {
            document.querySelectorAll('.device-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('frameWrap').className = 'frame-wrap ' + this.dataset.device;
        };
    });
    
    // SECTION MODAL
    function openSectionModal() { document.getElementById('sectionModal').classList.add('show'); }
    function closeSectionModal() { document.getElementById('sectionModal').classList.remove('show'); }
    function addSection(type) { toast('info', 'Funcao em desenvolvimento'); closeSectionModal(); }
    document.getElementById('sectionModal').onclick = e => { if (e.target.id === 'sectionModal') closeSectionModal(); };
    
    // UNDO/REDO
    function saveUndo() { if (selectedEl) { undoStack.push({ el: selectedEl, style: selectedEl.getAttribute('style')||'', html: selectedEl.innerHTML }); redoStack = []; }}
    function undo() { if (undoStack.length) { const s = undoStack.pop(); redoStack.push({ el:s.el, style:s.el.getAttribute('style')||'', html:s.el.innerHTML }); s.el.setAttribute('style',s.style); s.el.innerHTML=s.html; toast('info','Desfeito'); }}
    function redo() { if (redoStack.length) { const s = redoStack.pop(); undoStack.push({ el:s.el, style:s.el.getAttribute('style')||'', html:s.el.innerHTML }); s.el.setAttribute('style',s.style); s.el.innerHTML=s.html; toast('info','Refeito'); }}
    
    // PUBLISH
    function publish() {
        if (!Object.keys(changes).length) { toast('warning', 'Nenhuma alteracao'); return; }
        status('saving', 'Salvando...');
        fetch(APP + '/api/admin/save-theme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ theme: changes, create_version: true })
        })
        .then(r => {
            if (!r.ok) throw new Error('Erro ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.success) { changes = {}; status('ready', 'Salvo!'); toast('success', 'Publicado com sucesso!'); }
            else throw new Error(data.error || 'Erro');
        })
        .catch(e => { status('error', 'Erro'); toast('error', e.message); });
    }
    
    // STATUS
    function status(type, text) {
        const dot = document.getElementById('statusDot');
        const txt = document.getElementById('statusText');
        dot.className = 'status-dot' + (type === 'saving' ? ' saving' : type === 'error' ? ' error' : '');
        txt.textContent = text;
    }
    
    // TOAST
    function toast(type, msg) {
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':type==='error'?'exclamation-circle':type==='warning'?'exclamation-triangle':'info-circle'}"></i> ${msg}`;
        document.getElementById('toasts').appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
    </script>
</body>
</html>
