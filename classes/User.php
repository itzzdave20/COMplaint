<?php
/**
 * User Class - Handles authentication and user management
 */
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function register($data) {
        try {
            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $fullName = trim($data['full_name'] ?? '');
            $role = $data['role'] ?? 'student';

            if ($username === '' || $email === '' || $fullName === '' || $password === '') {
                return ['success' => false, 'message' => 'Please fill in all required fields.'];
            }

            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
                return ['success' => false, 'message' => 'Username must be 3-50 characters and contain only letters, numbers, dots, underscores, or hyphens.'];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Please enter a valid email address.'];
            }

            // Registration can be closed by the Super Admin (Settings).
            if (!REGISTRATION_OPEN || MAINTENANCE_MODE) {
                return ['success' => false, 'message' => 'Student registration is currently closed.'];
            }

            // Only students on OSWD's enrolled list may register, and each
            // Student ID can have one account. This is checked here (not only
            // in register.php) so no other route can skip it.
            $enrollment = new EnrollmentList();
            $check = $enrollment->verifyForRegistration($data['student_id'] ?? '');
            if (!$check['ok']) {
                return ['success' => false, 'message' => $check['message']];
            }
            $data['student_id'] = EnrollmentList::normalizeId($data['student_id']);
            // If OSWD's list gives the department, it wins over what was typed.
            if (!empty($check['record']['department'])) {
                $data['department'] = $check['record']['department'];
            }

            // Complaints are routed by the student's department, so it must
            // be one of the official departments (chosen from a dropdown).
            if (!in_array($data['department'] ?? '', departments(), true)) {
                return ['success' => false, 'message' => 'Please choose your department.'];
            }

            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
            }

            // Public registration is limited to students to prevent privilege escalation
            if ($role !== 'student') {
                $role = 'student';
            }

            // Every student gets a random alias so personnel never see the real name.
            $sql = "INSERT INTO users (username, email, password, full_name, alias, role, student_id,
                    department, program, contact_number)
                    VALUES (:username, :email, :password, :full_name, :alias, :role, :student_id,
                    :department, :program, :contact_number)";
            
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':full_name' => $fullName,
                ':alias' => $this->generateAlias(),
                ':role' => $role,
                ':student_id' => emptyToNull($data['student_id'] ?? null),
                ':department' => emptyToNull($data['department'] ?? null),
                ':program' => emptyToNull($data['program'] ?? null),
                ':contact_number' => emptyToNull($data['contact_number'] ?? null)
            ]);
            
            return ['success' => true, 'message' => 'Registration successful! You can now log in.'];
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                return ['success' => false, 'message' => 'Username or email already exists.'];
            }
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    public function login($username, $password) {
        $auth = new LoginAuth();
        return $auth->attempt($username, $password);
    }
    
    public function getUserById($userId) {
        try {
            $sql = "SELECT * FROM users WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    public function getAllUsers() {
        try {
            $sql = "SELECT user_id, username, email, full_name, role, student_id, department, program, contact_number, status, created_at
                    FROM users ORDER BY created_at DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function deleteUser($targetUserId, $actorUserId) {
        $targetUserId = (int)$targetUserId;
        $actorUserId = (int)$actorUserId;

        if ($targetUserId <= 0) {
            return ['success' => false, 'message' => 'Invalid user account.'];
        }

        if ($targetUserId === $actorUserId) {
            return ['success' => false, 'message' => 'You cannot delete your own account while signed in.'];
        }

        $user = $this->getUserById($targetUserId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        // Only the Super Admin manages Super Admin accounts (see super_admin_users.php).
        if (($user['role'] ?? '') === 'super_admin') {
            return ['success' => false, 'message' => 'Super Admin accounts cannot be deleted here.'];
        }

        if (($user['role'] ?? '') === 'oswd') {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM users WHERE role = 'oswd' AND status = 'active'"
            );
            if ((int)$stmt->fetchColumn() <= 1) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete the last active OSWD administrator account.',
                ];
            }
        }

        try {
            $stmt = $this->db->prepare('DELETE FROM users WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $targetUserId]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'User could not be deleted.'];
            }

            return [
                'success' => true,
                'message' => 'Account for ' . ($user['username'] ?? 'user') . ' was deleted.',
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete account. Please try again or contact support.',
            ];
        }
    }

    /**
     * Random, unique student alias such as "Student-7F3KQ2".
     * Characters 0/O/1/I are left out so aliases are easy to read aloud.
     */
    public function generateAlias() {
        $check = $this->db->prepare('SELECT 1 FROM users WHERE alias = :alias');
        do {
            $alias = 'Student-';
            for ($i = 0; $i < 6; $i++) {
                $alias .= '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)];
            }
            $check->execute([':alias' => $alias]);
        } while ($check->fetch());
        return $alias;
    }

    public function updateProfile($userId, $data) {
        try {
            $current = $this->getUserById($userId);
            $department = emptyToNull($data['department'] ?? null);
            $program = emptyToNull($data['program'] ?? null);

            if (($current['role'] ?? '') !== 'student') {
                // Personnel department/program decide which complaints they
                // receive, so only the Super Admin may change them.
                $department = $current['department'] ?? null;
                $program = $current['program'] ?? null;
            } elseif (!in_array($department, departments(), true)) {
                return ['success' => false, 'message' => 'Please choose your department from the list.'];
            }

            $sql = "UPDATE users SET full_name = :full_name, email = :email, contact_number = :contact_number,
                    department = :department, program = :program WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':full_name' => trim($data['full_name'] ?? ''),
                ':email' => trim($data['email'] ?? ''),
                ':contact_number' => emptyToNull($data['contact_number'] ?? null),
                ':department' => $department,
                ':program' => $program,
                ':user_id' => $userId
            ]);
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                return ['success' => false, 'message' => 'Email is already in use by another account.'];
            }
            return ['success' => false, 'message' => 'Failed to update profile.'];
        }
    }
}
?>
