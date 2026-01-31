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
.unseen-order {
    background: rgba(254, 243, 199, 0.3) !important;
    border-left: 4px solid #f59e0b !important;
    font-weight: 600;
}

.order-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.detail-section {
    background: var(--admin-bg-card);
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    padding: 1.5rem;
}

.detail-section h3 {
    margin-bottom: 1rem;
    color: var(--admin-text-primary);
    font-size: 1.125rem;
    font-weight: 700;
    border-bottom: 2px solid var(--admin-accent);
    padding-bottom: 0.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.detail-label {
    color: var(--admin-text-secondary);
    font-weight: 600;
}

.detail-value {
    color: var(--admin-text-primary);
}

.address-block {
    line-height: 1.8;
}

.order-items-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--admin-border);
}

.order-items-section h3 {
    margin-bottom: 1rem;
}

/* Melhorias visuais para tabela de pedidos */
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
    background: var(--admin-bg-hover);
}

.admin-table tbody td {
    padding: 1rem;
    color: var(--admin-text-primary);
}

.btn-primary.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    background: var(--admin-accent);
    color: #000;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}

.btn-primary.btn-sm:hover {
    background: var(--admin-accent-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
}

@media (max-width: 768px) {
    .admin-table {
        font-size: 0.875rem;
    }
    
    .admin-table th,
    .admin-table td {
        padding: 0.75rem 0.5rem !important;
    }
    
    .order-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
