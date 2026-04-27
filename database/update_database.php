<?php
/**
 * Database Update Script
 * Run this script to ensure all colleges and departments are properly set up
 * Access via: http://localhost/iconcern/database/update_database.php
 */

require_once __DIR__ . '/../config/config.php';

$db = getDB();
$errors = [];
$success = [];

// Define all required colleges
$requiredColleges = [
    ['code' => 'CCIS', 'name' => 'College of Computing and Information Sciences'],
    ['code' => 'CCJS', 'name' => 'College of Criminal Justice Studies'],
    ['code' => 'COED', 'name' => 'College of Education'],
    ['code' => 'CON', 'name' => 'College of Nursing'],
    ['code' => 'CAT', 'name' => 'College of Arts and Technology'],
    ['code' => 'CEA', 'name' => 'College of Engineering and Architecture'],
    ['code' => 'COM', 'name' => 'College of Management']
];

// Define all required college departments
$requiredDepartments = [
    ['name' => 'College of Computing and Information Sciences (CCIS)', 'desc' => 'CCIS department', 'code' => 'CCIS'],
    ['name' => 'College of Criminal Justice Studies (CCJS)', 'desc' => 'CCJS department', 'code' => 'CCJS'],
    ['name' => 'College of Education (COED)', 'desc' => 'COED department', 'code' => 'COED'],
    ['name' => 'College of Nursing (CON)', 'desc' => 'CON department', 'code' => 'CON'],
    ['name' => 'College of Arts and Technology (CAT)', 'desc' => 'CAT department', 'code' => 'CAT'],
    ['name' => 'College of Engineering and Architecture (CEA)', 'desc' => 'CEA department', 'code' => 'CEA'],
    ['name' => 'College of Management (COM)', 'desc' => 'COM department', 'code' => 'COM']
];

// Define required office departments used by dashboards/routing
$requiredOfficeDepartments = [
    ['name' => 'MIS Office', 'desc' => 'Management Information Systems office'],
    ['name' => 'IMCO Office', 'desc' => 'Information, Media, and Communications Office'],
    ['name' => 'Registrar Office', 'desc' => 'Academic records and enrollment'],
    ['name' => 'Internet Laboratory', 'desc' => 'Internet/computer laboratory services'],
    ['name' => 'SAS Office', 'desc' => 'Student Affairs and Services (OSAS)'],
    ['name' => 'Cashier Office', 'desc' => 'Tuition and financial payment processing'],
    ['name' => 'Accounting Office', 'desc' => 'Refunds and accounting concerns'],
    ['name' => 'Maintenance Office', 'desc' => 'Facilities and infrastructure maintenance'],
    ['name' => 'ISSC Office', 'desc' => 'Student council and student affairs support'],
    ['name' => 'Faculty Office', 'desc' => 'Faculty/instructor related concerns'],
    ['name' => 'GAD Office', 'desc' => 'Gender and Development / harassment handling'],
    ['name' => 'CODI Office', 'desc' => 'Committee on Decorum and Investigation'],
    ['name' => 'Campus Security Office', 'desc' => 'Campus security and safety'],
    ['name' => 'Student Affairs Office', 'desc' => 'Student discipline and welfare'],
    ['name' => 'Guidance Office', 'desc' => 'Counseling and guidance services'],
    ['name' => 'Help Desk', 'desc' => 'General inquiries and assistance'],
    ['name' => 'Service Inefficiency - cashier office', 'desc' => 'Queue/inefficiency complaints for cashier'],
    ['name' => 'Service Inefficiency - registrar office', 'desc' => 'Queue/inefficiency complaints for registrar'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 2rem;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.5rem;
        }
        .section {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
            border-left: 4px solid #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 1rem;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover {
            background: #5568d3;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-card h3 {
            color: #667eea;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .summary-card p {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Update Script</h1>
        <p style="color: #666; margin-bottom: 2rem;">This script ensures all colleges and departments are properly set up in the database.</p>

        <?php
        // Step 1: Update Colleges
        echo '<div class="section">';
        echo '<h2>Step 1: Updating Colleges</h2>';
        
        $collegeCount = 0;
        foreach ($requiredColleges as $college) {
            try {
                $stmt = $db->prepare("SELECT college_id FROM colleges WHERE college_code = ?");
                $stmt->execute([$college['code']]);
                $existing = $stmt->fetch();
                
                if (!$existing) {
                    $stmt = $db->prepare("INSERT INTO colleges (college_code, college_name) VALUES (?, ?)");
                    $stmt->execute([$college['code'], $college['name']]);
                    $success[] = "✓ Created college: {$college['code']} - {$college['name']}";
                    $collegeCount++;
                } else {
                    $success[] = "✓ College already exists: {$college['code']} - {$college['name']}";
                }
            } catch (Exception $e) {
                $errors[] = "✗ Error with college {$college['code']}: " . $e->getMessage();
            }
        }
        
        echo '<div class="info">Processed ' . count($requiredColleges) . ' colleges</div>';
        echo '</div>';

        // Step 2: Update Departments
        echo '<div class="section">';
        echo '<h2>Step 2: Updating College Departments</h2>';
        
        $deptCount = 0;
        foreach ($requiredDepartments as $dept) {
            try {
                // Check if department exists (by code in name)
                $stmt = $db->prepare("SELECT department_id FROM departments WHERE department_name LIKE ?");
                $stmt->execute(['%' . $dept['code'] . '%']);
                $existing = $stmt->fetch();
                
                if (!$existing) {
                    $stmt = $db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                    $stmt->execute([$dept['name'], $dept['desc']]);
                    $success[] = "✓ Created department: {$dept['name']}";
                    $deptCount++;
                } else {
                    $success[] = "✓ Department already exists: {$dept['name']}";
                }
            } catch (Exception $e) {
                $errors[] = "✗ Error with department {$dept['code']}: " . $e->getMessage();
            }
        }
        
        echo '<div class="info">Processed ' . count($requiredDepartments) . ' departments</div>';
        echo '</div>';

        // Step 2a: Ensure office departments exist
        echo '<div class="section">';
        echo '<h2>Step 2a: Updating Office Departments</h2>';
        $officeDeptCount = 0;
        foreach ($requiredOfficeDepartments as $dept) {
            try {
                $stmt = $db->prepare("SELECT department_id FROM departments WHERE department_name = ? LIMIT 1");
                $stmt->execute([$dept['name']]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    $stmt = $db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
                    $stmt->execute([$dept['name'], $dept['desc']]);
                    $success[] = "✓ Created office department: {$dept['name']}";
                    $officeDeptCount++;
                } else {
                    $success[] = "✓ Office department already exists: {$dept['name']}";
                }
            } catch (Exception $e) {
                $errors[] = "✗ Error with office department {$dept['name']}: " . $e->getMessage();
            }
        }
        echo '<div class="info">Processed ' . count($requiredOfficeDepartments) . ' office departments</div>';
        echo '</div>';

        // Step 2b: Create admin accounts per department/office
        echo '<div class="section">';
        echo '<h2>Step 2b: Creating Department/Office Admin Accounts</h2>';

        $createdAdmins = 0;
        $skippedAdmins = 0;
        try {
            $adminPasswordHash = password_hash('admin123', PASSWORD_BCRYPT);

            // Fetch all departments (includes offices + college departments)
            $deptStmt = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_id ASC");
            $allDepts = $deptStmt->fetchAll();

            $slugify = function ($name) {
                $s = strtolower((string)$name);
                $s = preg_replace('/\s+/', ' ', trim($s));
                // Prefer short usernames for college departments like "(CCIS)"
                if (preg_match('/\((CCIS|CCJS|COED|CON|CAT|CEA|COM)\)/i', $s, $m)) {
                    return 'admin_' . strtolower($m[1]);
                }
                $s = preg_replace('/[^a-z0-9]+/', '_', $s);
                $s = trim($s, '_');
                if ($s === '') {
                    $s = 'dept';
                }
                return 'admin_' . $s;
            };

            foreach ($allDepts as $dept) {
                $deptId = (int)$dept['department_id'];
                $deptName = (string)$dept['department_name'];

                // Skip if an admin already exists for this department
                $check = $db->prepare("SELECT user_id FROM users WHERE role = 'admin' AND department_id = ? LIMIT 1");
                $check->execute([$deptId]);
                if ($check->fetch()) {
                    $skippedAdmins++;
                    continue;
                }

                $username = $slugify($deptName);
                $email = $username . '@nwssu.edu.ph';
                $fullName = $deptName . ' Admin';

                // Ensure username/email uniqueness (defensive)
                $exists = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $exists->execute([$username, $email]);
                if ($exists->fetch()) {
                    // Fallback to deterministic id-based username
                    $username = 'admin_' . $deptId;
                    $email = $username . '@nwssu.edu.ph';
                    $exists->execute([$username, $email]);
                    if ($exists->fetch()) {
                        $skippedAdmins++;
                        continue;
                    }
                    $skippedAdmins++;
                    continue;
                }

                $ins = $db->prepare("INSERT INTO users (username, password, full_name, email, role, department_id) VALUES (?, ?, ?, ?, 'admin', ?)");
                $ins->execute([$username, $adminPasswordHash, $fullName, $email, $deptId]);
                $createdAdmins++;
            }

            echo '<div class="success">✓ Department/Office admin accounts created: ' . $createdAdmins . '</div>';
            echo '<div class="info">Skipped (already existed): ' . $skippedAdmins . '</div>';
            echo '<div class="info">Default password for these accounts: <strong>admin123</strong></div>';
            echo '<div class="info">Username format: <strong>admin_ccis</strong>, <strong>admin_cashier_office</strong>, etc. (fallback: admin_DEPARTMENT_ID)</div>';
        } catch (Exception $e) {
            echo '<div class="error">✗ Failed to create department admins: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div>';

        // Step 2c: Create specific admin accounts (provided credentials)
        echo '<div class="section">';
        echo '<h2>Step 2c: Creating Named Admin Accounts (Provided List)</h2>';

        $createdNamed = 0;
        $skippedNamed = 0;
        $namedErrors = 0;

        try {
            // Helper: find department_id by exact name first, then LIKE.
            $findDepartmentId = function (string $exactName, array $likeFallbacks = []) use ($db) {
                $stmt = $db->prepare("SELECT department_id FROM departments WHERE department_name = ? LIMIT 1");
                $stmt->execute([$exactName]);
                $row = $stmt->fetch();
                if ($row) return (int)$row['department_id'];

                foreach ($likeFallbacks as $like) {
                    $stmt = $db->prepare("SELECT department_id FROM departments WHERE department_name LIKE ? ORDER BY department_id ASC LIMIT 1");
                    $stmt->execute([$like]);
                    $row = $stmt->fetch();
                    if ($row) return (int)$row['department_id'];
                }
                return null;
            };

            // Colleges (department admins)
            $collegeAdmins = [
                ['email' => 'ecoccis@gmail.com', 'full_name' => 'Ccis admin', 'password' => 'Bataller123', 'dept' => 'College of Computing and Information Sciences (CCIS)', 'likes' => ['%CCIS%']],
                ['email' => 'rosemariecoed@gmail.com', 'full_name' => 'Coed admin', 'password' => 'Rosemarie123', 'dept' => 'College of Education (COED)', 'likes' => ['%COED%']],
                ['email' => 'airacea@gmail.com', 'full_name' => 'Cea Admin', 'password' => 'Lungsod123', 'dept' => 'College of Engineering and Architecture (CEA)', 'likes' => ['%CEA%']],
                ['email' => 'czeaccjs@gmail.com', 'full_name' => 'Ccjs Admin', 'password' => 'Lunas123', 'dept' => 'College of Criminal Justice Studies (CCJS)', 'likes' => ['%CCJS%']],
                ['email' => 'ivancon@gmail.com', 'full_name' => 'Con Admin', 'password' => 'Batican123', 'dept' => 'College of Nursing (CON)', 'likes' => ['%CON%']],
            ];

            // Offices (office admins)
            $officeAdmins = [
                ['email' => 'kianmis@gmail.com', 'full_name' => 'Mis Office Admin', 'password' => 'Caber123', 'dept' => 'MIS Office', 'likes' => ['%MIS%']],
                ['email' => 'glysaregistrar@gmail.com', 'full_name' => 'Registrar Office Admin', 'password' => 'Taneo123', 'dept' => 'Registrar Office', 'likes' => ['%Registrar%']],
                ['email' => 'graceinternet@gmail.com', 'full_name' => 'Internet Laboratory Office Admin', 'password' => 'Comedian123', 'dept' => 'Internet Laboratory', 'likes' => ['%Internet%Laboratory%']],
                ['email' => 'batallercashier@gmail.com', 'full_name' => 'Cashier Office Admin', 'password' => 'Bataller123', 'dept' => 'Cashier Office', 'likes' => ['%Cashier%']],
                ['email' => 'faculty@gmail.com', 'full_name' => 'Faculty Office Admin', 'password' => 'Faculty123', 'dept' => 'Faculty Office', 'likes' => ['%Faculty%']],
                ['email' => 'maintenance@gmail.com', 'full_name' => 'Maintenance Office Admin', 'password' => 'Maintenance123', 'dept' => 'Maintenance Office', 'likes' => ['%Maintenance%']],
                ['email' => 'gad@gmail.com', 'full_name' => 'Gad Office Admin', 'password' => 'Gadoffice123', 'dept' => 'GAD Office', 'likes' => ['%GAD%']],
                ['email' => 'security@gmail.com', 'full_name' => 'Campus Security Office Admin', 'password' => 'Security123', 'dept' => 'Campus Security Office', 'likes' => ['%Security%']],
                ['email' => 'sas@gmail.com', 'full_name' => 'Student Affairs and Services Office Admin', 'password' => 'Sasoffice123', 'dept' => 'SAS Office', 'likes' => ['%SAS%', '%OSAS%', '%Student Affairs and Services%']],
            ];

            $accounts = array_merge($collegeAdmins, $officeAdmins);

            foreach ($accounts as $acc) {
                $email = (string)$acc['email'];
                $username = $email; // Admin login uses username; use the email as username for these named accounts.
                $fullName = (string)$acc['full_name'];
                $passwordHash = password_hash((string)$acc['password'], PASSWORD_BCRYPT);

                $deptId = $findDepartmentId((string)$acc['dept'], $acc['likes'] ?? []);
                if (!$deptId) {
                    $namedErrors++;
                    $errors[] = "✗ Could not find department for {$email}: {$acc['dept']}";
                    continue;
                }

                // Skip if username/email already exists
                $exists = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $exists->execute([$username, $email]);
                if ($exists->fetch()) {
                    $skippedNamed++;
                    continue;
                }

                $ins = $db->prepare("INSERT INTO users (username, password, full_name, email, role, department_id) VALUES (?, ?, ?, ?, 'admin', ?)");
                $ins->execute([$username, $passwordHash, $fullName, $email, $deptId]);
                $createdNamed++;
            }

            echo '<div class="success">✓ Created named admin accounts: ' . $createdNamed . '</div>';
            echo '<div class="info">Skipped (already existed): ' . $skippedNamed . '</div>';
            if ($namedErrors > 0) {
                echo '<div class="error">✗ Errors: ' . $namedErrors . ' (see details below)</div>';
            }
            echo '<div class="info">Login for these accounts uses the <strong>email as username</strong>.</div>';
        } catch (Exception $e) {
            echo '<div class="error">✗ Failed to create named admin accounts: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div>';

        // Step 3: Display Results
        echo '<div class="section">';
        echo '<h2>Step 3: Verification</h2>';
        
        // Get all colleges
        $stmt = $db->query("SELECT college_id, college_code, college_name FROM colleges 
                           WHERE college_code IN ('CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM')
                           ORDER BY 
                               CASE college_code
                                   WHEN 'CCIS' THEN 1
                                   WHEN 'CCJS' THEN 2
                                   WHEN 'COED' THEN 3
                                   WHEN 'CON' THEN 4
                                   WHEN 'CAT' THEN 5
                                   WHEN 'CEA' THEN 6
                                   WHEN 'COM' THEN 7
                               END");
        $colleges = $stmt->fetchAll();
        
        // Get all departments
        $stmt = $db->query("SELECT department_id, department_name, description FROM departments 
                           WHERE department_name LIKE '%CCIS%' 
                              OR department_name LIKE '%CCJS%' 
                              OR department_name LIKE '%COED%' 
                              OR department_name LIKE '%CON%' 
                              OR department_name LIKE '%CAT%' 
                              OR department_name LIKE '%CEA%' 
                              OR department_name LIKE '%COM%'
                           ORDER BY 
                               CASE 
                                   WHEN department_name LIKE '%CCIS%' THEN 1
                                   WHEN department_name LIKE '%CCJS%' THEN 2
                                   WHEN department_name LIKE '%COED%' THEN 3
                                   WHEN department_name LIKE '%CON%' THEN 4
                                   WHEN department_name LIKE '%CAT%' THEN 5
                                   WHEN department_name LIKE '%CEA%' THEN 6
                                   WHEN department_name LIKE '%COM%' THEN 7
                               END");
        $departments = $stmt->fetchAll();
        
        echo '<div class="summary">';
        echo '<div class="summary-card">';
        echo '<h3>' . count($colleges) . '</h3>';
        echo '<p>Colleges</p>';
        echo '</div>';
        echo '<div class="summary-card">';
        echo '<h3>' . count($departments) . '</h3>';
        echo '<p>College Departments</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<h3 style="margin-top: 1.5rem; color: #333;">Colleges in Database:</h3>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Code</th><th>Name</th></tr>';
        foreach ($colleges as $col) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($col['college_id']) . '</td>';
            echo '<td><strong>' . htmlspecialchars($col['college_code']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($col['college_name']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<h3 style="margin-top: 1.5rem; color: #333;">College Departments in Database:</h3>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Department Name</th><th>Description</th></tr>';
        foreach ($departments as $dept) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($dept['department_id']) . '</td>';
            echo '<td><strong>' . htmlspecialchars($dept['department_name']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($dept['description']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';

        // Display success and error messages
        if (!empty($success)) {
            echo '<div class="section">';
            echo '<h2>Success Messages</h2>';
            foreach ($success as $msg) {
                echo '<div class="success">' . htmlspecialchars($msg) . '</div>';
            }
            echo '</div>';
        }
        
        if (!empty($errors)) {
            echo '<div class="section">';
            echo '<h2>Error Messages</h2>';
            foreach ($errors as $msg) {
                echo '<div class="error">' . htmlspecialchars($msg) . '</div>';
            }
            echo '</div>';
        }
        ?>

        <div class="section">
            <h2>✅ Update Complete!</h2>
            <p style="color: #666; margin-bottom: 1rem;">
                The database has been updated with all required colleges and departments. 
                You can now use the registration forms which will show all 7 college departments.
            </p>
            <a href="../register.php" class="btn">Go to Registration</a>
            <a href="../admin/dashboard.php" class="btn" style="background: #6c757d;">Go to Admin Dashboard</a>
        </div>
    </div>
</body>
</html>
