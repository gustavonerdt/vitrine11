<?php
// banners-upsell.php - Tela de upsell para funcionalidade de banners
$checkoutLink = defined('BANNERS_CHECKOUT_LINK') ? BANNERS_CHECKOUT_LINK : '#';
$featureEnabled = defined('FEATURE_BANNERS_ENABLED') ? FEATURE_BANNERS_ENABLED : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionalidade de Banners | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upsell-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .upsell-card {
            background: var(--admin-bg-card);
            border: 2px solid var(--admin-accent);
            border-radius: 16px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .upsell-icon {
            font-size: 5rem;
            color: var(--admin-accent);
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .upsell-card h1 {
            font-size: 2.5rem;
            color: var(--admin-text-primary);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .upsell-card h2 {
            font-size: 1.5rem;
            color: var(--admin-accent);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .upsell-card p {
            font-size: 1.1rem;
            color: var(--admin-text-secondary);
            line-height: 1.8;
            margin-bottom: 20px;
        }
        
        .features-list {
            text-align: left;
            max-width: 600px;
            margin: 30px auto;
            background: var(--admin-bg-primary);
            border-radius: 12px;
            padding: 30px;
        }
        
        .features-list h3 {
            color: var(--admin-text-primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
        }
        
        .features-list ul {
            list-style: none;
            padding: 0;
        }
        
        .features-list li {
            padding: 15px 0;
            border-bottom: 1px solid var(--admin-border);
            color: var(--admin-text-primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .features-list li:last-child {
            border-bottom: none;
        }
        
        .features-list li i {
            color: var(--admin-accent);
            font-size: 1.5rem;
            min-width: 30px;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, var(--admin-accent), #D4B84A);
            color: #000;
            padding: 18px 50px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
        }
        
        .cta-button i {
            margin-right: 10px;
        }
        
        .info-box {
            background: rgba(212, 175, 55, 0.1);
            border-left: 4px solid var(--admin-accent);
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: left;
        }
        
        .info-box p {
            margin: 0;
            color: var(--admin-text-primary);
            font-size: 0.95rem;
        }
        
        .info-box strong {
            color: var(--admin-accent);
        }
        
        @media (max-width: 768px) {
            .upsell-card {
                padding: 30px 20px;
            }
            
            .upsell-card h1 {
                font-size: 2rem;
            }
            
            .upsell-icon {
                font-size: 4rem;
            }
        }
    </style>
</head>
<body class="page-enter">
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <div class="upsell-container">
                    <div class="upsell-card">
                        <div class="upsell-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        
                        <h1>Funcionalidade de Banners</h1>
                        <h2>Adquira esta funcionalidade premium</h2>
                        
                        <p>
                            A funcionalidade de <strong>Banners e Carrossel</strong> permite que você crie banners personalizados 
                            com até 4 imagens que aparecem na vitrine de perfumes, acima dos filtros.
                        </p>
                        
                        <div class="features-list">
                            <h3><i class="fas fa-star"></i> O que você recebe:</h3>
                            <ul>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><strong>Carrossel de Imagens:</strong> Até 4 imagens que alternam automaticamente</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><strong>Upload de Imagens:</strong> Faça upload arrastando e soltando ou cole links</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><strong>Associação com Produtos:</strong> Cada imagem pode redirecionar para um produto específico</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><strong>Filtro por Marca:</strong> Configure banners que aparecem apenas para marcas específicas</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><strong>Totalmente Responsivo:</strong> Funciona perfeitamente em mobile, tablet e desktop</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span><strong>Animações Otimizadas:</strong> Transições suaves e performance otimizada</span>
                                </li>
                            </ul>
                        </div>
                        
                        <?php if (!empty($checkoutLink) && $checkoutLink !== '#'): ?>
                            <a href="<?php echo htmlspecialchars($checkoutLink); ?>" target="_blank" class="cta-button">
                                <i class="fas fa-shopping-cart"></i>
                                Adquirir Agora
                            </a>
                        <?php else: ?>
                            <div class="info-box">
                                <p>
                                    <strong><i class="fas fa-info-circle"></i> Link de checkout não configurado:</strong><br>
                                    Configure o link do checkout em <code>config.php</code> na constante <code>BANNERS_CHECKOUT_LINK</code>.
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-box">
                            <p>
                                <strong><i class="fas fa-cog"></i> Como ativar:</strong><br>
                                Após adquirir a funcionalidade, altere a constante <code>FEATURE_BANNERS_ENABLED</code> 
                                de <code>0</code> para <code>1</code> no arquivo <code>config.php</code>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

