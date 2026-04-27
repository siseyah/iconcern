<?php
/**
 * Script to ensure all college departments exist in the database
 * Run this once to add missing college departments (CCIS, CCJS, COED, CON, CAT, CEA, COM)
 */

require_once __DIR__ . '/../config/config.php';

$db = getDB();

// Define all required colleges and their departments
$collegeDepartments = [
    ['code' => 'CCIS', 'name' => 'College of Computing and Information Sciences (CCIS)', 'desc' => 'CCIS department'],
    ['code' => 'CCJS', 'name' => 'College of Criminal Justice Studies (CCJS)', 'desc' => 'CCJS department'],
    ['code' => 'COED', 'name' => 'College of Education (COED)', 'desc' => 'COED department'],
    ['code' => 'CON', 'name' => 'College of Nursing (CON)', 'desc' => 'CON department'],
    ['code' => 'CAT', 'name' => 'College of Arts and Technology (CAT)', 'desc' => 'CAT department'],
    ['code' => 'CEA', 'name' => 'College of Engineering and Architecture (CEA)', 'desc' => 'CEA department'],
    ['code' => 'COM', 'name' => 'College of Management (COM)', 'desc' => 'COM department'],
];

echo "Ensuring college departments exist...\n\n";

// First, ensure colleges exist
foreach ($collegeDepartments as $college) {
    $stmt = $db->prepare("INSERT IGNORE INTO colleges (college_code, college_name) VALUES (?, ?)");
    $collegeName = str_replace(' (' . $college['code'] . ')', '', $college['name']);
    $collegeName = str_replace(' (' . $college['code'] . ')', '', $collegeName);
    if (strpos($collegeName, 'College of') === false) {
        // Extract college name from department name
        $collegeName = str_replace([' (CCIS)', ' (CCJS)', ' (COED)', ' (CON)', ' (CAT)', ' (CEA)', ' (COM)'], '', $college['name']);
    }
    $stmt->execute([$college['code'], $collegeName]);
    echo "✓ College {$college['code']} ensured\n";
}

echo "\n";

// Then, ensure departments exist
foreach ($collegeDepartments as $dept) {
    $stmt = $db->prepare("SELECT department_id FROM departments WHERE department_name = ? OR department_name LIKE ?");
    $stmt->execute([$dept['name'], '%' . $dept['code'] . '%']);
    
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
        $stmt->execute([$dept['name'], $dept['desc']]);
        echo "✓ Created department: {$dept['name']}\n";
    } else {
        echo "✓ Department already exists: {$dept['name']}\n";
    }
}

echo "\nDone! All college departments are now available.\n";
