<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

if (isset($_POST['login'])) {
    if (!verifyCsrf()) {
        $_SESSION['message'] = 'Invalid request. Please try again.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $user = new User();
        $result = $user->login($username, $password);
        
        if ($result['success']) {
            redirect('dashboard.php');
        } else {
            $_SESSION['message'] = $result['message'];
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
    <title>OSWD Complaint System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">OSWD Complaint System</h3>
                        <h5 class="text-center mb-4">Login</h5>
                        
                        <?php if (isset($_SESSION['message'])): ?>
                            <?php echo showAlert($_SESSION['message'], $_SESSION['message_type'] ?? 'info'); ?>
                            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="login.php">
                            <?php echo csrfField(); ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>Don't have an account? <a href="register.php">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

