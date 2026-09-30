<?php
require_once 'config/config.php';
requireLogin();
requireRole('oswd');

$loginSecurity = new LoginSecurity();
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($days, [7, 14, 30], true)) {
    $days = 7;
}

if (isset($_POST['retrain_model']) && verifyCsrf()) {
    $trainResult = $loginSecurity->retrainModel();
    $_SESSION['message'] = $trainResult['message'];
    if (!empty($trainResult['accuracy'])) {
        $_SESSION['message'] .= ' Accuracy: ' . round((float)$trainResult['accuracy'] * 100, 1) . '%';
    }
    $_SESSION['message_type'] = $trainResult['success'] ? 'success' : 'danger';
    redirect('login_security.php?days=' . $days);
}

$summary = $loginSecurity->getSummary($days);
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$eventPage = $loginSecurity->getRecentEventsPaginated($page, $perPage);
$events = $eventPage['rows'];
$totalEvents = $eventPage['total'];
$totalPages = $eventPage['total_pages'];
$page = $eventPage['page'];
if ($page > $totalPages && $totalEvents > 0) {
    redirect('login_security.php?days=' . $days . '&page=' . $totalPages);
}
$rangeStart = $totalEvents === 0 ? 0 : (($page - 1) * $perPage) + 1;
$rangeEnd = min($page * $perPage, $totalEvents);
$modelReady = $loginSecurity->modelExists();

function loginSecurityPageUrl($days, $page) {
    return 'login_security.php?days=' . (int)$days . '&page=' . max(1, (int)$page);
}

function loginFailureLabel($reason) {
    return match ((string)$reason) {
        'invalid_credentials' => 'Invalid credentials',
        'blocked_risk' => 'Blocked (high risk)',
        'otp_required' => 'OTP required',
        'otp_verified' => 'OTP verified',
        'otp_email_failed' => 'OTP email failed (SMTP)',
        default => $reason ? formatStatus($reason) : '—',
    };
}

function loginRiskBadgeClass($label) {
    return match ((string)$label) {
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'success',
        'exempt' => 'info',
        default => 'secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Login Security - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="app-body">
<?php include 'includes/skip_link.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/flash.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-start pt-3 nemsu-page-header">
                    <div>
                        <h1 class="h2">Login Security (Random Forest)</h1>
                        <p class="text-muted mb-0">Monitor login risk scores, OTP challenges, and blocked attempts.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="btn-group btn-group-sm">
                            <a href="<?php echo loginSecurityPageUrl(7, 1); ?>" class="btn btn-outline-primary <?php echo $days === 7 ? 'active' : ''; ?>">7 days</a>
                            <a href="<?php echo loginSecurityPageUrl(14, 1); ?>" class="btn btn-outline-primary <?php echo $days === 14 ? 'active' : ''; ?>">14 days</a>
                            <a href="<?php echo loginSecurityPageUrl(30, 1); ?>" class="btn btn-outline-primary <?php echo $days === 30 ? 'active' : ''; ?>">30 days</a>
                        </div>
                        <form method="POST" class="d-inline">
                            <?php echo csrfField(); ?>
                            <button type="submit" name="retrain_model" class="btn btn-sm btn-warning">
                                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Retrain model
                            </button>
                        </form>
                    </div>
                </div>

                <div class="alert alert-light border mb-4">
                    <strong>Status:</strong>
                    RF enabled: <?php echo LOGIN_RF_ENABLED ? 'Yes' : 'No'; ?> ·
                    Shadow mode: <?php echo LOGIN_RF_SHADOW_MODE ? 'On (scores only)' : 'Off (enforce)'; ?> ·
                    Model file: <?php echo $modelReady ? 'Ready' : 'Missing (using rule fallback)'; ?> ·
                    SMTP: <?php echo SMTP_ENABLED && SMTP_USERNAME !== '' ? 'Configured' : 'Not configured (edit config/smtp.php)'; ?>
                </div>

                <div class="row mb-4 nemsu-stat-grid">
                    <div class="col-md-4 col-lg-2">
                        <div class="card nemsu-stat-card nemsu-stat-card--blue">
                            <div class="card-body position-relative py-3">
                                <h5 class="card-title">Attempts</h5>
                                <h2 class="h3"><?php echo $summary['total']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="card nemsu-stat-card nemsu-stat-card--success">
                            <div class="card-body position-relative py-3">
                                <h5 class="card-title">Success</h5>
                                <h2 class="h3"><?php echo $summary['success']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="card nemsu-stat-card nemsu-stat-card--gold">
                            <div class="card-body position-relative py-3">
                                <h5 class="card-title">OTP step</h5>
                                <h2 class="h3"><?php echo $summary['otp_required']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="card nemsu-stat-card nemsu-stat-card--sky">
                            <div class="card-body position-relative py-3">
                                <h5 class="card-title">Medium RF</h5>
                                <h2 class="h3"><?php echo $summary['medium_risk']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="card nemsu-stat-card nemsu-stat-card--gold">
                            <div class="card-body position-relative py-3">
                                <h5 class="card-title">High RF</h5>
                                <h2 class="h3"><?php echo $summary['high_risk']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="card nemsu-stat-card nemsu-stat-card--blue" style="background:linear-gradient(135deg,#6b1d1d,#a83232);">
                            <div class="card-body position-relative py-3">
                                <h5 class="card-title">Blocked</h5>
                                <h2 class="h3"><?php echo $summary['blocked']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card nemsu-panel">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Recent login events</span>
                        <?php if ($totalEvents > 0): ?>
                            <small class="text-muted">
                                Showing <?php echo $rangeStart; ?>–<?php echo $rangeEnd; ?> of <?php echo $totalEvents; ?>
                                · Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>User</th>
                                        <th>Username tried</th>
                                        <th>Result</th>
                                        <th>Reason</th>
                                        <th>Risk</th>
                                        <th>Score</th>
                                        <th>Model</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($events)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-4">No login events logged yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($events as $ev): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($ev['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($ev['full_name'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($ev['username_attempted']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $ev['success'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $ev['success'] ? 'Success' : 'Failed'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(loginFailureLabel($ev['failure_reason'] ?? '')); ?></td>
                                                <td>
                                                    <?php if (!empty($ev['risk_label'])): ?>
                                                        <span class="badge bg-<?php echo loginRiskBadgeClass($ev['risk_label']); ?>">
                                                            <?php echo htmlspecialchars(ucfirst($ev['risk_label'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $ev['risk_score'] !== null ? htmlspecialchars((string)$ev['risk_score']) : '—'; ?></td>
                                                <td><small><?php echo htmlspecialchars($ev['model_version'] ?? '—'); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer bg-white">
                            <nav aria-label="Login events pagination">
                                <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo loginSecurityPageUrl($days, $page - 1); ?>">Previous</a>
                                    </li>
                                    <?php
                                    $window = 2;
                                    $startPage = max(1, $page - $window);
                                    $endPage = min($totalPages, $page + $window);
                                    if ($startPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo loginSecurityPageUrl($days, 1); ?>">1</a>
                                        </li>
                                        <?php if ($startPage > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo loginSecurityPageUrl($days, $p); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo loginSecurityPageUrl($days, $totalPages); ?>"><?php echo $totalPages; ?></a>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo loginSecurityPageUrl($days, $page + 1); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <?php include 'includes/scripts.php'; ?>
</body>
</html>
