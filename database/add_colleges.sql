-- Add missing colleges and departments
-- Run this script to add all college departments (CCIS, CCJS, COED, CON, CAT, CEA, COM)

USE iconcern_db;

-- Add all colleges if they don't exist
INSERT IGNORE INTO colleges (college_code, college_name) VALUES
('CCIS', 'College of Computing and Information Sciences'),
('CCJS', 'College of Criminal Justice Studies'),
('COED', 'College of Education'),
('CON', 'College of Nursing'),
('CAT', 'College of Arts and Technology'),
('CEA', 'College of Engineering and Architecture'),
('COM', 'College of Management');

-- Add all college departments if they don't exist
INSERT IGNORE INTO departments (department_name, description) VALUES
('College of Computing and Information Sciences (CCIS)', 'CCIS department'),
('College of Criminal Justice Studies (CCJS)', 'CCJS department'),
('College of Education (COED)', 'COED department'),
('College of Nursing (CON)', 'CON department'),
('College of Arts and Technology (CAT)', 'CAT department'),
('College of Engineering and Architecture (CEA)', 'CEA department'),
('College of Management (COM)', 'COM department');

-- Verify all college departments exist
-- CCIS, CCJS, COED, CON, CAT, CEA, COM should all be present
