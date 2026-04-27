<?php
require_once 'config/config.php';
require_once 'includes/college.php';
$collegeHandler = new College();

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    require_once 'includes/auth.php';
    
    $auth = new Auth();
    // Public self-registration is student-only.
    $role = 'student';
    $college_id = !empty($_POST['college_id']) ? $_POST['college_id'] : null;
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null; // student department/course
    
    // Handle student registration (simplified)
    if ($role === 'student') {
        $student_id = trim((string)($_POST['student_id'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        // Trim and normalize email (optional contact email)
        $email = trim($_POST['student_email'] ?? '');
        $email = strtolower($email);
        $email = str_replace(["\r", "\n", " "], '', $email);
        // If user pasted extra characters (e.g. trailing ']'), try to extract a valid email.
        if (preg_match('/([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})\b/i', $email, $m)) {
            $email = strtolower($m[1]);
        }

        if (empty($student_id) || empty($password)) {
            $error = 'Student ID and password are required.';
        } else if (!preg_match('/^\d{2}-\d{5}$/', $student_id)) {
            $error = 'Student ID must be in the format 22-00000.';
        } else if ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } else if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must include uppercase, lowercase, and a number.';
        } else {
            $dir = $auth->getPreRegisteredStudent($student_id);
            if (!$dir) {
                $error = 'Student ID not found in the pre-registered list. Please contact the administrator.';
            } else {
                // Derive department_id and college_id from directory course text (CCIS/COED/etc.)
                $courseText = (string)($dir['course_year_level'] ?? '');
                $deptId = null;
                $collegeId = null;

                $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
                $matchedCode = null;
                foreach ($collegeCodes as $code) {
                    if (stripos($courseText, $code) !== false) {
                        $matchedCode = $code;
                        break;
                    }
                }

                if ($matchedCode) {
                    // Find department that contains the code
                    $deptStmt = getDB()->prepare("SELECT department_id FROM departments WHERE department_name LIKE ? ORDER BY department_id ASC LIMIT 1");
                    $deptStmt->execute(['%' . $matchedCode . '%']);
                    $deptRow = $deptStmt->fetch();
                    if ($deptRow) {
                        $deptId = (int)$deptRow['department_id'];
                    }

                    $colStmt = getDB()->prepare("SELECT college_id FROM colleges WHERE college_code = ? LIMIT 1");
                    $colStmt->execute([$matchedCode]);
                    $colRow = $colStmt->fetch();
                    if ($colRow) {
                        $collegeId = (int)$colRow['college_id'];
                    }
                }

                $contactEmail = '';
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $contactEmail = $email;
                } else {
                    // Use a deterministic placeholder to satisfy NOT NULL constraint.
                    $contactEmail = strtolower($student_id) . '@example.invalid';
                }

                $result = $auth->register(
                    $student_id,
                    $password,
                    (string)$dir['full_name'],
                    $contactEmail,
                    'student',
                    $collegeId,
                    $deptId
                );

                if ($result['success']) {
                    $success = 'Registration successful! Redirecting to your dashboard...';
                    // Auto login after registration using student ID
                    $login_result = $auth->login($student_id, $password);
                    if ($login_result['success']) {
                        header('Location: index.php');
                        exit();
                    }
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Get colleges and departments for dropdowns
$colleges = $collegeHandler->getColleges();
$departments = $collegeHandler->getDepartments();

// Get college departments only (CCIS, CCJS, COED, CON, CAT, CEA, COM)
// This method ensures all college departments exist and returns them without duplicates
$collegeDepartments = $collegeHandler->getCollegeDepartments();

$selected_role = 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.auth-page {
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .register-container {
            width: 100%;
            max-width: 1050px;
            margin: 2rem auto;
            padding: 1rem;
        }
        
        .register-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
        }
        
        .register-sidebar {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            position: sticky;
            top: 110px;
        }
        
        .register-sidebar h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 800;
        }
        
        .register-sidebar ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .register-sidebar li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .register-sidebar li:last-child {
            border-bottom: none;
        }
        
        .register-sidebar .dot {
            color: var(--success-color);
            font-weight: 900;
            margin-right: 0.5rem;
        }
        
        .register-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-xl);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            width: 100%;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            body.auth-page {
                padding: 1rem 0.5rem;
            }
            
            .register-container {
                max-width: 100%;
                padding: 0.5rem;
                margin: 1rem auto;
            }
            
            .register-layout {
                grid-template-columns: 1fr;
            }
            
            .register-sidebar {
                position: relative;
                top: 0;
            }
            
            .register-card {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr !important;
            }
            
            .role-selector {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 640px) {
            body.auth-page {
                padding: 0.5rem;
            }
            
            .register-container {
                margin: 0.5rem auto;
            }
            
            .register-card {
                padding: 1.25rem;
            }
            
            .register-header h1 {
                font-size: 1.5rem;
            }
            
            .form-section {
                margin-bottom: 1.5rem;
            }
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .register-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .register-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            border-radius: var(--radius-lg);
            background: var(--bg-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
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
            padding: 1.25rem 1rem;
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
            transform: translateY(-2px);
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
            font-size: 0.9rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr !important;
            }
            
            .role-selector {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 640px) {
            .register-card {
                padding: 1.25rem;
            }
            
            .register-header h1 {
                font-size: 1.5rem;
            }
            
            .form-section {
                margin-bottom: 1.5rem;
            }
        }
        
        .form-group {
            position: relative;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group label .required {
            color: var(--error-color);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--card-bg);
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            transform: translateY(-1px);
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .password-strength.weak .password-strength-bar {
            width: 33%;
            background: var(--error-color);
        }
        
        .password-strength.medium .password-strength-bar {
            width: 66%;
            background: var(--warning-color);
        }
        
        .password-strength.strong .password-strength-bar {
            width: 100%;
            background: var(--success-color);
        }
        
        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
        }
        
        .password-requirements li {
            padding: 0.25rem 0;
            padding-left: 1.25rem;
            position: relative;
        }
        
        .password-requirements li::before {
            content: "○";
            position: absolute;
            left: 0;
        }
        
        .password-requirements li.valid::before {
            content: "✓";
            color: var(--success-color);
        }
        
        .register-footer {
            margin-top: 2rem;
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
        
        .alert-enhanced {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }
        
        .alert-enhanced.alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-enhanced.alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-icon {
            font-size: 1.25rem;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .field-icon {
            position: absolute;
            right: 1rem;
            top: 2.5rem;
            color: var(--text-secondary);
            pointer-events: none;
        }
        
        .form-group:has(input:focus) .field-icon {
            color: var(--primary-color);
        }
    </style>
</head>
<body class="auth-page">
    <div class="register-container">
        <div class="register-layout">
            <div class="register-card">
                <div class="register-header">
                    <div class="register-logo">🎓</div>
                    <h1>Student Registration Dashboard</h1>
                    <p>Create your student account and start submitting concerns</p>
                </div>
            
                <?php if ($error): ?>
                    <div class="alert-enhanced alert-error">
                        <span class="alert-icon">⚠️</span>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
            
                <?php if ($success): ?>
                    <div class="alert-enhanced alert-success">
                        <span class="alert-icon">✓</span>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="auth-form" id="registerForm">
                    <input type="hidden" name="role" value="student">
                    
                    <!-- Student Registration Fields -->
                    <div class="form-section" id="studentFields">
                        <div class="form-section-title">Student Information</div>
                        <div class="form-group">
                            <label for="student_id">
                                Student ID
                                <span class="required">*</span>
                            </label>
                            <input type="text"
                                   id="student_id"
                                   name="student_id"
                                   value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
                                   placeholder="22-00000"
                                   required
                                   pattern="\d{2}-\d{5}"
                                   title="Format: 22-00000">
                            <span class="field-icon">🆔</span>
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">This Student ID will be your login username.</small>
                        </div>

                        <div class="form-group">
                            <label for="student_email">
                                Email Address (Optional)
                            </label>
                            <input type="email"
                                   id="student_email"
                                   name="student_email"
                                   value="<?php echo htmlspecialchars($_POST['student_email'] ?? ($_POST['email'] ?? '')); ?>"
                                   placeholder="name@example.com"
                                   title="Enter a valid email address (e.g., name@example.com)">
                            <span class="field-icon">✉️</span>
                            <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">Used for notifications/contact (not for login).</small>
                        </div>
                        
                    </div>
                    
                    <!-- Security Information -->
                    <div class="form-section">
                        <div class="form-section-title">Security</div>
                        <div class="form-group">
                            <label for="password">
                                Password
                                <span class="required">*</span>
                            </label>
                            <input type="password" id="password" name="password" required minlength="8" placeholder="Enter your password" onkeyup="checkPasswordStrength()">
                            <span class="field-icon">🔒</span>
                            <div class="password-strength" id="passwordStrength">
                                <div class="password-strength-bar"></div>
                            </div>
                            <div class="password-requirements" id="passwordRequirements">
                                <small>Password must contain:</small>
                                <ul>
                                    <li id="req-length">At least 8 characters</li>
                                    <li id="req-uppercase">One uppercase letter</li>
                                    <li id="req-lowercase">One lowercase letter</li>
                                    <li id="req-number">One number</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">
                                Confirm Password
                                <span class="required">*</span>
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm your password" onkeyup="checkPasswordMatch()">
                            <span class="field-icon">🔒</span>
                            <small id="passwordMatch" style="display: none; margin-top: 0.5rem;"></small>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-primary btn-block" style="margin-top: 1rem; padding: 1rem; font-size: 1.05rem; font-weight: 600;">
                        Create Account
                    </button>
                </form>
                
                <div class="register-footer">
                    <p>Already activated? <a href="student_login.php">Login here</a></p>
                </div>
            </div>
            
            <aside class="register-sidebar">
                <h3>Student Dashboard Checklist</h3>
                <ul>
                    <li><span class="dot">✓</span>Enter your First and Last Name</li>
                    <li><span class="dot">✓</span>Use a valid email address</li>
                    <li><span class="dot">✓</span>Select your Course (College/Department)</li>
                    <li><span class="dot">✓</span>Create a strong password</li>
                    <li><span class="dot">✓</span>After registration, you will be redirected to your dashboard</li>
                </ul>
            </aside>
        </div>
    </div>
    <script>
        function updateFormFields() {
            const studentFields = document.getElementById('studentFields');
            if (studentFields) {
                studentFields.style.display = 'block';
            }
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.querySelector('.password-strength-bar');
            const strengthContainer = document.getElementById('passwordStrength');
            const reqLength = document.getElementById('req-length');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqNumber = document.getElementById('req-number');
            
            let strength = 0;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Update requirement indicators
            reqLength.classList.toggle('valid', hasLength);
            reqUppercase.classList.toggle('valid', hasUppercase);
            reqLowercase.classList.toggle('valid', hasLowercase);
            reqNumber.classList.toggle('valid', hasNumber);
            
            // Calculate strength
            if (hasLength) strength++;
            if (hasUppercase) strength++;
            if (hasLowercase) strength++;
            if (hasNumber) strength++;
            
            // Update strength bar
            strengthContainer.className = 'password-strength';
            if (strength <= 1) {
                strengthContainer.classList.add('weak');
            } else if (strength <= 2) {
                strengthContainer.classList.add('medium');
            } else if (strength >= 3) {
                strengthContainer.classList.add('strong');
            }
        }
        
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
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
            
            const form = document.getElementById('registerForm');
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                const studentId = document.getElementById('student_id').value;
                const email = document.getElementById('student_email').value;
                
                if (!studentId) {
                    e.preventDefault();
                    alert('Please fill in all required student fields!');
                    return false;
                }

                if (!/^\d{2}-\d{5}$/.test(studentId)) {
                    e.preventDefault();
                    alert('Student ID must be in the format 22-00000');
                    return false;
                }
                
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
        });
    </script>
</body>
</html>

