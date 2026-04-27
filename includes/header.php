<?php
if (!isset($user)) {
    // Determine base path based on current file location
    $base_path = '';
    if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/student/') !== false) {
        $base_path = '../';
    }
    
    require_once $base_path . 'config/config.php';
    require_once $base_path . 'includes/auth.php';
    $auth = new Auth();
    $user = $auth->getCurrentUser();
}

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'full_name' => 'User',
], $user);

$unread_count = 0;
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $unread_count = (int)($row['count'] ?? 0);
} catch (Throwable $e) {
    // Fail soft in header rendering to keep pages usable if DB temporarily drops.
    $unread_count = 0;
}

// Determine base path for navigation and current area
$nav_base = '';
$is_admin_area = false;
$is_student_area = false;
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $nav_base = '../';
    $is_admin_area = true;
} elseif (strpos($_SERVER['PHP_SELF'], '/student/') !== false) {
    $nav_base = '../';
    $is_student_area = true;
}

// Avoid duplicate Logout buttons when the page already renders its own sidebar logout.
// - Admin pages: sidebar always includes Logout.
// - Student dashboard: sidebar includes Logout.
$hide_header_logout = false;
if ($is_admin_area) {
    $hide_header_logout = true;
} elseif ($is_student_area || (!$is_admin_area && strpos($_SERVER['PHP_SELF'], '/student/dashboard.php') !== false)) {
    $hide_header_logout = true;
}
?>
<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <a href="<?php echo $nav_base; ?>index.php" style="text-decoration: none; color: var(--primary-color);">
                <h2><?php echo APP_NAME; ?></h2>
            </a>
        </div>
        <nav class="main-nav">
            <?php if (!$is_admin_area && getUserRole() === 'student'): ?>
                <a href="<?php echo $nav_base; ?>student/dashboard.php" class="nav-link">Dashboard</a>
                <a href="<?php echo $nav_base; ?>submit_concern.php" class="nav-link">Submit Concern</a>
                <a href="<?php echo $nav_base; ?>my_concerns.php" class="nav-link">My Concerns</a>
            <?php endif; ?>
            <?php if (!$is_admin_area && (getUserRole() === 'admin' || getUserRole() === 'staff')): ?>
                <a href="<?php echo $nav_base; ?>admin/dashboard.php" class="nav-link">Dashboard</a>
                <a href="<?php echo $nav_base; ?>admin/concerns.php" class="nav-link">Manage Concerns</a>
                <a href="<?php echo $nav_base; ?>admin/routing.php" class="nav-link">Routing</a>
                <?php if (getUserRole() === 'admin'): ?>
                    <a href="<?php echo $nav_base; ?>admin/users.php" class="nav-link">Users</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <a href="<?php echo $nav_base; ?>notifications.php" class="notification-icon">
                🔔
                <?php if ($unread_count > 0): ?>
                    <span class="badge badge-notification"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <?php if (!$hide_header_logout): ?>
                <a href="<?php echo $nav_base; ?>logout.php" class="btn btn-secondary btn-sm">Logout</a>
            <?php endif; ?>
            <div class="user-menu">
                <button type="button" class="user-name dropdown-toggle" aria-haspopup="menu" aria-expanded="false">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </button>
                <div class="dropdown" role="menu" aria-label="Profile menu">
                    <a href="<?php echo $nav_base; ?>profile.php">Profile</a>
                    <?php if (!$hide_header_logout): ?>
                        <a href="<?php echo $nav_base; ?>logout.php">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>
