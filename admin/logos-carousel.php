<?php
/**
 * Gerenciador de Logos do Carrossel
 * Permite adicionar, editar e remover logos que aparecem no carrossel da vitrine
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Criar tabela se nao existir
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS carousel_logos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            link_url VARCHAR(500) DEFAULT NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    error_log("Error creating carousel_logos table: " . $e->getMessage());
}

$success = '';
$error = '';

// Processar acoes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        
        if (!empty($name) && !empty($image_url)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO carousel_logos (name, image_url, link_url, display_order, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$name, $image_url, $link_url, $display_order]);
                $success = "Logo adicionado com sucesso!";
            } catch (PDOException $e) {
                $error = "Erro ao adicionar logo: " . $e->getMessage();
            }
        } else {
            $error = "Nome e URL da imagem sao obrigatorios.";
        }
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        
        if ($id > 0 && !empty($name) && !empty($image_url)) {
            try {
                $stmt = $pdo->prepare("UPDATE carousel_logos SET name = ?, image_url = ?, link_url = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$name, $image_url, $link_url, $display_order, $id]);
                $success = "Logo atualizado com sucesso!";
            } catch (PDOException $e) {
                $error = "Erro ao atualizar logo: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM carousel_logos WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Logo removido com sucesso!";
            } catch (PDOException $e) {
                $error = "Erro ao remover logo: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE carousel_logos SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Status atualizado!";
            } catch (PDOException $e) {
                $error = "Erro ao atualizar status: " . $e->getMessage();
            }
        }
    }
}

// Buscar logos
$logos = [];
try {
    $stmt = $pdo->query("SELECT * FROM carousel_logos ORDER BY display_order ASC, created_at DESC");
    $logos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching logos: " . $e->getMessage());
}

$page_title = 'Logos do Carrossel';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .logos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        
        .logo-card {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius-xl);
            overflow: hidden;
            transition: all var(--admin-transition-fast);
        }
        
        .logo-card:hover {
            border-color: var(--admin-accent);
            box-shadow: var(--admin-shadow-lg);
            transform: translateY(-4px);
        }
        
        .logo-card.inactive {
            opacity: 0.5;
        }
        
        .logo-preview {
            height: 160px;
            background: var(--admin-bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
        }
        
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .logo-status {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: var(--admin-radius-full);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .logo-status.active {
            background: var(--admin-success-bg);
            color: var(--admin-success);
        }
        
        .logo-status.inactive {
            background: var(--admin-error-bg);
            color: var(--admin-error);
        }
        
        .logo-order {
            position: absolute;
            top: 12px;
            left: 12px;
            width: 32px;
            height: 32px;
            background: var(--admin-accent);
            color: #000;
            border-radius: var(--admin-radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        
        .logo-info {
            padding: 20px;
        }
        
        .logo-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--admin-text-primary);
            margin-bottom: 8px;
        }
        
        .logo-link {
            font-size: 0.8rem;
            color: var(--admin-text-muted);
            word-break: break-all;
            margin-bottom: 16px;
        }
        
        .logo-actions {
            display: flex;
            gap: 10px;
        }
        
        .logo-actions button {
            flex: 1;
            padding: 10px;
            border-radius: var(--admin-radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--admin-transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-edit {
            background: var(--admin-info-bg);
            border: 1px solid var(--admin-info-border);
            color: var(--admin-info);
        }
        
        .btn-edit:hover {
            background: var(--admin-info);
            color: white;
        }
        
        .btn-toggle {
            background: var(--admin-warning-bg);
            border: 1px solid var(--admin-warning-border);
            color: var(--admin-warning);
        }
        
        .btn-toggle:hover {
            background: var(--admin-warning);
            color: white;
        }
        
        .btn-delete {
            background: var(--admin-error-bg);
            border: 1px solid var(--admin-error-border);
            color: var(--admin-error);
        }
        
        .btn-delete:hover {
            background: var(--admin-error);
            color: white;
        }
        
        .add-logo-card {
            border: 2px dashed var(--admin-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            cursor: pointer;
            transition: all var(--admin-transition-fast);
        }
        
        .add-logo-card:hover {
            border-color: var(--admin-accent);
            background: var(--admin-accent-light);
        }
        
        .add-logo-card i {
            font-size: 3rem;
            color: var(--admin-text-subtle);
            margin-bottom: 16px;
        }
        
        .add-logo-card span {
            font-weight: 600;
            color: var(--admin-text-muted);
        }
        
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .preview-container {
            background: var(--admin-bg-secondary);
            border-radius: var(--admin-radius-lg);
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        
        .preview-container img {
            max-width: 200px;
            max-height: 80px;
            object-fit: contain;
        }
        
        .preview-placeholder {
            color: var(--admin-text-subtle);
            font-size: 0.9rem;
        }
        
        .info-box {
            background: var(--admin-info-bg);
            border: 1px solid var(--admin-info-border);
            border-radius: var(--admin-radius-lg);
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        
        .info-box h4 {
            color: var(--admin-info);
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box p {
            color: var(--admin-text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }
    </style>
</head>
<body class="page-enter">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-grip-horizontal"></i> Logos do Carrossel</h1>
                        <p>Gerencie as logos/marcas que aparecem no carrossel da vitrine</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success animate-slideDown">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger animate-slideDown">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Como Funciona</h4>
                    <p>
                        Adicione logos de marcas parceiras, selos de qualidade ou qualquer imagem que deseja exibir no carrossel da vitrine.
                        <br><strong>Desktop:</strong> Mostra 4 logos por vez | <strong>Celular:</strong> Mostra 3 logos por vez
                        <br>Use a ordem para definir a sequencia de exibicao. Clique em "Ativar/Desativar" para controlar a visibilidade.
                    </p>
                </div>

                <div class="logos-grid">
                    <!-- Botao Adicionar -->
                    <div class="logo-card add-logo-card" onclick="openAddModal()">
                        <i class="fas fa-plus-circle"></i>
                        <span>Adicionar Nova Logo</span>
                    </div>
                    
                    <?php foreach ($logos as $logo): ?>
                    <div class="logo-card <?php echo $logo['is_active'] ? '' : 'inactive'; ?>">
                        <div class="logo-preview">
                            <span class="logo-order"><?php echo $logo['display_order']; ?></span>
                            <span class="logo-status <?php echo $logo['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $logo['is_active'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                            <img src="<?php echo htmlspecialchars($logo['image_url']); ?>" alt="<?php echo htmlspecialchars($logo['name']); ?>">
                        </div>
                        <div class="logo-info">
                            <div class="logo-name"><?php echo htmlspecialchars($logo['name']); ?></div>
                            <?php if (!empty($logo['link_url'])): ?>
                            <div class="logo-link">
                                <i class="fas fa-link"></i> <?php echo htmlspecialchars($logo['link_url']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="logo-actions">
                                <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($logo)); ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <form method="POST" style="flex: 1; display: contents;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $logo['id']; ?>">
                                    <button type="submit" class="btn-toggle">
                                        <i class="fas fa-<?php echo $logo['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="flex: 1; display: contents;" onsubmit="return confirm('Tem certeza que deseja excluir esta logo?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $logo['id']; ?>">
                                    <button type="submit" class="btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Adicionar/Editar -->
    <div class="modal-overlay" id="logoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-image"></i> <span id="modalTitle">Adicionar Logo</span></h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="logoForm" class="modal-form">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId" value="">
                    
                    <div class="form-group">
                        <label><i class="fas fa-signature"></i> Nome da Logo/Marca</label>
                        <input type="text" name="name" id="formName" placeholder="Ex: Nike, Adidas, Selo de Qualidade" required>
                        <span class="form-hint">Nome para identificacao interna</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> URL da Imagem</label>
                        <input type="url" name="image_url" id="formImageUrl" placeholder="https://exemplo.com/logo.png" required oninput="updatePreview()">
                        <span class="form-hint">Cole o link da imagem (PNG ou JPG com fundo transparente recomendado)</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-eye"></i> Preview</label>
                        <div class="preview-container" id="previewContainer">
                            <span class="preview-placeholder">A imagem aparecera aqui</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-link"></i> Link (Opcional)</label>
                        <input type="url" name="link_url" id="formLinkUrl" placeholder="https://exemplo.com">
                        <span class="form-hint">Se preenchido, ao clicar na logo o usuario sera redirecionado</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sort-numeric-up"></i> Ordem de Exibicao</label>
                        <input type="number" name="display_order" id="formDisplayOrder" value="0" min="0">
                        <span class="form-hint">Logos com menor numero aparecem primeiro</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" form="logoForm" class="btn-primary">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>

    <script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Adicionar Logo';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formImageUrl').value = '';
        document.getElementById('formLinkUrl').value = '';
        document.getElementById('formDisplayOrder').value = '0';
        document.getElementById('previewContainer').innerHTML = '<span class="preview-placeholder">A imagem aparecera aqui</span>';
        document.getElementById('logoModal').classList.add('show');
    }
    
    function openEditModal(logo) {
        document.getElementById('modalTitle').textContent = 'Editar Logo';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = logo.id;
        document.getElementById('formName').value = logo.name;
        document.getElementById('formImageUrl').value = logo.image_url;
        document.getElementById('formLinkUrl').value = logo.link_url || '';
        document.getElementById('formDisplayOrder').value = logo.display_order;
        updatePreview();
        document.getElementById('logoModal').classList.add('show');
    }
    
    function closeModal() {
        document.getElementById('logoModal').classList.remove('show');
    }
    
    function updatePreview() {
        const url = document.getElementById('formImageUrl').value;
        const container = document.getElementById('previewContainer');
        
        if (url) {
            container.innerHTML = '<img src="' + url + '" onerror="this.parentElement.innerHTML=\'<span class=preview-placeholder>Erro ao carregar imagem</span>\'">';
        } else {
            container.innerHTML = '<span class="preview-placeholder">A imagem aparecera aqui</span>';
        }
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('logoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
