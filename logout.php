<?php
require_once 'config/config.php';

// Mark the user as signed out so User management shows "Inactive" at once.
if (isLoggedIn()) {
    try {
        Database::getInstance()->getConnection()
            ->prepare('UPDATE users SET is_signed_in = 0 WHERE user_id = :id')
            ->execute([':id' => (int)$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Never block logging out.
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header('Location: ' . SITE_URL . '/login.php');
exit;

