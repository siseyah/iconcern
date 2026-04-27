<?php
require_once '../config/config.php';
requireRole(['admin', 'staff']);
require_once '../includes/concern.php';
require_once '../includes/auth.php';

$db = getDB();
$concern = new Concern();
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'department_id' => null,
    'college_id' => null,
    'full_name' => 'Admin',
], $user);

// Per-admin scope: department first, otherwise college.
$filters = [];
if (!empty($user['department_id'])) {
    $filters['department_id'] = (int)$user['department_id'];
} elseif (!empty($user['college_id'])) {
    $filters['college_id'] = (int)$user['college_id'];
}

// Sidebar unread notifications.
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = (int)($stmt->fetch()['count'] ?? 0);

// Get statistics
$stats = [];
$model_metrics = null;

// Use existing scoped statistics helper.
$scoped_stats = $concern->getStatistics($filters);
$stats['total_concerns'] = (int)($scoped_stats['total'] ?? 0);
$stats['by_status'] = $scoped_stats['by_status'] ?? [];
$stats['recent_concerns'] = (int)($scoped_stats['this_week'] ?? 0);

// By college within admin scope.
if (!empty($filters['department_id'])) {
    $stmt = $db->prepare("SELECT c.college_name, COUNT(DISTINCT co.concern_id) as count
                          FROM concerns co
                          INNER JOIN colleges c ON co.college_id = c.college_id
                          INNER JOIN routing r ON co.concern_id = r.concern_id
                              AND r.routed_at = (SELECT MAX(r2.routed_at) FROM routing r2 WHERE r2.concern_id = co.concern_id)
                          WHERE r.department_id = ?
                          GROUP BY c.college_id, c.college_name
                          ORDER BY c.college_name ASC");
    $stmt->execute([$filters['department_id']]);
} elseif (!empty($filters['college_id'])) {
    $stmt = $db->prepare("SELECT c.college_name, COUNT(DISTINCT co.concern_id) as count
                          FROM colleges c
                          LEFT JOIN concerns co ON co.college_id = c.college_id
                          WHERE c.college_id = ?
                          GROUP BY c.college_id, c.college_name
                          ORDER BY c.college_name ASC");
    $stmt->execute([$filters['college_id']]);
} else {
    $stmt = $db->query("SELECT c.college_name, COALESCE(COUNT(co.concern_id), 0) as count
                        FROM colleges c
                        LEFT JOIN concerns co ON co.college_id = c.college_id
                        GROUP BY c.college_id, c.college_name
                        ORDER BY c.college_name ASC");
}
$stats['by_college'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load model performance metrics if available.
$metrics_file = __DIR__ . '/../ml/model_metrics.json';
if (is_readable($metrics_file)) {
    $metrics_json = @file_get_contents($metrics_file);
    if ($metrics_json !== false) {
        $decoded = json_decode($metrics_json, true);
        if (is_array($decoded)) {
            $model_metrics = $decoded;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        #report-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            justify-content: flex-end;
            margin: 0.5rem 0 1.25rem;
        }
        #report-content canvas {
            max-height: 280px;
            width: 100% !important;
            background: #fff;
        }
        .chart-card {
            padding: 1.5rem;
        }
        @media print {
            #report-actions, .main-header, .main-footer { display: none !important; }
            body { background: #fff !important; }
            #report-content { color: #000; }
            #report-content canvas { background: #fff; }
            .layout-admin { display: block !important; }
            .admin-sidebar { display: none !important; }
            .admin-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            #report-content {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0.2in 0.25in !important;
                font-family: "Times New Roman", Times, serif !important;
            }
            #report-content .page-header h1 {
                font-size: 22pt !important;
                margin-bottom: 6pt !important;
                letter-spacing: 0.2px;
            }
            #report-content .page-header p {
                font-size: 11pt !important;
                color: #333 !important;
                margin-bottom: 12pt !important;
            }
            .form-card,
            .chart-card,
            .stat-card {
                border: 1px solid #d9d9d9 !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .dashboard-stats {
                margin-bottom: 12pt !important;
            }
            h3, h4 {
                color: #111 !important;
                font-weight: 700 !important;
            }
            .report-print-meta {
                display: block !important;
                margin-bottom: 12pt;
                font-size: 10.5pt;
                color: #333;
            }
            .report-print-meta .line {
                margin-bottom: 2pt;
            }
        }
        .report-print-meta { display: none; }
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
            <div class="container" id="report-content">
        <div class="page-header">
            <div id="report-actions">
                <button type="button" class="btn btn-primary" onclick="window.print()">Save as PDF</button>
            </div>
            <h1>Reports & Analytics</h1>
            <p>Your scoped analytics and insights</p>
            <div class="report-print-meta">
                <div class="line"><strong>Document:</strong> iConcern Formal Analytics Report</div>
                <div class="line"><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
            </div>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Total Concerns</h3>
                <p class="stat-number"><?php echo $stats['total_concerns']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Recent (7 days)</h3>
                <p class="stat-number"><?php echo $stats['recent_concerns']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <p class="stat-number"><?php echo $stats['by_status']['Pending'] ?? 0; ?></p>
            </div>
            <div class="stat-card">
                <h3>Resolved</h3>
                <p class="stat-number"><?php echo $stats['by_status']['Resolved'] ?? 0; ?></p>
            </div>
        </div>

        <?php if ($model_metrics): ?>
            <div class="form-card chart-card" style="margin-top: 2rem;">
                <h3>Model Performance (SVM)</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="padding: 0.9rem; background: var(--bg-color); border-radius: 0.5rem;">
                        <strong>Accuracy</strong><br>
                        <?php echo number_format(((float)($model_metrics['accuracy'] ?? 0)) * 100, 2); ?>%
                    </div>
                    <div style="padding: 0.9rem; background: var(--bg-color); border-radius: 0.5rem;">
                        <strong>F1 Score (Macro)</strong><br>
                        <?php echo number_format((float)($model_metrics['f1_macro'] ?? 0), 4, '.', ''); ?>
                    </div>
                    <div style="padding: 0.9rem; background: var(--bg-color); border-radius: 0.5rem;">
                        <strong>F1 Score (Weighted)</strong><br>
                        <?php echo number_format((float)($model_metrics['f1_weighted'] ?? 0), 4, '.', ''); ?>
                    </div>
                    <div style="padding: 0.9rem; background: var(--bg-color); border-radius: 0.5rem;">
                        <strong>Overall Dataset</strong><br>
                        <?php echo (int)($model_metrics['dataset_samples'] ?? ($model_metrics['deduplicated_samples'] ?? $model_metrics['test_samples'] ?? 0)); ?>
                    </div>
                </div>
                <div style="margin-top: 1.25rem;">
                    <h4 style="margin-bottom: 0.75rem;">F1 Score Graph</h4>
                    <canvas id="f1ScoreChart" aria-label="F1 score metrics chart" role="img"></canvas>
                </div>
                <?php if (!empty($model_metrics['generated_at'])): ?>
                    <p style="margin-top: 0.75rem; color: var(--text-secondary); font-size: 0.9rem;">
                        Last evaluated: <?php echo htmlspecialchars((string)$model_metrics['generated_at']); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 2rem; margin-top: 2rem;">
            <div class="form-card chart-card">
                <h3>Concerns by Status</h3>
                <div style="margin-top: 1rem;">
                    <canvas id="statusChart" aria-label="Concerns by status chart" role="img"></canvas>
                </div>
            </div>

            <div class="form-card chart-card">
                <h3>Concerns by College</h3>
                <div style="margin-top: 1rem;">
                    <canvas id="collegeChart" aria-label="Concerns by college chart" role="img"></canvas>
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <div class="form-card chart-card">
                <h3>Table Summary (for easy reading)</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.25rem; margin-top: 1rem;">
                    <div>
                        <h4 style="margin-bottom: 0.75rem;">By Status</h4>
                        <?php foreach (($stats['by_status'] ?? []) as $status => $count): ?>
                            <div style="display:flex; justify-content:space-between; padding: 0.6rem 0.75rem; background: var(--bg-color); border-radius: 0.5rem; margin-bottom: 0.5rem;">
                                <span><?php echo htmlspecialchars($status); ?></span>
                                <strong><?php echo htmlspecialchars((string)$count); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 0.75rem;">By College</h4>
                        <?php foreach (($stats['by_college'] ?? []) as $college): ?>
                            <div style="display:flex; justify-content:space-between; padding: 0.6rem 0.75rem; background: var(--bg-color); border-radius: 0.5rem; margin-bottom: 0.5rem;">
                                <span><?php echo htmlspecialchars($college['college_name'] ?: 'Not specified'); ?></span>
                                <strong><?php echo htmlspecialchars((string)$college['count']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        (function () {
            const statusLabels = <?php echo json_encode(array_keys($stats['by_status'] ?? [])); ?>;
            const statusCounts = <?php echo json_encode(array_values($stats['by_status'] ?? [])); ?>;

            const collegeLabels = <?php echo json_encode(array_map(function($c){ return $c['college_name'] ?? 'Not specified'; }, $stats['by_college'] ?? [])); ?>;
            const collegeCounts = <?php echo json_encode(array_map(function($c){ return $c['count'] ?? 0; }, $stats['by_college'] ?? [])); ?>;
            const f1Labels = <?php echo json_encode([
                'F1 Macro',
                'F1 Weighted',
                'Holdout F1 Macro',
                'Holdout F1 Weighted'
            ]); ?>;
            const f1Values = <?php echo json_encode([
                (float)($model_metrics['f1_macro'] ?? 0),
                (float)($model_metrics['f1_weighted'] ?? 0),
                (float)($model_metrics['holdout_f1_macro'] ?? ($model_metrics['f1_macro'] ?? 0)),
                (float)($model_metrics['holdout_f1_weighted'] ?? ($model_metrics['f1_weighted'] ?? 0)),
            ]); ?>;

            const common = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true }
                }
            };

            const statusCanvas = document.getElementById('statusChart');
            if (statusCanvas) {
                statusCanvas.style.height = '280px';
                new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusCounts,
                            backgroundColor: [
                                '#16a34a', '#22c55e', '#15803d', '#34d399', '#10b981',
                                '#047857', '#059669', '#14532d', '#0f766e'
                            ].slice(0, Math.max(statusLabels.length, 1))
                        }]
                    },
                    options: common
                });
            }

            const collegeCanvas = document.getElementById('collegeChart');
            if (collegeCanvas) {
                collegeCanvas.style.height = '280px';
                new Chart(collegeCanvas, {
                    type: 'bar',
                    data: {
                        labels: collegeLabels,
                        datasets: [{
                            label: 'Concerns',
                            data: collegeCounts,
                            backgroundColor: '#22c55e'
                        }]
                    },
                    options: Object.assign({}, common, {
                        scales: {
                            y: { beginAtZero: true }
                        }
                    })
                });
            }

            const f1Canvas = document.getElementById('f1ScoreChart');
            if (f1Canvas) {
                f1Canvas.style.height = '260px';
                new Chart(f1Canvas, {
                    type: 'bar',
                    data: {
                        labels: f1Labels,
                        datasets: [{
                            label: 'Score',
                            data: f1Values,
                            backgroundColor: ['#16a34a', '#22c55e', '#059669', '#10b981'],
                            borderColor: '#047857',
                            borderWidth: 1
                        }]
                    },
                    options: Object.assign({}, common, {
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 1.0
                            }
                        }
                    })
                });
            }
        })();
    </script>
</body>
</html>

