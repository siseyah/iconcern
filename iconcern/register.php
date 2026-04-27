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
    // Allow role selection (student or admin)
    $role = $_POST['role'] ?? 'student';
    $college_id = !empty($_POST['college_id']) ? $_POST['college_id'] : null;
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    
    // Handle student registration (simplified)
    if ($role === 'student') {
        $student_id = $_POST['student_id'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($student_id) || empty($password) || empty($department_id)) {
            $error = 'Student ID, Password, and Department are required.';
        } else {
            // For students: use student_id as username, generate email and full_name
            $username = $student_id;
            $email = $student_id . '@student.nwssu.edu.ph'; // Auto-generate email
            $full_name = 'Student ' . $student_id; // Default name, can be updated later
            
            $result = $auth->register(
                $username,
                $password,
                $full_name,
                $email,
                $role,
                null, // Students don't have college_id initially
                $department_id
            );
            
            if ($result['success']) {
                $success = 'Registration successful! Please login.';
                // Auto login after registration using student_id as username
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
    // Handle admin registration (professional)
    else if ($role === 'admin') {
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        
        if (empty($department_id)) {
            $error = 'College/Department selection is required for admin registration. Select the college/department you will be administering (e.g., CCIS, CCJS, COED, CON, CAT, CEA, COM).';
        } else {
            // Get college_id from department (college departments have college info)
            $dept = $collegeHandler->getDepartment($department_id);
            $college_id = null;
            if ($dept) {
                // Try to find matching college by code in department name
                $collegeCodes = ['CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM'];
                foreach ($collegeCodes as $code) {
                    if (stripos($dept['department_name'], $code) !== false) {
                        $allColleges = $collegeHandler->getColleges();
                        foreach ($allColleges as $col) {
                            if ($col['college_code'] === $code) {
                                $college_id = $col['college_id'];
                                break 2;
                            }
                        }
                    }
                }
            }
            
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
                $success = 'Registration successful! Please login.';
                // Auto login after registration
                $login_result = $auth->login($_POST['username'], $_POST['password']);
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

// Get colleges and departments for dropdowns
$colleges = $collegeHandler->getColleges();
$departments = $collegeHandler->getDepartments();

// Get college departments only (CCIS, CCJS, COED, CON, CAT, CEA, COM)
// This method ensures all college departments exist and returns them without duplicates
$collegeDepartments = $collegeHandler->getCollegeDepartments();
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
            max-width: 700px;
            margin: 2rem auto;
            padding: 1rem;
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
            grid-template-columns: repeat(3, 1fr);
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
        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">🎓</div>
                <h1>Create Your Account</h1>
                <p>Join <?php echo APP_NAME; ?> and start managing your concerns</p>
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
                <!-- Role Selection -->
                <div class="form-section">
                    <div class="form-section-title">Account Type</div>
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="role_student" name="role" value="student" <?php echo (!isset($_POST['role']) || $_POST['role'] === 'student') ? 'checked' : ''; ?> onchange="updateFormFields()">
                            <label for="role_student">
                                <span class="role-icon">🎓</span>
                                <span class="role-name">Student</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="role_admin" name="role" value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'checked' : ''; ?> onchange="updateFormFields()">
                            <label for="role_admin">
                                <span class="role-icon">👨‍💼</span>
                                <span class="role-name">Admin</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Student Registration Fields -->
                <div class="form-section" id="studentFields">
                    <div class="form-section-title">Student Information</div>
                    <div class="form-group">
                        <label for="student_id">
                            Student ID
                            <span class="required">*</span>
                        </label>
                        <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>" placeholder="Enter your Student ID">
                        <span class="field-icon">🆔</span>
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">Your Student ID will be used as your username</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="department_id_student">
                            College/Department
                            <span class="required">*</span>
                        </label>
                        <select id="department_id_student" name="department_id" required>
                            <option value="">-- Select College/Department --</option>
                            <?php if (!empty($collegeDepartments)): ?>
                                <?php foreach ($collegeDepartments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No departments available. Please contact administrator.</option>
                            <?php endif; ?>
                        </select>
                        <span class="field-icon">🏢</span>
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">Select your college: CCIS, CCJS, COED, CON, CAT, CEA, or COM</small>
                    </div>
                </div>
                
                <!-- Admin Registration Fields -->
                <div class="form-section" id="adminFields" style="display: none;">
                    <div class="form-section-title">Professional Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">
                                Full Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="John Doe">
                            <span class="field-icon">👤</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">
                                Username
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="johndoe">
                            <span class="field-icon">@</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            Email Address
                            <span class="required">*</span>
                        </label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="john.doe@nwssu.edu.ph">
                        <span class="field-icon">✉️</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="department_id_admin">
                            College/Department
                            <span class="required">*</span>
                        </label>
                        <select id="department_id_admin" name="department_id" required>
                            <option value="">-- Select College/Department --</option>
                            <?php if (!empty($collegeDepartments)): ?>
                                <?php foreach ($collegeDepartments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No departments available. Please contact administrator.</option>
                            <?php endif; ?>
                        </select>
                        <span class="field-icon">🏢</span>
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">Select the college/department you will be administering: CCIS, CCJS, COED, CON, CAT, CEA, or COM. This determines which concerns you can see.</small>
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
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script>
        function updateFormFields() {
            const role = document.querySelector('input[name="role"]:checked').value;
            const studentFields = document.getElementById('studentFields');
            const adminFields = document.getElementById('adminFields');
            const studentIdField = document.getElementById('student_id');
            const departmentField = document.getElementById('department_id_student');
            const fullNameField = document.getElementById('full_name');
            const usernameField = document.getElementById('username');
            const emailField = document.getElementById('email');
            const departmentFieldAdmin = document.getElementById('department_id_admin');
            
            if (role === 'student') {
                // Show student fields, hide admin fields
                studentFields.style.display = 'block';
                adminFields.style.display = 'none';
                
                // Make student fields required
                if (studentIdField) studentIdField.setAttribute('required', 'required');
                if (departmentField) departmentField.setAttribute('required', 'required');
                
                // Remove required from admin fields
                if (fullNameField) fullNameField.removeAttribute('required');
                if (usernameField) usernameField.removeAttribute('required');
                if (emailField) emailField.removeAttribute('required');
                if (departmentFieldAdmin) departmentFieldAdmin.removeAttribute('required');
            } else {
                // Show admin fields, hide student fields
                studentFields.style.display = 'none';
                adminFields.style.display = 'block';
                
                // Make admin fields required
                if (fullNameField) fullNameField.setAttribute('required', 'required');
                if (usernameField) usernameField.setAttribute('required', 'required');
                if (emailField) emailField.setAttribute('required', 'required');
                if (departmentFieldAdmin) departmentFieldAdmin.setAttribute('required', 'required');
                
                // Remove required from student fields
                if (studentIdField) studentIdField.removeAttribute('required');
                if (departmentField) departmentField.removeAttribute('required');
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
        });
        
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
                const role = document.querySelector('input[name="role"]:checked').value;
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                // Validate based on role
                if (role === 'student') {
                    const studentId = document.getElementById('student_id').value;
                    const department = document.getElementById('department_id_student').value;
                    
                    if (!studentId || !department) {
                        e.preventDefault();
                        alert('Please fill in all required student fields!');
                        return false;
                    }
                } else {
                    const fullName = document.getElementById('full_name').value;
                    const username = document.getElementById('username').value;
                    const email = document.getElementById('email').value;
                    const department = document.getElementById('department_id_admin').value;
                    
                    if (!fullName || !username || !email || !department) {
                        e.preventDefault();
                        alert('Please fill in all required admin fields!');
                        return false;
                    }
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

