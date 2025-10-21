# 🔐 Security Setup for Google Drive Integration

## ⚠️ IMPORTANT: Protecting Your Credentials

This project uses Google OAuth for Google Drive integration. **Never commit your actual credentials to Git!**

## Setup Instructions

### 1. Configure Google OAuth Credentials

1. Copy the example configuration file:
   ```bash
   copy classes\GoogleDriveConfig.example.php classes\GoogleDriveConfig.php
   ```

2. Get your Google OAuth credentials:
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select an existing one
   - Enable the **Google Drive API**
   - Create **OAuth 2.0 credentials**
   - Copy your **Client ID** and **Client Secret**

3. Edit `classes/GoogleDriveConfig.php` and replace the placeholders:
   ```php
   return [
       'client_id' => 'YOUR_ACTUAL_CLIENT_ID.apps.googleusercontent.com',
       'client_secret' => 'YOUR_ACTUAL_CLIENT_SECRET',
       'redirect_uri' => 'http://localhost/ibacmi/oauth2callback.php'
   ];
   ```

### 2. Files Protected by .gitignore

The following files are automatically excluded from Git:
- `classes/GoogleDriveConfig.php` - Your OAuth credentials
- `config/google_drive_token.json` - Access tokens
- `AdminAccount/backup_errors.log` - Log files with sensitive data
- All `.log` files
- `.env` files

### 3. What's Safe to Commit

✅ **Safe to commit:**
- `classes/GoogleDriveConfig.example.php` - Template with placeholders
- All code files that reference the config
- This README

❌ **NEVER commit:**
- `classes/GoogleDriveConfig.php` - Contains real credentials
- Any file with actual OAuth tokens or secrets
- Log files with API responses

## Revoke Compromised Credentials

If you accidentally committed credentials to Git:

1. **Immediately revoke them** in [Google Cloud Console](https://console.cloud.google.com/)
2. Generate new credentials
3. Update your local `GoogleDriveConfig.php`
4. Follow the Git history cleanup guide below

## Cleaning Git History (If Needed)

If credentials were already committed, you need to remove them from Git history:

```powershell
# Option 1: Use git filter-repo (recommended)
# Install: pip install git-filter-repo
git filter-repo --path AdminAccount/backup_errors.log --invert-paths
git filter-repo --path classes/GoogleDriveConfig.php --invert-paths

# Option 2: Use BFG Repo-Cleaner
# Download from: https://rtyley.github.io/bfg-repo-cleaner/
java -jar bfg.jar --delete-files backup_errors.log
java -jar bfg.jar --replace-text passwords.txt
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# After cleaning, force push (⚠️ WARNING: This rewrites history!)
git push origin --force --all
```

## Best Practices

1. 🔒 **Never hardcode credentials** in source files
2. 📝 **Use configuration files** that are gitignored
3. 🔄 **Rotate credentials regularly**
4. 👁️ **Review commits** before pushing to ensure no secrets are included
5. 🚨 **Set up secret scanning** on your repository

## Need Help?

If you see push protection errors from GitHub, it means secrets were detected. Follow this guide to clean them up before pushing again.
