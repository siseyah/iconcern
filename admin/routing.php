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

// Aggregations for charts
$deptCounts = $db->query("
    SELECT COALESCE(d.department_name, 'Not routed') as name, COUNT(*) as count
    FROM routing r
    LEFT JOIN departments d ON r.department_id = d.department_id
    GROUP BY r.department_id
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

$collegeCounts = $db->query("
    SELECT COALESCE(col.college_name, 'Not routed') as name, COUNT(*) as count
    FROM routing r
    LEFT JOIN colleges col ON r.college_id = col.college_id
    GROUP BY r.college_id
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routing Information - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        #routing-report-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin: 0.5rem 0 1.25rem;
        }
        
        #routingDeptChart,
        #routingCollegeChart {
            width: 100% !important;
            background: #fff;
        }

        @media print {
            #routing-report-actions, .main-header, .main-footer { display: none !important; }
            body { background: #fff !important; }
            #routingDeptChart,
            #routingCollegeChart { background: #fff !important; }
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/header.php'; ?>
    
    <div class="layout-admin">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <div class="logo"></div>
                <div class="text">Admin Panel</div>
            </div>
            <nav class="admin-nav">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="concerns.php">📋 Manage Concerns</a>
                <a href="routing.php">📤 Routing</a>
                <a href="reports.php">📊 Reports & Analytics</a>
                <?php if (getUserRole() === 'admin'): ?>
                    <a href="users.php">👥 Users</a>
                    <a href="register_user.php">➕ Register User</a>
                <?php endif; ?>
                <a href="../notifications.php">🔔 Notifications (<?php echo $unread_count; ?>)</a>
            </nav>
            <div style="margin-top: 2rem;">
                <a href="../logout.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Logout</a>
                <div style="margin-top: 0.75rem; color: rgba(226,232,240,0.85); font-size: 0.92rem; word-break: break-word;">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </div>
            </div>
        </aside>
        <main class="admin-content">
            <div class="container">
        <div class="page-header">
            <h1>Routing Information</h1>
            <p>View concern routing and forwarding history</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
            <div class="form-card">
                <h3>Routed Count by Department</h3>
                <div style="margin-top: 1rem;">
                    <canvas id="routingDeptChart" style="height: 280px;"></canvas>
                </div>
            </div>
            <div class="form-card">
                <h3>Routed Count by College</h3>
                <div style="margin-top: 1rem;">
                    <canvas id="routingCollegeChart" style="height: 280px;"></canvas>
                </div>
            </div>
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
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        (function () {
            const deptLabels = <?php echo json_encode(array_map(function($d){ return $d['name']; }, $deptCounts)); ?>;
            const deptCountsArr = <?php echo json_encode(array_map(function($d){ return $d['count']; }, $deptCounts)); ?>;

            const collegeLabels = <?php echo json_encode(array_map(function($c){ return $c['name']; }, $collegeCounts)); ?>;
            const collegeCountsArr = <?php echo json_encode(array_map(function($c){ return $c['count']; }, $collegeCounts)); ?>;

            const common = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } }
            };

            const deptCanvas = document.getElementById('routingDeptChart');
            if (deptCanvas) {
                new Chart(deptCanvas, {
                    type: 'bar',
                    data: {
                        labels: deptLabels,
                        datasets: [{
                            label: 'Routed',
                            data: deptCountsArr,
                            backgroundColor: '#16a34a'
                        }]
                    },
                    options: Object.assign({}, common, {
                        indexAxis: 'y',
                        scales: { x: { beginAtZero: true } }
                    })
                });
            }

            const collegeCanvas = document.getElementById('routingCollegeChart');
            if (collegeCanvas) {
                new Chart(collegeCanvas, {
                    type: 'bar',
                    data: {
                        labels: collegeLabels,
                        datasets: [{
                            label: 'Routed',
                            data: collegeCountsArr,
                            backgroundColor: '#22c55e'
                        }]
                    },
                    options: Object.assign({}, common, {
                        scales: { y: { beginAtZero: true } }
                    })
                });
            }
        })();
    </script>
</body>
</html>

