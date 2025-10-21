# Destination-Aware Backup System Documentation

## Overview

The IBACMI Backup System now implements **destination-aware duplicate prevention**, which means each backup destination independently tracks which files have been backed up to it. This prevents unnecessary re-uploads/copies while allowing the same files to be backed up to multiple destinations.

## Key Concepts

### Independent Backup Destinations

The system supports four independent backup destinations:

1. **Google Drive** - Cloud storage via Google Drive API
2. **Local Storage** - Manual local folder backups
3. **Manual Backup** - User-initiated local backups
4. **Auto-Sync** - Automated Google Drive synchronization

Each destination maintains its own state and doesn't interfere with others.

### Manifest Tables

Two separate manifest tables track backups:

#### 1. `backup_manifest` - Tracks Google Drive Backups
- Used for Google Drive and Auto-Sync backups
- Stores: `google_drive_file_id`, `google_drive_folder_id`, `file_hash`
- Each record represents a file uploaded to Google Drive

#### 2. `local_backup_manifest` - Tracks Local Backups
- Used for Local Storage and Manual Backup
- Stores: `local_file_path`, `file_hash`
- Each record represents a file copied to local storage

## How It Works

### Scenario Example

Let's walk through a real-world scenario:

#### Initial State
- 10 new student documents uploaded
- No backups performed yet
- All 10 documents are "pending" for ALL destinations

#### Step 1: Backup 5 Files to Google Drive
```
Files backed up: Doc1, Doc2, Doc3, Doc4, Doc5
Files remaining: Doc6, Doc7, Doc8, Doc9, Doc10

Google Drive Status:
  ✅ 5 files backed up
  ⏳ 5 files pending

Local Storage Status:
  ⏳ 10 files pending (none backed up yet)
```

#### Step 2: Later, Backup to Local Storage
```
Because Local Storage is independent, it shows:
  ⏳ 10 files pending

The system will backup ALL 10 files to local storage,
including the 5 already on Google Drive.

After local backup completes:
  Google Drive: 5 backed up, 5 pending
  Local Storage: 10 backed up, 0 pending
```

#### Step 3: Upload 3 More Documents, Backup to Google Drive
```
New files: Doc11, Doc12, Doc13

Google Drive shows 8 pending files:
  - Doc6, Doc7, Doc8, Doc9, Doc10 (never backed up to GDrive)
  - Doc11, Doc12, Doc13 (newly uploaded)

Local Storage shows 3 pending files:
  - Doc11, Doc12, Doc13 (newly uploaded, not in local yet)
```

## API Functions

### Core Duplicate Check Functions

#### `isDocumentBackedUpToGoogleDrive($studentId, $documentId)`
- Checks if a document exists in Google Drive
- Returns file info if found, null otherwise

#### `isDocumentBackedUpLocally($studentId, $documentId)`
- Checks if a document exists in local storage
- Returns file info if found, null otherwise

### Pending Files Functions

#### `getPendingFilesCountByDestination($destination, $schoolYearId = null)`
```php
// Example usage:
$pendingGoogleDrive = getPendingFilesCountByDestination('google_drive');
$pendingLocal = getPendingFilesCountByDestination('local');

// Returns: integer count of files pending for that destination
```

#### `getPendingFilesByDestination($destination, $schoolYearId = null)`
```php
// Example usage:
$files = getPendingFilesByDestination('google_drive');

// Returns: array of documents with details:
// [
//   {
//     document_id, student_id, file_path, original_filename,
//     file_size, student_number, first_name, last_name, document_type
//   },
//   ...
// ]
```

### API Endpoints

#### Get Backup Statistics (Enhanced)
```
GET /backup.php?action=get_backup_statistics&destination=google_drive
```

**Parameters:**
- `destination` (optional): 'google_drive', 'local', 'manual', or 'auto_sync'

**Response:**
```json
{
  "status": "success",
  "data": {
    "total_backups": 15,
    "total_students": 120,
    "total_files": 450,
    "total_storage": 2147483648,
    "storage_formatted": "2.0 GB",
    "pending_files": 25,
    "pending_google_drive": 25,
    "pending_local": 10,
    "destination": "google_drive"
  }
}
```

#### Get Pending Files by Destination (New)
```
GET /backup.php?action=get_pending_files_by_destination&destination=local&school_year_id=3
```

**Parameters:**
- `destination` (required): 'google_drive', 'local', 'manual', or 'auto_sync'
- `school_year_id` (optional): Filter by specific school year

**Response:**
```json
{
  "status": "success",
  "data": {
    "destination": "local",
    "pending_count": 10,
    "school_year_id": 3,
    "pending_files": [
      {
        "document_id": 156,
        "student_id": 45,
        "file_path": "uploads/2025/...",
        "original_filename": "transcript.pdf",
        "file_size": 524288,
        "student_number": "2025-0045",
        "first_name": "John",
        "last_name": "Doe",
        "document_type": "Transcript of Records"
      }
    ]
  }
}
```

## Backup Process Flow

### Google Drive Backup Process

1. **Pre-Backup Check**
   - Query `backup_manifest` for student's documents
   - Identify files NOT in manifest (new files)
   - Identify files with changed hashes (modified files)

2. **Backup Execution**
   - **New Files**: Upload to Google Drive, record in manifest
   - **Modified Files**: Update existing Google Drive file, update manifest
   - **Unchanged Files**: Skip completely

3. **Result**
   - Only new/modified files are uploaded
   - Bandwidth and time saved
   - Google Drive stays in sync

### Local Backup Process

1. **Pre-Backup Check**
   - Query `local_backup_manifest` for student's documents
   - Identify files NOT in manifest (new files)
   - Identify files with changed hashes (modified files)

2. **Backup Execution**
   - **New Files**: Copy to local folder, record in manifest
   - **Modified Files**: Copy to local folder (overwrite), update manifest
   - **Unchanged Files**: Skip completely

3. **Result**
   - Only new/modified files are copied
   - Disk I/O minimized
   - Local backup stays current

## Benefits

### 1. No Cross-Destination Interference
- Files backed up to Google Drive don't affect local backup counts
- Each destination shows accurate pending counts
- Users can backup to multiple destinations without confusion

### 2. Efficient Resource Usage
- Prevents re-uploading files to Google Drive unnecessarily
- Prevents re-copying files to local storage unnecessarily
- Saves bandwidth, time, and storage space

### 3. Change Detection
- Uses MD5 file hashes to detect modifications
- Automatically updates changed files
- Skips unchanged files completely

### 4. Flexible Backup Strategy
- Users can choose different strategies per destination
- Example: Daily Google Drive sync, weekly local backups
- No conflicts between different backup schedules

## Implementation Details

### File Hash Calculation
```php
function calculateFileHash($filePath) {
    return md5_file($filePath);
}
```
- MD5 hash provides fast, reliable change detection
- Hash stored in manifest for comparison
- Files only re-backed-up if hash changes

### Database Schema Additions

#### Enhanced `backup_manifest` Table
```sql
CREATE TABLE `backup_manifest` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `backup_log_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `document_id` INT NOT NULL,
  `google_drive_file_id` VARCHAR(255),
  `google_drive_folder_id` VARCHAR(255),
  `file_hash` VARCHAR(64),
  `file_size` BIGINT,
  `backup_type` ENUM('new', 'modified'),
  `backed_up_at` TIMESTAMP,
  UNIQUE KEY `unique_student_document` (`student_id`, `document_id`)
);
```

#### `local_backup_manifest` Table
```sql
CREATE TABLE `local_backup_manifest` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `backup_log_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `document_id` INT NOT NULL,
  `local_file_path` VARCHAR(500),
  `file_hash` VARCHAR(64),
  `file_size` BIGINT,
  `backup_type` ENUM('new', 'modified'),
  `backed_up_at` TIMESTAMP,
  `last_synced_at` TIMESTAMP,
  UNIQUE KEY `unique_student_document` (`student_id`, `document_id`)
);
```

## User Interface Impact

### Before (Old System)
```
Pending Files: 0
(Even though local storage has never been used)
```

### After (Destination-Aware System)
```
Select Backup Destination:
● Google Drive    → Pending: 0 files
○ Local Storage   → Pending: 120 files

(Shows accurate count per destination)
```

## Troubleshooting

### "Why does local storage show pending files after Google Drive backup?"

**Answer:** This is correct behavior! Each destination tracks its own state. Files backed up to Google Drive still need to be backed up to local storage if you want local copies.

### "Can I backup the same files to multiple destinations?"

**Answer:** Yes! That's the whole point of destination-aware tracking. Each destination is independent, so you can backup files to Google Drive, local storage, and manual backup locations without interference.

### "How do I clear a destination's backup state?"

**Answer:** 
- For Google Drive: Delete records from `backup_manifest`
- For Local Storage: Delete records from `local_backup_manifest`

```sql
-- Clear Google Drive backup history for a student
DELETE FROM backup_manifest WHERE student_id = ?;

-- Clear local backup history for a student
DELETE FROM local_backup_manifest WHERE student_id = ?;
```

## Migration Notes

### Upgrading from Old System

If you're upgrading from the old backup system:

1. **Existing `backup_manifest` records** - These remain unchanged
2. **New `local_backup_manifest` table** - Created automatically
3. **First local backup** - Will backup all files (none are in local manifest yet)
4. **Future backups** - Will use destination-aware logic

### Backward Compatibility

The system maintains backward compatibility:
- Old backup logs still work
- Existing manifests remain valid
- New logic only affects future backups

## Performance Considerations

### Query Optimization
- Indexed columns: `student_id`, `document_id`
- Unique keys prevent duplicate records
- Efficient LEFT JOIN for pending file detection

### Scalability
- Works efficiently with thousands of documents
- Minimal database queries per backup
- Hash comparison faster than file comparison

## Future Enhancements

Potential future improvements:
1. **Multi-destination backup preview** - Show what would be backed up to each destination
2. **Backup scheduling per destination** - Different schedules for different destinations
3. **Automatic synchronization** - Keep destinations in sync automatically
4. **Backup verification** - Verify file integrity across destinations

---

**Version:** 1.0.0  
**Last Updated:** October 21, 2025  
**Author:** IBACMI Development Team
