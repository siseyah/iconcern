<?php
require_once '../config/config.php';
requireRole(['admin', 'staff']);

require_once '../includes/auth.php';
require_once '../includes/concern.php';
require_once '../includes/college.php';

$auth = new Auth();
$concern = new Concern();
$college = new College();
$user = $auth->getCurrentUser();
$db = getDB();

// Filter by department if admin has department_id, otherwise by college
$filters = [];
if ($user['department_id']) {
    // Admin is assigned to a specific department - only show concerns routed to that department
    $filters['department_id'] = $user['department_id'];
} elseif ($user['college_id']) {
    // Admin is assigned to a college - show concerns from that college
    $filters['college_id'] = $user['college_id'];
}

// Note: For department-based admins, they will only see concerns routed to their department
// This ensures CCIS Admin only sees CCIS concerns, CCJS Admin only sees CCJS concerns, etc.

// Get statistics (filtered by college if applicable)
$stats = $concern->getStatistics($filters);

// Get recent concerns (filtered by college if applicable)
$all_concerns = $concern->getConcerns($filters);
$recent_concerns = array_slice($all_concerns, 0, 10);

// Get concerns by category (filtered by department or college if applicable)
$category_sql = "SELECT cl.predicted_category, COUNT(*) as count 
                 FROM classifications cl
                 INNER JOIN concerns co ON cl.concern_id = co.concern_id";
if ($user['department_id']) {
    $category_sql .= " INNER JOIN routing r ON co.concern_id = r.concern_id
                       WHERE cl.predicted_category IS NOT NULL AND r.department_id = " . intval($user['department_id']);
} elseif ($user['college_id']) {
    $category_sql .= " WHERE cl.predicted_category IS NOT NULL AND co.college_id = " . intval($user['college_id']);
} else {
    $category_sql .= " WHERE cl.predicted_category IS NOT NULL";
}
$category_sql .= " GROUP BY cl.predicted_category ORDER BY count DESC";
$stmt = $db->query($category_sql);
$all_top_categories = $stmt->fetchAll();
// Only show categories that the classifier can actually produce
$valid_categories = [
    'MIS Office',
    'IMCO Office',
    'Registrar Office',
    'Internet Laboratory',
    'Cashier Office',
    'Maintenance Office',
    'ISSC Office',
    'Faculty Office',
];
$top_categories = array_filter($all_top_categories, function($cat) use ($valid_categories) {
    return isset($cat['predicted_category']) && in_array($cat['predicted_category'], $valid_categories, true);
});
$top_categories = array_slice($top_categories, 0, 5); // Limit to top 5 after filtering

// Get concerns by college (only show if not college-specific admin)
// Show all 7 colleges with their concern counts
$top_colleges = [];
if (!$user['college_id']) {
    // Get all 7 colleges with their concern counts
    $stmt = $db->query("SELECT c.college_id, c.college_code, c.college_name, 
                               COALESCE(COUNT(co.concern_id), 0) as count 
                        FROM colleges c
                        LEFT JOIN concerns co ON c.college_id = co.college_id
                        WHERE c.college_code IN ('CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM')
                        GROUP BY c.college_id, c.college_code, c.college_name
                        ORDER BY 
                            CASE c.college_code
                                WHEN 'CCIS' THEN 1
                                WHEN 'CCJS' THEN 2
                                WHEN 'COED' THEN 3
                                WHEN 'CON' THEN 4
                                WHEN 'CAT' THEN 5
                                WHEN 'CEA' THEN 6
                                WHEN 'COM' THEN 7
                            END");
    $top_colleges = $stmt->fetchAll();
} else {
    // For college-specific admins, show their college info
    $stmt = $db->prepare("SELECT c.college_id, c.college_code, c.college_name, 
                                 COALESCE(COUNT(co.concern_id), 0) as count 
                          FROM colleges c
                          LEFT JOIN concerns co ON c.college_id = co.college_id
                          WHERE c.college_id = ?
                          GROUP BY c.college_id, c.college_code, c.college_name");
    $stmt->execute([$user['college_id']]);
    $college_data = $stmt->fetch();
    if ($college_data) {
        $top_colleges = [$college_data];
    }
}

// Get today's concerns (filtered by department or college if applicable)
if ($user['department_id']) {
    $today_sql = "SELECT COUNT(*) as count FROM concerns c
                   INNER JOIN routing r ON c.concern_id = r.concern_id
                   WHERE DATE(c.created_at) = CURDATE() AND r.department_id = " . intval($user['department_id']);
} elseif ($user['college_id']) {
    $today_sql = "SELECT COUNT(*) as count FROM concerns WHERE DATE(created_at) = CURDATE() AND college_id = " . intval($user['college_id']);
} else {
    $today_sql = "SELECT COUNT(*) as count FROM concerns WHERE DATE(created_at) = CURDATE()";
}
$stmt = $db->query($today_sql);
$today_count = $stmt->fetch()['count'];

// Get unread notifications
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-dashboard-wrapper {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 0;
            min-height: calc(100vh - 80px);
        }
        
        .admin-content-wrapper {
            background: var(--bg-color);
            padding: 2.5rem;
            position: relative;
            min-height: 100vh;
        }
        
        .admin-content-wrapper::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('../assets/images/nwssu-logo.png');
            background-size: 720px 720px;
            background-position: 50% 45%;
            background-repeat: no-repeat;
            opacity: 0.04;
            z-index: 0;
            pointer-events: none;
            filter: grayscale(40%) blur(22px);
        }
        
        .admin-content-wrapper::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 90% 50%, rgba(14, 165, 233, 0.10) 0%, transparent 55%);
            z-index: 0;
            pointer-events: none;
        }
        
        .admin-content-wrapper > * {
            position: relative;
            z-index: 1;
        }
        
        .admin-header-section {
            background: linear-gradient(135deg, var(--card-bg) 0%, #fafafa 100%);
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .admin-header-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bg-gradient-primary);
        }
        
        .admin-header-section h1 {
            font-size: 2.25rem;
            font-weight: 900;
            margin-bottom: 0.75rem;
            background: var(--bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }
        
        .admin-header-section p {
            color: var(--text-secondary);
            font-size: 1.05rem;
            font-weight: 400;
        }
        
        .admin-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .admin-stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .admin-stat-enhanced {
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .admin-stat-enhanced::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bg-gradient-primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .admin-stat-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-100);
        }
        
        .admin-stat-enhanced:hover::before {
            transform: scaleX(1);
        }
        
        .admin-stat-enhanced h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .admin-stat-enhanced .number {
            font-size: 2.5rem;
            font-weight: 900;
            background: var(--bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            letter-spacing: -0.03em;
        }
        
        .admin-dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1200px) {
            .admin-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .admin-card-enhanced {
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .admin-card-enhanced:hover {
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-100);
            transform: translateY(-2px);
        }
        
        .admin-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid var(--border-light);
        }
        
        .admin-card-header h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.01em;
        }
        
        .admin-quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .admin-quick-action {
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
            position: relative;
            overflow: hidden;
        }
        
        .admin-quick-action::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(124, 58, 237, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .admin-quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-100);
        }
        
        .admin-quick-action:hover::before {
            opacity: 1;
        }
        
        .admin-quick-action .icon,
        .admin-quick-action .title,
        .admin-quick-action .desc {
            position: relative;
            z-index: 1;
        }
        
        .admin-quick-action .icon {
            font-size: 1.75rem;
        }
        
        .admin-quick-action .title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .admin-quick-action .desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/header.php'; ?>

    <div class="admin-dashboard-wrapper">
        <!-- Sidebar -->
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
        </aside>

        <!-- Content -->
        <main class="admin-content-wrapper">
            <div class="admin-header-section">
                <h1><?php 
                    if ($user['department_name']) {
                        echo htmlspecialchars($user['department_name']) . ' Admin Dashboard';
                    } elseif ($user['college_name']) {
                        echo htmlspecialchars($user['college_name']) . ' Admin Dashboard';
                    } else {
                        echo 'Admin Dashboard';
                    }
                ?></h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! Here's what's happening today.</p>
            </div>

            <!-- Toolbar -->
            <div class="admin-toolbar">
                <div class="badge-pill">📅 <?php echo date('l, F d, Y'); ?></div>
                <div class="actions">
                    <a href="concerns.php" class="btn btn-outline">View All Concerns</a>
                    <a href="reports.php" class="btn btn-primary">Open Reports</a>
                </div>
            </div>

            <!-- Main Statistics -->
            <div class="admin-stats-container">
                <div class="admin-stat-enhanced">
                    <h3>Total Concerns</h3>
                    <div class="number"><?php echo $stats['total']; ?></div>
                </div>
                <div class="admin-stat-enhanced">
                    <h3>Pending</h3>
                    <div class="number"><?php echo $stats['by_status']['Pending'] ?? 0; ?></div>
                </div>
                <div class="admin-stat-enhanced">
                    <h3>In Progress</h3>
                    <div class="number"><?php echo $stats['by_status']['In Progress'] ?? 0; ?></div>
                </div>
                <div class="admin-stat-enhanced">
                    <h3>Resolved</h3>
                    <div class="number"><?php echo $stats['by_status']['Resolved'] ?? 0; ?></div>
                </div>
                <div class="admin-stat-enhanced">
                    <h3>Today</h3>
                    <div class="number"><?php echo $today_count; ?></div>
                </div>
                <div class="admin-stat-enhanced">
                    <h3>This Week</h3>
                    <div class="number"><?php echo $stats['this_week']; ?></div>
                </div>
            </div>

            <div class="admin-dashboard-grid">
                <!-- Recent Concerns -->
                <div class="admin-card-enhanced">
                    <div class="admin-card-header">
                        <h3>🕒 Recent Concerns</h3>
                        <a href="concerns.php" class="btn btn-outline btn-sm">View All</a>
                    </div>
                    <?php if (empty($recent_concerns)): ?>
                        <div class="empty-state">
                            <p>No concerns yet</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 420px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($recent_concerns as $c): ?>
                                <div class="card-list-item">
                                    <div style="flex:1;">
                                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                                            <strong style="color: var(--primary-color);">#<?php echo $c['concern_id']; ?></strong>
                                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $c['status'])); ?>"><?php echo $c['status']; ?></span>
                                        </div>
                                        <div style="color: var(--text-primary); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                            <?php echo htmlspecialchars(substr($c['concern_text'], 0, 100)); ?>...
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($c['full_name']); ?> • <?php echo formatDate($c['created_at']); ?>
                                        </div>
                                    </div>
                                    <a href="../concern.php?id=<?php echo $c['concern_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top Categories -->
                <div class="admin-card-enhanced">
                    <div class="admin-card-header">
                        <h3>🏷️ Top Categories</h3>
                    </div>
                    <?php if (empty($top_categories)): ?>
                        <div class="empty-state">
                            <p>No data available</p>
                        </div>
                    <?php else: ?>
                        <div class="card-list">
                            <?php foreach ($top_categories as $cat): ?>
                                <div class="card-list-item">
                                    <span><span class="badge badge-info"><?php echo htmlspecialchars($cat['predicted_category']); ?></span></span>
                                    <strong><?php echo $cat['count']; ?> concerns</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Colleges Overview -->
                <div class="admin-card-enhanced">
                    <div class="admin-card-header">
                        <h3>🏫 <?php echo !$user['college_id'] ? 'All Colleges' : 'College Overview'; ?></h3>
                    </div>
                    <?php if (empty($top_colleges)): ?>
                        <div class="empty-state">
                            <p>No data available</p>
                        </div>
                    <?php else: ?>
                        <div class="card-list">
                            <?php foreach ($top_colleges as $col): ?>
                                <div class="card-list-item" style="display: flex; justify-content: space-between; align-items: center; padding: 0.875rem 0;">
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <span style="font-weight: 600; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($col['college_name']); ?>
                                        </span>
                                        <span style="font-size: 0.75rem; color: var(--text-secondary);">
                                            Code: <?php echo htmlspecialchars($col['college_code']); ?>
                                        </span>
                                    </div>
                                    <strong style="font-size: 1.1rem; color: var(--primary-color);">
                                        <?php echo $col['count']; ?> 
                                        <span style="font-size: 0.85rem; font-weight: 400; color: var(--text-secondary);">
                                            concern<?php echo $col['count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$user['college_id']): ?>
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                                <small style="color: var(--text-secondary); font-size: 0.85rem;">
                                    Showing all 7 colleges: CCIS, CCJS, COED, CON, CAT, CEA, COM
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>

