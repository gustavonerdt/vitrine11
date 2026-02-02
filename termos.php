<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Termos de Uso';
$bodyClass = 'legal-page';
$fromCheckout = isset($_GET['checkout']) && $_GET['checkout'] == '1';

include __DIR__ . '/includes/public-header.php';
?>

<style>
.legal-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
}

.legal-container h1 {
    color: #1a1a1a;
    font-size: 2rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #C7A333;
}

.legal-container h2 {
    color: #333;
    font-size: 1.25rem;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.legal-container p {
    color: #555;
    line-height: 1.8;
    margin-bottom: 1rem;
}

.legal-container ul {
    margin-left: 1.5rem;
    margin-bottom: 1rem;
}

.legal-container li {
    color: #555;
    line-height: 1.8;
    margin-bottom: 0.5rem;
}

.checkout-reminder {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #C7A333 0%, #D4B84A 100%);
    color: #000;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 9999;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
}

.checkout-reminder p {
    color: #000;
    font-weight: 600;
    margin: 0;
    font-size: 1rem;
}

.checkout-reminder a {
    background: #000;
    color: #C7A333;
    padding: 0.75rem 1.5rem;
    border-radius: 24px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
}

.checkout-reminder a:hover {
    background: #222;
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .legal-container {
        margin: 1rem;
        padding: 1.5rem;
    }
    
    .checkout-reminder {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}
</style>

<div class="legal-container">
    <h1>Termos de Uso</h1>
    
    <p>Bem-vindo ao <?php echo htmlspecialchars(getSetting($pdo, 'app_name', 'nossa loja')); ?>. Ao utilizar nosso site e realizar compras, voce concorda com os seguintes termos:</p>
    
    <h2>1. Aceitacao dos Termos</h2>
    <p>Ao acessar e usar este site, voce aceita e concorda em cumprir estes termos de uso. Se voce nao concordar com qualquer parte destes termos, nao devera usar nosso site.</p>
    
    <h2>2. Produtos e Precos</h2>
    <p>Todos os precos exibidos estao em Reais (R$) e podem ser alterados sem aviso previo. Nos reservamos o direito de limitar quantidades ou recusar pedidos a nosso exclusivo criterio.</p>
    
    <h2>3. Pagamentos</h2>
    <ul>
        <li>Aceitamos pagamentos via PIX, Cartao de Credito e Boleto Bancario</li>
        <li>Os pagamentos sao processados de forma segura atraves do Mercado Pago</li>
        <li>Pedidos pagos via PIX sao confirmados instantaneamente</li>
        <li>Pedidos pagos via Boleto sao confirmados em ate 3 dias uteis</li>
    </ul>
    
    <h2>4. Entrega</h2>
    <ul>
        <li>Os prazos de entrega sao estimados e podem variar conforme a regiao</li>
        <li>O frete e calculado com base no CEP de destino</li>
        <li>Nao nos responsabilizamos por atrasos causados pelos Correios ou transportadoras</li>
    </ul>
    
    <h2>5. Trocas e Devolucoes</h2>
    <p>Voce tem direito a trocar ou devolver produtos em ate 7 dias apos o recebimento, conforme o Codigo de Defesa do Consumidor. O produto deve estar lacrado e em perfeito estado.</p>
    
    <h2>6. Contato</h2>
    <p>Para duvidas ou suporte, entre em contato conosco atraves do WhatsApp disponivel no site.</p>
    
    <p style="margin-top: 2rem; font-size: 0.9rem; color: #888;">Ultima atualizacao: <?php echo date('d/m/Y'); ?></p>
</div>

<?php if ($fromCheckout): ?>
<div class="checkout-reminder">
    <p><i class="fas fa-shopping-cart"></i> Ei! Volte ao checkout para finalizar sua compra</p>
    <a href="<?php echo APP_URL; ?>/checkout-entrega.php"><i class="fas fa-arrow-left"></i> Voltar ao Checkout</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
