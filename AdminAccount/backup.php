<?php
// Suppress ALL output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Configure error logging ONLY to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/backup_errors.log');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 0);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once '../db_connect.php';

// Load Google Drive configuration
$config = require_once '../config/google_drive_config.php';
define('GOOGLE_CLIENT_ID', $config['client_id']);
define('GOOGLE_CLIENT_SECRET', $config['client_secret']);
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');

date_default_timezone_set('Asia/Manila');
session_start();

// Check if this is being included from staff or admin context
$isStaffView = defined('IS_STAFF_VIEW') && IS_STAFF_VIEW;
$staffInfo = $isStaffView && defined('STAFF_INFO') ? STAFF_INFO : array();

// Check if this is an AJAX request
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

/**
 * Initialize necessary system tables
 */
function initializeSystemTables($conn) {
    $tables = [];
    
    $createSettingsTable = "
        CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_name` varchar(100) NOT NULL,
            `setting_value` text,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_name` (`setting_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $conn->query($createSettingsTable);
        $tables[] = 'system_settings';
    } catch (Exception $e) {
        error_log("Error creating system_settings: " . $e->getMessage());
    }
    
    // ✅ ADD: Set auto_sync_status to 'disabled' by default
    $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_name, setting_value, updated_at)
                           VALUES ('auto_sync_status', 'disabled', NOW())");
    $stmt->execute();
    $stmt->close();
    
    return $tables;
}

initializeSystemTables($conn);

/**
 * Initialize local backup manifest table
 */
function initializeLocalBackupManifest($conn) {
    $createTable = "
        CREATE TABLE IF NOT EXISTS `local_backup_manifest` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `backup_log_id` int(11) NOT NULL,
            `student_id` int(11) NOT NULL,
            `document_id` int(11) NOT NULL,
            `local_file_path` varchar(500) NOT NULL,
            `file_hash` varchar(64) NOT NULL,
            `file_size` bigint(20) DEFAULT NULL,
            `backup_type` enum('new','modified') DEFAULT 'new',
            `backed_up_at` datetime NOT NULL,
            `last_synced_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_student_document` (`student_id`, `document_id`),
            KEY `backup_log_id` (`backup_log_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $conn->query($createTable);
        error_log("✓ local_backup_manifest table created/verified");
        return true;
    } catch (Exception $e) {
        error_log("Error creating local_backup_manifest: " . $e->getMessage());
        return false;
    }
}

// Call it after initializeSystemTables
initializeLocalBackupManifest($conn);

/**
 * Get stored tokens - SHARED SYSTEM
 */
function getStoredTokens() {
    global $conn;
    
    $tokens = [
        'access_token' => null,
        'refresh_token' => null
    ];
    
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
 * Check if Google Drive is connected
 */
function isGoogleDriveConnected() {
    global $conn;
    
    $query = "SELECT setting_value FROM system_settings 
              WHERE setting_name = 'google_drive_connected'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'] === '1';
    }
    
    return false;
}

/**
 * Store tokens persistently
 */
function storeTokensPersistent($accessToken, $refreshToken = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                                VALUES ('google_drive_access_token', ?, NOW())
                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("ss", $accessToken, $accessToken);
        $stmt->execute();
        $stmt->close();
        
        if ($refreshToken) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                                   VALUES ('google_drive_refresh_token', ?, NOW())
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("ss", $refreshToken, $refreshToken);
            $stmt->execute();
            $stmt->close();
        }
        
        markGoogleDriveConnected();
        
        error_log("✅ Shared tokens stored successfully");
        return true;
    } catch (Exception $e) {
        error_log("Error storing tokens: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark Google Drive as connected
 */
function markGoogleDriveConnected() {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                           VALUES ('google_drive_connected', '1', NOW())
                           ON DUPLICATE KEY UPDATE setting_value = '1', updated_at = NOW()");
    $stmt->execute();
    $stmt->close();
    
    return true;
}

/**
 * Get auto-sync status
 */
function getAutoSyncStatus() {
    global $conn;
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return 'disabled';
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
 * Test Google Drive connection
 */
function testGoogleDriveConnection() {
    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/about?fields=user',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/**
 * Send JSON response
 */
function sendJsonResponse($data) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Get valid access token (with auto-refresh)
 */
function getValidAccessToken() {
    $tokens = getStoredTokens();
    
    if (empty($tokens['access_token'])) {
        return null;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/about?fields=user',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $tokens['access_token']
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return $tokens['access_token'];
    }
    
    if ($httpCode === 401 && !empty($tokens['refresh_token'])) {
        $newAccessToken = refreshAccessToken();
        if ($newAccessToken) {
            return $newAccessToken;
        }
    }
    
    return null;
}

/**
 * Refresh access token using refresh token
 */
function refreshAccessToken() {
    global $conn;
    
    $tokens = getStoredTokens();
    if (empty($tokens['refresh_token'])) {
        error_log("No refresh token available");
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            storeTokensPersistent($data['access_token']);
            error_log("Access token refreshed successfully");
            return $data['access_token'];
        }
    }
    
    error_log("Token refresh failed. HTTP Code: $httpCode, Response: $response");
    return null;
}

/**
 * Clear Google Drive connection
 */
function clearGoogleDriveConnection() {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM system_settings WHERE setting_name LIKE 'google_drive_%'");
    $stmt->execute();
    $stmt->close();
    
    error_log("✅ Cleared Google Drive connection");
    
    return true;
}

/**
 * Get or create student folder ID from backup_manifest - FIXED VERSION
 */
function getStudentFolderFromManifest($studentId, $mainFolderId = null) {
    global $conn;
    
    // First, check if we have a folder for this student in the manifest
    $query = "SELECT DISTINCT google_drive_folder_id 
              FROM backup_manifest 
              WHERE student_id = ? 
              AND google_drive_folder_id IS NOT NULL
              ORDER BY backed_up_at DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $existingFolderId = $row['google_drive_folder_id'];
        $stmt->close();
        
        // Verify folder still exists on Google Drive
        $accessToken = getValidAccessToken();
        if ($accessToken && $existingFolderId) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files/' . $existingFolderId . '?fields=id,name,parents,trashed',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                if (!isset($data['trashed']) || $data['trashed'] === false) {
                    error_log("✅ Reusing existing student folder: $existingFolderId");
                    
                    // ✅ FIX: If mainFolderId is provided, verify/move folder to correct parent
                    if ($mainFolderId && isset($data['parents'])) {
                        $currentParents = $data['parents'];
                        
                        // Check if already in correct parent
                        if (!in_array($mainFolderId, $currentParents)) {
                            error_log("🔄 Moving folder to correct parent: $mainFolderId");
                            moveFileToFolder($existingFolderId, $mainFolderId, $currentParents[0]);
                        }
                    }
                    
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
 * Move file/folder to different parent in Google Drive
 */
function moveFileToFolder($fileId, $newParentId, $oldParentId = null) {
    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        return false;
    }
    
    $url = 'https://www.googleapis.com/drive/v3/files/' . $fileId;
    
    // Build query parameters
    $params = [];
    if ($newParentId) {
        $params['addParents'] = $newParentId;
    }
    if ($oldParentId) {
        $params['removeParents'] = $oldParentId;
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        error_log("✅ Moved file/folder successfully");
        return true;
    }
    
    error_log("❌ Failed to move file/folder - HTTP: $httpCode");
    return false;
}

/**
 * Create or get Google Drive folder - IMPROVED VERSION
 */
function createGoogleDriveFolder($folderName, $parentFolderId = null) {
    error_log("📁 Creating/finding folder: $folderName" . ($parentFolderId ? " in parent: $parentFolderId" : " at root"));
    
    $accessToken = getValidAccessToken();
    if (!$accessToken) {
        error_log("❌ No valid access token available");
        return null;
    }
    
    // ✅ IMPROVED: Search for folder by name AND parent
    $query = "name='" . addslashes($folderName) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";
    if ($parentFolderId) {
        $query .= " and '" . $parentFolderId . "' in parents";
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name,parents)',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if (!empty($data['files'])) {
            // ✅ If multiple folders found, prefer one with correct parent
            foreach ($data['files'] as $file) {
                if ($parentFolderId) {
                    if (isset($file['parents']) && in_array($parentFolderId, $file['parents'])) {
                        $folderId = $file['id'];
                        error_log("✅ Found existing folder with correct parent: $folderName (ID: $folderId)");
                        return $folderId;
                    }
                } else {
                    // No parent specified, use first result
                    $folderId = $file['id'];
                    error_log("✅ Found existing folder: $folderName (ID: $folderId)");
                    return $folderId;
                }
            }
            
            // If we're here and parentFolderId is set, we found folders but none with correct parent
            if ($parentFolderId && !empty($data['files'])) {
                // Move first found folder to correct parent
                $existingFolder = $data['files'][0];
                $folderId = $existingFolder['id'];
                $oldParent = $existingFolder['parents'][0] ?? null;
                
                error_log("🔄 Found folder in wrong location, moving to correct parent");
                if (moveFileToFolder($folderId, $parentFolderId, $oldParent)) {
                    error_log("✅ Moved existing folder: $folderName (ID: $folderId)");
                    return $folderId;
                }
            }
        }
    }
    
    // Folder doesn't exist, create it
    error_log("📤 Creating new folder: $folderName");
    
    $metadata = ['name' => $folderName, 'mimeType' => 'application/vnd.google-apps.folder'];
    if ($parentFolderId) {
        $metadata['parents'] = [$parentFolderId];
    }
    
    $metadataJson = json_encode($metadata);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $metadataJson,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $folderId = $data['id'] ?? null;
        
        if ($folderId) {
            error_log("✅ Created folder successfully: $folderName (ID: $folderId)");
            return $folderId;
        }
    }
    
    error_log("❌ Failed to create folder: $folderName");
    return null;
}

/**
 * Check if file already exists in Google Drive folder
 */
function checkFileExistsInDrive($fileName, $folderId) {
    $accessToken = getValidAccessToken();
    if (!$accessToken || !$folderId) {
        return null;
    }
    
    $escapedFileName = str_replace("'", "\\'", $fileName);
    $query = "name='{$escapedFileName}' and '{$folderId}' in parents and trashed=false";
    
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
            return [
                'id' => $data['files'][0]['id'],
                'md5' => $data['files'][0]['md5Checksum'] ?? null
            ];
        }
    }
    
    return null;
}

/**
 * Upload file to Google Drive
 */
function uploadFileToGoogleDrive($filePath, $fileName, $parentFolderId = null) {
    $accessToken = getValidAccessToken();
    if (!$accessToken || !file_exists($filePath)) {
        error_log("❌ Upload failed: No token or file missing - $fileName");
        return null;
    }
    
    // Check if file exists first
    if ($parentFolderId) {
        $existingFile = checkFileExistsInDrive($fileName, $parentFolderId);
        if ($existingFile) {
            error_log("⚠️ File already exists: $fileName (ID: {$existingFile['id']}) - Skipping upload");
            return $existingFile['id'];
        }
    }
    
    error_log("📤 Uploading new file: $fileName");
    
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
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
    
    error_log("❌ Upload failed: $fileName (HTTP: $httpCode)");
    return null;
}

/**
 * Calculate file hash for change detection
 */
function calculateFileHash($filePath) {
    return md5_file($filePath);
}

/**
 * Check if document is already backed up (Google Drive)
 * This checks the backup_manifest table which tracks Google Drive backups
 */
function isDocumentBackedUp($studentId, $documentId) {
    global $conn;
    
    $query = "SELECT google_drive_file_id, google_drive_folder_id, file_hash 
              FROM backup_manifest
              WHERE student_id = ? 
              AND document_id = ?
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
 * ========================================================================
 * DESTINATION-AWARE DUPLICATE PREVENTION FUNCTIONS
 * ========================================================================
 * These functions track backups by destination (Google Drive, Local, Manual, Auto-Sync)
 */

/**
 * Check if document is already backed up to Google Drive
 * Checks backup_manifest table (Google Drive backups only)
 */
function isDocumentBackedUpToGoogleDrive($studentId, $documentId) {
    return isDocumentBackedUp($studentId, $documentId);
}

/**
 * Check if document is already backed up locally
 * Checks local_backup_manifest table (Local storage backups only)
 */
function isDocumentBackedUpLocally($studentId, $documentId) {
    global $conn;
    
    $query = "SELECT local_file_path, file_hash 
              FROM local_backup_manifest
              WHERE student_id = ? 
              AND document_id = ?
              ORDER BY backed_up_at DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $studentId, $documentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'local_file_path' => $row['local_file_path'],
            'file_hash' => $row['file_hash']
        ];
    }
    
    $stmt->close();
    return null;
}

/**
 * Get pending files count for specific destination
 * 
 * @param string $destination - 'google_drive', 'local', 'manual', 'auto_sync'
 * @param int|null $schoolYearId - Filter by school year (optional)
 * @return int Number of pending files for this destination
 */
function getPendingFilesCountByDestination($destination = 'google_drive', $schoolYearId = null) {
    global $conn;
    
    $manifestTable = '';
    $joinCondition = '';
    
    // Determine which manifest table to check based on destination
    switch ($destination) {
        case 'google_drive':
            $manifestTable = 'backup_manifest';
            $joinCondition = "LEFT JOIN backup_manifest bm ON sd.student_id = bm.student_id AND sd.id = bm.document_id";
            break;
            
        case 'local':
        case 'manual':
            $manifestTable = 'local_backup_manifest';
            $joinCondition = "LEFT JOIN local_backup_manifest lbm ON sd.student_id = lbm.student_id AND sd.id = lbm.document_id";
            break;
            
        case 'auto_sync':
            // Auto-sync uses backup_manifest (Google Drive)
            $manifestTable = 'backup_manifest';
            $joinCondition = "LEFT JOIN backup_manifest bm ON sd.student_id = bm.student_id AND sd.id = bm.document_id";
            break;
            
        default:
            return 0;
    }
    
    // Build query
    $query = "SELECT COUNT(DISTINCT sd.id) as pending
              FROM student_documents sd
              INNER JOIN students s ON sd.student_id = s.id
              $joinCondition
              WHERE sd.is_submitted = 1 
              AND sd.file_path IS NOT NULL 
              AND sd.file_path != ''";
    
    // Add condition to check if NOT in manifest (pending)
    if ($destination === 'google_drive' || $destination === 'auto_sync') {
        $query .= " AND bm.id IS NULL";
    } else {
        $query .= " AND lbm.id IS NULL";
    }
    
    // Optional: Filter by school year
    if ($schoolYearId !== null) {
        $query .= " AND s.school_year_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($schoolYearId !== null) {
        $stmt->bind_param("i", $schoolYearId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['pending'] ?? 0);
}

/**
 * Get detailed list of pending files for specific destination
 * 
 * @param string $destination - 'google_drive', 'local', 'manual', 'auto_sync'
 * @param int|null $schoolYearId - Filter by school year (optional)
 * @return array List of pending documents with details
 */
function getPendingFilesByDestination($destination = 'google_drive', $schoolYearId = null) {
    global $conn;
    
    $joinCondition = '';
    $whereCondition = '';
    
    // Determine which manifest table to check based on destination
    switch ($destination) {
        case 'google_drive':
        case 'auto_sync':
            $joinCondition = "LEFT JOIN backup_manifest bm ON sd.student_id = bm.student_id AND sd.id = bm.document_id";
            $whereCondition = "AND bm.id IS NULL";
            break;
            
        case 'local':
        case 'manual':
            $joinCondition = "LEFT JOIN local_backup_manifest lbm ON sd.student_id = lbm.student_id AND sd.id = lbm.document_id";
            $whereCondition = "AND lbm.id IS NULL";
            break;
            
        default:
            return [];
    }
    
    // Build query
    $query = "SELECT 
                sd.id as document_id,
                sd.student_id,
                sd.file_path,
                sd.original_filename,
                sd.file_size,
                s.student_id as student_number,
                s.first_name,
                s.last_name,
                dt.doc_name as document_type
              FROM student_documents sd
              INNER JOIN students s ON sd.student_id = s.id
              INNER JOIN document_types dt ON sd.document_type_id = dt.id
              $joinCondition
              WHERE sd.is_submitted = 1 
              AND sd.file_path IS NOT NULL 
              AND sd.file_path != ''
              $whereCondition";
    
    // Optional: Filter by school year
    if ($schoolYearId !== null) {
        $query .= " AND s.school_year_id = ?";
    }
    
    $query .= " ORDER BY s.last_name, s.first_name, dt.doc_name";
    
    $stmt = $conn->prepare($query);
    
    if ($schoolYearId !== null) {
        $stmt->bind_param("i", $schoolYearId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pendingFiles = [];
    while ($row = $result->fetch_assoc()) {
        $pendingFiles[] = $row;
    }
    
    $stmt->close();
    
    return $pendingFiles;
}

/**
 * Record Google Drive upload in manifest
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
        $backupLogId, 
        $studentId, 
        $documentId, 
        $driveFileId, 
        $folderId, 
        $fileHash, 
        $filePath, 
        $fileSize, 
        $backupType
    );
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Failed to record backup in manifest: " . $stmt->error);
    }
    $stmt->close();
    
    return $result;
}

/**
 * Get configured backup directory - FIXED
 */
function getBackupDirectory() {
    global $conn;
    
    try {
        // ✅ Try to get from system_settings
        $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'backup_directory'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $directory = trim($row['setting_value']);
            
            if (!empty($directory)) {
                // ✅ Normalize path separators for Windows
                $directory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
                
                // ✅ ADDED: Expand relative paths
                if (!preg_match('/^[a-zA-Z]:[\\\\\/]/', $directory)) {
                    // It's a relative path, make it absolute
                    $directory = realpath(__DIR__ . DIRECTORY_SEPARATOR . $directory);
                    if ($directory === false) {
                        error_log("⚠️ Invalid backup directory path, using default");
                        $directory = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
                    }
                }
                
                error_log("📂 Using saved backup directory: $directory");
                return $directory;
            }
        }
    } catch (Exception $e) {
        error_log("❌ Error retrieving backup directory: " . $e->getMessage());
    }
    
    // ✅ Default fallback
    $defaultDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
    error_log("📂 Using default backup directory: $defaultDir");
    return $defaultDir;
}

/**
 * Find local file with multiple path attempts - IMPROVED
 */
function findLocalFile($filePath) {
    // ✅ Clean the path first
    $filePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filePath);
    $filePath = trim($filePath);
    
    error_log("🔍 Searching for file: $filePath");
    
    // ✅ Try absolute path first
    if (file_exists($filePath)) {
        error_log("✅ Found at absolute path: $filePath");
        return $filePath;
    }
    
    // ✅ Define base paths to try
    $basePaths = [
        __DIR__ . DIRECTORY_SEPARATOR . '..',                    // Parent of AdminAccount
        __DIR__,                                                 // AdminAccount folder
        'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'ibacmi',
        dirname(__DIR__),                                        // PHP's parent directory
    ];
    
    // ✅ Remove leading slashes/backslashes from filePath for concatenation
    $cleanPath = ltrim($filePath, '\\/');
    
    foreach ($basePaths as $basePath) {
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $cleanPath;
        $fullPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $fullPath);
        
        // ✅ Try to resolve real path
        $realPath = realpath($fullPath);
        
        if ($realPath && file_exists($realPath)) {
            error_log("✅ Found at: $realPath");
            return $realPath;
        }
        
        // ✅ Also try without realpath (for newly created files)
        if (file_exists($fullPath)) {
            error_log("✅ Found at: $fullPath");
            return $fullPath;
        }
    }
    
    error_log("❌ File not found after checking all paths");
    return null;
}

/**
 * Create backup endpoint - FIXED ERROR HANDLING
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    error_log("=== CREATE BACKUP STARTED ===");
    error_log("POST data: " . print_r($_POST, true));
    
    $storageType = $_POST['storageType'] ?? 'local';
    $backupName = $_POST['backupName'] ?? 'IBACMI_Backup';
    $schoolYear = $_POST['schoolYear'] ?? getCurrentSchoolYear();
    
    error_log("Backup Type: $storageType");
    error_log("Backup Name: $backupName");
    error_log("School Year: $schoolYear");
    
    // ✅ Validate school year
    if (empty($schoolYear)) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'School year is required'
        ]);
    }
    
    // Create backup log entry
    try {
        $stmt = $conn->prepare("INSERT INTO backup_logs 
                               (backup_type, storage_type, status, created_at, is_incremental) 
                               VALUES ('manual', ?, 'in_progress', NOW(), 1)");
        $stmt->bind_param("s", $storageType);
        $stmt->execute();
        $backupLogId = $conn->insert_id;
        $stmt->close();
        
        if (!$backupLogId) {
            throw new Exception("Failed to create backup log entry");
        }
        
        error_log("✅ Created backup log ID: $backupLogId");
        
    } catch (Exception $e) {
        error_log("❌ Error creating backup log: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Failed to initialize backup: ' . $e->getMessage()
        ]);
    }
    
    try {
        if ($storageType === 'local') {
            // === LOCAL BACKUP - FIXED ===
            error_log("Starting local backup with unchanged detection...");
            
            // ✅ GET AND VALIDATE BACKUP DIRECTORY
            $backupDir = getBackupDirectory();
            error_log("📂 Using backup directory: $backupDir");
            
            // ✅ Create directory if it doesn't exist
            if (!file_exists($backupDir)) {
                error_log("📁 Creating backup directory: $backupDir");
                if (!@mkdir($backupDir, 0755, true)) {
                    $error = error_get_last();
                    throw new Exception("Failed to create backup directory: $backupDir. Error: " . ($error['message'] ?? 'Unknown error'));
                }
                error_log("✅ Created backup directory");
            }
            
            // ✅ Verify directory is writable
            if (!is_writable($backupDir)) {
                throw new Exception("Backup directory is not writable: $backupDir. Please check folder permissions.");
            }
            
            // ✅ Main backup folder name
            $mainBackupFolder = $backupDir . DIRECTORY_SEPARATOR . 'IBACMI_Backup_' . str_replace('-', '_', $schoolYear);
            
            error_log("📁 Main backup folder: $mainBackupFolder");
            
            // Create main folder if it doesn't exist
            if (!file_exists($mainBackupFolder)) {
                if (!@mkdir($mainBackupFolder, 0755, true)) {
                    $error = error_get_last();
                    throw new Exception("Failed to create main backup folder: $mainBackupFolder. Error: " . ($error['message'] ?? 'Unknown error'));
                }
                error_log("✅ Created new main backup folder");
            } else {
                error_log("✅ Using existing main backup folder");
            }
            
            // ✅ GET STUDENTS WITH DOCUMENTS
            $query = "SELECT DISTINCT s.id, s.student_id, s.first_name, s.last_name, s.middle_name
                      FROM students s
                      INNER JOIN student_documents sd ON s.id = sd.student_id
                      WHERE sd.is_submitted = 1
                      AND sd.file_path IS NOT NULL 
                      AND sd.file_path != ''
                      ORDER BY s.last_name, s.first_name";
            
            $result = $conn->query($query);
            
            if (!$result) {
                throw new Exception("Database query failed: " . $conn->error);
            }
            
            if ($result->num_rows === 0) {
                throw new Exception("No student documents found to backup");
            }
            
            $studentCount = 0;
            $fileCount = 0;
            $filesAdded = 0;
            $filesUpdated = 0;
            $filesSkipped = 0;
            $totalSize = 0;
            $errors = [];
            
            error_log("Found " . $result->num_rows . " students for local backup");
            
            // ✅ PROCESS EACH STUDENT
            while ($student = $result->fetch_assoc()) {
                $studentFolderName = "{$student['last_name']}, {$student['first_name']} {$student['student_id']}";
                // ✅ Sanitize folder name (remove invalid characters)
                $studentFolderName = preg_replace('/[<>:"|?*]/', '', $studentFolderName);
                $studentFolderPath = $mainBackupFolder . DIRECTORY_SEPARATOR . $studentFolderName;
                
                error_log("Processing student: $studentFolderName");
                
                // Create student folder if it doesn't exist
                if (!file_exists($studentFolderPath)) {
                    if (!@mkdir($studentFolderPath, 0755, true)) {
                        $error = error_get_last();
                        error_log("❌ Failed to create folder: $studentFolderPath - " . ($error['message'] ?? 'Unknown'));
                        $errors[] = "Failed to create folder for {$studentFolderName}";
                        continue;
                    }
                    error_log("✅ Created student folder");
                }
                
                // Get documents for this student
                $docsQuery = "SELECT sd.id, sd.file_path, sd.original_filename, dt.doc_name, sd.file_size
                             FROM student_documents sd
                             INNER JOIN document_types dt ON sd.document_type_id = dt.id
                             WHERE sd.student_id = ? 
                             AND sd.is_submitted = 1
                             AND sd.file_path IS NOT NULL 
                             AND sd.file_path != ''";
                $docsStmt = $conn->prepare($docsQuery);
                $docsStmt->bind_param("i", $student['id']);
                $docsStmt->execute();
                $docsResult = $docsStmt->get_result();
                
                $studentFileCount = 0;
                
                while ($doc = $docsResult->fetch_assoc()) {
                    // ✅ Find source file
                    $sourcePath = findLocalFile($doc['file_path']);
                    
                    if (!$sourcePath || !file_exists($sourcePath)) {
                        error_log("❌ Source file not found: {$doc['file_path']}");
                        $errors[] = "File not found: {$doc['original_filename']} for {$studentFolderName}";
                        continue;
                    }
                    
                    // Create destination filename
                    $fileExtension = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
                    // ✅ Sanitize filename
                    $fileName = preg_replace('/[<>:"|?*]/', '', $doc['doc_name']) . '.' . $fileExtension;
                    $destinationPath = $studentFolderPath . DIRECTORY_SEPARATOR . $fileName;
                    
                    // Calculate current file hash
                    $currentHash = calculateFileHash($sourcePath);
                    $fileSize = filesize($sourcePath);
                    
                    // Check if file already exists in backup
                    $existingBackup = isDocumentBackedUpLocally($student['id'], $doc['id']);
                    
                    if ($existingBackup) {
                        // File exists - check if changed
                        if ($currentHash === $existingBackup['file_hash']) {
                            $filesSkipped++;
                            error_log("⏭ Skipped (unchanged): $fileName");
                            continue;
                        } else {
                            // File changed - update it
                            if (@copy($sourcePath, $destinationPath)) {
                                updateLocalBackupManifest($backupLogId, $student['id'], $doc['id'], 
                                    $destinationPath, $currentHash, $fileSize, 'modified');
                                $filesUpdated++;
                                $studentFileCount++;
                                $totalSize += $fileSize;
                                error_log("🔄 Updated: $fileName");
                            } else {
                                $error = error_get_last();
                                error_log("❌ Failed to update: $fileName - " . ($error['message'] ?? 'Unknown'));
                                $errors[] = "Failed to update: $fileName";
                            }
                        }
                    } else {
                        // New file - copy it
                        if (@copy($sourcePath, $destinationPath)) {
                            recordLocalBackupManifest($backupLogId, $student['id'], $doc['id'], 
                                $destinationPath, $currentHash, $fileSize, 'new');
                            $filesAdded++;
                            $studentFileCount++;
                            $totalSize += $fileSize;
                            error_log("➕ Added: $fileName");
                        } else {
                            $error = error_get_last();
                            error_log("❌ Failed to copy: $fileName - " . ($error['message'] ?? 'Unknown'));
                            $errors[] = "Failed to copy: $fileName";
                        }
                    }
                }
                
                $docsStmt->close();
                
                if ($studentFileCount > 0) {
                    $studentCount++;
                    $fileCount += $studentFileCount;
                }
            }
            
            error_log("===== LOCAL BACKUP COMPLETE =====");
            error_log("Students processed: $studentCount");
            error_log("Files added: $filesAdded");
            error_log("Files updated: $filesUpdated");
            error_log("Files skipped: $filesSkipped");
            error_log("Total files: $fileCount");
            
            // ✅ Update backup log
            $backupPath = 'IBACMI_Backup_' . str_replace('-', '_', $schoolYear);
            $updateStmt = $conn->prepare("UPDATE backup_logs SET 
                student_count = ?, file_count = ?, backup_size = ?,
                files_uploaded = ?, files_updated = ?, files_skipped = ?,
                backup_path = ?, status = 'success', completed_at = NOW()
                WHERE id = ?");
            $updateStmt->bind_param("iiiiiisi", $studentCount, $fileCount, $totalSize, 
                $filesAdded, $filesUpdated, $filesSkipped, $backupPath, $backupLogId);
            $updateStmt->execute();
            $updateStmt->close();
            
            sendJsonResponse([
                'status' => 'success',
                'message' => 'Local backup completed successfully',
                'data' => [
                    'student_count' => $studentCount,
                    'file_count' => $fileCount,
                    'files_added' => $filesAdded,
                    'files_updated' => $filesUpdated,
                    'files_skipped' => $filesSkipped,
                    'size' => $totalSize,
                    'backup_path' => $mainBackupFolder,
                    'errors' => $errors
                ]
            ]);
        } else {
            // === GOOGLE DRIVE BACKUP - FIXED DUPLICATE PREVENTION ===
            if (!isGoogleDriveConnected()) {
                throw new Exception('Google Drive is not connected. Please connect first.');
            }
            
            if (!testGoogleDriveConnection()) {
                throw new Exception('Google Drive connection test failed. Please reconnect.');
            }
            
            // Create main folder: "IBACMI Backup 2025-2026"
            $mainFolderName = "IBACMI Backup {$schoolYear}";
            error_log("Creating main folder: $mainFolderName");
            $mainFolderId = createGoogleDriveFolder($mainFolderName);
            
            if (!$mainFolderId) {
                throw new Exception('Failed to create main backup folder on Google Drive');
            }
            
            error_log("✅ Main folder created: ID = $mainFolderId");
            
            // Get all students with documents
            $query = "SELECT DISTINCT s.id, s.student_id, s.first_name, s.last_name, s.middle_name
                      FROM students s
                      INNER JOIN student_documents sd ON s.id = sd.student_id
                      WHERE sd.is_submitted = 1 
                      AND sd.file_path IS NOT NULL 
                      AND sd.file_path != ''
                      ORDER BY s.last_name, s.first_name";
            
            $result = $conn->query($query);
            
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            
            $studentCount = 0;
            $fileCount = 0;
            $filesUploaded = 0;
            $filesUpdated = 0;
            $filesSkipped = 0;
            $errors = [];
            
            error_log("Found " . $result->num_rows . " students with documents");
            
            // Process each student
            while ($student = $result->fetch_assoc()) {
                // Create student folder name: "LastName, FirstName StudentID"
                $studentFolderName = "{$student['last_name']}, {$student['first_name']} {$student['student_id']}";
                error_log("Processing: $studentFolderName");
                
                // ✅ FIX: Get existing folder from manifest (reuse existing folders)
                $studentFolderId = getStudentFolderFromManifest($student['id']);
                
                if (!$studentFolderId) {
                    // Create new folder INSIDE main folder
                    $studentFolderId = createGoogleDriveFolder($studentFolderName, $mainFolderId);
                    if (!$studentFolderId) {
                        error_log("❌ Failed to create folder: $studentFolderName");
                        $errors[] = "Failed to create folder for {$studentFolderName}";
                        continue;
                    }
                    error_log("✅ Created NEW student folder ID: $studentFolderId inside main folder: $mainFolderId");
                } else {
                    error_log("✅ Using EXISTING student folder ID: $studentFolderId");
                }
                
                // Get documents for this student
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
                        $errors[] = "File not found: {$doc['original_filename']}";
                        continue;
                    }
                    
                    // Create descriptive filename: "DocumentType.ext"
                    $fileExtension = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
                    $fileName = $doc['doc_name'] . '.' . $fileExtension;
                    
                    // ✅ FIX: Check MANIFEST first (database record of what's already uploaded)
                    $existingBackup = isDocumentBackedUp($student['id'], $doc['id']);
                    
                    $currentHash = calculateFileHash($localPath);
                    
                    if ($existingBackup) {
                        // File is ALREADY in manifest - check if it changed
                        
                        if ($currentHash === $existingBackup['file_hash']) {
                            // ✅ File UNCHANGED - skip completely
                            $filesSkipped++;
                            error_log("⏭ Skipped (unchanged): $fileName");
                            continue;
                        }
                        
                        // ✅ File CHANGED - update existing file on Google Drive
                        error_log("🔄 File changed, updating: $fileName");
                        
                        $updated = updateGoogleDriveFile(
                            $existingBackup['google_drive_file_id'], 
                            $localPath, 
                            $fileName
                        );
                        
                        if ($updated) {
                            // Update manifest with new hash
                            recordGoogleDriveUpload(
                                $backupLogId, 
                                $student['id'], 
                                $doc['id'], 
                                $existingBackup['google_drive_file_id'], 
                                $studentFolderId, 
                                $localPath, 
                                $doc['file_size'], 
                                'modified'
                            );
                            $filesUpdated++;
                            $studentFileCount++;
                            error_log("✅ Updated: $fileName");
                        } else {
                            error_log("❌ Update failed: $fileName");
                            $errors[] = "Failed to update: $fileName";
                        }
                        
                    } else {
                        // ✅ NEW FILE - not in manifest, upload to Google Drive
                        error_log("📤 New file, uploading: $fileName");
                        
                        $driveFileId = uploadFileToGoogleDrive($localPath, $fileName, $studentFolderId);
                        
                        if ($driveFileId) {
                            // Record in manifest
                            recordGoogleDriveUpload(
                                $backupLogId, 
                                $student['id'], 
                                $doc['id'], 
                                $driveFileId, 
                                $studentFolderId, 
                                $localPath, 
                                $doc['file_size'], 
                                'new'
                            );
                            $filesUploaded++;
                            $studentFileCount++;
                            error_log("✅ Uploaded new file: $fileName (ID: $driveFileId)");
                        } else {
                            error_log("❌ Upload failed: $fileName");
                            $errors[] = "Failed to upload: $fileName";
                        }
                    }
                }
                
                $docsStmt->close();
                
                if ($studentFileCount > 0) {
                    $studentCount++;
                    $fileCount += $studentFileCount;
                    error_log("✅ Student complete: $studentFileCount files");
                }
            }
            
            // Update backup log
            $updateStmt = $conn->prepare("UPDATE backup_logs SET 
                student_count = ?, file_count = ?, 
                files_uploaded = ?, files_updated = ?, files_skipped = ?,
                backup_path = ?, status = 'success', completed_at = NOW()
                WHERE id = ?");
            $updateStmt->bind_param("iiiissi", $studentCount, $fileCount, 
                $filesUploaded, $filesUpdated, $filesSkipped, $backupPath, $backupLogId);
            $updateStmt->execute();
            $updateStmt->close();
            
            sendJsonResponse([
                'status' => 'success',
                'message' => 'Cloud backup completed successfully',
                'data' => [
                    'student_count' => $studentCount,
                    'file_count' => $fileCount,
                    'files_uploaded' => $filesUploaded,
                    'files_updated' => $filesUpdated,
                    'files_skipped' => $filesSkipped,
                    'folder_name' => $backupPath,
                    'errors' => $errors
                ]
            ]);
            
        }
    } catch (Exception $e) {
        error_log("❌ Backup failed: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $stmt = $conn->prepare("UPDATE backup_logs SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?");
        $errorMsg = $e->getMessage();
        $stmt->bind_param("si", $errorMsg, $backupLogId);
        $stmt->execute();
        $stmt->close();
        
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Download backup file
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $backupId = (int)$_GET['id'];
    
    $query = "SELECT backup_path, filename FROM backup_logs WHERE id = ? AND status = 'success'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $backupId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $backup = $result->fetch_assoc();
        $filePath = __DIR__ . '/' . $backup['backup_path'];
        
        if (file_exists($filePath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Backup file not found']);
        }
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Backup not found']);
    }
    $stmt->close();
}

/**
 * Trigger instant auto-sync after document upload
 * Called from document upload handlers
 */
function triggerInstantAutoSync($studentId = null, $documentId = null) {
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
    if (!isGoogleDriveConnected()) {
        return false;
    }
    
    error_log("🚀 Instant auto-sync triggered for student: $studentId, document: $documentId");
    
    // Execute auto-sync processor asynchronously
    $autoSyncUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . 
                   str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']) . 
                   'auto_sync_processor.php';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $autoSyncUrl,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 500, // Very short timeout - fire and forget
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_POSTFIELDS => http_build_query([
            'trigger' => 'instant',
            'student_id' => $studentId,
            'document_id' => $documentId
        ])
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

// Add this endpoint to manually test auto-sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_auto_sync') {
    triggerInstantAutoSync();
    sendJsonResponse(['status' => 'success', 'message' => 'Auto-sync triggered']);
}

/**
 * Save archival settings
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_archival_settings') {
    $timing = $_POST['timing'] ?? '1_year';
    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
    
    try {
        // Save to archival_settings table (the correct table used by archival_api.php)
        $checkQuery = "SELECT id FROM archival_settings LIMIT 1";
        $checkResult = $conn->query($checkQuery);
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE archival_settings SET 
                timing_option = ?, 
                auto_archival_enabled = ?,
                updated_at = NOW()
                WHERE id = 1");
            $stmt->bind_param("si", $timing, $enabled);
        } else {
            // Insert new settings
            $stmt = $conn->prepare("INSERT INTO archival_settings 
                (timing_option, auto_archival_enabled, storage_destination) 
                VALUES (?, ?, 'google_drive')");
            $stmt->bind_param("si", $timing, $enabled);
        }
        
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Archival settings saved: timing=$timing, enabled=$enabled");
        
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Archival settings saved successfully'
        ]);
    } catch (Exception $e) {
        error_log("Error saving archival settings: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Failed to save archival settings: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get archival settings
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_archival_settings') {
    $timing = '1_year'; // default
    $enabled = 1; // default
    
    // Read from archival_settings table (the correct table used by archival_api.php)
    $query = "SELECT timing_option, auto_archival_enabled FROM archival_settings LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $timing = $row['timing_option'];
        $enabled = (int)$row['auto_archival_enabled'];
    }
    
    sendJsonResponse([
        'status' => 'success',
        'data' => [
            'timing' => $timing,
            'enabled' => $enabled
        ]
    ]);
}

/**
 * Get archival statistics - calls archival_api.php functions
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_archival_statistics') {
    require_once 'archival_api.php';
    
    try {
        $stats = getArchivalStatistics();
        sendJsonResponse([
            'status' => 'success',
            'data' => $stats
        ]);
    } catch (Exception $e) {
        error_log("Error getting archival statistics: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Failed to load archival statistics: ' . $e->getMessage()
        ]);
    }
}

/**
 * Run manual archival - calls archival_api.php functions
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_auto_archival') {
    require_once 'archival_api.php';
    
    try {
        $result = runAutoArchival();
        sendJsonResponse($result);
    } catch (Exception $e) {
        error_log("Error running auto-archival: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Failed to run archival: ' . $e->getMessage()
        ]);
    }
}

/**
 * Save backup directory path - NEW ENDPOINT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_backup_directory') {
    error_log("=== SAVE BACKUP DIRECTORY REQUESTED ===");
    error_log("POST data: " . print_r($_POST, true));
    
    $directory = trim($_POST['directory'] ?? '');
    
    if (empty($directory)) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Backup directory path is required'
        ]);
    }
    
    try {
        // ✅ Normalize path separators
        $directory = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
        
        // ✅ If it's a relative path, make it absolute
        if (!preg_match('/^[a-zA-Z]:[\\\\\/]/', $directory)) {
            $directory = __DIR__ . DIRECTORY_SEPARATOR . $directory;
        }
        
        error_log("📂 Attempting to save directory: $directory");
        
        // ✅ Validate directory exists or can be created
        if (!file_exists($directory)) {
            error_log("📁 Directory doesn't exist, attempting to create: $directory");
            
            if (!@mkdir($directory, 0755, true)) {
                $error = error_get_last();
                throw new Exception("Cannot create directory: $directory. Error: " . ($error['message'] ?? 'Permission denied'));
            }
            
            error_log("✅ Created directory successfully");
        }
        
        // ✅ Check if directory is writable
        if (!is_writable($directory)) {
            throw new Exception("Directory is not writable: $directory. Please check folder permissions.");
        }
        
        // ✅ Save to database
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                               VALUES ('backup_directory', ?, NOW())
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("ss", $directory, $directory);
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }
        
        $stmt->close();
        
        error_log("✅ Backup directory saved successfully: $directory");
        
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Backup directory saved successfully',
            'data' => [
                'directory' => $directory
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error saving backup directory: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get current backup directory - NEW ENDPOINT
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_backup_directory') {
    try {
        $directory = getBackupDirectory();
        
        sendJsonResponse([
            'status' => 'success',
            'data' => [
                'directory' => $directory,
                'exists' => file_exists($directory),
                'writable' => file_exists($directory) && is_writable($directory)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error getting backup directory: " . $e->getMessage());
        
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Record local backup in manifest
 */
function recordLocalBackupManifest($backupLogId, $studentId, $documentId, $localFilePath, $fileHash, $fileSize, $backupType = 'new') {
    global $conn;
    
    $query = "INSERT INTO local_backup_manifest 
              (backup_log_id, student_id, document_id, local_file_path, 
               file_hash, file_size, backup_type, backed_up_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE
              backup_log_id = VALUES(backup_log_id),
              local_file_path = VALUES(local_file_path),
              file_hash = VALUES(file_hash),
              file_size = VALUES(file_size),
              backup_type = VALUES(backup_type),
              last_synced_at = NOW()";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiissis", 
        $backupLogId, 
        $studentId, 
        $documentId, 
        $localFilePath, 
        $fileHash, 
        $fileSize, 
        $backupType
    );
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Failed to record local backup in manifest: " . $stmt->error);
    }
    $stmt->close();
    
    return $result;
}

/**
 * Update local backup manifest for modified files
 */
function updateLocalBackupManifest($backupLogId, $studentId, $documentId, $localFilePath, $fileHash, $fileSize, $backupType = 'modified') {
    global $conn;
    
    $query = "UPDATE local_backup_manifest SET
              backup_log_id = ?,
              local_file_path = ?,
              file_hash = ?,
              file_size = ?,
              backup_type = ?,
              last_synced_at = NOW()
              WHERE student_id = ? AND document_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issisii", 
        $backupLogId,
        $localFilePath, 
        $fileHash, 
        $fileSize, 
        $backupType,
        $studentId, 
        $documentId
    );
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Failed to update local backup manifest: " . $stmt->error);
    }
    $stmt->close();
    
    return $result;
}

/**
 * Update Google Drive file (for modified files)
 */
function updateGoogleDriveFile($fileId, $localPath, $fileName) {
    $accessToken = getValidAccessToken();
    if (!$accessToken || !file_exists($localPath)) {
        error_log("❌ Update failed: No token or file missing");
        return false;
    }
    
    error_log("🔄 Updating existing file: $fileName (ID: $fileId)");
    
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 300
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        error_log("✅ File updated successfully: $fileName");
        return true;
    }
    
    error_log("❌ Update failed: $fileName (HTTP: $httpCode)");
    return false;
}

/**
 * Get recent backups for display
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_recent_backups') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $query = "SELECT 
                    id,
                    backup_type,
                    storage_type,
                    backup_path,
                    student_count,
                    file_count,
                    backup_size as total_size,
                    files_uploaded,
                    files_updated,
                    files_skipped,
                    status,
                    error_message,
                    created_at,
                    completed_at
                  FROM backup_logs 
                  WHERE status IN ('success', 'failed')
                  ORDER BY created_at DESC 
                  LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $backups = [];
        while ($row = $result->fetch_assoc()) {
            $backups[] = [
                'id' => $row['id'],
                'backup_name' => $row['backup_path'] ?: 'Backup-' . date('Y-m-d', strtotime($row['created_at'])),
                'backup_type' => ucfirst($row['backup_type']),
                'storage_type' => $row['storage_type'],
                'student_count' => $row['student_count'] ?: 0,
                'file_count' => $row['file_count'] ?: 0,
                'total_size' => $row['total_size'] ?: 0,
                'files_uploaded' => $row['files_uploaded'] ?: 0,
                'files_updated' => $row['files_updated'] ?: 0,
                'files_skipped' => $row['files_skipped'] ?: 0,
                'status' => $row['status'],
                'error_message' => $row['error_message'],
                'created_at' => $row['created_at'],
                'completed_at' => $row['completed_at']
            ];
        }
        
        $stmt->close();
        
        sendJsonResponse([
            'status' => 'success',
            'data' => $backups
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error getting recent backups: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get backup statistics for dashboard
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_backup_statistics') {
    try {
        // Total number of successful backups
        $query = "SELECT COUNT(*) as total FROM backup_logs WHERE status = 'success'";
        $result = $conn->query($query);
        $totalBackups = $result ? $result->fetch_assoc()['total'] : 0;
        
        // Total unique students backed up (Google Drive)
        $query = "SELECT COUNT(DISTINCT student_id) as total FROM backup_manifest";
        $result = $conn->query($query);
        $totalStudentsGoogleDrive = $result ? $result->fetch_assoc()['total'] : 0;
        
        // Total unique students backed up (Local)
        $query = "SELECT COUNT(DISTINCT student_id) as total FROM local_backup_manifest";
        $result = $conn->query($query);
        $totalStudentsLocal = $result ? $result->fetch_assoc()['total'] : 0;
        
        // Use the higher count for display
        $totalStudents = max($totalStudentsGoogleDrive, $totalStudentsLocal);
        
        // Total files backed up (Google Drive)
        $query = "SELECT COUNT(*) as total FROM backup_manifest";
        $result = $conn->query($query);
        $totalFilesGoogleDrive = $result ? $result->fetch_assoc()['total'] : 0;
        
        // Total files backed up (Local)
        $query = "SELECT COUNT(*) as total FROM local_backup_manifest";
        $result = $conn->query($query);
        $totalFilesLocal = $result ? $result->fetch_assoc()['total'] : 0;
        
        // Combined total (may include duplicates across destinations)
        $totalFiles = $totalFilesGoogleDrive + $totalFilesLocal;
        
        // Total storage used (sum of file sizes from both sources)
        $query = "SELECT SUM(file_size) as total FROM backup_manifest";
        $result = $conn->query($query);
        $storageGoogleDrive = $result ? ($result->fetch_assoc()['total'] ?: 0) : 0;
        
        $query = "SELECT SUM(file_size) as total FROM local_backup_manifest";
        $result = $conn->query($query);
        $storageLocal = $result ? ($result->fetch_assoc()['total'] ?: 0) : 0;
        
        $totalStorage = $storageGoogleDrive + $storageLocal;
        
        // ✅ DESTINATION-AWARE PENDING FILES
        // Get pending count based on requested destination or default
        $destination = $_GET['destination'] ?? 'google_drive';
        $pendingFiles = getPendingFilesCountByDestination($destination);
        
        // Also get pending counts for each destination separately
        $pendingGoogleDrive = getPendingFilesCountByDestination('google_drive');
        $pendingLocal = getPendingFilesCountByDestination('local');
        
        // Format storage size
        $storageFormatted = formatBytes($totalStorage);
        
        sendJsonResponse([
            'status' => 'success',
            'data' => [
                'total_backups' => $totalBackups,
                'total_students' => $totalStudents,
                'total_files' => $totalFiles,
                'total_storage' => $totalStorage,
                'storage_formatted' => $storageFormatted,
                'pending_files' => $pendingFiles, // Pending for requested destination
                'pending_google_drive' => $pendingGoogleDrive,
                'pending_local' => $pendingLocal,
                'destination' => $destination // Echo back which destination was queried
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error getting backup statistics: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * ✅ NEW ENDPOINT: Get pending files by destination
 * Returns list of files that need to be backed up to a specific destination
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_pending_files_by_destination') {
    try {
        $destination = $_GET['destination'] ?? 'google_drive';
        $schoolYearId = isset($_GET['school_year_id']) ? (int)$_GET['school_year_id'] : null;
        
        // Validate destination
        $validDestinations = ['google_drive', 'local', 'manual', 'auto_sync'];
        if (!in_array($destination, $validDestinations)) {
            throw new Exception("Invalid destination: $destination");
        }
        
        // Get pending files list
        $pendingFiles = getPendingFilesByDestination($destination, $schoolYearId);
        $pendingCount = count($pendingFiles);
        
        sendJsonResponse([
            'status' => 'success',
            'data' => [
                'destination' => $destination,
                'pending_count' => $pendingCount,
                'pending_files' => $pendingFiles,
                'school_year_id' => $schoolYearId
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error getting pending files by destination: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Helper function to format bytes into human-readable format
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), $precision) . ' ' . $sizes[$i];
}

/**
 * Get available school years for backup
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_school_years') {
    try {
        $query = "SELECT id, school_year, is_active FROM school_years ORDER BY school_year DESC";
        $result = $conn->query($query);
        
        $schoolYears = [];
        $activeYear = null;
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $schoolYears[] = [
                    'id' => $row['id'],
                    'school_year' => $row['school_year'],
                    'is_active' => (int)$row['is_active']
                ];
                
                if ($row['is_active'] == 1) {
                    $activeYear = $row['school_year'];
                }
            }
        }
        
        sendJsonResponse([
            'status' => 'success',
            'data' => [
                'school_years' => $schoolYears,
                'active_year' => $activeYear
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error getting school years: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Check backup prerequisites
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_backup_prerequisites') {
    try {
        $issues = [];
        $canBackup = true;
        
        // Check 1: School year exists
        $schoolYear = getCurrentSchoolYear();
        if (empty($schoolYear)) {
            $issues[] = 'No active school year found';
            $canBackup = false;
        }
        
        // Check 2: Documents exist
        $query = "SELECT COUNT(*) as count FROM student_documents WHERE is_submitted = 1";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $documentCount = $row['count'];
        
        if ($documentCount == 0) {
            $issues[] = 'No submitted documents found';
            $canBackup = false;
        }
        
        // Check 3: Backup directory (for local)
        $backupDir = getBackupDirectory();
        if (!file_exists($backupDir)) {
            if (!@mkdir($backupDir, 0755, true)) {
                $issues[] = 'Cannot create backup directory';
            }
        }
        
        if (!is_writable($backupDir)) {
            $issues[] = 'Backup directory is not writable';
        }
        
        sendJsonResponse([
            'status' => 'success',
            'data' => [
                'can_backup' => $canBackup && empty($issues),
                'school_year' => $schoolYear,
                'document_count' => $documentCount,
                'backup_directory' => $backupDir,
                'issues' => $issues
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error checking prerequisites: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Check Google Drive connection status
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_connection') {
    try {
        error_log("==========================================");
        error_log("🔍 CHECK_CONNECTION ENDPOINT CALLED");
        error_log("==========================================");
        
        // ✅ SIMPLIFIED: Just check if we have valid tokens in database
        $tokens = getStoredTokens();
        $hasAccessToken = !empty($tokens['access_token']);
        $hasRefreshToken = !empty($tokens['refresh_token']);
        $isConnected = isGoogleDriveConnected();
        
        error_log("1️⃣ Database connection flag (google_drive_connected): " . ($isConnected ? 'YES (1)' : 'NO (0)'));
        error_log("2️⃣ Has access token: " . ($hasAccessToken ? 'YES (' . strlen($tokens['access_token']) . ' chars)' : 'NO'));
        error_log("3️⃣ Has refresh token: " . ($hasRefreshToken ? 'YES (' . strlen($tokens['refresh_token']) . ' chars)' : 'NO'));
        
        // ✅ If we have both tokens AND connection flag is set, consider it connected
        // Don't do API test on every check - only when explicitly needed
        $actuallyConnected = $isConnected && $hasAccessToken && $hasRefreshToken;
        
        error_log("4️⃣ FINAL DECISION: " . ($actuallyConnected ? '✅ CONNECTED' : '❌ NOT CONNECTED'));
        
        $userEmail = null;
        
        // Only try to get user email if connected (optional, non-blocking)
        if ($actuallyConnected) {
            error_log("5️⃣ Attempting to fetch user email (optional, non-blocking)...");
            // Try to get user email but don't fail if it doesn't work
            try {
                $accessToken = $tokens['access_token'];
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => 'https://www.googleapis.com/drive/v3/about?fields=user',
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3, // Very short timeout - this is optional
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    $userEmail = $data['user']['emailAddress'] ?? null;
                    error_log("   ✅ Got user email: $userEmail");
                } else {
                    error_log("   ℹ️ Could not get user email (HTTP $httpCode) - but connection is still valid");
                    $userEmail = "Connected"; // Fallback display
                }
            } catch (Exception $e) {
                error_log("   ℹ️ Email fetch failed: " . $e->getMessage() . " - but connection is still valid");
                $userEmail = "Connected"; // Fallback display
            }
        } else {
            error_log("5️⃣ Skipping email fetch - not connected");
        }
        
        // Get auto-sync status
        $syncStatus = getAutoSyncStatus();
        error_log("6️⃣ Auto-sync status: $syncStatus");
        
        $response = [
            'status' => 'success',
            'data' => [
                'connected' => $actuallyConnected,
                'user_email' => $userEmail,
                'sync_status' => $syncStatus
            ]
        ];
        
        error_log("7️⃣ Sending response: " . json_encode($response));
        error_log("==========================================");
        
        sendJsonResponse($response);
        
    } catch (Exception $e) {
        error_log("❌ EXCEPTION in check_connection: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get Google OAuth authorization URL - FIXED to use config redirect URI
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_auth_url') {
    try {
        // ✅ Use redirect URI from config file
        $redirectUri = $config['redirect_uri'];
        
        error_log("📍 Auth URL redirect URI: $redirectUri");
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);
        
        sendJsonResponse([
            'status' => 'success',
            'data' => ['auth_url' => $authUrl]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error generating auth URL: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Handle OAuth callback from Google - FIXED VERSION
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'oauth_callback') {
    error_log("=== OAUTH CALLBACK RECEIVED ===");
    error_log("Full URL: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    error_log("GET data: " . print_r($_GET, true));
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // ✅ FIX 1: Handle Google's error responses properly (including 403 access_denied)
    if (isset($_GET['error'])) {
        $errorType = $_GET['error'];
        $errorDesc = $_GET['error_description'] ?? 'Authorization denied';
        
        error_log("❌ OAuth error from Google: $errorType - $errorDesc");
        
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Authorization Failed</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
                .error { color: #dc3545; margin: 20px 0; font-size: 16px; }
                .instructions { background: #fff3cd; padding: 20px; margin: 20px auto; max-width: 600px; border-radius: 8px; text-align: left; border: 1px solid #ffc107; }
                .instructions h3 { margin-top: 0; color: #856404; }
                .instructions ol { text-align: left; line-height: 1.8; }
                .instructions code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
                button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; }
                button:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h2>❌ Authorization Failed</h2>
            <p class="error"><?php echo htmlspecialchars($errorDesc); ?></p>
            
            <?php if ($errorType === 'access_denied' && (strpos($errorDesc, 'verification') !== false || strpos($errorDesc, '403') !== false)): ?>
            <div class="instructions">
                <h3>⚠️ Google Verification Required</h3>
                <p><strong>Your app needs to add test users:</strong></p>
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank">Google Cloud Console → OAuth Consent Screen</a></li>
                    <li>Scroll down to the <strong>"Test users"</strong> section</li>
                    <li>Click <strong>"+ ADD USERS"</strong> button</li>
                    <li>Add the Gmail address you want to connect with</li>
                    <li>Click <strong>"SAVE"</strong></li>
                    <li>Wait a few seconds, then try connecting again</li>
                </ol>
                <p><strong>Note:</strong> Make sure your OAuth consent screen is set to "Testing" mode, not "Production".</p>
            </div>
            <?php endif; ?>
            
            <p><button onclick="window.close()">Close Window</button></p>
            
            <script>
                // Send error to parent window
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        status: 'error',
                        message: <?php echo json_encode($errorDesc); ?>,
                        errorType: <?php echo json_encode($errorType); ?>
                    }, '*');
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    // ✅ FIX 2: Validate authorization code
    if (!isset($_GET['code']) || empty(trim($_GET['code']))) {
        error_log("❌ No authorization code in callback");
        
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Authorization Error</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
                .error { color: #dc3545; }
                button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
            </style>
        </head>
        <body>
            <h2>❌ Authorization Error</h2>
            <p class="error">No authorization code received from Google.</p>
            <p>Please try again or contact support.</p>
            <p><button onclick="window.close()">Close Window</button></p>
            <script>
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        status: 'error',
                        message: 'No authorization code received'
                    }, '*');
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    $authCode = trim($_GET['code']);
    error_log("✅ Authorization code received: " . substr($authCode, 0, 30) . "...");
    
    try {
        // ✅ CRITICAL FIX: Use redirect URI from config file (must match Google Console exactly)
        $redirectUri = $config['redirect_uri'];
        
        error_log("📍 Using redirect URI from config: $redirectUri");
        error_log("📍 Client ID: " . substr(GOOGLE_CLIENT_ID, 0, 30) . "...");
        error_log("📍 Client Secret: " . substr(GOOGLE_CLIENT_SECRET, 0, 10) . "...");
        
        // ✅ FIX 4: Exchange code for tokens with proper error handling
        $postData = [
            'code' => $authCode,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        error_log("📤 Exchanging authorization code for tokens...");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => GOOGLE_TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        error_log("📥 Token exchange HTTP code: $httpCode");
        
        if ($curlError) {
            throw new Exception("Network error: $curlError (Code: $curlErrno)");
        }
        
        if (empty($response)) {
            throw new Exception("Empty response from Google token endpoint");
        }
        
        error_log("📥 Token response: " . substr($response, 0, 200) . "...");
        
        // ✅ FIX 5: Parse and validate response
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        // Check for error in response
        if (isset($data['error'])) {
            $errorMsg = $data['error_description'] ?? $data['error'];
            throw new Exception("Google API error: $errorMsg");
        }
        
        if ($httpCode !== 200) {
            $errorMsg = isset($data['error_description']) ? $data['error_description'] : 
                       (isset($data['error']) ? $data['error'] : "HTTP $httpCode");
            throw new Exception("Token exchange failed: $errorMsg");
        }
        
        if (!isset($data['access_token'])) {
            throw new Exception("No access token in response");
        }
        
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;
        
        error_log("✅ Access token received: " . substr($accessToken, 0, 30) . "...");
        if ($refreshToken) {
            error_log("✅ Refresh token received: " . substr($refreshToken, 0, 30) . "...");
        } else {
            error_log("⚠️ No refresh token received (may occur if reconnecting)");
        }
        
        // ✅ FIX 6: Store tokens with error handling
        if (!storeTokensPersistent($accessToken, $refreshToken)) {
            throw new Exception("Failed to save tokens to database");
        }
        
        error_log("✅ Tokens saved successfully to database");
        
        // ✅ FIX 7: Verify connection works
        $testConnection = testGoogleDriveConnection();
        if (!$testConnection) {
            error_log("⚠️ Warning: Token saved but connection test failed");
        }
        
        // Success response
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Authorization Success</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 40px; 
                    text-align: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                .success-icon { font-size: 64px; margin: 20px 0; animation: bounce 1s; }
                .message { font-size: 18px; margin: 20px 0; }
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-20px); }
                    60% { transform: translateY(-10px); }
                }
            </style>
        </head>
        <body>
            <div class="success-icon">✅</div>
            <h2>Authorization Successful!</h2>
            <p class="message">Google Drive has been connected successfully.</p>
            <p>This window will close automatically...</p>
            
            <script>
                // Send success message to parent window
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        status: 'success',
                        message: 'Google Drive connected successfully'
                    }, '*');
                }
                
                // Close window after delay
                setTimeout(function() {
                    window.close();
                }, 1500);
            </script>
        </body>
        </html>
        <?php
        
    } catch (Exception $e) {
        error_log("❌ OAuth callback exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Connection Error</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
                .error { color: #dc3545; margin: 20px 0; padding: 20px; background: #f8d7da; border-radius: 5px; border: 1px solid #f5c6cb; }
                button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 20px; }
            </style>
        </head>
        <body>
            <h2>❌ Connection Failed</h2>
            <div class="error">
                <strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            
            <p>Please try again or contact support if the problem persists.</p>
            <button onclick="window.close()">Close Window</button>
            
            <script>
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        status: 'error',
                        message: <?php echo json_encode($e->getMessage()); ?>
                    }, '*');
                }
            </script>
        </body>
        </html>
        <?php
    }
    
    exit;
}

/**
 * Toggle auto-sync status
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_sync') {
    $status = $_POST['status'] ?? 'disabled';
    
    // Validate status
    if (!in_array($status, ['enabled', 'disabled', 'paused'])) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Invalid sync status'
        ]);
    }
    
    // Check if Google Drive is connected when enabling
    if ($status === 'enabled' && !isGoogleDriveConnected()) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Please connect Google Drive first'
        ]);
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                               VALUES ('auto_sync_status', ?, NOW())
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("ss", $status, $status);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Auto-sync status updated to: $status");
        
        sendJsonResponse([
            'status' => 'success',
            'message' => "Auto-sync " . ($status === 'enabled' ? 'enabled' : ($status === 'paused' ? 'paused' : 'disabled')),
            'data' => ['sync_status' => $status]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error toggling sync: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Failed to update sync status: ' . $e->getMessage()
        ]);
    }
}

/**
 * Disconnect Google Drive
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disconnect_google') {
    try {
        // Clear all Google Drive related settings
        clearGoogleDriveConnection();
        
        error_log("✅ Google Drive disconnected successfully");
        
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Google Drive disconnected successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error disconnecting Google Drive: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Failed to disconnect: ' . $e->getMessage()
        ]);
    }
}

/**
 * Exchange authorization code for tokens (AJAX endpoint)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'exchange_code') {
    error_log("=== EXCHANGE CODE ENDPOINT ===");
    
    $authCode = $_POST['code'] ?? '';
    
    if (empty($authCode)) {
        sendJsonResponse(['status' => 'error', 'message' => 'Authorization code is required']);
    }
    
    try {
        $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                      '://' . $_SERVER['HTTP_HOST'] . 
                      str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']) . 
                      'backup.php';
        
        $postData = [
            'code' => $authCode,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => GOOGLE_TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Token exchange failed. HTTP Code: $httpCode");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception("No access token received");
        }
        
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;
        
        if (storeTokensPersistent($accessToken, $refreshToken)) {
            sendJsonResponse([
                'status' => 'success',
                'message' => 'Google Drive connected successfully'
            ]);
        } else {
            throw new Exception("Failed to store tokens");
        }
        
    } catch (Exception $e) {
        error_log("❌ Token exchange error: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get recent auto-sync activity
 * Checks if auto-sync is currently running or recently completed
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_recent_sync_activity') {
    try {
        // Check if auto-sync is enabled
        $syncStatus = getAutoSyncStatus();
        
        if ($syncStatus !== 'enabled') {
            sendJsonResponse([
                'status' => 'success',
                'data' => [
                    'is_syncing' => false,
                    'last_sync_time' => null,
                    'sync_count' => 0
                ]
            ]);
        }
        
        // Check for recent backup logs (within last 30 seconds)
        $query = "SELECT 
                    id,
                    status,
                    created_at,
                    completed_at,
                    file_count,
                    files_uploaded,
                    files_updated
                  FROM backup_logs 
                  WHERE backup_type = 'automatic' 
                  AND storage_type = 'cloud'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
                  ORDER BY created_at DESC 
                  LIMIT 1";
        
        $result = $conn->query($query);
        
        $isSyncing = false;
        $lastSyncTime = null;
        $syncCount = 0;
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Check if backup is in progress (status = 'in_progress')
            if ($row['status'] === 'in_progress') {
                $isSyncing = true;
                $lastSyncTime = $row['created_at'];
            } else if ($row['status'] === 'success') {
                // Recently completed (within last 30 seconds)
                $completedTime = strtotime($row['completed_at']);
                $now = time();
                
                // Show indicator for 5 seconds after completion
                if (($now - $completedTime) < 5) {
                    $isSyncing = true;
                }
                
                $lastSyncTime = $row['completed_at'];
                $syncCount = $row['files_uploaded'] + $row['files_updated'];
            }
        }
        
        sendJsonResponse([
            'status' => 'success',
            'data' => [
                'is_syncing' => $isSyncing,
                'last_sync_time' => $lastSyncTime,
                'sync_count' => $syncCount
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error checking auto-sync activity: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}