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

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../includes/dynamic-colors.php'; ?>
</head>
<body class="page-enter">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>
            
            <main class="admin-main" style="margin-left: 0;">
    <div class="admin-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1><?php echo $page_title; ?></h1>
                <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
            </div>
            <div class="export-buttons" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button onclick="exportData('csv')" class="btn-export" style="background: #22c55e; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button onclick="exportData('excel')" class="btn-export" style="background: #217346; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button onclick="exportData('txt')" class="btn-export" style="background: #6b7280; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">
                    <i class="fas fa-file-alt"></i> TXT
                </button>
            </div>
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



            <?php include __DIR__ . '/includes/footer.php'; ?>
        </div>
    </div>
<script>
// Dados dos pedidos para exportacao
const ordersData = <?php echo json_encode(array_map(function($o) {
    return [
        'id' => $o['id'],
        'status' => $o['status'],
        'total' => $o['total_amount'],
        'frete' => $o['shipping_cost'],
        'metodo_pagamento' => $o['payment_method'] ?? '',
        'cliente' => $o['address']['recipient_name'] ?? '',
        'email' => $o['customer_email'] ?? '',
        'telefone' => $o['address']['phone'] ?? '',
        'cidade' => ($o['address']['city'] ?? '') . ' - ' . ($o['address']['state'] ?? ''),
        'data' => date('d/m/Y H:i', strtotime($o['created_at']))
    ];
}, $orders)); ?>;

function exportData(format) {
    if (ordersData.length === 0) {
        alert('Nenhum pedido para exportar');
        return;
    }
    
    const headers = ['ID', 'Status', 'Total', 'Frete', 'Pagamento', 'Cliente', 'Email', 'Telefone', 'Cidade', 'Data'];
    const keys = ['id', 'status', 'total', 'frete', 'metodo_pagamento', 'cliente', 'email', 'telefone', 'cidade', 'data'];
    
    let content = '';
    let filename = 'pedidos_' + new Date().toISOString().slice(0,10);
    let mimeType = '';
    
    if (format === 'csv') {
        content = headers.join(';') + '\n';
        ordersData.forEach(row => {
            content += keys.map(k => '"' + (row[k] || '').toString().replace(/"/g, '""') + '"').join(';') + '\n';
        });
        filename += '.csv';
        mimeType = 'text/csv;charset=utf-8;';
    } else if (format === 'excel') {
        content = '<html><head><meta charset="UTF-8"></head><body><table border="1">';
        content += '<tr>' + headers.map(h => '<th>' + h + '</th>').join('') + '</tr>';
        ordersData.forEach(row => {
            content += '<tr>' + keys.map(k => '<td>' + (row[k] || '') + '</td>').join('') + '</tr>';
        });
        content += '</table></body></html>';
        filename += '.xls';
        mimeType = 'application/vnd.ms-excel;charset=utf-8;';
    } else if (format === 'txt') {
        content = headers.join('\t') + '\n';
        ordersData.forEach(row => {
            content += keys.map(k => row[k] || '').join('\t') + '\n';
        });
        filename += '.txt';
        mimeType = 'text/plain;charset=utf-8;';
    }
    
    const blob = new Blob(['\ufeff' + content], { type: mimeType });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}
</script>
</body>
</html>
