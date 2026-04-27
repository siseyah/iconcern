-- ============================================
-- Update Colleges and Departments
-- Run this script to ensure all 7 colleges and their departments exist
-- ============================================

USE iconcern_db;

-- ============================================
-- Step 1: Insert all colleges if they don't exist
-- ============================================
INSERT IGNORE INTO colleges (college_code, college_name) VALUES
('CCIS', 'College of Computing and Information Sciences'),
('CCJS', 'College of Criminal Justice Studies'),
('COED', 'College of Education'),
('CON', 'College of Nursing'),
('CAT', 'College of Arts and Technology'),
('CEA', 'College of Engineering and Architecture'),
('COM', 'College of Management');

-- ============================================
-- Step 2: Insert all college departments if they don't exist
-- ============================================
INSERT IGNORE INTO departments (department_name, description) VALUES
('College of Computing and Information Sciences (CCIS)', 'CCIS department'),
('College of Criminal Justice Studies (CCJS)', 'CCJS department'),
('College of Education (COED)', 'COED department'),
('College of Nursing (CON)', 'CON department'),
('College of Arts and Technology (CAT)', 'CAT department'),
('College of Engineering and Architecture (CEA)', 'CEA department'),
('College of Management (COM)', 'COM department');

-- ============================================
-- Step 3: Verify all colleges exist
-- ============================================
SELECT 'Colleges in database:' AS 'Status';
SELECT college_id, college_code, college_name FROM colleges 
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
    END;

-- ============================================
-- Step 4: Verify all college departments exist
-- ============================================
SELECT 'College Departments in database:' AS 'Status';
SELECT department_id, department_name, description FROM departments 
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
    END;

-- ============================================
-- Step 5: Summary
-- ============================================
SELECT 
    (SELECT COUNT(*) FROM colleges WHERE college_code IN ('CCIS', 'CCJS', 'COED', 'CON', 'CAT', 'CEA', 'COM')) AS 'Total Colleges',
    (SELECT COUNT(*) FROM departments 
     WHERE department_name LIKE '%CCIS%' 
        OR department_name LIKE '%CCJS%' 
        OR department_name LIKE '%COED%' 
        OR department_name LIKE '%CON%' 
        OR department_name LIKE '%CAT%' 
        OR department_name LIKE '%CEA%' 
        OR department_name LIKE '%COM%') AS 'Total College Departments';
