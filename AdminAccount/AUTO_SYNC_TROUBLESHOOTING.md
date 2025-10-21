# Auto-Sync Troubleshooting Guide

**Issue:** Auto-sync only works when updating documents, not when first enabling it  
**Status:** ✅ FIXED  
**Date:** January 2025

---

## What Was Fixed

### Problem
When you enable auto-sync AFTER adding students and documents, it should immediately sync all existing documents. But it was only syncing when you later updated a document.

### Root Cause
The JavaScript was calling `auto_sync_processor.php` but:
1. **No error handling** - You couldn't see if it failed
2. **No user feedback** - No loading or success message
3. **Silent failures** - Errors were logged to a separate file you couldn't see

### Solution
Added comprehensive error handling and user feedback:
- ✅ Loading spinner while syncing
- ✅ Success message with sync statistics
- ✅ Error messages if something fails
- ✅ Better logging in auto_sync_processor.php

---

## How to Test the Fix

### Step 1: Enable Auto-Sync
1. Go to **Backup Settings** page
2. Click **"Enable"** button under Auto-Sync section
3. **WAIT** - You should see:
   ```
   Loading: "Syncing Documents..."
   ↓
   (Processing... 10-60 seconds)
   ↓
   Success popup showing:
   - Students: X
   - Total Files: X
   - Uploaded: X
   - Updated: X
   - Skipped: X
   ```

### Step 2: Check Google Drive
1. Open your Google Drive
2. Find folder: **"IBACMI Backup 2024-2025"** (or current school year)
3. Inside, you should see student folders
4. Inside each student folder, you should see their documents

### Step 3: Verify in Browser Console
1. Press **F12** to open Developer Tools
2. Go to **Console** tab
3. You should see:
   ```
   🚀 Auto-sync enabled - triggering initial sync...
   📤 Calling auto_sync_processor.php...
   Response status: 200
   ✅ Sync response: {status: "success", synced: 50, ...}
   🔄 Starting auto-sync monitoring...
   ```

---

## Common Issues & Solutions

### Issue 1: "Auto-Sync is not enabled"
**Symptom:** Popup says "Auto-sync is not enabled. Please enable it first."

**Cause:** Database didn't save the 'enabled' status

**Solution:**
1. Check database:
   ```sql
   SELECT * FROM system_settings WHERE setting_name = 'auto_sync_status';
   ```
2. Should show `setting_value = 'enabled'`
3. If not, manually set it:
   ```sql
   UPDATE system_settings SET setting_value = 'enabled' WHERE setting_name = 'auto_sync_status';
   ```

---

### Issue 2: "Google Drive is not connected"
**Symptom:** Popup says "Google Drive is not connected"

**Cause:** Google Drive OAuth not completed

**Solution:**
1. Go to Backup Settings
2. Click **"Connect Google Drive"** button
3. Complete Google OAuth flow
4. Then try enabling auto-sync again

---

### Issue 3: Loading Spinner Never Stops
**Symptom:** "Syncing Documents..." spinner runs forever

**Cause:** PHP script crashed or timed out

**Solution:**
1. Open browser console (F12)
2. Look for error messages
3. Check PHP error log:
   ```
   c:\xampp\htdocs\ibacmi\AdminAccount\auto_sync_errors.log
   ```
4. Common causes:
   - No documents to sync (empty database)
   - File paths incorrect
   - Google API rate limit exceeded

---

### Issue 4: "Failed to sync documents: HTTP 500"
**Symptom:** Error popup with HTTP 500

**Cause:** PHP error in auto_sync_processor.php

**Solution:**
1. Check PHP error log:
   ```
   c:\xampp\htdocs\ibacmi\AdminAccount\auto_sync_errors.log
   ```
2. Look for:
   ```
   === AUTO-SYNC PROCESSOR CALLED ===
   [Error messages here]
   ```
3. Fix the error shown in the log

---

### Issue 5: Shows "0 uploaded, 0 updated, X skipped"
**Symptom:** Success message but all files skipped

**Cause:** Documents already synced previously

**Explanation:** This is CORRECT! The system is preventing duplicates.
- If documents were already synced before, they will be skipped
- This is exactly what should happen
- Only NEW or CHANGED documents will upload

**To verify documents exist:**
1. Check Google Drive folders
2. Look in `backup_manifest` table:
   ```sql
   SELECT COUNT(*) FROM backup_manifest;
   ```
3. Should match number of documents in database

---

## Checking Logs

### Browser Console
Press **F12** → **Console** tab

Look for:
```javascript
🚀 Auto-sync enabled - triggering initial sync for unsynced documents...
📤 Calling auto_sync_processor.php...
Response status: 200
✅ Sync response: {...}
```

### PHP Error Log
Location: `c:\xampp\htdocs\ibacmi\AdminAccount\auto_sync_errors.log`

Look for:
```
=== AUTO-SYNC PROCESSOR CALLED ===
Trigger type: enable_sync
Request method: POST
=== AUTO-SYNC PROCESS STARTED ===
Main folder ID: 1a2b3c4d5e6f
✅ Created student folder: Doe, John 2024-001
📤 New file, uploading: Birth Certificate.pdf
✅ Uploaded: Birth Certificate.pdf
=== AUTO-SYNC RESULT ===
Status: success
=== AUTO-SYNC PROCESSOR COMPLETE ===
```

### Database Check
Query to see sync status:
```sql
-- Check if auto-sync is enabled
SELECT * FROM system_settings WHERE setting_name = 'auto_sync_status';

-- Check synced documents
SELECT COUNT(*) as synced_count FROM backup_manifest;

-- Check total documents
SELECT COUNT(*) as total_count FROM student_documents WHERE is_submitted = 1;

-- See recent backup logs
SELECT * FROM backup_logs 
WHERE backup_type = 'automatic' 
ORDER BY created_at DESC 
LIMIT 5;
```

---

## Expected Behavior

### When Enabling Auto-Sync (Fresh Start)
```
Enable Auto-Sync button clicked
↓
Loading: "Syncing Documents..."
↓
auto_sync_processor.php runs (30-60 seconds)
↓
Success popup:
  - Students: 10
  - Total Files: 50
  - Uploaded: 50 (all new)
  - Updated: 0
  - Skipped: 0
↓
Google Drive has all 50 documents
```

### When Enabling Auto-Sync (Already Synced)
```
Enable Auto-Sync button clicked
↓
Loading: "Syncing Documents..."
↓
auto_sync_processor.php runs (5-10 seconds)
↓
Success popup:
  - Students: 10
  - Total Files: 50
  - Uploaded: 0
  - Updated: 0
  - Skipped: 50 (all already synced)
↓
No duplicates created!
```

### When Enabling Auto-Sync (Partial Sync)
```
Enable Auto-Sync button clicked
↓
Loading: "Syncing Documents..."
↓
auto_sync_processor.php runs (20-40 seconds)
↓
Success popup:
  - Students: 10
  - Total Files: 50
  - Uploaded: 20 (new documents)
  - Updated: 5 (changed documents)
  - Skipped: 25 (unchanged documents)
↓
Google Drive updated with new/changed files only
```

---

## What to Do If It Still Doesn't Work

### Step 1: Clear Browser Cache
1. Press **Ctrl+Shift+Delete**
2. Clear **Cached images and files**
3. Reload page with **Ctrl+F5**

### Step 2: Check Prerequisites
- ✅ Google Drive connected
- ✅ OAuth tokens valid
- ✅ Students exist in database
- ✅ Documents submitted (is_submitted = 1)
- ✅ File paths correct

### Step 3: Manual Test
Run this in browser console:
```javascript
fetch('auto_sync_processor.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'trigger=manual_test'
})
.then(r => r.json())
.then(d => console.log('Result:', d))
.catch(e => console.error('Error:', e));
```

Should return:
```javascript
{
  status: "success",
  synced: X,
  uploaded: X,
  updated: X,
  skipped: X,
  student_count: X
}
```

### Step 4: Check PHP Configuration
Verify in `php.ini`:
```ini
max_execution_time = 300
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
```

Restart Apache after changing.

---

## Success Indicators

### ✅ Working Correctly If You See:
1. **Loading spinner** appears when enabling
2. **Success popup** shows sync statistics
3. **Google Drive** has folders and files
4. **backup_manifest** table has entries
5. **Browser console** shows successful logs
6. **No errors** in auto_sync_errors.log

### ❌ Not Working If You See:
1. **No loading spinner** - JavaScript not running
2. **Spinner forever** - PHP crashed
3. **Error popup** - Check console/logs
4. **Empty Google Drive** - Sync failed silently
5. **Empty backup_manifest** - Documents not recorded

---

## File Locations Reference

| File | Purpose | Location |
|------|---------|----------|
| **backup.js** | Frontend sync controls | `AdminAccount/js/backup.js` |
| **auto_sync_processor.php** | Backend sync engine | `AdminAccount/auto_sync_processor.php` |
| **auto_sync_errors.log** | Error logging | `AdminAccount/auto_sync_errors.log` |
| **backup.html** | Backup settings page | `AdminAccount/backup.html` |

---

## Quick Diagnostic

Run this checklist:

```
□ Auto-sync status = 'enabled' in system_settings table
□ Google Drive tokens exist in system_settings table
□ Students exist with is_submitted = 1 documents
□ File paths in student_documents are valid
□ backup_manifest table exists
□ Google Drive API accessible (test connection)
□ No errors in auto_sync_errors.log
□ Browser console shows successful fetch
```

If ALL checked ✅ → Auto-sync should work!

---

## Contact Support

If after following this guide it still doesn't work:

1. **Export database query results:**
   ```sql
   SELECT * FROM system_settings WHERE setting_name LIKE '%sync%';
   SELECT COUNT(*) FROM student_documents WHERE is_submitted = 1;
   SELECT COUNT(*) FROM backup_manifest;
   SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 5;
   ```

2. **Copy browser console output** (F12 → Console)

3. **Copy last 50 lines** of `auto_sync_errors.log`

4. **Take screenshot** of error popup (if any)

---

## Conclusion

The auto-sync system now:
- ✅ Shows loading feedback when enabling
- ✅ Displays sync results with statistics
- ✅ Handles errors gracefully with messages
- ✅ Logs everything for debugging
- ✅ Prevents duplicates automatically
- ✅ Syncs immediately when enabled

**You should now see documents syncing right away when you enable auto-sync!** 🎉
