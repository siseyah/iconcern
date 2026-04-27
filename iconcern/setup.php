<?php
/**
 * iconcern Setup Script
 * Complete system setup and installation
 */

// Check if running from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Web interface
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>iconcern Setup</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .setup-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 800px;
                width: 100%;
                padding: 40px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 2rem;
            }
            .subtitle {
                color: #666;
                margin-bottom: 30px;
            }
            .step {
                background: #f8f9fa;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .step h2 {
                color: #333;
                margin-bottom: 10px;
                font-size: 1.2rem;
            }
            .step p {
                color: #666;
                margin-bottom: 10px;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 500;
                transition: background 0.3s;
                border: none;
                cursor: pointer;
                font-size: 1rem;
            }
            .btn:hover {
                background: #5568d3;
            }
            .btn-secondary {
                background: #6c757d;
            }
            .btn-secondary:hover {
                background: #5a6268;
            }
            .status {
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .status.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .status.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .status.info {
                background: #d1ecf1;
                color: #0c5460;
                border: 1px solid #bee5eb;
            }
            code {
                background: #f4f4f4;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
            }
            .credentials {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            .credentials h3 {
                color: #856404;
                margin-bottom: 10px;
            }
            .credentials p {
                color: #856404;
                margin: 5px 0;
            }
        </style>
    </head>
    <body>
        <div class="setup-container">
            <h1>🎓 iconcern Setup</h1>
            <p class="subtitle">Intelligent College-Based Student Concern Classification and Routing System</p>
            
            <?php
            $step = $_GET['step'] ?? '1';
            
            if ($step === '1') {
                // Step 1: Database Setup
                ?>
                <div class="step">
                    <h2>Step 1: Database Setup</h2>
                    <p>This will create the database and all required tables.</p>
                    <form method="GET" action="">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn">Initialize Database</button>
                    </form>
                </div>
                <?php
            } elseif ($step === '2') {
                // Run database initialization
                ob_start();
                include __DIR__ . '/database/init_database.php';
                $output = ob_get_clean();
                
                ?>
                <div class="step">
                    <h2>Database Initialization</h2>
                    <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars($output); ?></pre>
                    
                    <?php if (strpos($output, 'completed') !== false): ?>
                        <div class="status success">
                            ✓ Database setup completed successfully!
                        </div>
                        
                        <div class="credentials">
                            <h3>🔑 Admin Credentials</h3>
                            <p><strong>Username:</strong> admin</p>
                            <p><strong>Password:</strong> admin123</p>
                            <p style="margin-top: 10px; font-size: 0.9rem;">⚠️ Please change the password after first login!</p>
                        </div>
                        
                        <div class="step">
                            <h2>Step 2: Train SVM Model</h2>
                            <p>Train the machine learning model with 1000 training entries.</p>
                            <p><strong>Note:</strong> You need to run this from command line:</p>
                            <code>python ml/train_model.py</code>
                            <p style="margin-top: 10px;">Or click the button below to attempt automatic training:</p>
                            <form method="GET" action="">
                                <input type="hidden" name="step" value="3">
                                <button type="submit" class="btn">Train Model</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="status error">
                            ✗ Database setup encountered errors. Please check the output above.
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            } elseif ($step === '3') {
                // Train model
                ?>
                <div class="step">
                    <h2>Training SVM Model</h2>
                    <p>Training the model with 1000 entries. This may take a few moments...</p>
                    <?php
                    $python_cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'python' : 'python3';
                    $script_path = __DIR__ . '/ml/train_model.py';
                    $command = "$python_cmd " . escapeshellarg($script_path) . " 2>&1";
                    $output = shell_exec($command);
                    ?>
                    <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px; overflow-y: auto;"><?php echo htmlspecialchars($output ?: 'No output. Please run manually: python ml/train_model.py'); ?></pre>
                    
                    <?php if (strpos($output ?: '', 'completed') !== false || strpos($output ?: '', 'saved') !== false): ?>
                        <div class="status success">
                            ✓ Model training completed!
                        </div>
                    <?php else: ?>
                        <div class="status info">
                            ⚠️ If training failed, please run manually from command line:<br>
                            <code>python ml/train_model.py</code>
                        </div>
                    <?php endif; ?>
                    
                    <div class="step" style="margin-top: 20px;">
                        <h2>Setup Complete!</h2>
                        <p>Your iconcern system is now ready to use.</p>
                        <div style="margin-top: 20px;">
                            <a href="index.php" class="btn">Go to Homepage</a>
                            <a href="login.php" class="btn btn-secondary">Go to Login</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </body>
    </html>
    <?php
} else {
    // CLI mode
    echo "iconcern Setup Script\n";
    echo "====================\n\n";
    
    echo "Step 1: Initializing database...\n";
    include __DIR__ . '/database/init_database.php';
    
    echo "\nStep 2: Training SVM model...\n";
    $python_cmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'python' : 'python3';
    $script_path = __DIR__ . '/ml/train_model.py';
    $command = "$python_cmd " . escapeshellarg($script_path) . " 2>&1";
    passthru($command);
    
    echo "\n\nSetup completed!\n";
    echo "Access the system at: http://localhost/iconcern/\n";
    echo "Admin login: username=admin, password=admin123\n";
}

