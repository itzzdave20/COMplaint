<?php
require_once 'config/config.php';
requireLogin();

if (!hasRole('oswd')) {
    redirect('dashboard.php');
}

$complaint = new Complaint();
$loginSecurity = new LoginSecurity();
$all = $complaint->getAllComplaints();
$stats = $complaint->getStatistics();
$total = count($all);
$loginSummary = $loginSecurity->getSummary(7);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="oswd_complaints_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Title', 'Category', 'Status', 'Severity', 'Complainant', 'Created', 'Updated']);
    foreach ($all as $row) {
        fputcsv($out, [
            $row['complaint_id'],
            $row['complaint_title'],
            $row['complaint_category'] ?? '',
            $row['status'],
            $row['severity'],
            $row['complainant_name'] ?? '',
            $row['created_at'],
            $row['updated_at'],
        ]);
    }
    fclose($out);
    exit;
}

$byMonth = [];
$byCategory = [];
$resolutionDays = [];
$resolvedCount = 0;

foreach ($all as $row) {
    $month = date('Y-m', strtotime($row['created_at']));
    $byMonth[$month] = ($byMonth[$month] ?? 0) + 1;
    $cat = $row['complaint_category'] ?? 'Uncategorized';
    $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
    if (($row['status'] ?? '') === 'resolved') {
        $resolvedCount++;
        $days = (strtotime($row['updated_at']) - strtotime($row['created_at'])) / 86400;
        if ($days >= 0) {
            $resolutionDays[] = $days;
        }
    }
}

ksort($byMonth);
$avgResolution = count($resolutionDays) ? round(array_sum($resolutionDays) / count($resolutionDays), 1) : 0;

$chartOverTime = ['label' => 'Complaints', 'labels' => array_keys($byMonth), 'values' => array_values($byMonth)];
$chartCategory = ['label' => 'By category', 'labels' => array_keys($byCategory), 'values' => array_values($byCategory)];
$chartStatus = ['label' => 'By status', 'labels' => [], 'values' => []];
foreach ($stats as $status => $count) {
    $chartStatus['labels'][] = formatStatus($status);
    $chartStatus['values'][] = $count;
}

$pageScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'assets/js/reports.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Reports - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="app-body">
<?php include 'includes/skip_link.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/flash.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
            <div class="d-flex justify-content-between flex-wrap align-items-start pt-3 nemsu-page-header gap-2">
                <div>
                    <h1 class="h2">Reports</h1>
                    <p class="text-muted mb-0">Complaint trends and performance overview</p>
                </div>
                <a href="reports.php?export=csv" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>Export CSV
                </a>
            </div>

            <div class="row g-3 mb-4 nemsu-stat-grid">
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--blue h-100">
                        <div class="card-body">
                            <i class="bi bi-pie-chart stat-icon" aria-hidden="true"></i>
                            <h3 class="h6">Total</h3>
                            <p class="display-6 mb-0"><?php echo $total; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--gold h-100">
                        <div class="card-body">
                            <i class="bi bi-hourglass-split stat-icon" aria-hidden="true"></i>
                            <h3 class="h6">Pending</h3>
                            <p class="display-6 mb-0"><?php echo $stats['pending'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--success h-100">
                        <div class="card-body">
                            <i class="bi bi-check2-circle stat-icon" aria-hidden="true"></i>
                            <h3 class="h6">Resolved</h3>
                            <p class="display-6 mb-0"><?php echo $stats['resolved'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--sky h-100">
                        <div class="card-body">
                            <i class="bi bi-stopwatch stat-icon" aria-hidden="true"></i>
                            <h3 class="h6">Avg resolution</h3>
                            <p class="display-6 mb-0"><?php echo $avgResolution; ?><small class="fs-6">d</small></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card nemsu-panel h-100">
                        <div class="card-header">Complaints over time</div>
                        <div class="card-body">
                            <div class="nemsu-chart-wrap" id="chartOverTimeData" data-chart="<?php echo htmlspecialchars(json_encode($chartOverTime), ENT_QUOTES, 'UTF-8'); ?>">
                                <canvas id="chartOverTime" height="220" aria-label="Complaints over time chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card nemsu-panel h-100">
                        <div class="card-header">By category</div>
                        <div class="card-body">
                            <div class="nemsu-chart-wrap" id="chartCategoryData" data-chart="<?php echo htmlspecialchars(json_encode($chartCategory), ENT_QUOTES, 'UTF-8'); ?>">
                                <canvas id="chartCategory" height="220" aria-label="Complaints by category chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card nemsu-panel">
                        <div class="card-header">By status</div>
                        <div class="card-body">
                            <div class="nemsu-chart-wrap" id="chartStatusData" data-chart="<?php echo htmlspecialchars(json_encode($chartStatus), ENT_QUOTES, 'UTF-8'); ?>">
                                <canvas id="chartStatus" height="120" aria-label="Complaints by status chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card nemsu-panel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Login security (7 days)</span>
                    <a href="login_security.php" class="btn btn-sm btn-outline-primary">Details</a>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-4 col-md-2"><strong><?php echo $loginSummary['total']; ?></strong><br><small class="text-muted">Attempts</small></div>
                        <div class="col-4 col-md-2"><strong><?php echo $loginSummary['success']; ?></strong><br><small class="text-muted">Success</small></div>
                        <div class="col-4 col-md-2"><strong><?php echo $loginSummary['otp_required']; ?></strong><br><small class="text-muted">OTP</small></div>
                        <div class="col-4 col-md-2"><strong><?php echo $loginSummary['high_risk']; ?></strong><br><small class="text-muted">High risk</small></div>
                        <div class="col-4 col-md-2"><strong><?php echo $loginSummary['blocked']; ?></strong><br><small class="text-muted">Blocked</small></div>
                        <div class="col-4 col-md-2"><strong><?php echo $loginSecurity->modelExists() ? 'Ready' : 'Fallback'; ?></strong><br><small class="text-muted">Model</small></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
