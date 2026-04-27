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

    private function ensurePreRegisteredStudentsTableExists(): void {
        // Create table if missing (prevents fatal errors on fresh DBs).
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pre_registered_students (
                student_id VARCHAR(10) PRIMARY KEY,
                full_name VARCHAR(120) NOT NULL,
                course_year_level VARCHAR(160) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed/Upsert directory entries (safe to re-run).
        $seed = [
            ['22-00482', 'Jerico B. Jadaone', 'College of Computing and Information Science (CCIS)'],
            ['22-22021', 'Czea Dawn J. Lunas', 'College of Education (COED)'],
            ['22-22001', 'Aira M. Lungsod', 'College of Nursing (CON)'],
            ['22-22113', 'Rosemarie M. Magallem', 'College Of Engineering and Architecture (CEA)'],
            ['22-22003', 'Frances Ivan Batican', 'College of Criminal Justice Studies (CCJS)'],
        ];

        $ins = $this->db->prepare("
            INSERT INTO pre_registered_students (student_id, full_name, course_year_level)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                course_year_level = VALUES(course_year_level)
        ");
        foreach ($seed as $row) {
            try {
                $ins->execute($row);
            } catch (PDOException $e) {
                // Ignore seed failures.
            }
        }
    }

    /**
     * Register a new user
     */
    public function register($username, $password, $full_name, $email, $role = 'student', $college_id = null, $department_id = null) {
        // Validate input
        if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        // Student ID format enforcement when registering students (username is student_id).
        if ($role === 'student') {
            $studentId = trim((string)$username);
            if (!preg_match('/^\d{2}-\d{5}$/', $studentId)) {
                return ['success' => false, 'message' => 'Student ID must be in the format 22-00000'];
            }
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
            return ['success' => false, 'message' => 'Student ID and password are required'];
        }

        try {
            // Login is student-id (username) based. Do not allow email login.
            $stmt = $this->db->prepare("SELECT user_id, username, password, full_name, email, role, college_id, department_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'User not found. Please check your Student ID.'];
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
     * Student-only login (Student ID + password).
     */
    public function loginStudent($student_id, $password) {
        $sid = trim((string)$student_id);
        if ($sid === '' || $password === '') {
            return ['success' => false, 'message' => 'Student ID and password are required'];
        }
        // If account not activated yet, give a clear message.
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$sid]);
        $exists = $stmt->fetch();
        if (!$exists) {
            $dir = $this->getPreRegisteredStudent($sid);
            if ($dir) {
                return ['success' => false, 'message' => 'Account not activated yet. Please activate your account first.'];
            }
            return ['success' => false, 'message' => 'Student ID not found in the pre-registered list.'];
        }

        $res = $this->login($sid, $password);
        if (!$res['success']) {
            return $res;
        }
        if (($res['user']['role'] ?? null) !== 'student') {
            $this->logout();
            return ['success' => false, 'message' => 'This login is for students only.'];
        }
        return $res;
    }

    /**
     * Admin/Staff login (username + password).
     */
    public function loginAdmin($username, $password) {
        $u = trim((string)$username);
        if ($u === '' || $password === '') {
            return ['success' => false, 'message' => 'Username and password are required'];
        }
        $res = $this->login($u, $password);
        if (!$res['success']) {
            return $res;
        }
        $role = (string)($res['user']['role'] ?? '');
        if ($role !== 'admin' && $role !== 'staff') {
            $this->logout();
            return ['success' => false, 'message' => 'This login is for admin/staff only.'];
        }
        return $res;
    }

    /**
     * Directory lookup for pre-registered students.
     */
    public function getPreRegisteredStudent($student_id) {
        $sid = trim((string)$student_id);
        if ($sid === '') {
            return null;
        }
        try {
            $this->ensurePreRegisteredStudentsTableExists();
            $stmt = $this->db->prepare("SELECT student_id, full_name, course_year_level FROM pre_registered_students WHERE student_id = ?");
            $stmt->execute([$sid]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
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

