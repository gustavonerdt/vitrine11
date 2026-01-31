<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Estatísticas básicas
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'total_brands' => 0,
    'active_brands' => 0,
    'total_showcases' => 0,
    'active_showcases' => 0,
    'total_visits' => 0,
    'total_clicks' => 0,
    'visits_today' => 0,
    'clicks_today' => 0
];

try {
    // Produtos
    $stmt = $pdo->query('SELECT COUNT(*) FROM products');
    $stats['total_products'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1');
    $stats['active_products'] = $stmt->fetchColumn();
    
    // Marcas
    $stmt = $pdo->query('SELECT COUNT(*) FROM brands');
    $stats['total_brands'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM brands WHERE is_active = 1');
    $stats['active_brands'] = $stmt->fetchColumn();
    
    // Vitrines dinâmicas
    if (db_table_exists($pdo, 'dynamic_showcases')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM dynamic_showcases');
        $stats['total_showcases'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query('SELECT COUNT(*) FROM dynamic_showcases WHERE is_active = 1');
        $stats['active_showcases'] = $stmt->fetchColumn();
    }
    
    // Tracking - Visitas
    if (db_table_exists($pdo, 'page_visits')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM page_visits');
        $stats['total_visits'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM page_visits WHERE DATE(visited_at) = CURDATE()");
        $stats['visits_today'] = $stmt->fetchColumn();
    }
    
    // Tracking - Cliques
    if (db_table_exists($pdo, 'click_tracking')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM click_tracking');
        $stats['total_clicks'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM click_tracking WHERE DATE(clicked_at) = CURDATE()");
        $stats['clicks_today'] = $stmt->fetchColumn();
    }
    
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Dados de tracking - Visitas por tipo
$visits_by_type = [];
try {
    if (db_table_exists($pdo, 'page_visits')) {
        $stmt = $pdo->query("
            SELECT page_type, COUNT(*) as count 
            FROM page_visits 
            GROUP BY page_type
        ");
        $visits_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Visits by type error: " . $e->getMessage());
}

// Produtos mais visualizados
$top_products = [];
try {
    if (db_table_exists($pdo, 'page_visits')) {
        $stmt = $pdo->query("
            SELECT 
                pv.page_id,
                p.name as product_name,
                COUNT(*) as visit_count
            FROM page_visits pv
            LEFT JOIN products p ON pv.page_id = p.id
            WHERE pv.page_type = 'product' AND pv.page_id IS NOT NULL
            GROUP BY pv.page_id, p.name
            ORDER BY visit_count DESC
            LIMIT 10
        ");
        $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Top products error: " . $e->getMessage());
}

// Cliques por tipo
$clicks_by_type = [];
try {
    if (db_table_exists($pdo, 'click_tracking')) {
        $stmt = $pdo->query("
            SELECT click_type, COUNT(*) as count 
            FROM click_tracking 
            GROUP BY click_type
        ");
        $clicks_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Clicks by type error: " . $e->getMessage());
}

// Produtos mais clicados
$top_clicked_products = [];
try {
    if (db_table_exists($pdo, 'click_tracking')) {
        $stmt = $pdo->query("
            SELECT 
                ct.product_id,
                p.name as product_name,
                COUNT(*) as click_count
            FROM click_tracking ct
            LEFT JOIN products p ON ct.product_id = p.id
            WHERE ct.product_id IS NOT NULL
            GROUP BY ct.product_id, p.name
            ORDER BY click_count DESC
            LIMIT 10
        ");
        $top_clicked_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Top clicked products error: " . $e->getMessage());
}

// Gráfico de visitas (últimos 30 dias)
$visitsData = [];
$visitsLabels = [];
try {
    if (db_table_exists($pdo, 'page_visits')) {
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_visits WHERE DATE(visited_at) = ?");
            $stmt->execute([$date]);
            $visitsData[] = (int)$stmt->fetchColumn();
            $visitsLabels[] = date('d/m', strtotime($date));
        }
    } else {
        $visitsData = array_fill(0, 30, 0);
        for ($i = 29; $i >= 0; $i--) {
            $visitsLabels[] = date('d/m', strtotime("-$i days"));
        }
    }
} catch (PDOException $e) {
    $visitsData = array_fill(0, 30, 0);
    $visitsLabels = [];
    for ($i = 29; $i >= 0; $i--) {
        $visitsLabels[] = date('d/m', strtotime("-$i days"));
    }
}

// Gráfico de cliques (últimos 30 dias)
$clicksData = [];
$clicksLabels = [];
try {
    if (db_table_exists($pdo, 'click_tracking')) {
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM click_tracking WHERE DATE(clicked_at) = ?");
            $stmt->execute([$date]);
            $clicksData[] = (int)$stmt->fetchColumn();
            $clicksLabels[] = date('d/m', strtotime($date));
        }
    } else {
        $clicksData = array_fill(0, 30, 0);
        for ($i = 29; $i >= 0; $i--) {
            $clicksLabels[] = date('d/m', strtotime("-$i days"));
        }
    }
} catch (PDOException $e) {
    $clicksData = array_fill(0, 30, 0);
    $clicksLabels = [];
    for ($i = 29; $i >= 0; $i--) {
        $clicksLabels[] = date('d/m', strtotime("-$i days"));
    }
}

$page_title = 'Dashboard';
$page_subtitle = 'Visão geral da vitrine';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/animations.js"></script>
</head>
<body class="page-enter">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <div class="kpi-card kpi-card-blue">
                        <div class="kpi-icon blue" style="background: rgba(255, 255, 255, 0.2);">
                            <i class="fas fa-spray-can"></i>
                        </div>
                        <div class="kpi-content">
                            <span class="kpi-label">Total de Produtos</span>
                            <span class="kpi-value"><?php echo number_format($stats['total_products']); ?></span>
                            <small style="color: rgba(255, 255, 255, 0.8); font-size: 0.75rem;">
                                <?php echo number_format($stats['active_products']); ?> ativos
                            </small>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-green">
                        <div class="kpi-icon green" style="background: rgba(0, 0, 0, 0.2);">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="kpi-content">
                            <span class="kpi-label">Total de Marcas</span>
                            <span class="kpi-value"><?php echo number_format($stats['total_brands']); ?></span>
                            <small style="color: rgba(255, 255, 255, 0.8); font-size: 0.75rem;">
                                <?php echo number_format($stats['active_brands']); ?> ativas
                            </small>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-purple">
                        <div class="kpi-icon purple" style="background: rgba(255, 255, 255, 0.2);">
                            <i class="fas fa-magic"></i>
                        </div>
                        <div class="kpi-content">
                            <span class="kpi-label">Vitrines Dinâmicas</span>
                            <span class="kpi-value"><?php echo number_format($stats['total_showcases']); ?></span>
                            <small style="color: rgba(255, 255, 255, 0.8); font-size: 0.75rem;">
                                <?php echo number_format($stats['active_showcases']); ?> ativas
                            </small>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-gold">
                        <div class="kpi-icon gold" style="background: rgba(0, 0, 0, 0.2);">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="kpi-content">
                            <span class="kpi-label">Total de Visitas</span>
                            <span class="kpi-value"><?php echo number_format($stats['total_visits']); ?></span>
                            <small style="color: rgba(255, 255, 255, 0.8); font-size: 0.75rem;">
                                <?php echo number_format($stats['visits_today']); ?> hoje
                            </small>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-orange">
                        <div class="kpi-icon warning" style="background: rgba(255, 255, 255, 0.2);">
                            <i class="fas fa-mouse-pointer"></i>
                        </div>
                        <div class="kpi-content">
                            <span class="kpi-label">Total de Cliques</span>
                            <span class="kpi-value"><?php echo number_format($stats['total_clicks']); ?></span>
                            <small style="color: rgba(255, 255, 255, 0.8); font-size: 0.75rem;">
                                <?php echo number_format($stats['clicks_today']); ?> hoje
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Tracking Section -->
                <div class="charts-row" style="margin-top: 2rem;">
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-eye"></i> Visitas (Últimos 30 dias)</h3>
                        </div>
                        <div class="card-body" style="position: relative; width: 100%; height: 300px;">
                            <canvas id="visitsChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-mouse-pointer"></i> Cliques (Últimos 30 dias)</h3>
                        </div>
                        <div class="card-body" style="position: relative; width: 100%; height: 300px;">
                            <canvas id="clicksChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Visitas por Tipo -->
                <div class="charts-row" style="margin-top: 2rem;">
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Visitas por Tipo de Página</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($visits_by_type)): ?>
                                <p class="empty-message">Nenhuma visita registrada ainda.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Tipo de Página</th>
                                                <th>Total de Visitas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($visits_by_type as $visit): ?>
                                            <tr>
                                                <td>
                                                    <strong>
                                                        <?php 
                                                        $types = [
                                                            'vitrine' => 'Vitrine',
                                                            'product' => 'Produto',
                                                            'home' => 'Home'
                                                        ];
                                                        echo $types[$visit['page_type']] ?? ucfirst($visit['page_type']);
                                                        ?>
                                                    </strong>
                                                </td>
                                                <td><strong><?php echo number_format($visit['count']); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-mouse-pointer"></i> Cliques por Tipo</h3>
                        </div>
                        <div class="card-body scrollable-container">
                            <?php if (empty($clicks_by_type)): ?>
                                <p class="empty-message">Nenhum clique registrado ainda.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Tipo de Clique</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($clicks_by_type as $click): ?>
                                            <tr>
                                                <td>
                                                    <strong>
                                                        <?php 
                                                        $types = [
                                                            'buy_button' => 'Botão Comprar',
                                                            'whatsapp' => 'WhatsApp',
                                                            'product_view' => 'Visualização',
                                                            'variant_select' => 'Seleção Variante'
                                                        ];
                                                        echo $types[$click['click_type']] ?? ucfirst($click['click_type']);
                                                        ?>
                                                    </strong>
                                                </td>
                                                <td><strong><?php echo number_format($click['count']); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Produtos -->
                <div class="charts-row" style="margin-top: 2rem;">
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-star"></i> Produtos Mais Visualizados</h3>
                        </div>
                        <div class="card-body scrollable-container">
                            <?php if (empty($top_products)): ?>
                                <p class="empty-message">Nenhum produto visualizado ainda.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Visitas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $index => $product): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['product_name'] ?? 'Produto #' . $product['page_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-primary"><?php echo number_format($product['visit_count']); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-fire"></i> Produtos Mais Clicados</h3>
                        </div>
                        <div class="card-body scrollable-container">
                            <?php if (empty($top_clicked_products)): ?>
                                <p class="empty-message">Nenhum clique em produtos ainda.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Cliques</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_clicked_products as $index => $product): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['product_name'] ?? 'Produto #' . $product['product_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-success"><?php echo number_format($product['click_count']); ?></span>
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

                <!-- Quick Actions -->
                <div class="quick-actions-row" style="margin-top: 2rem;">
                    <a href="products.php" class="quick-action-card">
                        <i class="fas fa-spray-can"></i>
                        <div>
                            <strong>Gerenciar</strong>
                            <span>Produtos</span>
                        </div>
                    </a>
                    <a href="brands.php" class="quick-action-card">
                        <i class="fas fa-tags"></i>
                        <div>
                            <strong>Gerenciar</strong>
                            <span>Marcas</span>
                        </div>
                    </a>
                    <a href="dynamic-showcases.php" class="quick-action-card">
                        <i class="fas fa-rocket"></i>
                        <div>
                            <strong>Vitrine</strong>
                            <span>Independente</span>
                        </div>
                    </a>
                    <a href="banners.php" class="quick-action-card">
                        <i class="fas fa-image"></i>
                        <div>
                            <strong>Gerenciar</strong>
                            <span>Banners</span>
                        </div>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js não foi carregado.');
            return;
        }
        
        // Visits Chart
        const visitsCtx = document.getElementById('visitsChart');
        if (visitsCtx) {
            new Chart(visitsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($visitsLabels); ?>,
                    datasets: [{
                        label: 'Visitas',
                        data: <?php echo json_encode($visitsData); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#b3b3b3' },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        },
                        x: {
                            ticks: { color: '#b3b3b3', maxRotation: 45, minRotation: 45 },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // Clicks Chart
        const clicksCtx = document.getElementById('clicksChart');
        if (clicksCtx) {
            new Chart(clicksCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($clicksLabels); ?>,
                    datasets: [{
                        label: 'Cliques',
                        data: <?php echo json_encode($clicksData); ?>,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#b3b3b3' },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        },
                        x: {
                            ticks: { color: '#b3b3b3', maxRotation: 45, minRotation: 45 },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });
    </script>

    <style>
    /* Scroll para containers de tabelas no dashboard */
    .scrollable-container {
        max-height: 400px;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .scrollable-container::-webkit-scrollbar {
        width: 8px;
    }
    
    .scrollable-container::-webkit-scrollbar-track {
        background: var(--admin-bg-secondary, #1F1F1F);
        border-radius: 4px;
    }
    
    .scrollable-container::-webkit-scrollbar-thumb {
        background: var(--admin-accent, #C7A333);
        border-radius: 4px;
    }
    
    .scrollable-container::-webkit-scrollbar-thumb:hover {
        background: rgba(199, 163, 51, 0.8);
    }
    
    .scrollable-container .table-responsive {
        margin: 0;
    }
    
    .scrollable-container .data-table {
        margin: 0;
    }
    
    /* Garantir que o thead fique fixo durante o scroll */
    .scrollable-container .data-table thead {
        position: sticky;
        top: 0;
        background: var(--admin-bg-card, #1F1F1F);
        z-index: 10;
    }
    </style>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>