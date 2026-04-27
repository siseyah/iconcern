# iconcern - Intelligent College-Based Student Concern Classification and Routing System

A complete web-based system for NWSSU that uses SVM (Support Vector Machine) machine learning to automatically classify and route student concerns to the appropriate college or department.

## Features

- **Student Portal**: Submit concerns with automatic AI classification
- **Admin/Staff Dashboard**: Manage concerns, update status, view routing
- **SVM Classification**: Automatic categorization of concerns (Academic, Financial, Guidance, Facilities, IT, Library)
- **Intelligent Routing**: Auto-route concerns to appropriate departments
- **Status Tracking**: Track concern status (Pending → In Progress → Resolved)
- **Notifications**: Real-time notifications for status updates
- **Reports & Analytics**: View statistics and insights

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (via XAMPP)
- **Frontend**: HTML5, CSS3, JavaScript
- **Machine Learning**: Python 3.x with scikit-learn (SVM)
- **Server**: XAMPP (Apache + MySQL)

## Installation & Setup

### Quick Setup (Recommended)

**Option 1: Automated Setup (Easiest)**

1. Ensure XAMPP is running (Apache and MySQL)
2. Open your browser and navigate to:
   ```
   http://localhost/iconcern/setup.php
   ```
3. Follow the on-screen instructions to:
   - Initialize the database
   - Train the SVM model with 1000 entries
4. Login with admin credentials (username: `admin`, password: `admin123`)

**Option 2: Manual Setup**

### Prerequisites

1. **XAMPP** installed and running
   - Download from: https://www.apachefriends.org/
   - Ensure Apache and MySQL are running

2. **Python 3.x** with required packages
   ```bash
   pip install scikit-learn pandas numpy
   ```

### Step 1: Clone/Copy Project

1. Copy the `iconcern` folder to your XAMPP htdocs directory:
   ```
   C:\xampp\htdocs\iconcern\
   ```

### Step 2: Database Setup

**Method A: Using PHP Script (Recommended)**
```bash
php database/init_database.php
```

**Method B: Using phpMyAdmin**
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Import the database schema:
   - Click "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

The database `iconcern_db` will be created with all required tables, including:
- All 6 NWSSU colleges (COED, CCIS, CCJS, COM, CEA, CON)
- Complete department list
- Admin account (username: `admin`, password: `admin123`)

### Step 3: Configure Database Connection

Edit `config/database.php` if your MySQL credentials are different:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password
define('DB_NAME', 'iconcern_db');
```

### Step 4: Train the SVM Model

The system includes a comprehensive **1000-entry training dataset** for high accuracy classification.

1. Open terminal/command prompt
2. Navigate to the project directory:
   ```bash
   cd C:\xampp\htdocs\iconcern
   ```
3. Run the training script:
   ```bash
   python ml/train_model.py
   ```

This will:
- Load 1000 training entries from `ml/training_data.csv`
- Train the SVM model with 97%+ accuracy
- Create `svm_model.pkl` and `vectorizer.pkl` files in the `ml` directory

**Expected Output:**
```
Model Accuracy: 97.50%
Training completed successfully!
```

### Step 6: Access the Application

1. Start XAMPP (Apache and MySQL)
2. Open your browser and navigate to:
   ```
   http://localhost/iconcern/
   ```
3. You will be redirected to the login page

## Default Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `admin123`

### Student Account
- **Username**: `student1`
- **Password**: `admin123`

### Staff Account
- **Username**: `staff1`
- **Password**: `admin123`

**⚠️ Important**: Change these default passwords in production!

## Project Structure

```
iconcern/
├── admin/                  # Admin panel pages
│   ├── concerns.php
│   ├── routing.php
│   ├── users.php
│   └── reports.php
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── config/                 # Configuration files
│   ├── config.php
│   └── database.php
├── database/               # Database schema
│   └── schema.sql
├── includes/               # PHP includes
│   ├── auth.php
│   ├── concern.php
│   ├── college.php
│   ├── header.php
│   └── footer.php
├── ml/                     # Machine Learning scripts
│   ├── classify.py
│   └── train_model.py
├── uploads/                # File uploads directory
├── index.php              # Main dashboard
├── login.php              # Login page
├── register.php           # Registration page
├── submit_concern.php     # Submit concern form
├── my_concerns.php       # Student concerns list
├── concern.php           # Concern details page
├── notifications.php     # Notifications page
├── profile.php           # User profile
├── logout.php            # Logout handler
└── README.md             # This file
```

## Database Schema

The system uses 9 main tables:

1. **users** - User accounts (students, staff, admins)
2. **colleges** - NWSSU colleges (COED, CCIS, CCJS, COM, CEA, CON)
3. **departments** - Administrative departments (Guidance, Cashiers, Registrar, Library, OSAS, IT Support, Maintenance, and all colleges as departments)
4. **concerns** - Student-submitted concerns
5. **classifications** - SVM classification results with confidence scores
6. **routing** - Concern routing information (college and department)
7. **status_history** - Status change logs with notes
8. **notifications** - User notifications
9. **training_data** - ML training data (1000 entries included)

## How It Works

### 1. Student Submits Concern
- Student logs in and fills out the concern form
- Optionally uploads an attachment (image/document)

### 2. Automatic Classification
- System calls Python SVM script (`ml/classify.py`)
- Concern text is preprocessed and classified
- Result is saved in `classifications` table

### 3. Intelligent Classification & Routing
- **SVM Classification**: Concern is automatically classified into categories:
  - Academic, Financial, Guidance, Facilities, IT, Library, General
- **College Detection**: System detects college names in concern text (e.g., "CCIS", "COED", "CEA")
- **Smart Routing**: Based on classification AND detected college:
  - **Academic** → Registrar Office (or specific college if detected)
  - **Financial** → Cashiers Office
  - **Guidance** → Guidance Office
  - **Facilities** → Maintenance Office (or specific college department if college detected)
  - **IT** → IT Support
  - **Library** → Library Office
  
**Example**: "The TV in the CCIS classroom is broken" → Classified as **Facilities** → Routed to **CCIS Department**

### 4. Staff/Admin Management
- Staff can view routed concerns
- Update status (Pending → In Progress → Resolved)
- Add notes and track history

### 5. Notifications
- Students receive notifications when status changes
- Real-time updates via notification system

## SVM Classification

The system uses a Support Vector Machine (SVM) classifier trained on **1000 labeled entries** with:
- **Text preprocessing**: Lowercasing, special character removal, whitespace normalization
- **TF-IDF Vectorization**: 2000 features with n-grams (1-3) for better accuracy
- **Linear SVM**: Fast and accurate for text classification
- **Model Accuracy**: 97.50% on test set
- **Fallback classification**: Keyword-based if model not available
- **College Detection**: Automatically detects college names (COED, CCIS, CCJS, COM, CEA, CON) in concern text

### Categories
- **Academic** (200 training samples) - Course, grades, exams, enrollment
- **Financial** (150 training samples) - Tuition, fees, scholarships, payments
- **Guidance** (150 training samples) - Counseling, mental health, personal issues
- **Facilities** (200 training samples) - Buildings, maintenance, equipment
- **IT** (150 training samples) - Computers, network, software, technical issues
- **Library** (100 training samples) - Books, research, study rooms
- **General** (50 training samples) - General inquiries and other concerns

## Customization

### Adding New Colleges
Edit `database/schema.sql` and add to colleges table, or use phpMyAdmin to insert new records.

### Adding New Departments
Insert into `departments` table via phpMyAdmin or SQL.

### Modifying Routing Rules
Edit `includes/concern.php` in the `autoRoute()` method to change routing logic.

### Training with Custom Data
1. Update `ml/training_data.csv` with your labeled data (format: concern_text,label)
2. Or add data to the `training_data` table in the database
3. Run training script: `python ml/train_model.py`
4. Model will be saved automatically

**Note**: The system includes 1000 pre-labeled training entries. You can add more to improve accuracy.

## Troubleshooting

### Database Connection Error
- Check XAMPP MySQL is running
- Verify credentials in `config/database.php`
- Ensure database `iconcern_db` exists

### Python Classification Not Working
- Verify Python is installed: `python --version`
- Install required packages: `pip install scikit-learn pandas numpy`
- Check Python path in `includes/concern.php` (line with `python` command)
- On Windows, you may need to use `python` instead of `python3`

### File Upload Issues
- Ensure `uploads/` directory exists and is writable
- Check file size limits in `config/config.php`
- Verify allowed file extensions

### Session Issues
- Ensure PHP sessions are enabled
- Check session directory permissions
- Clear browser cookies if login persists

## Security Notes

⚠️ **For Production Use:**
1. Change all default passwords
2. Use strong password hashing (already using bcrypt)
3. Enable HTTPS/SSL
4. Sanitize all user inputs (already implemented)
5. Implement CSRF protection
6. Set proper file upload permissions
7. Hide sensitive error messages
8. Use environment variables for database credentials

## Support

For issues or questions:
- Check the database connection settings
- Verify all dependencies are installed
- Review Apache error logs: `C:\xampp\apache\logs\error.log`
- Check PHP error logs

## License

This project is developed for NWSSU (Northwest Samar State University).

## Version

Current Version: 1.0.0

---

**Developed for NWSSU** | Intelligent Concern Classification System

