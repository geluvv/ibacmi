# Destination-Aware Backup System - Visual Guide

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    IBACMI Backup System                          │
│                 Destination-Aware Architecture                   │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │ Student Documents│
                    │   (Uploads)      │
                    └────────┬─────────┘
                             │
                             ▼
            ┌────────────────────────────────┐
            │   Backup Destination Selector   │
            │   (User chooses destination)    │
            └────────────┬───────────────────┘
                         │
        ┌────────────────┼────────────────┐
        │                │                │
        ▼                ▼                ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ Google Drive │  │Local Storage │  │  Auto-Sync   │
│  Destination │  │ Destination  │  │ Destination  │
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                 │                 │
       ▼                 ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│backup_manifest│ │local_backup_ │  │backup_manifest│
│    Table     │  │manifest Table│  │    Table     │
│              │  │              │  │              │
│ - student_id │  │ - student_id │  │ - student_id │
│ - document_id│  │ - document_id│  │ - document_id│
│ - file_hash  │  │ - file_hash  │  │ - file_hash  │
│ - gdrive_id  │  │ - local_path │  │ - gdrive_id  │
└──────────────┘  └──────────────┘  └──────────────┘

     ▲                 ▲                 ▲
     │                 │                 │
     └─────────────────┴─────────────────┘
               Each destination maintains
              its own independent state!
```

## Backup Decision Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     BACKUP PROCESS FLOW                          │
└─────────────────────────────────────────────────────────────────┘

START: User initiates backup
  │
  ▼
┌───────────────────────────┐
│ Step 1: Select Destination│
│ - Google Drive            │
│ - Local Storage           │
│ - Manual Backup           │
│ - Auto-Sync              │
└──────────┬────────────────┘
           │
           ▼
┌────────────────────────────────────────┐
│ Step 2: Query Destination Manifest     │
│                                        │
│ IF Google Drive/Auto-Sync:             │
│   → Query backup_manifest              │
│                                        │
│ IF Local/Manual:                       │
│   → Query local_backup_manifest        │
└──────────┬─────────────────────────────┘
           │
           ▼
┌────────────────────────────────────────┐
│ Step 3: Identify Pending Files         │
│                                        │
│ FOR EACH document:                     │
│   ┌─────────────────────────────────┐ │
│   │ Is it in this manifest?         │ │
│   │  NO  → Mark as PENDING (new)    │ │
│   │  YES → Check hash               │ │
│   │    Changed?   → PENDING (mod)   │ │
│   │    Unchanged? → SKIP            │ │
│   └─────────────────────────────────┘ │
└──────────┬─────────────────────────────┘
           │
           ▼
┌────────────────────────────────────────┐
│ Step 4: Execute Backup                 │
│                                        │
│ FOR EACH pending file:                 │
│   ┌─────────────────────────────────┐ │
│   │ NEW file:                       │ │
│   │   - Upload/Copy to destination  │ │
│   │   - Add record to manifest      │ │
│   │                                 │ │
│   │ MODIFIED file:                  │ │
│   │   - Update at destination       │ │
│   │   - Update manifest record      │ │
│   │                                 │ │
│   │ SKIPPED file:                   │ │
│   │   - Do nothing                  │ │
│   └─────────────────────────────────┘ │
└──────────┬─────────────────────────────┘
           │
           ▼
┌────────────────────────────────────────┐
│ Step 5: Update Statistics              │
│ - Files uploaded/copied                │
│ - Files updated                        │
│ - Files skipped                        │
│ - Total time/size                      │
└────────────────────────────────────────┘

END: Backup complete for THIS destination
(Other destinations remain independent)
```

## Example: 10 Files, 3 Destinations

```
┌─────────────────────────────────────────────────────────────────┐
│              VISUAL EXAMPLE: FILE BACKUP STATUS                  │
└─────────────────────────────────────────────────────────────────┘

Initial State (10 new documents uploaded):
─────────────────────────────────────────────────────────────────

Documents:  [1] [2] [3] [4] [5] [6] [7] [8] [9] [10]

Google Drive: [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ]
              └──────────── All 10 PENDING ────────────┘

Local Storage:[ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ]
              └──────────── All 10 PENDING ────────────┘


After backing up files 1-5 to Google Drive:
─────────────────────────────────────────────────────────────────

Documents:  [1] [2] [3] [4] [5] [6] [7] [8] [9] [10]

Google Drive: [✓] [✓] [✓] [✓] [✓] [ ] [ ] [ ] [ ] [ ]
              └────5 BACKED UP────┘ └────5 PENDING────┘

Local Storage:[ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ] [ ]
              └──────────── All 10 STILL PENDING ──────┘
              (Independent from Google Drive!)


After backing up ALL files to Local Storage:
─────────────────────────────────────────────────────────────────

Documents:  [1] [2] [3] [4] [5] [6] [7] [8] [9] [10]

Google Drive: [✓] [✓] [✓] [✓] [✓] [ ] [ ] [ ] [ ] [ ]
              └────5 BACKED UP────┘ └────5 PENDING────┘
              (Unchanged from before)

Local Storage:[✓] [✓] [✓] [✓] [✓] [✓] [✓] [✓] [✓] [✓]
              └──────────── All 10 BACKED UP ─────────┘
              (Includes the 5 already on Google Drive!)


After uploading 3 NEW documents (11, 12, 13):
─────────────────────────────────────────────────────────────────

Documents:  [1] [2] [3] [4] [5] [6] [7] [8] [9] [10] [11] [12] [13]

Google Drive: [✓] [✓] [✓] [✓] [✓] [ ] [ ] [ ] [ ] [ ]  [ ]  [ ]  [ ]
              └────5 BACKED UP────┘ └─────5 OLD PENDING────┘ └─3 NEW─┘
              Total: 8 PENDING

Local Storage:[✓] [✓] [✓] [✓] [✓] [✓] [✓] [✓] [✓] [✓] [ ]  [ ]  [ ]
              └──────────── 10 BACKED UP ──────────────┘ └──3 NEW──┘
              Total: 3 PENDING
```

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                   HOW DATA FLOWS IN THE SYSTEM                   │
└─────────────────────────────────────────────────────────────────┘

User Action: "Create Backup to Google Drive"
│
├─► Frontend sends: POST /backup.php
│   {
│     action: "create_backup",
│     storageType: "google_drive",
│     schoolYear: "2025-2026"
│   }
│
├─► Backend processes:
│   │
│   ├─► Step 1: Create backup_logs entry
│   │   INSERT INTO backup_logs (storage_type, school_year, ...)
│   │
│   ├─► Step 2: Get all students with documents
│   │   SELECT * FROM students WHERE has_documents = 1
│   │
│   ├─► Step 3: For each student:
│   │   │
│   │   ├─► Get student's documents
│   │   │   SELECT * FROM student_documents WHERE student_id = ?
│   │   │
│   │   ├─► For each document:
│   │   │   │
│   │   │   ├─► Check if already in backup_manifest
│   │   │   │   SELECT * FROM backup_manifest
│   │   │   │   WHERE student_id = ? AND document_id = ?
│   │   │   │
│   │   │   ├─► Calculate current file hash
│   │   │   │   $hash = md5_file($filePath)
│   │   │   │
│   │   │   ├─► Decision:
│   │   │   │   ┌─────────────────────────────────┐
│   │   │   │   │ NOT in manifest?                │
│   │   │   │   │   → Upload to Google Drive      │
│   │   │   │   │   → INSERT into backup_manifest │
│   │   │   │   │                                 │
│   │   │   │   │ In manifest, hash CHANGED?      │
│   │   │   │   │   → Update on Google Drive      │
│   │   │   │   │   → UPDATE backup_manifest hash │
│   │   │   │   │                                 │
│   │   │   │   │ In manifest, hash SAME?         │
│   │   │   │   │   → Skip (already backed up)    │
│   │   │   │   └─────────────────────────────────┘
│   │   │   │
│   │   │   └─► Record result (uploaded/updated/skipped)
│   │   │
│   │   └─► Move to next document
│   │
│   └─► Step 4: Update backup_logs with final counts
│       UPDATE backup_logs SET
│         files_uploaded = ?,
│         files_updated = ?,
│         files_skipped = ?,
│         status = 'success'
│
└─► Frontend receives:
    {
      status: "success",
      data: {
        student_count: 120,
        file_count: 450,
        files_uploaded: 25,    // NEW files
        files_updated: 5,      // MODIFIED files
        files_skipped: 420     // UNCHANGED files
      }
    }
```

## Comparison: Old vs New System

```
┌─────────────────────────────────────────────────────────────────┐
│             OLD SYSTEM (Before Destination-Aware)                │
└─────────────────────────────────────────────────────────────────┘

Problem: Single shared state
─────────────────────────────

  Backup to Google Drive (5 files)
         ↓
  backup_manifest table updated
         ↓
  System thinks ALL destinations have these files!
         ↓
  Local storage shows: 0 pending ❌
  (Even though nothing was copied locally!)


┌─────────────────────────────────────────────────────────────────┐
│             NEW SYSTEM (Destination-Aware)                       │
└─────────────────────────────────────────────────────────────────┘

Solution: Independent state per destination
───────────────────────────────────────────

  Backup to Google Drive (5 files)
         ↓
  backup_manifest table updated
         ↓
  Google Drive: 5 backed up ✓
         
  Local storage is independent!
         ↓
  local_backup_manifest NOT affected
         ↓
  Local storage shows: 10 pending ✓
  (Correct! Nothing copied locally yet)
```

## Performance Comparison

```
┌─────────────────────────────────────────────────────────────────┐
│                   PERFORMANCE VISUALIZATION                      │
└─────────────────────────────────────────────────────────────────┘

Scenario: Backup 100 files (90 unchanged, 10 new)

OLD SYSTEM (No Duplicate Prevention):
████████████████████████████████████████████████████████████████
Upload: 100 files
Time: ████████████████████████████████████ 10 minutes
Bandwidth: ███████████████████████████████████████ 500 MB


NEW SYSTEM (With Destination-Aware Duplicate Prevention):
██████
Upload: 10 files only
Time: ████ 1 minute  (90% faster!)
Bandwidth: ████ 50 MB  (90% less bandwidth!)

Files Skipped: ██████████████████████████████████████████ 90
              (Already backed up, unchanged)


┌─────────────────────────────────────────────────────────────────┐
│ Efficiency Gains:                                                │
│ ✓ 90% reduction in upload time                                   │
│ ✓ 90% reduction in bandwidth usage                               │
│ ✓ 90% reduction in API calls                                     │
│ ✓ 100% increase in user happiness! 😊                            │
└─────────────────────────────────────────────────────────────────┘
```

## State Machine Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│              DOCUMENT STATE PER DESTINATION                      │
└─────────────────────────────────────────────────────────────────┘

                    ┌─────────────┐
                    │   UPLOADED  │
                    │ (New Document)│
                    └──────┬──────┘
                           │
                           ▼
                    ┌─────────────┐
                    │  PENDING    │◄─────┐
                    │ (Not in     │      │
                    │  manifest)  │      │
                    └──────┬──────┘      │
                           │             │
                 Backup    │             │ File
                 Started   │             │ Modified
                           ▼             │
                    ┌─────────────┐      │
                    │ BACKING UP  │      │
                    │ (In progress)│      │
                    └──────┬──────┘      │
                           │             │
                 Success   │             │
                           ▼             │
                    ┌─────────────┐      │
                    │ BACKED UP   │      │
        ┌──────────►│ (In manifest├──────┘
        │           │  w/ hash)   │
        │           └──────┬──────┘
        │                  │
        │        File      │ No
        │        Changed?  │ Change
        │                  │
        │         Yes      │
        └──────────────────┘

Each destination has its OWN state machine!
Google Drive state ≠ Local Storage state
```

## Quick Reference Card

```
╔═══════════════════════════════════════════════════════════════╗
║         DESTINATION-AWARE BACKUP QUICK REFERENCE              ║
╠═══════════════════════════════════════════════════════════════╣
║                                                               ║
║  Destinations:                                                ║
║  ┌──────────────────┬────────────────────────────────────┐  ║
║  │ google_drive     │ Google Drive cloud backups         │  ║
║  │ local            │ Local folder backups               │  ║
║  │ manual           │ Manual backup location             │  ║
║  │ auto_sync        │ Automatic Google Drive sync        │  ║
║  └──────────────────┴────────────────────────────────────┘  ║
║                                                               ║
║  Manifest Tables:                                             ║
║  ┌──────────────────────┬──────────────────────────────┐    ║
║  │ backup_manifest      │ Google Drive/Auto-Sync       │    ║
║  │ local_backup_manifest│ Local/Manual backups         │    ║
║  └──────────────────────┴──────────────────────────────┘    ║
║                                                               ║
║  Key API Functions:                                           ║
║  • getPendingFilesCountByDestination($dest)                   ║
║  • getPendingFilesByDestination($dest, $yearId)               ║
║  • isDocumentBackedUpToGoogleDrive($sid, $did)                ║
║  • isDocumentBackedUpLocally($sid, $did)                      ║
║                                                               ║
║  API Endpoints:                                               ║
║  GET /backup.php?action=get_backup_statistics&destination=X   ║
║  GET /backup.php?action=get_pending_files_by_destination      ║
║                  &destination=X&school_year_id=Y              ║
║                                                               ║
║  Remember:                                                    ║
║  ✓ Each destination is INDEPENDENT                            ║
║  ✓ Files backed up to one destination don't affect others     ║
║  ✓ Unchanged files are automatically SKIPPED                  ║
║  ✓ Modified files are automatically UPDATED                   ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
```

---

**This visual guide complements the technical documentation.**  
**For detailed implementation, see DESTINATION_AWARE_BACKUP_SYSTEM.md**
