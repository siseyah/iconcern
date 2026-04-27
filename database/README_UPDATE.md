# Database Update Instructions

This directory contains scripts to update your database with all the latest changes, including all 7 colleges and their departments.

## Quick Update Options

### Option 1: Run PHP Script (Recommended)
1. Open your browser
2. Navigate to: `http://localhost/iconcern/database/update_database.php`
3. The script will automatically:
   - Create all missing colleges (CCIS, CCJS, COED, CON, CAT, CEA, COM)
   - Create all missing college departments
   - Display a summary of what was created/updated
   - Show verification tables

### Option 2: Run SQL Script via phpMyAdmin
1. Open phpMyAdmin
2. Select your `iconcern_db` database
3. Go to the "SQL" tab
4. Copy and paste the contents of `update_colleges_departments.sql`
5. Click "Go" to execute

### Option 3: Run SQL Script via Command Line
```bash
mysql -u root -p iconcern_db < database/update_colleges_departments.sql
```

## What Gets Updated

### Colleges Added/Verified:
- ✅ CCIS - College of Computing and Information Sciences
- ✅ CCJS - College of Criminal Justice Studies
- ✅ COED - College of Education
- ✅ CON - College of Nursing
- ✅ CAT - College of Arts and Technology
- ✅ CEA - College of Engineering and Architecture
- ✅ COM - College of Management

### College Departments Added/Verified:
- ✅ College of Computing and Information Sciences (CCIS)
- ✅ College of Criminal Justice Studies (CCJS)
- ✅ College of Education (COED)
- ✅ College of Nursing (CON)
- ✅ College of Arts and Technology (CAT)
- ✅ College of Engineering and Architecture (CEA)
- ✅ College of Management (COM)

## After Running the Update

1. **Verify Registration Forms**: 
   - Go to `register.php` - both student and admin registration should show all 7 college departments
   - Go to `admin/register_user.php` - should also show all 7 college departments

2. **Test Registration**:
   - Try registering a student and verify all departments are available
   - Try registering an admin and verify all departments are available

3. **Check Database**:
   - Verify in phpMyAdmin that all 7 colleges exist in the `colleges` table
   - Verify that all 7 college departments exist in the `departments` table

## Troubleshooting

### If departments don't appear:
1. Clear your browser cache
2. Run the update script again
3. Check database connection in `config/database.php`
4. Verify database name is `iconcern_db`

### If you get errors:
1. Check that MySQL is running
2. Verify database credentials
3. Ensure you have INSERT permissions
4. Check for duplicate entries (scripts use INSERT IGNORE to prevent duplicates)

## Files in This Directory

- `update_database.php` - PHP script with web interface (recommended)
- `update_colleges_departments.sql` - SQL script for manual execution
- `add_colleges.sql` - Simple script to add missing colleges
- `ensure_college_departments.php` - Alternative PHP script
- `schema.sql` - Main database schema (updated with all colleges)
