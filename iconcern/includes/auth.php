<?php
/**
 * Authentication Handler
 * Handles user login, registration, and session management
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Register a new user
     */
    public function register($username, $password, $full_name, $email, $role = 'student', $college_id = null, $department_id = null) {
        // Validate input
        if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        // Check if username or email already exists
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, password, full_name, email, role, college_id, department_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $college_id, $department_id]);
            
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     */
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        try {
            $stmt = $this->db->prepare("SELECT user_id, username, password, full_name, email, role, college_id, department_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'User not found. Please check your username or email.'];
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid password. Please check your password.'];
            }

            // Ensure session is started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['college_id'] = $user['college_id'];
            $_SESSION['department_id'] = $user['department_id'];
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!isLoggedIn()) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT u.*, c.college_name, d.department_name 
                                     FROM users u 
                                     LEFT JOIN colleges c ON u.college_id = c.college_id 
                                     LEFT JOIN departments d ON u.department_id = d.department_id 
                                     WHERE u.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}

