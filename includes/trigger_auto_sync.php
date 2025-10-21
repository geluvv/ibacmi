<?php
// filepath: c:\xampp\htdocs\ibacmi\includes\trigger_auto_sync.php
/**
 * Auto-Sync Trigger - Called after document uploads/updates
 * This ensures real-time syncing without waiting for the 2-minute interval
 */

require_once __DIR__ . '/../db_connect.php';

/**
 * Trigger auto-sync immediately after document upload/update
 */
function triggerAutoSyncNow($studentId = null, $documentId = null) {
    global $conn;
    
    // Check if auto-sync is enabled
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    if ($row['setting_value'] !== 'enabled') {
        return false;
    }
    
    // Check if Google Drive is connected
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'google_drive_connected'";
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    if ($row['setting_value'] !== '1') {
        return false;
    }
    
    // Trigger the auto-sync processor
    $autoSyncUrl = 'http://localhost/ibacmi/AdminAccount/auto_sync_processor.php';
    
    // Use async call (non-blocking)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $autoSyncUrl,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 1, // Very short timeout - fire and forget
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_POSTFIELDS => http_build_query([
            'trigger' => 'immediate',
            'student_id' => $studentId,
            'document_id' => $documentId
        ])
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    error_log("✓ Auto-sync triggered for student ID: $studentId, document ID: $documentId");
    
    return true;
}
?>