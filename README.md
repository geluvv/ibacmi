# IBACMI - IBA College Misamis Document Management System

A comprehensive student document management system for IBA College Misamis with Google Drive backup integration.

---

## 🚀 Quick Start for Deployment

**For Instructors/Administrators**: Please see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for complete setup instructions.

### TL;DR - What You Need to Do:

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Set up database:**
   ```bash
   mysql -u root -p iba_db < AdminAccount/iba_db.sql
   ```

3. **Configure Google Drive (for backup feature):**
   ```bash
   # Copy the template
   copy classes\GoogleDriveConfig.example.php classes\GoogleDriveConfig.php
   
   # Edit classes/GoogleDriveConfig.php with your Google OAuth credentials
   ```
   
   See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md#step-4-google-drive-integration-setup) for detailed Google Cloud setup.

4. **Update database connection in `db_connect.php`**

---

## ⚠️ Important Note About Credentials

For security reasons, **Google OAuth credentials are NOT included** in this repository. 

- The system is **fully functional** - all code is present
- You just need to add **your own Google credentials** for the backup feature
- Follow the [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for step-by-step instructions
- Template file provided: `classes/GoogleDriveConfig.example.php`

---

## 📋 Features

### Admin Account
- ✅ Student Registration (New Students & Transferees)
- ✅ Document Management & Verification
- ✅ Staff Account Management
- ✅ Automated Google Drive Backup
- ✅ School Year Management & Auto-Advancement
- ✅ Archival System
- ✅ Document Status Tracking
- ✅ Email Notifications

### Staff Account
- ✅ Student Document Verification
- ✅ New Student Entry
- ✅ Transferee Processing
- ✅ Document Upload
- ✅ Limited Dashboard Access

---

## 🛠️ System Requirements

- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB
- **Web Server**: Apache with mod_rewrite
- **Composer**: For PHP dependencies
- **Google Cloud Account**: For Drive backup feature (free tier available)

---

## 📁 Project Structure

```
ibacmi/
├── AdminAccount/          # Admin features and dashboard
├── StaffAccount/          # Staff features and dashboard  
├── classes/              # Core PHP classes
│   ├── BackupHandler.php
│   ├── GoogleDriveHandler.php
│   ├── GoogleDriveUploader.php
│   └── GoogleDriveConfig.php        # ⚠️ YOU CREATE THIS (see deployment guide)
├── uploads/              # Student document storage
├── backups/              # System backup files
├── db_connect.php        # Database configuration
└── composer.json         # PHP dependencies
```

---

## 🔐 Security Features

- ✅ Password hashing
- ✅ Session management
- ✅ Protected upload directories
- ✅ Credential isolation (not in git)
- ✅ SQL injection prevention
- ✅ Input validation

---

## 📖 Documentation

- **[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)** - Complete deployment instructions
- **[SECURITY_SETUP.md](SECURITY_SETUP.md)** - Security configuration guide
- **User Manual**: Available at `AdminAccount/usersmanual.html` after deployment

---

## 🚨 First-Time Setup Checklist

1. ✅ Install Composer dependencies
2. ✅ Create database and import schema
3. ✅ Configure database connection
4. ✅ Set up Google Drive credentials (follow deployment guide)
5. ✅ Set file permissions for uploads/backups folders
6. ✅ Change default admin password
7. ✅ Test backup functionality

---

## 💡 Getting Help

### If you see errors about missing credentials:
➡️ You need to set up `classes/GoogleDriveConfig.php` - see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md#step-4-google-drive-integration-setup)

### If database connection fails:
➡️ Check your credentials in `db_connect.php`

### If backups don't work:
➡️ Verify your Google Drive API setup and credentials

### For detailed troubleshooting:
➡️ Check the [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md#common-issues--solutions)

---

## 📄 License

Educational project for IBA College Misamis

---

## 👨‍💻 Development Info

- **Framework**: Core PHP (no framework)
- **Database**: MySQL
- **Frontend**: HTML, CSS, Bootstrap 5, JavaScript
- **APIs**: Google Drive API v3
- **Dependencies**: Managed via Composer (see `composer.json`)

---

## 🎓 About This Project

This is a student project developed for IBA College Misamis to manage student documents, track document completion, and automate backups to Google Drive.

**Note to Instructors**: All functionality is present in the code. The only additional step needed is adding your Google OAuth credentials for the backup feature. This takes about 5-10 minutes to set up. See the [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for exact steps.

---

**Questions?** Refer to the deployment guide or check the inline code documentation.
