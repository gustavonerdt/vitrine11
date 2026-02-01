<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Cupons de Desconto';
$page_subtitle = 'Gerencie os cupons de desconto da sua loja';

$success = '';
$error = '';

// Criar tabela se nao existir
if (!db_table_exists($pdo, 'coupons')) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `coupons` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `description` VARCHAR(255) DEFAULT NULL,
                `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
                `discount_value` DECIMAL(10, 2) NOT NULL,
                `min_order_value` DECIMAL(10, 2) DEFAULT 0,
                `max_discount` DECIMAL(10, 2) DEFAULT NULL,
                `usage_limit` INT DEFAULT NULL,
                `usage_count` INT DEFAULT 0,
                `valid_from` DATE DEFAULT NULL,
                `valid_until` DATE DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_code` (`code`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_valid_dates` (`valid_from`, `valid_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Error creating coupons table: " . $e->getMessage());
    }
}

$csrf = generateCsrfToken();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguranca invalido.";
    } else {
        try {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'add_coupon') {
                $code = strtoupper(trim($_POST['code'] ?? ''));
                $description = trim($_POST['description'] ?? '');
                $discount_type = $_POST['discount_type'] ?? 'percentage';
                $discount_value = floatval($_POST['discount_value'] ?? 0);
                $min_order_value = floatval($_POST['min_order_value'] ?? 0);
                $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
                $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
                $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
                $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($code)) {
                    throw new Exception("Codigo do cupom e obrigatorio.");
                }
                if ($discount_value <= 0) {
                    throw new Exception("Valor do desconto deve ser maior que zero.");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO coupons (code, description, discount_type, discount_value, min_order_value, max_discount, usage_limit, valid_from, valid_until, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $description, $discount_type, $discount_value, $min_order_value, $max_discount, $usage_limit, $valid_from, $valid_until, $is_active]);
                
                $success = "Cupom criado com sucesso!";
                logActivity($pdo, $_SESSION['user_id'], 'create_coupon', "Criou cupom: $code");
                
            } elseif ($action === 'update_coupon') {
                $id = (int)$_POST['id'];
                $code = strtoupper(trim($_POST['code'] ?? ''));
                $description = trim($_POST['description'] ?? '');
                $discount_type = $_POST['discount_type'] ?? 'percentage';
                $discount_value = floatval($_POST['discount_value'] ?? 0);
                $min_order_value = floatval($_POST['min_order_value'] ?? 0);
                $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
                $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
                $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
                $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE coupons SET code = ?, description = ?, discount_type = ?, discount_value = ?, 
                    min_order_value = ?, max_discount = ?, usage_limit = ?, valid_from = ?, valid_until = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $description, $discount_type, $discount_value, $min_order_value, $max_discount, $usage_limit, $valid_from, $valid_until, $is_active, $id]);
                
                $success = "Cupom atualizado com sucesso!";
                logActivity($pdo, $_SESSION['user_id'], 'update_coupon', "Atualizou cupom ID: $id");
                
            } elseif ($action === 'delete_coupon') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$id]);
                
                $success = "Cupom excluido com sucesso!";
                logActivity($pdo, $_SESSION['user_id'], 'delete_coupon', "Excluiu cupom ID: $id");
                
            } elseif ($action === 'toggle_coupon') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Status do cupom alterado.";
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Buscar cupons
$coupons = [];
try {
    $stmt = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC");
    $coupons = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Coupons fetch error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
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
                        <h1><i class="fas fa-tag"></i> <?php echo $page_title; ?></h1>
                        <p><?php echo $page_subtitle; ?></p>
                    </div>
                    <button class="btn btn-primary" onclick="openCouponModal()">
                        <i class="fas fa-plus"></i> Novo Cupom
                    </button>
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

                <div class="admin-card">
                    <div class="card-header">
                        <h3>Lista de Cupons</h3>
                        <span class="card-badge"><?php echo count($coupons); ?> cupons</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($coupons)): ?>
                            <div class="empty-state" style="text-align: center; padding: 3rem 2rem;">
                                <i class="fas fa-tag" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 1rem;"></i>
                                <p style="font-size: 1.125rem; color: var(--admin-text-primary); margin-bottom: 0.5rem; font-weight: 600;">Nenhum cupom cadastrado ainda.</p>
                                <small style="color: var(--admin-text-muted);">Clique em "Novo Cupom" para criar seu primeiro cupom de desconto.</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Desconto</th>
                                            <th>Validade</th>
                                            <th>Uso</th>
                                            <th>Status</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($coupons as $coupon): ?>
                                            <tr>
                                                <td>
                                                    <strong style="font-family: monospace; font-size: 1rem; color: var(--admin-accent);">
                                                        <?php echo htmlspecialchars($coupon['code']); ?>
                                                    </strong>
                                                    <?php if (!empty($coupon['description'])): ?>
                                                        <br><small style="color: var(--admin-text-muted);"><?php echo htmlspecialchars($coupon['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                                        <span style="color: #22c55e; font-weight: 700;"><?php echo number_format($coupon['discount_value'], 0); ?>%</span>
                                                        <?php if (!empty($coupon['max_discount'])): ?>
                                                            <br><small style="color: var(--admin-text-muted);">Max: R$ <?php echo number_format($coupon['max_discount'], 2, ',', '.'); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: #22c55e; font-weight: 700;">R$ <?php echo number_format($coupon['discount_value'], 2, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($coupon['min_order_value'] > 0): ?>
                                                        <br><small style="color: var(--admin-text-muted);">Min: R$ <?php echo number_format($coupon['min_order_value'], 2, ',', '.'); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $today = date('Y-m-d');
                                                    $isExpired = !empty($coupon['valid_until']) && $today > $coupon['valid_until'];
                                                    $notYetValid = !empty($coupon['valid_from']) && $today < $coupon['valid_from'];
                                                    ?>
                                                    <?php if (!empty($coupon['valid_from']) || !empty($coupon['valid_until'])): ?>
                                                        <?php if ($isExpired): ?>
                                                            <span style="color: #ef4444;">Expirado</span>
                                                        <?php elseif ($notYetValid): ?>
                                                            <span style="color: #f59e0b;">A partir de <?php echo date('d/m/Y', strtotime($coupon['valid_from'])); ?></span>
                                                        <?php else: ?>
                                                            <?php if (!empty($coupon['valid_from'])): ?>
                                                                De <?php echo date('d/m/Y', strtotime($coupon['valid_from'])); ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($coupon['valid_until'])): ?>
                                                                <br>Ate <?php echo date('d/m/Y', strtotime($coupon['valid_until'])); ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--admin-text-muted);">Sem limite</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($coupon['usage_limit'])): ?>
                                                        <span style="<?php echo $coupon['usage_count'] >= $coupon['usage_limit'] ? 'color: #ef4444;' : ''; ?>">
                                                            <?php echo $coupon['usage_count']; ?> / <?php echo $coupon['usage_limit']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?php echo $coupon['usage_count']; ?> usos
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                        <input type="hidden" name="action" value="toggle_coupon">
                                                        <input type="hidden" name="id" value="<?php echo $coupon['id']; ?>">
                                                        <button type="submit" class="badge-toggle <?php echo $coupon['is_active'] ? 'active' : 'inactive'; ?>">
                                                            <?php echo $coupon['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <button class="btn-icon-sm" onclick="editCoupon(<?php echo htmlspecialchars(json_encode($coupon)); ?>)" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este cupom?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                            <input type="hidden" name="action" value="delete_coupon">
                                                            <input type="hidden" name="id" value="<?php echo $coupon['id']; ?>">
                                                            <button type="submit" class="btn-icon-sm btn-danger" title="Excluir">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Cupom -->
    <div id="couponModal" class="admin-modal" style="display: none;">
        <div class="admin-modal-content">
            <div class="admin-modal-header">
                <h3 id="couponModalTitle">Novo Cupom</h3>
                <button type="button" class="admin-modal-close" onclick="closeCouponModal()">&times;</button>
            </div>
            <form method="POST" id="couponForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" id="couponAction" value="add_coupon">
                <input type="hidden" name="id" id="couponId" value="">
                
                <div class="admin-modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Codigo do Cupom *</label>
                            <input type="text" name="code" id="couponCode" required placeholder="EX: DESCONTO10" style="text-transform: uppercase;">
                            <small class="form-hint">O codigo sera convertido para maiusculas automaticamente</small>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Desconto *</label>
                            <select name="discount_type" id="couponDiscountType" required>
                                <option value="percentage">Porcentagem (%)</option>
                                <option value="fixed">Valor Fixo (R$)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Descricao (opcional)</label>
                        <input type="text" name="description" id="couponDescription" placeholder="Descricao interna do cupom">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valor do Desconto *</label>
                            <input type="number" name="discount_value" id="couponDiscountValue" required min="0.01" step="0.01" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Desconto Maximo (R$)</label>
                            <input type="number" name="max_discount" id="couponMaxDiscount" min="0" step="0.01" placeholder="Sem limite">
                            <small class="form-hint">Apenas para descontos em porcentagem</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valor Minimo do Pedido (R$)</label>
                            <input type="number" name="min_order_value" id="couponMinOrderValue" min="0" step="0.01" placeholder="0.00" value="0">
                        </div>
                        <div class="form-group">
                            <label>Limite de Usos</label>
                            <input type="number" name="usage_limit" id="couponUsageLimit" min="1" placeholder="Sem limite">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valido a partir de</label>
                            <input type="date" name="valid_from" id="couponValidFrom">
                        </div>
                        <div class="form-group">
                            <label>Valido ate</label>
                            <input type="date" name="valid_until" id="couponValidUntil">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="couponIsActive" checked>
                            <span>Cupom ativo</span>
                        </label>
                    </div>
                </div>
                
                <div class="admin-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCouponModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Cupom</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .admin-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    
    .admin-modal-content {
        background: var(--admin-bg-card);
        border-radius: 16px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid var(--admin-border);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }
    
    .admin-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--admin-border);
    }
    
    .admin-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--admin-text-primary);
    }
    
    .admin-modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--admin-text-muted);
        padding: 0;
        line-height: 1;
    }
    
    .admin-modal-close:hover {
        color: var(--admin-text-primary);
    }
    
    .admin-modal-body {
        padding: 1.5rem;
    }
    
    .admin-modal-footer {
        display: flex;
        gap: 1rem;
        padding: 1.5rem;
        border-top: 1px solid var(--admin-border);
        justify-content: flex-end;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    @media (max-width: 600px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    .badge-toggle {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.75rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .badge-toggle.active {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
    }
    
    .badge-toggle.inactive {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    
    .btn-icon-sm {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--admin-bg-secondary);
        color: var(--admin-text-primary);
        transition: all 0.3s;
    }
    
    .btn-icon-sm:hover {
        background: var(--admin-accent);
        color: #000;
    }
    
    .btn-icon-sm.btn-danger:hover {
        background: var(--admin-error);
        color: #fff;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }
    
    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .admin-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: var(--admin-text-muted);
        border-bottom: 2px solid var(--admin-border);
    }
    
    .admin-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--admin-border);
        color: var(--admin-text-primary);
    }
    
    .admin-table tbody tr:hover {
        background: var(--admin-bg-hover);
    }
    </style>

    <script>
    function openCouponModal() {
        document.getElementById('couponModalTitle').textContent = 'Novo Cupom';
        document.getElementById('couponAction').value = 'add_coupon';
        document.getElementById('couponId').value = '';
        document.getElementById('couponForm').reset();
        document.getElementById('couponIsActive').checked = true;
        document.getElementById('couponModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeCouponModal() {
        document.getElementById('couponModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    function editCoupon(coupon) {
        document.getElementById('couponModalTitle').textContent = 'Editar Cupom';
        document.getElementById('couponAction').value = 'update_coupon';
        document.getElementById('couponId').value = coupon.id;
        document.getElementById('couponCode').value = coupon.code;
        document.getElementById('couponDescription').value = coupon.description || '';
        document.getElementById('couponDiscountType').value = coupon.discount_type;
        document.getElementById('couponDiscountValue').value = coupon.discount_value;
        document.getElementById('couponMaxDiscount').value = coupon.max_discount || '';
        document.getElementById('couponMinOrderValue').value = coupon.min_order_value || 0;
        document.getElementById('couponUsageLimit').value = coupon.usage_limit || '';
        document.getElementById('couponValidFrom').value = coupon.valid_from || '';
        document.getElementById('couponValidUntil').value = coupon.valid_until || '';
        document.getElementById('couponIsActive').checked = coupon.is_active == 1;
        document.getElementById('couponModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('couponModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCouponModal();
        }
    });
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
