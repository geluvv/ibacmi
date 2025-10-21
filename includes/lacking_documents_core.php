<?php
// All the PHP logic from lackingofdoc.php (without the HTML)
// Include database connection and enhanced sync
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/enhanced_sync_hook.php';
require_once __DIR__ . '/../AdminAccount/document_validator.php';

// Initialize enhanced sync tables
initializeEnhancedSyncTables($conn);

// Initialize variables
$updateSuccess = false;
$updateMessage = '';
$updateError = '';
$incompleteStudents = [];

// Process document update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_documents'])) {
    $studentId = $_POST['student_id'];
    $recordId = $_POST['record_id'];
    
    // Define upload directory
    $upload_dir = "../uploads/";
    
    // Ensure the directory exists
    if (!file_exists($upload_dir) && !is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Get student info for auto-sync
    $studentInfoQuery = "SELECT * FROM students WHERE id = ?";
    $studentInfoStmt = $conn->prepare($studentInfoQuery);
    $studentInfoStmt->bind_param("i", $recordId);
    $studentInfoStmt->execute();
    $studentInfoResult = $studentInfoStmt->get_result();
    $studentInfo = $studentInfoResult->fetch_assoc();
    $studentInfoStmt->close();
    
    // Initialize enhanced auto-sync
    $autoSync = new EnhancedAutoSync($conn);
    
    // Get document types that were updated
    $updatedDocs = [];
    $uploadedFiles = [];
    $atLeastOneFileUploaded = false;
    $validationErrors = [];
    
    // Process each document upload
    foreach ($_POST as $key => $value) {
        // Check if this is a document submission flag
        if (strpos($key, '_submitted') !== false && $value == 1) {
            $docCode = str_replace('_submitted', '', $key);
            $fileInputName = $docCode . '_file';
            
            // Get document type ID
            $docTypeSql = "SELECT id FROM document_types WHERE doc_code = ?";
            $docTypeStmt = $conn->prepare($docTypeSql);
            $docTypeStmt->bind_param('s', $docCode);
            $docTypeStmt->execute();
            $docTypeResult = $docTypeStmt->get_result();
            
            if ($docTypeResult->num_rows > 0) {
                $docTypeData = $docTypeResult->fetch_assoc();
                $docTypeId = $docTypeData['id'];
                $updatedDocs[] = $docTypeId;
                
                // Handle file upload if provided
                if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
                    
                    // Enhanced: Document validation
                    $validation = validateDocumentType($_FILES[$fileInputName], $docCode);
                    if (!$validation['valid']) {
                        $validationErrors[] = $validation['message'];
                        $docTypeStmt->close();
                        continue;
                    }
                    
                    $atLeastOneFileUploaded = true;
                    
                    // Create organized directory structure
                    $year = date('Y');
                    $studentFolder = $studentInfo ? 
                        preg_replace('/[^\w\s-]/', '', $studentInfo['first_name'] . '_' . $studentInfo['last_name']) :
                        'Student_' . $recordId;
                    
                    $uploadPath = dirname(__DIR__) . "/uploads/{$year}/{$studentFolder}/";
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    // Generate unique filename
                    $fileExtension = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
                    $newFileName = $recordId . '_' . $docCode . '_' . time() . '.' . $fileExtension;
                    $targetFile = $uploadPath . $newFileName;
                    $relativePath = "uploads/{$year}/{$studentFolder}/" . $newFileName;
                    
                    // Get file info
                    $fileName = $_FILES[$fileInputName]['name'];
                    $fileTmpName = $_FILES[$fileInputName]['tmp_name'];
                    $fileSize = $_FILES[$fileInputName]['size'];
                    $fileType = $_FILES[$fileInputName]['type'];
                    
                    // Check if file already exists using MD5 hash
                    $fileHash = md5_file($fileTmpName);
                    
                    // Create document_files table if it doesn't exist
                    $createDocFileTable = "
                        CREATE TABLE IF NOT EXISTS document_files (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            file_path VARCHAR(500) NOT NULL,
                            file_hash VARCHAR(32) NOT NULL UNIQUE,
                            original_name VARCHAR(255) NOT NULL,
                            file_size INT NOT NULL,
                            mime_type VARCHAR(100) NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_hash (file_hash)
                        )
                    ";
                    $conn->query($createDocFileTable);
                    
                    // Check if this hash already exists in the database
                    $hashSql = "SELECT file_path FROM document_files WHERE file_hash = ?";
                    $hashStmt = $conn->prepare($hashSql);
                    $hashStmt->bind_param("s", $fileHash);
                    $hashStmt->execute();
                    $hashResult = $hashStmt->get_result();
                    
                    $filePath = '';
                    $stored = false;
                    
                    if ($hashResult->num_rows > 0) {
                        // File already exists, use the existing path
                        $existingFile = $hashResult->fetch_assoc();
                        $filePath = $existingFile['file_path'];
                        $stored = true;
                    } else {
                        // Move uploaded file
                        if (move_uploaded_file($fileTmpName, $targetFile)) {
                            $filePath = $relativePath;
                            $stored = true;
                            
                            // Insert into document_files table
                            $insertFileSql = "INSERT INTO document_files (file_path, file_hash, original_name, file_size, mime_type) VALUES (?, ?, ?, ?, ?)";
                            $insertFileStmt = $conn->prepare($insertFileSql);
                            $insertFileStmt->bind_param("sssis", $relativePath, $fileHash, $fileName, $fileSize, $fileType);
                            $insertFileStmt->execute();
                            $insertFileStmt->close();
                            
                            $uploadedFiles[] = $fileName;
                        }
                    }
                    
                    $hashStmt->close();
                    
                    if ($stored && !empty($filePath)) {
                        // Check if document record already exists
                        $checkSql = "SELECT id FROM student_documents WHERE student_id = ? AND document_type_id = ?";
                        $checkStmt = $conn->prepare($checkSql);
                        $checkStmt->bind_param("ii", $recordId, $docTypeId);
                        $checkStmt->execute();
                        $checkResult = $checkStmt->get_result();
                        
                        if ($checkResult->num_rows > 0) {
                            // Update existing record
                            $updateSql = "UPDATE student_documents SET 
                                         file_name = ?, file_path = ?, is_submitted = 1, 
                                         submitted_at = NOW(), file_size = ?, mime_type = ? 
                                         WHERE student_id = ? AND document_type_id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->bind_param("ssisii", $fileName, $filePath, $fileSize, $fileType, $recordId, $docTypeId);
                            
                            if ($updateStmt->execute()) {
                                $autoSync->recordDocumentUpdate($recordId, $docTypeId, 'updated', $studentInfo);
                            }
                            
                            $updateStmt->close();
                        } else {
                            // Insert new record
                            $insertSql = "INSERT INTO student_documents 
                                         (student_id, document_type_id, file_name, file_path, is_submitted, submitted_at, file_size, mime_type) 
                                         VALUES (?, ?, ?, ?, 1, NOW(), ?, ?)";
                            
                            $insertStmt = $conn->prepare($insertSql);
                            $insertStmt->bind_param("iissis", 
                                $recordId, $docTypeId, $fileName, $filePath, $fileSize, $fileType
                            );
                            
                            if ($insertStmt->execute()) {
                                $autoSync->recordDocumentUpdate($recordId, $docTypeId, 'inserted', $studentInfo);
                            }
                            
                            $insertStmt->close();
                        }
                        
                        $checkStmt->close();
                    }
                }
                
                $docTypeStmt->close();
            }
        }
    }
    
    // Handle validation errors and success messages
    if (!empty($validationErrors)) {
        $updateSuccess = false;
        $updateError = "Invalid Documents\nThe following documents are invalid:\n• " . implode("\n• ", $validationErrors) . "\n\nPlease upload the correct document types and try again.";
    } else if ($atLeastOneFileUploaded) {
        // Update student status based on document completeness
        $newStatus = checkStudentDocumentStatus($conn, $recordId);
        $updateStatusSql = "UPDATE students SET status = ? WHERE id = ?";
        $updateStatusStmt = $conn->prepare($updateStatusSql);
        $updateStatusStmt->bind_param("si", $newStatus, $recordId);
        $updateStatusStmt->execute();
        $updateStatusStmt->close();
        
        $updateSuccess = true;
        $updateMessage = "Documents updated successfully! " . count($uploadedFiles) . " valid document(s) uploaded. Auto-sync completed.";
    } else if (!empty($updatedDocs)) {
        // No files were uploaded but form was submitted
        $updateSuccess = false;
        $updateError = "No files were uploaded. Please select at least one file to upload.";
    }
}

// Helper function to check student document status
function checkStudentDocumentStatus($conn, $studentId) {
    // Get student type
    $studentTypeSql = "SELECT student_type FROM students WHERE id = ?";
    $studentTypeStmt = $conn->prepare($studentTypeSql);
    $studentTypeStmt->bind_param("i", $studentId);
    $studentTypeStmt->execute();
    $studentTypeResult = $studentTypeStmt->get_result();
    
    if ($studentTypeResult->num_rows === 0) {
        $studentTypeStmt->close();
        return 'incomplete';
    }
    
    $studentType = $studentTypeResult->fetch_assoc()['student_type'];
    $studentTypeStmt->close();
    
    // Get required documents count (excluding marriage certificate that wasn't required)
    $requiredDocsSql = "SELECT COUNT(*) as total FROM document_types dt
                        WHERE (dt.required_for = 'All' OR dt.required_for = ?) 
                        AND dt.is_active = 1
                        AND (dt.doc_code != 'marriage' OR EXISTS (
                            SELECT 1 FROM student_documents sd 
                            WHERE sd.student_id = ? AND sd.document_type_id = dt.id
                        ))";
    $requiredDocsStmt = $conn->prepare($requiredDocsSql);
    $requiredDocsStmt->bind_param("si", $studentType, $studentId);
    $requiredDocsStmt->execute();
    $requiredDocsResult = $requiredDocsStmt->get_result();
    $requiredDocsCount = $requiredDocsResult->fetch_assoc()['total'];
    $requiredDocsStmt->close();
    
    // Get submitted documents count
    $submittedDocsSql = "SELECT COUNT(DISTINCT sd.document_type_id) as total 
                         FROM student_documents sd
                         JOIN document_types dt ON sd.document_type_id = dt.id
                         WHERE sd.student_id = ? AND sd.is_submitted = 1 
                         AND (dt.required_for = 'All' OR dt.required_for = ?) 
                         AND dt.is_active = 1";
    $submittedDocsStmt = $conn->prepare($submittedDocsSql);
    $submittedDocsStmt->bind_param("is", $studentId, $studentType);
    $submittedDocsStmt->execute();
    $submittedDocsResult = $submittedDocsStmt->get_result();
    $submittedDocsCount = $submittedDocsResult->fetch_assoc()['total'];
    $submittedDocsStmt->close();
    
    // Return status
    return ($submittedDocsCount >= $requiredDocsCount) ? 'complete' : 'incomplete';
}

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchTerm = "%{$searchTerm}%";
    $searchCondition = "AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.course LIKE ?)";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Updated query to properly get students with missing documents
$sql = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.course, s.year_level, 
        s.student_type, s.status, s.date_added,
        (SELECT COUNT(*) FROM document_types dt 
         WHERE (dt.required_for = 'All' OR dt.required_for = s.student_type) AND dt.is_active = 1) as required_docs,
        (SELECT COUNT(DISTINCT sd.document_type_id) FROM student_documents sd 
         JOIN document_types dt ON sd.document_type_id = dt.id
         WHERE sd.student_id = s.id AND sd.is_submitted = 1 
         AND (dt.required_for = 'All' OR dt.required_for = s.student_type) 
         AND dt.is_active = 1) as submitted_docs
        FROM students s
        WHERE (s.status IS NULL OR s.status = 'incomplete' OR s.status = 'active') 
        AND s.id NOT IN (
            SELECT DISTINCT s2.id FROM students s2
            WHERE (
                SELECT COUNT(DISTINCT sd.document_type_id) FROM student_documents sd 
                JOIN document_types dt ON sd.document_type_id = dt.id
                WHERE sd.student_id = s2.id AND sd.is_submitted = 1 
                AND (dt.required_for = 'All' OR dt.required_for = s2.student_type) 
                AND dt.is_active = 1
            ) >= (
                SELECT COUNT(*) FROM document_types dt2 
                WHERE (dt2.required_for = 'All' OR dt2.required_for = s2.student_type) AND dt2.is_active = 1
            )
        )
        $searchCondition
        ORDER BY s.date_added DESC";

$stmt = $conn->prepare($sql);

if (!empty($searchParams)) {
    $types = str_repeat('s', count($searchParams));
    $stmt->bind_param($types, ...$searchParams);
}

$stmt->execute();
$result = $stmt->get_result();

// Prepare data for display
while ($row = $result->fetch_assoc()) {
    // Get missing documents for this student
    $missingDocs = fetchMissingDocuments($conn, $row['id']);
    
    if (!empty($missingDocs)) {
        $row['missing_docs'] = $missingDocs;
        $incompleteStudents[] = $row;
    }
}

$stmt->close();

// Function to get missing documents
function fetchMissingDocuments($conn, $studentId) {
    // Get student type
    $studentTypeSql = "SELECT student_type FROM students WHERE id = ?";
    $studentTypeStmt = $conn->prepare($studentTypeSql);
    $studentTypeStmt->bind_param("i", $studentId);
    $studentTypeStmt->execute();
    $studentTypeResult = $studentTypeStmt->get_result();
    
    if ($studentTypeResult->num_rows === 0) {
        $studentTypeStmt->close();
        return [];
    }
    
    $studentType = $studentTypeResult->fetch_assoc()['student_type'];
    $studentTypeStmt->close();
    
    // Get required documents for this student type (excluding marriage if no record exists)
    $requiredDocsSql = "SELECT dt.id, dt.doc_name, dt.doc_code 
                        FROM document_types dt
                        WHERE (dt.required_for = 'All' OR dt.required_for = ?) 
                        AND dt.is_active = 1
                        AND (dt.doc_code != 'marriage' OR EXISTS (
                            SELECT 1 FROM student_documents sd 
                            WHERE sd.student_id = ? AND sd.document_type_id = dt.id
                        ))";
    $requiredDocsStmt = $conn->prepare($requiredDocsSql);
    $requiredDocsStmt->bind_param("si", $studentType, $studentId);
    $requiredDocsStmt->execute();
    $requiredDocsResult = $requiredDocsStmt->get_result();
    
    $requiredDocs = [];
    while ($row = $requiredDocsResult->fetch_assoc()) {
        $requiredDocs[] = $row;
    }
    $requiredDocsStmt->close();
    
    // Get submitted documents for this student
    $submittedDocsSql = "SELECT DISTINCT document_type_id FROM student_documents 
                         WHERE student_id = ? AND is_submitted = 1";
    $submittedDocsStmt = $conn->prepare($submittedDocsSql);
    $submittedDocsStmt->bind_param("i", $studentId);
    $submittedDocsStmt->execute();
    $submittedDocsResult = $submittedDocsStmt->get_result();
    
    $submittedDocs = [];
    while ($row = $submittedDocsResult->fetch_assoc()) {
        $submittedDocs[] = $row['document_type_id'];
    }
    $submittedDocsStmt->close();
    
    // Find missing documents
    $missingDocs = [];
    foreach ($requiredDocs as $doc) {
        if (!in_array($doc['id'], $submittedDocs)) {
            $missingDocs[] = $doc;
        }
    }
    
    return $missingDocs;
}

// Function to get document name from document type ID
function getDocumentName($conn, $docTypeId) {
    $sql = "SELECT doc_name FROM document_types WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $docTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['doc_name'];
    }
    
    $stmt->close();
    return "Unknown Document";
}

// Function to check if a document is already submitted
function isDocumentSubmitted($conn, $studentId, $docTypeId) {
    $sql = "SELECT is_submitted FROM student_documents 
            WHERE student_id = ? AND document_type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $studentId, $docTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['is_submitted'] == 1;
    }
    
    $stmt->close();
    return false;
}

// Return the variables needed for display
return [
    'incompleteStudents' => $incompleteStudents,
    'updateSuccess' => $updateSuccess,
    'updateMessage' => $updateMessage,
    'updateError' => $updateError
];
?>