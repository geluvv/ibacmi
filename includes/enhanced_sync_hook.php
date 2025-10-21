<?php
class EnhancedAutoSync {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Sync student folder to Google Drive with proper structure
     */
    public function syncStudentToGoogleDrive($student, $accessToken, $schoolYear, $studentFolderName) {
        try {
            // Create main school year folder if not exists
            $mainFolderId = $this->createOrGetFolder("IBACMI {$schoolYear}", null, $accessToken);
            
            // Create student folder inside main folder
            $studentFolderId = $this->createOrGetFolder($studentFolderName, $mainFolderId, $accessToken);
            
            // Get all documents for this student
            $docQuery = "SELECT * FROM student_documents 
                        WHERE student_id = ? AND is_submitted = 1 
                        AND file_path IS NOT NULL AND file_path != ''";
            $docStmt = $this->conn->prepare($docQuery);
            $docStmt->bind_param("i", $student['id']);
            $docStmt->execute();
            $docResult = $docStmt->get_result();
            
            $uploadedCount = 0;
            $failedCount = 0;
            $uploadedFiles = [];
            
            while ($doc = $docResult->fetch_assoc()) {
                $filePath = '../' . ltrim($doc['file_path'], '/');
                
                if (file_exists($filePath)) {
                    $fileName = $doc['original_filename'] ?: basename($doc['file_path']);
                    
                    $uploadResult = $this->uploadFileToGoogleDrive(
                        $filePath, 
                        $fileName, 
                        $studentFolderId, 
                        $accessToken
                    );
                    
                    if ($uploadResult['success']) {
                        $uploadedCount++;
                        $uploadedFiles[] = $fileName;
                        
                        // Log successful sync
                        $this->logSync(
                            $student['id'], 
                            $fileName, 
                            'success', 
                            "IBACMI {$schoolYear}/{$studentFolderName}",
                            $uploadResult['file_id']
                        );
                    } else {
                        $failedCount++;
                        
                        // Log failed sync
                        $this->logSync(
                            $student['id'], 
                            $fileName, 
                            'failed', 
                            "IBACMI {$schoolYear}/{$studentFolderName}",
                            null,
                            $uploadResult['error']
                        );
                    }
                } else {
                    $failedCount++;
                    error_log("File not found: {$filePath}");
                }
            }
            
            $docStmt->close();
            
            return [
                'success' => $uploadedCount > 0,
                'uploaded_count' => $uploadedCount,
                'failed_count' => $failedCount,
                'uploaded_files' => $uploadedFiles,
                'student_folder' => $studentFolderName,
                'folder_path' => "IBACMI {$schoolYear}/{$studentFolderName}"
            ];
            
        } catch (Exception $e) {
            error_log("Sync error for student {$student['id']}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'uploaded_count' => 0
            ];
        }
    }
    
    /**
     * Create or get existing folder in Google Drive
     */
    private function createOrGetFolder($folderName, $parentId = null, $accessToken) {
        // First, check if folder already exists
        $searchQuery = "name='" . addslashes($folderName) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";
        if ($parentId) {
            $searchQuery .= " and '{$parentId}' in parents";
        }
        
        $searchUrl = "https://www.googleapis.com/drive/v3/files?" . http_build_query([
            'q' => $searchQuery,
            'fields' => 'files(id, name)'
        ]);
        
        $searchResponse = $this->makeGoogleApiRequest($searchUrl, 'GET', null, $accessToken);
        
        if ($searchResponse && isset($searchResponse['files']) && count($searchResponse['files']) > 0) {
            return $searchResponse['files'][0]['id'];
        }
        
        // Create new folder
        $folderMetadata = [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ];
        
        if ($parentId) {
            $folderMetadata['parents'] = [$parentId];
        }
        
        $createUrl = 'https://www.googleapis.com/drive/v3/files';
        $response = $this->makeGoogleApiRequest($createUrl, 'POST', json_encode($folderMetadata), $accessToken);
        
        if ($response && isset($response['id'])) {
            return $response['id'];
        }
        
        throw new Exception("Failed to create folder: {$folderName}");
    }
    
    /**
     * Upload file to Google Drive
     */
    private function uploadFileToGoogleDrive($filePath, $fileName, $parentFolderId, $accessToken) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }
            
            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            
            // Check if file already exists in the folder
            $searchQuery = "name='" . addslashes($fileName) . "' and '{$parentFolderId}' in parents and trashed=false";
            $searchUrl = "https://www.googleapis.com/drive/v3/files?" . http_build_query([
                'q' => $searchQuery,
                'fields' => 'files(id, name)'
            ]);
            
            $searchResponse = $this->makeGoogleApiRequest($searchUrl, 'GET', null, $accessToken);
            
            // If file exists, update it instead of creating duplicate
            if ($searchResponse && isset($searchResponse['files']) && count($searchResponse['files']) > 0) {
                $existingFileId = $searchResponse['files'][0]['id'];
                return $this->updateFileInGoogleDrive($filePath, $existingFileId, $accessToken);
            }
            
            // Create new file
            $metadata = [
                'name' => $fileName,
                'parents' => [$parentFolderId]
            ];
            
            $boundary = uniqid();
            $delimiter = '-------' . $boundary;
            $close_delim = "\r\n--{$delimiter}--";
            
            $body = "--{$delimiter}\r\n";
            $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
            $body .= json_encode($metadata) . "\r\n";
            $body .= "--{$delimiter}\r\n";
            $body .= "Content-Type: {$mimeType}\r\n\r\n";
            $body .= file_get_contents($filePath);
            $body .= $close_delim;
            
            $uploadUrl = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $uploadUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$accessToken}",
                    "Content-Type: multipart/related; boundary=\"{$delimiter}\"",
                    "Content-Length: " . strlen($body)
                ],
                CURLOPT_TIMEOUT => 300, // 5 minutes timeout
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: {$error}");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP error {$httpCode}: {$response}");
            }
            
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['id'])) {
                throw new Exception("Invalid response from Google Drive API");
            }
            
            return [
                'success' => true,
                'file_id' => $result['id'],
                'file_name' => $fileName
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update existing file in Google Drive
     */
    private function updateFileInGoogleDrive($filePath, $fileId, $accessToken) {
        try {
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            $fileContent = file_get_contents($filePath);
            
            $uploadUrl = "https://www.googleapis.com/upload/drive/v3/files/{$fileId}?uploadType=media";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $uploadUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => $fileContent,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$accessToken}",
                    "Content-Type: {$mimeType}",
                    "Content-Length: " . strlen($fileContent)
                ],
                CURLOPT_TIMEOUT => 300,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'file_id' => $fileId,
                    'action' => 'updated'
                ];
            } else {
                throw new Exception("Failed to update file: HTTP {$httpCode}");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make Google API request
     */
    private function makeGoogleApiRequest($url, $method = 'GET', $data = null, $accessToken) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Log sync operation
     */
    private function logSync($studentId, $filename, $status, $folderPath, $googleFileId = null, $errorMessage = null) {
        $query = "INSERT INTO enhanced_sync_logs 
                  (student_id, filename, status, folder_path, google_file_id, error_message, synced_at, file_count) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isssss", $studentId, $filename, $status, $folderPath, $googleFileId, $errorMessage);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Trigger auto-sync when file is uploaded
     */
    public function triggerAutoSyncOnUpload($studentId, $filePath, $filename, $documentTypeId = null) {
        try {
            if (!isAutoSyncEnabled()) {
                return false;
            }
            
            $accessToken = getStoredAccessToken();
            if (!$accessToken) {
                return false;
            }
            
            // Get student information
            $studentQuery = "SELECT * FROM students WHERE id = ?";
            $studentStmt = $this->conn->prepare($studentQuery);
            $studentStmt->bind_param("i", $studentId);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();
            $student = $studentResult->fetch_assoc();
            $studentStmt->close();
            
            if (!$student) {
                return false;
            }
            
            // Get current school year
            $currentMonth = (int)date('n');
            $currentYear = (int)date('Y');
            $schoolYear = ($currentMonth >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
            
            // Create student folder name
            $studentFolderName = $student['student_id'] . '_' . 
                               str_replace(' ', '_', $student['first_name']) . '_' . 
                               str_replace(' ', '_', $student['last_name']);
            
            // Sync single file
            $mainFolderId = $this->createOrGetFolder("IBACMI {$schoolYear}", null, $accessToken);
            $studentFolderId = $this->createOrGetFolder($studentFolderName, $mainFolderId, $accessToken);
            
            $uploadResult = $this->uploadFileToGoogleDrive($filePath, $filename, $studentFolderId, $accessToken);
            
            if ($uploadResult['success']) {
                $this->logSync(
                    $studentId, 
                    $filename, 
                    'success', 
                    "IBACMI {$schoolYear}/{$studentFolderName}",
                    $uploadResult['file_id']
                );
                return true;
            } else {
                $this->logSync(
                    $studentId, 
                    $filename, 
                    'failed', 
                    "IBACMI {$schoolYear}/{$studentFolderName}",
                    null,
                    $uploadResult['error']
                );
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Auto-sync error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sync entire student folder (for manual/force sync)
     */
    public function syncEntireStudentFolder($student, $accessToken) {
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        $schoolYear = ($currentMonth >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
        
        $studentFolderName = $student['student_id'] . '_' . 
                           str_replace(' ', '_', $student['first_name']) . '_' . 
                           str_replace(' ', '_', $student['last_name']);
        
        return $this->syncStudentToGoogleDrive($student, $accessToken, $schoolYear, $studentFolderName);
    }
}

// Helper functions
function initializeEnhancedSyncTables($conn) {
    $createSyncLogsTable = "
        CREATE TABLE IF NOT EXISTS enhanced_sync_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            status ENUM('success', 'failed', 'pending') NOT NULL,
            folder_path VARCHAR(500),
            google_file_id VARCHAR(100),
            error_message TEXT,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            file_count INT DEFAULT 1,
            archived TINYINT(1) DEFAULT 0,
            INDEX idx_student_sync (student_id),
            INDEX idx_sync_status (status),
            INDEX idx_sync_date (synced_at)
        )
    ";
    $conn->query($createSyncLogsTable);
    
    $createSystemSettingsTable = "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $conn->query($createSystemSettingsTable);
}

function getStoredAccessToken() {
    global $conn;
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'google_drive_access_token' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return null;
}

function isAutoSyncEnabled() {
    global $conn;
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'] === 'enabled';
    }
    
    return false;
}
?>
