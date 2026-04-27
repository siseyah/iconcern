<?php
/**
 * Main Configuration File
 * iconcern - System Configuration
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/database.php';

// Application settings
define('APP_NAME', 'iconcern');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/iconcern/');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// SVM Model Settings
define('SVM_MODEL_PATH', __DIR__ . '/../ml/svm_model.pkl');
define('SVM_SCRIPT_PATH', __DIR__ . '/../ml/classify.py');

// Security settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array(getUserRole(), (array)$allowedRoles)) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('M d, Y h:i A', strtotime($date));
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

