<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de segurança inválido.";
    } else {
        try {
            if ($_POST['action'] === 'add_product') {
                $name = trim($_POST['name'] ?? '');
                $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
                
                // Processar preço - remover formatação brasileira e converter
                $priceRaw = $_POST['price'] ?? '0';
                error_log("Price received (raw): " . var_export($priceRaw, true));
                
                // Remover espaços e converter vírgula para ponto
                $priceStr = str_replace(' ', '', $priceRaw);
                $priceStr = str_replace(',', '.', $priceStr);
                // Se tiver múltiplos pontos, manter apenas o último (formato brasileiro: 1.234,56)
                if (substr_count($priceStr, '.') > 1) {
                    $parts = explode('.', $priceStr);
                    $lastPart = array_pop($parts);
                    $priceStr = implode('', $parts) . '.' . $lastPart;
                }
                $price = (float)$priceStr;
                error_log("Price converted: " . var_export($price, true));
                
                $description = trim($_POST['description'] ?? '');
                $is_vip = isset($_POST['is_vip']) ? 1 : 0;
                $image_path = trim($_POST['image_path'] ?? '');

                if (empty($name)) {
                    throw new Exception("Nome do produto é obrigatório.");
                }
                if ($price < 0) {
                    throw new Exception("Preço inválido.");
                }

                $is_dynamic_ad = isset($_POST['enable_dynamic_ads']) ? 1 : 0;
                
                // Handle multiple images
                $product_images = [];
                $cover_image_index = 0;
                
                error_log("=== ADD PRODUCT DEBUG ===");
                error_log("POST keys: " . implode(', ', array_keys($_POST)));
                error_log("product_images in POST: " . (isset($_POST['product_images']) ? 'YES' : 'NO'));
                
                if (isset($_POST['product_images']) && is_array($_POST['product_images'])) {
                    error_log("product_images count: " . count($_POST['product_images']));
                    $product_images = array_filter($_POST['product_images'], function($img) {
                        return !empty(trim($img));
                    });
                    error_log("product_images after filter: " . count($product_images));
                    foreach ($product_images as $idx => $img) {
                        error_log("  Image $idx: " . trim($img));
                    }
                    $cover_image_index = isset($_POST['product_images_cover_index']) ? (int)$_POST['product_images_cover_index'] : 0;
                    error_log("Cover index: $cover_image_index");
                } else {
                    error_log("No product_images array in POST");
                }
                
                // Use first image as image_path for backward compatibility
                $image_path = !empty($product_images) ? $product_images[0] : trim($_POST['image_path'] ?? '');
                error_log("Final image_path: $image_path");
                
                $stmt = $pdo->prepare("INSERT INTO products (name, brand_id, price, description, is_vip, image_path, is_dynamic_ad, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$name, $brand_id, $price, $description, $is_vip, $image_path, $is_dynamic_ad]);
                
                $productId = $pdo->lastInsertId();
                error_log("Product ID created: $productId");
                
                // Create product_images table if it doesn't exist
                if (!db_table_exists($pdo, 'product_images')) {
                    try {
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS product_images (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                product_id INT NOT NULL,
                                image_url VARCHAR(500) NOT NULL,
                                is_cover TINYINT(1) DEFAULT 0,
                                display_order INT DEFAULT 0,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_product_id (product_id),
                                INDEX idx_is_cover (is_cover)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                        error_log("product_images table created successfully");
                    } catch (PDOException $e) {
                        error_log("Error creating product_images table: " . $e->getMessage());
                    }
                }
                
                // Save multiple images
                if (!empty($product_images)) {
                    error_log("Saving " . count($product_images) . " images to product_images table");
                    $imageStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_cover, display_order, created_at) VALUES (?, ?, ?, ?, NOW())");
                    foreach ($product_images as $index => $imageUrl) {
                        $is_cover = ($index === $cover_image_index) ? 1 : 0;
                        $imageUrl = trim($imageUrl);
                        error_log("  Inserting image $index: $imageUrl (cover: $is_cover)");
                        $imageStmt->execute([$productId, $imageUrl, $is_cover, $index]);
                    }
                    error_log("Images saved successfully");
                } else {
                    error_log("No images to save (product_images array is empty)");
                }
                error_log("=== END ADD PRODUCT DEBUG ===");
                
                // Create dynamic ad if enabled (simplified - only if variants exist)
                if ($is_dynamic_ad && db_table_exists($pdo, 'dynamic_ads')) {
                    $showVariants = 1; // Always show variants if dynamic ad is enabled
                    $adStmt = $pdo->prepare("INSERT INTO dynamic_ads (product_id, title, description, keywords, show_variants, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                    $adStmt->execute([$productId, $name, $description, '', $showVariants]);
                }
                
                // Create variants if provided (works for both dynamic ads and regular products)
                error_log("=== VARIANTES DEBUG ===");
                error_log("POST keys: " . implode(', ', array_keys($_POST)));
                error_log("dynamic_variants in POST: " . (isset($_POST['dynamic_variants']) ? 'YES' : 'NO'));
                
                if (isset($_POST['dynamic_variants'])) {
                    error_log("dynamic_variants type: " . gettype($_POST['dynamic_variants']));
                    error_log("dynamic_variants content: " . json_encode($_POST['dynamic_variants']));
                }
                
                // Create product_variants table if it doesn't exist
                if (!db_table_exists($pdo, 'product_variants')) {
                    try {
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS product_variants (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                product_id INT NOT NULL,
                                name VARCHAR(255) NOT NULL,
                                description TEXT,
                                price DECIMAL(10, 2) NOT NULL,
                                points INT DEFAULT 0,
                                image_path VARCHAR(500),
                                is_active TINYINT(1) DEFAULT 1,
                                display_order INT DEFAULT 0,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_product_id (product_id),
                                INDEX idx_is_active (is_active)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                        error_log("product_variants table created successfully");
                    } catch (PDOException $e) {
                        error_log("Error creating product_variants table: " . $e->getMessage());
                    }
                }
                
                if (isset($_POST['dynamic_variants']) && is_array($_POST['dynamic_variants']) && !empty($_POST['dynamic_variants'])) {
                    error_log("Variantes recebidas: " . count($_POST['dynamic_variants']));
                    $variantStmt = $pdo->prepare("INSERT INTO product_variants (product_id, name, description, price, points, image_path, is_active, display_order, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())");
                    $variantsSaved = 0;
                    foreach ($_POST['dynamic_variants'] as $index => $variant) {
                        error_log("Processing variant $index: " . json_encode($variant));
                        if (!empty($variant['name']) && isset($variant['price']) && floatval($variant['price']) > 0) {
                            try {
                                $variantStmt->execute([
                                    $productId,
                                    trim($variant['name']),
                                    trim($variant['description'] ?? ''),
                                    floatval($variant['price']),
                                    intval($variant['points'] ?? 0),
                                    trim($variant['image_path'] ?? ''),
                                    $index
                                ]);
                                $variantsSaved++;
                                error_log("Variante $index salva com sucesso");
                            } catch (PDOException $e) {
                                error_log("Erro ao salvar variante $index: " . $e->getMessage());
                            }
                        } else {
                            error_log("Variante $index ignorada - nome vazio ou preço inválido");
                        }
                    }
                    error_log("Total de variantes salvas: $variantsSaved");
                } else {
                    error_log("Nenhuma variante encontrada no POST. POST keys: " . implode(', ', array_keys($_POST)));
                }
                error_log("=== END VARIANTES DEBUG ===");
                
                $success = "Produto adicionado com sucesso!";
                logActivity($pdo, $_SESSION['user_id'], 'create_product', "Criou produto: $name");
                
            } elseif ($_POST['action'] === 'delete_product') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Produto excluído.";
                logActivity($pdo, $_SESSION['user_id'], 'delete_product', "Excluiu produto ID: $id");
                
            } elseif ($_POST['action'] === 'toggle_vip') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE products SET is_vip = NOT is_vip WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Status VIP alterado.";
                
            } elseif ($_POST['action'] === 'update_product') {
                $id = (int)$_POST['id'];
                $name = trim($_POST['name'] ?? '');
                $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
                
                // Processar preço - remover formatação brasileira e converter
                $priceRaw = $_POST['price'] ?? '0';
                error_log("UPDATE - Price received (raw): " . var_export($priceRaw, true));
                
                // Remover espaços e converter vírgula para ponto
                $priceStr = str_replace(' ', '', $priceRaw);
                $priceStr = str_replace(',', '.', $priceStr);
                // Se tiver múltiplos pontos, manter apenas o último (formato brasileiro: 1.234,56)
                if (substr_count($priceStr, '.') > 1) {
                    $parts = explode('.', $priceStr);
                    $lastPart = array_pop($parts);
                    $priceStr = implode('', $parts) . '.' . $lastPart;
                }
                $price = (float)$priceStr;
                error_log("UPDATE - Price converted: " . var_export($price, true));
                
                $description = trim($_POST['description'] ?? '');
                $is_vip = isset($_POST['is_vip']) ? 1 : 0;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $is_dynamic_ad = isset($_POST['enable_dynamic_ads']) ? 1 : 0;

                if (empty($name)) {
                    throw new Exception("Nome do produto é obrigatório.");
                }
                if ($price < 0) {
                    throw new Exception("Preço inválido.");
                }

                // Handle multiple images
                $product_images = [];
                $cover_image_index = 0;
                
                error_log("=== UPDATE PRODUCT DEBUG ===");
                error_log("Product ID: $id");
                error_log("POST keys: " . implode(', ', array_keys($_POST)));
                error_log("product_images in POST: " . (isset($_POST['product_images']) ? 'YES' : 'NO'));
                
                if (isset($_POST['product_images']) && is_array($_POST['product_images'])) {
                    error_log("product_images count: " . count($_POST['product_images']));
                    $product_images = array_filter($_POST['product_images'], function($img) {
                        return !empty(trim($img));
                    });
                    error_log("product_images after filter: " . count($product_images));
                    foreach ($product_images as $idx => $img) {
                        error_log("  Image $idx: " . trim($img));
                    }
                    $cover_image_index = isset($_POST['product_images_cover_index']) ? (int)$_POST['product_images_cover_index'] : 0;
                    error_log("Cover index: $cover_image_index");
                } else {
                    error_log("No product_images array in POST");
                }
                
                // Use first image as image_path for backward compatibility
                $image_path = !empty($product_images) ? $product_images[0] : trim($_POST['image_path'] ?? '');
                error_log("Final image_path: $image_path");
                
                // Update product
                $stmt = $pdo->prepare("UPDATE products SET name = ?, brand_id = ?, price = ?, description = ?, is_vip = ?, image_path = ?, is_dynamic_ad = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $brand_id, $price, $description, $is_vip, $image_path, $is_dynamic_ad, $is_active, $id]);
                
                // Create product_images table if it doesn't exist
                if (!db_table_exists($pdo, 'product_images')) {
                    try {
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS product_images (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                product_id INT NOT NULL,
                                image_url VARCHAR(500) NOT NULL,
                                is_cover TINYINT(1) DEFAULT 0,
                                display_order INT DEFAULT 0,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_product_id (product_id),
                                INDEX idx_is_cover (is_cover),
                                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                        error_log("product_images table created successfully");
                    } catch (PDOException $e) {
                        error_log("Error creating product_images table: " . $e->getMessage());
                        // Try without foreign key if it fails
                        try {
                            $pdo->exec("
                                CREATE TABLE IF NOT EXISTS product_images (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    product_id INT NOT NULL,
                                    image_url VARCHAR(500) NOT NULL,
                                    is_cover TINYINT(1) DEFAULT 0,
                                    display_order INT DEFAULT 0,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    INDEX idx_product_id (product_id),
                                    INDEX idx_is_cover (is_cover)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                            ");
                            error_log("product_images table created successfully (without foreign key)");
                        } catch (PDOException $e2) {
                            error_log("Error creating product_images table (second attempt): " . $e2->getMessage());
                        }
                    }
                }
                
                // Delete existing images
                $deleteStmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                $deleteStmt->execute([$id]);
                error_log("Deleted existing images for product $id");
                
                // Insert new images
                if (!empty($product_images)) {
                    error_log("Saving " . count($product_images) . " images to product_images table");
                    $imageStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_cover, display_order, created_at) VALUES (?, ?, ?, ?, NOW())");
                    foreach ($product_images as $index => $imageUrl) {
                        $is_cover = ($index === $cover_image_index) ? 1 : 0;
                        $imageUrl = trim($imageUrl);
                        error_log("  Inserting image $index: $imageUrl (cover: $is_cover)");
                        $imageStmt->execute([$id, $imageUrl, $is_cover, $index]);
                    }
                    error_log("Images saved successfully");
                } else {
                    error_log("No images to save (product_images is empty)");
                }
                error_log("=== END UPDATE PRODUCT DEBUG ===");
                
                // Create/Update variants if provided
                error_log("=== VARIANTES UPDATE DEBUG ===");
                error_log("POST keys: " . implode(', ', array_keys($_POST)));
                error_log("dynamic_variants in POST: " . (isset($_POST['dynamic_variants']) ? 'YES' : 'NO'));
                
                if (isset($_POST['dynamic_variants'])) {
                    error_log("dynamic_variants type: " . gettype($_POST['dynamic_variants']));
                    error_log("dynamic_variants content: " . json_encode($_POST['dynamic_variants']));
                }
                
                // Create product_variants table if it doesn't exist
                if (!db_table_exists($pdo, 'product_variants')) {
                    try {
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS product_variants (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                product_id INT NOT NULL,
                                name VARCHAR(255) NOT NULL,
                                description TEXT,
                                price DECIMAL(10, 2) NOT NULL,
                                points INT DEFAULT 0,
                                image_path VARCHAR(500),
                                is_active TINYINT(1) DEFAULT 1,
                                display_order INT DEFAULT 0,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_product_id (product_id),
                                INDEX idx_is_active (is_active)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                        error_log("product_variants table created successfully");
                    } catch (PDOException $e) {
                        error_log("Error creating product_variants table: " . $e->getMessage());
                    }
                }
                
                // Delete existing variants
                $deleteVariantsStmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
                $deleteVariantsStmt->execute([$id]);
                error_log("Deleted existing variants for product $id");
                
                // Insert new variants
                if (isset($_POST['dynamic_variants']) && is_array($_POST['dynamic_variants']) && !empty($_POST['dynamic_variants'])) {
                    error_log("Variantes recebidas: " . count($_POST['dynamic_variants']));
                    $variantStmt = $pdo->prepare("INSERT INTO product_variants (product_id, name, description, price, points, image_path, is_active, display_order, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())");
                    $variantsSaved = 0;
                    foreach ($_POST['dynamic_variants'] as $index => $variant) {
                        error_log("Processing variant $index: " . json_encode($variant));
                        if (!empty($variant['name']) && isset($variant['price']) && floatval($variant['price']) > 0) {
                            try {
                                $variantStmt->execute([
                                    $id,
                                    trim($variant['name']),
                                    trim($variant['description'] ?? ''),
                                    floatval($variant['price']),
                                    intval($variant['points'] ?? 0),
                                    trim($variant['image_path'] ?? ''),
                                    $index
                                ]);
                                $variantsSaved++;
                                error_log("Variante $index salva com sucesso");
                            } catch (PDOException $e) {
                                error_log("Erro ao salvar variante $index: " . $e->getMessage());
                            }
                        } else {
                            error_log("Variante $index ignorada - nome vazio ou preço inválido");
                        }
                    }
                    error_log("Total de variantes salvas: $variantsSaved");
                } else {
                    error_log("Nenhuma variante encontrada no POST para atualização");
                }
                error_log("=== END VARIANTES UPDATE DEBUG ===");
                
                $success = "Produto atualizado com sucesso!";
                logActivity($pdo, $_SESSION['user_id'], 'update_product', "Atualizou produto: $name (ID: $id)");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch Brands
$brands = [];
try {
    if (db_table_exists($pdo, 'brands')) {
        $stmt = $pdo->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $brands = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Brands fetch error: " . $e->getMessage());
}

// Fetch Products
$products = [];
try {
    // Verificar se a coluna description existe
    $hasDescription = db_has_column($pdo, 'products', 'description');
    
    // Construir query com colunas específicas
    $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
    if ($hasDescription) {
        $columns .= ", p.description";
    }
    
    $stmt = $pdo->query("SELECT $columns, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.created_at DESC");
    $products = $stmt->fetchAll();
    
    // Garantir que description existe no array mesmo se a coluna não existir
    if (!$hasDescription) {
        foreach ($products as &$product) {
            $product['description'] = null;
        }
        unset($product);
    }
} catch (PDOException $e) {
    error_log("Error loading products: " . $e->getMessage());
    $error = "Erro ao carregar produtos.";
}

$csrf = generateCsrfToken();
$page_title = 'Gerenciar Produtos';
$page_subtitle = 'Adicione e edite produtos do marketplace';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/image-upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <style>
        /* Quill Editor Custom Styles */
        .quill-editor-container {
            background: var(--admin-bg-card);
            border-radius: 10px;
            overflow: hidden;
        }
        .quill-editor-container .ql-toolbar {
            background: var(--admin-bg-darker);
            border: 1px solid var(--admin-border);
            border-radius: 10px 10px 0 0;
        }
        .quill-editor-container .ql-container {
            border: 1px solid var(--admin-border);
            border-top: none;
            border-radius: 0 0 10px 10px;
            min-height: 150px;
            font-size: 14px;
        }
        .quill-editor-container .ql-editor {
            min-height: 150px;
            background: var(--admin-bg-card);
            color: var(--admin-text);
        }
        .quill-editor-container .ql-editor.ql-blank::before {
            color: var(--admin-text-muted);
            font-style: normal;
        }
        .ql-toolbar .ql-stroke {
            stroke: var(--admin-text);
        }
        .ql-toolbar .ql-fill {
            fill: var(--admin-text);
        }
        .ql-toolbar .ql-picker {
            color: var(--admin-text);
        }
        .ql-toolbar button:hover .ql-stroke,
        .ql-toolbar button.ql-active .ql-stroke {
            stroke: var(--admin-accent);
        }
        .ql-toolbar button:hover .ql-fill,
        .ql-toolbar button.ql-active .ql-fill {
            fill: var(--admin-accent);
        }
        .ql-snow .ql-picker-options {
            background: var(--admin-bg-card);
            border-color: var(--admin-border);
        }
        .ql-snow .ql-picker-item:hover {
            color: var(--admin-accent);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="admin-container">
                <?php include __DIR__ . '/includes/header.php'; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="grid-12">
                    <!-- Form -->
                    <div class="col-span-4">
                        <div class="form-card">
                            <h3 id="formTitle">Adicionar Produto</h3>
                            <form method="POST" class="form mt-4" id="productForm">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="add_product" id="formAction">
                                <input type="hidden" name="id" value="" id="productId">

                                <div class="form-group">
                                    <label class="label">
                                        <i class="fas fa-tag"></i> Nome do Produto <span class="required">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-control form-control-modern" required placeholder="Ex: Chanel N5">
                                </div>

                                <div class="form-group">
                                    <label class="label">
                                        <i class="fas fa-building"></i> Marca
                                    </label>
                                    <select name="brand_id" class="form-control form-control-modern">
                                        <option value="">Selecione uma marca...</option>
                                        <?php foreach ($brands as $b): ?>
                                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="label">
                                        <i class="fas fa-dollar-sign"></i> Preço (R$) <span class="required">*</span>
                                    </label>
                                    <input type="number" step="0.01" min="0" name="price" class="form-control form-control-modern" required placeholder="0.00">
                                </div>

                                <div class="form-group">
                                    <label class="label">
                                        <i class="fas fa-info-circle admin-tooltip" data-tooltip="Você pode adicionar até 5 imagens. Defina qual será a capa."></i>
                                        Imagens do Produto (até 5)
                                    </label>
                                    <div id="productImagesUpload"></div>
                                    <small class="form-hint">Arraste e solte ou clique para selecionar. Você pode definir qual imagem será a capa.</small>
                                </div>

                                <div class="form-group">
                                    <label class="label">
                                        <i class="fas fa-align-left"></i> Descricao
                                    </label>
                                    <div class="quill-editor-container">
                                        <div id="productDescriptionEditor"></div>
                                    </div>
                                    <textarea name="description" id="productDescription" class="form-control form-control-modern" rows="4" placeholder="Descreva o produto..." style="display: none;"></textarea>
                                </div>

                                <div class="form-group">
                                    <div class="dynamic-ads-toggle-card" onclick="toggleDynamicAdsCard(event)">
                                        <div class="dynamic-ads-toggle-header">
                                            <div>
                                                <i class="fas fa-magic"></i>
                                                <strong>Ativar Anúncio Dinâmico</strong>
                                            </div>
                                            <label class="custom-checkbox-wrapper">
                                                <input type="checkbox" name="enable_dynamic_ads" id="enableDynamicAds" value="1" onchange="toggleDynamicAds()" onclick="event.stopPropagation()">
                                                <span class="custom-checkbox"></span>
                                            </label>
                                        </div>
                                        <p class="dynamic-ads-toggle-desc">Permite criar variações do mesmo produto com diferentes preços, tamanhos ou versões</p>
                                    </div>
                                </div>

                                <div id="dynamicAdsSection" style="display: none; margin-top: 20px; padding: 24px; background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(212, 175, 55, 0.05)); border-radius: 12px; border: 2px solid var(--admin-accent);">
                                    <h4 style="margin-bottom: 20px; color: var(--admin-accent); display: flex; align-items: center; gap: 10px;">
                                        <i class="fas fa-boxes"></i> Variantes do Produto
                                    </h4>
                                    
                                    <div class="form-group">
                                        <div id="dynamicVariantsList" style="margin-bottom: 15px;">
                                            <!-- Variantes serão adicionadas aqui -->
                                        </div>
                                        <button type="button" id="addVariantBtn" onclick="if(typeof addDynamicVariant === 'function') { addDynamicVariant(); } else if(typeof window.addDynamicVariant === 'function') { window.addDynamicVariant(); } else { console.error('addDynamicVariant function not found'); alert('Erro: Função não encontrada. Recarregue a página.'); } return false;" class="btn-outline" style="width: 100%; cursor: pointer !important; pointer-events: auto !important; z-index: 1000 !important; position: relative;">
                                            <i class="fas fa-plus"></i> Adicionar Variante
                                        </button>
                                        <small class="form-hint">Adicione as variações do produto (ex: 80ml, 100ml, EDP, EDT, etc.)</small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox custom-checkbox-label">
                                        <input type="checkbox" name="is_active" value="1" class="custom-checkbox-input" checked>
                                        <span class="custom-checkbox"></span>
                                        <span>Produto ativo (visível no marketplace)</span>
                                    </label>
                                </div>

                                <button type="submit" class="btn-primary w-full mt-4" id="submitBtn">Adicionar Produto</button>
                            </form>
                        </div>
                    </div>

                    <!-- Products List -->
                    <div class="col-span-8">
                        <div class="form-card">
                            <div class="flex justify-between items-center mb-4">
                                <h3>Produtos (<?php echo count($products); ?>)</h3>
                                <div style="display: flex; gap: 10px;">
                                    <button onclick="bulkAction('delete')" class="btn-sm btn-danger" id="bulkDeleteBtn" style="display: none;">
                                        <i class="fas fa-trash"></i> Excluir Selecionados
                                    </button>
                                    <button onclick="bulkAction('public')" class="btn-sm btn-success" id="bulkPublicBtn" style="display: none;">
                                        <i class="fas fa-globe"></i> Tornar Públicos
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (empty($products)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📦</div>
                                    <h3>Nenhum produto cadastrado</h3>
                                    <p>Adicione seu primeiro produto usando o formulario.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px; text-align: center;">
                                                    <label class="custom-checkbox-wrapper" style="display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
                                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" title="Selecionar todos" class="custom-checkbox-input">
                                                        <span class="custom-checkbox"></span>
                                                    </label>
                                                </th>
                                                <th>Produto</th>
                                                <th>Marca</th>
                                                <th>Preco</th>
                                                <th>Status</th>
                                                <th>Acoes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $p): ?>
                                                <tr>
                                                    <td style="text-align: center;">
                                                        <label class="custom-checkbox-wrapper" style="display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
                                                            <input type="checkbox" class="product-checkbox custom-checkbox-input" value="<?php echo $p['id']; ?>" onchange="updateBulkActions()">
                                                            <span class="custom-checkbox"></span>
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p['brand_name'] ?? '-'); ?></td>
                                                    <td><?php echo formatPrice($p['price']); ?></td>
                                                    <td>
                                                        <?php if ($p['is_active']): ?>
                                                            <span class="badge badge-success">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inativo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="table-actions">
                                                            <button onclick="editProduct(<?php echo $p['id']; ?>)" 
                                                                    class="btn-sm btn-primary" title="Editar Produto">
                                                                <i class="fas fa-edit"></i> Editar
                                                            </button>
                                                            <button onclick="manageVariants(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>')" 
                                                                    class="btn-sm btn-outline" title="Gerenciar Variantes">
                                                                <i class="fas fa-list"></i>
                                                            </button>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ ATENÇÃO: Tem certeza que deseja excluir este produto?\n\nEsta ação não pode ser desfeita. O produto será removido permanentemente do marketplace.')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                                <input type="hidden" name="action" value="delete_product">
                                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                                <button type="submit" class="btn-sm danger" title="Excluir produto permanentemente">
                                                                    <i class="fas fa-trash"></i> Excluir
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Dynamic Variant Modal -->
    <div id="variantModal" class="modal">
        <div class="modal-content modal-md">
            <div class="modal-header">
                <h3 id="variantModalTitle"><i class="fas fa-boxes"></i> Adicionar Variante</h3>
                <button onclick="closeVariantModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="variantForm" onsubmit="event.preventDefault(); saveVariantFromModal();">
                    <div class="form-group">
                        <label class="label">
                            <i class="fas fa-tag"></i> Nome da Variante <span class="required">*</span>
                        </label>
                        <input type="text" id="variantModalName" class="form-control form-control-modern" required placeholder="Ex: 80ml, 100ml, EDP">
                    </div>
                    
                    <div class="form-group">
                        <label class="label">
                            <i class="fas fa-align-left"></i> Descrição (opcional)
                        </label>
                        <textarea id="variantModalDescription" class="form-control form-control-modern" rows="2" placeholder="Descrição da variante..."></textarea>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="label">
                                <i class="fas fa-dollar-sign"></i> Preço (R$) <span class="required">*</span>
                            </label>
                            <input type="number" id="variantModalPrice" step="0.01" min="0" class="form-control form-control-modern" required placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="label">
                                <i class="fas fa-coins"></i> Pontos (opcional)
                            </label>
                            <input type="number" id="variantModalPoints" min="0" class="form-control form-control-modern" placeholder="0" value="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="label">
                            <i class="fas fa-image"></i> Imagem (opcional)
                        </label>
                        <div id="variantImageUpload" style="margin-bottom: 10px;"></div>
                        <div class="image-upload-divider" style="margin: 10px 0;">
                            <span>OU</span>
                        </div>
                        <input type="url" id="variantModalImageUrl" class="form-control form-control-modern" placeholder="https://exemplo.com/imagem.jpg" onchange="previewVariantImage()">
                        <small class="form-hint">Cole o link da imagem da variante</small>
                        <div style="margin-top: 10px; text-align: center;">
                            <img id="variantModalImagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px; display: none; border: 1px solid var(--admin-border);">
                        </div>
                    </div>
                    
                    <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="closeVariantModal()" class="btn-outline">Cancelar</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Salvar Variante
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Variants Modal -->
    <div id="variantsModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="variantsModalTitle">Gerenciar Variantes</h3>
                <button onclick="closeModal('variantsModal')" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="variantsList"></div>
                <button onclick="openVariantForm()" class="btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Adicionar Variante
                </button>
            </div>
        </div>
    </div>

    <!-- Dynamic Variant Modal -->
    <div id="variantModal" class="modal">
        <div class="modal-content modal-md">
            <div class="modal-header">
                <h3 id="variantModalTitle"><i class="fas fa-boxes"></i> Adicionar Variante</h3>
                <button onclick="closeVariantModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="variantForm" onsubmit="event.preventDefault(); saveVariantFromModal();">
                    <div class="form-group">
                        <label class="label">
                            <i class="fas fa-tag"></i> Nome da Variante <span class="required">*</span>
                        </label>
                        <input type="text" id="variantModalName" class="form-control form-control-modern" required placeholder="Ex: 80ml, 100ml, EDP">
                    </div>
                    
                    <div class="form-group">
                        <label class="label">
                            <i class="fas fa-align-left"></i> Descrição (opcional)
                        </label>
                        <textarea id="variantModalDescription" class="form-control form-control-modern" rows="2" placeholder="Descrição da variante..."></textarea>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="label">
                                <i class="fas fa-dollar-sign"></i> Preço (R$) <span class="required">*</span>
                            </label>
                            <input type="number" id="variantModalPrice" step="0.01" min="0" class="form-control form-control-modern" required placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="label">
                                <i class="fas fa-coins"></i> Pontos (opcional)
                            </label>
                            <input type="number" id="variantModalPoints" min="0" class="form-control form-control-modern" placeholder="0" value="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="label">
                            <i class="fas fa-image"></i> Imagem (opcional)
                        </label>
                        <input type="url" id="variantModalImageUrl" class="form-control form-control-modern" placeholder="https://exemplo.com/imagem.jpg" onchange="previewVariantImage()">
                        <small class="form-hint">Cole o link da imagem da variante</small>
                        <div style="margin-top: 10px; text-align: center;">
                            <img id="variantModalImagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 8px; display: none; border: 1px solid var(--admin-border);">
                        </div>
                    </div>
                    
                    <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="closeVariantModal()" class="btn-outline">Cancelar</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Salvar Variante
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Variant Form Modal -->
    <div id="variantFormModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="variantFormTitle">Nova Variante</h3>
                <button onclick="closeVariantForm()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="variantForm" onsubmit="saveVariant(event)">
                    <input type="hidden" id="variantId" name="id" value="">
                    
                    <div class="form-group">
                        <label for="variantName">Nome da Variante *</label>
                        <input type="text" id="variantName" name="name" class="form-control" 
                               placeholder="Ex: 50ml, 100ml, 200ml" required>
                        <small class="form-text">Ex: Tamanho, volume, cor, etc.</small>
                    </div>

                    <div class="form-group">
                        <label for="variantDescription">Descrição</label>
                        <textarea id="variantDescription" name="description" class="form-control" 
                                  rows="3" placeholder="Descrição opcional da variante"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="variantPrice">Preço (R$) *</label>
                            <input type="number" id="variantPrice" name="price" class="form-control" 
                                   step="0.01" min="0" placeholder="0.00" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="variantPoints">Pontos</label>
                            <input type="number" id="variantPoints" name="points" class="form-control" 
                                   min="0" placeholder="0" value="0">
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closeVariantForm()" class="btn-outline">Cancelar</button>
                        <button type="submit" class="btn-primary">Salvar Variante</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/multiple-image-upload.js"></script>
    <script>
        let currentProductId = 0;
        let currentProductName = '';
        let multipleImageUpload;

        // Initialize multiple image upload component
        document.addEventListener('DOMContentLoaded', function() {
            multipleImageUpload = new MultipleImageUpload('productImagesUpload', {
                uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
                inputName: 'product_images',
                folder: 'products',
                maxImages: 5,
                showLinkOption: true
            });
            // Make it globally accessible
            window.multipleImageUpload = multipleImageUpload;
            console.log('MultipleImageUpload initialized:', window.multipleImageUpload);
            
            // Initialize variant image upload component (single image) - lazy initialization
            // Will be initialized when modal opens
        });
        
        // Function to initialize variant image upload component
        function initVariantImageUpload() {
            const container = document.getElementById('variantImageUpload');
            if (!container) {
                console.warn('variantImageUpload container not found');
                return;
            }
            
            // Only initialize if not already initialized
            if (window.variantImageUpload && window.variantImageUpload.container) {
                console.log('VariantImageUpload already initialized');
                return;
            }
            
            try {
                window.variantImageUpload = new MultipleImageUpload('variantImageUpload', {
                    uploadUrl: '<?php echo APP_URL; ?>/api/upload-image.php',
                    inputName: 'variant_image',
                    folder: 'products/variants',
                    maxImages: 1,
                    showLinkOption: false
                });
                console.log('VariantImageUpload initialized:', window.variantImageUpload);
            } catch (err) {
                console.error('Error initializing VariantImageUpload:', err);
            }
        }

        function manageVariants(productId, productName) {
            currentProductId = productId;
            currentProductName = productName;
            document.getElementById('variantsModalTitle').textContent = 'Variantes: ' + productName;
            document.getElementById('variantsModal').classList.add('show');
            loadVariants();
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        async function loadVariants() {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin/product-variants.php?action=list&product_id=' + currentProductId);
                const data = await response.json();
                
                if (data.success) {
                    renderVariants(data.variants);
                }
            } catch (err) {
                console.error('Error loading variants:', err);
            }
        }

        function renderVariants(variants) {
            const container = document.getElementById('variantsList');
            if (variants.length === 0) {
                container.innerHTML = '<p>Nenhuma variante cadastrada. Clique em "Adicionar Variante" para criar.</p>';
                return;
            }

            container.innerHTML = variants.map(v => `
                <div class="variant-item" data-id="${v.id}">
                    <div class="variant-details">
                        <strong>${v.name}</strong>
                        ${v.description ? '<p>' + v.description + '</p>' : ''}
                        <span class="variant-price">${formatPrice(v.price)}</span>
                        ${v.points > 0 ? '<span class="variant-points">+' + v.points + ' pts</span>' : ''}
                    </div>
                    <div class="variant-actions">
                        <button onclick="editVariant(${v.id}, '${v.name.replace(/'/g, "\\'")}', '${(v.description || '').replace(/'/g, "\\'")}', ${v.price}, ${v.points}, ${v.display_order})" class="btn-sm btn-outline">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteVariant(${v.id})" class="btn-sm btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function openVariantForm(id = null, name = '', description = '', price = '', points = 0, displayOrder = 0) {
            const form = document.getElementById('variantForm');
            const modal = document.getElementById('variantFormModal');
            const title = document.getElementById('variantFormTitle');
            
            if (id) {
                title.textContent = 'Editar Variante';
                document.getElementById('variantId').value = id;
                document.getElementById('variantName').value = name;
                document.getElementById('variantDescription').value = description || '';
                document.getElementById('variantPrice').value = price;
                document.getElementById('variantPoints').value = points || 0;
            } else {
                title.textContent = 'Nova Variante';
                form.reset();
                document.getElementById('variantId').value = '';
            }
            
            modal.classList.add('show');
        }

        function closeVariantForm() {
            document.getElementById('variantFormModal').classList.remove('show');
            document.getElementById('variantForm').reset();
        }

        function saveVariant(event) {
            event.preventDefault();
            
            const form = event.target;
            const id = document.getElementById('variantId').value;
            const name = document.getElementById('variantName').value.trim();
            const description = document.getElementById('variantDescription').value.trim();
            const price = parseFloat(document.getElementById('variantPrice').value);
            const points = parseInt(document.getElementById('variantPoints').value) || 0;
            
            if (!name) {
                alert('Nome da variante é obrigatório');
                return;
            }
            
            if (price <= 0 || isNaN(price)) {
                alert('Preço inválido');
                return;
            }

            if (id) {
                updateVariant(id, name, description, price, points, 0);
            } else {
                createVariant(name, description, price, points);
            }
        }

        function editVariant(id, name, description, price, points, displayOrder) {
            openVariantForm(id, name, description, price, points, displayOrder);
        }

        async function createVariant(name, description, price, points) {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin/product-variants.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        product_id: currentProductId,
                        name: name,
                        description: description,
                        price: price,
                        points: points
                    })
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                if (!text) {
                    throw new Error('Resposta vazia do servidor');
                }
                
                const data = JSON.parse(text);
                
                if (data.success) {
                    loadVariants();
                    closeVariantForm();
                    showNotification('Variante criada com sucesso!', 'success');
                } else {
                    alert('Erro: ' + (data.message || 'Erro desconhecido'));
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Erro ao criar variante: ' + (err.message || 'Erro de conexão'));
            }
        }

        async function updateVariant(id, name, description, price, points, displayOrder) {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin/product-variants.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update',
                        id: id,
                        name: name,
                        description: description,
                        price: price,
                        points: points,
                        display_order: displayOrder
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    loadVariants();
                    closeVariantForm();
                    showNotification('Variante atualizada com sucesso!', 'success');
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (err) {
                alert('Erro ao atualizar variante: ' + err.message);
            }
        }

        async function deleteVariant(id) {
            if (!confirm('Excluir esta variante?')) return;
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin/product-variants.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    loadVariants();
                    showNotification('Variante excluída com sucesso!', 'success');
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (err) {
                alert('Erro ao excluir variante: ' + err.message);
            }
        }

        function showNotification(message, type = 'info') {
            // Simple notification - you can enhance this later
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#22c55e' : '#3b82f6'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        function formatPrice(price) {
            return 'R$ ' + parseFloat(price).toFixed(2).replace('.', ',');
        }

        function toggleDynamicAdsCard(event) {
            // Se o clique foi no checkbox ou no label, não fazer nada (já foi tratado)
            if (event && (event.target.tagName === 'INPUT' || event.target.tagName === 'LABEL' || event.target.closest('label'))) {
                return;
            }
            const checkbox = document.getElementById('enableDynamicAds');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                toggleDynamicAds();
            }
        }
        
        function toggleDynamicAds() {
            const checkbox = document.getElementById('enableDynamicAds');
            const section = document.getElementById('dynamicAdsSection');
            if (!section) {
                console.error('dynamicAdsSection not found');
                return;
            }
            if (checkbox && checkbox.checked) {
                section.style.display = 'block';
                section.style.animation = 'slideDown 0.3s ease';
            } else {
                section.style.animation = 'slideUp 0.3s ease';
                setTimeout(() => {
                    section.style.display = 'none';
                }, 300);
            }
        }
        
        // Dynamic variants management
        let dynamicVariants = [];
        let editingVariantIndex = null;
        
        function addDynamicVariant() {
            console.log('addDynamicVariant called');
            try {
                editingVariantIndex = null;
                
                // Initialize variant image upload if not already initialized
                initVariantImageUpload();
                
                // Clear modal form
                const nameInput = document.getElementById('variantModalName');
                const descInput = document.getElementById('variantModalDescription');
                const priceInput = document.getElementById('variantModalPrice');
                const pointsInput = document.getElementById('variantModalPoints');
                const imageUrlInput = document.getElementById('variantModalImageUrl');
                const previewImg = document.getElementById('variantModalImagePreview');
                
                if (nameInput) nameInput.value = '';
                if (descInput) descInput.value = '';
                if (priceInput) priceInput.value = '';
                if (pointsInput) pointsInput.value = '0';
                if (imageUrlInput) imageUrlInput.value = '';
                if (previewImg) previewImg.style.display = 'none';
                
                // Clear image upload - wait a bit for component to be ready
                setTimeout(() => {
                    if (window.variantImageUpload && typeof window.variantImageUpload.setImages === 'function') {
                        window.variantImageUpload.setImages([], 0);
                    }
                }, 100);
                
                openVariantModal();
            } catch (err) {
                console.error('Error in addDynamicVariant:', err);
                alert('Erro ao abrir modal de variante: ' + err.message);
            }
        }
        
        // Make function globally accessible
        window.addDynamicVariant = addDynamicVariant;
        
        function editDynamicVariant(index) {
            editingVariantIndex = index;
            const variant = dynamicVariants[index];
            
            // Initialize variant image upload if not already initialized
            initVariantImageUpload();
            
            document.getElementById('variantModalName').value = variant.name || '';
            document.getElementById('variantModalDescription').value = variant.description || '';
            document.getElementById('variantModalPrice').value = variant.price || '';
            document.getElementById('variantModalPoints').value = variant.points || 0;
            document.getElementById('variantModalImageUrl').value = variant.image_path || '';
            
            // Load image in upload component if exists - wait a bit for component to be ready
            setTimeout(() => {
                if (window.variantImageUpload && typeof window.variantImageUpload.setImages === 'function' && variant.image_path) {
                    window.variantImageUpload.setImages([variant.image_path], 0);
                }
            }, 100);
            
            if (variant.image_path) {
                document.getElementById('variantModalImagePreview').src = variant.image_path;
                document.getElementById('variantModalImagePreview').style.display = 'block';
            } else {
                document.getElementById('variantModalImagePreview').style.display = 'none';
            }
            openVariantModal();
        }
        
        function openVariantModal() {
            console.log('openVariantModal called');
            const modal = document.getElementById('variantModal');
            if (!modal) {
                console.error('variantModal not found');
                alert('Erro: Modal de variante não encontrado. Recarregue a página.');
                return;
            }
            
            // Initialize variant image upload component when modal opens
            initVariantImageUpload();
            
            const title = document.getElementById('variantModalTitle');
            if (editingVariantIndex !== null) {
                title.innerHTML = '<i class="fas fa-edit"></i> Editar Variante';
            } else {
                title.innerHTML = '<i class="fas fa-boxes"></i> Adicionar Variante';
                // Clear image upload when adding new variant - wait for component to be ready
                setTimeout(() => {
                    if (window.variantImageUpload && typeof window.variantImageUpload.setImages === 'function') {
                        window.variantImageUpload.setImages([], 0);
                    }
                }, 100);
            }
            modal.classList.add('show');
            console.log('Modal should be visible now');
        }
        
        function closeVariantModal() {
            document.getElementById('variantModal').classList.remove('show');
            const form = document.getElementById('dynamicVariantForm');
            if (form) form.reset();
            const preview = document.getElementById('variantModalImagePreview');
            if (preview) preview.style.display = 'none';
            editingVariantIndex = null;
        }
        
        function saveVariantFromModal() {
            const name = document.getElementById('variantModalName').value.trim();
            const description = document.getElementById('variantModalDescription').value.trim();
            const priceInput = document.getElementById('variantModalPrice').value.trim();
            const pointsInput = document.getElementById('variantModalPoints').value.trim();
            let imageUrl = document.getElementById('variantModalImageUrl').value.trim();
            
            // Get image from upload component if available
            if (window.variantImageUpload && typeof window.variantImageUpload.getImages === 'function') {
                try {
                    const uploadedImages = window.variantImageUpload.getImages();
                    if (uploadedImages && uploadedImages.length > 0) {
                        imageUrl = uploadedImages[0];
                    }
                } catch (err) {
                    console.warn('Error getting images from upload component:', err);
                }
            }
            
            if (!name) {
                alert('Nome da variante é obrigatório');
                return;
            }
            
            if (!priceInput) {
                alert('Preço é obrigatório');
                return;
            }
            
            const price = parseFloat(priceInput.replace(',', '.'));
            if (isNaN(price) || price <= 0) {
                alert('Preço inválido');
                return;
            }
            
            const points = parseInt(pointsInput) || 0;
            
            const variant = {
                name: name,
                description: description,
                price: price,
                points: points,
                image_path: imageUrl
            };
            
            console.log('Saving variant:', variant);
            
            if (editingVariantIndex !== null) {
                dynamicVariants[editingVariantIndex] = variant;
                console.log('Updated variant at index:', editingVariantIndex);
            } else {
                dynamicVariants.push(variant);
                console.log('Added new variant. Total variants:', dynamicVariants.length);
            }
            
            renderDynamicVariants();
            closeVariantModal();
        }
        
        function previewVariantImage() {
            const url = document.getElementById('variantModalImageUrl').value.trim();
            const preview = document.getElementById('variantModalImagePreview');
            if (url) {
                preview.src = url;
                preview.style.display = 'block';
                preview.onerror = function() {
                    preview.style.display = 'none';
                };
            } else {
                preview.style.display = 'none';
            }
        }
        
        function removeDynamicVariant(index) {
            if (confirm('Remover esta variante?')) {
                dynamicVariants.splice(index, 1);
                renderDynamicVariants();
            }
        }
        
        function renderDynamicVariants() {
            const container = document.getElementById('dynamicVariantsList');
            if (!container) {
                console.error('dynamicVariantsList container not found');
                return;
            }
            
            if (dynamicVariants.length === 0) {
                container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.875rem; padding: 1rem; text-align: center; background: var(--bg-secondary); border-radius: 6px;">Nenhuma variante adicionada ainda. Clique em "Adicionar Variante" para começar.</p>';
            } else {
                container.innerHTML = dynamicVariants.map((v, index) => `
                    <div style="display: flex; gap: 12px; padding: 12px; background: var(--admin-bg-secondary); border: 1px solid var(--admin-border); border-radius: 8px; margin-bottom: 8px;">
                        ${v.image_path ? `
                        <div style="width: 60px; height: 60px; flex-shrink: 0; border-radius: 6px; overflow: hidden; background: var(--admin-bg-input);">
                            <img src="${escapeHtml(v.image_path)}" alt="${escapeHtml(v.name)}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'">
                        </div>
                        ` : ''}
                        <div style="flex: 1; min-width: 0;">
                            <strong style="display: block; margin-bottom: 4px; color: var(--admin-text-primary);">${escapeHtml(v.name)}</strong>
                            ${v.description ? '<small style="color: var(--admin-text-muted); display: block; margin-bottom: 4px;">' + escapeHtml(v.description) + '</small>' : ''}
                            <div style="display: flex; gap: 12px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                                <span style="color: var(--admin-accent); font-weight: 600;">R$ ${v.price.toFixed(2).replace('.', ',')}</span>
                                ${v.points > 0 ? '<span style="color: var(--admin-text-muted); font-size: 0.875rem;">+' + v.points + ' pts</span>' : ''}
                            </div>
                        </div>
                        <div style="display: flex; gap: 6px; align-items: center;">
                            <button type="button" onclick="editDynamicVariant(${index})" class="btn-sm btn-outline" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" onclick="removeDynamicVariant(${index})" class="btn-sm btn-danger" title="Remover">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
            
            // Re-attach event listener to button after rendering
            setTimeout(() => {
                const addBtn = document.getElementById('addVariantBtn');
                if (addBtn) {
                    // Remove existing listeners
                    const newBtn = addBtn.cloneNode(true);
                    addBtn.parentNode.replaceChild(newBtn, addBtn);
                    
                    // Add new listener
                    document.getElementById('addVariantBtn').addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Add variant button clicked');
                        addDynamicVariant();
                        return false;
                    });
                    console.log('Add variant button event listener re-attached');
                }
            }, 100);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Edit Product Function
        async function editProduct(productId) {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin/products.php?action=get&id=' + productId);
                const data = await response.json();
                
                if (data.success && data.product) {
                    const product = data.product;
                    
                    // Update form title and action
                    document.getElementById('formTitle').textContent = 'Editar Produto';
                    document.getElementById('formAction').value = 'update_product';
                    document.getElementById('productId').value = productId;
                    document.getElementById('submitBtn').textContent = 'Atualizar Produto';
                    
                    // Fill form fields
                    document.querySelector('input[name="name"]').value = product.name || '';
                    document.querySelector('select[name="brand_id"]').value = product.brand_id || '';
                    
                    // Formatar preço corretamente para o input
                    let priceValue = '';
                    if (product.price !== null && product.price !== undefined && product.price !== '') {
                        const priceNum = parseFloat(product.price);
                        if (!isNaN(priceNum)) {
                            priceValue = priceNum.toFixed(2);
                        }
                    }
                    document.querySelector('input[name="price"]').value = priceValue;
                    
                    document.querySelector('textarea[name="description"]').value = product.description || '';
                    document.querySelector('input[name="is_active"]').checked = (product.is_active == 1 || product.is_active === null);
                    document.getElementById('enableDynamicAds').checked = product.is_dynamic_ad == 1;
                    toggleDynamicAds();
                    
                    // Load product images
                    console.log('Product images data:', data.images);
                    if (data.images && data.images.length > 0) {
                        const imageUrls = data.images.map(img => img.image_url).filter(url => url && url.trim() !== '');
                        const coverIndex = data.images.findIndex(img => img.is_cover == 1);
                        console.log('Image URLs to load:', imageUrls);
                        console.log('Cover index:', coverIndex);
                        
                        // Function to set images with retry
                        const setImagesWithRetry = (retries = 10) => {
                            // Try multiple ways to access the component
                            const uploadComponent = window.multipleImageUpload_productImagesUpload || 
                                                   window.multipleImageUpload || 
                                                   multipleImageUpload;
                            
                            if (uploadComponent && typeof uploadComponent.setImages === 'function') {
                                console.log('Setting images on component:', uploadComponent);
                                uploadComponent.setImages(imageUrls, coverIndex >= 0 ? coverIndex : 0);
                                console.log('Images set successfully');
                            } else if (retries > 0) {
                                console.log('Component not ready, retrying...', retries, {
                                    'window.multipleImageUpload_productImagesUpload': window.multipleImageUpload_productImagesUpload,
                                    'window.multipleImageUpload': window.multipleImageUpload,
                                    'multipleImageUpload': multipleImageUpload
                                });
                                setTimeout(() => setImagesWithRetry(retries - 1), 200);
                            } else {
                                console.error('Failed to set images: component not available after retries');
                                alert('Erro ao carregar imagens. Por favor, recarregue a página e tente novamente.');
                            }
                        };
                        
                        // Start trying to set images
                        setImagesWithRetry();
                    } else {
                        // Clear images if no images found
                        const uploadComponent = window.multipleImageUpload_productImagesUpload || 
                                               window.multipleImageUpload || 
                                               multipleImageUpload;
                        if (uploadComponent && typeof uploadComponent.setImages === 'function') {
                            uploadComponent.setImages([], 0);
                        }
                    }
                    
                    // Load product variants
                    console.log('Product variants data:', data.variants);
                    if (data.variants && data.variants.length > 0) {
                        dynamicVariants = data.variants.map(v => ({
                            name: v.name || '',
                            description: v.description || '',
                            price: parseFloat(v.price) || 0,
                            points: parseInt(v.points) || 0,
                            image_path: v.image_path || ''
                        }));
                        console.log('Loaded variants:', dynamicVariants);
                        renderDynamicVariants();
                    } else {
                        dynamicVariants = [];
                        renderDynamicVariants();
                    }
                    
                    // Show dynamic ads section if variants exist or if dynamic ad is enabled
                    if (dynamicVariants.length > 0 || product.is_dynamic_ad == 1) {
                        const checkbox = document.getElementById('enableDynamicAds');
                        if (checkbox) {
                            checkbox.checked = true;
                            // Force show the section immediately
                            const section = document.getElementById('dynamicAdsSection');
                            if (section) {
                                section.style.display = 'block';
                                section.style.visibility = 'visible';
                                section.style.opacity = '1';
                                section.style.animation = 'none';
                                // Re-apply animation after a brief moment
                                setTimeout(() => {
                                    section.style.animation = 'slideDown 0.3s ease';
                                }, 10);
                                
                                // Ensure button is clickable
                                const addBtn = document.getElementById('addVariantBtn');
                                if (addBtn) {
                                    addBtn.style.pointerEvents = 'auto';
                                    addBtn.style.cursor = 'pointer';
                                    addBtn.style.zIndex = '10';
                                    addBtn.style.position = 'relative';
                                    console.log('Add variant button should be clickable now');
                                }
                            }
                        }
                    }
                    
                    // Scroll to form
                    document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    alert('Erro ao carregar produto: ' + (data.message || 'Produto não encontrado'));
                }
            } catch (err) {
                console.error('Error loading product:', err);
                alert('Erro ao carregar produto: ' + err.message);
            }
        }
        
        // Reset form to add mode
        function resetProductForm() {
            document.getElementById('formTitle').textContent = 'Adicionar Produto';
            document.getElementById('formAction').value = 'add_product';
            document.getElementById('productId').value = '';
            document.getElementById('submitBtn').textContent = 'Adicionar Produto';
            document.getElementById('productForm').reset();
            const uploadComponent = window.multipleImageUpload_productImagesUpload || 
                                   window.multipleImageUpload || 
                                   multipleImageUpload;
            if (uploadComponent && typeof uploadComponent.setImages === 'function') {
                uploadComponent.setImages([], 0);
            }
            dynamicVariants = [];
            renderDynamicVariants();
        }
        
        // Add variants to form before submit
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize variants list
            renderDynamicVariants();
            
            // Function to attach button listener
            function attachAddVariantListener() {
                const addVariantBtn = document.getElementById('addVariantBtn');
                if (addVariantBtn) {
                    // Remove any existing listeners by cloning
                    const newBtn = addVariantBtn.cloneNode(true);
                    addVariantBtn.parentNode.replaceChild(newBtn, addVariantBtn);
                    
                    // Attach new listener
                    document.getElementById('addVariantBtn').addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Add variant button clicked via event listener');
                        addDynamicVariant();
                        return false;
                    });
                    console.log('Add variant button event listener attached');
                } else {
                    console.warn('Add variant button not found, will retry...');
                    setTimeout(attachAddVariantListener, 500);
                }
            }
            
            // Try to attach immediately
            attachAddVariantListener();
            
            // Also try after a delay in case section is hidden
            setTimeout(attachAddVariantListener, 1000);
            
            // Bulk actions
            updateBulkActions();
            
            // Add variants to form before submit - FIXED
            const productForm = document.getElementById('productForm');
            if (productForm) {
                productForm.addEventListener('submit', function(e) {
                    // Debug: Log form data before submit
                    const formData = new FormData(productForm);
                    console.log('=== FORM SUBMIT DEBUG ===');
                    console.log('Form action:', productForm.action || 'POST');
                    console.log('Form method:', productForm.method);
                    
                    // Check product_images inputs
                    const productImagesInputs = document.querySelectorAll('input[name="product_images[]"]');
                    console.log('Product images inputs found:', productImagesInputs.length);
                    productImagesInputs.forEach((input, idx) => {
                        console.log(`  Image ${idx + 1}:`, input.value);
                    });
                    
                    // Check cover index
                    const coverIndexInput = document.querySelector('input[name="product_images_cover_index"]');
                    console.log('Cover index input:', coverIndexInput ? coverIndexInput.value : 'NOT FOUND');
                    
                    // Clear existing hidden inputs for variants
                    document.querySelectorAll('input[name^="dynamic_variants"]').forEach(input => input.remove());
                    
                    // Add variants as hidden inputs
                    if (dynamicVariants && dynamicVariants.length > 0) {
                        console.log('Preparing to add variants to form:', dynamicVariants);
                        dynamicVariants.forEach((variant, index) => {
                            const prefix = `dynamic_variants[${index}]`;
                            console.log(`Adding variant ${index}:`, variant);
                            ['name', 'description', 'price', 'points', 'image_path'].forEach(field => {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = `${prefix}[${field}]`;
                                const value = variant[field] !== undefined && variant[field] !== null ? String(variant[field]) : '';
                                input.value = value;
                                productForm.appendChild(input);
                                console.log(`  Added input: ${prefix}[${field}] = ${value}`);
                            });
                        });
                        console.log('Variantes adicionadas ao formulário:', dynamicVariants.length);
                        
                        // Verify inputs were added
                        const addedInputs = document.querySelectorAll('input[name^="dynamic_variants"]');
                        console.log('Total variant inputs in form:', addedInputs.length);
                    } else {
                        console.log('No variants to add to form');
                    }
                    
                    console.log('=== END FORM SUBMIT DEBUG ===');
                });
                
                // Reset form after successful submission
                productForm.addEventListener('submit', function(e) {
                    setTimeout(() => {
                        if (document.querySelector('.alert-success')) {
                            resetProductForm();
                        }
                    }, 2000);
                });
            } else {
                console.error('Formulário não encontrado!');
            }
        });
        
        // Bulk selection functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const count = checkboxes.length;
            const deleteBtn = document.getElementById('bulkDeleteBtn');
            const publicBtn = document.getElementById('bulkPublicBtn');
            const activateBtn = document.getElementById('bulkActivateBtn');
            const deactivateBtn = document.getElementById('bulkDeactivateBtn');
            
            console.log('Update bulk actions - checked count:', count);
            
            if (count > 0) {
                if (deleteBtn) deleteBtn.style.display = 'inline-flex';
                if (publicBtn) publicBtn.style.display = 'inline-flex';
                if (activateBtn) activateBtn.style.display = 'inline-flex';
                if (deactivateBtn) deactivateBtn.style.display = 'inline-flex';
            } else {
                if (deleteBtn) deleteBtn.style.display = 'none';
                if (publicBtn) publicBtn.style.display = 'none';
                if (activateBtn) activateBtn.style.display = 'none';
                if (deactivateBtn) deactivateBtn.style.display = 'none';
            }
        }
        
        // Add event listeners to checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.product-checkbox').forEach(cb => {
                cb.addEventListener('change', updateBulkActions);
            });
        });
        
        // Initialize Quill Editor for Product Description
        let quillEditor = null;
        document.addEventListener('DOMContentLoaded', function() {
            const editorContainer = document.getElementById('productDescriptionEditor');
            if (editorContainer) {
                quillEditor = new Quill('#productDescriptionEditor', {
                    theme: 'snow',
                    placeholder: 'Descreva o produto...',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'align': [] }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });
                
                // Sync content to hidden textarea on change
                quillEditor.on('text-change', function() {
                    const descriptionField = document.getElementById('productDescription');
                    if (descriptionField) {
                        descriptionField.value = quillEditor.root.innerHTML;
                    }
                });
                
                // Sync on form submit
                const form = document.getElementById('addProductForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const descriptionField = document.getElementById('productDescription');
                        if (descriptionField && quillEditor) {
                            descriptionField.value = quillEditor.root.innerHTML;
                        }
                    });
                }
            }
        });
        
        // Function to set editor content (for editing existing products)
        function setEditorContent(html) {
            if (quillEditor) {
                quillEditor.root.innerHTML = html || '';
            }
        }
        
        async function bulkAction(action) {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            if (checkboxes.length === 0) {
                showNotification('Selecione pelo menos um produto', 'error');
                return;
            }
            
            const productIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            const actionNames = {
                'delete': 'Excluir',
                'public': 'Tornar Públicos',
                'activate': 'Ativar',
                'deactivate': 'Desativar'
            };
            
            const actionMessages = {
                'delete': 'excluído(s)',
                'public': 'tornados públicos',
                'activate': 'ativado(s)',
                'deactivate': 'desativado(s)'
            };
            
            const actionName = actionNames[action] || action;
            if (!confirm(`Tem certeza que deseja ${actionName.toLowerCase()} ${productIds.length} produto(s)?`)) {
                return;
            }
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin/products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: `bulk_${action}`,
                        product_ids: productIds,
                        csrf_token: '<?php echo generateCsrfToken(); ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message || `${productIds.length} produto(s) ${actionMessages[action] || 'processado(s)'}!`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message || `Erro ao ${actionName.toLowerCase()}`, 'error');
                }
            } catch (err) {
                showNotification('Erro de conexão: ' + err.message, 'error');
            }
        }
    </script>
    <style>
    .variant-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-4);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-3);
    }

    .variant-details strong {
        display: block;
        margin-bottom: var(--space-1);
    }

    .variant-price {
        color: var(--accent-primary);
        font-weight: 600;
        margin-right: var(--space-2);
    }

    .variant-points {
        color: var(--text-muted);
        font-size: 0.875rem;
    }
    </style>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
