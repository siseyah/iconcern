<?php
require_once 'config/config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    require_once 'includes/auth.php';
    
    try {
        $auth = new Auth();
        $student_id = trim((string)($_POST['student_id'] ?? ''));
        $result = $auth->login($student_id, $_POST['password']);
        
        if ($result['success']) {
            // Login successful - redirect
            header('Location: index.php');
            exit();
        } else {
            $error = $result['message'];
            // Add more specific error message
            if (strpos($result['message'], 'Invalid') !== false) {
                $error = 'Invalid username or password. Please check your credentials.';
            }
        }
    } catch (Exception $e) {
        $error = 'Login error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.auth-page {
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .auth-container {
            width: 100%;
            max-width: 450px;
            margin: 2rem auto;
            padding: 1rem;
        }
        
        .auth-card {
            margin-bottom: 2rem;
        }
        
        @media (max-width: 640px) {
            body.auth-page {
                padding: 1rem 0.5rem;
            }
            
            .auth-container {
                padding: 0.5rem;
                margin: 1rem auto;
            }
            
            .auth-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Intelligent College-Based Student Concern Classification System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id" required autofocus placeholder="22-00000">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>

