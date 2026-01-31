<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auth Check
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

// Check if brands table exists
if (!db_table_exists($pdo, 'brands')) {
    echo json_encode(['success' => false, 'message' => 'Tabela brands não existe. Execute a migração.']);
    exit;
}

// Get action from request (can be in GET, POST, or JSON body)
$action = $_REQUEST['action'] ?? $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $stmt = $pdo->query("SELECT * FROM brands ORDER BY name ASC");
        $brands = $stmt->fetchAll();
        echo json_encode(['success' => true, 'brands' => $brands]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get input data (can be JSON or form-data)
        $input = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            error_log("Raw JSON input: " . $rawInput);
            $input = json_decode($rawInput, true) ?? [];
            error_log("Decoded input: " . json_encode($input));
            // Get action from JSON if not in REQUEST
            if (empty($action) && isset($input['action'])) {
                $action = $input['action'];
                error_log("Action extraído do JSON: " . $action);
            }
        } else {
            $input = $_POST;
        }
        
        error_log("Action final: " . $action);
        
        $csrfToken = $_POST['csrf_token'] ?? $input['csrf_token'] ?? '';
        
        // CSRF Check
        if (!verifyCsrfToken($csrfToken)) {
            error_log("CSRF token inválido: " . $csrfToken);
            throw new Exception('Token de segurança inválido');
        }

        if ($action === 'create') {
            $name = trim($input['name'] ?? $_POST['name'] ?? '');
            $desc = trim($input['description'] ?? $_POST['description'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : (isset($_POST['is_active']) ? 1 : 0);

            if (empty($name)) {
                throw new Exception("Nome é obrigatório");
            }

            // Check duplicate
            $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                throw new Exception("Já existe uma marca com este nome");
            }

            $stmt = $pdo->prepare("INSERT INTO brands (name, description, is_active, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $desc, $isActive]);
            
            logActivity($pdo, $_SESSION['user_id'], 'create_brand', "Criou marca: $name");
            echo json_encode(['success' => true, 'message' => 'Marca criada com sucesso']);

        } elseif ($action === 'update') {
            $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
            $name = trim($input['name'] ?? $_POST['name'] ?? '');
            $desc = trim($input['description'] ?? $_POST['description'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : (isset($_POST['is_active']) ? 1 : 0);

            if (empty($name)) {
                throw new Exception("Nome é obrigatório");
            }

            $stmt = $pdo->prepare("UPDATE brands SET name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $desc, $isActive, $id]);

            logActivity($pdo, $_SESSION['user_id'], 'update_brand', "Atualizou marca ID: $id");
            echo json_encode(['success' => true, 'message' => 'Marca atualizada']);

        } elseif ($action === 'delete') {
            $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
            
            // Check if brand has products
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                // Soft delete - just deactivate
                $stmt = $pdo->prepare("UPDATE brands SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                logActivity($pdo, $_SESSION['user_id'], 'deactivate_brand', "Desativou marca ID: $id (tem $count produtos)");
            } else {
                // Hard delete if no products
                $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
                $stmt->execute([$id]);
                logActivity($pdo, $_SESSION['user_id'], 'delete_brand', "Excluiu marca ID: $id");
            }
            
            echo json_encode(['success' => true, 'message' => 'Marca removida']);

        } elseif ($action === 'toggle') {
            $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE brands SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'toggle_brand', "Alternou status marca ID: $id");
            echo json_encode(['success' => true]);

        } elseif ($action === 'bulk_activate') {
            $ids = $input['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                throw new Exception('Lista de IDs inválida');
            }
            
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmt = $pdo->prepare("UPDATE brands SET is_active = 1, updated_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $affected = $stmt->rowCount();
            logActivity($pdo, $_SESSION['user_id'], 'bulk_activate_brands', "Ativou $affected marca(s) em massa");
            
            echo json_encode([
                'success' => true,
                'message' => "$affected marca(s) ativada(s) com sucesso",
                'affected' => $affected
            ]);

        } elseif ($action === 'bulk_deactivate') {
            $ids = $input['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                throw new Exception('Lista de IDs inválida');
            }
            
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmt = $pdo->prepare("UPDATE brands SET is_active = 0, updated_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $affected = $stmt->rowCount();
            logActivity($pdo, $_SESSION['user_id'], 'bulk_deactivate_brands', "Desativou $affected marca(s) em massa");
            
            echo json_encode([
                'success' => true,
                'message' => "$affected marca(s) desativada(s) com sucesso",
                'affected' => $affected
            ]);

        } elseif ($action === 'bulk_import') {
            error_log("=== BULK IMPORT INICIADO ===");
            error_log("Input recebido: " . json_encode($input));
            error_log("POST recebido: " . json_encode($_POST));
            error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'não definido'));
            error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
            
            $brands = $input['brands'] ?? [];
            $isActive = (int)($input['is_active'] ?? $_POST['is_active'] ?? 1);
            $description = trim($input['description'] ?? $_POST['description'] ?? '') ?: null;
            
            error_log("Brands extraído: " . json_encode($brands));
            error_log("Is Active: " . $isActive);
            error_log("Description: " . ($description ?? 'null'));
            
            if (empty($brands) || !is_array($brands)) {
                error_log("ERRO: Lista de marcas inválida ou vazia");
                throw new Exception('Lista de marcas inválida. Certifique-se de enviar um array de nomes de marcas.');
            }
            
            $created = 0;
            $skipped = 0;
            $errors = [];
            
            error_log("Total de marcas para processar: " . count($brands));
            
            $pdo->beginTransaction();
            
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM brands WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
                $stmtInsert = $pdo->prepare("INSERT INTO brands (name, description, is_active, created_at) VALUES (?, ?, ?, NOW())");
                
                foreach ($brands as $index => $brandName) {
                    $brandName = trim($brandName);
                    if (empty($brandName)) {
                        error_log("Marca #$index vazia, pulando");
                        continue;
                    }
                    
                    error_log("Processando marca #$index: '$brandName'");
                    
                    // Check if exists (case-insensitive)
                    $stmtCheck->execute([$brandName]);
                    if ($stmtCheck->fetch()) {
                        error_log("Marca '$brandName' já existe, pulando");
                        $skipped++;
                        continue;
                    }
                    
                    // Insert
                    try {
                        $stmtInsert->execute([$brandName, $description, $isActive]);
                        $created++;
                        error_log("Marca '$brandName' inserida com sucesso (ID: " . $pdo->lastInsertId() . ")");
                    } catch (PDOException $e) {
                        $errorMsg = "Erro ao inserir '$brandName': " . $e->getMessage();
                        $errors[] = $errorMsg;
                        error_log("ERRO ao inserir '$brandName': " . $e->getMessage());
                    }
                }
                
                $pdo->commit();
                error_log("Transaction commitado. Criadas: $created, Puladas: $skipped, Erros: " . count($errors));
                
                logActivity($pdo, $_SESSION['user_id'], 'bulk_import_brands', "Importou $created marca(s) em massa");
                
                $message = "$created marca(s) importada(s) com sucesso";
                if ($skipped > 0) {
                    $message .= ", $skipped já existiam";
                }
                if (!empty($errors)) {
                    $message .= ". " . count($errors) . " erro(s) ocorreram";
                }
                
                $response = [
                    'success' => true, 
                    'message' => $message,
                    'created' => $created,
                    'skipped' => $skipped,
                    'errors' => $errors
                ];
                
                error_log("Resposta JSON: " . json_encode($response));
                echo json_encode($response);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("ERRO na transaction, fazendo rollback: " . $e->getMessage());
                throw $e;
            }

        } else {
            throw new Exception("Ação não reconhecida");
        }
    }
} catch (Exception $e) {
    error_log("=== ERRO NA API ADMIN-BRANDS ===");
    error_log("Action: " . ($action ?? 'não definido'));
    error_log("Erro: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Input: " . json_encode($input ?? []));
    error_log("POST: " . json_encode($_POST));
    error_log("================================");
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
