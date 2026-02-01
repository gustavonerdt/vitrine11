<?php
// includes/functions.php
// Core Functions for Naipe da Gringa MLM System

// Ensure config is loaded
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}

// --- Security Functions ---

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// --- Auth Functions ---

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isVip() {
    return isset($_SESSION['is_vip']) && $_SESSION['is_vip'] == 1;
}

function isActive() {
    return isset($_SESSION['is_active']) && $_SESSION['is_active'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . APP_URL . "/login.php");
        exit;
    }
}

function requireAdmin() {
    if (!isLoggedIn()) {
        header("Location: " . APP_URL . "/admin/login.php");
        exit;
    }
    if (!isAdmin()) {
        http_response_code(403);
        die("Acesso Negado: Somente administradores.");
    }
}

// --- DB Schema Helpers ---

function db_has_column($pdo, $table, $column) {
    static $cache = [];
    $key = strtolower($table) . '::' . strtolower($column);
    if (isset($cache[$key])) return $cache[$key];

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        $stmt->execute([$dbName, $table, $column]);
        $has = (bool)$stmt->fetchColumn();
        $cache[$key] = $has;
        return $has;
    } catch (Exception $e) {
        error_log('db_has_column error: ' . $e->getMessage());
        $cache[$key] = false;
        return false;
    }
}

function db_table_exists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        $stmt->execute([$dbName, $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// --- Logging ---

if (!function_exists('logActivity')) {
    function logActivity($pdo, $userId, $action, $details = null) {
        if (!db_table_exists($pdo, 'activity_logs')) {
            return false;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $action, $details, $ip, $userAgent]);
            return true;
        } catch (PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
}

// --- User Functions ---

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    try {
        // Verificar quais colunas existem antes de fazer SELECT *
        $columns = ['id', 'name', 'email', 'role', 'is_active'];
        $selectCols = implode(', ', $columns);
        
        $stmt = $pdo->prepare("SELECT $selectCols FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("getCurrentUser error: " . $e->getMessage());
        return null;
    }
}

function getUserById($pdo, $id) {
    try {
        // Verificar quais colunas existem antes de fazer SELECT *
        $columns = ['id', 'name', 'email', 'role', 'is_active'];
        $selectCols = implode(', ', $columns);
        
        $stmt = $pdo->prepare("SELECT $selectCols FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("getUserById error: " . $e->getMessage());
        return null;
    }
}

function generateReferralCode($userId) {
    return 'NPC' . str_pad($userId, 6, '0', STR_PAD_LEFT);
}

function getUserByReferralCode($pdo, $code) {
    try {
        // Verificar quais colunas existem antes de fazer SELECT *
        $columns = ['id', 'name', 'email', 'role', 'is_active', 'referral_code'];
        $selectCols = implode(', ', $columns);
        
        $stmt = $pdo->prepare("SELECT $selectCols FROM users WHERE referral_code = ? AND is_active = 1");
        $stmt->execute([$code]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("getUserByReferralCode error: " . $e->getMessage());
        return null;
    }
}

// --- MLM Functions ---

function getDirectReferrals($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   COALESCE(SUM(c.amount), 0) as commission_generated
            FROM users u
            LEFT JOIN commissions c ON c.from_user_id = u.id AND c.user_id = ?
            WHERE u.referred_by = ?
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getSecondLevelReferrals($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT u2.*, u1.name as referred_through,
                   COALESCE(SUM(c.amount), 0) as commission_generated
            FROM users u1
            JOIN users u2 ON u2.referred_by = u1.id
            LEFT JOIN commissions c ON c.from_user_id = u2.id AND c.user_id = ?
            WHERE u1.referred_by = ?
            GROUP BY u2.id
            ORDER BY u2.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getTotalReferralsCount($pdo, $userId) {
    $n1 = count(getDirectReferrals($pdo, $userId));
    $n2 = count(getSecondLevelReferrals($pdo, $userId));
    return ['n1' => $n1, 'n2' => $n2, 'total' => $n1 + $n2];
}

// --- Commission Functions ---

function calculateCommission($pdo, $orderId, $userId, $amount) {
    try {
        $user = getUserById($pdo, $userId);
        if (!$user) {
            error_log("calculateCommission: User not found - ID: $userId");
            return false;
        }
        
        // Get order to check package
        $stmt = $pdo->prepare("SELECT package_id, product_id FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            error_log("calculateCommission: Order not found - ID: $orderId");
            return false;
        }
        
        // Check if package generates commission
        $generatesCommission = false;
        if ($order['package_id']) {
            $stmt = $pdo->prepare("SELECT generates_commission FROM packages WHERE id = ?");
            $stmt->execute([$order['package_id']]);
            $package = $stmt->fetch();
            if ($package && isset($package['generates_commission']) && $package['generates_commission'] == 1) {
                $generatesCommission = true;
            }
        }
        
        // If package doesn't generate commission, skip
        if (!$generatesCommission) {
            error_log("calculateCommission: Package does not generate commission - Order ID: $orderId");
            return false;
        }
        
        // Get commission percentages from settings
        $n1Percent = floatval(getSetting($pdo, 'commission_n1_percent', 40));
        $n2Percent = floatval(getSetting($pdo, 'commission_n2_percent', 10));
        $retentionDays = intval(getSetting($pdo, 'retention_days', 30));
        
        if ($n1Percent <= 0 && $n2Percent <= 0) {
            error_log("calculateCommission: Commission percentages are zero or invalid");
            return false;
        }
        
        $retainedUntil = date('Y-m-d H:i:s', strtotime("+$retentionDays days"));
        
        // N1 Commission (upline direto)
        if (!empty($user['referred_by'])) {
            $n1Upline = getUserById($pdo, $user['referred_by']);
            
            // Check if N1 upline is active
            if ($n1Upline && ($n1Upline['office_status'] ?? 'inactive') === 'active') {
                $n1Amount = $amount * ($n1Percent / 100);
                if ($n1Amount > 0) {
                    $result = createCommission($pdo, $user['referred_by'], $userId, $orderId, 1, $n1Percent, $n1Amount, $retainedUntil);
                    if ($result) {
                        error_log("calculateCommission: N1 commission created - Upline ID: {$user['referred_by']}, Amount: $n1Amount");
                    }
                }
                
                // N2 Commission (upline do upline)
                if (!empty($n1Upline['referred_by'])) {
                    $n2Upline = getUserById($pdo, $n1Upline['referred_by']);
                    
                    // Check if N2 upline is active
                    if ($n2Upline && ($n2Upline['office_status'] ?? 'inactive') === 'active') {
                        $n2Amount = $amount * ($n2Percent / 100);
                        if ($n2Amount > 0) {
                            $result = createCommission($pdo, $n1Upline['referred_by'], $userId, $orderId, 2, $n2Percent, $n2Amount, $retainedUntil);
                            if ($result) {
                                error_log("calculateCommission: N2 commission created - Upline ID: {$n1Upline['referred_by']}, Amount: $n2Amount");
                            }
                        }
                    } else {
                        error_log("calculateCommission: N2 upline is not active - ID: {$n1Upline['referred_by']}");
                    }
                }
            } else {
                error_log("calculateCommission: N1 upline is not active - ID: {$user['referred_by']}");
            }
        } else {
            error_log("calculateCommission: User has no referred_by - User ID: $userId");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("calculateCommission error: " . $e->getMessage());
        return false;
    }
}

function createCommission($pdo, $userId, $fromUserId, $orderId, $level, $percentage, $amount, $retainedUntil) {
    try {
        // Check if retained_until column exists
        $hasRetainedUntil = db_has_column($pdo, 'commissions', 'retained_until');
        
        if ($hasRetainedUntil) {
            $stmt = $pdo->prepare("
                INSERT INTO commissions (user_id, from_user_id, order_id, level, percentage, amount, status, retained_until, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'retained', ?, NOW())
            ");
            $stmt->execute([$userId, $fromUserId, $orderId, $level, $percentage, $amount, $retainedUntil]);
        } else {
            // Fallback if column doesn't exist
            $stmt = $pdo->prepare("
                INSERT INTO commissions (user_id, from_user_id, order_id, level, percentage, amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'retained', NOW())
            ");
            $stmt->execute([$userId, $fromUserId, $orderId, $level, $percentage, $amount]);
        }
        
        // Add to retained balance
        $updateStmt = $pdo->prepare("UPDATE users SET balance_retained = balance_retained + ?, total_accumulated = total_accumulated + ? WHERE id = ?");
        $updateStmt->execute([$amount, $amount, $userId]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Commission error: " . $e->getMessage());
        return false;
    }
}

function releaseRetainedCommissions($pdo) {
    try {
        // Check if retained_until column exists
        $hasRetainedUntil = db_has_column($pdo, 'commissions', 'retained_until');
        
        // Get commissions ready to release
        if ($hasRetainedUntil) {
            $stmt = $pdo->prepare("
                SELECT * FROM commissions 
                WHERE status = 'retained' AND retained_until <= NOW()
            ");
        } else {
            // Fallback: release all retained commissions if column doesn't exist
            $stmt = $pdo->prepare("
                SELECT * FROM commissions 
                WHERE status = 'retained'
            ");
        }
        $stmt->execute();
        $commissions = $stmt->fetchAll();
        
        foreach ($commissions as $commission) {
            // Move from retained to available
            $updateUser = $pdo->prepare("
                UPDATE users 
                SET balance_available = balance_available + ?,
                    balance_retained = balance_retained - ?
                WHERE id = ?
            ");
            $updateUser->execute([$commission['amount'], $commission['amount'], $commission['user_id']]);
            
            // Update commission status
            $updateCommission = $pdo->prepare("UPDATE commissions SET status = 'released', released_at = NOW() WHERE id = ?");
            $updateCommission->execute([$commission['id']]);
        }
        
        return count($commissions);
    } catch (PDOException $e) {
        error_log("Release commissions error: " . $e->getMessage());
        return false;
    }
}

// --- Points Functions ---

function addPoints($pdo, $userId, $orderId, $amount, $origin = 'purchase') {
    try {
        $user = getUserById($pdo, $userId);
        $newBalance = ($user['points_available'] ?? 0) + $amount;
        
        $stmt = $pdo->prepare("
            INSERT INTO point_transactions (user_id, order_id, type, origin, amount, balance_after, status, created_at)
            VALUES (?, ?, 'earned', ?, ?, ?, 'released', NOW())
        ");
        $stmt->execute([$userId, $orderId, $origin, $amount, $newBalance]);
        
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET points_accumulated = points_accumulated + ?,
                points_available = points_available + ?
            WHERE id = ?
        ");
        $updateStmt->execute([$amount, $amount, $userId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Add points error: " . $e->getMessage());
        return false;
    }
}

function getPointsHistory($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM point_transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// --- Financial Functions ---

function getUserFinancialSummary($pdo, $userId) {
    // Buscar dados atualizados diretamente do banco
    // Verificar quais colunas existem antes de fazer SELECT *
    $columns = ['id', 'balance_available', 'balance_pending', 'total_earnings'];
    $selectCols = implode(', ', $columns);
    
    $stmt = $pdo->prepare("SELECT $selectCols FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) return null;
    
    // Get pending withdrawals
    $pendingStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as pending FROM withdrawals WHERE user_id = ? AND status IN ('pending', 'processing')");
    $pendingStmt->execute([$userId]);
    $pendingWithdrawals = $pendingStmt->fetch()['pending'];
    
    return [
        'balance_available' => floatval($user['balance_available'] ?? 0),
        'balance_retained' => floatval($user['balance_retained'] ?? 0),
        'total_invested' => floatval($user['total_invested'] ?? 0),
        'total_received' => floatval($user['total_received'] ?? 0),
        'total_accumulated' => floatval($user['total_accumulated'] ?? 0),
        'points_accumulated' => intval($user['points_accumulated'] ?? 0),
        'points_available' => intval($user['points_available'] ?? 0),
        'pending_withdrawals' => floatval($pendingWithdrawals)
    ];
}

function requestWithdrawal($pdo, $userId, $amount, $method, $pixKeyType = null, $pixKey = null, $bankName = null, $bankAgency = null, $bankAccount = null, $pixFullName = null, $pixCpf = null, $pixBankName = null, $tedFullName = null, $tedCpf = null) {
    $user = getUserById($pdo, $userId);
    
    if (!$user) {
        return ['success' => false, 'error' => 'Usuário não encontrado'];
    }
    
    $minAmount = getSetting($pdo, 'min_withdrawal_amount', 50);
    $feePercent = getSetting($pdo, 'withdrawal_fee_percent', 0);
    
    if ($amount < $minAmount) {
        return ['success' => false, 'error' => "Valor mínimo para saque é R$ " . number_format($minAmount, 2, ',', '.')];
    }
    
    $currentBalance = floatval($user['balance_available'] ?? 0);
    if ($amount > $currentBalance) {
        return ['success' => false, 'error' => 'Saldo insuficiente. Saldo disponível: R$ ' . number_format($currentBalance, 2, ',', '.')];
    }
    
    $fee = $amount * ($feePercent / 100);
    $netAmount = $amount - $fee;
    
    try {
        $pdo->beginTransaction();
        
        // Verificar saldo novamente antes de deduzir (evitar race condition)
        $checkStmt = $pdo->prepare("SELECT balance_available FROM users WHERE id = ? FOR UPDATE");
        $checkStmt->execute([$userId]);
        $checkUser = $checkStmt->fetch();
        $checkBalance = floatval($checkUser['balance_available'] ?? 0);
        
        if ($amount > $checkBalance) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Saldo insuficiente. Saldo disponível: R$ ' . number_format($checkBalance, 2, ',', '.')];
        }
        
        // Deduct from available balance
        $newBalance = $checkBalance - $amount;
        if ($newBalance < 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Erro: O saldo não pode ficar negativo'];
        }
        
        $updateStmt = $pdo->prepare("UPDATE users SET balance_available = ? WHERE id = ?");
        $updateStmt->execute([$newBalance, $userId]);
        $rowsAffected = $updateStmt->rowCount();
        
        if ($rowsAffected === 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Erro ao atualizar saldo do usuário'];
        }
        
        // Create withdrawal
        $stmt = $pdo->prepare("
            INSERT INTO withdrawals (user_id, amount, fee, net_amount, method, pix_key_type, pix_key, bank_name, bank_agency, bank_account, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $userId, $amount, $fee, $netAmount, $method, 
            $pixKeyType ?? $user['pix_key_type'], 
            $pixKey ?? $user['pix_key'],
            $bankName ?? $user['bank_name'] ?? null,
            $bankAgency ?? $user['bank_agency'] ?? null,
            $bankAccount ?? $user['bank_account'] ?? null
        ]);
        
        $withdrawalId = $pdo->lastInsertId();
        
        if (!$withdrawalId) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Erro ao criar registro de saque'];
        }
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Solicitação de saque enviada com sucesso', 'withdrawal_id' => $withdrawalId];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Withdrawal error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao processar solicitação'];
    }
}

function getWithdrawalHistory($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT w.*, 
                   (SELECT file_path FROM withdrawal_receipts WHERE withdrawal_id = w.id LIMIT 1) as receipt
            FROM withdrawals w
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// --- Settings Functions ---

function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function updateSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$key, $value, $value]);
        
        // Se for logo_url, atualizar também o .env
        if ($key === 'logo_url') {
            updateEnvFile('LOGO_URL', $value);
        }
        
        // Se for app_name, atualizar também o .env
        if ($key === 'app_name') {
            updateEnvFile('APP_NAME', $value);
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function updateEnvFile($key, $value) {
    try {
        $envPath = __DIR__ . '/../.env';
        $envExamplePath = __DIR__ . '/../.env.example';
        
        // Se .env não existe, criar a partir do .env.example se existir
        if (!file_exists($envPath)) {
            if (file_exists($envExamplePath)) {
                copy($envExamplePath, $envPath);
            } else {
                // Criar arquivo .env vazio
                file_put_contents($envPath, '');
            }
        }
        
        // Ler conteúdo do .env
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
        $lines = explode("\n", $envContent);
        $updated = false;
        $newLines = [];
        
        // Procurar e atualizar a chave
        foreach ($lines as $line) {
            $originalLine = $line;
            $line = trim($line);
            
            // Ignorar linhas vazias e comentários
            if (empty($line) || strpos($line, '#') === 0) {
                $newLines[] = $originalLine;
                continue;
            }
            
            // Verificar se é a chave que queremos atualizar (pode ter espaços antes ou depois do =)
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                // Se o valor contém espaços ou caracteres especiais, usar aspas
                $formattedValue = $value;
                if (preg_match('/[\s#"]/', $value)) {
                    $formattedValue = '"' . addslashes($value) . '"';
                }
                $newLines[] = $key . '=' . $formattedValue;
                $updated = true;
            } else {
                $newLines[] = $originalLine;
            }
        }
        
        // Se não encontrou, adicionar no final
        if (!$updated) {
            $formattedValue = $value;
            if (preg_match('/[\s#"]/', $value)) {
                $formattedValue = '"' . addslashes($value) . '"';
            }
            $newLines[] = $key . '=' . $formattedValue;
        }
        
        // Escrever de volta no arquivo
        file_put_contents($envPath, implode("\n", $newLines));
        return true;
    } catch (Exception $e) {
        error_log("Error updating .env file: " . $e->getMessage());
        return false;
    }
}

// --- Package Functions ---

function getActivePackages($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE is_active = 1 ORDER BY display_order ASC, price ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getPackageById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function getPackageFeatures($pdo, $packageId) {
    if (!db_table_exists($pdo, 'package_features')) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM package_features WHERE package_id = ? ORDER BY display_order ASC, id ASC");
        $stmt->execute([$packageId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// --- Product Functions ---

function getActiveProducts($pdo, $categoryId = null, $limit = null) {
    try {
        // Verificar se a coluna description existe
        $hasDescription = db_has_column($pdo, 'products', 'description');
        
        // Construir query com colunas específicas
        $columns = "p.id, p.brand_id, p.name, p.price, p.image_path, p.is_vip, p.is_dynamic_ad, p.is_active, p.created_at, p.updated_at";
        if ($hasDescription) {
            $columns .= ", p.description";
        }
        
        $sql = "
            SELECT $columns, b.name as brand_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.is_active = 1
        ";
        $params = [];
        
        // Check if category_id column exists before using it
        if ($categoryId && db_has_column($pdo, 'products', 'category_id')) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        // Check if display_order column exists
        if (db_has_column($pdo, 'products', 'display_order')) {
            $sql .= " ORDER BY p.display_order ASC, p.name ASC";
        } else {
            $sql .= " ORDER BY p.name ASC";
        }
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Garantir que description existe no array mesmo se a coluna não existir
        if (!$hasDescription) {
            foreach ($products as &$product) {
                $product['description'] = null;
            }
            unset($product);
        }
        
        // Add points field if it doesn't exist (default to 0)
        foreach ($products as &$product) {
            if (!isset($product['points'])) {
                $product['points'] = 0;
            }
        }
        
        return $products;
    } catch (PDOException $e) {
        error_log("getActiveProducts error: " . $e->getMessage());
        return [];
    }
}

function getCategories($pdo) {
    try {
        // Check if categories table exists
        if (!db_table_exists($pdo, 'categories')) {
            return [];
        }
        
        $sql = "SELECT * FROM categories WHERE is_active = 1";
        
        // Check if display_order column exists
        if (db_has_column($pdo, 'categories', 'display_order')) {
            $sql .= " ORDER BY display_order ASC";
        } else {
            $sql .= " ORDER BY name ASC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// --- Order Functions ---

function createOrder($pdo, $userId, $type, $packageId, $amount, $points, $paymentMethod = 'pix') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, order_type, package_id, total_amount, total_points, payment_method, payment_status, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
        ");
        $stmt->execute([$userId, $type, $packageId, $amount, $points, $paymentMethod]);
        $orderId = $pdo->lastInsertId();
        
        // Create invoice
        $invoiceStmt = $pdo->prepare("
            INSERT INTO invoices (order_id, user_id, amount, points, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $invoiceStmt->execute([$orderId, $userId, $amount, $points]);
        
        return $orderId;
    } catch (PDOException $e) {
        error_log("Order error: " . $e->getMessage());
        return false;
    }
}

function getUserOrders($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, p.name as package_name, p.image_path as package_image
            FROM orders o
            LEFT JOIN packages p ON o.package_id = p.id
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// --- Utilities ---

function formatPrice($price) {
    if ($price === null || $price === '') {
        return 'R$ 0,00';
    }
    $price = floatval($price);
    return 'R$ ' . number_format($price, 2, ',', '.');
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . ' dias atrás';
    return date('d/m/Y', $time);
}

function getOfficeStatusBadge($status) {
    $badges = [
        'inactive' => '<span class="badge badge-danger">Inativo</span>',
        'pending' => '<span class="badge badge-warning">Pendente</span>',
        'active' => '<span class="badge badge-success">Ativo</span>'
    ];
    return $badges[$status] ?? $badges['inactive'];
}

function getPaymentStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pendente</span>',
        'paid' => '<span class="badge badge-success">Pago</span>',
        'cancelled' => '<span class="badge badge-danger">Cancelado</span>',
        'refunded' => '<span class="badge badge-info">Reembolsado</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

function getWithdrawalStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pendente</span>',
        'processing' => '<span class="badge badge-info">Processando</span>',
        'approved' => '<span class="badge badge-primary">Aprovado</span>',
        'rejected' => '<span class="badge badge-danger">Rejeitado</span>',
        'paid' => '<span class="badge badge-success">Pago</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

// --- WhatsApp ---

function getWhatsAppNumber($pdo) {
    return getSetting($pdo, 'whatsapp_number', defined('WHATSAPP_DEFAULT_NUMBER') ? WHATSAPP_DEFAULT_NUMBER : '5511999999999');
}

function getWhatsAppDefaultMessage($pdo) {
    return getSetting($pdo, 'default_message', defined('WHATSAPP_DEFAULT_MESSAGE') ? WHATSAPP_DEFAULT_MESSAGE : 'Olá! Quero comprar: ');
}

function generateWhatsAppLink($pdo, $productName) {
    $whatsappNumber = getWhatsAppNumber($pdo);
    $message = getWhatsAppDefaultMessage($pdo) . $productName;
    $encodedMessage = urlencode($message);
    return "https://wa.me/$whatsappNumber?text=$encodedMessage";
}

// --- Rate Limiting ---

function checkLoginAttempts($pdo, $email) {
    if (!db_table_exists($pdo, 'activity_logs')) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM activity_logs WHERE action = 'Login Failed' AND details LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute(["%$email%"]);
        $result = $stmt->fetch();
        return $result['attempts'] < (defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5);
    } catch (PDOException $e) {
        return true;
    }
}

// --- Banner Functions ---

/**
 * Get banners for a specific page and user status
 */
function getBannersForPage($pdo, $page, $userStatus = 'inactive') {
    if (!db_table_exists($pdo, 'banners')) {
        return [];
    }
    
    try {
        // Get all active banners (including split banners)
        $stmt = $pdo->prepare("
            SELECT * FROM banners 
            WHERE is_active = 1 
            AND (target_user_status = 'all' OR target_user_status = ?)
            ORDER BY display_order ASC, created_at DESC
        ");
        $stmt->execute([$userStatus]);
        $allBanners = $stmt->fetchAll();
        
        // Ensure type field exists for backward compatibility
        foreach ($allBanners as &$banner) {
            if (!isset($banner['type']) && isset($banner['display_type'])) {
                $banner['type'] = $banner['display_type'];
            }
        }
        unset($banner);
        
        // Filter by target pages
        $banners = [];
        foreach ($allBanners as $banner) {
            $targetPages = json_decode($banner['target_pages'] ?? '[]', true) ?: [];
            if (in_array($page, $targetPages)) {
                $banner['target_pages'] = $targetPages;
                $banners[] = $banner;
            }
        }
        
        return $banners;
    } catch (PDOException $e) {
        error_log("Banners fetch error: " . $e->getMessage());
        return [];
    }
}

/**
 * Render banner HTML
 */
function renderBanner($banner, $page = '') {
    $html = '';
    $style = '';
    $imgStyle = '';
    
    if ($banner['background_color']) {
        $style .= 'background-color: ' . htmlspecialchars($banner['background_color']) . '; ';
    }
    
    if (!empty($banner['width'])) {
        $imgStyle .= 'width: ' . htmlspecialchars($banner['width']) . '; ';
    }
    
    if (!empty($banner['height'])) {
        $imgStyle .= 'height: ' . htmlspecialchars($banner['height']) . '; ';
    }
    
    $displayType = $banner['type'] ?? $banner['display_type'] ?? 'banner';
    
    if ($displayType === 'split') {
        // Split banner (divided banner)
        $html .= '<div class="banner-split-container" id="banner-split-' . $banner['id'] . '">';
        $html .= '<div class="banner-split-wrapper">';
        
        // Left side (Escritório Virtual)
        $leftLink = !empty($banner['split_left_link']) ? htmlspecialchars($banner['split_left_link']) : '';
        $leftImage = !empty($banner['split_left_image']) ? htmlspecialchars($banner['split_left_image']) : '';
        $leftText = !empty($banner['split_left_text']) ? htmlspecialchars($banner['split_left_text']) : 'Escritório Virtual';
        $leftTextPosition = !empty($banner['split_left_text_position']) ? htmlspecialchars($banner['split_left_text_position']) : 'center-center';
        
        $html .= '<div class="banner-split-side banner-split-left">';
        if ($leftLink) {
            $html .= '<a href="' . $leftLink . '" class="banner-split-link">';
        }
        if ($leftImage) {
            $html .= '<img src="' . $leftImage . '" alt="' . $leftText . '" class="banner-split-image">';
        }
        if ($leftText) {
            $positionClass = 'text-' . str_replace('-', '-', $leftTextPosition);
            $html .= '<div class="banner-split-overlay ' . $positionClass . '"><span class="banner-split-text">' . $leftText . '</span></div>';
        }
        if ($leftLink) {
            $html .= '</a>';
        }
        $html .= '</div>';
        
        // Right side (Perfumes)
        $rightLink = !empty($banner['split_right_link']) ? htmlspecialchars($banner['split_right_link']) : '';
        $rightImage = !empty($banner['split_right_image']) ? htmlspecialchars($banner['split_right_image']) : '';
        $rightText = !empty($banner['split_right_text']) ? htmlspecialchars($banner['split_right_text']) : 'Perfumes';
        $rightTextPosition = !empty($banner['split_right_text_position']) ? htmlspecialchars($banner['split_right_text_position']) : 'center-center';
        
        $html .= '<div class="banner-split-side banner-split-right">';
        if ($rightLink) {
            $html .= '<a href="' . $rightLink . '" class="banner-split-link">';
        }
        if ($rightImage) {
            $html .= '<img src="' . $rightImage . '" alt="' . $rightText . '" class="banner-split-image">';
        }
        if ($rightText) {
            $positionClass = 'text-' . str_replace('-', '-', $rightTextPosition);
            $html .= '<div class="banner-split-overlay ' . $positionClass . '"><span class="banner-split-text">' . $rightText . '</span></div>';
        }
        if ($rightLink) {
            $html .= '</a>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
    } else if ($displayType === 'popup') {
        // Pop-up modal
        $html .= '<div class="banner-popup" id="banner-popup-' . $banner['id'] . '" style="display: none;">';
        $html .= '<div class="banner-popup-overlay" onclick="closeBannerPopup(' . $banner['id'] . ')"></div>';
        $html .= '<div class="banner-popup-content" style="' . $style . '">';
        $html .= '<button class="banner-popup-close" onclick="closeBannerPopup(' . $banner['id'] . ')">&times;</button>';
        
        if ($banner['image_url']) {
            $linkStart = $banner['link_url'] ? '<a href="' . htmlspecialchars($banner['link_url']) . '" target="_blank">' : '';
            $linkEnd = $banner['link_url'] ? '</a>' : '';
            $html .= $linkStart;
            $imgStyle = 'display: block;';
            if (!empty($banner['width'])) {
                $imgStyle .= ' width: ' . htmlspecialchars($banner['width']) . ';';
            } else {
                $imgStyle .= ' width: 100%;';
            }
            if (!empty($banner['height'])) {
                $imgStyle .= ' height: ' . htmlspecialchars($banner['height']) . ';';
            } else {
                $imgStyle .= ' height: auto;';
            }
            $html .= '<img src="' . htmlspecialchars($banner['image_url']) . '" alt="' . htmlspecialchars($banner['name']) . '" style="' . $imgStyle . '">';
            $html .= $linkEnd;
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        // Auto-show script
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const popup = document.getElementById("banner-popup-' . $banner['id'] . '");
                if (popup && !sessionStorage.getItem("banner-closed-' . $banner['id'] . '")) {
                    setTimeout(function() {
                        popup.style.display = "flex";
                    }, 500);
                }
            });
            function closeBannerPopup(id) {
                const popup = document.getElementById("banner-popup-" + id);
                if (popup) {
                    popup.style.display = "none";
                    sessionStorage.setItem("banner-closed-" + id, "true");
                }
            }
        </script>';
    } else {
        // Regular banner
        $html .= '<div class="banner-container" id="banner-' . $banner['id'] . '" style="' . $style . '">';
        
        if ($banner['image_url']) {
            $linkStart = $banner['link_url'] ? '<a href="' . htmlspecialchars($banner['link_url']) . '" class="banner-link">' : '';
            $linkEnd = $banner['link_url'] ? '</a>' : '';
            $html .= $linkStart;
            $imgStyle = '';
            if (!empty($banner['width'])) {
                $imgStyle .= ' width: ' . htmlspecialchars($banner['width']) . ';';
            }
            if (!empty($banner['height'])) {
                $imgStyle .= ' height: ' . htmlspecialchars($banner['height']) . ';';
            }
            $html .= '<img src="' . htmlspecialchars($banner['image_url']) . '" alt="' . htmlspecialchars($banner['name']) . '" class="banner-image"' . ($imgStyle ? ' style="' . $imgStyle . '"' : '') . '>';
            $html .= $linkEnd;
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Render all banners for a page
 */
function renderBanners($pdo, $page, $userStatus = 'inactive') {
    $banners = getBannersForPage($pdo, $page, $userStatus);
    $html = '';
    
    foreach ($banners as $banner) {
        $html .= renderBanner($banner, $page);
    }
    
    return $html;
}

/**
 * Get carousel banners for a specific brand (or all if no brand specified)
 */
function getCarouselBanners($pdo, $brandId = null) {
    if (!db_table_exists($pdo, 'banners')) {
        return [];
    }
    
    try {
        if ($brandId) {
            // Get carousels for specific brand OR carousels without brand filter
            $stmt = $pdo->prepare("
                SELECT * FROM banners 
                WHERE is_active = 1 
                AND carousel_type = 'carousel'
                AND (target_brand_id = ? OR target_brand_id IS NULL)
                ORDER BY display_order ASC, created_at DESC
            ");
            $stmt->execute([$brandId]);
        } else {
            // Get carousels without brand filter
            $stmt = $pdo->prepare("
                SELECT * FROM banners 
                WHERE is_active = 1 
                AND carousel_type = 'carousel'
                AND target_brand_id IS NULL
                ORDER BY display_order ASC, created_at DESC
            ");
            $stmt->execute();
        }
        
        $banners = $stmt->fetchAll();
        
        // Parse carousel_images JSON and filter empty carousels
        $validBanners = [];
        foreach ($banners as &$banner) {
            if (!empty($banner['carousel_images'])) {
                $banner['carousel_images'] = json_decode($banner['carousel_images'], true) ?: [];
            } else {
                $banner['carousel_images'] = [];
            }
            
            // Only include banners with at least one image
            if (!empty($banner['carousel_images']) && count($banner['carousel_images']) > 0) {
                $validBanners[] = $banner;
            }
        }
        unset($banner);
        
        // Return only the first banner (limit 1)
        return !empty($validBanners) ? [array_slice($validBanners, 0, 1)[0]] : [];
    } catch (PDOException $e) {
        error_log("Carousel banners fetch error: " . $e->getMessage());
        return [];
    }
}

/**
 * Render carousel banner HTML using Swiper.js (based on provided example)
 */
function renderCarousel($banner) {
    if (empty($banner['carousel_images']) || count($banner['carousel_images']) === 0) {
        return '';
    }
    
    $carouselId = 'carousel-' . $banner['id'];
    $images = $banner['carousel_images'];
    
    // Limit to 4 images
    $images = array_slice($images, 0, 4);
    
    // Get colors from settings
    global $pdo;
    $paginationColor = getSetting($pdo, 'carousel_pagination_color', '#FFFFFF');
    $arrowColor = getSetting($pdo, 'carousel_arrow_color', '#FFFFFF');
    
    // Check if there are multiple images (same or different)
    $hasMultipleImages = count($images) > 1;
    
    // Check if Swiper is already loaded
    static $swiperLoaded = false;
    $loadSwiper = !$swiperLoaded;
    $swiperLoaded = true;
    
    $html = '';
    
    // Load Swiper CSS and JS only once
    if ($loadSwiper) {
        $html .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />';
        $html .= '<script src="https://cdn.tailwindcss.com"></script>';
    }
    
    $html .= '<style>
        #' . $carouselId . ' {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        #' . $carouselId . ' .swiper-slide {
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        #' . $carouselId . ' .swiper-slide-active {
            animation: slideInActive 0.6s ease-out;
        }
        
        @keyframes slideInActive {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        #' . $carouselId . ' .swiper-pagination-bullet {
            background: ' . htmlspecialchars($paginationColor) . ' !important;
            width: 15px !important;
            height: 3px !important;
            border-radius: 99px !important;
            opacity: 0.5;
            transition: all 0.3s ease;
        }

        #' . $carouselId . ' .swiper-pagination-bullet-active {
            opacity: 1;
            width: 30px !important;
            animation: pulseBullet 0.3s ease-out;
        }
        
        @keyframes pulseBullet {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        #' . $carouselId . ' .hero-prev-button, 
        #' . $carouselId . ' .hero-next-button {
            color: ' . htmlspecialchars($arrowColor) . ' !important;
            filter: drop-shadow(0 3px 3px rgba(0,0,0,0.5));
            opacity: 1 !important;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 50%;
            padding: 8px;
            width: 40px !important;
            height: 40px !important;
        }
        
        #' . $carouselId . ' .hero-prev-button:active, 
        #' . $carouselId . ' .hero-next-button:active {
            transform: scale(0.95);
        }
    </style>';
    
    $html .= '<section class="group/hero relative max-w-[1324px] mx-auto">';
    $html .= '<div class="swiper mySwiper md:rounded-lg overflow-hidden">';
    $html .= '<div class="swiper-wrapper">';
    
    // Generate slides for each image (up to 4)
    foreach ($images as $index => $imageData) {
        // Desktop / default image
        $desktopImageUrl = $imageData['image_url'] ?? $imageData['image_url_desktop'] ?? '';
        // Optional mobile-specific image
        $mobileImageUrl = $imageData['mobile_image_url'] ?? $imageData['image_url_mobile'] ?? $desktopImageUrl;

        // Fallback if for algum motivo não tiver nem desktop nem mobile
        $imageUrl = $desktopImageUrl ?: $mobileImageUrl;

        $productId = $imageData['product_id'] ?? $banner['target_product_id'] ?? null;
        
        // Build link URL
        $linkUrl = '#';
        if ($productId) {
            $linkUrl = APP_URL . '/product.php?id=' . (int)$productId;
        } elseif (!empty($banner['link_url'])) {
            $linkUrl = $banner['link_url'];
        } else {
            // Default link if provided
            $linkUrl = 'https://raspagreen.com/raspadinhas';
        }
        
        $html .= '<div class="swiper-slide">';
        $html .= '<a href="' . htmlspecialchars($linkUrl) . '">';

        // Usar <picture> para trocar a imagem no mobile
        $html .= '<picture class="block w-full">';
        if (!empty($mobileImageUrl)) {
            $html .= '<source media="(max-width: 768px)" srcset="' . htmlspecialchars($mobileImageUrl) . '">';
        }
        // Fallback desktop / padrão
        $html .= '<img src="' . htmlspecialchars($imageUrl) . '" ';
        $html .= 'class="aspect-[14/5] w-full object-contain transition-all rounded-lg" ';
        $html .= 'alt="Banner ' . ($index + 1) . '">';
        $html .= '</picture>';

        $html .= '</a>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Show pagination only if there are multiple images
    if ($hasMultipleImages) {
        $html .= '<div class="swiper-pagination !bottom-4"></div>';
    }
    
    $html .= '</div>';
    
    // Navigation arrows - Always visible when there are multiple images
    if ($hasMultipleImages) {
        $html .= '<svg width="1em" height="1em" fill="none" viewBox="0 0 24 24" ';
        $html .= 'class="hero-prev-button size-10 absolute left-4 top-1/2 -translate-y-1/2 cursor-pointer z-20 text-white">';
        $html .= '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 4-8 8 8 8"></path>';
        $html .= '</svg>';
        
        $html .= '<svg width="1em" height="1em" fill="none" viewBox="0 0 24 24" ';
        $html .= 'class="hero-next-button size-10 absolute right-4 top-1/2 -translate-y-1/2 cursor-pointer z-20 -scale-x-100 text-white">';
        $html .= '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 4-8 8 8 8"></path>';
        $html .= '</svg>';
    }
    
    $html .= '</section>';
    
    // Initialize Swiper only once
    if ($loadSwiper) {
        $html .= '<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>';
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Swiper !== "undefined") {
                    const swiperContainer = document.querySelector("#' . $carouselId . ' .mySwiper");
                    if (swiperContainer) {
                        const swiper = new Swiper(swiperContainer, {
                            loop: ' . ($hasMultipleImages ? 'true' : 'false') . ',
                            autoplay: {
                                delay: 5000,
                                disableOnInteraction: false,
                            },
                            effect: "slide",
                            speed: 600,
                            ' . ($hasMultipleImages ? 'pagination: {
                                el: swiperContainer.querySelector(".swiper-pagination"),
                                clickable: true,
                            },
                            navigation: {
                                nextEl: document.querySelector("#' . $carouselId . ' .hero-next-button"),
                                prevEl: document.querySelector("#' . $carouselId . ' .hero-prev-button"),
                            },' : '') . '
                            on: {
                                slideChangeTransitionStart: function() {
                                    const activeSlide = this.slides[this.activeIndex];
                                    if (activeSlide) {
                                        activeSlide.style.animation = "slideInActive 0.6s ease-out";
                                    }
                                },
                                slideChangeTransitionEnd: function() {
                                    const slides = this.slides;
                                    slides.forEach((slide, index) => {
                                        if (index !== this.activeIndex) {
                                            slide.style.animation = "none";
                                        }
                                    });
                                }
                            }
                        });
                    }
                }
            });
        </script>';
    } else {
        // If Swiper already loaded, just initialize this instance
        $html .= '<script>
            (function initSwiper' . $carouselId . '() {
                if (typeof Swiper === "undefined") {
                    setTimeout(initSwiper' . $carouselId . ', 100);
                    return;
                }
                const swiperContainer = document.querySelector("#' . $carouselId . ' .mySwiper");
                if (swiperContainer && !swiperContainer.swiper) {
                    const swiper = new Swiper(swiperContainer, {
                        loop: ' . ($hasMultipleImages ? 'true' : 'false') . ',
                        autoplay: {
                            delay: 5000,
                            disableOnInteraction: false,
                        },
                        effect: "slide",
                        speed: 600,
                        ' . ($hasMultipleImages ? 'pagination: {
                            el: swiperContainer.querySelector(".swiper-pagination"),
                            clickable: true,
                        },
                        navigation: {
                            nextEl: document.querySelector("#' . $carouselId . ' .hero-next-button"),
                            prevEl: document.querySelector("#' . $carouselId . ' .hero-prev-button"),
                        },' : '') . '
                        on: {
                            slideChangeTransitionStart: function() {
                                const activeSlide = this.slides[this.activeIndex];
                                if (activeSlide) {
                                    activeSlide.style.animation = "slideInActive 0.6s ease-out";
                                }
                            },
                            slideChangeTransitionEnd: function() {
                                const slides = this.slides;
                                slides.forEach((slide, index) => {
                                    if (index !== this.activeIndex) {
                                        slide.style.animation = "none";
                                    }
                                });
                            }
                        }
                    });
                }
            })();
        </script>';
    }
    
    // Wrap in a container with the carousel ID e classe padrão
    return '<div id="' . $carouselId . '" class="premium-carousel-container">' . $html . '</div>';
}

// --- Rate Limiting ---

/**
 * Verifica rate limiting baseado em IP e acao
 * @param PDO $pdo Conexao com banco de dados
 * @param string $action Nome da acao (ex: 'login', 'payment', 'api_call')
 * @param int $maxAttempts Numero maximo de tentativas permitidas
 * @param int $windowSeconds Janela de tempo em segundos
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => string]
 */
function checkRateLimit($pdo, $action, $maxAttempts = 10, $windowSeconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = md5($ip . ':' . $action);
    $now = time();
    $windowStart = $now - $windowSeconds;
    
    // Usar sessao para rate limiting simples (sem necessidade de tabela extra)
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    // Limpar tentativas antigas
    if (isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = array_filter(
            $_SESSION['rate_limits'][$key],
            function($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            }
        );
    } else {
        $_SESSION['rate_limits'][$key] = [];
    }
    
    $attempts = count($_SESSION['rate_limits'][$key]);
    $allowed = $attempts < $maxAttempts;
    $remaining = max(0, $maxAttempts - $attempts);
    $resetAt = date('Y-m-d H:i:s', $now + $windowSeconds);
    
    // Se permitido, registrar esta tentativa
    if ($allowed) {
        $_SESSION['rate_limits'][$key][] = $now;
    }
    
    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'reset_at' => $resetAt,
        'attempts' => $attempts
    ];
}

/**
 * Aplica rate limiting e retorna erro JSON se excedido
 * @param PDO $pdo Conexao com banco de dados
 * @param string $action Nome da acao
 * @param int $maxAttempts Numero maximo de tentativas
 * @param int $windowSeconds Janela de tempo
 * @return bool True se permitido, false se bloqueado (ja enviou resposta)
 */
function applyRateLimit($pdo, $action, $maxAttempts = 10, $windowSeconds = 60) {
    $result = checkRateLimit($pdo, $action, $maxAttempts, $windowSeconds);
    
    if (!$result['allowed']) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $windowSeconds);
        echo json_encode([
            'success' => false,
            'error' => 'Muitas requisicoes. Tente novamente em alguns segundos.',
            'retry_after' => $windowSeconds
        ]);
        return false;
    }
    
    return true;
}
?>
