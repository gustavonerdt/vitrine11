<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in as admin
if (isLoggedIn() && isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?php echo defined('APP_NAME') ? APP_NAME : 'Admin'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo defined('APP_URL') ? APP_URL : ''; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <img src="<?php echo htmlspecialchars(LOGO_URL); ?>" alt="<?php echo APP_NAME; ?>" class="auth-logo">
                <h1 class="auth-title">Painel Administrativo</h1>
                <p class="auth-subtitle">Acesse sua conta de administrador</p>
            </div>

            <div id="errorMessage" class="alert alert-danger hidden"></div>

            <form id="adminLoginForm" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

                <div class="form-group">
                    <label class="label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" required placeholder="admin@email.com">
                </div>

                <div class="form-group">
                    <label class="label" for="password">Senha</label>
                    <input class="input" type="password" id="password" name="password" required placeholder="••••••••">
                </div>

                <button class="btn-primary w-full" type="submit" id="submitBtn">Acessar Painel</button>
            </form>

            <p class="auth-footer">
                <a href="<?php echo defined('APP_URL') ? APP_URL : '/'; ?>">Voltar ao site</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('adminLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const errorDiv = document.getElementById('errorMessage');
            
            btn.disabled = true;
            btn.textContent = 'Entrando...';
            errorDiv.classList.add('hidden');

            try {
                const formData = new FormData(e.target);
                const response = await fetch('../api/admin-login.php', {
                    method: 'POST',
                    body: formData
                });

                // Log da resposta
                console.log('Login Response Status:', response.status);
                
                let data;
                try {
                    const text = await response.text();
                    console.log('Login Response Text:', text);
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('Erro ao parsear JSON:', parseError);
                    console.error('Resposta recebida:', await response.text());
                    throw new Error('Resposta inválida do servidor. Verifique os logs.');
                }
                
                if (data.success) {
                    window.location.href = data.redirect || 'dashboard.php';
                } else {
                    errorDiv.textContent = data.message || 'Credenciais inválidas';
                    errorDiv.classList.remove('hidden');
                    console.error('Login Error:', data.message);
                }
            } catch (error) {
                console.error('Login Exception:', error);
                console.error('Error Stack:', error.stack);
                
                // Log para o servidor também (se possível)
                try {
                    await fetch('../api/log-error.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            error: error.message,
                            stack: error.stack,
                            url: window.location.href,
                            userAgent: navigator.userAgent
                        })
                    });
                } catch (logError) {
                    console.error('Erro ao logar erro:', logError);
                }
                
                errorDiv.textContent = 'Erro de conexão: ' + error.message + '. Verifique o console para mais detalhes.';
                errorDiv.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Acessar Painel';
            }
        });
    </script>
</body>
</html>
