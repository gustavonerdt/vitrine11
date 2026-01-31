<?php
// user/index.php - User Dashboard (Painel)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user = getCurrentUser($pdo);
$financial = getUserFinancialSummary($pdo, $user['id']);
$referrals = getTotalReferralsCount($pdo, $user['id']);

// Generate referral link
$referralLink = APP_URL . '/register.php?ref=' . ($user['referral_code'] ?? generateReferralCode($user['id']));

// Release retained commissions (run on every dashboard load)
releaseRetainedCommissions($pdo);

// Get recent commissions for statistics
$recentCommissions = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as from_user_name, o.amount as order_amount
        FROM commissions c
        LEFT JOIN users u ON c.from_user_id = u.id
        LEFT JOIN orders o ON c.order_id = o.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $recentCommissions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Recent commissions error: " . $e->getMessage());
}

// Get statistics for different periods
$statsToday = ['commissions' => 0, 'referrals' => 0];
$statsWeek = ['commissions' => 0, 'referrals' => 0];
$statsMonth = ['commissions' => 0, 'referrals' => 0];

try {
    // Today
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM commissions WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$user['id']]);
    $statsToday['commissions'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$user['id']]);
    $statsToday['referrals'] = $stmt->fetch()['total'] ?? 0;
    
    // Week
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM commissions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$user['id']]);
    $statsWeek['commissions'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$user['id']]);
    $statsWeek['referrals'] = $stmt->fetch()['total'] ?? 0;
    
    // Month
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM commissions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$user['id']]);
    $statsMonth['commissions'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$user['id']]);
    $statsMonth['referrals'] = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Statistics error: " . $e->getMessage());
}

// Get banners for dashboard
$userStatus = ($user['office_status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
$banners = getBannersForPage($pdo, 'dashboard', $userStatus);

$pageTitle = 'Meu Painel';
include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-modern">
    <?php 
    // Display banners (regular and split banners)
    foreach ($banners as $banner) {
        $displayType = $banner['type'] ?? $banner['display_type'] ?? 'banner';
        if ($displayType === 'banner' || $displayType === 'split') {
            echo renderBanner($banner, 'dashboard');
        }
    }
    ?>
    
    <!-- Dashboard Header -->
    <div class="dashboard-header-modern">
        <div class="header-left">
            <h1 class="dashboard-title">Olá <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h1>
            <p class="dashboard-subtitle">Dashboard</p>
        </div>
        <div class="header-right">
            <?php if (($user['office_status'] ?? 'inactive') === 'active'): ?>
                <div class="balance-pill-modern">
                    <span class="balance-label">Saldo Disponível</span>
                    <span class="balance-value"><?php echo formatPrice($financial['balance_available']); ?></span>
                </div>
            <?php else: ?>
                <div class="status-badge-modern inactive">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Escritório Inativo</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid-modern">
        <!-- Left Column: Withdrawal & Link -->
        <div class="dashboard-left">
            <!-- Withdrawal Request Card -->
            <?php if (($user['office_status'] ?? 'inactive') === 'active'): ?>
            <div class="withdrawal-card-modern">
                <div class="card-header-modern">
                    <h3>Solicitar Saque</h3>
                    <span class="status-badge-modern active">
                        <i class="fas fa-check-circle"></i>
                        Ativado
                    </span>
                </div>
                <div class="withdrawal-conditions">
                    <div class="condition-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Ter pelo menos R$ 100,00 em saldo disponível</span>
                    </div>
                </div>
                <div class="withdrawal-amount">
                    <label>Disponível para saque</label>
                    <div class="amount-value"><?php echo formatPrice($financial['balance_available']); ?></div>
                </div>
                <a href="<?php echo APP_URL; ?>/user/financial.php?action=withdraw" class="btn-withdraw-modern">
                    <i class="fas fa-money-bill-wave"></i>
                    Solicitar Saque
                </a>
            </div>
            <?php else: ?>
            <div class="inactive-notice-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Escritório Inativo</h3>
                <p><?php echo getSetting($pdo, 'office_inactive_message', 'Seu escritório está inativo. Adquira um pacote para ativar e começar a ganhar comissões!'); ?></p>
                <a href="<?php echo APP_URL; ?>/user/packages.php" class="btn-activate-modern">
                    <i class="fas fa-rocket"></i>
                    Ativar Agora
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Referral Link Card -->
            <div class="referral-card-modern">
                <div class="card-header-modern">
                    <h3>Link de Indicação</h3>
                </div>
                <p class="card-description">Compartilhe este link para convidar novos associados</p>
                <div class="referral-link-modern">
                    <input type="text" id="referralLink" value="<?php echo $referralLink; ?>" readonly>
                    <button onclick="copyReferralLink()" class="btn-copy-modern" title="Copiar">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Statistics -->
        <div class="dashboard-right">
            <div class="statistics-card-modern">
                <div class="card-header-modern">
                    <h3>Estatísticas</h3>
                    <a href="<?php echo APP_URL; ?>/user/commissions.php" class="view-all-link">Ver todas</a>
                </div>
                
                <!-- Time Period Tabs -->
                <div class="stats-tabs-modern">
                    <button class="tab-btn active" data-period="day" onclick="switchStatsPeriod('day')">
                        Hoje
                    </button>
                    <button class="tab-btn" data-period="week" onclick="switchStatsPeriod('week')">
                        Semana
                    </button>
                    <button class="tab-btn" data-period="month" onclick="switchStatsPeriod('month')">
                        Mês
                    </button>
                </div>
                
                <!-- Statistics Content -->
                <div class="stats-content-modern">
                    <div class="stat-item-modern" id="stat-day">
                        <div class="stat-metric">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-label">Associados</span>
                                <span class="stat-value"><?php echo $statsToday['referrals']; ?></span>
                            </div>
                        </div>
                        <div class="stat-metric">
                            <div class="stat-icon success">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-label">Comissões</span>
                                <span class="stat-value"><?php echo formatPrice($statsToday['commissions']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-item-modern" id="stat-week" style="display: none;">
                        <div class="stat-metric">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-label">Associados</span>
                                <span class="stat-value"><?php echo $statsWeek['referrals']; ?></span>
                            </div>
                        </div>
                        <div class="stat-metric">
                            <div class="stat-icon success">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-label">Comissões</span>
                                <span class="stat-value"><?php echo formatPrice($statsWeek['commissions']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-item-modern" id="stat-month" style="display: none;">
                        <div class="stat-metric">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-label">Associados</span>
                                <span class="stat-value"><?php echo $statsMonth['referrals']; ?></span>
                            </div>
                        </div>
                        <div class="stat-metric">
                            <div class="stat-icon success">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-label">Comissões</span>
                                <span class="stat-value"><?php echo formatPrice($statsMonth['commissions']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats Grid -->
            <div class="quick-stats-grid-modern">
                <div class="quick-stat-card">
                    <div class="quick-stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="quick-stat-content">
                        <span class="quick-stat-label">Associados Diretos</span>
                        <span class="quick-stat-value"><?php echo $referrals['n1']; ?></span>
                    </div>
                </div>
                
                <div class="quick-stat-card">
                    <div class="quick-stat-icon success">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="quick-stat-content">
                        <span class="quick-stat-label">Pontos</span>
                        <span class="quick-stat-value"><?php echo number_format($financial['points_available']); ?></span>
                    </div>
                </div>
                
                <div class="quick-stat-card" onclick="showRetainedInfo()" style="cursor: pointer;">
                    <div class="quick-stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-stat-content">
                        <span class="quick-stat-label">Saldo Retido</span>
                        <span class="quick-stat-value"><?php echo formatPrice($financial['balance_retained']); ?></span>
                    </div>
                    <button class="quick-stat-info-btn" onclick="event.stopPropagation(); showRetainedInfo();" title="Ver detalhes">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </div>
                
                <div class="quick-stat-card">
                    <div class="quick-stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="quick-stat-content">
                        <span class="quick-stat-label">Total Acumulado</span>
                        <span class="quick-stat-value"><?php echo formatPrice($financial['total_accumulated']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions-modern">
        <a href="<?php echo APP_URL; ?>/user/associates.php" class="action-card-modern">
            <i class="fas fa-users"></i>
            <span>Meus Associados</span>
        </a>
        <a href="<?php echo APP_URL; ?>/user/financial.php" class="action-card-modern">
            <i class="fas fa-university"></i>
            <span>Financeiro</span>
        </a>
        <a href="<?php echo APP_URL; ?>/user/products.php" class="action-card-modern">
            <i class="fas fa-store"></i>
            <span>Vitrine de Perfumes</span>
        </a>
        <a href="<?php echo APP_URL; ?>/user/packages.php" class="action-card-modern">
            <i class="fas fa-box-open"></i>
            <span>Pacotes</span>
        </a>
    </div>
</div>

<!-- Retained Balance Modal -->
<div id="retainedModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Saldo Retido</h3>
            <button onclick="closeModal('retainedModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p><?php echo str_replace('{days}', getSetting($pdo, 'retention_days', 30), getSetting($pdo, 'retained_balance_message', 'Este valor ficará retido por {days} dias antes de ser liberado para saque.')); ?></p>
            <div class="retained-info">
                <div class="info-row">
                    <span>Saldo Retido Atual:</span>
                    <strong><?php echo formatPrice($financial['balance_retained']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Período de Retenção:</span>
                    <strong><?php echo getSetting($pdo, 'retention_days', 30); ?> dias</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyReferralLink() {
    const input = document.getElementById('referralLink');
    input.select();
    document.execCommand('copy');
    showToast('Link copiado!', 'success');
}

function switchStatsPeriod(period) {
    // Update tabs
    document.querySelectorAll('.stats-tabs-modern .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.stats-tabs-modern .tab-btn[data-period="${period}"]`).classList.add('active');
    
    // Update content
    document.querySelectorAll('.stat-item-modern').forEach(item => {
        item.style.display = 'none';
    });
    document.getElementById(`stat-${period}`).style.display = 'flex';
}

function showRetainedInfo() {
    document.getElementById('retainedModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
