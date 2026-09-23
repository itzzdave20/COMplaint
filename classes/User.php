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

            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
            }

            // Public registration is limited to students to prevent privilege escalation
            if ($role !== 'student') {
                $role = 'student';
            }

            $sql = "INSERT INTO users (username, email, password, full_name, role, student_id, 
                    department, program, contact_number) 
                    VALUES (:username, :email, :password, :full_name, :role, :student_id, 
                    :department, :program, :contact_number)";
            
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':full_name' => $fullName,
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
        try {
            $sql = "SELECT * FROM users WHERE (username = :username OR email = :email) 
                    AND status = 'active' LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':username' => $username, ':email' => $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                
                return ['success' => true, 'message' => 'Login successful!', 'user' => $user];
            }
            return ['success' => false, 'message' => 'Invalid credentials!'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login error. Please try again.'];
        }
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
    
    public function updateProfile($userId, $data) {
        try {
            $sql = "UPDATE users SET full_name = :full_name, email = :email, contact_number = :contact_number,
                    department = :department, program = :program WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':full_name' => trim($data['full_name'] ?? ''),
                ':email' => trim($data['email'] ?? ''),
                ':contact_number' => emptyToNull($data['contact_number'] ?? null),
                ':department' => emptyToNull($data['department'] ?? null),
                ':program' => emptyToNull($data['program'] ?? null),
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
