<?php
require_once '../config/config.php';
requireRole('admin');

require_once '../includes/auth.php';
$auth = new Auth();
$user = $auth->getCurrentUser();

$db = getDB();

// Get users - filter by college if admin is college-specific
$sql = "SELECT u.*, c.college_name, d.department_name 
        FROM users u
        LEFT JOIN colleges c ON u.college_id = c.college_id
        LEFT JOIN departments d ON u.department_id = d.department_id";
        
if ($user['college_id']) {
    $sql .= " WHERE u.college_id = " . intval($user['college_id']);
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->query($sql);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page">
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>User Management<?php echo $user['college_name'] ? ' - ' . htmlspecialchars($user['college_name']) : ''; ?></h1>
            <p><?php echo $user['college_name'] ? 'Manage users for ' . htmlspecialchars($user['college_name']) : 'Manage system users'; ?></p>
        </div>

        <div class="action-buttons" style="margin-bottom: 1.5rem;">
            <a href="register_user.php" class="btn btn-primary">➕ Register New User</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>College</th>
                        <th>Department</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                No users found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo ucfirst($u['role']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($u['college_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($u['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatDate($u['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>

