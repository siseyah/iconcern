<?php
/**
 * Setup Verification Script
 * Run this file to check if your iconcern installation is configured correctly
 * Access: http://localhost/iconcern/setup_check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iconcern - Setup Verification</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .check { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        h1 { color: #2563eb; }
        h2 { color: #1e293b; margin-top: 30px; }
    </style>
</head>
<body>
    <h1>🔍 iconcern Setup Verification</h1>
    <p>This script checks if your installation is configured correctly.</p>

    <?php
    $allGood = true;

    // Check PHP Version
    echo "<h2>PHP Configuration</h2>";
    $phpVersion = phpversion();
    if (version_compare($phpVersion, '7.4.0', '>=')) {
        echo "<div class='check success'>✓ PHP Version: $phpVersion (OK)</div>";
    } else {
        echo "<div class='check error'>✗ PHP Version: $phpVersion (Requires 7.4+)</div>";
        $allGood = false;
    }

    // Check PDO Extension
    if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
        echo "<div class='check success'>✓ PDO MySQL Extension: Installed</div>";
    } else {
        echo "<div class='check error'>✗ PDO MySQL Extension: Not installed</div>";
        $allGood = false;
    }

    // Check Database Connection
    echo "<h2>Database Connection</h2>";
    try {
        require_once 'config/database.php';
        $db = getDB();
        echo "<div class='check success'>✓ Database Connection: Successful</div>";
        
        // Check if tables exist
        $tables = ['users', 'colleges', 'departments', 'concerns', 'classifications', 'routing', 'status_history', 'notifications', 'training_data'];
        $missingTables = [];
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                $missingTables[] = $table;
            }
        }
        
        if (empty($missingTables)) {
            echo "<div class='check success'>✓ Database Tables: All tables exist</div>";
        } else {
            echo "<div class='check error'>✗ Missing Tables: " . implode(', ', $missingTables) . "</div>";
            echo "<div class='check warning'>⚠️ Please import database/schema.sql</div>";
            $allGood = false;
        }
    } catch (Exception $e) {
        echo "<div class='check error'>✗ Database Connection: Failed - " . $e->getMessage() . "</div>";
        $allGood = false;
    }

    // Check Directories
    echo "<h2>Directory Permissions</h2>";
    $dirs = [
        'uploads' => 'File uploads directory',
        'ml' => 'Machine learning scripts directory'
    ];
    
    foreach ($dirs as $dir => $desc) {
        if (is_dir($dir)) {
            if (is_writable($dir)) {
                echo "<div class='check success'>✓ $desc ($dir): Writable</div>";
            } else {
                echo "<div class='check warning'>⚠️ $desc ($dir): Exists but not writable</div>";
            }
        } else {
            echo "<div class='check error'>✗ $desc ($dir): Directory not found</div>";
            $allGood = false;
        }
    }

    // Check Python
    echo "<h2>Python & Machine Learning</h2>";
    $pythonCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'python' : 'python3';
    $pythonVersion = @shell_exec("$pythonCmd --version 2>&1");
    
    if ($pythonVersion) {
        echo "<div class='check success'>✓ Python: " . trim($pythonVersion) . "</div>";
        
        // Check scikit-learn
        $sklearnCheck = @shell_exec("$pythonCmd -c \"import sklearn; print(sklearn.__version__)\" 2>&1");
        if ($sklearnCheck) {
            echo "<div class='check success'>✓ scikit-learn: Installed (v" . trim($sklearnCheck) . ")</div>";
        } else {
            echo "<div class='check warning'>⚠️ scikit-learn: Not installed (Run: pip install scikit-learn)</div>";
        }
    } else {
        echo "<div class='check error'>✗ Python: Not found in PATH</div>";
        echo "<div class='check warning'>⚠️ Classification will use fallback keyword-based method</div>";
    }

    // Check ML Files
    if (file_exists('ml/classify.py')) {
        echo "<div class='check success'>✓ Classification Script: Found</div>";
    } else {
        echo "<div class='check error'>✗ Classification Script: Missing</div>";
        $allGood = false;
    }

    if (file_exists('ml/train_model.py')) {
        echo "<div class='check success'>✓ Training Script: Found</div>";
    } else {
        echo "<div class='check warning'>⚠️ Training Script: Missing</div>";
    }

    // Check Model Files
    if (file_exists('ml/svm_model.pkl')) {
        echo "<div class='check success'>✓ SVM Model: Found (will use trained model)</div>";
    } else {
        echo "<div class='check warning'>⚠️ SVM Model: Not found (will use keyword-based fallback)</div>";
        echo "<div class='check warning'>⚠️ Run: python ml/train_model.py to train the model</div>";
    }

    // Check Configuration Files
    echo "<h2>Configuration Files</h2>";
    $configFiles = [
        'config/config.php' => 'Main configuration',
        'config/database.php' => 'Database configuration',
        'includes/auth.php' => 'Authentication handler',
        'includes/concern.php' => 'Concern handler'
    ];
    
    foreach ($configFiles as $file => $desc) {
        if (file_exists($file)) {
            echo "<div class='check success'>✓ $desc: Found</div>";
        } else {
            echo "<div class='check error'>✗ $desc: Missing ($file)</div>";
            $allGood = false;
        }
    }

    // Final Summary
    echo "<h2>Summary</h2>";
    if ($allGood) {
        echo "<div class='check success' style='font-size: 1.2em; font-weight: bold;'>";
        echo "🎉 All checks passed! Your installation looks good.";
        echo "</div>";
        echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    } else {
        echo "<div class='check error' style='font-size: 1.2em; font-weight: bold;'>";
        echo "⚠️ Some issues found. Please fix the errors above before using the system.";
        echo "</div>";
    }
    ?>

    <hr style="margin: 30px 0;">
    <p><small>iconcern Setup Verification | Version 1.0.0</small></p>
</body>
</html>

