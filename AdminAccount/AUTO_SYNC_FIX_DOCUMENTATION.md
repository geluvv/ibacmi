# Auto-Sync Duplicate Folder Fix

## Problem Identified
The auto-sync feature was creating duplicate student folders in Google Drive whenever a document was updated through the "Lacking of Documents" page. This happened because different parts of the system were using inconsistent folder naming conventions.

## Root Cause
1. **Main Auto-Sync Processor** (`auto_sync_processor.php` line 635):
   - Used format: `"LastName, FirstName StudentID"` (with comma and space)
   
2. **Lacking of Doc Logic** (`lackingofdoc_logic.php` line 543 - BEFORE FIX):
   - Used format: `"LastName_FirstName_StudentID"` (with underscores)
   
3. **Auto-Sync Upload** (`auto_sync_upload.php` line 60 - BEFORE FIX):
   - Used format: `"LastName_FirstName_StudentID"` (with underscores)

When a document was updated through the lacking of documents page, the system couldn't find the existing folder because it was searching for a different name pattern, so it created a new folder instead.

## Solution Applied

### 1. Fixed `lackingofdoc_logic.php` (Line ~455)
**BEFORE:**
```php
$studentFolderName = "{$student['last_name']}_{$student['first_name']}_{$student['student_id']}";
$studentFolderId = createGoogleDriveFolder($studentFolderName, $mainFolderId);
```

**AFTER:**
```php
// ✅ FIX: Use SAME folder name format as auto_sync_processor.php to prevent duplicates
// Format: "LastName, FirstName StudentID" (with comma and space, NOT underscores)
$studentFolderName = "{$student['last_name']}, {$student['first_name']} {$student['student_id']}";

// ✅ CRITICAL: Check if folder already exists in manifest before creating new one
$studentFolderId = getStudentFolderFromManifest($studentDbId, $mainFolderId);

if (!$studentFolderId) {
    // Only create new folder if not found in manifest
    $studentFolderId = createGoogleDriveFolder($studentFolderName, $mainFolderId);
    if (!$studentFolderId) return false;
    error_log("✅ Created new student folder: {$studentFolderName}");
} else {
    error_log("✅ Reusing existing student folder from manifest: {$studentFolderName}");
}
```

### 2. Fixed `auto_sync_upload.php` (Line ~62)
**BEFORE:**
```php
// Create/get student folder: "LastName_FirstName_StudentID"
$studentFolderName = "{$student['last_name']}_{$student['first_name']}_{$student['student_id']}";
$studentFolderId = createGoogleDriveFolder($studentFolderName, $mainFolderId);
```

**AFTER:**
```php
// ✅ FIX: Use SAME folder name format as auto_sync_processor.php to prevent duplicates
// Format: "LastName, FirstName StudentID" (with comma and space, NOT underscores)
$studentFolderName = "{$student['last_name']}, {$student['first_name']} {$student['student_id']}";

// ✅ CRITICAL: Check if folder already exists in manifest before creating new one
$studentFolderId = getStudentFolderFromManifest($studentId, $mainFolderId);

if (!$studentFolderId) {
    // Only create new folder if not found in manifest
    $studentFolderId = createGoogleDriveFolder($studentFolderName, $mainFolderId);
    if (!$studentFolderId) {
        throw new Exception("Failed to create/find student folder");
    }
    error_log("✅ Created new student folder: {$studentFolderName}");
} else {
    error_log("✅ Reusing existing student folder from manifest: {$studentFolderName}");
}
```

## How the Fix Works

### Unified Folder Naming Convention
All auto-sync functions now use the **same** folder name format:
```php
"{$student['last_name']}, {$student['first_name']} {$student['student_id']}"
```

Example: `"Dela Cruz, Juan 2024-00123"`

### Smart Folder Reuse
Before creating a new folder, the system now:
1. **Checks the database** (`backup_manifest` table) for existing folder IDs associated with the student
2. **Verifies the folder exists** on Google Drive and is in the correct parent folder
3. **Reuses the existing folder** if found
4. **Only creates a new folder** if none exists

### Database Tracking
The `backup_manifest` table maintains a record of:
- Student ID → Google Drive Folder ID mapping
- Document ID → Google Drive File ID mapping
- File hashes for change detection
- Last sync timestamps

## Benefits

✅ **No More Duplicates**: Updated documents now sync to the existing student folder

✅ **Consistent Structure**: All auto-sync processes use the same naming convention

✅ **Better Performance**: Reusing folders reduces API calls and sync time

✅ **Accurate Tracking**: The manifest ensures we always know where files are stored

## Testing Recommendations

1. **Test Document Update**:
   - Enable auto-sync
   - Upload a document through "New Student" or "Transferee"
   - Update the same document through "Lacking of Documents"
   - Verify only ONE folder exists in Google Drive
   - Verify the updated file is in the same folder

2. **Test Multiple Updates**:
   - Update the same document multiple times
   - Verify no new folders are created
   - Verify file is updated in place

3. **Check Logs**:
   - Look for messages: `"✅ Reusing existing student folder from manifest"`
   - Look for folder name in format: `"LastName, FirstName StudentID"`

## Related Files Modified

1. `c:\xampp\htdocs\ibacmi\AdminAccount\lackingofdoc_logic.php`
2. `c:\xampp\htdocs\ibacmi\includes\auto_sync_upload.php`

## Related Functions (Already Correct)

These functions already use the correct format and were not modified:
- `auto_sync_processor.php` - Main sync processor
- `backup.php` - Manual backup functions
- `getStudentFolderFromManifest()` - Folder lookup function
- `createGoogleDriveFolder()` - Folder creation with duplicate prevention

## Date Fixed
October 20, 2025

## Developer Notes
If you need to add new auto-sync functionality in the future, always use:
1. The **unified folder naming format**: `"{$last_name}, {$first_name} {$student_id}"`
2. Call `getStudentFolderFromManifest()` before creating new folders
3. Log folder reuse vs. creation for debugging

---
**Status**: ✅ RESOLVED - Auto-sync now properly updates existing folders instead of creating duplicates.
