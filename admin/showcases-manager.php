<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Get all showcases
$showcases = [];
try {
    $stmt = $pdo->query("SELECT * FROM dynamic_showcases ORDER BY display_order ASC, created_at DESC");
    $showcases = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Get all products for selection
$products = [];
try {
    $stmt = $pdo->query("SELECT p.id, p.name, p.price, p.image_path, b.name as brand_name 
                         FROM products p 
                         LEFT JOIN brands b ON p.brand_id = b.id 
                         WHERE p.is_active = 1 
                         ORDER BY p.name ASC");
    $products = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'Gerenciador de Vitrines';
$page_subtitle = 'Crie e gerencie secoes de produtos na pagina inicial';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Vitrines | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --accent: #C7A333;
            --accent-light: rgba(199, 163, 51, 0.15);
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-dark: #0f0f0f;
            --bg-card: #1a1a1a;
            --border: #2a2a2a;
            --text: #f5f5f5;
            --text-muted: #888;
        }

        .showcases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .showcase-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .showcase-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
        }

        .showcase-card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .showcase-info {
            flex: 1;
            min-width: 0;
        }

        .showcase-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .showcase-title {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .showcase-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .showcase-status.active {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }

        .showcase-status.inactive {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .showcase-card-body {
            padding: 20px;
        }

        .showcase-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .showcase-stat {
            text-align: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        .showcase-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent);
        }

        .showcase-stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4px;
        }

        .showcase-products-preview {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            overflow-x: auto;
            padding: 4px 0;
        }

        .showcase-products-preview::-webkit-scrollbar {
            height: 4px;
        }

        .showcase-products-preview::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .product-preview-thumb {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--border);
            transition: border-color 0.2s;
        }

        .product-preview-thumb:hover {
            border-color: var(--accent);
        }

        .product-preview-more {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 8px;
            background: var(--accent-light);
            border: 2px dashed var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .showcase-card-actions {
            display: flex;
            gap: 8px;
        }

        .showcase-card-actions .btn {
            flex: 1;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-edit {
            background: var(--accent);
            color: #000;
        }

        .btn-edit:hover {
            background: #d4b445;
        }

        .btn-products {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .btn-products:hover {
            background: rgba(34, 197, 94, 0.25);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
            width: 44px;
            min-width: 44px;
            flex: none;
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.25);
        }

        /* Add New Card */
        .showcase-card-new {
            border: 2px dashed var(--border);
            background: transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .showcase-card-new:hover {
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .showcase-card-new i {
            font-size: 48px;
            color: var(--text-muted);
            margin-bottom: 16px;
            transition: color 0.3s;
        }

        .showcase-card-new:hover i {
            color: var(--accent);
        }

        .showcase-card-new span {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: color 0.3s;
        }

        .showcase-card-new:hover span {
            color: var(--text);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 20px;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal {
            transform: scale(1) translateY(0);
        }

        .modal-large {
            max-width: 900px;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-group .help-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--accent-light);
            color: var(--accent);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: help;
            position: relative;
        }

        .form-group .help-icon:hover::after {
            content: attr(data-help);
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 12px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-muted);
            white-space: nowrap;
            z-index: 10;
            max-width: 250px;
            white-space: normal;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-switch {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .switch {
            position: relative;
            width: 48px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--border);
            border-radius: 26px;
            transition: 0.3s;
        }

        .switch-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: var(--text);
            border-radius: 50%;
            transition: 0.3s;
        }

        .switch input:checked + .switch-slider {
            background: var(--success);
        }

        .switch input:checked + .switch-slider:before {
            transform: translateX(22px);
        }

        /* Display Type Selection */
        .display-types {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .display-type-option {
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }

        .display-type-option:hover {
            border-color: var(--accent);
        }

        .display-type-option.selected {
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .display-type-option i {
            font-size: 24px;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: block;
        }

        .display-type-option.selected i {
            color: var(--accent);
        }

        .display-type-option span {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .display-type-option.selected span {
            color: var(--text);
        }

        /* Product Selector */
        .products-selector {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .products-search {
            position: relative;
        }

        .products-search input {
            padding-left: 44px;
        }

        .products-search i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .products-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            max-height: 400px;
            overflow-y: auto;
            padding: 4px;
        }

        .product-select-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .product-select-item:hover {
            border-color: var(--accent);
        }

        .product-select-item.selected {
            border-color: var(--success);
            background: rgba(34, 197, 94, 0.1);
        }

        .product-select-item img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
        }

        .product-select-info {
            flex: 1;
            min-width: 0;
        }

        .product-select-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-select-price {
            font-size: 0.8rem;
            color: var(--accent);
            font-weight: 700;
        }

        .product-select-check {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            transition: all 0.2s;
        }

        .product-select-item.selected .product-select-check {
            background: var(--success);
            color: #fff;
        }

        /* Selected products */
        .selected-products {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            min-height: 60px;
        }

        .selected-product-tag {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--accent-light);
            border: 1px solid var(--accent);
            border-radius: 20px;
        }

        .selected-product-tag img {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            object-fit: cover;
        }

        .selected-product-tag span {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .selected-product-tag button {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.2);
            border: none;
            color: var(--danger);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .selected-product-tag button:hover {
            background: var(--danger);
            color: #fff;
        }

        /* Buttons */
        .btn-primary {
            padding: 12px 24px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #d4b445;
        }

        .btn-secondary {
            padding: 12px 24px;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            padding: 14px 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            max-width: 350px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success { border-color: var(--success); }
        .toast.error { border-color: var(--danger); }
        .toast.warning { border-color: var(--warning); }

        .toast i {
            font-size: 1.25rem;
        }

        .toast.success i { color: var(--success); }
        .toast.error i { color: var(--danger); }
        .toast.warning i { color: var(--warning); }

        .toast span {
            flex: 1;
            font-size: 0.875rem;
            color: var(--text);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-muted);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text);
            margin: 0 0 8px 0;
        }

        .empty-state p {
            color: var(--text-muted);
            margin: 0;
        }

        @media (max-width: 768px) {
            .showcases-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .display-types {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-store"></i> Gerenciador de Vitrines</h1>
                        <p>Crie secoes personalizadas de produtos para exibir na pagina inicial</p>
                    </div>
                </div>

                <div class="showcases-grid">
                    <?php foreach ($showcases as $showcase): ?>
                    <?php 
                        // Get products for this showcase
                        $stmt = $pdo->prepare("SELECT p.id, p.name, p.image_path FROM dynamic_showcase_products dsp 
                                               JOIN products p ON dsp.product_id = p.id 
                                               WHERE dsp.showcase_id = ? 
                                               ORDER BY dsp.display_order ASC LIMIT 5");
                        $stmt->execute([$showcase['id']]);
                        $showcaseProducts = $stmt->fetchAll();
                        
                        // Get total count
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dynamic_showcase_products WHERE showcase_id = ?");
                        $stmt->execute([$showcase['id']]);
                        $totalProducts = $stmt->fetchColumn();
                    ?>
                    <div class="showcase-card" data-id="<?php echo $showcase['id']; ?>">
                        <div class="showcase-card-header">
                            <div class="showcase-info">
                                <h3 class="showcase-name">
                                    <i class="fas fa-layer-group" style="color: var(--accent);"></i>
                                    <?php echo htmlspecialchars($showcase['name']); ?>
                                </h3>
                                <p class="showcase-title"><?php echo htmlspecialchars($showcase['title']); ?></p>
                            </div>
                            <span class="showcase-status <?php echo $showcase['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $showcase['is_active'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </div>
                        <div class="showcase-card-body">
                            <div class="showcase-stats">
                                <div class="showcase-stat">
                                    <div class="showcase-stat-value"><?php echo $totalProducts; ?></div>
                                    <div class="showcase-stat-label">Produtos</div>
                                </div>
                                <div class="showcase-stat">
                                    <div class="showcase-stat-value"><?php echo $showcase['max_products'] ?: 'Ilimitado'; ?></div>
                                    <div class="showcase-stat-label">Maximo</div>
                                </div>
                                <div class="showcase-stat">
                                    <div class="showcase-stat-value"><?php echo $showcase['display_order']; ?></div>
                                    <div class="showcase-stat-label">Ordem</div>
                                </div>
                            </div>
                            
                            <?php if (!empty($showcaseProducts)): ?>
                            <div class="showcase-products-preview">
                                <?php foreach ($showcaseProducts as $prod): ?>
                                <img src="<?php echo htmlspecialchars($prod['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($prod['name']); ?>" 
                                     class="product-preview-thumb"
                                     title="<?php echo htmlspecialchars($prod['name']); ?>">
                                <?php endforeach; ?>
                                <?php if ($totalProducts > 5): ?>
                                <div class="product-preview-more">+<?php echo $totalProducts - 5; ?></div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="showcase-products-preview" style="color: var(--text-muted); font-size: 0.85rem;">
                                <i class="fas fa-info-circle"></i>&nbsp; Nenhum produto adicionado
                            </div>
                            <?php endif; ?>
                            
                            <div class="showcase-card-actions">
                                <button class="btn btn-edit" onclick="editShowcase(<?php echo $showcase['id']; ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-products" onclick="manageProducts(<?php echo $showcase['id']; ?>, '<?php echo addslashes($showcase['name']); ?>')">
                                    <i class="fas fa-box"></i> Produtos
                                </button>
                                <button class="btn btn-delete" onclick="deleteShowcase(<?php echo $showcase['id']; ?>, '<?php echo addslashes($showcase['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="showcase-card showcase-card-new" onclick="openCreateModal()">
                        <i class="fas fa-plus-circle"></i>
                        <span>Nova Vitrine</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal-overlay" id="showcaseModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-layer-group"></i> <span id="modalTitle">Nova Vitrine</span></h2>
                <button class="modal-close" onclick="closeModal('showcaseModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="showcaseForm">
                    <input type="hidden" name="id" id="showcaseId">
                    
                    <div class="form-group">
                        <label>
                            Nome Interno
                            <span class="help-icon" data-help="Identificador unico para a vitrine (ex: lancamentos, destaques)">
                                <i class="fas fa-question"></i>
                            </span>
                        </label>
                        <input type="text" class="form-control" name="name" id="showcaseName" placeholder="Ex: lancamentos-2024" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Titulo Exibido
                            <span class="help-icon" data-help="Titulo que aparece na pagina inicial acima dos produtos">
                                <i class="fas fa-question"></i>
                            </span>
                        </label>
                        <input type="text" class="form-control" name="title" id="showcaseTitle" placeholder="Ex: Lancamentos da Semana" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Descricao
                            <span class="help-icon" data-help="Texto descritivo exibido abaixo do titulo (opcional)">
                                <i class="fas fa-question"></i>
                            </span>
                        </label>
                        <textarea class="form-control" name="description" id="showcaseDescription" placeholder="Confira os produtos mais recentes da nossa colecao"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                Ordem de Exibicao
                                <span class="help-icon" data-help="Menor numero aparece primeiro na pagina">
                                    <i class="fas fa-question"></i>
                                </span>
                            </label>
                            <input type="number" class="form-control" name="display_order" id="showcaseOrder" value="0" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                Maximo de Produtos
                                <span class="help-icon" data-help="Limite de produtos exibidos (deixe vazio para ilimitado)">
                                    <i class="fas fa-question"></i>
                                </span>
                            </label>
                            <input type="number" class="form-control" name="max_products" id="showcaseMaxProducts" placeholder="Ilimitado" min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Tipo de Exibicao
                            <span class="help-icon" data-help="Como os produtos serao exibidos na pagina">
                                <i class="fas fa-question"></i>
                            </span>
                        </label>
                        <div class="display-types">
                            <div class="display-type-option selected" data-value="carousel">
                                <i class="fas fa-arrows-left-right"></i>
                                <span>Carrossel</span>
                            </div>
                            <div class="display-type-option" data-value="grid">
                                <i class="fas fa-th-large"></i>
                                <span>Grade</span>
                            </div>
                            <div class="display-type-option" data-value="list">
                                <i class="fas fa-list"></i>
                                <span>Lista</span>
                            </div>
                        </div>
                        <input type="hidden" name="display_type" id="showcaseDisplayType" value="carousel">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            URL do Banner (opcional)
                            <span class="help-icon" data-help="Imagem de banner exibida acima dos produtos">
                                <i class="fas fa-question"></i>
                            </span>
                        </label>
                        <input type="url" class="form-control" name="banner_url" id="showcaseBanner" placeholder="https://exemplo.com/banner.jpg">
                    </div>
                    
                    <div class="form-group">
                        <div class="form-switch">
                            <label class="switch">
                                <input type="checkbox" name="is_active" id="showcaseActive" checked>
                                <span class="switch-slider"></span>
                            </label>
                            <span style="color: var(--text);">Vitrine Ativa</span>
                            <span class="help-icon" data-help="Vitrines inativas nao aparecem na pagina inicial">
                                <i class="fas fa-question"></i>
                            </span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('showcaseModal')">Cancelar</button>
                <button class="btn-primary" onclick="saveShowcase()">
                    <i class="fas fa-save"></i> Salvar Vitrine
                </button>
            </div>
        </div>
    </div>

    <!-- Products Modal -->
    <div class="modal-overlay" id="productsModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-box"></i> <span id="productsModalTitle">Produtos da Vitrine</span></h2>
                <button class="modal-close" onclick="closeModal('productsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentShowcaseId">
                
                <div class="form-group">
                    <label>Produtos Selecionados</label>
                    <div class="selected-products" id="selectedProducts">
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Selecione produtos abaixo...</span>
                    </div>
                </div>
                
                <div class="products-selector">
                    <div class="products-search">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="productSearch" placeholder="Buscar produtos...">
                    </div>
                    
                    <div class="products-list" id="productsList">
                        <?php foreach ($products as $product): ?>
                        <div class="product-select-item" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" onclick="toggleProduct(this)">
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="product-select-info">
                                <div class="product-select-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-select-price">R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></div>
                            </div>
                            <div class="product-select-check">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('productsModal')">Fechar</button>
                <button class="btn-primary" onclick="saveProducts()">
                    <i class="fas fa-save"></i> Salvar Produtos
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        const APP = '<?php echo APP_URL; ?>';
        let selectedProductIds = [];
        
        // Toast
        function toast(type, message) {
            const container = document.getElementById('toastContainer');
            const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
            
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas ${icons[type]}"></i><span>${message}</span>`;
            container.appendChild(t);
            
            setTimeout(() => {
                t.style.opacity = '0';
                setTimeout(() => t.remove(), 300);
            }, 4000);
        }
        
        // Modal
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        // Display Type Selection
        document.querySelectorAll('.display-type-option').forEach(opt => {
            opt.addEventListener('click', () => {
                document.querySelectorAll('.display-type-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                document.getElementById('showcaseDisplayType').value = opt.dataset.value;
            });
        });
        
        // Create
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Nova Vitrine';
            document.getElementById('showcaseForm').reset();
            document.getElementById('showcaseId').value = '';
            document.getElementById('showcaseActive').checked = true;
            document.querySelectorAll('.display-type-option').forEach(o => o.classList.remove('selected'));
            document.querySelector('.display-type-option[data-value="carousel"]').classList.add('selected');
            document.getElementById('showcaseDisplayType').value = 'carousel';
            openModal('showcaseModal');
        }
        
        // Edit
        async function editShowcase(id) {
            try {
                const res = await fetch(`${APP}/api/admin/dynamic-showcases.php?action=get&id=${id}`);
                const data = await res.json();
                
                if (!data.success) throw new Error(data.error);
                
                const s = data.showcase;
                document.getElementById('modalTitle').textContent = 'Editar Vitrine';
                document.getElementById('showcaseId').value = s.id;
                document.getElementById('showcaseName').value = s.name;
                document.getElementById('showcaseTitle').value = s.title;
                document.getElementById('showcaseDescription').value = s.description || '';
                document.getElementById('showcaseOrder').value = s.display_order || 0;
                document.getElementById('showcaseMaxProducts').value = s.max_products || '';
                document.getElementById('showcaseBanner').value = s.banner_url || '';
                document.getElementById('showcaseActive').checked = s.is_active == 1;
                
                const displayType = s.display_type || 'carousel';
                document.querySelectorAll('.display-type-option').forEach(o => o.classList.remove('selected'));
                document.querySelector(`.display-type-option[data-value="${displayType}"]`)?.classList.add('selected');
                document.getElementById('showcaseDisplayType').value = displayType;
                
                openModal('showcaseModal');
            } catch (e) {
                toast('error', e.message);
            }
        }
        
        // Save
        async function saveShowcase() {
            const form = document.getElementById('showcaseForm');
            const data = {
                action: document.getElementById('showcaseId').value ? 'update' : 'create',
                id: document.getElementById('showcaseId').value || null,
                name: document.getElementById('showcaseName').value,
                title: document.getElementById('showcaseTitle').value,
                description: document.getElementById('showcaseDescription').value,
                display_order: parseInt(document.getElementById('showcaseOrder').value) || 0,
                max_products: document.getElementById('showcaseMaxProducts').value || null,
                display_type: document.getElementById('showcaseDisplayType').value,
                banner_url: document.getElementById('showcaseBanner').value,
                is_active: document.getElementById('showcaseActive').checked ? 1 : 0
            };
            
            if (!data.name || !data.title) {
                toast('error', 'Nome e titulo sao obrigatorios');
                return;
            }
            
            try {
                const res = await fetch(`${APP}/api/admin/dynamic-showcases.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (!result.success) throw new Error(result.error);
                
                toast('success', result.message);
                closeModal('showcaseModal');
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                toast('error', e.message);
            }
        }
        
        // Delete
        async function deleteShowcase(id, name) {
            if (!confirm(`Tem certeza que deseja excluir a vitrine "${name}"?\n\nEsta acao nao pode ser desfeita.`)) return;
            
            try {
                const res = await fetch(`${APP}/api/admin/dynamic-showcases.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id })
                });
                const data = await res.json();
                
                if (!data.success) throw new Error(data.error);
                
                toast('success', data.message);
                document.querySelector(`.showcase-card[data-id="${id}"]`)?.remove();
            } catch (e) {
                toast('error', e.message);
            }
        }
        
        // Products Management
        async function manageProducts(showcaseId, showcaseName) {
            document.getElementById('currentShowcaseId').value = showcaseId;
            document.getElementById('productsModalTitle').textContent = `Produtos: ${showcaseName}`;
            selectedProductIds = [];
            
            // Reset all selections
            document.querySelectorAll('.product-select-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Load current products
            try {
                const res = await fetch(`${APP}/api/admin/dynamic-showcases.php?action=get_products&showcase_id=${showcaseId}`);
                const data = await res.json();
                
                if (data.success && data.products) {
                    data.products.forEach(p => {
                        selectedProductIds.push(p.id);
                        document.querySelector(`.product-select-item[data-id="${p.id}"]`)?.classList.add('selected');
                    });
                }
            } catch (e) {
                console.error(e);
            }
            
            updateSelectedDisplay();
            openModal('productsModal');
        }
        
        function toggleProduct(element) {
            const id = parseInt(element.dataset.id);
            const name = element.dataset.name;
            const img = element.querySelector('img').src;
            
            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedProductIds = selectedProductIds.filter(pid => pid !== id);
            } else {
                element.classList.add('selected');
                selectedProductIds.push(id);
            }
            
            updateSelectedDisplay();
        }
        
        function updateSelectedDisplay() {
            const container = document.getElementById('selectedProducts');
            
            if (selectedProductIds.length === 0) {
                container.innerHTML = '<span style="color: var(--text-muted); font-size: 0.85rem;">Selecione produtos abaixo...</span>';
                return;
            }
            
            container.innerHTML = selectedProductIds.map(id => {
                const item = document.querySelector(`.product-select-item[data-id="${id}"]`);
                if (!item) return '';
                return `
                    <div class="selected-product-tag">
                        <img src="${item.querySelector('img').src}" alt="">
                        <span>${item.dataset.name}</span>
                        <button onclick="removeProduct(${id}, event)"><i class="fas fa-times"></i></button>
                    </div>
                `;
            }).join('');
        }
        
        function removeProduct(id, event) {
            event.stopPropagation();
            document.querySelector(`.product-select-item[data-id="${id}"]`)?.classList.remove('selected');
            selectedProductIds = selectedProductIds.filter(pid => pid !== id);
            updateSelectedDisplay();
        }
        
        async function saveProducts() {
            const showcaseId = document.getElementById('currentShowcaseId').value;
            
            try {
                // First, get current products to determine adds/removes
                const currentRes = await fetch(`${APP}/api/admin/dynamic-showcases.php?action=get_products&showcase_id=${showcaseId}`);
                const currentData = await currentRes.json();
                const currentIds = currentData.success ? currentData.products.map(p => p.id) : [];
                
                // Products to add
                const toAdd = selectedProductIds.filter(id => !currentIds.includes(id));
                
                // Products to remove
                const toRemove = currentIds.filter(id => !selectedProductIds.includes(id));
                
                // Add new products
                for (const productId of toAdd) {
                    await fetch(`${APP}/api/admin/dynamic-showcases.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'add_product', showcase_id: showcaseId, product_id: productId })
                    });
                }
                
                // Remove products
                for (const productId of toRemove) {
                    await fetch(`${APP}/api/admin/dynamic-showcases.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'remove_product', showcase_id: showcaseId, product_id: productId })
                    });
                }
                
                toast('success', 'Produtos atualizados com sucesso!');
                closeModal('productsModal');
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                toast('error', e.message);
            }
        }
        
        // Product Search
        document.getElementById('productSearch').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('.product-select-item').forEach(item => {
                const name = item.dataset.name.toLowerCase();
                item.style.display = name.includes(search) ? 'flex' : 'none';
            });
        });
        
        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
        
        // ESC to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>
