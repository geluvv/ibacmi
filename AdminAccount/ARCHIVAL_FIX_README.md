# Archival System Fix - Documentation

## 🔧 What Was Fixed

### 1. **Enhanced Student Detection Logic**
   - **Before**: System only checked if `is_graduated = 1` and `is_archived = 0`
   - **After**: System now checks BOTH:
     - Students with `is_graduated = 1` AND `is_archived = 0`
     - Students with `status = 'archived'` (for flexibility)
   - Added detailed logging to track why students are or aren't detected

### 2. **Fixed "Immediate Upon Graduation" Logic**
   - **Before**: Only checked if graduation_date equals today
   - **After**: Checks if graduation_date is today OR in the past (graduation date has passed)
   - This means students who graduated yesterday, last week, etc., will now be detected

### 3. **Added Table Auto-Creation**
   - System now automatically creates `archival_settings` table if it doesn't exist
   - System now automatically creates `archive_logs` table if it doesn't exist
   - Prevents database errors when tables are missing

### 4. **Improved Error Logging**
   - Added comprehensive logging throughout the archival process
   - Logs now show:
     - Which timing option is being used
     - What date threshold is being checked
     - Each student being evaluated
     - Why students are/aren't eligible
     - Detailed deletion progress

### 5. **Better Status Handling**
   - Before deletion, students are now marked with `is_archived = 1` and `status = 'archived'`
   - This creates an audit trail before actual deletion
   - Better tracking in archive_logs table

### 6. **Added Diagnostic Tools**
   - Created `test_archival.php` - A comprehensive diagnostic page
   - Added new API endpoint: `get_eligible_students` for debugging
   - Shows all students with graduation data and their eligibility status

---

## 📋 How the Archival System Works

### Timing Options Explained

1. **Immediate Upon Graduation** (`immediate`)
   - Archives students whose `graduation_date` is **today or earlier**
   - Example: If today is 2025-10-20, it will archive students with graduation_date of 2025-10-20, 2025-10-19, 2025-10-18, etc.

2. **6 Months After Graduation** (`6_months`)
   - Archives students whose `graduation_date` is **6 months ago or earlier**
   - Example: If today is 2025-10-20, it will archive students with graduation_date of 2025-04-20 or earlier

3. **1 Year After Graduation** (`1_year`)
   - Archives students whose `graduation_date` is **1 year ago or earlier**
   - Example: If today is 2025-10-20, it will archive students with graduation_date of 2024-10-20 or earlier

4. **2 Years After Graduation** (`2_years`)
   - Archives students whose `graduation_date` is **2 years ago or earlier**
   - Example: If today is 2025-10-20, it will archive students with graduation_date of 2023-10-20 or earlier

### Requirements for a Student to be Archived

A student will ONLY be archived if ALL of these conditions are met:

1. ✅ `graduation_date` is set (NOT NULL)
2. ✅ `graduation_date` is less than or equal to the threshold date (has passed)
3. ✅ `is_graduated = 1` OR `status = 'archived'`
4. ✅ `is_archived = 0` (not already archived)

---

## 🚀 How to Use the System

### Step 1: Mark Students as Graduated

For students to be eligible for archival, they MUST have `is_graduated = 1` set.

**Option A: Use the Test Page (Recommended)**
1. Navigate to: `http://localhost/ibacmi/AdminAccount/test_archival.php`
2. Scroll to section "5. Students Who Should Be Marked as Graduated"
3. Click the button "Mark Eligible Students as Graduated"
4. This will automatically set `is_graduated = 1` for all students with past graduation dates

**Option B: Use SQL Directly**
Run this SQL command in phpMyAdmin:
```sql
UPDATE students 
SET is_graduated = 1 
WHERE graduation_date IS NOT NULL 
AND graduation_date <= CURDATE() 
AND is_graduated = 0;
```

### Step 2: Configure Archival Settings

1. Go to your backup management page (backup.html)
2. Find the "Archival Settings" section
3. Select your desired timing option:
   - **Immediate** - for testing or if you want students removed right after graduation
   - **6 months/1 year/2 years** - for keeping records for a period after graduation
4. Make sure "Auto-Archival Enabled" is checked
5. Click "Save Settings"

### Step 3: Test the Archival System

**Use the Diagnostic Page:**
1. Navigate to: `http://localhost/ibacmi/AdminAccount/test_archival.php`
2. Review all 7 diagnostic sections:
   - Current settings
   - All students with graduation data
   - Threshold dates for each timing option
   - **Eligible students found** (most important!)
   - Students who need to be marked as graduated
   - Recent archive logs
3. If section 4 shows eligible students, the system is ready!

### Step 4: Run Archival

**Option A: Manual Trigger**
1. On the backup page, click "Run Auto-Archival Now"
2. System will process all eligible students
3. Check the results in the response

**Option B: Automatic (via Cron)**
The system includes `archival_cron.php` for automatic execution.

**Windows Task Scheduler Setup:**
- Program: `C:\xampp\php\php.exe`
- Arguments: `-f "C:\xampp\htdocs\ibacmi\AdminAccount\archival_cron.php"`
- Schedule: Daily at preferred time (e.g., 2:00 AM)

**Linux/Mac Cron Setup:**
```bash
0 2 * * * /usr/bin/php /path/to/ibacmi/AdminAccount/archival_cron.php
```

---

## 🔍 Troubleshooting Guide

### Problem: No Students Detected for Archival

**Check these items in order:**

1. **Do students have graduation dates set?**
   - Run test_archival.php, check section 2
   - Students MUST have `graduation_date` filled in

2. **Are students marked as graduated?**
   - Run test_archival.php, check section 5
   - Students MUST have `is_graduated = 1`
   - Use the "Mark Eligible Students as Graduated" button if needed

3. **Do graduation dates meet the threshold?**
   - Run test_archival.php, check section 3
   - Compare student graduation dates against the threshold for your selected timing option
   - Example: If using "1 year" option and today is 2025-10-20, students must have graduation_date ≤ 2024-10-20

4. **Are students already archived?**
   - Check if `is_archived = 1` for these students
   - If yes, they won't be archived again

5. **Is auto-archival enabled?**
   - Check section 1 of test_archival.php
   - Must show "Auto Archival Enabled: Yes"

### Problem: Archival Runs But Doesn't Delete Students

**Check the error log:**
```
Location: C:\xampp\htdocs\ibacmi\AdminAccount\archival_errors.log
```

Look for error messages that explain what went wrong.

### Problem: Want to Test Without Actually Deleting

Unfortunately, the current system performs actual deletions. For testing:

1. **Backup your database first:**
   - Use phpMyAdmin to export the `students` and `student_documents` tables
   
2. **Create a test student:**
   ```sql
   INSERT INTO students (student_id, first_name, last_name, course, year_level, 
                        graduation_date, is_graduated, is_archived) 
   VALUES ('TEST001', 'Test', 'Student', 'BSIT', 4, '2020-06-15', 1, 0);
   ```

3. **Run archival on timing = "immediate"**

4. **Verify the test student was deleted**

---

## 📊 Monitoring and Logs

### View Logs in Database
```sql
SELECT * FROM archive_logs 
ORDER BY archived_at DESC 
LIMIT 20;
```

### View Error Log File
```
C:\xampp\htdocs\ibacmi\AdminAccount\archival_errors.log
```

### Check Statistics
Use the test_archival.php page or query directly:
```sql
-- Count eligible students
SELECT COUNT(*) FROM students 
WHERE graduation_date IS NOT NULL 
AND graduation_date <= CURDATE()
AND is_graduated = 1 
AND is_archived = 0;

-- Count already archived
SELECT COUNT(*) FROM students 
WHERE is_archived = 1;
```

---

## 🎯 Quick Reference Commands

### Mark All Eligible Students as Graduated
```sql
UPDATE students 
SET is_graduated = 1 
WHERE graduation_date <= CURDATE() 
AND is_graduated = 0;
```

### View All Students with Graduation Info
```sql
SELECT student_id, first_name, last_name, graduation_date, 
       is_graduated, is_archived, status 
FROM students 
WHERE graduation_date IS NOT NULL 
ORDER BY graduation_date DESC;
```

### Reset a Student's Archive Status (for testing)
```sql
UPDATE students 
SET is_archived = 0, status = 'complete' 
WHERE student_id = 'YOUR_STUDENT_ID';
```

### View Archive History
```sql
SELECT archived_at, student_name, graduation_date, file_count, status, error_message 
FROM archive_logs 
ORDER BY archived_at DESC;
```

---

## 📝 Important Notes

1. **Data is Permanently Deleted**: The archival system DELETES data, it doesn't move it to another location. Make sure to backup important data before running.

2. **No File Archiving**: This system deletes database records only. Physical files are not backed up or archived.

3. **Irreversible**: Once a student is archived (deleted), the data cannot be recovered unless you have a database backup.

4. **Test First**: Always use the test_archival.php page to verify which students will be affected before running the actual archival.

5. **Graduation Date is Required**: Students without a graduation_date will NEVER be archived automatically.

---

## 🆘 Support

If you encounter issues:

1. Run `test_archival.php` first
2. Check the error log: `archival_errors.log`
3. Review the archive_logs table for any failed attempts
4. Check that all required fields are set on students

---

**Last Updated:** 2025-10-20  
**Version:** 1.0 - Initial Fix
