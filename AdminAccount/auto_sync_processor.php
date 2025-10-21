<?php
/**
 * Auto-Sync Processor - Automatic Google Drive Sync
 * Runs automatically when auto-sync is enabled
 * Syncs new/modified documents to Google Drive instantly
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/auto_sync_errors.log');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

require_once '../db_connect.php';

// Load Google Drive configuration
$config = require_once '../config/google_drive_config.php';
define('GOOGLE_CLIENT_ID', $config['client_id']);
define('GOOGLE_CLIENT_SECRET', $config['client_secret']);
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');

date_default_timezone_set('Asia/Manila');

/**
 * Check if auto-sync is enabled
 */
function isAutoSyncEnabled() {
    global $conn;
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'] === 'enabled';
    }
    
    return false;
}

/**
 * Check if Google Drive is connected
 */
function isGoogleDriveConnected() {
    global $conn;
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'google_drive_connected'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'] === '1';
    }
    
    return false;
}

/**
 * Get stored tokens
 */
function getStoredTokens() {
    global $conn;
    
    $tokens = ['access_token' => null, 'refresh_token' => null];
    
    $query = "SELECT setting_name, setting_value FROM system_settings 
              WHERE setting_name IN ('google_drive_access_token', 'google_drive_refresh_token')";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['setting_name'] === 'google_drive_access_token') {
                $tokens['access_token'] = $row['setting_value'];
            } elseif ($row['setting_name'] === 'google_drive_refresh_token') {
                $tokens['refresh_token'] = $row['setting_value'];
            }
        }
    }
    
    return $tokens;
}

/**
 * Refresh access token
 */
function refreshAccessToken() {
    global $conn;
    
    $tokens = getStoredTokens();
    if (empty($tokens['refresh_token'])) {
        return null;
    }
    
    $postData = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $tokens['refresh_token'],
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => GOOGLE_TOKEN_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            // Store new token
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                                   VALUES ('google_drive_access_token', ?, NOW())
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("ss", $data['access_token'], $data['access_token']);
            $stmt->execute();
            $stmt->close();
            
            return $data['access_token'];
        }
    }
    
    return null;
}

/**
 * Get valid access token
 */
function getValidAccessToken() {
    $tokens = getStoredTokens();
    
    if (empty($tokens['access_token'])) {
        return null;
    }
    
    // Test token
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/about?fields=user',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokens['access_token']],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return $tokens['access_token'];
    }
    
    // Token expired, refresh it
    if ($httpCode === 401) {
        return refreshAccessToken();
    }
    
    return null;
}

/**
 * Create or get Google Drive folder
 */
function createGoogleDriveFolder($folderName, $parentFolderId = null) {
    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        return null;
    }
    
    // Search for existing folder
    $query = "name='" . addslashes($folderName) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";
    if ($parentFolderId) {
        $query .= " and '" . $parentFolderId . "' in parents";
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name)',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['files'])) {
            return $data['files'][0]['id'];
        }
    }
    
    // Create new folder
    $metadata = ['name' => $folderName, 'mimeType' => 'application/vnd.google-apps.folder'];
    if ($parentFolderId) {
        $metadata['parents'] = [$parentFolderId];
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($metadata),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }
    
    return null;
}

/**
 * Check if file already exists in Google Drive folder - ENHANCED VERSION
 */
function checkFileExistsInDrive($fileName, $folderId) {
    $accessToken = getValidAccessToken();
    if (!$accessToken || !$folderId) {
        return null;
    }
    
    // Search for file with exact name in specific folder
    $query = "name='" . addslashes($fileName) . "' and '" . $folderId . "' in parents and trashed=false";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name,md5Checksum)',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['files'])) {
            // Return the first matching file
            return [
                'id' => $data['files'][0]['id'],
                'md5' => $data['files'][0]['md5Checksum'] ?? null
            ];
        }
    }
    
    return null;
}

/**
 * Upload file to Google Drive - WITH DUPLICATE CHECK
 */
function uploadFileToGoogleDrive($filePath, $fileName, $parentFolderId = null) {
    $accessToken = getValidAccessToken();
    if (!$accessToken || !file_exists($filePath)) {
        return null;
    }
    
    // **STEP 1: Check if file already exists**
    if ($parentFolderId) {
        $existingFile = checkFileExistsInDrive($fileName, $parentFolderId);
        if ($existingFile) {
            error_log("⚠️ File already exists in Drive: $fileName (ID: {$existingFile['id']})");
            return $existingFile['id']; // Return existing file ID instead of uploading duplicate
        }
    }
    
    // **STEP 2: File doesn't exist, upload it**
    $fileContent = file_get_contents($filePath);
    $mimeType = mime_content_type($filePath);
    
    $boundary = '-------314159265358979323846';
    $delimiter = "\r\n--" . $boundary . "\r\n";
    $closeDelim = "\r\n--" . $boundary . "--";
    
    $metadata = ['name' => $fileName];
    if ($parentFolderId) {
        $metadata['parents'] = [$parentFolderId];
    }
    
    $multipartBody = $delimiter;
    $multipartBody .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $multipartBody .= json_encode($metadata);
    $multipartBody .= $delimiter;
    $multipartBody .= "Content-Type: " . $mimeType . "\r\n";
    $multipartBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $multipartBody .= base64_encode($fileContent);
    $multipartBody .= $closeDelim;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $multipartBody,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($multipartBody)
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        error_log("✅ New file uploaded: $fileName (ID: {$data['id']})");
        return $data['id'] ?? null;
    }
    
    error_log("❌ Upload failed for: $fileName (HTTP: $httpCode)");
    return null;
}

/**
 * Update existing file on Google Drive
 */
function updateGoogleDriveFile($fileId, $localPath, $fileName) {
    $accessToken = getValidAccessToken();
    if (!$accessToken || !file_exists($localPath)) {
        return false;
    }
    
    $fileContent = file_get_contents($localPath);
    $mimeType = mime_content_type($localPath);
    
    $boundary = '-------314159265358979323846';
    $delimiter = "\r\n--" . $boundary . "\r\n";
    $closeDelim = "\r\n--" . $boundary . "--";
    
    $metadata = ['name' => $fileName];
    
    $multipartBody = $delimiter;
    $multipartBody .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $multipartBody .= json_encode($metadata);
    $multipartBody .= $delimiter;
    $multipartBody .= "Content-Type: " . $mimeType . "\r\n";
    $multipartBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $multipartBody .= base64_encode($fileContent);
    $multipartBody .= $closeDelim;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files/' . $fileId . '?uploadType=multipart',
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $multipartBody,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($multipartBody)
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/**
 * Calculate file hash
 */
function calculateFileHash($filePath) {
    return md5_file($filePath);
}

/**
 * Find local file
 */
function findLocalFile($filePath) {
    $filePath = str_replace('\\', '/', $filePath);
    
    $basePaths = [
        __DIR__ . '/../',
        __DIR__ . '/',
        'C:/xampp/htdocs/ibacmi/',
        '../'
    ];
    
    foreach ($basePaths as $basePath) {
        $fullPath = realpath($basePath . $filePath);
        if ($fullPath && file_exists($fullPath)) {
            return $fullPath;
        }
    }
    
    if (file_exists($filePath)) {
        return $filePath;
    }
    
    return null;
}

/**
 * Check if document is already backed up - ENHANCED VERSION
 */
function isDocumentBackedUp($studentId, $documentId) {
    global $conn;
    
    $query = "SELECT google_drive_file_id, google_drive_folder_id, file_hash 
              FROM backup_manifest
              WHERE student_id = ? AND document_id = ?
              ORDER BY backed_up_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $studentId, $documentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'google_drive_file_id' => $row['google_drive_file_id'],
            'google_drive_folder_id' => $row['google_drive_folder_id'],
            'file_hash' => $row['file_hash']
        ];
    }
    
    $stmt->close();
    return null;
}

/**
 * Get student folder from manifest
 */
function getStudentFolderFromManifest($studentId, $mainFolderId = null) {
    global $conn;
    
    if (!$mainFolderId) {
        return null;
    }
    
    $query = "SELECT DISTINCT google_drive_folder_id 
              FROM backup_manifest 
              WHERE student_id = ? AND google_drive_folder_id IS NOT NULL
              ORDER BY backed_up_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $existingFolderId = $row['google_drive_folder_id'];
        $stmt->close();
        
        // Verify folder is in correct main folder
        $accessToken = getValidAccessToken();
        if ($accessToken && $existingFolderId) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files/' . $existingFolderId . '?fields=parents',
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['parents']) && in_array($mainFolderId, $data['parents'])) {
                    return $existingFolderId;
                }
            }
        }
    } else {
        $stmt->close();
    }
    
    return null;
}

/**
 * Record Google Drive upload
 */
function recordGoogleDriveUpload($backupLogId, $studentId, $documentId, $driveFileId, $folderId, $filePath, $fileSize, $backupType = 'new') {
    global $conn;
    
    $fileHash = calculateFileHash($filePath);
    
    if ($backupType === 'repaired') {
        $backupType = 'modified';
    }
    
    $query = "INSERT INTO backup_manifest 
              (backup_log_id, student_id, document_id, google_drive_file_id, 
               google_drive_folder_id, file_hash, file_path, file_size, backup_type, backed_up_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE
              google_drive_file_id = VALUES(google_drive_file_id),
              google_drive_folder_id = VALUES(google_drive_folder_id),
              file_hash = VALUES(file_hash),
              file_path = VALUES(file_path),
              file_size = VALUES(file_size),
              backup_type = VALUES(backup_type),
              last_synced_at = NOW()";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiisssssi", 
        $backupLogId, $studentId, $documentId, $driveFileId, $folderId, 
        $fileHash, $filePath, $fileSize, $backupType
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get current school year
 */
function getCurrentSchoolYear() {
    global $conn;
    
    $query = "SELECT school_year FROM school_years WHERE is_active = 1 LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['school_year'];
    }
    
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    return "$currentYear-$nextYear";
}

/**
 * Run Auto-Sync Process - WITH DUPLICATE PREVENTION
 */
function runAutoSync() {
    global $conn;
    
    error_log("=== AUTO-SYNC PROCESS STARTED ===");
    
    // Check if auto-sync is enabled
    if (!isAutoSyncEnabled()) {
        error_log("Auto-sync is disabled");
        return [
            'status' => 'skipped',
            'reason' => 'Auto-sync is disabled'
        ];
    }
    
    // Check if Google Drive is connected
    if (!isGoogleDriveConnected()) {
        error_log("Google Drive not connected");
        return [
            'status' => 'skipped',
            'reason' => 'Google Drive is not connected'
        ];
    }
    
    // Verify token is valid
    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        error_log("No valid access token");
        return [
            'status' => 'error',
            'message' => 'Failed to get valid access token'
        ];
    }
    
    // Create backup log entry
    $stmt = $conn->prepare("INSERT INTO backup_logs 
                           (backup_type, storage_type, status, created_at, is_incremental) 
                           VALUES ('automatic', 'cloud', 'in_progress', NOW(), 1)");
    $stmt->execute();
    $backupLogId = $conn->insert_id;
    $stmt->close();
    
    try {
        $schoolYear = getCurrentSchoolYear();
        
        // Create main folder
        $mainFolderName = "IBACMI Backup {$schoolYear}";
        $mainFolderId = createGoogleDriveFolder($mainFolderName);
        
        if (!$mainFolderId) {
            throw new Exception('Failed to create main backup folder');
        }
        
        error_log("Main folder ID: $mainFolderId");
        
        // Get all students with documents
        $query = "SELECT DISTINCT s.id, s.student_id, s.first_name, s.last_name, s.middle_name
                  FROM students s
                  INNER JOIN student_documents sd ON s.id = sd.student_id
                  WHERE sd.is_submitted = 1 
                  AND sd.file_path IS NOT NULL 
                  AND sd.file_path != ''
                  ORDER BY s.last_name, s.first_name";
        
        $result = $conn->query($query);
        
        $studentCount = 0;
        $fileCount = 0;
        $filesUploaded = 0;
        $filesUpdated = 0;
        $filesSkipped = 0;
        
        while ($student = $result->fetch_assoc()) {
            $studentFolderName = "{$student['last_name']}, {$student['first_name']} {$student['student_id']}";
            
            // Get or create student folder
            $studentFolderId = getStudentFolderFromManifest($student['id'], $mainFolderId);
            
            if (!$studentFolderId) {
                $studentFolderId = createGoogleDriveFolder($studentFolderName, $mainFolderId);
                if (!$studentFolderId) {
                    error_log("Failed to create folder: $studentFolderName");
                    continue;
                }
                error_log("✅ Created student folder: $studentFolderName");
            } else {
                error_log("✓ Using existing folder for: $studentFolderName");
            }
            
            // Get documents
            $docsQuery = "SELECT sd.id, sd.file_path, sd.original_filename, sd.file_size, dt.doc_name
                         FROM student_documents sd
                         INNER JOIN document_types dt ON sd.document_type_id = dt.id
                         WHERE sd.student_id = ? 
                         AND sd.is_submitted = 1
                         AND sd.file_path IS NOT NULL 
                         AND sd.file_path != ''
                         ORDER BY dt.doc_name";
            $docsStmt = $conn->prepare($docsQuery);
            $docsStmt->bind_param("i", $student['id']);
            $docsStmt->execute();
            $docsResult = $docsStmt->get_result();
            
            $studentFileCount = 0;
            
            while ($doc = $docsResult->fetch_assoc()) {
                $localPath = findLocalFile($doc['file_path']);
                
                if (!$localPath || !file_exists($localPath)) {
                    error_log("❌ File not found: {$doc['file_path']}");
                    continue;
                }
                
                $fileExtension = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
                $fileName = $doc['doc_name'] . '.' . $fileExtension;
                
                // **Check if already backed up in database**
                $existingBackup = isDocumentBackedUp($student['id'], $doc['id']);
                
                if ($existingBackup) {
                    $currentHash = calculateFileHash($localPath);
                    
                    if ($currentHash === $existingBackup['file_hash']) {
                        $filesSkipped++;
                        error_log("⏭ Skipped (unchanged): $fileName");
                        continue;
                    }
                    
                    // File changed - update it
                    error_log("🔄 File changed, updating: $fileName");
                    $updated = updateGoogleDriveFile($existingBackup['google_drive_file_id'], $localPath, $fileName);
                    
                    if ($updated) {
                        recordGoogleDriveUpload($backupLogId, $student['id'], $doc['id'], 
                            $existingBackup['google_drive_file_id'], $studentFolderId, 
                            $localPath, $doc['file_size'], 'modified');
                        $filesUpdated++;
                        $studentFileCount++;
                        error_log("✅ Updated: $fileName");
                    } else {
                        error_log("❌ Update failed: $fileName");
                    }
                } else {
                    // **New file - upload with duplicate check**
                    error_log("📤 New file, uploading: $fileName");
                    $driveFileId = uploadFileToGoogleDrive($localPath, $fileName, $studentFolderId);
                    
                    if ($driveFileId) {
                        recordGoogleDriveUpload($backupLogId, $student['id'], $doc['id'], 
                            $driveFileId, $studentFolderId, $localPath, 
                            $doc['file_size'], 'new');
                        $filesUploaded++;
                        $studentFileCount++;
                        error_log("✅ Uploaded: $fileName");
                    } else {
                        error_log("❌ Upload failed: $fileName");
                    }
                }
            }
            
            $docsStmt->close();
            
            if ($studentFileCount > 0) {
                $studentCount++;
                $fileCount += $studentFileCount;
            }
        }
        
        // Update backup log
        $updateStmt = $conn->prepare("UPDATE backup_logs SET 
            student_count = ?, file_count = ?, 
            files_uploaded = ?, files_updated = ?, files_skipped = ?,
            backup_path = ?, status = 'success', completed_at = NOW()
            WHERE id = ?");
        $backupPath = $mainFolderName;
        $updateStmt->bind_param("iiiissi", $studentCount, $fileCount, 
            $filesUploaded, $filesUpdated, $filesSkipped, $backupPath, $backupLogId);
        $updateStmt->execute();
        $updateStmt->close();
        
        error_log("=== AUTO-SYNC COMPLETED ===");
        error_log("Students: $studentCount, Files: $fileCount");
        error_log("Uploaded: $filesUploaded, Updated: $filesUpdated, Skipped: $filesSkipped");
        
        return [
            'status' => 'success',
            'synced' => $fileCount,
            'uploaded' => $filesUploaded,
            'updated' => $filesUpdated,
            'skipped' => $filesSkipped,
            'student_count' => $studentCount
        ];
        
    } catch (Exception $e) {
        error_log("Auto-sync failed: " . $e->getMessage());
        
        $stmt = $conn->prepare("UPDATE backup_logs SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?");
        $errorMsg = $e->getMessage();
        $stmt->bind_param("si", $errorMsg, $backupLogId);
        $stmt->execute();
        $stmt->close();
        
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// ✅ Execute auto-sync
error_log("=== AUTO-SYNC PROCESSOR CALLED ===");
error_log("Trigger type: " . ($_POST['trigger'] ?? 'automatic'));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

$result = runAutoSync();

error_log("=== AUTO-SYNC RESULT ===");
error_log("Status: " . ($result['status'] ?? 'unknown'));
error_log("Details: " . json_encode($result));

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result);

error_log("=== AUTO-SYNC PROCESSOR COMPLETE ===");
?>