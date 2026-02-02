<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Politica de Privacidade';
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
    <h1>Politica de Privacidade</h1>
    
    <p>O <?php echo htmlspecialchars(getSetting($pdo, 'app_name', 'nossa loja')); ?> esta comprometido em proteger sua privacidade. Esta politica descreve como coletamos, usamos e protegemos suas informacoes pessoais.</p>
    
    <h2>1. Informacoes Coletadas</h2>
    <p>Coletamos as seguintes informacoes quando voce realiza uma compra:</p>
    <ul>
        <li>Nome completo</li>
        <li>E-mail</li>
        <li>Telefone</li>
        <li>CPF/CNPJ (para emissao de nota fiscal)</li>
        <li>Endereco de entrega</li>
    </ul>
    
    <h2>2. Uso das Informacoes</h2>
    <p>Suas informacoes sao utilizadas para:</p>
    <ul>
        <li>Processar e entregar seus pedidos</li>
        <li>Emitir notas fiscais</li>
        <li>Entrar em contato sobre seu pedido</li>
        <li>Melhorar nossos servicos</li>
    </ul>
    
    <h2>3. Compartilhamento de Dados</h2>
    <p>Nao vendemos ou compartilhamos suas informacoes pessoais com terceiros, exceto:</p>
    <ul>
        <li>Transportadoras para entrega dos produtos</li>
        <li>Processadores de pagamento (Mercado Pago)</li>
        <li>Quando exigido por lei</li>
    </ul>
    
    <h2>4. Seguranca</h2>
    <p>Utilizamos medidas de seguranca como criptografia SSL para proteger suas informacoes durante a transmissao. Seus dados de pagamento sao processados diretamente pelo Mercado Pago e nao sao armazenados em nossos servidores.</p>
    
    <h2>5. Cookies</h2>
    <p>Utilizamos cookies para melhorar sua experiencia de navegacao e manter sua sessao ativa durante as compras.</p>
    
    <h2>6. Seus Direitos</h2>
    <p>Voce tem direito a:</p>
    <ul>
        <li>Acessar seus dados pessoais</li>
        <li>Corrigir dados incorretos</li>
        <li>Solicitar exclusao de seus dados</li>
        <li>Revogar consentimento</li>
    </ul>
    
    <h2>7. Contato</h2>
    <p>Para exercer seus direitos ou tirar duvidas sobre privacidade, entre em contato conosco atraves do WhatsApp disponivel no site.</p>
    
    <p style="margin-top: 2rem; font-size: 0.9rem; color: #888;">Ultima atualizacao: <?php echo date('d/m/Y'); ?></p>
</div>

<?php if ($fromCheckout): ?>
<div class="checkout-reminder">
    <p><i class="fas fa-shopping-cart"></i> Ei! Volte ao checkout para finalizar sua compra</p>
    <a href="<?php echo APP_URL; ?>/checkout-entrega.php"><i class="fas fa-arrow-left"></i> Voltar ao Checkout</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
