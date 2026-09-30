<?php
require_once 'config/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    $_SESSION['message'] = 'Only students can submit complaints';
    $_SESSION['message_type'] = 'danger';
    redirect('dashboard.php');
}

$successId = isset($_GET['success']) ? (int)$_GET['success'] : 0;

if (isset($_POST['submit_complaint'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $complaint = new Complaint();
        $errors = [];

        $title = sanitizeInput($_POST['complaint_title'] ?? '');
        $description = sanitizeInput($_POST['complaint_description'] ?? '');
        $category = sanitizeInput($_POST['complaint_category'] ?? '');
        $respondentName = sanitizeInput($_POST['respondent_name'] ?? '');
        $respondentType = sanitizeInput($_POST['respondent_type'] ?? '');
        $incidentDate = $_POST['incident_date'] ?? '';
        $incidentLocation = sanitizeInput($_POST['incident_location'] ?? '');
        $severity = $_POST['severity'] ?? 'medium';

        if ($title === '' || $description === '') {
            $errors[] = 'Title and description are required.';
        }
        if (!in_array($category, allowedComplaintCategories(), true)) {
            $errors[] = 'Please select a valid complaint category.';
        }
        if ($respondentType !== '' && !in_array($respondentType, allowedRespondentTypes(), true)) {
            $errors[] = 'Please select a valid respondent type.';
        }
        if (!in_array($severity, allowedSeverities(), true)) {
            $severity = 'medium';
        }
        if ($incidentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $incidentDate)) {
            $errors[] = 'Please provide a valid incident date.';
        } elseif (strtotime($incidentDate) > strtotime(date('Y-m-d'))) {
            $errors[] = 'Incident date cannot be in the future.';
        }

        $supportingDocs = null;
        if (isset($_FILES['supporting_documents']) && $_FILES['supporting_documents']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['supporting_documents'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload failed. Please try again.';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'File is too large. Maximum size is 5MB.';
            } else {
                $originalName = basename($file['name']);
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
                    $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $allowedMimes = [
                        'pdf' => ['application/pdf'],
                        'jpg' => ['image/jpeg'],
                        'jpeg' => ['image/jpeg'],
                        'png' => ['image/png'],
                        'doc' => ['application/msword'],
                        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    ];
                    if (!isset($allowedMimes[$ext]) || !in_array($mime, $allowedMimes[$ext], true)) {
                        $errors[] = 'The uploaded file type does not match its extension.';
                    } else {
                        $uploadDir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $supportingDocs = $fileName;
                        } else {
                            $errors[] = 'Failed to save the uploaded file.';
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            $data = [
                'complainant_id' => $_SESSION['user_id'],
                'respondent_name' => $respondentName,
                'respondent_type' => $respondentType,
                'complaint_title' => $title,
                'complaint_description' => $description,
                'complaint_category' => $category,
                'predicted_category' => $category,
                'incident_date' => $incidentDate,
                'incident_location' => $incidentLocation,
                'severity' => $severity,
                'supporting_documents' => $supportingDocs,
            ];

            $result = $complaint->submitComplaint($data);

            if ($result['success']) {
                redirect('submit_complaint.php?success=' . (int)$result['complaint_id']);
            }
            $_SESSION['message'] = 'Failed to submit complaint. Please try again.';
            $_SESSION['message_type'] = 'danger';
        } else {
            $_SESSION['message'] = implode(' ', $errors);
            $_SESSION['message_type'] = 'danger';
        }
    }
}

$pageScripts = ['assets/js/wizard.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Submit Complaint - ' . SITE_NAME; include 'includes/head.php'; ?>
</head>
<body class="app-body">
<?php include 'includes/skip_link.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/flash.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 app-main" id="main-content">
            <div class="pt-3 nemsu-page-header mb-4">
                <h1 class="h2">File a complaint</h1>
                <p class="text-muted mb-0">Three short steps. Your draft is saved locally until you submit.</p>
            </div>

            <?php if ($successId > 0): ?>
                <div class="card nemsu-success-card text-center py-5 px-4">
                    <div class="nemsu-success-card__icon" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></div>
                    <h2 class="h4">Complaint submitted</h2>
                    <p class="text-muted mb-3">Your reference number is</p>
                    <p class="display-6 fw-bold text-primary mb-3">#<?php echo $successId; ?></p>
                    <p class="small text-muted mb-4">Keep this number. You will receive updates in Notifications and on your dashboard.</p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="view_complaint.php?id=<?php echo $successId; ?>" class="btn btn-primary">View your case</a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
                    </div>
                </div>
            <?php else: ?>
                <div id="complaintWizard" data-current-step="1">
                    <ol class="nemsu-wizard-steps mb-4" aria-label="Complaint form progress">
                        <li class="nemsu-wizard-step-indicator is-active" data-wizard-indicator="1"><span>1</span> What happened</li>
                        <li class="nemsu-wizard-step-indicator" data-wizard-indicator="2"><span>2</span> Details</li>
                        <li class="nemsu-wizard-step-indicator" data-wizard-indicator="3"><span>3</span> Review</li>
                    </ol>

                    <div id="wizardErrors" class="alert alert-danger py-2" role="alert" hidden aria-live="assertive"></div>

                    <form method="POST" action="submit_complaint.php" enctype="multipart/form-data" id="submitComplaintForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="complaint_category" id="complaint_category" value="">

                        <section data-wizard-step="1" class="nemsu-wizard-panel">
                            <h2 class="h5 mb-3">What happened?</h2>
                            <p class="text-muted small mb-3">Choose the option that best matches your concern.</p>
                            <div class="row g-3">
                                <?php foreach (complaintCategoryCards() as $card): ?>
                                    <div class="col-md-6">
                                        <div class="nemsu-category-card" tabindex="0" role="button"
                                             data-category="<?php echo htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                             aria-pressed="false">
                                            <i class="bi <?php echo htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8'); ?> nemsu-category-card__icon" aria-hidden="true"></i>
                                            <h3 class="h6 mb-1"><?php echo htmlspecialchars($card['name']); ?></h3>
                                            <p class="small text-muted mb-2"><?php echo htmlspecialchars($card['example']); ?></p>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-signpost-2 me-1" aria-hidden="true"></i><?php echo htmlspecialchars($card['route']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-primary" data-wizard-next>Continue</button>
                            </div>
                        </section>

                        <section data-wizard-step="2" class="nemsu-wizard-panel" hidden>
                            <h2 class="h5 mb-3">Tell us more</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="complaint_title" class="form-label">Short title *</label>
                                    <input type="text" class="form-control" id="complaint_title" name="complaint_title" required maxlength="255">
                                </div>
                                <div class="col-12">
                                    <label for="complaint_description" class="form-label">What happened? *</label>
                                    <textarea class="form-control" id="complaint_description" name="complaint_description" rows="5" required></textarea>
                                    <div class="form-text" id="descCounter">0 characters</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="respondent_name" class="form-label">Respondent name</label>
                                    <input type="text" class="form-control" id="respondent_name" name="respondent_name">
                                </div>
                                <div class="col-md-6">
                                    <label for="respondent_type" class="form-label">Respondent type</label>
                                    <select class="form-select" id="respondent_type" name="respondent_type">
                                        <option value="">Select type</option>
                                        <?php foreach (allowedRespondentTypes() as $rt): ?>
                                            <option value="<?php echo htmlspecialchars($rt); ?>"><?php echo htmlspecialchars(formatStatus($rt)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="incident_date" class="form-label">Incident date *</label>
                                    <input type="date" class="form-control" id="incident_date" name="incident_date" required max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="incident_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="incident_location" name="incident_location">
                                </div>
                                <div class="col-md-4">
                                    <label for="severity" class="form-label">Severity *</label>
                                    <select class="form-select" id="severity" name="severity" required>
                                        <?php foreach (allowedSeverities() as $sev): ?>
                                            <option value="<?php echo htmlspecialchars($sev); ?>" <?php echo $sev === 'medium' ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($sev)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="supporting_documents">Supporting evidence (optional)</label>
                                    <div class="nemsu-file-drop" id="fileDropZone">
                                        <i class="bi bi-cloud-arrow-up fs-3 d-block mb-2" aria-hidden="true"></i>
                                        <p class="mb-2">Drag and drop a file here, or browse</p>
                                        <input type="file" class="form-control" id="supporting_documents" name="supporting_documents"
                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                        <small class="text-muted d-block mt-2">Max 5MB · PDF, JPG, PNG, DOC, DOCX</small>
                                    </div>
                                    <div id="filePreview" class="mt-2"></div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confidential_toggle" disabled>
                                        <label class="form-check-label text-muted" for="confidential_toggle">
                                            Submit confidentially (coming soon)
                                        </label>
                                    </div>
                                    <!-- TODO: wire confidential flag when backend supports restricted visibility -->
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" data-wizard-back>Back</button>
                                <button type="button" class="btn btn-primary" data-wizard-next>Review</button>
                            </div>
                        </section>

                        <section data-wizard-step="3" class="nemsu-wizard-panel" hidden>
                            <h2 class="h5 mb-3">Review and submit</h2>
                            <dl class="nemsu-review-list" id="wizardReview"></dl>
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" data-wizard-back>Back</button>
                                <button type="submit" name="submit_complaint" class="btn btn-primary" id="submitComplaintBtn">Submit complaint</button>
                            </div>
                        </section>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php include 'includes/scripts.php'; ?>
</body>
</html>
