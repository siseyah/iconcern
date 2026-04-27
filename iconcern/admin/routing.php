<?php
require_once '../config/config.php';
requireRole(['admin', 'staff']);

$db = getDB();

// Get all routing records
$stmt = $db->query("SELECT r.*, c.concern_text, u.full_name as student_name, col.college_name, d.department_name, u2.full_name as routed_by_name
                    FROM routing r
                    LEFT JOIN concerns c ON r.concern_id = c.concern_id
                    LEFT JOIN users u ON c.user_id = u.user_id
                    LEFT JOIN colleges col ON r.college_id = col.college_id
                    LEFT JOIN departments d ON r.department_id = d.department_id
                    LEFT JOIN users u2 ON r.routed_by = u2.user_id
                    ORDER BY r.routed_at DESC");
$routings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routing Information - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page">
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Routing Information</h1>
            <p>View concern routing and forwarding history</p>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Concern ID</th>
                        <th>Student</th>
                        <th>Concern Preview</th>
                        <th>Routed To</th>
                        <th>Routed By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($routings)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                No routing records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($routings as $r): ?>
                            <tr>
                                <td>#<?php echo $r['concern_id']; ?></td>
                                <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars(substr($r['concern_text'], 0, 100)); ?>...
                                </td>
                                <td>
                                    <?php if ($r['college_name']): ?>
                                        <strong>College:</strong> <?php echo htmlspecialchars($r['college_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($r['department_name']): ?>
                                        <strong>Department:</strong> <?php echo htmlspecialchars($r['department_name']); ?>
                                    <?php endif; ?>
                                    <?php if (!$r['college_name'] && !$r['department_name']): ?>
                                        <span style="color: var(--text-secondary);">Not routed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($r['routed_by_name']); ?></td>
                                <td><?php echo formatDate($r['routed_at']); ?></td>
                                <td>
                                    <a href="../concern.php?id=<?php echo $r['concern_id']; ?>" class="btn btn-sm btn-primary">View Concern</a>
                                </td>
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

