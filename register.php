<?php
require_once 'config/config.php';
if (isLoggedIn()) redirect('dashboard.php');

if (isset($_POST['register'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $_SESSION['message'] = 'Passwords do not match!';
        $_SESSION['message_type'] = 'danger';
    } else {
        $data = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
            'role' => 'student',
            'student_id' => sanitizeInput($_POST['student_id'] ?? ''),
            'department' => sanitizeInput($_POST['department'] ?? ''),
            'program' => sanitizeInput($_POST['program'] ?? ''),
            'contact_number' => sanitizeInput($_POST['contact_number'] ?? '')
        ];
        
        $user = new User();
        $result = $user->register($data);
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
        if ($result['success']) redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - OSWD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">Register Account</h3>
                        
                        <?php if (isset($_SESSION['message'])): ?>
                            <?php echo showAlert($_SESSION['message'], $_SESSION['message_type'] ?? 'info'); ?>
                            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php">
                            <?php echo csrfField(); ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" minlength="3" maxlength="50" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" name="student_id">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Program</label>
                                    <input type="text" class="form-control" name="program">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" minlength="6" required>
                                    <small class="text-muted">At least 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
                        </form>
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

