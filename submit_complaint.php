<?php
require_once 'config/config.php';
requireLogin();

// Only students can submit complaints
if ($_SESSION['role'] !== 'student') {
    $_SESSION['message'] = 'Only students can submit complaints';
    $_SESSION['message_type'] = 'danger';
    redirect('dashboard.php');
}

if (isset($_POST['submit_complaint'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $complaint = new Complaint();
        $mlClassifier = new MLClassifier();
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
                        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
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
            $complaintText = $title . ' ' . $description;
            $mlResult = $mlClassifier->classifyComplaint($complaintText);

            $data = [
                'complainant_id' => $_SESSION['user_id'],
                'respondent_name' => $respondentName,
                'respondent_type' => $respondentType,
                'complaint_title' => $title,
                'complaint_description' => $description,
                'complaint_category' => $category,
                'predicted_category' => $mlResult['category'] ?? null,
                'incident_date' => $incidentDate,
                'incident_location' => $incidentLocation,
                'severity' => $severity,
                'supporting_documents' => $supportingDocs
            ];

            $result = $complaint->submitComplaint($data);

            if ($result['success']) {
                $_SESSION['message'] = 'Complaint submitted successfully!';
                $_SESSION['message_type'] = 'success';
                redirect('view_complaint.php?id=' . $result['complaint_id']);
            } else {
                $_SESSION['message'] = 'Failed to submit complaint. Please try again.';
                $_SESSION['message_type'] = 'danger';
            }
        } else {
            $_SESSION['message'] = implode(' ', $errors);
            $_SESSION['message_type'] = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - OSWD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Submit New Complaint</h1>
                </div>
                <?php if (isset($_SESSION['message'])): ?>
                    <?php echo showAlert($_SESSION['message'], $_SESSION['message_type'] ?? 'info'); ?>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="submit_complaint.php" enctype="multipart/form-data">
                            <?php echo csrfField(); ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Complaint Title *</label>
                                    <input type="text" class="form-control" name="complaint_title" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Complaint Category *</label>
                                    <select class="form-control" name="complaint_category" required>
                                        <option value="">Select Category</option>
                                        <option value="Academic Integrity Violation">Academic Integrity Violation</option>
                                        <option value="Unprofessional Behavior">Unprofessional Behavior</option>
                                        <option value="Institutional Rules Violation">Institutional Rules Violation</option>
                                        <option value="Teaching Standards Failure">Teaching Standards Failure</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Complaint Description *</label>
                                <textarea class="form-control" name="complaint_description" rows="5" required></textarea>
                            </div>


                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Respondent Name</label>
                                    <input type="text" class="form-control" name="respondent_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Respondent Type</label>
                                    <select class="form-control" name="respondent_type">
                                        <option value="">Select Type</option>
                                        <option value="student">Student</option>
                                        <option value="faculty">Faculty</option>
                                        <option value="staff">Staff</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Incident Date *</label>
                                    <input type="date" class="form-control" name="incident_date" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Incident Location</label>
                                    <input type="text" class="form-control" name="incident_location">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Severity *</label>
                                    <select class="form-control" name="severity" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Supporting Documents (Optional)</label>
                                <input type="file" class="form-control" name="supporting_documents" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <small class="text-muted">Max file size: 5MB. Allowed: PDF, JPG, PNG, DOC, DOCX</small>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="submit_complaint" class="btn btn-primary">Submit Complaint</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
