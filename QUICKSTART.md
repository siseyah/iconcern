# iconcern - Quick Start Guide

## 🚀 5-Minute Setup

### Step 1: Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL**

### Step 2: Import Database
1. Open http://localhost/phpmyadmin
2. Click **Import** tab
3. Select `database/schema.sql`
4. Click **Go**

### Step 3: Verify Installation
1. Open http://localhost/iconcern/setup_check.php
2. Fix any issues shown

### Step 4: Train ML Model (Optional)
```bash
cd C:\xampp\htdocs\iconcern\ml
pip install scikit-learn pandas numpy
python train_model.py
```

### Step 5: Login
- Go to: http://localhost/iconcern/
- Username: `admin`
- Password: `admin123`

## 📝 Default Accounts

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |
| Student | student1 | admin123 |
| Staff | staff1 | admin123 |

## ✅ What's Working

- ✅ Student concern submission
- ✅ Automatic AI classification (SVM)
- ✅ Intelligent routing to departments
- ✅ Status tracking and updates
- ✅ Notifications system
- ✅ Admin/Staff dashboard
- ✅ Reports and analytics

## 🎯 Next Steps

1. **Change default passwords** (important!)
2. **Add more users** via registration
3. **Train model** with your own data (optional)
4. **Customize routing rules** in `includes/concern.php`

## 📚 Full Documentation

See `README.md` for complete documentation.

---

**Need Help?** Check `setup_check.php` to verify your installation.

