<?php
/**
 * API para salvar configuracoes do tema
 */
// session_start() ja e chamado em config.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticacao usando as funcoes padrao do sistema
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo nao permitido']);
    exit;
}

// Ler dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['theme']) || !is_array($input['theme'])) {
    echo json_encode(['success' => false, 'error' => 'Dados do tema invalidos']);
    exit;
}

$themeData = $input['theme'];
$createVersion = isset($input['create_version']) && $input['create_version'] === true;

// Lista de configuracoes permitidas - COMPLETA
$allowedSettings = [
    // Cores Principais
    'color_primary', 'color_secondary', 'color_accent', 'color_background',
    'color_text', 'color_text_muted', 'header_bg', 'header_text', 'nav_bg',
    'card_bg', 'card_border', 'card_hover_bg', 'button_primary_bg',
    'button_primary_text', 'button_secondary_bg', 'button_secondary_text',
    'footer_bg', 'footer_text',
    
    // Cores do Carrinho
    'cart_bg', 'cart_item_bg', 'cart_border', 'cart_button_bg', 'cart_button_text',
    'cart_badge_bg', 'cart_badge_text', 'cart_sticky_bg',
    
    // Cores do Checkout
    'checkout_bg', 'checkout_card_bg', 'checkout_border', 'checkout_input_bg',
    'checkout_input_border', 'checkout_button_bg', 'checkout_button_text',
    'checkout_progress_active', 'checkout_progress_inactive',
    
    // Cores dos Produtos
    'product_card_bg', 'product_card_border', 'product_card_hover',
    'product_price_color', 'product_original_price_color', 'product_discount_bg',
    'product_badge_vip_bg', 'product_badge_vip_text',
    
    // Cores do Menu/Navegacao
    'menu_bg', 'menu_text', 'menu_hover_bg', 'menu_active_bg',
    'search_bar_bg', 'search_bar_border', 'search_bar_text',
    'filter_btn_bg', 'filter_btn_text', 'filter_btn_active_bg',
    
    // Cores de Feedback
    'success_color', 'error_color', 'warning_color', 'info_color',
    'discount_badge_bg', 'discount_badge_text',
    
    // Tipografia
    'font_heading', 'font_body', 'font_size_base',
    'heading_weight', 'body_weight', 'line_height',
    
    // Layout
    'border_radius', 'spacing_unit', 'container_max_width', 'products_per_row',
    'card_shadow', 'button_radius', 'input_radius',
    
    // Faixa Rotativa
    'faixa_enabled', 'faixa_bg_color', 'faixa_text_color', 'faixa_font_size',
    'faixa_frases', 'faixa_links', 'faixa_interval',
    
    // Textos do site
    'search_placeholder', 'filter_button_text', 'buy_button_text', 'empty_cart_text',
    'cart_title', 'checkout_title', 'product_add_button_text',
    'footer_text_content', 'copyright_text',
    
    // Redirecionamentos
    'logo_redirect_url', 'after_add_cart_redirect', 'after_purchase_url',
    
    // Configuracoes de Checkout
    'checkout_email_placeholder', 'checkout_cep_placeholder', 'checkout_phone_placeholder',
    'checkout_name_placeholder', 'checkout_cpf_placeholder',
    'checkout_button_text', 'checkout_success_message',
    
    // Configuracoes do Carrinho
    'cart_empty_message', 'cart_continue_text', 'cart_checkout_text',
    'cart_summary_title', 'cart_subtotal_text', 'cart_shipping_text',
    
    // Configuracoes de Produtos
    'product_vip_label', 'product_sale_label', 'product_new_label',
    'product_out_of_stock_text', 'product_add_to_cart_text',
    
    // Configuracoes de WhatsApp
    'whatsapp_button_text', 'whatsapp_message_template',
    
    // Configuracoes de Footer
    'footer_column1_title', 'footer_column2_title', 'footer_column3_title',
    'footer_social_facebook', 'footer_social_instagram', 'footer_social_whatsapp',
    
    // Configuracoes Gerais
    'show_product_description', 'show_product_brand', 'show_product_badge',
    'enable_product_zoom', 'enable_sticky_header', 'enable_back_to_top',
    
    // Configuracoes de Mobile
    'mobile_menu_style', 'mobile_products_per_row', 'mobile_font_size_reduction'
];

try {
    $pdo->beginTransaction();
    
    // Criar versao antes de salvar (se solicitado)
    if ($createVersion) {
        // Criar tabela de versoes se nao existir
        $pdo->exec("CREATE TABLE IF NOT EXISTS theme_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            settings_data LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Buscar configuracoes atuais para backup
        $currentSettings = [];
        foreach ($allowedSettings as $key) {
            $currentSettings[$key] = getSetting($pdo, $key, '');
        }
        
        // Inserir versao
        $versionName = 'Backup ' . date('d/m/Y H:i');
        $stmt = $pdo->prepare("INSERT INTO theme_versions (name, settings_data) VALUES (?, ?)");
        $stmt->execute([$versionName, json_encode($currentSettings)]);
        
        // Manter apenas as ultimas 20 versoes
        $pdo->exec("DELETE FROM theme_versions WHERE id NOT IN (
            SELECT id FROM (SELECT id FROM theme_versions ORDER BY created_at DESC LIMIT 20) AS t
        )");
    }
    
    // Salvar novas configuracoes
    foreach ($themeData as $key => $value) {
        if (in_array($key, $allowedSettings)) {
            $value = is_string($value) ? trim($value) : $value;
            
            // Verificar se existe
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Tema salvo com sucesso'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error saving theme: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar tema: ' . $e->getMessage()
    ]);
}
