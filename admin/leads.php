<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Leads';
$page_subtitle = 'E-mails de clientes que optaram por receber ofertas';

$success = '';
$error = '';
$csrf = generateCsrfToken();

// Criar tabela se nao existir
if (!db_table_exists($pdo, 'leads')) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `leads` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) DEFAULT NULL,
                `phone` VARCHAR(50) DEFAULT NULL,
                `opted_in` TINYINT(1) DEFAULT 1,
                `source` VARCHAR(100) DEFAULT 'checkout',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_email` (`email`),
                KEY `idx_opted_in` (`opted_in`)
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
try {
    $stmt = $pdo->query("
        SELECT id, email, name, phone, opted_in, source, created_at
        FROM leads
        ORDER BY created_at DESC
    ");
    $leads = $stmt->fetchAll();
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
                <div class="kpi-grid">
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
                        <div class="kpi-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo count(array_filter($leads, fn($l) => $l['opted_in'] == 1)); ?></div>
                            <div class="kpi-label">Optaram Receber</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: rgba(199, 163, 51, 0.1); color: #c7a333;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="kpi-content">
                            <?php
                            $today = date('Y-m-d');
                            $leadsToday = count(array_filter($leads, fn($l) => date('Y-m-d', strtotime($l['created_at'])) === $today));
                            ?>
                            <div class="kpi-value"><?php echo $leadsToday; ?></div>
                            <div class="kpi-label">Novos Hoje</div>
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
                                            <th>E-mail</th>
                                            <th>Nome</th>
                                            <th>Optou In</th>
                                            <th>Origem</th>
                                            <th>Data</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $lead): ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: var(--admin-accent);"><?php echo htmlspecialchars($lead['email']); ?></strong>
                                                    <?php if (!empty($lead['phone'])): ?>
                                                        <br><small style="color: var(--admin-text-muted);"><?php echo htmlspecialchars($lead['phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($lead['name'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($lead['opted_in'] == 1): ?>
                                                        <span class="badge badge-success">Sim</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Nao</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($lead['source'] ?? 'checkout'); ?></span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($lead['created_at'])); ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este lead?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                        <input type="hidden" name="action" value="delete_lead">
                                                        <input type="hidden" name="id" value="<?php echo $lead['id']; ?>">
                                                        <button type="submit" class="btn-icon-sm btn-danger" title="Excluir">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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
    </style>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
