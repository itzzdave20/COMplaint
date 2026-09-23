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
    $complaint = new Complaint();
    $mlClassifier = new MLClassifier();
    
    // Handle file upload
    $supportingDocs = null;
    if (isset($_FILES['supporting_documents']) && $_FILES['supporting_documents']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['supporting_documents']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['supporting_documents']['tmp_name'], $targetPath)) {
            $supportingDocs = $fileName;
        }
    }
    
    // Classify complaint using ML
    $complaintText = $_POST['complaint_title'] . ' ' . $_POST['complaint_description'];
    $mlResult = $mlClassifier->classifyComplaint($complaintText);
    
    $data = [
        'complainant_id' => $_SESSION['user_id'],
        'respondent_name' => sanitizeInput($_POST['respondent_name']),
        'respondent_type' => sanitizeInput($_POST['respondent_type']),
        'complaint_title' => sanitizeInput($_POST['complaint_title']),
        'complaint_description' => sanitizeInput($_POST['complaint_description']),
        'complaint_category' => sanitizeInput($_POST['complaint_category']),
        'predicted_category' => $mlResult['category'] ?? null,
        'incident_date' => $_POST['incident_date'],
        'incident_location' => sanitizeInput($_POST['incident_location']),
        'severity' => $_POST['severity'],
        'supporting_documents' => $supportingDocs
    ];
    
    $result = $complaint->submitComplaint($data);
    
    if ($result['success']) {
        $_SESSION['message'] = 'Complaint submitted successfully!';
        $_SESSION['message_type'] = 'success';
        redirect('view_complaint.php?id=' . $result['complaint_id']);
    } else {
        $_SESSION['message'] = 'Failed to submit complaint: ' . $result['message'];
        $_SESSION['message_type'] = 'danger';
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
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="submit_complaint.php" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Complaint Title *</label>
                                    <input type="text" class="form-control" name="complaint_title" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Complaint Category *</label>
                                    <select class="form-control" name="complaint_category" required>
                                        <option value="">Select Category</option>
                                        <option value="Unprofessional Behavior">Unprofessional Behavior</option>
                                        <option value="Bullying">Bullying</option>
                                        <option value="Harassment">Harassment</option>
                                        <option value="Discrimination">Discrimination</option>
                                        <option value="Academic Misconduct">Academic Misconduct</option>
                                        <option value="Other">Other</option>
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
