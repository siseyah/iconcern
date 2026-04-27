<?php
require_once 'config/config.php';
requireRole('student');

require_once 'includes/auth.php';
require_once 'includes/concern.php';
require_once 'includes/college.php';

$auth = new Auth();
$concern = new Concern();
$college = new College();
$user = $auth->getCurrentUser();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'full_name' => 'Student',
], $user);

// Get college departments only for concern submission (CCIS, CCJS, COED, CON, CAT, CEA, COM)
$collegeDepartments = $college->getCollegeDepartments();

// Destination dropdown should show school departments only.
$departments = $collegeDepartments;

// Debug: If no college departments found, fallback
if (empty($departments)) {
    $allDepts = $college->getDepartments();
    $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
    $departments = array_filter($allDepts, function($dept) use ($collegeCodes) {
        foreach ($collegeCodes as $code) {
            if (stripos($dept['department_name'], $code) !== false) {
                return true;
            }
        }
        return false;
    });
    $departments = array_values($departments);
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_concern'])) {
    $concern_text = $_POST['concern_text'] ?? '';
    $department_id = $_POST['department_id'] ?? '__AUTO__';
    $attachment = null;
    
    if (empty($department_id) || $department_id === '') {
        $department_id = '__AUTO__';
    }
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, ALLOWED_EXTENSIONS) && $file['size'] <= MAX_UPLOAD_SIZE) {
            $filename = 'concern_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = UPLOAD_DIR . $filename;
            
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $attachment = $filename;
            }
        }
    }
    
    $result = $concern->submitConcern(
        $_SESSION['user_id'],
        $user['college_id'],
        $concern_text,
        $attachment,
        $department_id
    );
    
    if ($result['success']) {
        $stage1 = $result['classification']['stage1_category'] ?? 'General Inquiry / School Updates';
        $stage2 = $result['classification']['stage2_selected'] ?? (($result['classification']['stage2_candidates'][0] ?? 'Help Desk'));
        $confidenceDecimal = isset($result['classification']['confidence']) ? number_format((float)$result['classification']['confidence'], 2, '.', '') : '0.50';
        $success = 'Concern submitted successfully! Prediction: ' . $stage1 . ' - (' . $stage2 . ') | Accuracy: ' . $confidenceDecimal;
        if (!empty($result['classification']['requires_sas_flow'])) {
            $success .= ' | Flow: SAS Office first, then Internet Laboratory';
        }
        // Clear form
        $_POST = [];
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
    <title>Submit Concern - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Submit a Concern</h1>
            <p>Describe your concern, select the department, and our svm will automatically categorize it</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" class="concern-form">
                <div class="form-group">
                    <label for="concern_text">Concern Description *</label>
                    <textarea id="concern_text" name="concern_text" rows="8" required placeholder="Describe your concern in detail..."><?php echo htmlspecialchars($_POST['concern_text'] ?? ''); ?></textarea>
                    <small>Be as detailed as possible. The system will automatically categorize your concern.</small>
                </div>

                <div class="form-group">
                    <label for="department_id">Select Destination (College/Office Category) *</label>
                    <select id="department_id" name="department_id" required>
                        <option value="__AUTO__" <?php echo (($_POST['department_id'] ?? '__AUTO__') === '__AUTO__') ? 'selected' : ''; ?>>
                            Auto-route by AI (recommended)
                        </option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>AI will route automatically by predicted category + detected college. You can still override manually.</small>
                </div>

                <div class="form-group">
                    <label for="attachment">Attachment (Optional)</label>
                    <input type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    <small>Max size: 5MB. Allowed: JPG, PNG, PDF, DOC, DOCX</small>
                </div>

                <div class="form-group">
                    <label>Your College</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['college_name'] ?? 'Not specified'); ?>" disabled>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_concern" class="btn btn-primary">Submit Concern</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="info-box">
            <h3>💡 Tips for Submitting Concerns</h3>
            <ul>
                <li>Be specific about your concern</li>
                <li>Include relevant details (dates, locations, etc.)</li>
                <li>Select the appropriate department to route your concern</li>
                <li>The system uses AI to automatically categorize your concern (e.g., Maintenance, Registrar)</li>
                <li>You will receive notifications about status updates</li>
            </ul>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>

