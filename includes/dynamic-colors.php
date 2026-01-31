<?php
/**
 * Dynamic Colors System
 * Aplica cores personalizadas do banco de dados
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/functions.php';
}

// Função helper para converter hex para RGB
function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) != 6) {
        return ['r' => 199, 'g' => 163, 'b' => 51]; // Default gold
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return ['r' => $r, 'g' => $g, 'b' => $b];
}

// Carregar cores do banco de dados
$colors = [
    'primary' => getSetting($pdo, 'color_primary', '#C7A333'),
    'secondary' => getSetting($pdo, 'color_secondary', '#D4B84A'),
    'accent' => getSetting($pdo, 'color_accent', '#E0C966'),
    'background' => getSetting($pdo, 'color_background', '#0F0F0F'),
    'surface' => getSetting($pdo, 'color_surface', '#1F1F1F'),
    'text' => getSetting($pdo, 'color_text', '#E0E0E0'),
    'text_muted' => getSetting($pdo, 'color_text_muted', '#B3B3B3'),
    'sidebar_logo_text' => getSetting($pdo, 'color_sidebar_logo_text', '#FFFFFF'),
];

// Converter primary para RGB para uso em rgba
$primaryRgb = hex2rgb($colors['primary']);

// Gerar CSS dinâmico
?>
<style>
:root {
    /* Cores Personalizadas do Sistema */
    --color-primary: <?php echo htmlspecialchars($colors['primary']); ?>;
    --color-secondary: <?php echo htmlspecialchars($colors['secondary']); ?>;
    --color-accent: <?php echo htmlspecialchars($colors['accent']); ?>;
    --color-background: <?php echo htmlspecialchars($colors['background']); ?>;
    --color-surface: <?php echo htmlspecialchars($colors['surface']); ?>;
    --color-text: <?php echo htmlspecialchars($colors['text']); ?>;
    --color-text-muted: <?php echo htmlspecialchars($colors['text_muted']); ?>;
    
    /* Aplicar cores personalizadas nas variáveis existentes */
    --accent-primary: var(--color-primary);
    --accent-secondary: var(--color-secondary);
    --accent-hover: var(--color-secondary);
    --bg-primary: var(--color-background);
    --bg-card: var(--color-surface);
    --text-primary: var(--color-text);
    --text-secondary: var(--color-text);
    --text-muted: var(--color-text-muted);
    
    /* Admin colors */
    --admin-accent: var(--color-primary);
    --admin-accent-hover: var(--color-secondary);
    --admin-bg-primary: var(--color-background);
    --admin-bg-card: var(--color-surface);
    --admin-text-primary: var(--color-text);
    --admin-text-secondary: var(--color-text-muted);
    --admin-sidebar-logo-text: <?php echo htmlspecialchars($colors['sidebar_logo_text']); ?>;
}

/* Aplicar cores em elementos específicos */
.btn-primary,
.btn-whatsapp-modern,
.filter-toggle-btn-desktop,
.btn-support-whatsapp-large,
.admin-link {
    background: var(--color-primary) !important;
    border-color: var(--color-primary) !important;
    color: #000000 !important;
}

.btn-primary:hover,
.btn-whatsapp-modern:hover,
.filter-toggle-btn-desktop:hover,
.btn-support-whatsapp-large:hover,
.admin-link:hover {
    background: var(--color-secondary) !important;
    border-color: var(--color-secondary) !important;
    color: #000000 !important;
}

.search-input:focus,
.filter-toggle-btn:hover {
    border-color: var(--color-primary) !important;
}

.search-input:focus {
    box-shadow: 0 0 0 4px rgba(<?php echo $primaryRgb['r'] . ', ' . $primaryRgb['g'] . ', ' . $primaryRgb['b']; ?>, 0.15) !important;
}

body {
    background: var(--color-background) !important;
    color: var(--color-text) !important;
}

.admin-card,
.vitrine-page {
    background: var(--color-background) !important;
}

.admin-card .card-body,
.vitrine-top-bar {
    background: var(--color-surface) !important;
}

a {
    color: var(--color-primary) !important;
}

a:hover {
    color: var(--color-secondary) !important;
}

/* Support Footer Card - Dynamic Colors */
.support-content-wrapper {
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.1) 0%, 
        var(--color-primary) 43%, 
        rgba(255, 255, 255, 0.1) 100%) !important;
}

.support-icon-large {
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.1) 0%, 
        var(--color-primary) 50%, 
        rgba(255, 255, 255, 0.1) 100%) !important;
    border-color: var(--color-primary) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4),
                0 0 0 8px rgba(<?php echo $primaryRgb['r'] . ', ' . $primaryRgb['g'] . ', ' . $primaryRgb['b']; ?>, 0.1) !important;
}

.support-icon-large i {
    color: #000000 !important;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.3)) !important;
}

.support-title-large {
    background: linear-gradient(135deg, 
        var(--color-text) 0%, 
        var(--color-primary) 50%, 
        var(--color-text) 100%) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    background-clip: text !important;
}

/* Sidebar Logo Text Color */
.sidebar-logo-text {
    color: var(--admin-sidebar-logo-text) !important;
}
</style>
