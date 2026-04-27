<?php
require_once '../config/config.php';
requireRole(['admin', 'staff']);

require_once '../includes/auth.php';
require_once '../includes/concern.php';
require_once '../includes/college.php';

$auth = new Auth();
$concern = new Concern();
$db = getDB();
$user = $auth->getCurrentUser();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'department_id' => null,
    'full_name' => 'Admin',
], $user);

$requested_department_id = isset($_GET['department_id']) && $_GET['department_id'] !== ''
    ? intval($_GET['department_id'])
    : intval($user['department_id'] ?? 0);

// Staff users are restricted to their assigned department only.
if (getUserRole() !== 'admin' && (!empty($user['department_id']) && $requested_department_id !== intval($user['department_id']))) {
    die('Access denied.');
}

if (empty($requested_department_id)) {
    die('Department not selected.');
}

$stmt = $db->prepare("SELECT department_name FROM departments WHERE department_id = ?");
$stmt->execute([$requested_department_id]);
$department_name = $stmt->fetch()['department_name'] ?? ('Department #' . $requested_department_id);

$filters = ['department_id' => $requested_department_id];

$all_concerns = $concern->getConcerns($filters);

// Ensure one row per concern (defensive against duplicate join rows).
$unique_concerns = [];
foreach ($all_concerns as $row) {
    $cid = (int)($row['concern_id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }
    if (!isset($unique_concerns[$cid])) {
        $unique_concerns[$cid] = $row;
    }
}
$all_concerns = array_values($unique_concerns);
usort($all_concerns, function ($a, $b) {
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});

// Build stats from already filtered concerns.
$stats = [
    'total' => count($all_concerns),
    'by_status' => [],
    'today' => 0,
    'this_week' => 0,
];
$nowTs = time();
$weekAgoTs = strtotime('-7 days', $nowTs);
foreach ($all_concerns as $c) {
    $status = (string)($c['status'] ?? 'Pending');
    $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;

    $createdTs = isset($c['created_at']) ? strtotime((string)$c['created_at']) : false;
    if ($createdTs !== false) {
        if (date('Y-m-d', $createdTs) === date('Y-m-d', $nowTs)) {
            $stats['today']++;
        }
        if ($createdTs >= $weekAgoTs) {
            $stats['this_week']++;
        }
    }
}

$recent_concerns = array_slice($all_concerns, 0, 10);

// Get top categories for this office/department
$category_sql = "
    SELECT cl.predicted_category, COUNT(*) as count
    FROM concerns co
    INNER JOIN (
        SELECT r1.concern_id, r1.department_id
        FROM routing r1
        INNER JOIN (
            SELECT concern_id, MAX(routed_at) AS max_routed_at
            FROM routing
            GROUP BY concern_id
        ) r2 ON r1.concern_id = r2.concern_id AND r1.routed_at = r2.max_routed_at
    ) r ON co.concern_id = r.concern_id
    INNER JOIN classifications cl ON co.concern_id = cl.concern_id
    WHERE cl.predicted_category IS NOT NULL AND r.department_id = " . intval($requested_department_id) . "
    GROUP BY cl.predicted_category
    ORDER BY count DESC
";
$top_categories = [];
$stmt = $db->query($category_sql);
$top_categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .office-dashboard-wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }
        .office-header {
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .stat-box h3 {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .stat-box .number {
            font-size: 2.25rem;
            font-weight: 900;
            color: var(--primary-color);
        }
        .card-list {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .office-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 1200px) {
            .office-grid {
                grid-template-columns: 1fr;
            }
        }

        #office-report-actions, .main-header, .main-footer { }
        @media print {
            #office-report-actions, .main-header, .main-footer { display: none !important; }
            body { background: #fff !important; }
            #officeStatusChart,
            #officeCategoryChart { background: #fff !important; }
        }

        #officeStatusChart,
        #officeCategoryChart {
            width: 100% !important;
            background: #fff;
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
            <div class="container office-dashboard-wrapper" id="report-content">
        <div class="office-header">
            <h1 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($department_name); ?> - Office Dashboard</h1>
            <p style="color: var(--text-secondary); font-size: 1.05rem;">
                Showing concerns routed to this office.
            </p>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <h3>Total Concerns</h3>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-box">
                <h3>Pending</h3>
                <div class="number"><?php echo $stats['by_status']['Pending'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>In Progress</h3>
                <div class="number"><?php echo $stats['by_status']['In Progress'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Resolved</h3>
                <div class="number"><?php echo $stats['by_status']['Resolved'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Today</h3>
                <div class="number"><?php echo $stats['today']; ?></div>
            </div>
            <div class="stat-box">
                <h3>This Week</h3>
                <div class="number"><?php echo $stats['this_week']; ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="table-container" style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Status Breakdown</h3>
                <canvas id="officeStatusChart" aria-label="Office status breakdown chart" role="img"></canvas>
            </div>
            <div class="table-container" style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Top Categories</h3>
                <canvas id="officeCategoryChart" aria-label="Top categories chart" role="img"></canvas>
            </div>
        </div>

        <div class="office-grid">
            <div class="table-container" style="padding: 1.5rem;">
                <div class="page-header" style="margin-bottom: 1rem;">
                    <h1 style="font-size: 1.35rem;">Recent Concerns</h1>
                    <p style="margin: 0;">Last 10 routed concerns for this office.</p>
                </div>

                <?php if (empty($recent_concerns)): ?>
                    <div class="empty-state">
                        <p>No concerns found for this office.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <?php foreach ($recent_concerns as $c): ?>
                            <div class="card-list-item">
                                <div style="display:flex; justify-content:space-between; gap:1rem; align-items:center;">
                                    <div style="flex:1;">
                                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                            <strong style="color: var(--primary-color);">#<?php echo $c['concern_id']; ?></strong>
                                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $c['status'])); ?>">
                                                <?php echo htmlspecialchars($c['status']); ?>
                                            </span>
                                        </div>
                                        <div style="color: var(--text-primary); font-size:0.9rem;">
                                            <?php echo htmlspecialchars(substr($c['concern_text'], 0, 120)); ?>...
                                        </div>
                                        <div style="color: var(--text-secondary); font-size:0.8rem; margin-top:0.25rem;">
                                            <?php echo htmlspecialchars($c['full_name']); ?> • <?php echo formatDate($c['created_at']); ?>
                                        </div>
                                    </div>
                                    <a href="../concern.php?id=<?php echo $c['concern_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-container" style="padding: 1.5rem;">
                <div class="page-header" style="margin-bottom: 1rem;">
                    <h1 style="font-size: 1.35rem;">Top Categories</h1>
                    <p style="margin: 0;">AI classified concern categories for this office.</p>
                </div>

                <?php if (empty($top_categories)): ?>
                    <div class="empty-state">
                        <p>No category data yet.</p>
                    </div>
                <?php else: ?>
                    <div class="card-list">
                        <?php foreach ($top_categories as $cat): ?>
                            <div class="card-list-item">
                                <span class="badge badge-info"><?php echo htmlspecialchars($cat['predicted_category']); ?></span>
                                <strong style="display:block; margin-top:0.5rem;"><?php echo $cat['count']; ?> concerns</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
        (function () {
            const statusLabels = <?php echo json_encode(array_keys($stats['by_status'] ?? [])); ?>;
            const statusCounts = <?php echo json_encode(array_values($stats['by_status'] ?? [])); ?>;

            const categoryLabels = <?php echo json_encode(array_map(function($c){ return $c['predicted_category']; }, $top_categories ?? [])); ?>;
            const categoryCounts = <?php echo json_encode(array_map(function($c){ return $c['count']; }, $top_categories ?? [])); ?>;

            const common = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } }
            };

            const statusCanvas = document.getElementById('officeStatusChart');
            if (statusCanvas) {
                statusCanvas.style.height = '260px';
                new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusCounts,
                            backgroundColor: ['#16a34a', '#22c55e', '#15803d', '#34d399', '#10b981']
                        }]
                    },
                    options: common
                });
            }

            const categoryCanvas = document.getElementById('officeCategoryChart');
            if (categoryCanvas) {
                categoryCanvas.style.height = '260px';
                new Chart(categoryCanvas, {
                    type: 'bar',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            label: 'Concerns',
                            data: categoryCounts,
                            backgroundColor: '#16a34a'
                        }]
                    },
                    options: Object.assign({}, common, {
                        indexAxis: 'y',
                        scales: { x: { beginAtZero: true } }
                    })
                });
            }
        })();
    </script>
</body>
</html>

