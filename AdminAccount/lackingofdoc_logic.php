<?php
// CRITICAL: No whitespace before this line!
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
if (!isset($conn)) {
    require_once '../db_connect.php';
}

date_default_timezone_set('Asia/Manila');

// Function to trigger backup sync - safely check if backup.php exists
if (!function_exists('triggerBackupSync')) {
    function triggerBackupSync($studentId, $documentId = null) {
        try {
            if (file_exists('backup.php')) {
                require_once 'backup.php';
                if (function_exists('triggerAutoSync')) {
                    triggerAutoSync($studentId, $documentId, true);
                    error_log("Auto-sync triggered for student ID: {$studentId}, doc ID: {$documentId}");
                    return true;
                }
            }
            error_log("Backup sync not available - backup.php not found or function missing");
            return false;
        } catch (Exception $e) {
            error_log("Backup sync failed: " . $e->getMessage());
            return false;
        }
    }
}

// Fallback formatFileSize function if not available
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes, $precision = 2) {
        if ($bytes == 0) return '0 B';
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// ADD THIS NEW FUNCTION - Duplicate file checker (same as newstudent.php and transferee.php)
function checkDuplicateFile($conn, $fileName, $fileSize, $fileType) {
    $sql = "SELECT sd.id, sd.file_name, s.student_id, s.first_name, s.last_name 
            FROM student_documents sd 
            JOIN students s ON sd.student_id = s.id 
            WHERE sd.original_filename = ? 
            AND sd.file_size = ? 
            AND sd.file_type = ?
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for duplicate file check: " . $conn->error);
        return ['isDuplicate' => false, 'message' => ''];
    }
    
    $stmt->bind_param("sis", $fileName, $fileSize, $fileType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'isDuplicate' => true,
            'message' => "File '{$fileName}' already exists for student {$row['first_name']} {$row['last_name']} (ID: {$row['student_id']})",
            'student_info' => $row
        ];
    }
    
    $stmt->close();
    return ['isDuplicate' => false, 'message' => ''];
}

// Helper functions
function getDocumentTypeId($conn, $docCode) {
    $sql = "SELECT id FROM document_types WHERE doc_code = ? AND is_active = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("s", $docCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    
    $stmt->close();
    return null;
}

function getDocumentName($docCode) {
    $names = [
        'card138' => 'Card 138',
        'moral' => 'Certificate of Good Moral',
        'birth' => 'PSA Birth Certificate',
        'marriage' => 'PSA Marriage Certificate',
        'tor' => 'Transcript of Records',
        'honorable' => 'Honorable Dismissal',
        'gradeslip' => 'Grade Slip',
        'id' => '2x2 Picture'
    ];
    return $names[$docCode] ?? $docCode;
}

function handleDocumentUpload($file, $studentDbId, $docId, $conn, $studentId, $docCode) {
    // UPDATED FOR SECURITY: Add mandatory validation before upload
    require_once 'document_validator.php';  // Ensure validator is included
    
    // CHECK FOR DUPLICATE FILE FIRST (NEW - same as newstudent.php)
    $duplicateFileCheck = checkDuplicateFile($conn, $file['name'], $file['size'], $file['type']);
    if ($duplicateFileCheck['isDuplicate']) {
        error_log("Duplicate file detected: " . $duplicateFileCheck['message']);
        return ['success' => false, 'message' => $duplicateFileCheck['message'], 'isDuplicate' => true];
    }
    
    // Perform server-side validation
    $validation = validateDocumentType($file, $docCode);
    if (!$validation['valid']) {
        error_log("Document validation failed for {$docCode}: " . $validation['message']);
        return ['success' => false, 'message' => 'Document validation failed: ' . $validation['message']];
    }
    
    // Additional file upload validation
    if ($file["error"] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file["error"]];
    }
    
    // Validate file type against allowed types from validator
    $allowedTypes = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
    $fileExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    // Validate file size (50MB max)
    if ($file["size"] > 50 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds 50MB limit'];
    }

    // Create proper upload directory structure - MATCH newstudent.php format
    $year = date('Y');
    $uploadPath = dirname(__DIR__) . "/uploads/{$year}/";
    if (!is_dir($uploadPath)) {
        if (!mkdir($uploadPath, 0755, true)) {
            error_log("Failed to create directory: " . $uploadPath);
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Create unique filename - SIMPLIFIED to match newstudent.php
    $newFileName = $studentDbId . '_' . $docCode . '_' . time() . '.' . $fileExt;
    $fullPath = $uploadPath . $newFileName;
    
    // CRITICAL FIX: Store path EXACTLY as newstudent.php does
    $relativePath = "uploads/{$year}/" . $newFileName;
    
    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $fullPath)) {
        $fileSize = filesize($fullPath);
        $originalName = $file["name"];
        $mimeType = $file["type"];
        
        // Check if record exists first
        $checkSql = "SELECT id FROM student_documents WHERE student_id = ? AND document_type_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            error_log("Prepare failed for check: " . $conn->error);
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $checkStmt->bind_param("ii", $studentDbId, $docId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // FIXED: Update to EXACTLY match newstudent.php structure
            $updateSql = "UPDATE student_documents 
                          SET file_name = ?, 
                              file_path = ?, 
                              original_filename = ?, 
                              file_size = ?, 
                              file_type = ?,
                              is_submitted = 1, 
                              submission_date = NOW(),
                              last_updated = NOW()
                          WHERE student_id = ? AND document_type_id = ?";
            
            $stmt = $conn->prepare($updateSql);
            if ($stmt) {
                // Bind parameters in exact order
                $stmt->bind_param("sssisii",
                    $relativePath,       // file_name (YES, this is the relative path)
                    $relativePath,       // file_path (same as file_name for consistency)
                    $originalName,       // original_filename
                    $fileSize,           // file_size
                    $mimeType,           // file_type
                    $studentDbId,        // student_id (WHERE clause)
                    $docId               // document_type_id (WHERE clause)
                );
                
                if ($stmt->execute()) {
                    $documentRecordId = $checkResult->fetch_assoc()['id'] ?? null;
                    $checkStmt->close();
                    $stmt->close();
                    error_log("Successfully updated document: {$relativePath}");
                    
                    // Trigger auto-sync
                    if ($documentRecordId) {
                        triggerAutoSyncForDocument($studentDbId, $documentRecordId, $docCode, $fullPath, $originalName, $conn);
                    }
                    
                    return ['success' => true, 'message' => 'File uploaded successfully'];
                } else {
                    error_log("Update failed: " . $stmt->error);
                    $checkStmt->close();
                    $stmt->close();
                    return ['success' => false, 'message' => 'Failed to update database'];
                }
            }
        } else {
            // FIXED: Insert to EXACTLY match newstudent.php structure
            $insertSql = "INSERT INTO student_documents 
                          (student_id, document_type_id, file_name, file_path, original_filename, 
                           file_size, file_type, is_submitted, submission_date)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmt = $conn->prepare($insertSql);
            if ($stmt) {
                // Bind parameters in exact order
                $stmt->bind_param("iisssis",
                    $studentDbId,        // student_id
                    $docId,              // document_type_id
                    $relativePath,       // file_name (relative path)
                    $relativePath,       // file_path (same as file_name)
                    $originalName,       // original_filename
                    $fileSize,           // file_size
                    $mimeType            // file_type
                );
                
                if ($stmt->execute()) {
                    $documentRecordId = $stmt->insert_id;
                    $checkStmt->close();
                    $stmt->close();
                    error_log("Successfully inserted document: {$relativePath}");
                    
                    // Trigger auto-sync
                    triggerAutoSyncForDocument($studentDbId, $documentRecordId, $docCode, $fullPath, $originalName, $conn);
                    
                    return ['success' => true, 'message' => 'File uploaded successfully'];
                } else {
                    error_log("Insert failed: " . $stmt->error);
                    $checkStmt->close();
                    $stmt->close();
                    return ['success' => false, 'message' => 'Failed to insert into database'];
                }
            }
        }
        $checkStmt->close();
    } else {
        error_log("Failed to move uploaded file from " . $file["tmp_name"] . " to " . $fullPath);
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
    
    return ['success' => false, 'message' => 'Unknown error occurred'];
}

function getMissingDocuments($conn, $searchTerm = '') {
    // Base query to get students with missing documents
    $whereClause = '';
    $params = [];
    $types = '';
    
    if (!empty($searchTerm)) {
        $whereClause = " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.course LIKE ?)";
        $searchPattern = "%$searchTerm%";
        $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern];
        $types = 'ssss';
    }
    
    // Get all students
    $sql = "
        SELECT DISTINCT
            s.id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.course,
            s.year_level,
            s.student_type,
            s.marriage_cert_required,
            s.date_added
        FROM students s
        WHERE 1=1
        $whereClause
        ORDER BY s.date_added DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $studentId = $row['id'];
        $studentType = $row['student_type'];
        $marriageCertRequired = $row['marriage_cert_required'];
        
        // Get the required document types based on student type
        $requiredDocTypes = [];
        if ($studentType === 'Regular') {
            $requiredDocTypes = ['card138', 'moral', 'birth', 'id'];
            // Add marriage certificate only if required
            if ($marriageCertRequired) {
                $requiredDocTypes[] = 'marriage';
            }
        } else if ($studentType === 'Transferee') {
            $requiredDocTypes = ['moral', 'birth', 'tor', 'honorable', 'gradeslip', 'id'];
            // Add marriage certificate only if required
            if ($marriageCertRequired) {
                $requiredDocTypes[] = 'marriage';
            }
        }
        
        // Check which documents are missing
        $missingDocs = [];
        
        foreach ($requiredDocTypes as $docCode) {
            // Check if document exists and is submitted
            $docCheckSql = "
                SELECT dt.doc_code, dt.doc_name, sd.is_submitted
                FROM document_types dt
                LEFT JOIN student_documents sd ON dt.id = sd.document_type_id AND sd.student_id = ?
                WHERE dt.doc_code = ? AND dt.is_active = 1
            ";
            
            $docCheckStmt = $conn->prepare($docCheckSql);
            if ($docCheckStmt) {
                $docCheckStmt->bind_param("is", $studentId, $docCode);
                $docCheckStmt->execute();
                $docCheckResult = $docCheckStmt->get_result();
                
                if ($docCheckResult->num_rows > 0) {
                    $docRow = $docCheckResult->fetch_assoc();
                    // If document doesn't exist (is_submitted is NULL) or is not submitted (is_submitted = 0)
                    if ($docRow['is_submitted'] === null || $docRow['is_submitted'] == 0) {
                        $missingDocs[] = [
                            'doc_code' => $docRow['doc_code'],
                            'doc_name' => $docRow['doc_name']
                        ];
                    }
                }
                $docCheckStmt->close();
            }
        }
        
        // Only include students who actually have missing documents
        if (!empty($missingDocs)) {
            $row['missing_docs'] = $missingDocs;
            $students[] = $row;
        }
    }
    
    $stmt->close();
    return $students;
}

/**
 * Auto-sync uploaded document to Google Drive
 */
function triggerAutoSyncForDocument($studentDbId, $documentRecordId, $docCode, $filePath, $originalFilename, $conn) {
    try {
        // Check if auto-sync is enabled
        $syncQuery = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
        $syncResult = $conn->query($syncQuery);
        
        if (!$syncResult || $syncResult->num_rows === 0 || $syncResult->fetch_assoc()['setting_value'] !== 'enabled') {
            return false;
        }
        
        // Check Google Drive connection
        if (!file_exists(__DIR__ . '/backup.php')) return false;
        require_once __DIR__ . '/backup.php';
        
        $accessToken = getValidAccessToken();
        if (!$accessToken) return false;
        
        // Get student info
        $studentQuery = "SELECT student_id, first_name, last_name FROM students WHERE id = ?";
        $stmt = $conn->prepare($studentQuery);
        $stmt->bind_param("i", $studentDbId);
        $stmt->execute();
        $studentResult = $stmt->get_result();
        
        if ($studentResult->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $student = $studentResult->fetch_assoc();
        $stmt->close();
        
        // Get document type name
        $docTypeQuery = "SELECT doc_name FROM document_types WHERE doc_code = ?";
        $stmt = $conn->prepare($docTypeQuery);
        $stmt->bind_param("s", $docCode);
        $stmt->execute();
        $docTypeResult = $stmt->get_result();
        
        $docTypeName = 'Document';
        if ($docTypeResult->num_rows > 0) {
            $docTypeName = $docTypeResult->fetch_assoc()['doc_name'];
        }
        $stmt->close();
        
        // Determine school year
        $currentMonth = date('n');
        $currentYear = date('Y');
        $schoolYear = ($currentMonth >= 6) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
        
        // Create/get folders
        $mainFolderName = "IBACMI Backup {$schoolYear}";
        $mainFolderId = createGoogleDriveFolder($mainFolderName);
        if (!$mainFolderId) return false;
        
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
        
        // Upload file
        if (!file_exists($filePath)) return false;
        
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $fileName = $docTypeName . '.' . $fileExtension;
        
        $googleFileId = uploadFileToGoogleDrive($filePath, $fileName, $studentFolderId);
        
        if ($googleFileId) {
            error_log("✓ Auto-synced: {$fileName} for {$studentFolderName}");
            
            // Log success
            $logQuery = "INSERT INTO sync_logs (student_id, document_id, google_drive_file_id, sync_status, sync_type, synced_at) 
                        VALUES (?, ?, ?, 'success', 'auto', NOW())
                        ON DUPLICATE KEY UPDATE 
                        google_drive_file_id = VALUES(google_drive_file_id),
                        sync_status = 'success',
                        synced_at = NOW()";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->bind_param("iis", $studentDbId, $documentRecordId, $googleFileId);
            $logStmt->execute();
            $logStmt->close();
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Auto-sync error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// HANDLE FORM SUBMISSION - RETURN JSON ONLY
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_documents'])) {
    
    // Clean any output and set JSON header
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['record_id']) || empty($_POST['record_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing record ID']);
            exit();
        }

        $recordId = intval($_POST['record_id']);

        if ($recordId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
            exit();
        }

        // Check if any files were uploaded
        $hasFiles = false;
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'document_') === 0 && $file['error'] != UPLOAD_ERR_NO_FILE && !empty($file['name'])) {
                $hasFiles = true;
                break;
            }
        }

        if (!$hasFiles) {
            echo json_encode(['success' => false, 'message' => 'No files were selected for upload']);
            exit();
        }

        // Get student info
        $studentInfoQuery = "SELECT * FROM students WHERE id = ?";
        $studentInfoStmt = $conn->prepare($studentInfoQuery);
        if (!$studentInfoStmt) {
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit();
        }
        
        $studentInfoStmt->bind_param("i", $recordId);
        $studentInfoStmt->execute();
        $studentInfoResult = $studentInfoStmt->get_result();
        
        if ($studentInfoResult->num_rows == 0) {
            $studentInfoStmt->close();
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }
        
        $studentInfo = $studentInfoResult->fetch_assoc();
        $studentId = $studentInfo['student_id'];
        $studentInfoStmt->close();

        $uploadedFiles = [];
        $failedFiles = [];
        $duplicateFiles = [];

        // Process each uploaded file
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'document_') === 0 && $file['error'] != UPLOAD_ERR_NO_FILE && !empty($file['name'])) {
                $docCode = str_replace('document_', '', $key);
                $docTypeId = getDocumentTypeId($conn, $docCode);
                
                if ($docTypeId) {
                    $result = handleDocumentUpload($file, $recordId, $docTypeId, $conn, $studentId, $docCode);
                    
                    if ($result['success']) {
                        $uploadedFiles[] = getDocumentName($docCode);
                    } else {
                        if (isset($result['isDuplicate']) && $result['isDuplicate']) {
                            $duplicateFiles[] = $result['message'];
                        } else {
                            $failedFiles[] = getDocumentName($docCode) . ': ' . $result['message'];
                        }
                    }
                }
            }
        }

        // Handle duplicates
        if (!empty($duplicateFiles)) {
            echo json_encode([
                'success' => false,
                'isDuplicate' => true,
                'message' => 'Duplicate file(s) detected',
                'duplicateFiles' => $duplicateFiles
            ]);
            exit();
        }

        // Handle success
        if (!empty($uploadedFiles)) {
            $successMessage = count($uploadedFiles) . " document(s) uploaded successfully:\n" . implode(', ', $uploadedFiles);
            
            if (!empty($failedFiles)) {
                $successMessage .= "\n\nFailed uploads:\n" . implode("\n", $failedFiles);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $successMessage,
                'uploadedFiles' => $uploadedFiles,
                'failedFiles' => $failedFiles
            ]);
            exit();
        } else {
            $errorMessage = "All uploads failed";
            if (!empty($failedFiles)) {
                $errorMessage .= ":\n" . implode("\n", $failedFiles);
            }
            
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit();
        }

    } catch (Exception $e) {
        error_log("Document update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// ============================================
// NON-POST: Get students with missing documents
// ============================================
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$incompleteStudents = getMissingDocuments($conn, $searchTerm);

error_log("Found " . count($incompleteStudents) . " students with missing documents");
?>