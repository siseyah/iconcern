<?php
require_once '../config/config.php';
requireRole('student');

require_once '../includes/auth.php';
require_once '../includes/concern.php';

$auth = new Auth();
$concern = new Concern();
$user = $auth->getCurrentUser();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'full_name' => 'Student',
], $user);

// Get user's concerns
$concerns = $concern->getConcerns(['user_id' => $_SESSION['user_id']]);

// Get statistics
$pending_count = count($concern->getConcerns(['user_id' => $_SESSION['user_id'], 'status' => 'Pending']));
$in_progress_count = count($concern->getConcerns(['user_id' => $_SESSION['user_id'], 'status' => 'In Progress']));
$resolved_count = count($concern->getConcerns(['user_id' => $_SESSION['user_id'], 'status' => 'Resolved']));

// Get unread notifications
$db = getDB();

// Top categories for this student (for dashboard charts)
$student_top_categories = [];
try {
    $catStmt = $db->prepare("
        SELECT cl.predicted_category, COUNT(*) as count
        FROM classifications cl
        INNER JOIN concerns c ON cl.concern_id = c.concern_id
        WHERE c.user_id = ? AND cl.predicted_category IS NOT NULL
        GROUP BY cl.predicted_category
        ORDER BY count DESC
        LIMIT 5
    ");
    $catStmt->execute([$_SESSION['user_id']]);
    $student_top_categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $student_top_categories = [];
}

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
    'GAD Office',
    'Campus Security Office',
    'Student Affairs Office',
    'Guidance Office',
];
$student_top_categories = array_values(array_filter($student_top_categories, function($row) use ($valid_categories) {
    return isset($row['predicted_category']) && in_array($row['predicted_category'], $valid_categories, true);
}));

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetch()['count'];

// Get recent concerns (last 5)
$recent_concerns = array_slice($concerns, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #student-report-actions {
            display: flex;
            justify-content: flex-end;
            margin: 0 0 1.25rem;
        }

        @media print {
            #student-report-actions, .main-header, .main-footer { display: none !important; }
            body { background: #fff !important; }
        }

        .student-dashboard {
            max-width: none;
            margin: 0;
            padding: 0;
            position: relative;
            min-height: 100vh;
        }

        /* Dashboard background logo: blurred and centered */
        .student-dashboard::before {
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
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
            filter: grayscale(40%) blur(22px);
        }

        .student-dashboard::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(14, 165, 233, 0.10) 0%, transparent 55%);
            z-index: 0;
            pointer-events: none;
        }

        .student-dashboard > * {
            position: relative;
            z-index: 1;
        }
        
        .student-hero {
            background: var(--card-bg);
            color: var(--text-primary);
            border-radius: var(--radius-2xl);
            padding: 3.5rem 3rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
        }
        
        .student-hero::before {
            content: none;
        }
        
        .student-hero::after {
            content: none;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(20px, 20px) rotate(180deg); }
        }
        
        .student-hero h1 {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
            text-shadow: none;
        }
        
        .student-hero p {
            font-size: 1.15rem;
            opacity: 0.85;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }

        .student-overview {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .student-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .student-stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
        }
        
        .student-stat-card::before {
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
        
        .student-stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-100);
        }
        
        .student-stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .student-stat-card .stat-label {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .student-stat-card .stat-value {
            font-size: 3rem;
            font-weight: 900;
            background: var(--bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            letter-spacing: -0.03em;
        }
        
        .student-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .student-action-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 2.25rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        
        .student-action-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.03) 0%, rgba(124, 58, 237, 0.03) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .student-action-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-100);
        }
        
        .student-action-card:hover::before {
            opacity: 1;
        }
        
        .action-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-lg);
            background: var(--bg-gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.875rem;
            margin-bottom: 0.5rem;
            box-shadow: var(--shadow-md);
            position: relative;
            z-index: 1;
            transition: transform 0.4s ease;
        }
        
        .student-action-card:hover .action-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .action-content h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .action-content p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .recent-section {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .section-title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .section-title-bar h2 {
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .tips-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .tips-card h3 {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tips-card ul {
            list-style: none;
            padding: 0;
        }
        
        .tips-card li {
            padding: 0.75rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--text-primary);
        }
        
        .tips-card li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-weight: bold;
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="layout-admin">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <div class="logo"></div>
                <div class="text">Student Panel</div>
            </div>
            <nav class="admin-nav">
                <a href="../student/dashboard.php">🏠 Dashboard</a>
                <a href="../submit_concern.php">📝 Submit Concern</a>
                <a href="../my_concerns.php">📋 My Concerns</a>
                <a href="../notifications.php">
                    🔔 Notifications
                    <?php if (!empty($unread_count) && (int)$unread_count > 0): ?>
                        <span class="badge badge-notification" style="position: static; margin-left: 0.5rem;"><?php echo (int)$unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            <div style="margin-top: 2rem;">
                <a href="../logout.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Logout</a>
                <div style="margin-top: 0.75rem; color: rgba(226,232,240,0.85); font-size: 0.92rem; word-break: break-word;">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </div>
            </div>
        </aside>

        <main class="admin-content">
            <div class="student-dashboard">
        <!-- Hero Section -->
        <div class="student-hero">
            <div class="student-overview">
                <h1>Overview</h1>
                <p>Here's what's happening today!</p>
            </div>
            <div id="student-report-actions">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Save as PDF</button>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="student-stats-grid">
            <div class="student-stat-card">
                <div class="stat-label">Total Concerns</div>
                <div class="stat-value"><?php echo count($concerns); ?></div>
            </div>
            <div class="student-stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo $pending_count; ?></div>
            </div>
            <div class="student-stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value"><?php echo $in_progress_count; ?></div>
            </div>
            <div class="student-stat-card">
                <div class="stat-label">Resolved</div>
                <div class="stat-value"><?php echo $resolved_count; ?></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="student-actions-grid">
            <div class="student-action-card">
                <div class="action-icon">📝</div>
                <div class="action-content">
                    <h3>Submit New Concern</h3>
                    <p>Report an issue or concern to the administration. Our AI will automatically classify and route it.</p>
                    <a href="../submit_concern.php" class="btn btn-primary">Submit Concern</a>
                </div>
            </div>
            
            <div class="student-action-card">
                <div class="action-icon">📊</div>
                <div class="action-content">
                    <h3>My Concerns</h3>
                    <p>View and track all your submitted concerns with real-time status updates.</p>
                    <a href="../my_concerns.php" class="btn btn-secondary">View All Concerns</a>
                </div>
            </div>
            
            <div class="student-action-card">
                <div class="action-icon">🔔</div>
                <div class="action-content">
                    <h3>Notifications</h3>
                    <p><?php echo $unread_count > 0 ? "You have <strong>{$unread_count}</strong> unread notifications" : "All caught up! No new notifications."; ?></p>
                    <a href="../notifications.php" class="btn btn-secondary">View Notifications</a>
                </div>
            </div>
        </div>

        <!-- Recent Concerns -->
        <div class="recent-section">
            <div class="section-title-bar">
                <h2>Recent Concerns</h2>
                <a href="../my_concerns.php" class="btn btn-sm btn-outline">View All →</a>
            </div>
            
            <?php if (empty($recent_concerns)): ?>
                <div class="empty-state">
                    <p>You haven't submitted any concerns yet.</p>
                    <a href="../submit_concern.php" class="btn btn-primary">Submit Your First Concern</a>
                </div>
            <?php else: ?>
                <div class="concerns-list">
                    <?php foreach ($recent_concerns as $c): ?>
                        <div class="concern-item">
                            <div class="concern-header">
                                <span class="concern-id">Concern #<?php echo $c['concern_id']; ?></span>
                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $c['status'])); ?>">
                                    <?php echo $c['status']; ?>
                                </span>
                            </div>
                            <p class="concern-text"><?php echo htmlspecialchars(substr($c['concern_text'], 0, 150)); ?>...</p>
                            <div class="concern-footer">
                                <span class="concern-meta">
                                    <?php if ($c['predicted_category']): ?>
                                        <span class="badge badge-info"><?php echo $c['predicted_category']; ?></span>
                                    <?php endif; ?>
                                    <?php echo formatDate($c['created_at']); ?>
                                </span>
                                <a href="../concern.php?id=<?php echo $c['concern_id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Tips -->
        <div class="tips-card">
            <h3>💡 Tips for Students</h3>
            <ul>
                <li>Be specific when describing your concern for better AI classification</li>
                <li>Check notifications regularly for status updates</li>
                <li>You can track all your concerns in "My Concerns" section</li>
                <li>Attachments can be uploaded (images, documents) to support your concern</li>
            </ul>
        </div>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>

