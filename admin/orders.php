<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Pedidos';
$page_subtitle = 'Gerenciar todos os pedidos realizados';

// Marcar pedido como visto
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $order_id = (int)$_GET['view'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET viewed_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);
        header('Location: ' . APP_URL . '/admin/orders.php');
        exit;
    } catch (PDOException $e) {
        error_log("Order view update error: " . $e->getMessage());
    }
}

// Buscar pedidos
$orders = [];
try {
    if (db_table_exists($pdo, 'orders')) {
        $stmt = $pdo->query("
            SELECT o.*, 
                   COUNT(oi.id) as items_count,
                   GROUP_CONCAT(p.name SEPARATOR ', ') as products_names
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $orders = $stmt->fetchAll();
        
        // Buscar detalhes de cada pedido
        foreach ($orders as &$order) {
            // Buscar endereço
            $addrStmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE order_id = ? LIMIT 1");
            $addrStmt->execute([$order['id']]);
            $order['address'] = $addrStmt->fetch();
            
            // Buscar metadata
            if (db_table_exists($pdo, 'order_metadata')) {
                $metaStmt = $pdo->prepare("SELECT * FROM order_metadata WHERE order_id = ? LIMIT 1");
                $metaStmt->execute([$order['id']]);
                $order['metadata'] = $metaStmt->fetch();
            }
        }
        unset($order);
    }
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage());
}

$viewing_order_id = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
$viewing_order = null;

if ($viewing_order_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$viewing_order_id]);
        $viewing_order = $stmt->fetch();
        
        if ($viewing_order) {
            // Buscar itens
            $itemsStmt = $pdo->prepare("
                SELECT oi.*, p.name as product_name, p.image_path
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemsStmt->execute([$viewing_order_id]);
            $viewing_order['items'] = $itemsStmt->fetchAll();
            
            // Buscar endereço
            $addrStmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE order_id = ? LIMIT 1");
            $addrStmt->execute([$viewing_order_id]);
            $viewing_order['address'] = $addrStmt->fetch();
            
            // Buscar metadata
            if (db_table_exists($pdo, 'order_metadata')) {
                $metaStmt = $pdo->prepare("SELECT * FROM order_metadata WHERE order_id = ? LIMIT 1");
                $metaStmt->execute([$viewing_order_id]);
                $viewing_order['metadata'] = $metaStmt->fetch();
            }
            
            // Marcar como visto
            $viewStmt = $pdo->prepare("UPDATE orders SET viewed_at = NOW() WHERE id = ?");
            $viewStmt->execute([$viewing_order_id]);
        }
    } catch (PDOException $e) {
        error_log("Order detail fetch error: " . $e->getMessage());
    }
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

        <?php if ($viewing_order): ?>
            <!-- Detalhes do Pedido -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>Detalhes do Pedido #<?php echo $viewing_order['id']; ?></h2>
                    <a href="<?php echo APP_URL; ?>/admin/orders.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <div class="order-details-grid">
                        <div class="detail-section">
                            <h3>Informações do Pedido</h3>
                            <div class="detail-item">
                                <span class="detail-label">Número:</span>
                                <span class="detail-value">#<?php echo $viewing_order['id']; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value">
                                    <?php
                                    $status_labels = [
                                        'pending' => 'Pendente',
                                        'paid' => 'Pago',
                                        'processing' => 'Processando',
                                        'shipped' => 'Enviado',
                                        'delivered' => 'Entregue',
                                        'cancelled' => 'Cancelado'
                                    ];
                                    $status = $viewing_order['status'];
                                    $status_class = $status === 'paid' ? 'badge-success' : ($status === 'pending' ? 'badge-warning' : 'badge-danger');
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_labels[$status] ?? $status; ?></span>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Data:</span>
                                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($viewing_order['created_at'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Valor Total:</span>
                                <span class="detail-value"><?php echo formatPrice($viewing_order['total_amount']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Frete:</span>
                                <span class="detail-value"><?php echo formatPrice($viewing_order['shipping_cost']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Método de Pagamento:</span>
                                <span class="detail-value"><?php echo strtoupper($viewing_order['payment_method'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <?php if ($viewing_order['address']): ?>
                            <div class="detail-section">
                                <h3>Endereço de Entrega</h3>
                                <div class="address-block">
                                    <p><strong><?php echo htmlspecialchars($viewing_order['address']['recipient_name']); ?></strong></p>
                                    <p>
                                        <?php 
                                        $address_parts = array_filter([
                                            $viewing_order['address']['street'],
                                            $viewing_order['address']['number'] ? 'Nº ' . $viewing_order['address']['number'] : '',
                                            $viewing_order['address']['complement'],
                                            $viewing_order['address']['neighborhood'],
                                            $viewing_order['address']['city'] . ' - ' . $viewing_order['address']['state'],
                                            'CEP ' . $viewing_order['address']['cep']
                                        ]);
                                        echo htmlspecialchars(implode(', ', $address_parts));
                                        ?>
                                    </p>
                                    <?php if (!empty($viewing_order['address']['phone'])): ?>
                                        <p><strong>Telefone:</strong> <?php echo htmlspecialchars($viewing_order['address']['phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($viewing_order['address']['email'])): ?>
                                        <p><strong>E-mail:</strong> <?php echo htmlspecialchars($viewing_order['address']['email']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($viewing_order['address']['cpf_cnpj'])): ?>
                                        <p><strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($viewing_order['address']['cpf_cnpj']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($viewing_order['metadata']): ?>
                            <div class="detail-section">
                                <h3>Informações Técnicas</h3>
                                <div class="detail-item">
                                    <span class="detail-label">IP:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($viewing_order['metadata']['ip_address'] ?? 'N/A'); ?></span>
                                </div>
                                <?php if (!empty($viewing_order['metadata']['location_data'])): 
                                    $location = json_decode($viewing_order['metadata']['location_data'], true);
                                    if ($location && $location['status'] === 'success'):
                                ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Localização:</span>
                                        <span class="detail-value">
                                            <?php 
                                            echo htmlspecialchars($location['city'] ?? '') . ', ' . 
                                                 htmlspecialchars($location['regionName'] ?? '') . ' - ' . 
                                                 htmlspecialchars($location['country'] ?? '');
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label">Data/Hora:</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i:s', strtotime($viewing_order['metadata']['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-items-section">
                        <h3>Produtos do Pedido</h3>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Quantidade</th>
                                        <th>Preço Unitário</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($viewing_order['items'] as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name'] ?? 'Produto #' . $item['product_id']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td><?php echo formatPrice($item['price']); ?></td>
                                            <td><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Lista de Pedidos -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>Lista de Pedidos</h2>
                    <span class="card-badge"><?php echo count($orders); ?> pedidos</span>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <p>Nenhum pedido encontrado.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produto(s)</th>
                                        <th>Valor Final</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="<?php echo $order['viewed_at'] ? '' : 'unseen-order'; ?>">
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td>
                                                <?php 
                                                $products = explode(', ', $order['products_names']);
                                                if (count($products) > 2) {
                                                    echo htmlspecialchars($products[0] . ', ' . $products[1] . '...');
                                                } else {
                                                    echo htmlspecialchars($order['products_names'] ?: 'N/A');
                                                }
                                                ?>
                                                <?php if ($order['items_count'] > 1): ?>
                                                    <small style="color: #666;">(+<?php echo $order['items_count'] - 1; ?> mais)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td>
                                                <?php
                                                $status = $order['status'];
                                                $status_class = $status === 'paid' ? 'badge-success' : ($status === 'pending' ? 'badge-warning' : 'badge-danger');
                                                $status_labels = [
                                                    'pending' => 'Pendente',
                                                    'paid' => 'Pago',
                                                    'processing' => 'Processando',
                                                    'shipped' => 'Enviado',
                                                    'delivered' => 'Entregue',
                                                    'cancelled' => 'Cancelado'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_labels[$status] ?? $status; ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo APP_URL; ?>/admin/orders.php?detail=<?php echo $order['id']; ?>" class="btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> Ver Detalhes
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
        <?php endif; ?>
    </div>
</main>

<style>
/* Orders Page Premium Styles */
.admin-content {
    padding: 1.5rem;
    max-width: 100%;
    overflow-x: hidden;
}

.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--admin-border, #2a2a2a);
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.page-subtitle {
    color: #888;
    font-size: 0.9rem;
    margin: 0;
}

/* Unseen orders highlight */
.unseen-order {
    background: linear-gradient(90deg, rgba(212, 175, 55, 0.15), transparent) !important;
    border-left: 4px solid #d4af37 !important;
}

.unseen-order td {
    font-weight: 600;
}

/* Order Details Grid */
.order-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.detail-section {
    background: linear-gradient(145deg, #1a1a1a, #141414);
    border: 1px solid #2a2a2a;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.detail-section h3 {
    margin: 0 0 1.25rem 0;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #d4af37;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-section h3::before {
    content: '';
    width: 8px;
    height: 8px;
    background: #d4af37;
    border-radius: 50%;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    color: #888;
    font-weight: 500;
    font-size: 0.9rem;
}

.detail-value {
    color: #fff;
    font-weight: 600;
    text-align: right;
}

.address-block {
    line-height: 1.8;
    color: #ccc;
}

.address-block p {
    margin: 0.5rem 0;
}

.address-block strong {
    color: #fff;
}

/* Order Items Section */
.order-items-section {
    margin-top: 2rem;
    background: linear-gradient(145deg, #1a1a1a, #141414);
    border: 1px solid #2a2a2a;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.order-items-section h3 {
    margin: 0 0 1.25rem 0;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Admin Card */
.admin-card {
    background: linear-gradient(145deg, #1a1a1a, #141414);
    border: 1px solid #2a2a2a;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    background: rgba(212, 175, 55, 0.05);
    border-bottom: 1px solid #2a2a2a;
}

.card-header h2 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
}

.card-badge {
    background: linear-gradient(135deg, #d4af37, #b8962e);
    color: #000;
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.card-body {
    padding: 1.5rem;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.admin-table thead {
    background: rgba(212, 175, 55, 0.08);
}

.admin-table thead th {
    padding: 1rem 1.25rem;
    text-align: left;
    color: #d4af37;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.1em;
    border-bottom: 2px solid #2a2a2a;
    white-space: nowrap;
}

.admin-table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}

.admin-table tbody tr:hover {
    background: rgba(212, 175, 55, 0.05);
}

.admin-table tbody td {
    padding: 1rem 1.25rem;
    color: #ccc;
    font-size: 0.9rem;
    vertical-align: middle;
}

.admin-table tbody td strong {
    color: #fff;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.badge-success {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, #d4af37, #b8962e);
    color: #000;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
}

.btn-primary.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    border-radius: 8px;
}

.btn-secondary {
    background: transparent;
    color: #d4af37;
    border: 2px solid #d4af37;
    padding: 0.65rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-secondary:hover {
    background: rgba(212, 175, 55, 0.1);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #666;
}

.empty-state i {
    font-size: 4rem;
    color: #333;
    margin-bottom: 1.5rem;
    display: block;
}

.empty-state p {
    font-size: 1.1rem;
    margin: 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .admin-content {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.35rem;
    }
    
    .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .admin-table thead th,
    .admin-table tbody td {
        padding: 0.75rem;
        font-size: 0.8rem;
    }
    
    .order-details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .detail-section {
        padding: 1.25rem;
    }
    
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .detail-value {
        text-align: left;
    }
    
    .btn-primary.btn-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
