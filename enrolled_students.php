<?php
/**
 * OSWD — Enrolled students list.
 *
 * OSWD uploads the official list of enrolled Student IDs (CSV, Excel .xlsx
 * or a text file with one ID per line). Registration only accepts Student
 * IDs on this list (see register.php and EnrollmentList).
 */
require_once 'config/config.php';
requireRole('oswd');

$enrollment = new EnrollmentList();
$importResult = null;

// ---- Template download: a CSV showing the expected columns -------
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enrolled_students_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Full Name', 'Department', 'Program']);
    fputcsv($out, ['2023-01721', 'Juan Dela Cruz', 'DCS', 'BS Computer Science']);
    fputcsv($out, ['2023-01722', 'Maria Santos', 'DIT', 'BS Information Technology']);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
        redirect('enrolled_students.php');
    }

    if (isset($_POST['upload_list'])) {
        $file = $_FILES['list_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $_SESSION['message'] = 'Choose a file to upload.';
            $_SESSION['message_type'] = 'danger';
            redirect('enrolled_students.php');
        }
        $mode = ($_POST['mode'] ?? '') === 'replace' ? 'replace' : 'add';
        // No redirect on purpose: the import summary is shown on this response.
        $importResult = $enrollment->import($file['tmp_name'], $file['name'], $mode, (int)$_SESSION['user_id']);
    } elseif (isset($_POST['add_one'])) {
        $result = $enrollment->addOne($_POST['student_id'] ?? '', $_POST['full_name'] ?? '', $_POST['department'] ?? '', (int)$_SESSION['user_id']);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        redirect('enrolled_students.php');
    } elseif (isset($_POST['remove'])) {
        $result = $enrollment->remove((int)($_POST['enrolled_id'] ?? 0));
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        redirect('enrolled_students.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'department', 'registered', 'page']))));
    }
}

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'department' => in_array($_GET['department'] ?? '', departments(), true) ? $_GET['department'] : '',
    'registered' => in_array($_GET['registered'] ?? '', ['yes', 'no'], true) ? $_GET['registered'] : '',
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$list = $enrollment->search($filters, $page, $perPage);
$totalPages = max(1, (int)ceil($list['total'] / $perPage));
$stats = $enrollment->stats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Enrolled students - ' . SITE_NAME; include 'includes/head.php'; ?>
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
                    <h1 class="h2">Enrolled students</h1>
                    <p class="text-muted mb-0">Only Student IDs on this list can register for an account.</p>
                </div>
                <a href="enrolled_students.php?template=1" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>Download template
                </a>
            </div>

            <?php if ($importResult): ?>
                <!-- Upload summary -->
                <?php if ($importResult['success']): ?>
                    <div class="alert alert-success" role="status">
                        <h2 class="h6"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                            List <?php echo $importResult['mode'] === 'replace' ? 'replaced' : 'updated'; ?></h2>
                        <ul class="mb-0 small">
                            <li><?php echo $importResult['valid']; ?> valid Student ID(s) in the file</li>
                            <li><?php echo $importResult['added']; ?> added, <?php echo $importResult['updated']; ?> updated, <?php echo $importResult['unchanged']; ?> already on the list</li>
                            <?php if ($importResult['duplicates']): ?><li><?php echo $importResult['duplicates']; ?> duplicate row(s) in the file were ignored</li><?php endif; ?>
                        </ul>
                    </div>
                    <?php if ($importResult['invalid']): ?>
                        <div class="alert alert-warning small" role="status">
                            <strong><?php echo count($importResult['invalid']); ?> row(s) skipped</strong> because the Student ID was empty or contained characters other than letters, numbers and dashes:
                            <?php echo htmlspecialchars(implode(', ', array_slice($importResult['invalid'], 0, 15))); ?><?php echo count($importResult['invalid']) > 15 ? ', …' : ''; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($importResult['message']); ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="row g-3 mb-4 nemsu-stat-grid">
                <div class="col-12 col-md-4">
                    <div class="card nemsu-stat-card nemsu-stat-card--blue h-100">
                        <div class="card-body">
                            <i class="bi bi-person-vcard stat-icon" aria-hidden="true"></i>
                            <h2 class="h6 card-title">Enrolled IDs</h2>
                            <p class="display-6 mb-0"><?php echo $stats['total']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card nemsu-stat-card nemsu-stat-card--success h-100">
                        <div class="card-body">
                            <i class="bi bi-person-check stat-icon" aria-hidden="true"></i>
                            <h2 class="h6 card-title">Registered</h2>
                            <p class="display-6 mb-0"><?php echo $stats['registered']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card nemsu-stat-card nemsu-stat-card--gold h-100">
                        <div class="card-body">
                            <i class="bi bi-hourglass-split stat-icon" aria-hidden="true"></i>
                            <h2 class="h6 card-title">Not yet registered</h2>
                            <p class="display-6 mb-0"><?php echo $stats['not_registered']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Upload a file -->
                <div class="col-lg-7">
                    <form method="POST" enctype="multipart/form-data" class="card nemsu-panel h-100">
                        <?php echo csrfField(); ?>
                        <div class="card-header"><i class="bi bi-upload me-2" aria-hidden="true"></i>Upload list</div>
                        <div class="card-body">
                            <label for="list_file" class="form-label">File (.csv, .xlsx or .txt, up to 10 MB)</label>
                            <input type="file" class="form-control" id="list_file" name="list_file" accept=".csv,.xlsx,.txt" required>
                            <div class="form-text mb-3">
                                Use a column named <strong>Student ID</strong> (optional: Full Name, Department, Program),
                                or put one ID per line with no header. In Excel, format the ID column as <em>Text</em> so leading zeros are kept.
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode_add" value="add" checked>
                                <label class="form-check-label" for="mode_add"><strong>Add to the list</strong> — keep current IDs, add new ones</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode_replace" value="replace">
                                <label class="form-check-label" for="mode_replace"><strong>Replace the list</strong> — e.g. for a new semester. Existing accounts are not affected.</label>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-end">
                            <!-- Replacing the whole list asks for confirmation first (app.js) -->
                            <button type="submit" name="upload_list" value="1" class="btn btn-primary"
                                    data-confirm-when="mode_replace"
                                    data-confirm-when-message="Replace the entire enrolled list with this file? IDs not in the file will no longer be able to register.">
                                <i class="bi bi-upload me-1" aria-hidden="true"></i>Upload
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Add one ID by hand -->
                <div class="col-lg-5">
                    <form method="POST" class="card nemsu-panel h-100">
                        <?php echo csrfField(); ?>
                        <div class="card-header"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Add one student</div>
                        <div class="card-body row g-2">
                            <div class="col-12">
                                <label for="one_id" class="form-label small">Student ID *</label>
                                <input type="text" class="form-control form-control-sm" id="one_id" name="student_id" required maxlength="50" placeholder="2023-01721">
                            </div>
                            <div class="col-md-7">
                                <label for="one_name" class="form-label small">Full name</label>
                                <input type="text" class="form-control form-control-sm" id="one_name" name="full_name">
                            </div>
                            <div class="col-md-5">
                                <label for="one_dept" class="form-label small">Department</label>
                                <select class="form-select form-select-sm" id="one_dept" name="department">
                                    <option value="">—</option>
                                    <?php foreach (departments() as $dept): ?><option value="<?php echo $dept; ?>"><?php echo $dept; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-end">
                            <button type="submit" name="add_one" value="1" class="btn btn-outline-primary">Add</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="card nemsu-panel mb-3">
                <div class="card-body row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="q" class="form-label small">Search</label>
                        <input type="search" class="form-control form-control-sm" id="q" name="q" placeholder="Student ID or name" value="<?php echo htmlspecialchars($filters['q']); ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="fdept" class="form-label small">Department</label>
                        <select class="form-select form-select-sm" id="fdept" name="department">
                            <option value="">All</option>
                            <?php foreach (departments() as $dept): ?>
                                <option value="<?php echo $dept; ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="freg" class="form-label small">Account</label>
                        <select class="form-select form-select-sm" id="freg" name="registered">
                            <option value="">All</option>
                            <option value="yes" <?php echo $filters['registered'] === 'yes' ? 'selected' : ''; ?>>Registered</option>
                            <option value="no" <?php echo $filters['registered'] === 'no' ? 'selected' : ''; ?>>Not yet registered</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="enrolled_students.php">Reset</a>
                    </div>
                </div>
            </form>

            <!-- The list -->
            <div class="card nemsu-panel">
                <div class="card-header"><i class="bi bi-list-ul me-2" aria-hidden="true"></i>Enrolled students (<?php echo $list['total']; ?>)</div>
                <div class="card-body p-0">
                    <?php if (!$list['rows']): ?>
                        <?php echo $stats['total'] === 0
                            ? renderEmptyState('person-vcard', 'No list uploaded yet', 'Until a list is uploaded, no new student can register.')
                            : renderEmptyState('search', 'No matches', 'Try different filters.'); ?>
                    <?php else: ?>
                        <div class="table-responsive nemsu-table-wrap">
                            <table class="table table-hover align-middle mb-0 nemsu-table nemsu-table--stack">
                                <thead>
                                    <tr><th>Student ID</th><th>Name</th><th>Department</th><th>Program</th><th>Account</th><th class="text-end nemsu-stack-full"></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($list['rows'] as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['full_name'] ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($row['department'] ?: '—'); ?></td>
                                            <td><small><?php echo htmlspecialchars($row['program'] ?: '—'); ?></small></td>
                                            <td>
                                                <?php if ($row['registered_username']): ?>
                                                    <span class="badge bg-success">Registered</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="enrolled_id" value="<?php echo (int)$row['enrolled_id']; ?>">
                                                    <button type="submit" name="remove" value="1" class="btn btn-sm btn-outline-danger"
                                                            data-confirm-message="Remove <?php echo htmlspecialchars($row['student_id'], ENT_QUOTES); ?> from the enrolled list? They will not be able to register. An existing account is not affected.">
                                                        <i class="bi bi-x-lg" aria-hidden="true"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white">
                        <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="Enrolled students pagination">
                            <span class="small text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page - 1]))); ?>">Previous</a>
                                </li>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page + 1]))); ?>">Next</a>
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
