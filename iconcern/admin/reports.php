<?php
require_once '../config/config.php';
requireRole(['admin', 'staff']);
require_once '../includes/auth.php';
require_once '../includes/concern.php';

$db = getDB();
$auth = new Auth();
$concern = new Concern();
$user = $auth->getCurrentUser();

// Per-admin scope: department first, otherwise college.
$filters = [];
if (!empty($user['department_id'])) {
    $filters['department_id'] = (int)$user['department_id'];
} elseif (!empty($user['college_id'])) {
    $filters['college_id'] = (int)$user['college_id'];
}

// Get statistics
$stats = [];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page">
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Reports & Analytics</h1>
            <p>Your scoped analytics and insights</p>
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

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 2rem;">
            <div class="form-card">
                <h3>Concerns by Status</h3>
                <div style="margin-top: 1rem;">
                    <?php foreach ($stats['by_status'] as $status => $count): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--bg-color); border-radius: 0.5rem; margin-bottom: 0.5rem;">
                            <span><?php echo $status; ?></span>
                            <strong><?php echo $count; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-card">
                <h3>Concerns by College</h3>
                <div style="margin-top: 1rem;">
                    <?php foreach ($stats['by_college'] as $college): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: var(--bg-color); border-radius: 0.5rem; margin-bottom: 0.5rem;">
                            <span><?php echo htmlspecialchars($college['college_name'] ?: 'Not specified'); ?></span>
                            <strong><?php echo $college['count']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>

