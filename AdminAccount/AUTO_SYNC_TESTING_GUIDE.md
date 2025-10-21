# Testing Guide: Auto-Sync for New Students

## Prerequisites
1. ✅ Auto-sync is enabled in backup settings
2. ✅ Google Drive is connected
3. ✅ You have access to your Google Drive account

---

## Test Case 1: Add New Regular Student

### Steps:
1. Navigate to **New Student** page (Admin or Staff account)
2. Fill in student information:
   - Student ID: TEST-2025-001
   - First Name: John
   - Last Name: Doe
   - Middle Name: (optional)
   - Course: BSIT
   - Year Level: 1
3. Upload required documents:
   - Form 138 (Card 138)
   - Good Moral Certificate
   - Birth Certificate
   - ID Photo
4. Click **Submit**

### Expected Result:
- ✅ Success message appears
- ✅ Student is created in database
- ✅ Within 5-10 seconds, check your Google Drive
- ✅ You should see a folder structure:
  ```
  IBACMI Backup [Current School Year]/
    └── Doe, John TEST-2025-001/
        ├── Form 138.pdf (or .jpg, etc.)
        ├── Good Moral Certificate.pdf
        ├── Birth Certificate.pdf
        └── ID Photo.jpg
  ```

### Debug Steps if Not Working:
1. Check browser console for errors
2. Check `AdminAccount/auto_sync_errors.log` for sync errors
3. Check `AdminAccount/debug.log` for upload errors
4. Verify in database:
   ```sql
   SELECT * FROM backup_logs ORDER BY id DESC LIMIT 1;
   SELECT * FROM backup_manifest WHERE student_id = [student_id] ORDER BY backed_up_at DESC;
   ```

---

## Test Case 2: Add New Transferee Student

### Steps:
1. Navigate to **Transferee** page
2. Fill in student information:
   - Student ID: TRANS-2025-001
   - First Name: Jane
   - Last Name: Smith
   - Course: BSCS
   - Year Level: 2
3. Upload required documents:
   - Good Moral Certificate
   - Birth Certificate
   - TOR (Transcript of Records)
   - Honorable Dismissal
   - Grade Slip
   - ID Photo
4. Click **Submit**

### Expected Result:
- ✅ Success message appears
- ✅ Student created with type "Transferee"
- ✅ Within 5-10 seconds, documents appear in Google Drive:
  ```
  IBACMI Backup [Current School Year]/
    └── Smith, Jane TRANS-2025-001/
        ├── Good Moral Certificate.pdf
        ├── Birth Certificate.pdf
        ├── TOR.pdf
        ├── Honorable Dismissal.pdf
        ├── Grade Slip.pdf
        └── ID Photo.jpg
  ```

---

## Test Case 3: Verify No Duplicates

### Steps:
1. Add a student with 2-3 documents (use Test Case 1)
2. Wait for auto-sync to complete
3. Check Google Drive - files should appear
4. Wait 2-3 minutes (let auto-sync run again via cron/schedule)
5. Check Google Drive again

### Expected Result:
- ✅ NO duplicate files are created
- ✅ Same files remain in folder
- ✅ File count doesn't increase
- ✅ In `backup_logs` table, you should see entries with:
  - `files_skipped` > 0 (unchanged files)
  - `files_uploaded` = 0 (no new uploads)

---

## Test Case 4: Update Existing Student Document

### Steps:
1. Go to **Lacking of Documents** page
2. Find the test student you created
3. Replace one document (e.g., upload new Birth Certificate)
4. Save changes

### Expected Result:
- ✅ File is updated in local database
- ✅ Auto-sync triggers immediately
- ✅ File is updated in Google Drive (same filename, new content)
- ✅ In `backup_manifest`, the record shows:
  - `backup_type` = 'modified'
  - `last_synced_at` updated to current time

---

## Verification Queries

### Check if auto-sync is enabled:
```sql
SELECT setting_name, setting_value 
FROM system_settings 
WHERE setting_name = 'auto_sync_status';
```
Expected: `setting_value` = 'enabled'

### Check Google Drive connection:
```sql
SELECT setting_name, setting_value 
FROM system_settings 
WHERE setting_name = 'google_drive_connected';
```
Expected: `setting_value` = '1'

### Check recent backup logs:
```sql
SELECT id, backup_type, storage_type, status, 
       student_count, file_count, files_uploaded, files_updated, files_skipped,
       created_at, completed_at
FROM backup_logs 
ORDER BY id DESC 
LIMIT 10;
```

### Check document sync status:
```sql
SELECT bm.id, s.student_id, s.first_name, s.last_name,
       dt.doc_name, bm.backup_type, bm.backed_up_at, bm.last_synced_at
FROM backup_manifest bm
JOIN students s ON bm.student_id = s.id
JOIN document_types dt ON bm.document_type_id = dt.id
ORDER BY bm.backed_up_at DESC
LIMIT 20;
```

---

## Troubleshooting

### Problem: Documents not appearing in Google Drive

**Check 1:** Is auto-sync enabled?
- Go to **Backup** page → Check "Auto-Sync Status"
- Should say "Enabled" with green indicator

**Check 2:** Is Google Drive connected?
- Go to **Backup** page → Check "Google Drive Status"
- Should say "Connected" with user email

**Check 3:** Check error logs
```powershell
# In PowerShell
Get-Content "c:\xampp\htdocs\ibacmi\AdminAccount\auto_sync_errors.log" -Tail 50
Get-Content "c:\xampp\htdocs\ibacmi\AdminAccount\debug.log" -Tail 50
```

**Check 4:** Manually trigger auto-sync
- Go to **Backup** page
- Click "Run Auto-Sync Now" button
- Check if files appear

### Problem: Duplicate files being created

**Cause:** This should NOT happen with the fixed code

**Check:** Look at `backup_manifest` table:
```sql
SELECT student_id, document_id, COUNT(*) as count
FROM backup_manifest
GROUP BY student_id, document_id
HAVING count > 1;
```

If duplicates exist, run:
```sql
-- Keep only the most recent record for each student+document
DELETE bm1 FROM backup_manifest bm1
INNER JOIN backup_manifest bm2 
WHERE bm1.student_id = bm2.student_id 
  AND bm1.document_id = bm2.document_id
  AND bm1.backed_up_at < bm2.backed_up_at;
```

### Problem: Auto-sync very slow

**Normal behavior:**
- Each document takes ~2-5 seconds to upload
- A student with 5 documents = ~10-25 seconds total
- Background processing doesn't block the UI

**If too slow:**
1. Check internet connection speed
2. Check file sizes (large PDFs take longer)
3. Check Google Drive API quota limits

---

## Success Indicators

✅ **After adding a new student:**
- Student appears in database immediately
- Documents appear in Google Drive within 10-30 seconds
- No errors in logs
- Backup log entry created with status 'success'

✅ **After updating a document:**
- Document is replaced in Google Drive
- `backup_manifest` shows 'modified' type
- No duplicate files created

✅ **Auto-sync running regularly:**
- Backup logs show periodic entries
- Files_skipped count increases (unchanged files)
- No errors in auto_sync_errors.log

---

## Cleanup After Testing

To remove test students:
```sql
-- Get test student IDs first
SELECT id, student_id, first_name, last_name FROM students 
WHERE student_id LIKE 'TEST-%' OR student_id LIKE 'TRANS-%';

-- Delete documents (replace XXX with actual student id)
DELETE FROM student_documents WHERE student_id = XXX;

-- Delete backup manifest entries
DELETE FROM backup_manifest WHERE student_id = XXX;

-- Delete student
DELETE FROM students WHERE id = XXX;
```

To remove test folders from Google Drive:
- Manually delete the student folders from "IBACMI Backup [Year]" folder
