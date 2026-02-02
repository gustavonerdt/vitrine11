<?php
/**
 * EDITOR DE PAGINAS - Gerencie paginas com HTML/CSS/JS
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Create pages table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS custom_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(100) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            meta_description TEXT,
            html_content LONGTEXT,
            css_content LONGTEXT,
            js_content LONGTEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

// Handle actions
$action = $_GET['action'] ?? 'list';
$pageId = $_GET['id'] ?? null;

// Fetch pages
$pages = [];
try {
    $stmt = $pdo->query("SELECT * FROM custom_pages ORDER BY title ASC");
    $pages = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch single page for editing
$editPage = null;
if ($action === 'edit' && $pageId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
        $stmt->execute([$pageId]);
        $editPage = $stmt->fetch();
    } catch (Exception $e) {}
}

$appUrl = APP_URL;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Paginas - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        /* HEADER */
        .header { height: 60px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; position: sticky; top: 0; z-index: 100; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .back-btn { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border); background: transparent; color: var(--text); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .back-btn:hover { border-color: var(--accent); color: var(--accent); }
        .logo { font-weight: 600; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .logo i { color: var(--accent); }
        .header-right { display: flex; gap: 10px; }
        .btn { padding: 10px 18px; border-radius: 10px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: var(--accent); }
        .btn-primary { background: linear-gradient(135deg, var(--accent), #e5c04b); color: #000; }
        .btn-primary:hover { box-shadow: 0 4px 15px rgba(212,175,55,0.4); transform: translateY(-1px); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger { background: var(--error); color: #fff; }
        
        /* MAIN CONTENT */
        .main { padding: 24px; max-width: 1400px; margin: 0 auto; }
        
        /* PAGES LIST */
        .pages-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .pages-header h1 { font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .pages-header h1 i { color: var(--accent); }
        
        .pages-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .page-card { background: var(--panel); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: all 0.2s; }
        .page-card:hover { border-color: var(--accent); transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .page-card-header { padding: 20px; border-bottom: 1px solid var(--border); }
        .page-title { font-size: 16px; font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
        .page-title .status { width: 8px; height: 8px; border-radius: 50%; }
        .page-title .status.active { background: var(--success); }
        .page-title .status.inactive { background: var(--error); }
        .page-slug { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
        .page-card-body { padding: 16px 20px; }
        .page-meta { display: flex; gap: 16px; font-size: 11px; color: var(--muted); }
        .page-meta span { display: flex; align-items: center; gap: 5px; }
        .page-card-footer { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; }
        .page-btn { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--text); font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
        .page-btn:hover { border-color: var(--accent); color: var(--accent); }
        .page-btn.danger:hover { border-color: var(--error); color: var(--error); }
        
        /* ADD PAGE CARD */
        .add-card { background: transparent; border: 2px dashed var(--border); border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px; cursor: pointer; transition: all 0.2s; }
        .add-card:hover { border-color: var(--accent); background: rgba(212,175,55,0.05); }
        .add-card i { font-size: 36px; color: var(--muted); margin-bottom: 12px; }
        .add-card span { font-size: 14px; color: var(--muted); }
        
        /* EDITOR VIEW */
        .editor-container { display: flex; height: calc(100vh - 60px); }
        .editor-sidebar { width: 320px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .editor-main { flex: 1; display: flex; flex-direction: column; }
        
        /* SIDEBAR FORM */
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { color: var(--accent); }
        .sidebar-body { flex: 1; padding: 20px; overflow-y: auto; }
        .field { margin-bottom: 18px; }
        .label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); margin-bottom: 8px; font-weight: 500; }
        .label .help { width: 16px; height: 16px; border-radius: 50%; background: var(--panel); border: 1px solid var(--border); color: var(--muted); font-size: 9px; display: inline-flex; align-items: center; justify-content: center; cursor: help; position: relative; }
        .label .help:hover { color: var(--accent); border-color: var(--accent); }
        .label .help .tip { position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); background: #000; color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 11px; width: 200px; opacity: 0; visibility: hidden; transition: all 0.2s; z-index: 1000; line-height: 1.5; font-weight: 400; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        .label .help .tip::before { content: ''; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: #000; }
        .label .help:hover .tip { opacity: 1; visibility: visible; }
        .input { width: 100%; padding: 12px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-size: 13px; transition: border-color 0.2s; }
        .input:focus { outline: none; border-color: var(--accent); }
        textarea.input { resize: vertical; min-height: 80px; }
        .toggle-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px; background: var(--panel); border-radius: 10px; }
        .toggle-wrap span { font-size: 13px; }
        .toggle { position: relative; width: 48px; height: 26px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--border); border-radius: 26px; transition: 0.3s; }
        .toggle-slider::before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: var(--muted); border-radius: 50%; transition: 0.3s; }
        .toggle input:checked + .toggle-slider { background: var(--success); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(22px); background: #fff; }
        
        /* CODE TABS */
        .code-tabs { display: flex; background: var(--sidebar); border-bottom: 1px solid var(--border); }
        .code-tab { padding: 14px 24px; border: none; background: transparent; color: var(--muted); font-size: 13px; font-weight: 500; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
        .code-tab:hover { color: var(--text); }
        .code-tab.active { color: var(--accent); border-bottom-color: var(--accent); background: rgba(212,175,55,0.05); }
        .code-tab i { font-size: 14px; }
        
        /* CODE EDITOR */
        .code-editors { flex: 1; position: relative; }
        .code-editor { position: absolute; inset: 0; display: none; }
        .code-editor.active { display: flex; }
        .code-editor textarea { width: 100%; height: 100%; background: #0d0d0d; border: none; color: #e0e0e0; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.6; padding: 20px; resize: none; }
        .code-editor textarea:focus { outline: none; }
        .code-editor textarea::placeholder { color: #444; }
        
        /* LINE NUMBERS */
        .line-numbers { width: 50px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 20px 0; text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.6; color: #444; user-select: none; overflow: hidden; }
        .line-numbers span { display: block; padding-right: 12px; }
        
        /* PREVIEW PANEL */
        .preview-panel { width: 50%; border-left: 1px solid var(--border); display: flex; flex-direction: column; }
        .preview-header { padding: 14px 20px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .preview-header h3 { font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .preview-header h3 i { color: var(--accent); }
        .preview-actions { display: flex; gap: 8px; }
        .preview-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--muted); cursor: pointer; transition: all 0.2s; }
        .preview-btn:hover { border-color: var(--accent); color: var(--accent); }
        .preview-btn.active { background: var(--accent); color: #000; border-color: var(--accent); }
        .preview-frame { flex: 1; background: #fff; }
        .preview-frame iframe { width: 100%; height: 100%; border: none; }
        
        /* TOAST */
        .toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 1000; display: flex; flex-direction: column; gap: 10px; }
        .toast { padding: 14px 20px; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-size: 13px; animation: slideIn 0.3s ease; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .toast.success { background: var(--success); color: #fff; }
        .toast.error { background: var(--error); color: #fff; }
        .toast.warning { background: var(--warning); color: #000; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        /* MODAL */
        .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 200; }
        .modal-bg.show { display: flex; }
        .modal { background: var(--sidebar); border-radius: 16px; width: 90%; max-width: 500px; border: 1px solid var(--border); }
        .modal-head { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-head h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .modal-head h3 i { color: var(--accent); }
        .modal-close { width: 36px; height: 36px; border-radius: 8px; border: none; background: var(--panel); color: var(--text); cursor: pointer; transition: all 0.2s; }
        .modal-close:hover { background: var(--error); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <a href="theme-editor.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="logo"><i class="fas fa-file-code"></i> Editor de Paginas</div>
        </div>
        <div class="header-right">
            <?php if ($action === 'edit'): ?>
                <button class="btn btn-outline" onclick="previewPage()"><i class="fas fa-external-link-alt"></i> Visualizar</button>
                <button class="btn btn-primary" onclick="savePage()"><i class="fas fa-save"></i> Salvar Pagina</button>
            <?php else: ?>
                <button class="btn btn-primary" onclick="openNewPageModal()"><i class="fas fa-plus"></i> Nova Pagina</button>
            <?php endif; ?>
        </div>
    </header>
    
    <?php if ($action === 'list'): ?>
    <!-- PAGES LIST -->
    <main class="main">
        <div class="pages-header">
            <h1><i class="fas fa-layer-group"></i> Suas Paginas</h1>
        </div>
        
        <div class="pages-grid">
            <div class="page-card add-card" onclick="openNewPageModal()">
                <i class="fas fa-plus-circle"></i>
                <span>Criar Nova Pagina</span>
            </div>
            
            <?php foreach ($pages as $page): ?>
            <div class="page-card">
                <div class="page-card-header">
                    <div class="page-title">
                        <span class="status <?php echo $page['is_active'] ? 'active' : 'inactive'; ?>"></span>
                        <?php echo htmlspecialchars($page['title']); ?>
                    </div>
                    <div class="page-slug">/pagina/<?php echo htmlspecialchars($page['slug']); ?></div>
                </div>
                <div class="page-card-body">
                    <div class="page-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($page['created_at'])); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($page['updated_at'])); ?></span>
                    </div>
                </div>
                <div class="page-card-footer">
                    <a href="?action=edit&id=<?php echo $page['id']; ?>" class="page-btn"><i class="fas fa-edit"></i> Editar</a>
                    <a href="<?php echo APP_URL; ?>/pagina/<?php echo $page['slug']; ?>" target="_blank" class="page-btn"><i class="fas fa-eye"></i> Ver</a>
                    <button class="page-btn danger" onclick="deletePage(<?php echo $page['id']; ?>)"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
    
    <?php else: ?>
    <!-- EDITOR VIEW -->
    <div class="editor-container">
        <aside class="editor-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Configuracoes</h2>
            </div>
            <div class="sidebar-body">
                <input type="hidden" id="pageId" value="<?php echo $editPage['id'] ?? ''; ?>">
                
                <div class="field">
                    <label class="label">
                        Titulo da Pagina
                        <span class="help"><i class="fas fa-question"></i><span class="tip">O titulo que aparece na aba do navegador e nos resultados de busca.</span></span>
                    </label>
                    <input type="text" id="pageTitle" class="input" value="<?php echo htmlspecialchars($editPage['title'] ?? ''); ?>" placeholder="Ex: Sobre Nos">
                </div>
                
                <div class="field">
                    <label class="label">
                        Slug (URL)
                        <span class="help"><i class="fas fa-question"></i><span class="tip">O endereco da pagina. Use apenas letras minusculas, numeros e hifens. Ex: sobre-nos</span></span>
                    </label>
                    <input type="text" id="pageSlug" class="input" value="<?php echo htmlspecialchars($editPage['slug'] ?? ''); ?>" placeholder="sobre-nos" pattern="[a-z0-9\-]+">
                </div>
                
                <div class="field">
                    <label class="label">
                        Meta Descricao (SEO)
                        <span class="help"><i class="fas fa-question"></i><span class="tip">Descricao que aparece nos resultados de busca do Google. Ideal entre 120-160 caracteres.</span></span>
                    </label>
                    <textarea id="pageMeta" class="input" placeholder="Descricao para SEO..."><?php echo htmlspecialchars($editPage['meta_description'] ?? ''); ?></textarea>
                </div>
                
                <div class="toggle-wrap">
                    <span>Pagina Ativa</span>
                    <label class="toggle">
                        <input type="checkbox" id="pageActive" <?php echo ($editPage['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </aside>
        
        <main class="editor-main">
            <div class="code-tabs">
                <button class="code-tab active" data-tab="html" onclick="switchTab('html')"><i class="fab fa-html5"></i> HTML</button>
                <button class="code-tab" data-tab="css" onclick="switchTab('css')"><i class="fab fa-css3-alt"></i> CSS</button>
                <button class="code-tab" data-tab="js" onclick="switchTab('js')"><i class="fab fa-js"></i> JavaScript</button>
            </div>
            
            <div class="code-editors">
                <div class="code-editor active" id="htmlEditor">
                    <div class="line-numbers" id="htmlLines"></div>
                    <textarea id="htmlCode" placeholder="<!-- Seu codigo HTML aqui -->&#10;&#10;<div class=&quot;container&quot;>&#10;    <h1>Titulo</h1>&#10;    <p>Conteudo...</p>&#10;</div>"><?php echo htmlspecialchars($editPage['html_content'] ?? ''); ?></textarea>
                </div>
                <div class="code-editor" id="cssEditor">
                    <div class="line-numbers" id="cssLines"></div>
                    <textarea id="cssCode" placeholder="/* Seus estilos CSS aqui */&#10;&#10;.container {&#10;    max-width: 1200px;&#10;    margin: 0 auto;&#10;    padding: 2rem;&#10;}"><?php echo htmlspecialchars($editPage['css_content'] ?? ''); ?></textarea>
                </div>
                <div class="code-editor" id="jsEditor">
                    <div class="line-numbers" id="jsLines"></div>
                    <textarea id="jsCode" placeholder="// Seu codigo JavaScript aqui&#10;&#10;document.addEventListener('DOMContentLoaded', function() {&#10;    // Codigo executado ao carregar&#10;});"><?php echo htmlspecialchars($editPage['js_content'] ?? ''); ?></textarea>
                </div>
            </div>
        </main>
        
        <aside class="preview-panel">
            <div class="preview-header">
                <h3><i class="fas fa-eye"></i> Preview ao Vivo</h3>
                <div class="preview-actions">
                    <button class="preview-btn" onclick="refreshPreview()" title="Atualizar Preview"><i class="fas fa-sync-alt"></i></button>
                    <button class="preview-btn" id="desktopBtn" onclick="setPreviewSize('desktop')" title="Desktop"><i class="fas fa-desktop"></i></button>
                    <button class="preview-btn" id="tabletBtn" onclick="setPreviewSize('tablet')" title="Tablet"><i class="fas fa-tablet-alt"></i></button>
                    <button class="preview-btn" id="mobileBtn" onclick="setPreviewSize('mobile')" title="Mobile"><i class="fas fa-mobile-alt"></i></button>
                </div>
            </div>
            <div class="preview-frame">
                <iframe id="previewFrame" srcdoc=""></iframe>
            </div>
        </aside>
    </div>
    <?php endif; ?>
    
    <!-- NEW PAGE MODAL -->
    <div class="modal-bg" id="newPageModal">
        <div class="modal">
            <div class="modal-head">
                <h3><i class="fas fa-plus-circle"></i> Nova Pagina</h3>
                <button class="modal-close" onclick="closeNewPageModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label class="label">
                        Titulo da Pagina
                        <span class="help"><i class="fas fa-question"></i><span class="tip">Nome da pagina que aparece no navegador.</span></span>
                    </label>
                    <input type="text" id="newPageTitle" class="input" placeholder="Ex: Sobre Nos" oninput="generateSlug(this.value)">
                </div>
                <div class="field">
                    <label class="label">
                        Slug (URL)
                        <span class="help"><i class="fas fa-question"></i><span class="tip">Endereco amigavel. Gerado automaticamente.</span></span>
                    </label>
                    <input type="text" id="newPageSlug" class="input" placeholder="sobre-nos">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeNewPageModal()">Cancelar</button>
                <button class="btn btn-primary" onclick="createPage()">Criar Pagina</button>
            </div>
        </div>
    </div>
    
    <div class="toast-wrap" id="toasts"></div>
    
    <script>
    const APP = '<?php echo $appUrl; ?>';
    
    // TABS
    function switchTab(tab) {
        document.querySelectorAll('.code-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        document.querySelectorAll('.code-editor').forEach(e => e.classList.remove('active'));
        document.getElementById(tab + 'Editor').classList.add('active');
    }
    
    // LINE NUMBERS
    function updateLineNumbers(textarea, linesEl) {
        const lines = textarea.value.split('\n').length;
        let html = '';
        for (let i = 1; i <= lines; i++) html += `<span>${i}</span>`;
        linesEl.innerHTML = html;
    }
    
    // INIT EDITORS
    <?php if ($action === 'edit'): ?>
    ['html', 'css', 'js'].forEach(type => {
        const textarea = document.getElementById(type + 'Code');
        const lines = document.getElementById(type + 'Lines');
        updateLineNumbers(textarea, lines);
        textarea.addEventListener('input', () => {
            updateLineNumbers(textarea, lines);
            updatePreview();
        });
        textarea.addEventListener('scroll', () => {
            lines.scrollTop = textarea.scrollTop;
        });
        textarea.addEventListener('keydown', e => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + '    ' + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + 4;
                updateLineNumbers(textarea, lines);
            }
        });
    });
    
    // PREVIEW
    function updatePreview() {
        const html = document.getElementById('htmlCode').value;
        const css = document.getElementById('cssCode').value;
        const js = document.getElementById('jsCode').value;
        
        const doc = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Inter', sans-serif; }
                    ${css}
                </style>
            </head>
            <body>
                ${html}
                <script>${js}<\/script>
            </body>
            </html>
        `;
        
        document.getElementById('previewFrame').srcdoc = doc;
    }
    
    function refreshPreview() { updatePreview(); toast('success', 'Preview atualizado!'); }
    
    function setPreviewSize(size) {
        document.querySelectorAll('.preview-btn').forEach(b => b.classList.remove('active'));
        const frame = document.getElementById('previewFrame');
        const sizes = { desktop: '100%', tablet: '768px', mobile: '375px' };
        frame.style.width = sizes[size];
        frame.style.margin = size === 'desktop' ? '0' : '0 auto';
        document.getElementById(size + 'Btn').classList.add('active');
    }
    
    // INITIAL PREVIEW
    setTimeout(updatePreview, 500);
    <?php endif; ?>
    
    // SAVE PAGE
    function savePage() {
        const data = {
            action: document.getElementById('pageId').value ? 'update' : 'create',
            id: document.getElementById('pageId').value || null,
            title: document.getElementById('pageTitle').value,
            slug: document.getElementById('pageSlug').value,
            meta_description: document.getElementById('pageMeta').value,
            html_content: document.getElementById('htmlCode').value,
            css_content: document.getElementById('cssCode').value,
            js_content: document.getElementById('jsCode').value,
            is_active: document.getElementById('pageActive').checked ? 1 : 0
        };
        
        if (!data.title || !data.slug) {
            toast('error', 'Preencha titulo e slug');
            return;
        }
        
        fetch(APP + '/api/admin/pages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                toast('success', 'Pagina salva com sucesso!');
                if (!document.getElementById('pageId').value && result.id) {
                    document.getElementById('pageId').value = result.id;
                }
            } else {
                throw new Error(result.error);
            }
        })
        .catch(e => toast('error', e.message));
    }
    
    function previewPage() {
        const slug = document.getElementById('pageSlug').value;
        if (slug) window.open(APP + '/pagina/' + slug, '_blank');
    }
    
    // NEW PAGE MODAL
    function openNewPageModal() { document.getElementById('newPageModal').classList.add('show'); }
    function closeNewPageModal() { document.getElementById('newPageModal').classList.remove('show'); }
    document.getElementById('newPageModal').onclick = e => { if (e.target.id === 'newPageModal') closeNewPageModal(); };
    
    function generateSlug(title) {
        const slug = title.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
        document.getElementById('newPageSlug').value = slug;
    }
    
    function createPage() {
        const title = document.getElementById('newPageTitle').value;
        const slug = document.getElementById('newPageSlug').value;
        
        if (!title || !slug) {
            toast('error', 'Preencha titulo e slug');
            return;
        }
        
        fetch(APP + '/api/admin/pages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'create', title, slug })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                window.location.href = '?action=edit&id=' + result.id;
            } else {
                throw new Error(result.error);
            }
        })
        .catch(e => toast('error', e.message));
    }
    
    function deletePage(id) {
        if (!confirm('Tem certeza que deseja excluir esta pagina?')) return;
        
        fetch(APP + '/api/admin/pages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', id })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                toast('success', 'Pagina excluida');
                location.reload();
            } else {
                throw new Error(result.error);
            }
        })
        .catch(e => toast('error', e.message));
    }
    
    // KEYBOARD SHORTCUTS
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            savePage();
        }
    });
    
    // TOAST
    function toast(type, msg) {
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        const icons = {success:'check-circle',error:'exclamation-circle',warning:'exclamation-triangle'};
        el.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
        document.getElementById('toasts').appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
    </script>
</body>
</html>
