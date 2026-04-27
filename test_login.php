<?php
/**
 * Test Login Script - Diagnose login issues
 * Access: http://localhost/iconcern/test_login.php
 */

require_once 'config/config.php';

echo "<h2>Login Diagnostic Test</h2>";
echo "<hr>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $db = getDB();
    echo "✓ Database connection successful<br>";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Check if admin user exists
echo "<h3>2. Admin User Check</h3>";
try {
    $stmt = $db->prepare("SELECT user_id, username, email, role, password FROM users WHERE username = 'admin' OR email = 'admin@nwssu.edu.ph'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✓ Admin user found:<br>";
        echo "- Username: " . htmlspecialchars($admin['username']) . "<br>";
        echo "- Email: " . htmlspecialchars($admin['email']) . "<br>";
        echo "- Role: " . htmlspecialchars($admin['role']) . "<br>";
        echo "- Password Hash: " . substr($admin['password'], 0, 20) . "...<br>";
    } else {
        echo "✗ Admin user NOT found in database<br>";
        echo "<strong>Solution:</strong> Run fix_admin.php to create the admin account<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking admin: " . $e->getMessage() . "<br>";
}

// Test password verification
echo "<h3>3. Password Verification Test</h3>";
if (isset($admin)) {
    $test_password = 'admin123';
    $is_valid = password_verify($test_password, $admin['password']);
    
    if ($is_valid) {
        echo "✓ Password 'admin123' is VALID for admin account<br>";
    } else {
        echo "✗ Password 'admin123' is INVALID for admin account<br>";
        echo "<strong>Solution:</strong> Run fix_admin.php to reset the password<br>";
        
        // Test with new hash
        $new_hash = password_hash($test_password, PASSWORD_BCRYPT);
        echo "<br>New hash generated: " . substr($new_hash, 0, 30) . "...<br>";
        echo "Verification with new hash: " . (password_verify($test_password, $new_hash) ? "✓ Valid" : "✗ Invalid") . "<br>";
    }
}

// Test login function
echo "<h3>4. Login Function Test</h3>";
require_once 'includes/auth.php';
$auth = new Auth();
$result = $auth->login('admin', 'admin123');

if ($result['success']) {
    echo "✓ Login function works correctly!<br>";
    echo "- User ID: " . $result['user']['user_id'] . "<br>";
    echo "- Username: " . htmlspecialchars($result['user']['username']) . "<br>";
    echo "- Role: " . htmlspecialchars($result['user']['role']) . "<br>";
} else {
    echo "✗ Login function failed: " . $result['message'] . "<br>";
}

// Check session
echo "<h3>5. Session Test</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✓ Session is active<br>";
    if (isset($_SESSION['user_id'])) {
        echo "- Session user_id: " . $_SESSION['user_id'] . "<br>";
        echo "- Session role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
    } else {
        echo "- No user session data<br>";
    }
} else {
    echo "✗ Session is not active<br>";
}

echo "<hr>";
echo "<h3>Quick Fix</h3>";
echo "<p><a href='fix_admin.php' style='display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Fix Admin Account</a></p>";
echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h2 { color: #2563eb; }
        h3 { color: #1e293b; margin-top: 2rem; }
        .test-result { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
</body>
</html>

