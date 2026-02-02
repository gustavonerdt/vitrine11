<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Leads';
$page_subtitle = 'Carrinhos abandonados e potenciais clientes';

$success = '';
$error = '';
$csrf = generateCsrfToken();

// Criar tabela se nao existir
if (!db_table_exists($pdo, 'leads')) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `leads` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) DEFAULT NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) DEFAULT NULL,
                `cpf_cnpj` VARCHAR(20) DEFAULT NULL,
                `cep` VARCHAR(10) DEFAULT NULL,
                `street` VARCHAR(255) DEFAULT NULL,
                `number` VARCHAR(20) DEFAULT NULL,
                `neighborhood` VARCHAR(100) DEFAULT NULL,
                `city` VARCHAR(100) DEFAULT NULL,
                `state` VARCHAR(2) DEFAULT NULL,
                `cart_data` LONGTEXT DEFAULT NULL,
                `cart_total` DECIMAL(10,2) DEFAULT 0.00,
                `checkout_step` ENUM('cart','delivery','payment') DEFAULT 'cart',
                `source` VARCHAR(100) DEFAULT 'checkout',
                `opted_in` TINYINT(1) DEFAULT 1,
                `recovered` TINYINT(1) DEFAULT 0,
                `session_id` VARCHAR(255) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_email` (`email`),
                KEY `idx_checkout_step` (`checkout_step`),
                KEY `idx_recovered` (`recovered`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Error creating leads table: " . $e->getMessage());
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguranca invalido.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'delete_lead') {
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Lead excluido com sucesso!";
            } catch (PDOException $e) {
                $error = "Erro ao excluir lead.";
            }
        } elseif ($action === 'export_leads') {
            // Exportar para CSV
            try {
                $stmt = $pdo->query("SELECT email, name, phone, opted_in, source, created_at FROM leads ORDER BY created_at DESC");
                $leads = $stmt->fetchAll();
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=leads_' . date('Y-m-d') . '.csv');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['E-mail', 'Nome', 'Telefone', 'Optou In', 'Origem', 'Data']);
                
                foreach ($leads as $lead) {
                    fputcsv($output, [
                        $lead['email'],
                        $lead['name'] ?? '',
                        $lead['phone'] ?? '',
                        $lead['opted_in'] ? 'Sim' : 'Nao',
                        $lead['source'] ?? 'checkout',
                        date('d/m/Y H:i', strtotime($lead['created_at']))
                    ]);
                }
                
                fclose($output);
                exit;
            } catch (PDOException $e) {
                $error = "Erro ao exportar leads.";
            }
        }
    }
}

// Buscar leads
$leads = [];
$abandonedCount = 0;
$recoveredCount = 0;
try {
    $stmt = $pdo->query("
        SELECT id, email, name, phone, cep, city, state, cart_total, checkout_step, 
               source, opted_in, recovered, created_at, updated_at
        FROM leads
        ORDER BY updated_at DESC, created_at DESC
    ");
    $leads = $stmt->fetchAll();
    
    // Contar abandonados e recuperados
    foreach ($leads as $l) {
        if ($l['checkout_step'] !== 'cart' && !$l['recovered']) $abandonedCount++;
        if ($l['recovered']) $recoveredCount++;
    }
} catch (PDOException $e) {
    error_log("Leads fetch error: " . $e->getMessage());
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
                        <h1><i class="fas fa-envelope"></i> <?php echo $page_title; ?></h1>
                        <p><?php echo $page_subtitle; ?></p>
                    </div>
                    <?php if (!empty($leads)): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="export_leads">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Exportar CSV
                        </button>
                    </form>
                    <?php endif; ?>
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

                <!-- KPIs -->
                <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo count($leads); ?></div>
                            <div class="kpi-label">Total de Leads</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo $abandonedCount; ?></div>
                            <div class="kpi-label">Carrinhos Abandonados</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-undo"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo $recoveredCount; ?></div>
                            <div class="kpi-label">Recuperados</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: rgba(199, 163, 51, 0.1); color: #c7a333;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="kpi-content">
                            <?php
                            $totalAbandoned = array_sum(array_column(array_filter($leads, fn($l) => !$l['recovered']), 'cart_total'));
                            ?>
                            <div class="kpi-value">R$ <?php echo number_format($totalAbandoned, 0, ',', '.'); ?></div>
                            <div class="kpi-label">Valor Abandonado</div>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="card-header">
                        <h3>Lista de Leads</h3>
                        <span class="card-badge"><?php echo count($leads); ?> leads</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leads)): ?>
                            <div class="empty-state">
                                <i class="fas fa-envelope-open"></i>
                                <p>Nenhum lead cadastrado ainda.</p>
                                <small>Os leads aparecerao aqui quando clientes marcarem a opcao de receber ofertas no checkout.</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Lead</th>
                                            <th>Localizacao</th>
                                            <th>Etapa</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $lead): ?>
                                            <tr class="<?php echo (!$lead['recovered'] && $lead['checkout_step'] !== 'cart') ? 'abandoned-row' : ''; ?>">
                                                <td>
                                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                                        <strong style="color: var(--admin-accent);"><?php echo htmlspecialchars($lead['email']); ?></strong>
                                                        <?php if (!empty($lead['name'])): ?>
                                                            <span style="color: var(--admin-text-primary);"><?php echo htmlspecialchars($lead['name']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($lead['phone'])): ?>
                                                            <small style="color: var(--admin-text-muted);"><?php echo htmlspecialchars($lead['phone']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($lead['city'])): ?>
                                                        <span><?php echo htmlspecialchars($lead['city']); ?>/<?php echo htmlspecialchars($lead['state']); ?></span>
                                                        <?php if (!empty($lead['cep'])): ?>
                                                            <br><small style="color: var(--admin-text-muted);">CEP: <?php echo htmlspecialchars($lead['cep']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: var(--admin-text-muted);">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $stepLabels = [
                                                        'cart' => ['label' => 'Carrinho', 'color' => '#6b7280'],
                                                        'delivery' => ['label' => 'Entrega', 'color' => '#f59e0b'],
                                                        'payment' => ['label' => 'Pagamento', 'color' => '#ef4444']
                                                    ];
                                                    $step = $stepLabels[$lead['checkout_step']] ?? $stepLabels['cart'];
                                                    ?>
                                                    <span class="badge" style="background: <?php echo $step['color']; ?>20; color: <?php echo $step['color']; ?>; border: 1px solid <?php echo $step['color']; ?>40;">
                                                        <?php echo $step['label']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($lead['cart_total'] > 0): ?>
                                                        <strong style="color: var(--admin-text-primary);">R$ <?php echo number_format($lead['cart_total'], 2, ',', '.'); ?></strong>
                                                    <?php else: ?>
                                                        <span style="color: var(--admin-text-muted);">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($lead['recovered']): ?>
                                                        <span class="badge badge-success"><i class="fas fa-check"></i> Recuperado</span>
                                                    <?php elseif ($lead['checkout_step'] !== 'cart'): ?>
                                                        <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">Abandonado</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Novo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span><?php echo date('d/m/Y', strtotime($lead['created_at'])); ?></span>
                                                    <br><small style="color: var(--admin-text-muted);"><?php echo date('H:i', strtotime($lead['updated_at'] ?? $lead['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 4px;">
                                                        <?php if (!empty($lead['phone'])): ?>
                                                        <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $lead['phone']); ?>?text=Oi%20<?php echo urlencode($lead['name'] ?? ''); ?>%2C%20vi%20que%20voce%20deixou%20alguns%20itens%20no%20carrinho..." 
target="_blank" class="btn-icon-sm" style="background: #25d366;" title="WhatsApp">
                                                            <i class="fab fa-whatsapp" style="color: #000 !important;"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir este lead?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                            <input type="hidden" name="action" value="delete_lead">
                                                            <input type="hidden" name="id" value="<?php echo $lead['id']; ?>">
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

    <style>
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        background: var(--admin-bg-secondary);
        border-radius: 12px;
        border: 1px solid var(--admin-border);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--admin-text-muted);
        margin-bottom: 1rem;
    }
    
    .empty-state p {
        font-size: 1.125rem;
        color: var(--admin-text-primary);
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    
    .empty-state small {
        color: var(--admin-text-muted);
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
        background: var(--admin-bg-secondary);
    }
    
    .admin-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--admin-border);
        color: var(--admin-text-primary);
    }
    
    .admin-table tbody tr:hover {
        background: var(--admin-bg-hover);
    }
    
    .badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-success {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
    }
    
    .badge-danger {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    
    .badge-secondary {
        background: var(--admin-bg-secondary);
        color: var(--admin-text-secondary);
        border: 1px solid var(--admin-border);
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
    
    .btn-icon-sm.btn-danger:hover {
        background: var(--admin-error);
        color: #fff;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid var(--admin-border);
    }
    
    .abandoned-row {
        background: linear-gradient(90deg, rgba(239, 68, 68, 0.08), transparent) !important;
        border-left: 3px solid #ef4444;
    }
    
    .abandoned-row:hover {
        background: linear-gradient(90deg, rgba(239, 68, 68, 0.12), rgba(255,255,255,0.02)) !important;
    }
    
    @media (max-width: 768px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    </style>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
