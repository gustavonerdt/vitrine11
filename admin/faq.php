<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'FAQ e Dúvidas';
$page_subtitle = 'Perguntas frequentes e explicações sobre o sistema';

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ e Dúvidas | <?php echo APP_NAME; ?> Admin</title>
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
                        <h1><i class="fas fa-question-circle"></i> FAQ e Dúvidas</h1>
                        <p>Perguntas frequentes e explicações sobre o sistema</p>
                    </div>
                </div>

                <!-- Feedback Banner -->
                <div class="admin-card" style="background: linear-gradient(135deg, rgba(199, 163, 51, 0.1) 0%, rgba(212, 175, 55, 0.1) 100%); border: 2px solid rgba(199, 163, 51, 0.3); margin-bottom: 2rem;">
                    <div class="card-body" style="text-align: center; padding: 2rem;">
                        <i class="fas fa-rocket" style="font-size: 3rem; color: #C7A333; margin-bottom: 1rem;"></i>
                        <h2 style="color: #C7A333; margin-bottom: 1rem;">Vitrine Independente está em BETA!</h2>
                        <p style="font-size: 1.1rem; line-height: 1.8; margin-bottom: 1.5rem;">
                            Estamos trabalhando para criar o melhor sistema de vitrine para você. 
                            <strong>Sua opinião é muito importante!</strong>
                        </p>
                        <p style="font-size: 1rem; line-height: 1.8; margin-bottom: 1.5rem;">
                            Encontrou um problema? Tem uma sugestão? Quer reportar um bug?<br>
                            <strong>Entre em contato conosco!</strong> Cada feedback nos ajuda a melhorar o sistema.
                        </p>
                        
                    </div>
                </div>

                <!-- FAQ Sections -->
                <div class="faq-sections">
                    <!-- Produtos -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-spray-can"></i> Produtos</h3>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como adicionar um produto?</h4>
                                <p>Vá em <strong>Produtos > Adicionar Produto</strong>. Preencha o nome, descrição, preço, selecione a marca e faça upload das imagens. Clique em "Salvar" quando terminar.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> O que são variantes de produto?</h4>
                                <p>Variantes são diferentes versões do mesmo produto. Por exemplo, um perfume pode ter variantes de 50ml, 100ml e 200ml, cada uma com seu próprio preço e imagem.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como ativar ou desativar um produto?</h4>
                                <p>Na lista de produtos, clique no botão de ações (três pontos) ao lado do produto e selecione "Ativar" ou "Desativar". Produtos desativados não aparecem na vitrine pública.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Posso adicionar várias imagens para um produto?</h4>
                                <p>Sim! Ao criar ou editar um produto, você pode fazer upload de múltiplas imagens. A primeira imagem será a imagem principal exibida na vitrine.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Marcas -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tags"></i> Marcas</h3>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como adicionar uma marca?</h4>
                                <p>Vá em <strong>Marcas > Adicionar Marca</strong>. Preencha o nome e descrição da marca, faça upload do logo e clique em "Salvar".</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Posso importar várias marcas de uma vez?</h4>
                                <p>Sim! Use a opção "Importar em Massa" na página de marcas. Cole os nomes das marcas, um por linha, e clique em "Salvar".</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como ativar ou desativar marcas em massa?</h4>
                                <p>Na lista de marcas, marque as marcas que deseja alterar usando os checkboxes. Depois, use os botões "Ativar Selecionados" ou "Desativar Selecionados" no topo da lista.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Banners -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-image"></i> Banners e Carrossel</h3>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> O que são banners?</h4>
                                <p>Banners são imagens promocionais que aparecem no topo da sua vitrine, acima dos filtros. Você pode adicionar até 4 imagens que formam um carrossel que muda automaticamente a cada 5 segundos.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-power-off"></i> O que significa "Banners Ativos" ou "Banners Desativados"?</h4>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid var(--admin-accent); margin-top: 0.5rem; color: #000;">
                                    <p style="margin-bottom: 1rem; color: #000;"><strong style="color: #000;">Status Ativo (✓ Verde):</strong></p>
                                    <ul style="margin-left: 1.5rem; margin-bottom: 1rem; color: #000;">
                                        <li style="color: #000;">Os banners estão <strong style="color: #000;">visíveis</strong> na vitrine pública</li>
                                        <li style="color: #000;">Os visitantes conseguem ver o carrossel de imagens</li>
                                        <li style="color: #000;">As imagens estão configuradas e funcionando</li>
                                    </ul>
                                    
                                    <p style="margin-bottom: 1rem; color: #000;"><strong style="color: #000;">Status Desativado (✗ Vermelho):</strong></p>
                                    <ul style="margin-left: 1.5rem; color: #000;">
                                        <li style="color: #000;">Os banners estão <strong style="color: #000;">ocultos</strong> na vitrine pública</li>
                                        <li style="color: #000;">Os visitantes <strong style="color: #000;">não veem</strong> o carrossel</li>
                                        <li style="color: #000;">Você ainda pode editar e configurar os banners, mas eles não aparecem para os visitantes</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-toggle-on"></i> Como ativar ou desativar os banners?</h4>
                                <p>Na página de <strong>Banners</strong>, no topo você verá um card roxo com o status atual. Para ativar ou desativar:</p>
                                <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                    <li>Clique no botão <strong>"Ligar"</strong> (se estiver desativado) ou <strong>"Desligar"</strong> (se estiver ativado)</li>
                                    <li>Ou marque/desmarque o checkbox <strong>"Banners Ligados"</strong></li>
                                    <li>Clique em <strong>"Salvar Tudo"</strong> no botão fixo na parte inferior da tela</li>
                                </ol>
                                <p style="margin-top: 0.75rem;"><strong>Dica:</strong> O botão "Salvar Tudo" fica sempre visível na parte inferior da tela para facilitar o salvamento rápido!</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-info-circle"></i> Como verificar se os banners estão ativos?</h4>
                                <p>Existem duas formas de verificar o status dos banners:</p>
                                <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                    <li><strong>No menu lateral:</strong> Ao lado do item "Banners" no menu, você verá um ícone:
                                        <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                            <li><i class="fas fa-check-circle" style="color: #22c55e;"></i> <strong>Verde com check</strong> = Banners Ativos</li>
                                            <li><i class="fas fa-times-circle" style="color: #ef4444;"></i> <strong>Vermelho com X</strong> = Banners Desativados</li>
                                        </ul>
                                    </li>
                                    <li><strong>Na página de Banners:</strong> No topo da página, você verá um card roxo mostrando o status atual com um badge verde (ATIVO) ou vermelho (INATIVO)</li>
                                </ol>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-images"></i> Como adicionar imagens aos banners?</h4>
                                <p>Na página de <strong>Banners</strong>, você pode adicionar até 4 imagens:</p>
                                <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                    <li><strong>Primeira imagem é obrigatória</strong> - você precisa adicionar pelo menos uma imagem</li>
                                    <li><strong>As outras 3 são opcionais</strong> - você pode adicionar mais imagens se quiser</li>
                                    <li>Para cada imagem, você pode:
                                        <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                            <li><strong>Arrastar e soltar</strong> uma imagem do seu computador</li>
                                            <li><strong>Clicar para escolher</strong> um arquivo</li>
                                            <li><strong>Colar um link</strong> de uma imagem que já está na internet</li>
                                        </ul>
                                    </li>
                                    <li>Você também pode escolher um <strong>produto de destino</strong> para cada imagem - quando alguém clicar na imagem, será levado para a página desse produto</li>
                                </ol>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-eye"></i> O que acontece quando os banners estão desativados?</h4>
                                <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 0.5rem; color: #000;">
                                    <p style="margin: 0; color: #000;"><strong style="color: #000;">Importante:</strong> Quando os banners estão desativados, eles <strong style="color: #000;">não aparecem</strong> na vitrine pública, mesmo que você tenha configurado as imagens. Isso é útil quando você quer:</p>
                                    <ul style="margin-left: 1.5rem; margin-top: 0.5rem; color: #000;">
                                        <li style="color: #000;">Preparar novos banners sem que os visitantes vejam</li>
                                        <li style="color: #000;">Desativar temporariamente os banners</li>
                                        <li style="color: #000;">Testar diferentes configurações</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-cog"></i> O que são "Opções Avançadas"?</h4>
                                <p>As opções avançadas permitem configurações extras:</p>
                                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                    <li><strong>Marca Específica:</strong> Se você escolher uma marca, os banners só aparecerão quando o visitante filtrar por essa marca na vitrine</li>
                                    <li><strong>Produto de Destino Padrão:</strong> Um produto que será usado quando alguém clicar em uma imagem que não tenha um produto específico definido</li>
                                </ul>
                                <p style="margin-top: 0.75rem;"><strong>Dica:</strong> Essas opções são opcionais. Se você não configurar nada, os banners aparecerão sempre na vitrine, independente do filtro de marca.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-cog"></i> Configurações</h3>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como mudar as cores do sistema?</h4>
                                <p>Vá em <strong>Configurações > Cores do Sistema</strong>. Use os seletores de cor para escolher as cores desejadas. Após salvar, recarregue a página para ver as mudanças.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como configurar o WhatsApp?</h4>
                                <p>Em <strong>Configurações > WhatsApp</strong>, digite o número no formato: código do país + DDD + número (ex: 5511999999999). Configure também a mensagem padrão que aparece quando alguém clica em "Comprar".</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como mudar o nome do sistema?</h4>
                                <p>Vá em <strong>Configurações > Informações Básicas</strong> e altere o campo "Nome do Sistema". Este nome aparecerá no título das páginas e no menu.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como adicionar o logo?</h4>
                                <p>Em <strong>Configurações > Informações Básicas</strong>, cole o link da imagem do logo no campo "Logo do Sistema". Você pode fazer upload da imagem e colar o link aqui.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard e Tracking -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Dashboard e Estatísticas</h3>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> O que são "Visitas" e "Cliques"?</h4>
                                <p><strong>Visitas:</strong> Número de vezes que alguém acessou uma página (vitrine ou produto).<br>
                                <strong>Cliques:</strong> Número de vezes que alguém clicou em botões como "Comprar" ou links de produtos.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Como vejo quais produtos são mais visualizados?</h4>
                                <p>No Dashboard, role até a seção "Top 10 Produtos Mais Visualizados". Lá você verá os produtos que receberam mais visitas.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-question-circle"></i> Os gráficos mostram dados em tempo real?</h4>
                                <p>Os gráficos mostram dados dos últimos 30 dias. Eles são atualizados automaticamente conforme as visitas e cliques são registrados.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Problemas Comuns -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tools"></i> Problemas Comuns</h3>
                        </div>
                        <div class="card-body">
                            <div class="faq-item">
                                <h4><i class="fas fa-exclamation-triangle"></i> As imagens não aparecem</h4>
                                <p>Verifique se o link da imagem está correto e acessível. Certifique-se de que a imagem está hospedada em um servidor público ou use o sistema de upload do próprio sistema.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-exclamation-triangle"></i> O botão do WhatsApp não funciona</h4>
                                <p>Verifique se o número do WhatsApp está configurado corretamente em <strong>Configurações</strong>. O formato deve ser apenas números: código do país + DDD + número.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-exclamation-triangle"></i> As cores não mudaram após salvar</h4>
                                <p>Após salvar as cores em <strong>Configurações</strong>, recarregue a página (F5 ou Ctrl+R) para ver as mudanças. Algumas cores podem precisar de ajustes para manter boa legibilidade.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4><i class="fas fa-exclamation-triangle"></i> Não consigo fazer login</h4>
                                <p>Verifique se está usando o email e senha corretos. Se esqueceu a senha, entre em contato com o suporte. Certifique-se de que está acessando a URL correta do painel administrativo.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Section -->
                <div class="admin-card" style="margin-top: 2rem; background: linear-gradient(135deg, rgba(199, 163, 51, 0.05) 0%, rgba(212, 175, 55, 0.05) 100%);">
                    <div class="card-body" style="text-align: center; padding: 2rem;">
                        <h3 style="color: #C7A333; margin-bottom: 1rem;">
                            <i class="fas fa-comments"></i> Ajudou? Tem mais dúvidas?
                        </h3>
                        <p style="font-size: 1rem; line-height: 1.8; margin-bottom: 1.5rem;">
                            Se você não encontrou a resposta que procurava ou tem sugestões para melhorar este FAQ, 
                            <strong>entre em contato conosco!</strong>
                        </p>
                        <p style="font-size: 0.95rem; line-height: 1.8; margin-bottom: 1.5rem; color: var(--text-muted);">
                            Lembre-se: <strong>Vitrine Independente está em BETA!</strong><br>
                            Seu feedback é essencial para que possamos criar um sistema cada vez melhor para todos.
                        </p>
                     
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
    .faq-sections {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .faq-item {
        padding: 1.5rem 0;
        border-bottom: 1px solid var(--border-default);
    }
    
    .faq-item:last-child {
        border-bottom: none;
    }
    
    .faq-item h4 {
        color: var(--admin-accent);
        margin-bottom: 0.75rem;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .faq-item h4 i {
        font-size: 0.9rem;
    }
    
    .faq-item p {
        line-height: 1.8;
        color: var(--text-secondary);
        margin: 0;
    }
    
    .faq-item ol,
    .faq-item ul {
        line-height: 1.8;
        color: var(--text-secondary);
        margin: 0.5rem 0;
    }
    
    .faq-item li {
        margin-bottom: 0.5rem;
    }
    
    .faq-item strong {
        color: var(--text-primary);
        font-weight: 600;
    }
    
    .faq-item .status-box {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid var(--admin-accent);
        margin-top: 0.75rem;
        color: #000 !important;
    }
    
    .faq-item .status-box p,
    .faq-item .status-box ul,
    .faq-item .status-box li,
    .faq-item .status-box strong {
        color: #000 !important;
    }
    
    .faq-item .warning-box {
        background: #fff3cd;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid #ffc107;
        margin-top: 0.75rem;
        color: #000 !important;
    }
    
    .faq-item .warning-box p,
    .faq-item .warning-box ul,
    .faq-item .warning-box li,
    .faq-item .warning-box strong {
        color: #000 !important;
    }
    
    /* Estilos para elementos com background branco/claro */
    [style*="background: #f8f9fa"],
    [style*="background:#f8f9fa"],
    [style*="background: #fff3cd"],
    [style*="background:#fff3cd"],
    [style*="background: white"],
    [style*="background:white"],
    [style*="background: #fff"],
    [style*="background:#fff"],
    [style*="background: #ffffff"],
    [style*="background:#ffffff"] {
        color: #000 !important;
    }
    
    [style*="background: #f8f9fa"] p,
    [style*="background:#f8f9fa"] p,
    [style*="background: #f8f9fa"] ul,
    [style*="background:#f8f9fa"] ul,
    [style*="background: #f8f9fa"] li,
    [style*="background:#f8f9fa"] li,
    [style*="background: #f8f9fa"] strong,
    [style*="background:#f8f9fa"] strong,
    [style*="background: #fff3cd"] p,
    [style*="background:#fff3cd"] p,
    [style*="background: #fff3cd"] ul,
    [style*="background:#fff3cd"] ul,
    [style*="background: #fff3cd"] li,
    [style*="background:#fff3cd"] li,
    [style*="background: #fff3cd"] strong,
    [style*="background:#fff3cd"] strong {
        color: #000 !important;
    }
    
    @media (max-width: 768px) {
        .faq-item {
            padding: 1rem 0;
        }
        
        .faq-item h4 {
            font-size: 1rem;
        }
    }
    </style>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
