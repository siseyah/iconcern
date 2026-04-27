<?php
require_once 'config/config.php';
requireRole('student');

require_once 'includes/auth.php';
require_once 'includes/concern.php';

$auth = new Auth();
$concern = new Concern();
$user = $auth->getCurrentUser();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'full_name' => 'Student',
], $user);

$concerns = $concern->getConcerns(['user_id' => $_SESSION['user_id']]);

// Get routing information for each concern
foreach ($concerns as &$c) {
    $routing = $concern->getRouting($c['concern_id']);
    $c['routing'] = $routing;
    $c['display_classification'] = $concern->getDisplayClassification($c, $routing);
}
unset($c);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Concerns - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Concerns</h1>
            <p>View all your submitted concerns</p>
        </div>

        <div class="action-buttons">
            <a href="submit_concern.php" class="btn btn-primary">Submit New Concern</a>
        </div>

        <?php if (empty($concerns)): ?>
            <div class="empty-state">
                <p>You haven't submitted any concerns yet.</p>
                <a href="submit_concern.php" class="btn btn-primary">Submit Your First Concern</a>
            </div>
        <?php else: ?>
            <div class="concerns-list">
                <?php foreach ($concerns as $c): ?>
                    <div class="concern-item">
                        <div class="concern-header">
                            <span class="concern-id">Concern #<?php echo $c['concern_id']; ?></span>
                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $c['status'])); ?>">
                                <?php echo $c['status']; ?>
                            </span>
                        </div>
                        <p class="concern-text"><?php echo nl2br(htmlspecialchars($c['concern_text'])); ?></p>
                        <div class="concern-footer">
                            <div class="concern-meta">
                                <?php
                                    $dc = $c['display_classification'] ?? null;
                                    $stage1Label = $dc ? str_ireplace(' Concern', '', (string)$dc['stage1_category']) : null;
                                ?>
                                <?php if ($dc): ?>
                                    <?php
                                        $stage2Label = trim((string)($dc['stage2_selected'] ?? ''));
                                        $stage1LabelClean = trim((string)$stage1Label);
                                        if ($stage1LabelClean !== '' && $stage2Label !== '') {
                                            $categoryLabel = $stage1LabelClean . ' - ' . $stage2Label;
                                        } elseif ($stage1LabelClean !== '') {
                                            $categoryLabel = $stage1LabelClean;
                                        } else {
                                            $categoryLabel = $stage2Label;
                                        }
                                        if (!empty($dc['requires_sas_flow'])) {
                                            $categoryLabel = 'SAS Office - Internet Laboratory';
                                        }
                                    ?>
                                    <span class="badge badge-info">Category: <?php echo htmlspecialchars($categoryLabel); ?></span>
                                    <span>(<?php echo number_format((float)$dc['confidence'] * 100, 1); ?>% confidence)</span>
                                <?php elseif ($c['predicted_category']): ?>
                                    <span class="badge badge-info">Category: <?php echo htmlspecialchars($c['predicted_category']); ?></span>
                                    <?php if ($c['confidence_score']): ?>
                                        <span>(<?php echo number_format($c['confidence_score'] * 100, 1); ?>% confidence)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($dc['requires_sas_flow']) && !empty($c['college_name'])): ?>
                                    <span class="badge badge-secondary">Dept: <?php echo htmlspecialchars($c['college_name']); ?></span>
                                <?php elseif (!empty($c['routing']) && isset($c['routing'][0]['department_name'])): ?>
                                    <span class="badge badge-secondary">Dept: <?php echo htmlspecialchars($c['routing'][0]['department_name']); ?></span>
                                <?php endif; ?>
                                <span>Submitted: <?php echo formatDate($c['created_at']); ?></span>
                            </div>
                            <a href="concern.php?id=<?php echo $c['concern_id']; ?>" class="btn btn-sm btn-primary">View Details</a>
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

