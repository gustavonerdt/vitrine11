<?php
/**
 * API para gerenciar paginas customizadas
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autorizado']);
    exit;
}

// Create table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS custom_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(100) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            meta_description TEXT,
            html_content LONGTEXT,
            css_content LONGTEXT,
            js_content LONGTEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $stmt = $pdo->prepare("
                INSERT INTO custom_pages (slug, title, meta_description, html_content, css_content, js_content, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                strtolower(preg_replace('/[^a-z0-9\-]/', '', $input['slug'])),
                $input['title'],
                $input['meta_description'] ?? '',
                $input['html_content'] ?? '',
                $input['css_content'] ?? '',
                $input['js_content'] ?? '',
                $input['is_active'] ?? 1
            ]);
            
            echo json_encode([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'message' => 'Pagina criada com sucesso!'
            ]);
            break;
            
        case 'update':
            $stmt = $pdo->prepare("
                UPDATE custom_pages SET 
                    slug = ?, title = ?, meta_description = ?,
                    html_content = ?, css_content = ?, js_content = ?,
                    is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                strtolower(preg_replace('/[^a-z0-9\-]/', '', $input['slug'])),
                $input['title'],
                $input['meta_description'] ?? '',
                $input['html_content'] ?? '',
                $input['css_content'] ?? '',
                $input['js_content'] ?? '',
                $input['is_active'] ?? 1,
                $input['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Pagina atualizada com sucesso!'
            ]);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM custom_pages WHERE id = ?");
            $stmt->execute([$input['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Pagina excluida com sucesso!'
            ]);
            break;
            
        case 'get':
            $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($page) {
                echo json_encode(['success' => true, 'page' => $page]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Pagina nao encontrada']);
            }
            break;
            
        case 'list':
            $stmt = $pdo->query("SELECT id, slug, title, is_active, created_at, updated_at FROM custom_pages ORDER BY title ASC");
            $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'pages' => $pages]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acao invalida']);
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'error' => 'Ja existe uma pagina com este slug']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
}
