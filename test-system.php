<?php
/**
 * Script de Teste do Sistema de Carrinho e Checkout
 * Execute este arquivo para verificar se tudo está funcionando
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

$tests = [];
$errors = [];

// Test 1: Verificar se as funções existem
$tests[] = ['name' => 'Função formatPrice existe', 'result' => function_exists('formatPrice')];
$tests[] = ['name' => 'Função getSetting existe', 'result' => function_exists('getSetting')];
$tests[] = ['name' => 'Função db_table_exists existe', 'result' => function_exists('db_table_exists')];

// Test 2: Verificar conexão com banco
try {
    $stmt = $pdo->query("SELECT 1");
    $tests[] = ['name' => 'Conexão com banco de dados', 'result' => true];
} catch (Exception $e) {
    $tests[] = ['name' => 'Conexão com banco de dados', 'result' => false];
    $errors[] = 'Erro de conexão: ' . $e->getMessage();
}

// Test 3: Verificar se as tabelas existem
$tables = ['products', 'orders', 'order_items', 'shipping_addresses', 'cart_items', 'coupons', 'order_bumps'];
foreach ($tables as $table) {
    $exists = db_table_exists($pdo, $table);
    $tests[] = ['name' => "Tabela $table existe", 'result' => $exists];
    if (!$exists) {
        $errors[] = "Tabela $table não existe no banco de dados";
    }
}

// Test 4: Verificar se há produtos ativos
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $result = $stmt->fetch();
    $productCount = $result['count'] ?? 0;
    $tests[] = ['name' => 'Produtos ativos no banco', 'result' => $productCount > 0, 'data' => "Encontrados: $productCount"];
    if ($productCount == 0) {
        $errors[] = 'Nenhum produto ativo encontrado no banco de dados';
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Produtos ativos no banco', 'result' => false];
    $errors[] = 'Erro ao buscar produtos: ' . $e->getMessage();
}

// Test 5: Verificar sessão
$tests[] = ['name' => 'Sessão PHP funcionando', 'result' => session_status() === PHP_SESSION_ACTIVE];
if (session_status() !== PHP_SESSION_ACTIVE) {
    $errors[] = 'Sessão PHP não está ativa';
}

// Test 6: Verificar constantes
$tests[] = ['name' => 'Constante APP_URL definida', 'result' => defined('APP_URL')];
if (!defined('APP_URL')) {
    $errors[] = 'APP_URL não está definida';
}

// Test 7: Verificar se os arquivos de API existem
$apiFiles = [
    'api/add-to-cart.php',
    'api/update-cart.php',
    'api/get-cart.php',
    'api/calculate-shipping.php',
    'api/create-payment.php'
];
foreach ($apiFiles as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $tests[] = ['name' => "Arquivo $file existe", 'result' => $exists];
    if (!$exists) {
        $errors[] = "Arquivo $file não encontrado";
    }
}

// Test 8: Verificar configurações do admin
$settings = [
    'correios_cep_origem',
    'frete_gratis_valor_minimo',
    'whatsapp_float_enabled',
    'mercado_pago_environment'
];
foreach ($settings as $setting) {
    $value = getSetting($pdo, $setting, null);
    $tests[] = ['name' => "Configuração $setting", 'result' => $value !== null, 'data' => "Valor: " . ($value ?? 'não configurado')];
}

// Test 9: Verificar se o diretório de logs pode ser criado
$logDir = __DIR__ . '/.cursor';
$logFile = $logDir . '/debug.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$canWrite = is_writable($logDir) || @file_put_contents($logFile, 'test') !== false;
$tests[] = ['name' => 'Diretório de logs pode ser escrito', 'result' => $canWrite];
if (!$canWrite) {
    $errors[] = 'Não é possível escrever no diretório .cursor';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste do Sistema</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-result {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-pass {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-fail {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .errors {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .summary {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="summary">
        <h1>Teste do Sistema de Carrinho e Checkout</h1>
        <p><strong>Total de testes:</strong> <?php echo count($tests); ?></p>
        <p><strong>Passou:</strong> <?php echo count(array_filter($tests, fn($t) => $t['result'])); ?></p>
        <p><strong>Falhou:</strong> <?php echo count(array_filter($tests, fn($t) => !$t['result'])); ?></p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <h2>Erros Encontrados:</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Resultados dos Testes:</h2>
    <?php foreach ($tests as $test): ?>
        <div class="test-result <?php echo $test['result'] ? 'test-pass' : 'test-fail'; ?>">
            <span><?php echo htmlspecialchars($test['name']); ?></span>
            <span>
                <?php if (isset($test['data'])): ?>
                    <small><?php echo htmlspecialchars($test['data']); ?></small> - 
                <?php endif; ?>
                <strong><?php echo $test['result'] ? '✓ PASSOU' : '✗ FALHOU'; ?></strong>
            </span>
        </div>
    <?php endforeach; ?>

    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 5px;">
        <h2>Próximos Passos:</h2>
        <ol>
            <li>Se todos os testes passaram, acesse a página inicial e teste adicionar um produto ao carrinho</li>
            <li>Verifique se o badge do carrinho atualiza</li>
            <li>Acesse /carrinho.php e verifique se os produtos aparecem</li>
            <li>Teste o cálculo de frete na página de checkout</li>
        </ol>
    </div>
</body>
</html>
