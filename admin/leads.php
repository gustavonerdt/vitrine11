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

// Buscar leads
$leads = [];
try {
    if (db_table_exists($pdo, 'leads')) {
        $stmt = $pdo->query("
            SELECT id, email, opted_in, source, created_at
            FROM leads
            ORDER BY created_at DESC
        ");
        $leads = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Leads fetch error: " . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<main class="admin-main">
    <div class="admin-content">
        <div class="page-header">
            <h1><?php echo $page_title; ?></h1>
            <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
        </div>

        <div class="admin-card">
            <div class="card-header">
                <h2>Lista de Leads</h2>
                <span class="card-badge"><?php echo count($leads); ?> leads</span>
            </div>
            <div class="card-body">
                <?php if (empty($leads)): ?>
                    <div class="empty-state" style="text-align: center; padding: 3rem 2rem; background: var(--admin-bg-secondary); border-radius: 12px; border: 1px solid var(--admin-border);">
                        <i class="fas fa-envelope-open" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 1rem;"></i>
                        <p style="font-size: 1.125rem; color: var(--admin-text-primary); margin-bottom: 0.5rem; font-weight: 600;">Nenhum lead cadastrado ainda.</p>
                        <small style="color: var(--admin-text-muted);">Os leads aparecerão aqui quando clientes marcarem a opção de receber ofertas no checkout.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--admin-border);">
                        <table class="admin-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--admin-bg-secondary); border-bottom: 2px solid var(--admin-border);">
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-primary); font-weight: 700; text-transform: uppercase; font-size: 0.875rem; letter-spacing: 0.05em;">E-mail</th>
                                    <th style="padding: 1rem; text-align: center; color: var(--admin-text-primary); font-weight: 700; text-transform: uppercase; font-size: 0.875rem; letter-spacing: 0.05em;">Deixou?</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-primary); font-weight: 700; text-transform: uppercase; font-size: 0.875rem; letter-spacing: 0.05em;">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leads as $lead): ?>
                                    <tr style="border-bottom: 1px solid var(--admin-border); transition: background 0.2s;">
                                        <td style="padding: 1rem; color: var(--admin-text-primary);">
                                            <strong><?php echo htmlspecialchars($lead['email']); ?></strong>
                                        </td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <?php if ($lead['opted_in'] == 1): ?>
                                                <span class="badge badge-success" style="background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; border: 1px solid rgba(34, 197, 94, 0.3); display: inline-block;">Sim</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.3); display: inline-block;">Não</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; color: var(--admin-text-secondary);">
                                            <?php echo date('d/m/Y H:i', strtotime($lead['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <style>
                .admin-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: var(--admin-bg-card);
                    border-radius: 8px;
                    overflow: hidden;
                }
                
                .admin-table thead {
                    background: var(--admin-bg-secondary);
                }
                
                .admin-table thead th {
                    padding: 1rem;
                    text-align: left;
                    color: var(--admin-text-primary);
                    font-weight: 700;
                    text-transform: uppercase;
                    font-size: 0.875rem;
                    letter-spacing: 0.05em;
                    border-bottom: 2px solid var(--admin-border);
                }
                
                .admin-table tbody tr {
                    border-bottom: 1px solid var(--admin-border);
                    transition: background 0.2s;
                }
                
                .admin-table tbody tr:hover {
                    background: var(--admin-bg-hover) !important;
                }
                
                .admin-table tbody td {
                    padding: 1rem;
                    color: var(--admin-text-primary);
                }
                
                @media (max-width: 768px) {
                    .admin-table {
                        font-size: 0.875rem;
                    }
                    
                    .admin-table th,
                    .admin-table td {
                        padding: 0.75rem 0.5rem !important;
                    }
                }
                </style>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
