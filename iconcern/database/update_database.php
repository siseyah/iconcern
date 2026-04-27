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
