<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

// Check if music feature is enabled - ler diretamente do .env
$musicEnabledEnv = function_exists('env') ? env('FEATURE_MUSIC_ENABLED', '1') : '1';
$musicEnabled = in_array(strtolower($musicEnabledEnv), ['1', 'true', 'yes', 'on']) ? 1 : 0;
$checkoutLink = defined('MUSIC_CHECKOUT_LINK') ? MUSIC_CHECKOUT_LINK : '';

// If feature is disabled, show upsell page
if ($musicEnabled === 0) {
    include __DIR__ . '/music-upsell.php';
    exit;
}

$page_title = 'Música de Fundo';
$page_subtitle = 'Configure a música que toca na vitrine';
$csrf = generateCsrfToken();

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de segurança inválido.";
    } else {
        try {
            // Get music file path
            $musicPath = trim($_POST['music_path'] ?? '');
            
            // Get music active status
            $is_active = isset($_POST['music_active']) ? 1 : 0;
            
            // Save music path to settings
            if (!empty($musicPath)) {
                updateSetting($pdo, 'music_file_path', $musicPath);
            }
            
            // Save music active status
            updateSetting($pdo, 'music_is_active', $is_active);
            
            // Determine success message
            if (isset($_POST['deactivate_music'])) {
                $success = "Música desativada com sucesso!";
            } elseif (isset($_POST['music_active']) && $_POST['music_active'] == '1' && !isset($_POST['deactivate_music'])) {
                $success = "Música ativada com sucesso!";
            } else {
                $success = $is_active ? "Música atualizada e ativada com sucesso!" : "Música atualizada com sucesso!";
            }
            
            logActivity($pdo, $_SESSION['user_id'], 'music_updated', "Música de fundo atualizada");
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
            error_log("Music save error: " . $e->getMessage());
        }
    }
}

// Load current settings
$currentMusicPath = getSetting($pdo, 'music_file_path', APP_URL . '/assets/musica.mp3');
$musicIsActive = (int)getSetting($pdo, 'music_is_active', 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/image-upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .simple-container {
            max-width: 900px;
            margin: 0 auto;
            padding-bottom: 100px;
        }
        
        .status-card {
            background: linear-gradient(135deg, var(--admin-accent) 0%, rgba(199, 163, 51, 0.8) 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            color: var(--admin-text-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid var(--admin-border);
        }
        
        .status-card h2 {
            margin: 0 0 15px 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-card .status-controls {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--admin-bg-card);
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid var(--admin-border);
        }
        
        .status-toggle:hover {
            background: var(--admin-bg-secondary);
            border-color: var(--admin-accent);
        }
        
        .status-toggle input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid var(--admin-border);
        }
        
        .status-badge.active {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border-color: rgba(34, 197, 94, 0.3);
        }
        
        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .simple-section {
            background: var(--admin-bg-card);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--admin-border);
        }
        
        .simple-section h3 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            color: var(--admin-text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .music-upload-area {
            min-height: 200px;
            margin-bottom: 15px;
        }
        
        .help-box {
            background: var(--admin-bg-secondary);
            border-left: 4px solid var(--admin-accent);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid var(--admin-border);
        }
        
        .help-box p {
            margin: 5px 0;
            color: var(--admin-text-secondary);
            font-size: 0.9rem;
        }
        
        .help-box strong {
            color: var(--admin-accent);
        }
        
        .save-button-container {
            position: fixed;
            bottom: 0;
            left: 250px;
            right: 0;
            background: var(--admin-bg-card);
            padding: 20px;
            border-top: 3px solid var(--admin-accent);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            border-left: 1px solid var(--admin-border);
            border-right: 1px solid var(--admin-border);
        }
        
        @media (max-width: 1024px) {
            .save-button-container {
                left: 0;
            }
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--admin-accent), rgba(199, 163, 51, 0.9));
            color: var(--admin-text-primary);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
        }
        
        .btn-toggle {
            background: var(--admin-bg-card);
            color: var(--admin-text-primary);
            border: 2px solid var(--admin-border);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-toggle:hover {
            border-color: var(--admin-accent);
            background: var(--admin-accent);
            color: var(--admin-text-primary);
        }
        
        @media (max-width: 768px) {
            .simple-container {
                padding: 0 15px;
            }
            
            .simple-section {
                padding: 20px;
            }
            
            .status-card {
                padding: 20px;
            }
            
            .status-controls {
                flex-direction: column;
                align-items: stretch;
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
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-music"></i> <?php echo $page_title; ?></h1>
                        <p class="page-description">
                            <?php echo $page_subtitle; ?>
                        </p>
                    </div>
                </div>
                
                <div class="simple-container">
                    <form method="POST" id="musicForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                        <!-- Status Card -->
                        <div class="status-card">
                            <h2><i class="fas fa-power-off"></i> Status da Música</h2>
                            <div class="status-controls">
                                <label class="status-toggle">
                                    <input type="checkbox" 
                                           id="musicActivationToggle" 
                                           name="music_active" 
                                           value="1"
                                           <?php echo $musicIsActive == 1 ? 'checked' : ''; ?>>
                                    <span>Música Ligada</span>
                                </label>
                                <span class="status-badge <?php echo $musicIsActive == 1 ? 'active' : 'inactive'; ?>">
                                    <?php echo $musicIsActive == 1 ? '✓ ATIVO' : '✗ INATIVO'; ?>
                                </span>
                                <?php if ($musicIsActive == 1): ?>
                                    <button type="submit" 
                                            name="deactivate_music" 
                                            value="1"
                                            form="musicForm"
                                            class="btn-toggle">
                                        <i class="fas fa-ban"></i> Desligar
                                    </button>
                                <?php else: ?>
                                    <button type="submit" 
                                            name="music_active" 
                                            value="1"
                                            form="musicForm"
                                            class="btn-toggle">
                                        <i class="fas fa-check-circle"></i> Ligar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Music File Section -->
                        <div class="simple-section">
                            <h3>
                                <i class="fas fa-file-audio"></i>
                                Arquivo de Música
                            </h3>
                            <p>Faça upload de um arquivo de música ou cole o caminho/URL do arquivo.</p>
                            
                            <div class="music-upload-area">
                                <div id="musicFileUpload" style="min-height: 200px;">
                                    <div style="padding: 40px; text-align: center; color: var(--admin-text-secondary); border: 2px dashed var(--admin-border); border-radius: 8px; background: var(--admin-bg-secondary);">
                                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                        <p>Carregando...</p>
                                    </div>
                                </div>
                                <input type="hidden" name="music_path" 
                                       id="musicFilePath" 
                                       value="<?php echo htmlspecialchars($currentMusicPath); ?>">
                            </div>
                            
                            <div class="help-box">
                                <p><strong>Como adicionar:</strong></p>
                                <p>• Arraste um arquivo de áudio (MP3, WAV, OGG) e solte na área acima</p>
                                <p>• Ou clique na área para escolher um arquivo</p>
                                <p>• Ou cole o caminho/URL do arquivo de música (ex: uploads/music/musica.mp3 ou https://exemplo.com/musica.mp3)</p>
                                <p><strong>Formatos suportados:</strong> MP3, WAV, OGG</p>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </main>
        
        <!-- Fixed Save Button -->
        <div class="save-button-container">
            <button type="submit" form="musicForm" class="btn-save">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/image-upload.js"></script>
    <script>
        // Initialize music file upload
        function initializeMusicUpload() {
            if (typeof ImageUpload === 'undefined') {
                setTimeout(initializeMusicUpload, 300);
                return;
            }
            
            const containerId = 'musicFileUpload';
            const container = document.getElementById(containerId);
            
            if (!container) {
                return;
            }
            
            const hiddenInput = document.getElementById('musicFilePath');
            const currentMusic = hiddenInput ? hiddenInput.value : '';
            
            try {
                container.innerHTML = '';
                
                // Usar ImageUpload mas com validação customizada para áudio
                const uploadInstance = new ImageUpload(containerId, {
                    uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
                    inputName: 'music_path',
                    folder: 'music',
                    currentImage: currentMusic,
                    showLinkOption: true,
                    allowedTypes: ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/x-m4a'],
                    maxSize: 20 * 1024 * 1024, // 20MB para áudio
                    onUploadSuccess: function(url) {
                        if (hiddenInput) {
                            hiddenInput.value = url;
                        }
                    },
                    onUploadError: function(error) {
                        alert('Erro ao fazer upload: ' + (error.message || error));
                    }
                });
                
                // Sincronizar o campo hidden com o componente
                setTimeout(function() {
                    const componentHiddenInput = document.getElementById(containerId + '_hiddenInput');
                    if (componentHiddenInput && hiddenInput) {
                        // Atualizar o campo hidden do formulário quando o componente mudar
                        const syncInputs = function() {
                            if (componentHiddenInput.value !== hiddenInput.value) {
                                hiddenInput.value = componentHiddenInput.value;
                            }
                        };
                        
                        // Observar mudanças no valor
                        componentHiddenInput.addEventListener('input', syncInputs);
                        componentHiddenInput.addEventListener('change', syncInputs);
                        
                        // Sincronizar periodicamente (fallback)
                        setInterval(syncInputs, 500);
                        
                        // Sincronizar inicialmente
                        syncInputs();
                    }
                }, 100);
            } catch (error) {
                console.error('Error initializing music upload:', error);
            }
        }
        
        function initWhenReady() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(initializeMusicUpload, 500);
                });
            } else {
                setTimeout(initializeMusicUpload, 500);
            }
        }
        
        initWhenReady();
        
        // Retry initialization
        let retryCount = 0;
        const maxRetries = 20;
        const retryInterval = setInterval(function() {
            retryCount++;
            if (typeof ImageUpload !== 'undefined') {
                clearInterval(retryInterval);
                if (!document.getElementById('musicFileUpload').querySelector('.image-upload-wrapper')) {
                    initializeMusicUpload();
                }
            } else if (retryCount >= maxRetries) {
                clearInterval(retryInterval);
            }
        }, 500);
        
        // Garantir que o campo hidden seja atualizado antes do submit
        const musicForm = document.getElementById('musicForm');
        if (musicForm) {
            musicForm.addEventListener('submit', function(e) {
                const componentHiddenInput = document.getElementById('musicFileUpload_hiddenInput');
                const hiddenInput = document.getElementById('musicFilePath');
                if (componentHiddenInput && hiddenInput) {
                    hiddenInput.value = componentHiddenInput.value;
                }
            });
        }
    </script>
</body>
</html>

