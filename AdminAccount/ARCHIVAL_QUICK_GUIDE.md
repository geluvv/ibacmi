# 🎯 ARCHIVAL SYSTEM - QUICK REFERENCE GUIDE

## ✅ SYSTEM IS NOW WORKING!

The archival system has been **FIXED** and is now fully functional. Students who graduate are automatically archived (deleted) from the system based on your timing settings.

---

## 📋 What Was Fixed

### Before:
- ❌ Students were not being archived even after graduation
- ❌ "Immediate upon graduating" setting was not working
- ❌ No automatic trigger to check for eligible students

### After:
- ✅ Automatic archival check runs every hour on dashboard load
- ✅ Students are archived immediately after school year ends (if set to "Immediate")
- ✅ All 4th year students are properly archived when eligible
- ✅ Archive logs are created for tracking

---

## 🔧 Current Configuration

**Archival Settings:**
- Timing: **Immediate upon graduating**
- Status: **Enabled**
- Last Run: Just updated (check Backup page for exact time)

**Active School Year:**
- Year: **2025-2026**
- End Date: **2025-10-20**
- Status: **ENDED** (as of today, 2025-10-21)

---

## 📊 Current Status

**Students Archived Today:**
- Jessica Bayang (5225612) - ✅ Archived
- Angela Caldoza (5225613) - ✅ Archived

**Total Archived:** 2 students, 4 documents

---

## 🚀 How to Use

### Automatic Mode (Recommended):
1. Keep auto-archival **ENABLED** in Backup & Settings
2. System automatically checks every hour when dashboard loads
3. Eligible students are archived automatically
4. No action needed from you!

### Manual Mode:
1. Go to **Backup & Settings** page
2. Scroll to **Archival Status** section
3. Check if "Pending Archival" shows any count
4. If yes, click **"Run Now"** button
5. Confirm the action
6. Students will be archived immediately

---

## ⚙️ Settings Explained

### Timing Options:
- **Immediate upon graduating** → Archives right when school year ends
- **6 months after graduating** → Archives 6 months after graduation date
- **1 year after graduating** → Archives 1 year after graduation date  
- **2 years after graduating** → Archives 2 years after graduation date

### When Are Students Eligible?
Students become eligible for archival when:
1. They are in 4th year (year_level = 4)
2. School year has ended (end_date has passed)
3. They are marked as graduated (auto-marked when SY ends)
4. Enough time has passed based on your timing setting
5. They haven't been archived yet

---

## 🔍 How to Check If It's Working

### Method 1: Via Backup Page (Easiest)
1. Open **Backup & Settings** page
2. Look at **"Archival Status"** section
3. Check these values:
   - **Pending Archival:** Should be 0 if all eligible students are archived
   - **Archived Count:** Shows total archived students
   - **Last Run:** Shows when archival last ran
   - **Next Eligible Date:** Shows when next students will be eligible

### Method 2: Via Command Line (For Testing)
```bash
cd C:\xampp\htdocs\ibacmi\AdminAccount
php test_archival_check.php
```

This will show:
- Current settings
- School year status
- 4th year students (if any)
- Archive logs

### Method 3: Check Database Directly
Look at these tables:
- `students` - Check `is_archived` column
- `archive_logs` - See all archival history
- `archival_settings` - View current configuration

---

## ⚠️ Important Notes

### What Happens During Archival:
- ❌ Students are **PERMANENTLY DELETED** from the system
- ❌ All their documents are **PERMANENTLY DELETED**
- ✅ Only archive logs remain for record-keeping
- ⚠️ **NO FILES ARE BACKED UP** - it's immediate deletion!

### Before Enabling Auto-Archival:
1. ✅ Create a full backup (Local or Google Drive)
2. ✅ Verify backup contains all student data
3. ✅ Test with a non-production database first (if possible)
4. ✅ Understand that deletion is permanent

### Best Practices:
- 📦 Always backup before archival runs
- 📅 Set appropriate timing (consider using 6 months or 1 year, not immediate)
- 📊 Monitor archive logs regularly
- 🔍 Check "Pending Archival" count weekly

---

## 🐛 Troubleshooting

### "Archival is not running"
**Check:**
1. Is auto-archival enabled? (Backup page settings)
2. Has school year ended? (Check school_years table)
3. Are there any 4th year students? (Check students table)
4. Run debug: `php test_archival_check.php`

### "Students not being archived"
**Check:**
1. Has enough time passed based on timing setting?
2. Are students marked as graduated?
3. Do students have graduation_date set?
4. Check logs: `archival_errors.log`

### "How to disable archival temporarily?"
1. Go to Backup & Settings page
2. Uncheck "Enable automatic archival"
3. Click "Save Archival Settings"

### "How to change timing?"
1. Go to Backup & Settings page
2. Select different option in "Archive Timing" dropdown
3. Click "Save Archival Settings"

---

## 📂 Key Files

### Core System:
- `archival_api.php` - Main archival logic
- `check_archival.php` - Automatic trigger
- `archival_cron.php` - For scheduled tasks
- `backup.php` - API endpoints

### UI & Frontend:
- `backup.html` - Settings interface
- `js/backup.js` - Frontend logic
- `dashboard.php` - Auto-check trigger

### Testing & Debug:
- `test_archival_check.php` - Quick status check
- `test_full_archival.php` - Interactive test
- `archival_errors.log` - Error logging

---

## ✨ Success Checklist

- [✅] Auto-archival is enabled
- [✅] Timing is set to "Immediate"
- [✅] School year has ended
- [✅] All 4th year students are archived
- [✅] Archive logs created successfully
- [✅] System checks automatically every hour
- [✅] Manual "Run Now" button works
- [✅] No errors in logs

---

## 📞 Need Help?

If something isn't working:
1. Run: `php test_archival_check.php`
2. Check: `archival_errors.log`
3. Verify: Backup page shows correct statistics
4. Review: Archive logs in database

The system is designed to fail gracefully - if archival fails, it won't break your dashboard or backup functionality.

---

**Status: ✅ FULLY OPERATIONAL**

Last Updated: October 21, 2025
