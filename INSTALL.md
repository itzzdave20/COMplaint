# OSWD Complaint System - Installation Guide

## Quick Start

### 1. Prerequisites
- XAMPP (Apache + MySQL + PHP 7.4+)
- Python 3.8+
- Web browser

### 2. Installation Steps

#### Step 1: Copy Files
Place the entire `Complaint` folder in `c:\xampp\htdocs\`

#### Step 2: Start XAMPP
- Open XAMPP Control Panel
- Start Apache and MySQL

#### Step 3: Create Database
- Open phpMyAdmin: http://localhost/phpmyadmin
- Click "Import" tab
- Select `database.sql` file
- Click "Go"

Alternatively, use MySQL command line:
```sql
mysql -u root < c:\xampp\htdocs\Complaint\database.sql
```

#### Step 4: Install Python Dependencies
Open Command Prompt:
```bash
cd c:\xampp\htdocs\Complaint\ml_model
pip install -r requirements.txt
```

#### Step 5: Train ML Model (First Time)
```bash
cd c:\xampp\htdocs\Complaint\ml_model
python classifier.py train
```

#### Step 6: Access the System
Open browser and go to: **http://localhost/Complaint**

### 3. Default Login
- **Username**: admin
- **Password**: admin123
- **Role**: OSWD

### 4. Test the System

#### As Student:
1. Register new account with role "Student"
2. Login
3. Submit a complaint (system will auto-classify using Random Forest)
4. View complaint status

#### As OSWD/Staff:
1. Login with admin credentials
2. View all complaints
3. Update status
4. Add comments
5. Escalate if needed

### 5. System Features

**Random Forest ML Classification:**
- Automatically categorizes complaints into 4 types:
  1. Academic Integrity Violation
  2. Unprofessional Behavior  
  3. Institutional Rules Violation
  4. Teaching Standards Failure

**User Roles:**
- Student
- Program Coordinator
- Department Chair
- Guidance Office
- OSWD (Admin)

**Core Functions:**
- Submit complaint with AI classification
- View complaint status
- Add feedback/comments
- Escalate complaints
- Update status
- Dashboard with statistics

### 6. Troubleshooting

**Database Connection Error:**
- Check MySQL is running in XAMPP
- Verify database credentials in `config/database.php`

**ML Classification Not Working:**
- Ensure Python is installed: `python --version`
- Install dependencies: `pip install scikit-learn numpy pandas`
- Train model: `python classifier.py train`

**Permission Issues:**
- Ensure write permissions on `ml_model/models/` folder

### 7. File Structure
```
Complaint/
├── config/           # Configuration files
├── classes/          # PHP classes
├── includes/         # Reusable components
├── ml_model/         # Python ML module
├── assets/css/       # Stylesheets
├── database.sql      # Database schema
└── *.php            # Application pages
```

### 8. Support
For issues, refer to README.md or contact system administrator.

**System developed for North Eastern Mindanao State University**
**Office of Student Welfare and Development (OSWD)**
