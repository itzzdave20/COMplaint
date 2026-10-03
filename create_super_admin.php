<?php
/**
 * Create a Super Admin account from the command line.
 *
 *   C:\xampp\php\php.exe create_super_admin.php
 *
 * Asks for username, email, full name and password. The password is typed
 * hidden, entered twice, and checked with passwordPolicyErrors() (the same
 * rules used when the Super Admin creates accounts in the browser).
 *
 * Nothing is hardcoded: no default username or password exists anywhere.
 * The script refuses to run from a web browser, so it cannot be abused
 * remotely to create an administrator.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/config.php';

$db = Database::getInstance()->getConnection();

// The migration must have run first, otherwise 'super_admin' is not a valid role.
$roleColumn = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
if (!$roleColumn || strpos($roleColumn['Type'], "'super_admin'") === false) {
    fwrite(STDERR, "Run the migration first:  php migrate_super_admin.php\n");
    exit(1);
}

echo "=== Create Super Admin account ===\n\n";

/** Read one visible line from the keyboard. */
function ask($prompt) {
    echo $prompt;
    return trim((string)fgets(STDIN));
}

/**
 * Read a line WITHOUT showing what is typed.
 * Windows: PowerShell's Read-Host -AsSecureString shows * for each key.
 * Linux/macOS: turn terminal echo off with stty while reading.
 */
function askHidden($prompt) {
    if (PHP_OS_FAMILY === 'Windows') {
        $ps = '$p = Read-Host -AsSecureString ' . escapeshellarg($prompt) . ';'
            . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
            . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))';
        $value = shell_exec('powershell -NoProfile -Command "' . str_replace('"', '\"', $ps) . '"');
        return rtrim((string)$value, "\r\n");
    }
    echo $prompt . ': ';
    shell_exec('stty -echo');
    $value = rtrim((string)fgets(STDIN), "\r\n");
    shell_exec('stty echo');
    echo "\n";
    return $value;
}

// ---- Username -------------------------------------------------------
while (true) {
    $username = ask('Username: ');
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        echo "  Use 3-50 letters, numbers, dots, underscores or hyphens.\n";
        continue;
    }
    $taken = $db->prepare('SELECT 1 FROM users WHERE username = :u');
    $taken->execute([':u' => $username]);
    if ($taken->fetch()) {
        echo "  That username is already taken.\n";
        continue;
    }
    break;
}

// ---- Email ----------------------------------------------------------
// A real, reachable address is needed: the Random Forest login check may
// send a 6-digit verification code here.
while (true) {
    $email = ask('Email: ');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "  Enter a valid email address.\n";
        continue;
    }
    $taken = $db->prepare('SELECT 1 FROM users WHERE email = :e');
    $taken->execute([':e' => $email]);
    if ($taken->fetch()) {
        echo "  That email is already used by another account.\n";
        continue;
    }
    break;
}

// ---- Full name ------------------------------------------------------
while (($fullName = ask('Full name: ')) === '') {
    echo "  Full name is required.\n";
}

// ---- Password (hidden, twice, validated) ----------------------------
while (true) {
    $password = askHidden('Password');
    $errors = passwordPolicyErrors($password, $username, $email);
    if ($errors) {
        echo "  Password not accepted:\n";
        foreach ($errors as $error) {
            echo "   - {$error}\n";
        }
        continue;
    }
    if (askHidden('Password (again)') !== $password) {
        echo "  The passwords did not match. Try again.\n";
        continue;
    }
    break;
}

// ---- Save -----------------------------------------------------------
$stmt = $db->prepare(
    "INSERT INTO users (username, email, password, full_name, role, status)
     VALUES (:username, :email, :password, :full_name, 'super_admin', 'active')"
);
$stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password' => password_hash($password, PASSWORD_BCRYPT),   // never stored in plain text
    ':full_name' => $fullName,
]);
$newId = (int)$db->lastInsertId();

// Record the creation in the audit log. There is no logged-in user here,
// so the actor is recorded as the command line.
(new AuditLog())->record(
    'super_admin_cli', 'user', $newId, $username, null,
    ['email' => $email, 'full_name' => $fullName],
    ['id' => null, 'username' => 'cli']
);

echo "\nSuper Admin '{$username}' created.\n";
echo "Log in at the normal login page. You will be taken to the Super Admin dashboard.\n";
