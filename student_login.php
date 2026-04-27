<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_student'])) {
    require_once 'includes/auth.php';
    $auth = new Auth();
    $student_id = trim((string)($_POST['student_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $result = $auth->loginStudent($student_id, $password);
    if ($result['success']) {
        header('Location: index.php');
        exit();
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Student Login</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
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

                <button type="submit" name="login_student" class="btn btn-primary btn-block">Login</button>
            </form>

            <div class="auth-footer">
                <p>Admin/Staff? <a href="admin_login.php">Login here</a></p>
                <p>Student not activated? <a href="register.php">Activate account</a></p>
            </div>
        </div>
    </div>
</body>
</html>

