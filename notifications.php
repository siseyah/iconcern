<?php
require_once 'config/config.php';
requireLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Mark as read if requested
if (isset($_GET['read']) && $_GET['read'] > 0) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?");
    $stmt->execute([$_GET['read'], $user_id]);
    header('Location: notifications.php');
    exit();
}

// Mark all as read
if (isset($_GET['read_all'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit();
}

// Get notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Notifications</h1>
            <p>Your alerts and updates</p>
        </div>

        <div class="action-buttons">
            <?php if (count($notifications) > 0): ?>
                <a href="notifications.php?read_all=1" class="btn btn-secondary">Mark All as Read</a>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <p>No notifications yet.</p>
            </div>
        <?php else: ?>
            <div class="concerns-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="concern-item" style="<?php echo !$notif['is_read'] ? 'border-left: 4px solid var(--primary-color);' : ''; ?>">
                        <div class="concern-header">
                            <span><?php echo formatDate($notif['sent_at']); ?></span>
                            <?php if (!$notif['is_read']): ?>
                                <span class="badge badge-info">New</span>
                            <?php endif; ?>
                        </div>
                        <p class="concern-text"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                        <div class="concern-footer">
                            <?php if ($notif['link']): ?>
                                <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="btn btn-sm btn-primary">View</a>
                            <?php endif; ?>
                            <?php if (!$notif['is_read']): ?>
                                <a href="notifications.php?read=<?php echo $notif['notif_id']; ?>" class="btn btn-sm btn-secondary">Mark as Read</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

