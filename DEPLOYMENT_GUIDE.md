# 🚀 Deployment Guide for IBACMI System

## For Instructors/System Administrators

This guide explains how to deploy the IBACMI Document Management System.

---

## Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB database
- Apache/Nginx web server
- Composer (PHP dependency manager)
- Google Cloud account (for Google Drive integration)

---

## Step 1: Clone the Repository

```bash
git clone https://github.com/geluvv/ibacmi.git
cd ibacmi
```

---

## Step 2: Install Dependencies

```bash
composer install
```

This will install:
- Google API PHP Client
- PHPMailer
- Other required packages

---

## Step 3: Database Setup

1. Create a MySQL database:
   ```sql
   CREATE DATABASE iba_db;
   ```

2. Import the database schema:
   ```bash
   mysql -u root -p iba_db < AdminAccount/iba_db.sql
   ```

3. Configure database connection in `db_connect.php`:
   ```php
   $servername = "localhost";
   $username = "your_db_username";
   $password = "your_db_password";
   $dbname = "iba_db";
   ```

---

## Step 4: Google Drive Integration Setup

### 4.1 Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click **"Create Project"**
3. Name it (e.g., "IBACMI-Backup-System")
4. Click **Create**

### 4.2 Enable Google Drive API

1. In your project, go to **APIs & Services** → **Library**
2. Search for **"Google Drive API"**
3. Click **Enable**

### 4.3 Create OAuth 2.0 Credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **"Create Credentials"** → **"OAuth client ID"**
3. If prompted, configure the OAuth consent screen:
   - User Type: **Internal** (or External if needed)
   - App name: **IBACMI System**
   - User support email: Your email
   - Developer contact: Your email
   - Click **Save and Continue**
4. Back to Create OAuth client ID:
   - Application type: **Web application**
   - Name: **IBACMI Backup**
   - Authorized redirect URIs: Add your callback URL
     ```
     http://your-domain.com/ibacmi/oauth2callback.php
     ```
     (For local testing: `http://localhost/ibacmi/oauth2callback.php`)
5. Click **Create**
6. **Save your Client ID and Client Secret** - you'll need these!

### 4.4 Configure the Application

1. Copy the example configuration:
   ```bash
   # Windows
   copy classes\GoogleDriveConfig.example.php classes\GoogleDriveConfig.php
   
   # Linux/Mac
   cp classes/GoogleDriveConfig.example.php classes/GoogleDriveConfig.php
   ```

2. Edit `classes/GoogleDriveConfig.php`:
   ```php
   <?php
   return [
       'client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
       'client_secret' => 'YOUR_CLIENT_SECRET',
       'redirect_uri' => 'http://your-domain.com/ibacmi/oauth2callback.php'
   ];
   ```

3. **Important**: Update the redirect_uri to match your actual domain!

---

## Step 5: File Permissions

Ensure these directories are writable by the web server:

```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 backups/
chmod 755 photos/

# Windows (usually no action needed if running on XAMPP)
```

---

## Step 6: Email Configuration (Optional)

If you want to enable email notifications:

1. Edit `AdminAccount/email_config.php` and `StaffAccount/email_config.php`
2. Configure SMTP settings for your email provider

---

## Step 7: Security Checklist

✅ **Before going live:**

1. Change all default passwords in the database
2. Update `db_connect.php` with strong database credentials
3. Ensure `classes/GoogleDriveConfig.php` is NOT publicly accessible
4. Verify `.htaccess` files are working:
   - `backups/.htaccess` - Prevents direct access to backups
   - `uploads/.htaccess` - Prevents direct access to uploaded files
5. Enable HTTPS for production deployment
6. Set appropriate file permissions
7. Disable error display in production:
   ```php
   // In a config file or .htaccess
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

---

## Step 8: Test the System

### 8.1 Test Database Connection
Visit: `http://your-domain.com/ibacmi/StaffAccount/test-db.php`

### 8.2 Test Google Drive Connection
1. Login to admin account
2. Go to Backup page
3. Click "Authorize Google Drive"
4. Complete OAuth flow
5. Try creating a backup

### 8.3 Test Main Features
- ✅ Student registration (new and transferee)
- ✅ Document upload
- ✅ Staff account management
- ✅ Backup creation and Google Drive upload
- ✅ Document verification
- ✅ School year advancement

---

## Common Issues & Solutions

### Issue: "Client ID not configured"
**Solution**: You haven't set up `classes/GoogleDriveConfig.php` yet. Follow Step 4.4.

### Issue: "Redirect URI mismatch"
**Solution**: The redirect URI in your `GoogleDriveConfig.php` must EXACTLY match what you configured in Google Cloud Console.

### Issue: Database connection failed
**Solution**: Check credentials in `db_connect.php` and ensure MySQL is running.

### Issue: Can't upload files
**Solution**: Check folder permissions on `uploads/` and `backups/` directories.

### Issue: OAuth authorization fails
**Solution**: 
1. Verify Google Drive API is enabled
2. Check redirect URI matches exactly
3. Ensure your domain is added to authorized origins in Google Console

---

## Production Deployment Notes

### For Deployment on a Live Server:

1. **Update all URLs** in the codebase:
   - Replace `http://localhost/ibacmi/` with your actual domain
   - Update in `GoogleDriveConfig.php`
   - Update in database if URLs are stored

2. **Use Environment Variables** (recommended):
   - Create a `.env` file (already gitignored)
   - Store sensitive configs there
   - Use a library like `vlucas/phpdotenv` to load them

3. **Enable HTTPS**:
   - Get SSL certificate (Let's Encrypt is free)
   - Update redirect URIs to use `https://`
   - Force HTTPS in `.htaccess`

4. **Database Security**:
   - Use strong passwords
   - Create a dedicated database user with minimal privileges
   - Don't use root account

5. **Backup Strategy**:
   - Set up automated database backups
   - Configure the Google Drive backup feature
   - Test backup restoration

---

## Support

If you encounter issues during deployment:

1. Check the error logs (`AdminAccount/backup_errors.log` if backup-related)
2. Verify all prerequisites are met
3. Ensure file permissions are correct
4. Review `SECURITY_SETUP.md` for credential-related issues

---

## System Architecture

- **Admin Account**: Full system access (student management, backups, settings)
- **Staff Account**: Limited access (document verification, student entry)
- **Google Drive Integration**: Automated backup to Google Drive
- **Document Management**: Upload, verify, and track student documents
- **Auto-Sync**: Automatic document synchronization
- **Archival System**: Automatic school year advancement and archiving

---

## Default Admin Credentials

**Important**: Change these immediately after first login!

Check the database for default admin credentials or create a new admin account:

```sql
INSERT INTO admin (username, password, email) 
VALUES ('admin', MD5('admin123'), 'admin@example.com');
```

---

## File Structure Overview

```
ibacmi/
├── AdminAccount/          # Admin dashboard and features
├── StaffAccount/          # Staff dashboard and features
├── classes/              # Core classes (BackupHandler, GoogleDrive, etc.)
│   └── GoogleDriveConfig.php  # ⚠️ YOUR CREDENTIALS (not in git)
├── uploads/              # Student document uploads
├── backups/              # System backups
├── photos/               # Profile photos
├── db_connect.php        # Database configuration
└── composer.json         # PHP dependencies
```

---

## Questions?

This system was developed as a student project. For specific questions about functionality, refer to the inline code documentation or the user manual at `AdminAccount/usersmanual.html`.

---

**Last Updated**: January 2025
