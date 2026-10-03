<?php
/**
 * Super Admin — Reports.
 * Choose filters, preview the summary, and export it as PDF or Excel.
 * Exports list complainants by alias only and are recorded in the audit log.
 */
require_once 'config/config.php';
requireRole('super_admin');

$superAdmin = new SuperAdmin();
$filters = [
    'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
    'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
    'status' => in_array($_GET['status'] ?? '', allowedComplaintStatuses(), true) ? $_GET['status'] : '',
    'department' => in_array($_GET['department'] ?? '', departments(), true) ? $_GET['department'] : '',
    'level' => in_array($_GET['level'] ?? '', workflowLevels(), true) ? $_GET['level'] : '',
    'category' => in_array($_GET['category'] ?? '', allowedComplaintCategories(), true) ? $_GET['category'] : '',
];
$report = $superAdmin->reportData($filters);

// ---- Export: send the file instead of the page ------------------
$format = $_GET['export'] ?? '';
if (in_array($format, ['pdf', 'xlsx'], true)) {
    // Human-readable description of the filters, printed in the file.
    $described = [];
    if ($filters['from'] || $filters['to']) {
        $described[] = 'Dates ' . ($filters['from'] ?: 'start') . ' to ' . ($filters['to'] ?: 'today');
    }
    if ($filters['department']) { $described[] = 'Department: ' . $filters['department']; }
    if ($filters['status']) { $described[] = 'Status: ' . formatStatus($filters['status']); }
    if ($filters['level']) { $described[] = 'Level: ' . workflowLevelLabel($filters['level']); }
    if ($filters['category']) { $described[] = 'Category: ' . $filters['category']; }

    $meta = [
        'generated' => date('M j, Y g:i A'),
        'by' => $_SESSION['full_name'] . ' (' . $_SESSION['username'] . ')',
        'filters' => $described ? implode('; ', $described) : 'None (all complaints)',
    ];
    $superAdmin->logExport($format, $filters, $report['total']);

    $filename = 'complaint_summary_' . date('Y-m-d_His') . '.' . $format;
    if ($format === 'pdf') {
        $bytes = ReportExporter::pdf($report, $meta);
        header('Content-Type: application/pdf');
    } else {
        $bytes = ReportExporter::xlsx($report, $meta);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

$exportQuery = http_build_query(array_filter($filters));
$pageTitle = 'Reports';
$pageHeading = 'Reports';
$pageSubtitle = 'Complaint summaries for the selected period, exportable to PDF and Excel.';
$pageActions = '<a class="btn btn-outline-danger btn-sm" href="super_admin_reports.php?' . htmlspecialchars($exportQuery . ($exportQuery ? '&' : '') . 'export=pdf') . '">'
    . '<i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Export PDF</a>'
    . '<a class="btn btn-outline-success btn-sm" href="super_admin_reports.php?' . htmlspecialchars($exportQuery . ($exportQuery ? '&' : '') . 'export=xlsx') . '">'
    . '<i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Export Excel</a>';
include 'includes/super_admin_top.php';
?>

<form method="GET" class="card nemsu-panel mb-4">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="from" class="form-label small">From</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="department" class="form-label small">Department</label>
            <select class="form-select form-select-sm" id="department" name="department">
                <option value="">All</option>
                <?php foreach (departments() as $dept): ?>
                    <option value="<?php echo $dept; ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="">All</option>
                <?php foreach (allowedComplaintStatuses() as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo formatStatus($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="level" class="form-label small">Level</label>
            <select class="form-select form-select-sm" id="level" name="level">
                <option value="">All</option>
                <?php foreach (workflowLevels() as $level): ?>
                    <option value="<?php echo $level; ?>" <?php echo $filters['level'] === $level ? 'selected' : ''; ?>><?php echo htmlspecialchars(workflowLevelLabel($level)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="category" class="form-label small">Category</label>
            <select class="form-select form-select-sm" id="category" name="category">
                <option value="">All</option>
                <?php foreach (allowedComplaintCategories() as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filters['category'] === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-fill" type="submit">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="super_admin_reports.php">Reset</a>
        </div>
    </div>
</form>

<!-- Preview of what the export will contain -->
<div class="row g-4">
    <?php
    foreach (ReportExporter::GROUPS as $title => $key):   // same groups as the exported files
    ?>
        <div class="col-md-6 col-xl-4">
            <div class="card nemsu-panel h-100">
                <div class="card-header"><?php echo $title; ?></div>
                <ul class="list-group list-group-flush">
                    <?php if (!$report['summary'][$key]): ?>
                        <li class="list-group-item small text-muted">No complaints</li>
                    <?php endif; ?>
                    <?php foreach ($report['summary'][$key] as $label => $count): ?>
                        <li class="list-group-item d-flex justify-content-between small">
                            <span><?php echo htmlspecialchars($label); ?></span>
                            <span class="badge bg-primary rounded-pill"><?php echo $count; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="text-muted small mt-3">
    <?php echo $report['total']; ?> complaint(s) match. The PDF and Excel files contain this summary plus the full list,
    with complainants shown by alias. Each export is recorded in the audit log.
</p>

<?php include 'includes/super_admin_bottom.php'; ?>
