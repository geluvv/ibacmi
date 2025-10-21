<?php
/**
 * Auto-sync newly uploaded documents to Google Drive
 * Include this file after successful document uploads
 */

function autoSyncToGoogleDrive($studentId, $documentId, $filePath, $originalFilename) {
    global $conn;
    
    // Check if auto-sync is enabled
    $syncQuery = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
    $syncResult = $conn->query($syncQuery);
    
    if (!$syncResult || $syncResult->num_rows === 0) {
        return false; // Auto-sync not configured
    }
    
    $syncRow = $syncResult->fetch_assoc();
    if ($syncRow['setting_value'] !== 'enabled') {
        return false; // Auto-sync disabled
    }
    
    // Get access token
    require_once __DIR__ . '/../AdminAccount/backup.php';
    $accessToken = getValidAccessToken();
    
    if (!$accessToken) {
        error_log("Auto-sync failed: No valid access token");
        return false;
    }
    
    try {
        // Get student info
        $studentQuery = "SELECT student_id, first_name, last_name FROM students WHERE id = ?";
        $stmt = $conn->prepare($studentQuery);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentResult = $stmt->get_result();
        
        if ($studentResult->num_rows === 0) {
            throw new Exception("Student not found");
        }
        
        $student = $studentResult->fetch_assoc();
        $stmt->close();
        
        // Determine school year
        $currentMonth = date('n');
        $currentYear = date('Y');
        $schoolYear = ($currentMonth >= 6) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
        
        // Create/get main folder: "IBACMI Backup [School Year]"
        $mainFolderName = "IBACMI Backup {$schoolYear}";
        $mainFolderId = createGoogleDriveFolder($mainFolderName);
        
        if (!$mainFolderId) {
            throw new Exception("Failed to create/find main folder");
        }
        
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
        
        // Upload the file
        $fullPath = '../' . ltrim($filePath, '/');
        
        if (!file_exists($fullPath)) {
            throw new Exception("File not found: {$fullPath}");
        }
        
        $googleFileId = uploadFileToGoogleDrive($fullPath, $originalFilename, $studentFolderId);
        
        if (!$googleFileId) {
            throw new Exception("Failed to upload file to Google Drive");
        }
        
        // Log successful sync
        $logQuery = "INSERT INTO sync_logs (student_id, document_id, google_drive_file_id, sync_status, sync_type, synced_at) 
                    VALUES (?, ?, ?, 'success', 'auto', NOW())
                    ON DUPLICATE KEY UPDATE 
                    google_drive_file_id = VALUES(google_drive_file_id),
                    sync_status = 'success',
                    sync_type = 'auto',
                    error_message = NULL,
                    synced_at = NOW()";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("iis", $studentId, $documentId, $googleFileId);
        $logStmt->execute();
        $logStmt->close();
        
        error_log("Auto-sync successful: File '{$originalFilename}' synced to folder '{$studentFolderName}'");
        return true;
        
    } catch (Exception $e) {
        // Log failed sync
        error_log("Auto-sync error: " . $e->getMessage());
        
        $logQuery = "INSERT INTO sync_logs (student_id, document_id, sync_status, sync_type, error_message, synced_at) 
                    VALUES (?, ?, 'failed', 'auto', ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    sync_status = 'failed',
                    sync_type = 'auto',
                    error_message = VALUES(error_message),
                    synced_at = NOW()";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("iis", $studentId, $documentId, $e->getMessage());
        $logStmt->execute();
        $logStmt->close();
        
        return false;
    }
}
?>