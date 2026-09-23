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
            $sql = "INSERT INTO users (username, email, password, full_name, role, student_id, 
                    department, program, contact_number) 
                    VALUES (:username, :email, :password, :full_name, :role, :student_id, 
                    :department, :program, :contact_number)";
            
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            
            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password' => $hashedPassword,
                ':full_name' => $data['full_name'],
                ':role' => $data['role'],
                ':student_id' => $data['student_id'] ?? null,
                ':department' => $data['department'] ?? null,
                ':program' => $data['program'] ?? null,
                ':contact_number' => $data['contact_number'] ?? null
            ]);
            
            return ['success' => true, 'message' => 'Registration successful!'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
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
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                
                return ['success' => true, 'message' => 'Login successful!', 'user' => $user];
            }
            return ['success' => false, 'message' => 'Invalid credentials!'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
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
                ':full_name' => $data['full_name'],
                ':email' => $data['email'],
                ':contact_number' => $data['contact_number'] ?? null,
                ':department' => $data['department'] ?? null,
                ':program' => $data['program'] ?? null,
                ':user_id' => $userId
            ]);
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
