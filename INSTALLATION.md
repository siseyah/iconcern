# iconcern Installation Guide

## Complete Setup Instructions

### System Requirements
- XAMPP (Apache + MySQL)
- Python 3.7 or higher
- PHP 7.4 or higher

### Quick Installation (5 Minutes)

#### Step 1: Install Python Packages
Open Command Prompt or PowerShell and run:
```bash
pip install scikit-learn pandas numpy
```

#### Step 2: Run Automated Setup
1. Start XAMPP (Apache and MySQL)
2. Open browser: `http://localhost/iconcern/setup.php`
3. Click "Initialize Database"
4. Click "Train Model"
5. Done! Login with:
   - Username: `admin`
   - Password: `admin123`

### Manual Installation

#### Step 1: Database Setup
```bash
# Option A: Using PHP script
php database/init_database.php

# Option B: Using phpMyAdmin
# 1. Go to http://localhost/phpmyadmin
# 2. Import database/schema.sql
```

#### Step 2: Train SVM Model
```bash
cd C:\xampp\htdocs\iconcern
python ml/train_model.py
```

Expected output:
```
Model Accuracy: 97.50%
Training completed successfully!
```

#### Step 3: Verify Installation
1. Access: `http://localhost/iconcern/`
2. Login with admin credentials
3. Check admin dashboard loads correctly
4. Try submitting a test concern

### Database Configuration

If your MySQL credentials differ, edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your password here
define('DB_NAME', 'iconcern_db');
```

### Default Accounts

**Admin:**
- Username: `admin`
- Password: `admin123`
- Role: Administrator (full access)

**Student:**
- Username: `student1`
- Password: `admin123`
- Role: Student

**Staff:**
- Username: `staff1`
- Password: `admin123`
- Role: Staff

⚠️ **Change passwords after first login!**

### System Features

✅ **1000-entry Training Dataset** - Pre-trained SVM model with 97.50% accuracy
✅ **6 NWSSU Colleges** - COED, CCIS, CCJS, COM, CEA, CON
✅ **Intelligent Routing** - Auto-detects college names in concerns
✅ **Complete Departments** - All administrative offices
✅ **Student Dashboard** - Submit and track concerns
✅ **Admin Dashboard** - Manage all concerns and users
✅ **Status Tracking** - Pending → In Progress → Resolved
✅ **Notifications** - Real-time updates
✅ **Reports & Analytics** - Comprehensive statistics

### Troubleshooting

**Database Connection Error:**
- Check MySQL is running in XAMPP
- Verify credentials in `config/database.php`
- Ensure database `iconcern_db` exists

**Python Classification Not Working:**
- Verify Python: `python --version`
- Install packages: `pip install scikit-learn pandas numpy`
- Check model files exist: `ml/svm_model.pkl` and `ml/vectorizer.pkl`
- If missing, run: `python ml/train_model.py`

**File Upload Issues:**
- Ensure `uploads/` directory exists
- Check write permissions
- Verify file size limits in `config/config.php`

**Login Issues:**
- Clear browser cookies
- Check session directory permissions
- Verify admin account exists in database

### Testing the System

1. **Test Student Registration:**
   - Go to Register page
   - Create a new student account
   - Login and submit a concern

2. **Test Classification:**
   - Submit: "The TV in the CCIS classroom is broken"
   - Should classify as "Facilities" and route to CCIS

3. **Test Admin Functions:**
   - Login as admin
   - View concerns dashboard
   - Update concern status
   - View routing information

### Next Steps

1. ✅ Database initialized
2. ✅ Model trained
3. ✅ System ready
4. 🔄 Change default passwords
5. 🔄 Add more users (students/staff)
6. 🔄 Customize college/department names if needed
7. 🔄 Configure email notifications (optional)

### Support

For issues:
- Check Apache error logs: `C:\xampp\apache\logs\error.log`
- Check PHP error logs
- Verify all prerequisites are installed
- Review README.md for detailed documentation

---

**System Version:** 1.0.0
**Last Updated:** 2024
**Developed for:** NWSSU (Northwest Samar State University)

