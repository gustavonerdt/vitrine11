<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Vitrine Independente';
$page_subtitle = 'Em breve, nosso próprio sistema de e-commerce completo em 2026!';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitrine Independente | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        @keyframes glow {
            0%, 100% { text-shadow: 0 0 20px rgba(212, 175, 55, 0.5); }
            50% { text-shadow: 0 0 40px rgba(212, 175, 55, 0.8), 0 0 60px rgba(212, 175, 55, 0.4); }
        }
        .rocket-icon {
            animation: pulse 2s infinite, glow 3s infinite;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-rocket"></i> Vitrine Independente</h1>
                        <p>O futuro do e-commerce está chegando. Uma plataforma completa e independente desenvolvida especialmente para você.</p>
                    </div>
                </div>

                <div class="admin-card" style="background: linear-gradient(135deg, rgba(15, 15, 15, 0.98) 0%, rgba(26, 26, 26, 0.98) 100%); border: 1px solid var(--admin-accent); padding: 80px 40px; min-height: 600px; display: flex; align-items: center; justify-content: center;">
                    <div style="text-align: center; max-width: 700px; width: 100%;">
                        <div style="margin-bottom: 40px;">
                            <i class="fas fa-rocket rocket-icon" style="font-size: 80px; color: var(--admin-accent);"></i>
                        </div>
                        
                        <h2 style="color: var(--admin-text-primary); margin-bottom: 30px; font-size: 2.5rem; font-weight: 700; letter-spacing: -0.5px;">
                            Vitrine Independente
                        </h2>
                        
                        <div style="background: rgba(212, 175, 55, 0.1); border-left: 4px solid var(--admin-accent); padding: 25px; margin: 30px 0; border-radius: 8px; text-align: left;">
                            <p style="color: var(--admin-text-secondary); font-size: 1.2rem; line-height: 1.8; margin: 0;">
                                <strong style="color: var(--admin-accent);">Em breve, nosso próprio sistema de e-commerce!</strong>
                            </p>
                            <p style="color: var(--admin-text-muted); font-size: 1rem; line-height: 1.6; margin: 15px 0 0 0;">
                                Estamos desenvolvendo uma plataforma completa e independente para revolucionar sua experiência de vendas online.
                            </p>
                        </div>
                        
                        <div style="margin-top: 50px;">
                            <p style="color: var(--admin-accent); font-size: 3.5rem; font-weight: 800; margin: 0; text-shadow: 0 0 30px rgba(212, 175, 55, 0.3); letter-spacing: 2px;">
                                2026
                            </p>
                            <p style="color: var(--admin-text-muted); font-size: 0.9rem; margin-top: 15px; text-transform: uppercase; letter-spacing: 2px;">
                                O Futuro Chegando
                            </p>
                        </div>
                        
                        <div style="margin-top: 60px; padding-top: 30px; border-top: 1px solid var(--admin-border);">
                            <p style="color: var(--admin-text-secondary); font-size: 1rem; line-height: 1.6; margin: 0;">
                                <i class="fas fa-sparkles" style="color: var(--admin-accent); margin-right: 8px;"></i>
                                Uma solução completa, moderna e totalmente personalizada para o seu negócio
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>