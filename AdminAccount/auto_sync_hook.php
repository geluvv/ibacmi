<?php
// Auto-sync hook that can be called when documents are uploaded or updated
require_once 'backup.php';

// Function to trigger auto-sync when a document is uploaded or updated
function triggerDocumentSync($studentId, $documentId) {
    global $conn;
    
    // Check if auto-sync is enabled
    if (!isAutoSyncEnabled()) {
        return false;
    }
    
    // Get access token
    $accessToken = getStoredAccessToken();
    if (!$accessToken) {
        error_log("Auto-sync failed: No Google Drive access token found");
        return false;
    }
    
    try {
        // Get student information
        $studentQuery = "SELECT * FROM students WHERE id = ?";
        $studentStmt = $conn->prepare($studentQuery);
        $studentStmt->bind_param("i", $studentId);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();
        $student = $studentResult->fetch_assoc();
        $studentStmt->close();
        
        if (!$student) {
            error_log("Auto-sync failed: Student not found with ID {$studentId}");
            return false;
        }
        
        // Sync the student's folder to Google Drive
        $syncResult = syncStudentToGoogleDrive($student, $accessToken);
        
        if ($syncResult['success']) {
            error_log("Auto-sync successful for student {$studentId}: {$syncResult['uploaded_count']} files synced");
            return true;
        } else {
            error_log("Auto-sync failed for student {$studentId}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Auto-sync error for student {$studentId}: " . $e->getMessage());
        return false;
    }
}

// Function to be called when a document is uploaded
function onDocumentUpload($studentId, $documentId) {
    // Trigger auto-sync in the background
    if (isAutoSyncEnabled()) {
        // You can implement this as a background job or immediate sync
        triggerDocumentSync($studentId, $documentId);
    }
}

// Function to be called when a document is updated
function onDocumentUpdate($studentId, $documentId) {
    // Trigger auto-sync in the background
    if (isAutoSyncEnabled()) {
        triggerDocumentSync($studentId, $documentId);
    }
}
?>
