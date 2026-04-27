<?php
/**
 * Database Initialization Script
 * Creates database, tables, and initial data for iconcern system
 * Run this script once to set up the database
 */

require_once __DIR__ . '/../config/database.php';

// Database credentials
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

echo "========================================\n";
echo "iconcern Database Initialization\n";
echo "========================================\n\n";

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server\n";
    
    // Create database
    echo "Creating database '$dbname'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created\n\n";
    
    // Use the database
    $pdo->exec("USE `$dbname`");
    
    // Read and execute schema file
    echo "Reading schema file...\n";
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Schema file not found: $schema_file");
    }
    
    $schema = file_get_contents($schema_file);
    
    // Remove CREATE DATABASE and USE statements (already done)
    $schema = preg_replace('/CREATE DATABASE.*?;/is', '', $schema);
    $schema = preg_replace('/USE.*?;/is', '', $schema);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    echo "Executing schema statements...\n";
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    echo "✓ Schema executed\n\n";
    
    // Verify admin account exists
    echo "Verifying admin account...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $admin_count = $stmt->fetch()['count'];
    
    if ($admin_count == 0) {
        // Create admin account (password: admin123)
        $admin_password = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $admin_password, 'System Administrator', 'admin@nwssu.edu.ph', 'admin']);
        echo "✓ Admin account created\n";
        echo "  Username: admin\n";
        echo "  Password: admin123\n";
    } else {
        echo "✓ Admin account already exists\n";
    }
    
    // Count records
    echo "\nDatabase Summary:\n";
    $tables = ['colleges', 'departments', 'users', 'concerns', 'classifications', 'routing', 'status_history', 'notifications', 'training_data'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "  $table: $count records\n";
        } catch (PDOException $e) {
            echo "  $table: Error\n";
        }
    }
    
    echo "\n========================================\n";
    echo "Database initialization completed!\n";
    echo "========================================\n";
    echo "\nNext steps:\n";
    echo "1. Train the SVM model: python ml/train_model.py\n";
    echo "2. Access the system at: http://localhost/iconcern/\n";
    echo "3. Login with admin account (username: admin, password: admin123)\n";
    
} catch (PDOException $e) {
    echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

