<?php
/**
 * Dashboard Admin - Painel Completo com Metricas de Vendas
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// ====== ESTATISTICAS GERAIS ======
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'total_brands' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'new_orders' => 0,
    'total_revenue' => 0,
    'revenue_today' => 0,
    'revenue_month' => 0,
    'total_visits' => 0,
    'visits_today' => 0,
    'total_leads' => 0,
    'unread_leads' => 0,
];

try {
    // Produtos
    $stmt = $pdo->query('SELECT COUNT(*) FROM products');
    $stats['total_products'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1');
    $stats['active_products'] = $stmt->fetchColumn();
    
    // Marcas
    $stmt = $pdo->query('SELECT COUNT(*) FROM brands WHERE is_active = 1');
    $stats['total_brands'] = $stmt->fetchColumn();
    
    // Pedidos
    if (db_table_exists($pdo, 'orders')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM orders');
        $stats['total_orders'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'processing', 'approved')");
        $stats['pending_orders'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE viewed_at IS NULL");
        $stats['new_orders'] = $stmt->fetchColumn();
        
        // Receita Total
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('approved', 'paid', 'delivered', 'completed')");
        $stats['total_revenue'] = floatval($stmt->fetchColumn());
        
        // Receita Hoje
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('approved', 'paid', 'delivered', 'completed') AND DATE(created_at) = CURDATE()");
        $stats['revenue_today'] = floatval($stmt->fetchColumn());
        
        // Receita Mes
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('approved', 'paid', 'delivered', 'completed') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        $stats['revenue_month'] = floatval($stmt->fetchColumn());
    }
    
    // Visitas
    if (db_table_exists($pdo, 'page_visits')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM page_visits');
        $stats['total_visits'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM page_visits WHERE DATE(visited_at) = CURDATE()");
        $stats['visits_today'] = $stmt->fetchColumn();
    }
    
    // Leads
    if (db_table_exists($pdo, 'leads')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM leads');
        $stats['total_leads'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM leads WHERE is_read = 0 OR is_read IS NULL");
        $stats['unread_leads'] = $stmt->fetchColumn();
    }
    
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// ====== PEDIDOS RECENTES ======
$recentOrders = [];
try {
    if (db_table_exists($pdo, 'orders')) {
        $stmt = $pdo->query("
            SELECT id, customer_name, customer_email, total, status, payment_method, created_at, viewed_at
            FROM orders 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $recentOrders = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Recent orders error: " . $e->getMessage());
}

// ====== GRAFICO DE VENDAS (Ultimos 30 dias) ======
$salesData = [];
$salesLabels = [];
try {
    if (db_table_exists($pdo, 'orders')) {
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = ? AND status IN ('approved', 'paid', 'delivered', 'completed')");
            $stmt->execute([$date]);
            $salesData[] = floatval($stmt->fetchColumn());
            $salesLabels[] = date('d/m', strtotime($date));
        }
    } else {
        $salesData = array_fill(0, 30, 0);
        for ($i = 29; $i >= 0; $i--) {
            $salesLabels[] = date('d/m', strtotime("-$i days"));
        }
    }
} catch (PDOException $e) {
    $salesData = array_fill(0, 30, 0);
    $salesLabels = [];
    for ($i = 29; $i >= 0; $i--) {
        $salesLabels[] = date('d/m', strtotime("-$i days"));
    }
}

// ====== GRAFICO DE PEDIDOS (Ultimos 30 dias) ======
$ordersData = [];
try {
    if (db_table_exists($pdo, 'orders')) {
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
            $stmt->execute([$date]);
            $ordersData[] = intval($stmt->fetchColumn());
        }
    } else {
        $ordersData = array_fill(0, 30, 0);
    }
} catch (PDOException $e) {
    $ordersData = array_fill(0, 30, 0);
}

// ====== PRODUTOS MAIS VENDIDOS ======
$topProducts = [];
try {
    if (db_table_exists($pdo, 'order_items')) {
        $stmt = $pdo->query("
            SELECT 
                oi.product_id,
                p.name as product_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.price) as total_revenue
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN orders o ON oi.order_id = o.id
            WHERE o.status IN ('approved', 'paid', 'delivered', 'completed')
            GROUP BY oi.product_id, p.name
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        $topProducts = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Top products error: " . $e->getMessage());
}

// ====== STATUS DOS PEDIDOS ======
$ordersByStatus = [];
try {
    if (db_table_exists($pdo, 'orders')) {
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM orders
            GROUP BY status
        ");
        $ordersByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} catch (PDOException $e) {
    error_log("Orders by status error: " . $e->getMessage());
}

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .dashboard-welcome {
            background: linear-gradient(135deg, var(--admin-accent) 0%, var(--admin-accent-hover) 100%);
            border-radius: var(--admin-radius-2xl);
            padding: 32px 40px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--admin-shadow-glow);
        }
        
        .dashboard-welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .dashboard-welcome h1 {
            font-family: 'Sora', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: #000;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-welcome p {
            color: rgba(0, 0, 0, 0.7);
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .revenue-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .revenue-card {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius-xl);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all var(--admin-transition-fast);
        }
        
        .revenue-card:hover {
            border-color: var(--admin-accent);
            box-shadow: var(--admin-shadow-lg);
            transform: translateY(-4px);
        }
        
        .revenue-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .revenue-card-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--admin-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .revenue-card-icon.green {
            background: var(--admin-success-bg);
            color: var(--admin-success);
        }
        
        .revenue-card-icon.blue {
            background: var(--admin-info-bg);
            color: var(--admin-info);
        }
        
        .revenue-card-icon.gold {
            background: var(--admin-accent-light);
            color: var(--admin-accent);
        }
        
        .revenue-card-icon.purple {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .revenue-card-label {
            font-size: 0.85rem;
            color: var(--admin-text-muted);
            font-weight: 500;
        }
        
        .revenue-card-value {
            font-family: 'Sora', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--admin-text-primary);
        }
        
        .revenue-card-change {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .revenue-card-change.positive {
            color: var(--admin-success);
        }
        
        .revenue-card-change.negative {
            color: var(--admin-error);
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-container {
            position: relative;
            height: 320px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th {
            text-align: left;
            padding: 14px 16px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--admin-text-muted);
            background: var(--admin-bg-secondary);
            border-bottom: 1px solid var(--admin-border);
        }
        
        .orders-table td {
            padding: 16px;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--admin-border);
            color: var(--admin-text-secondary);
        }
        
        .orders-table tr:hover {
            background: var(--admin-surface-hover);
        }
        
        .orders-table tr.new-order {
            background: var(--admin-warning-bg);
        }
        
        .order-id {
            font-weight: 700;
            color: var(--admin-text-primary);
        }
        
        .order-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: var(--admin-radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .order-status.pending { background: var(--admin-warning-bg); color: var(--admin-warning); }
        .order-status.processing { background: var(--admin-info-bg); color: var(--admin-info); }
        .order-status.approved, .order-status.paid { background: var(--admin-success-bg); color: var(--admin-success); }
        .order-status.cancelled, .order-status.failed { background: var(--admin-error-bg); color: var(--admin-error); }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .quick-stat-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: var(--admin-bg-secondary);
            border-radius: var(--admin-radius-lg);
        }
        
        .quick-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--admin-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .quick-stat-info h4 {
            font-size: 0.8rem;
            color: var(--admin-text-muted);
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .quick-stat-info span {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--admin-text-primary);
        }
        
        .export-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }
    </style>
</head>
<body class="page-enter">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <!-- Welcome Banner -->
                <div class="dashboard-welcome">
                    <h1>Bem-vindo de volta!</h1>
                    <p>Aqui esta o resumo das suas vendas e metricas de hoje.</p>
                </div>

                <!-- Revenue Cards -->
                <div class="revenue-cards">
                    <div class="revenue-card">
                        <div class="revenue-card-header">
                            <div class="revenue-card-icon green">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <span class="revenue-card-label">Receita Total</span>
                        <span class="revenue-card-value">R$ <?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?></span>
                    </div>
                    
                    <div class="revenue-card">
                        <div class="revenue-card-header">
                            <div class="revenue-card-icon blue">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                        <span class="revenue-card-label">Receita Hoje</span>
                        <span class="revenue-card-value">R$ <?php echo number_format($stats['revenue_today'], 2, ',', '.'); ?></span>
                    </div>
                    
                    <div class="revenue-card">
                        <div class="revenue-card-header">
                            <div class="revenue-card-icon gold">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                        <span class="revenue-card-label">Receita do Mes</span>
                        <span class="revenue-card-value">R$ <?php echo number_format($stats['revenue_month'], 2, ',', '.'); ?></span>
                    </div>
                    
                    <div class="revenue-card">
                        <div class="revenue-card-header">
                            <div class="revenue-card-icon purple">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                        <span class="revenue-card-label">Pedidos Novos</span>
                        <span class="revenue-card-value"><?php echo $stats['new_orders']; ?></span>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <div class="kpi-card kpi-card-blue">
                        <div class="kpi-icon"><i class="fas fa-spray-can"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Produtos Ativos</span>
                            <span class="kpi-value"><?php echo $stats['active_products']; ?></span>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-green">
                        <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Total de Pedidos</span>
                            <span class="kpi-value"><?php echo $stats['total_orders']; ?></span>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-orange">
                        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Pedidos Pendentes</span>
                            <span class="kpi-value"><?php echo $stats['pending_orders']; ?></span>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-purple">
                        <div class="kpi-icon"><i class="fas fa-eye"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Visitas Hoje</span>
                            <span class="kpi-value"><?php echo $stats['visits_today']; ?></span>
                        </div>
                    </div>
                    
                    <div class="kpi-card kpi-card-gold">
                        <div class="kpi-icon"><i class="fas fa-envelope"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Leads Nao Lidos</span>
                            <span class="kpi-value"><?php echo $stats['unread_leads']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Vendas (Ultimos 30 dias)</h3>
                            <div class="export-buttons">
                                <button class="btn-export" onclick="exportChart('sales', 'csv')"><i class="fas fa-file-csv"></i> CSV</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Status dos Pedidos</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-stats">
                                <div class="quick-stat-item">
                                    <div class="quick-stat-icon" style="background: var(--admin-warning-bg); color: var(--admin-warning);">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="quick-stat-info">
                                        <h4>Pendentes</h4>
                                        <span><?php echo $ordersByStatus['pending'] ?? 0; ?></span>
                                    </div>
                                </div>
                                <div class="quick-stat-item">
                                    <div class="quick-stat-icon" style="background: var(--admin-info-bg); color: var(--admin-info);">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div class="quick-stat-info">
                                        <h4>Processando</h4>
                                        <span><?php echo $ordersByStatus['processing'] ?? 0; ?></span>
                                    </div>
                                </div>
                                <div class="quick-stat-item">
                                    <div class="quick-stat-icon" style="background: var(--admin-success-bg); color: var(--admin-success);">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="quick-stat-info">
                                        <h4>Aprovados</h4>
                                        <span><?php echo ($ordersByStatus['approved'] ?? 0) + ($ordersByStatus['paid'] ?? 0); ?></span>
                                    </div>
                                </div>
                                <div class="quick-stat-item">
                                    <div class="quick-stat-icon" style="background: var(--admin-error-bg); color: var(--admin-error);">
                                        <i class="fas fa-times"></i>
                                    </div>
                                    <div class="quick-stat-info">
                                        <h4>Cancelados</h4>
                                        <span><?php echo ($ordersByStatus['cancelled'] ?? 0) + ($ordersByStatus['failed'] ?? 0); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="admin-card">
                    <div class="card-header">
                        <h3><i class="fas fa-shopping-bag"></i> Pedidos Recentes</h3>
                        <div class="export-buttons">
                            <button class="btn-export" onclick="exportTable('orders', 'csv')"><i class="fas fa-file-csv"></i> CSV</button>
                            <button class="btn-export" onclick="exportTable('orders', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            <a href="orders.php" class="btn-secondary btn-sm">Ver Todos</a>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <h3>Nenhum pedido ainda</h3>
                                <p>Quando voce receber pedidos, eles aparecerao aqui.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="orders-table" id="ordersTable">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Pagamento</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <tr class="<?php echo empty($order['viewed_at']) ? 'new-order' : ''; ?>">
                                            <td>
                                                <span class="order-id">#<?php echo $order['id']; ?></span>
                                                <?php if (empty($order['viewed_at'])): ?>
                                                    <span class="badge badge-warning" style="margin-left: 8px;">Novo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></strong>
                                                <br><small style="color: var(--admin-text-muted);"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                            </td>
                                            <td><strong>R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></strong></td>
                                            <td><?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="order-status <?php echo $order['status']; ?>">
                                                    <?php 
                                                    $statusLabels = [
                                                        'pending' => 'Pendente',
                                                        'processing' => 'Processando',
                                                        'approved' => 'Aprovado',
                                                        'paid' => 'Pago',
                                                        'delivered' => 'Entregue',
                                                        'completed' => 'Concluido',
                                                        'cancelled' => 'Cancelado',
                                                        'failed' => 'Falhou',
                                                    ];
                                                    echo $statusLabels[$order['status']] ?? ucfirst($order['status']);
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <a href="orders.php?view=<?php echo $order['id']; ?>" class="btn-ghost" style="padding: 6px 12px;">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($topProducts)): ?>
                <!-- Top Products -->
                <div class="admin-card" style="margin-top: 28px;">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Produtos Mais Vendidos</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Produto</th>
                                        <th>Quantidade Vendida</th>
                                        <th>Receita Gerada</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $index => $product): ?>
                                    <tr>
                                        <td><span class="badge badge-primary"><?php echo $index + 1; ?>o</span></td>
                                        <td><strong><?php echo htmlspecialchars($product['product_name'] ?? 'Produto #' . $product['product_id']); ?></strong></td>
                                        <td><?php echo number_format($product['total_sold']); ?> unidades</td>
                                        <td><strong>R$ <?php echo number_format($product['total_revenue'], 2, ',', '.'); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($salesLabels); ?>,
                datasets: [{
                    label: 'Vendas (R$)',
                    data: <?php echo json_encode($salesData); ?>,
                    borderColor: '#C7A333',
                    backgroundColor: 'rgba(199, 163, 51, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#C7A333',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#18181b',
                        titleColor: '#fff',
                        bodyColor: '#d4d4d8',
                        borderColor: '#27272a',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#71717a', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: 'rgba(113, 113, 122, 0.1)' },
                        ticks: {
                            color: '#71717a',
                            font: { size: 11 },
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Export Functions
    function exportChart(type, format) {
        const data = type === 'sales' ? <?php echo json_encode(array_combine($salesLabels, $salesData)); ?> : {};
        
        if (format === 'csv') {
            let csv = 'Data,Valor\n';
            for (const [date, value] of Object.entries(data)) {
                csv += `${date},${value}\n`;
            }
            downloadFile(csv, `${type}_${new Date().toISOString().split('T')[0]}.csv`, 'text/csv');
        }
    }
    
    function exportTable(type, format) {
        const table = document.getElementById('ordersTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        let data = [];
        
        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('th, td');
            let rowData = [];
            cells.forEach((cell, cellIndex) => {
                if (cellIndex < cells.length - 1) { // Skip actions column
                    rowData.push(cell.textContent.trim().replace(/\n/g, ' '));
                }
            });
            data.push(rowData);
        });
        
        if (format === 'csv') {
            let csv = data.map(row => row.join(',')).join('\n');
            downloadFile(csv, `pedidos_${new Date().toISOString().split('T')[0]}.csv`, 'text/csv');
        } else if (format === 'excel') {
            let csv = data.map(row => row.join('\t')).join('\n');
            downloadFile(csv, `pedidos_${new Date().toISOString().split('T')[0]}.xls`, 'application/vnd.ms-excel');
        }
    }
    
    function downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType + ';charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
