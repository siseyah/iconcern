<?php
require_once '../config/config.php';
requireRole(['admin']);

require_once '../includes/auth.php';
require_once '../includes/college.php';

$auth = new Auth();
$college = new College();
$user = $auth->getCurrentUser();
$db = getDB();

if (!is_array($user)) {
    $user = [];
}

$user = array_merge([
    'college_id' => null,
    'college_name' => '',
    'full_name' => 'Admin',
], $user);

$error = '';
$success = '';

// Get colleges - if admin is college-specific, only show their college
$colleges = [];
if ($user['college_id']) {
    $college_data = $college->getCollege($user['college_id']);
    if ($college_data) {
        $colleges = [$college_data];
    }
} else {
    $colleges = $college->getColleges();
}

$departments = $college->getDepartments();
// Get college departments for selection (CCIS, CCJS, COED, CON, CAT, CEA, COM)
// This method ensures all college departments exist and returns them without duplicates
$collegeDepartments = $college->getCollegeDepartments();

// Office category departments (Maintenance, Registrar, etc.) for admin selection
$officeCategories = [
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
    'Guidance Office'
];

$officeDepartments = [];
foreach ($officeCategories as $cat) {
    $stmt = $db->prepare("SELECT department_id, department_name, description FROM departments WHERE department_name = ?");
    $stmt->execute([$cat]);
    $dept = $stmt->fetch();
    if (!$dept) {
        $desc = $cat . " department (auto-created for office dashboard)";
        $ins = $db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
        $ins->execute([$cat, $desc]);
        $dept = [
            'department_id' => $db->lastInsertId(),
            'department_name' => $cat,
            'description' => $desc
        ];
    }
    $officeDepartments[] = $dept;
}

$adminDepartmentOptions = array_values(array_merge($collegeDepartments, $officeDepartments));

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $role = $_POST['role'] ?? 'student';
    
    // For admins, require department_id (which represents the college/department they'll manage)
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    
    // Get college_id from department (college departments have college info)
    $college_id = null;
    if ($department_id) {
        $dept = $college->getDepartment($department_id);
        if ($dept) {
            // Try to find matching college by code in department name
            $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
            foreach ($collegeCodes as $code) {
                if (stripos($dept['department_name'], $code) !== false) {
                    $allColleges = $college->getColleges();
                    foreach ($allColleges as $col) {
                        if ($col['college_code'] === $code) {
                            $college_id = $col['college_id'];
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    // If admin is college-specific, ensure department matches their college
    if ($user['college_id'] && $college_id && $college_id != $user['college_id']) {
        $error = 'You can only register users for your assigned college.';
    } else if ($user['college_id'] && !$college_id) {
        // If admin is college-specific but no college found from department, use their college
        $college_id = $user['college_id'];
    }
    
    // Validate admin registration - require department
    if ($role === 'admin' && empty($department_id)) {
        $error = 'Department selection is required for admin registration. Choose a college/department (CCIS, CCJS, COED, CON, CAT, CEA, COM) OR an office category (Maintenance, Registrar, MIS, etc.).';
    } else {
        // For admins, use department_id to determine which concerns they can see
        // For students, use department_id normally
        $result = $auth->register(
            $_POST['username'],
            $_POST['password'],
            $_POST['full_name'],
            $_POST['email'],
            $role,
            $college_id,
            $department_id
        );
        
        if ($result['success']) {
            $success = 'User registered successfully!';
            $_POST = []; // Clear form
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .register-user-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .form-section {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-light);
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .role-option {
            position: relative;
        }
        
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .role-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--card-bg);
            text-align: center;
        }
        
        .role-option label:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .role-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .role-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    
        #register-user-report-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }
        
        @media print {
            #register-user-report-actions, .main-header, .main-footer { display: none !important; }
            body { background: #fff !important; }
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
            <div class="container">
        <div class="page-header">
            <h1>Register New User</h1>
            <p><?php echo $user['college_name'] ? 'Register users for ' . htmlspecialchars($user['college_name']) : 'Register new users to the system'; ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="register-user-container">
            <form method="POST" action="" class="auth-form">
                <!-- Role Selection -->
                <div class="form-section">
                    <div class="form-section-title">Account Type</div>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="role_student" name="role" value="student" checked onchange="updateFormFields()">
                            <label for="role_student">
                                <span class="role-icon">🎓</span>
                                <span class="role-name">Student</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_admin" name="role" value="admin" onchange="updateFormFields()">
                            <label for="role_admin">
                                <span class="role-icon">👨‍💼</span>
                                <span class="role-name">Admin</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-title">Personal Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- College/Department Selection -->
                <div class="form-section">
                    <div class="form-section-title">College/Department Assignment</div>
                    <div class="form-group" id="departmentGroup">
                        <label for="department_id">
                            College/Department
                            <span id="deptRequired" style="display: none;" class="required">*</span>
                        </label>
                        <select id="department_id" name="department_id">
                            <option value="">-- Select College/Department --</option>
                            <?php if (!empty($adminDepartmentOptions)): ?>
                                <?php foreach ($adminDepartmentOptions as $d): ?>
                                    <?php
                                        $type = in_array($d['department_name'], $officeCategories, true) ? 'office' : 'college';
                                    ?>
                                    <option value="<?php echo $d['department_id']; ?>"
                                            data-type="<?php echo $type; ?>"
                                            <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $d['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No departments available. Please contact administrator.</option>
                            <?php endif; ?>
                        </select>
                        <small id="deptHelp" style="display: none; margin-top: 0.5rem; color: var(--text-secondary);">Select the college/department you will be administering (CCIS, CCJS, COED, CON, CAT, CEA, COM)</small>
                    </div>
                </div>

                <!-- Security Information -->
                <div class="form-section">
                    <div class="form-section-title">Security</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" onkeyup="checkPasswordMatch()">
                            <small id="passwordMatch" style="display: none; margin-top: 0.5rem;"></small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="register_user" class="btn btn-primary">Register User</button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
        function updateFormFields() {
            const role = document.querySelector('input[name="role"]:checked').value;
            const departmentGroup = document.getElementById('departmentGroup');
            const departmentSelect = document.getElementById('department_id');
            const deptRequired = document.getElementById('deptRequired');
            const deptHelp = document.getElementById('deptHelp');
            
            if (role === 'admin') {
                // Show department for admins and make it required
                departmentGroup.style.display = 'block';
                departmentSelect.setAttribute('required', 'required');
                if (deptRequired) deptRequired.style.display = 'inline';
                if (deptHelp) deptHelp.style.display = 'block';
                // Enable all options for admins
                if (departmentSelect) {
                    departmentSelect.querySelectorAll('option[data-type="office"]').forEach(opt => opt.disabled = false);
                }
            } else {
                // For students, department is optional
                departmentGroup.style.display = 'block';
                departmentSelect.removeAttribute('required');
                if (deptRequired) deptRequired.style.display = 'none';
                if (deptHelp) deptHelp.style.display = 'none';
                // Disable office options for student registration
                if (departmentSelect) {
                    departmentSelect.querySelectorAll('option[data-type="office"]').forEach(opt => opt.disabled = true);
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
        });
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIndicator = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchIndicator.style.display = 'none';
                return;
            }
            
            matchIndicator.style.display = 'block';
            if (password === confirmPassword) {
                matchIndicator.textContent = '✓ Passwords match';
                matchIndicator.style.color = 'var(--success-color)';
            } else {
                matchIndicator.textContent = '✗ Passwords do not match';
                matchIndicator.style.color = 'var(--error-color)';
            }
        }
        
        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>
