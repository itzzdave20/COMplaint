<?php
require_once 'config/config.php';
requireLogin();

// Every login (including after the Random Forest OTP step) lands here,
// so this is where the Super Admin is sent to their own dashboard.
if (hasRole('super_admin')) {
    redirect('super_admin.php');
}

$complaint = new Complaint();

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$dashboardMeta = dashboardMetaForRole($userRole);

$allComplaints = $complaint->getComplaintsForDashboard($userId, $userRole);
$complaints = $allComplaints;

$totalComplaints = count($allComplaints);
$pendingCount = count(array_filter($allComplaints, fn($c) => ($c['status'] ?? '') === 'pending'));
$inProgressCount = count(array_filter($allComplaints, fn($c) => in_array($c['status'] ?? '', ['under_review', 'investigating'], true)));
$resolvedCount = count(array_filter($allComplaints, fn($c) => ($c['status'] ?? '') === 'resolved'));
$criticalCount = count(array_filter($allComplaints, fn($c) => in_array($c['severity'] ?? '', ['high', 'critical'], true)));

$filterStatus = sanitizeInput($_GET['status'] ?? '');
$filterSeverity = sanitizeInput($_GET['severity'] ?? '');
$filterCategory = sanitizeInput($_GET['category'] ?? '');
$filterQ = sanitizeInput($_GET['q'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'date_desc');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

if ($userRole !== 'student') {
    $complaints = array_values(array_filter($complaints, function ($c) use ($filterStatus, $filterSeverity, $filterCategory, $filterQ) {
        if ($filterStatus !== '' && ($c['status'] ?? '') !== $filterStatus) {
            return false;
        }
        if ($filterSeverity !== '' && ($c['severity'] ?? '') !== $filterSeverity) {
            return false;
        }
        if ($filterCategory !== '' && ($c['complaint_category'] ?? '') !== $filterCategory) {
            return false;
        }
        if ($filterQ !== '') {
            $hay = strtolower(($c['complaint_title'] ?? '') . ' ' . ($c['complainant_name'] ?? ''));
            if (!str_contains($hay, strtolower($filterQ))) {
                return false;
            }
        }
        return true;
    }));

    usort($complaints, function ($a, $b) use ($sort) {
        if ($sort === 'severity') {
            $order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return ($order[$b['severity'] ?? 'low'] ?? 0) <=> ($order[$a['severity'] ?? 'low'] ?? 0);
        }
        if ($sort === 'date_asc') {
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        }
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    $totalFiltered = count($complaints);
    $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $complaintsPage = array_slice($complaints, ($page - 1) * $perPage, $perPage);
} else {
    $complaintsPage = $complaints;
    $totalFiltered = $totalComplaints;
    $totalPages = 1;
    $page = 1;
}

$latestComplaint = ($userRole === 'student' && !empty($allComplaints)) ? $allComplaints[0] : null;
$latestSteps = $latestComplaint ? complaintProgressSteps($latestComplaint) : [];
$latestCurrent = null;
foreach ($latestSteps as $step) {
    if (($step['state'] ?? '') === 'current') {
        $latestCurrent = $step['label'];
        break;
    }
}

function dashboardQuery(array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    unset($params[0]);
    $q = http_build_query($params);
    return 'dashboard.php' . ($q !== '' ? '?' . $q : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Dashboard - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                    <h1 class="h2"><?php echo htmlspecialchars($dashboardMeta['title']); ?></h1>
                    <?php if ($dashboardMeta['subtitle'] !== ''): ?>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($dashboardMeta['subtitle']); ?></p>
                    <?php endif; ?>
                </div>
                <span class="nemsu-user-chip">
                    <i class="bi bi-person-badge text-warning" aria-hidden="true"></i>
                    <strong><?php echo htmlspecialchars(roleLabel($userRole)); ?></strong>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>

            <?php if ($userRole === 'student'): ?>
                <section class="nemsu-hero-card mb-4" aria-labelledby="your-case-heading">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                            <h2 id="your-case-heading" class="h5 mb-2"><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Your case</h2>
                            <?php if ($latestComplaint): ?>
                                <p class="mb-2 fw-semibold"><?php echo htmlspecialchars($latestComplaint['complaint_title']); ?></p>
                                <p class="small text-muted mb-2">Reference #<?php echo (int)$latestComplaint['complaint_id']; ?> · <?php echo statusPlainLanguage($latestComplaint['status']); ?></p>
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                    <?php echo statusBadgeHtml($latestComplaint['status']); ?>
                                    <?php echo severityBadgeHtml($latestComplaint['severity']); ?>
                                </div>
                                <p class="small mb-1"><strong>Next step:</strong> <?php echo htmlspecialchars($latestCurrent ?? 'Awaiting update'); ?></p>
                                <p class="small text-muted mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i><?php echo htmlspecialchars(expectedResponseHint($latestComplaint['status'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted mb-0">You have not filed a complaint yet. OSWD is here to help when something needs to be addressed.</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <a href="submit_complaint.php" class="btn btn-primary btn-lg w-100 w-lg-auto">
                                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> File a complaint
                            </a>
                            <?php if ($latestComplaint): ?>
                                <a href="view_complaint.php?id=<?php echo (int)$latestComplaint['complaint_id']; ?>" class="btn btn-outline-primary w-100 w-lg-auto mt-2">View latest case</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <div class="row mb-4 nemsu-stat-grid g-3">
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--blue h-100">
                        <div class="card-body">
                            <i class="bi bi-inbox stat-icon" aria-hidden="true"></i>
                            <h3 class="h6 card-title">Total</h3>
                            <p class="display-6 mb-0"><?php echo $userRole === 'student' ? $totalComplaints : $totalFiltered; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--gold h-100">
                        <div class="card-body">
                            <i class="bi bi-hourglass-split stat-icon" aria-hidden="true"></i>
                            <h3 class="h6 card-title">Pending</h3>
                            <p class="display-6 mb-0"><?php echo $pendingCount; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--sky h-100">
                        <div class="card-body">
                            <i class="bi bi-arrow-repeat stat-icon" aria-hidden="true"></i>
                            <h3 class="h6 card-title">In progress</h3>
                            <p class="display-6 mb-0"><?php echo $inProgressCount; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card nemsu-stat-card nemsu-stat-card--success h-100">
                        <div class="card-body">
                            <i class="bi bi-check2-circle stat-icon" aria-hidden="true"></i>
                            <h3 class="h6 card-title">Resolved</h3>
                            <p class="display-6 mb-0"><?php echo $resolvedCount; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($userRole !== 'student' && $criticalCount > 0): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2" role="status">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <span><?php echo (int)$criticalCount; ?> case(s) marked high or critical severity need attention.</span>
                </div>
            <?php endif; ?>

            <div class="card nemsu-panel">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">
                        <?php
                        echo $userRole === 'student' ? 'Recent complaints' : ($userRole === 'oswd' ? 'Work queue — all complaints' : 'Work queue — assigned to you');
                        ?>
                    </h2>
                    <?php if ($userRole === 'student'): ?>
                        <a href="submit_complaint.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New</a>
                    <?php endif; ?>
                </div>

                <?php if ($userRole !== 'student'): ?>
                    <div class="card-body border-bottom bg-light-subtle">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label for="q" class="form-label small mb-1">Search</label>
                                <input type="search" class="form-control form-control-sm" id="q" name="q" value="<?php echo htmlspecialchars($filterQ); ?>" placeholder="Title or complainant">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label small mb-1">Status</label>
                                <select class="form-select form-select-sm" id="status" name="status">
                                    <option value="">All</option>
                                    <?php foreach (allowedComplaintStatuses() as $st): ?>
                                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars(formatStatus($st)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="severity" class="form-label small mb-1">Severity</label>
                                <select class="form-select form-select-sm" id="severity" name="severity">
                                    <option value="">All</option>
                                    <?php foreach (allowedSeverities() as $sev): ?>
                                        <option value="<?php echo htmlspecialchars($sev); ?>" <?php echo $filterSeverity === $sev ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($sev)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sort" class="form-label small mb-1">Sort</label>
                                <select class="form-select form-select-sm" id="sort" name="sort">
                                    <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest</option>
                                    <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest</option>
                                    <option value="severity" <?php echo $sort === 'severity' ? 'selected' : ''; ?>>Severity</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="card-body p-0">
                    <?php if (empty($complaintsPage)): ?>
                        <?php echo renderEmptyState(
                            'inbox',
                            'No complaints here',
                            $userRole === 'student' ? 'When you submit a complaint, it will appear in this list.' : 'No cases match your filters right now.',
                            $userRole === 'student' ? 'File a complaint' : '',
                            $userRole === 'student' ? 'submit_complaint.php' : ''
                        ); ?>
                    <?php else: ?>
                        <div class="d-md-none p-3 vstack gap-3">
                            <?php foreach ($complaintsPage as $comp): ?>
                                <?php $rowCritical = in_array($comp['severity'] ?? '', ['high', 'critical'], true); ?>
                                <article class="nemsu-complaint-card <?php echo $rowCritical ? 'nemsu-complaint-card--critical' : ''; ?>">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <span class="text-muted small">#<?php echo (int)$comp['complaint_id']; ?></span>
                                        <span class="text-muted small"><?php echo date('M j, Y', strtotime($comp['created_at'])); ?></span>
                                    </div>
                                    <h3 class="h6 mb-2"><?php echo htmlspecialchars($comp['complaint_title']); ?></h3>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php echo statusBadgeHtml($comp['status']); ?>
                                        <?php echo severityBadgeHtml($comp['severity']); ?>
                                    </div>
                                    <a href="view_complaint.php?id=<?php echo (int)$comp['complaint_id']; ?>" class="btn btn-sm btn-outline-primary w-100">View details</a>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="table-responsive d-none d-md-block nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <?php if ($userRole !== 'student'): ?><th>Complainant</th><?php endif; ?>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaintsPage as $comp): ?>
                                        <?php
                                        $rowCritical = in_array($comp['severity'] ?? '', ['high', 'critical'], true);
                                        $overdue = ($comp['status'] ?? '') === 'pending' && strtotime($comp['created_at']) < strtotime('-7 days');
                                        ?>
                                        <tr class="<?php echo $rowCritical ? 'table-danger' : ($overdue ? 'table-warning' : ''); ?>">
                                            <td>#<?php echo (int)$comp['complaint_id']; ?></td>
                                            <?php if ($userRole !== 'student'): ?>
                                                <td><?php echo htmlspecialchars($comp['complainant_name'] ?? '—'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($comp['complaint_title']); ?></td>
                                            <td><small><?php echo htmlspecialchars($comp['complaint_category'] ?? '—'); ?></small></td>
                                            <td><?php echo statusBadgeHtml($comp['status']); ?></td>
                                            <td><?php echo severityBadgeHtml($comp['severity']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($comp['created_at'])); ?></td>
                                            <td><a href="view_complaint.php?id=<?php echo (int)$comp['complaint_id']; ?>" class="btn btn-sm btn-primary">Open</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($userRole !== 'student' && $totalPages > 1): ?>
                    <div class="card-footer bg-white">
                        <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="Complaints pagination">
                            <span class="small text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars(dashboardQuery(['page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                                </li>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars(dashboardQuery(['page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
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
