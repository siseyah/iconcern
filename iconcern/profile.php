<?php
require_once 'config/config.php';
requireLogin();

require_once 'includes/auth.php';
$auth = new Auth();
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Profile</h1>
        </div>

        <div class="form-card">
            <div class="form-group">
                <label>Full Name</label>
                <p><?php echo htmlspecialchars($user['full_name']); ?></p>
            </div>

            <div class="form-group">
                <label>Username</label>
                <p><?php echo htmlspecialchars($user['username']); ?></p>
            </div>

            <div class="form-group">
                <label>Email</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <div class="form-group">
                <label>Role</label>
                <p><span class="badge badge-info"><?php echo ucfirst($user['role']); ?></span></p>
            </div>

            <?php if ($user['college_name']): ?>
                <div class="form-group">
                    <label>College</label>
                    <p><?php echo htmlspecialchars($user['college_name']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($user['department_name']): ?>
                <div class="form-group">
                    <label>Department</label>
                    <p><?php echo htmlspecialchars($user['department_name']); ?></p>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Member Since</label>
                <p><?php echo formatDate($user['created_at']); ?></p>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

