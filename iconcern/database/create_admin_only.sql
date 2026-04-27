-- ============================================
-- Quick Admin Account Creation/Reset
-- Run this if you only need to create/reset the admin account
-- ============================================

USE iconcern_db;

-- Delete existing admin if exists (optional)
-- DELETE FROM users WHERE username = 'admin' OR email = 'admin@nwssu.edu.ph';

-- Create/Update Admin Account
-- Username: admin
-- Password: admin123
-- Email: admin@nwssu.edu.ph
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@nwssu.edu.ph', 'admin')
ON DUPLICATE KEY UPDATE 
    password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    role = 'admin',
    full_name = 'System Administrator',
    email = 'admin@nwssu.edu.ph';

-- Verify admin was created
SELECT user_id, username, email, role, created_at FROM users WHERE username = 'admin';

