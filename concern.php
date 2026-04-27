<?php
require_once 'config/config.php';
requireLogin();

require_once 'includes/auth.php';
require_once 'includes/concern.php';

$auth = new Auth();
$concern = new Concern();
$user = $auth->getCurrentUser();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'full_name' => 'User',
], $user);

$concern_id = $_GET['id'] ?? 0;
$concern_data = $concern->getConcern($concern_id);

if (!$concern_data) {
    header('Location: index.php');
    exit();
}

// Check if user has permission to view this concern
if (getUserRole() === 'student' && $concern_data['user_id'] != $_SESSION['user_id']) {
    header('Location: index.php');
    exit();
}

$routing = $concern->getRouting($concern_id);
$status_history = $concern->getStatusHistory($concern_id);
$display_classification = $concern->getDisplayClassification($concern_data, $routing);
$stage1Label = str_ireplace(' Concern', '', (string)$display_classification['stage1_category']);

$error = '';
$success = '';

// Handle status update (staff/admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && (getUserRole() === 'admin' || getUserRole() === 'staff')) {
    $result = $concern->updateStatus(
        $concern_id,
        $_POST['status'],
        $_SESSION['user_id'],
        $_POST['notes'] ?? null
    );
    
    if ($result['success']) {
        $success = $result['message'];
        // Refresh data
        $concern_data = $concern->getConcern($concern_id);
        $status_history = $concern->getStatusHistory($concern_id);
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concern Details - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Concern Details</h1>
            <p>Concern #<?php echo $concern_id; ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <div class="concern-header" style="margin-bottom: 1.5rem;">
                <span class="concern-id">Concern #<?php echo $concern_id; ?></span>
                <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $concern_data['status'])); ?>">
                    <?php echo $concern_data['status']; ?>
                </span>
            </div>

            <div class="form-group">
                <label>Submitted By</label>
                <p><?php echo htmlspecialchars($concern_data['full_name']); ?> (<?php echo htmlspecialchars($concern_data['email']); ?>)</p>
            </div>

            <div class="form-group">
                <label>College</label>
                <p><?php echo htmlspecialchars($concern_data['college_name'] ?? 'Not specified'); ?></p>
            </div>

            <div class="form-group">
                <label>Classification</label>
                <p>
                    <?php
                        $stage1Display = trim((string)$stage1Label);
                        $stage2Display = trim((string)($display_classification['stage2_selected'] ?? ''));
                        if ($stage1Display !== '' && $stage2Display !== '') {
                            $classificationLabel = $stage1Display . ' - ' . $stage2Display;
                        } elseif ($stage1Display !== '') {
                            $classificationLabel = $stage1Display;
                        } else {
                            $classificationLabel = $stage2Display;
                        }
                    ?>
                    <span class="badge badge-info">
                        <?php echo htmlspecialchars($classificationLabel); ?>
                    </span>
                    <span style="margin-left: 0.5rem; color: var(--text-secondary);">
                        Accuracy: <?php echo number_format((float)$display_classification['confidence'], 2, '.', ''); ?>
                    </span>
                </p>
            </div>

            <div class="form-group">
                <label>Concern Description</label>
                <div style="background: var(--bg-color); padding: 1rem; border-radius: 0.5rem; white-space: pre-wrap;">
                    <?php echo htmlspecialchars($concern_data['concern_text']); ?>
                </div>
            </div>

            <?php if ($concern_data['attachment']): ?>
                <div class="form-group">
                    <label>Attachment</label>
                    <p>
                        <a href="uploads/<?php echo htmlspecialchars($concern_data['attachment']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                            View Attachment
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Submitted</label>
                <p><?php echo formatDate($concern_data['created_at']); ?></p>
            </div>
        </div>

            <?php if (!empty($routing)): ?>
            <div class="form-card">
                <h3>Routing Information</h3>
                <?php foreach ($routing as $route): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 0.5rem;">
                        <p><strong>Routed to Department:</strong> 
                            <?php if ($route['department_name']): ?>
                                <span class="badge badge-info"><?php echo htmlspecialchars($route['department_name']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">Not specified</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($route['college_name']): ?>
                            <p><strong>College:</strong> <?php echo htmlspecialchars($route['college_name']); ?></p>
                        <?php endif; ?>
                        <p><strong>Routed by:</strong> <?php echo htmlspecialchars($route['routed_by_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo formatDate($route['routed_at']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="form-card">
                <h3>Routing Information</h3>
                <p style="color: var(--text-secondary);">This concern has not been routed to a department yet.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($status_history)): ?>
            <div class="form-card">
                <h3>Status History</h3>
                <div style="margin-top: 1rem;">
                    <?php foreach ($status_history as $history): ?>
                        <div style="margin-bottom: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 0.5rem; border-left: 4px solid var(--primary-color);">
                            <p><strong><?php echo htmlspecialchars($history['new_status']); ?></strong></p>
                            <?php if ($history['old_status']): ?>
                                <p style="color: var(--text-secondary); font-size: 0.875rem;">
                                    Changed from <?php echo htmlspecialchars($history['old_status']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($history['notes']): ?>
                                <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($history['notes'])); ?></p>
                            <?php endif; ?>
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                                Updated by <?php echo htmlspecialchars($history['updated_by_name']); ?> on <?php echo formatDate($history['updated_at']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (getUserRole() === 'admin' || getUserRole() === 'staff'): ?>
            <div class="form-card">
                <h3>Update Status</h3>
                <form method="POST" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label for="status">New Status</label>
                        <select id="status" name="status" required>
                            <option value="Pending" <?php echo $concern_data['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $concern_data['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $concern_data['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Add any notes about this status update..."></textarea>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </form>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem;">
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

