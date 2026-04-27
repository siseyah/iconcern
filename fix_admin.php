<?php
/**
 * Fix Admin Account Script
 * Run this to reset/create the admin account
 * Access: http://localhost/iconcern/fix_admin.php
 */

require_once 'config/config.php';

// Only allow access if not in production (or add password protection)
$admin_password_hash = password_hash('admin123', PASSWORD_BCRYPT);

try {
    $db = getDB();
    
    // Check if admin exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = 'admin' OR email = 'admin@nwssu.edu.ph'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Update existing admin password
        $stmt = $db->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = 'admin' OR email = 'admin@nwssu.edu.ph'");
        $stmt->execute([$admin_password_hash]);
        echo "<h2>✓ Admin account password has been reset!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    } else {
        // Create new admin account
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute(['admin', $admin_password_hash, 'System Administrator', 'admin@nwssu.edu.ph']);
        echo "<h2>✓ Admin account created successfully!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Make sure the database is set up correctly.</p>";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Admin Account</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        h2 { color: #2563eb; }
        p { line-height: 1.6; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
</body>
</html>

