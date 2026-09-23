<?php
require_once 'config/config.php';
requireLogin();

$userObj = new User();

if (isset($_POST['update_profile'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = 'Please provide a valid name and email address.';
            $_SESSION['message_type'] = 'danger';
        } else {
            $data = [
                'full_name' => $fullName,
                'email' => $email,
                'contact_number' => sanitizeInput($_POST['contact_number'] ?? ''),
                'department' => sanitizeInput($_POST['department'] ?? ''),
                'program' => sanitizeInput($_POST['program'] ?? '')
            ];
            $result = $userObj->updateProfile($_SESSION['user_id'], $data);
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = $result['success'] ? 'success' : 'danger';
            if ($result['success']) {
                $_SESSION['full_name'] = $data['full_name'];
                $_SESSION['email'] = $data['email'];
            }
        }
    }
}

$user = $userObj->getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - OSWD</title>
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
                    <h1 class="h2">My Profile</h1>
                </div>
                <?php if (isset($_SESSION['message'])): ?>
                    <?php echo showAlert($_SESSION['message'], $_SESSION['message_type'] ?? 'info'); ?>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(formatStatus($user['role'])); ?>" disabled>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Program</label>
                                <input type="text" class="form-control" name="program" value="<?php echo htmlspecialchars($user['program'] ?? ''); ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
