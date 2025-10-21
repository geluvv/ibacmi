<?php
/**
 * Simple document upload handler
 * Include this file in your newstudent.php and transferee.php
 */

/**
 * Handle document file upload
 * 
 * @param int $studentId The ID of the student
 * @param int $documentTypeId The ID of the document type
 * @param array $file The uploaded file from $_FILES
 * @return bool True if upload was successful, false otherwise
 */
function handleDocumentUpload($studentId, $documentTypeId, $file) {
    global $conn;
    
    // Check if file was uploaded
    if (!isset($file) || $file["error"] != 0) {
        return false;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = "uploads/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file["name"], PATHINFO_EXTENSION);
    $newFileName = "doc_" . $studentId . "_" . $documentTypeId . "_" . time() . "." . $fileExtension;
    $filePath = $uploadDir . $newFileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file["tmp_name"], $filePath)) {
        return false;
    }
    
    // Set the web-accessible path for the database
    $webPath = "uploads/" . $newFileName;
    
    // Update the database
    $sql = "UPDATE student_documents 
            SET file_path = ?, is_submitted = 1, submission_date = NOW() 
            WHERE student_id = ? AND document_type_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $webPath, $studentId, $documentTypeId);
    $result = $stmt->execute();
    
    // Get the document ID that was just updated
    $getDocIdSql = "SELECT id FROM student_documents WHERE student_id = ? AND document_type_id = ?";
    $getDocStmt = $conn->prepare($getDocIdSql);
    $getDocStmt->bind_param("ii", $studentId, $documentTypeId);
    $getDocStmt->execute();
    $docResult = $getDocStmt->get_result();
    $docRow = $docResult->fetch_assoc();
    $documentId = $docRow ? $docRow['id'] : null;
    $getDocStmt->close();
    
    $stmt->close();
    
    // ✅ TRIGGER AUTO-SYNC if enabled
    if ($result && $documentId) {
        // Check if auto-sync is enabled
        $checkSyncSql = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
        $syncResult = $conn->query($checkSyncSql);
        
        if ($syncResult && $syncResult->num_rows > 0) {
            $syncRow = $syncResult->fetch_assoc();
            if ($syncRow['setting_value'] === 'enabled') {
                // Trigger auto-sync asynchronously
                error_log("🚀 Triggering auto-sync for student $studentId, document $documentId");
                
                // Call auto_sync_processor.php asynchronously
                $autoSyncUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . 
                              '://' . $_SERVER['HTTP_HOST'] . 
                              dirname($_SERVER['SCRIPT_NAME']) . '/AdminAccount/auto_sync_processor.php';
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $autoSyncUrl,
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT_MS => 500, // Very short timeout - fire and forget
                    CURLOPT_NOSIGNAL => 1,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'trigger' => 'document_upload',
                        'student_id' => $studentId,
                        'document_id' => $documentId
                    ])
                ]);
                
                curl_exec($ch);
                curl_close($ch);
                
                error_log("✅ Auto-sync triggered for document upload");
            }
        }
    }
    
    return $result;
}

/**
 * Create document records for a student
 * 
 * @param int $studentId The ID of the student
 * @param string $studentType The type of student (Regular or Transferee)
 * @return int Number of document records created
 */
function createDocumentRecords($studentId, $studentType) {
    global $conn;
    
    // Get document types for this student type
    $sql = "SELECT id FROM document_types 
            WHERE (required_for = ? OR required_for = 'All')
            AND is_active = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $studentType);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $created = 0;
    
    while ($row = $result->fetch_assoc()) {
        $docTypeId = $row["id"];
        
        // Check if record already exists
        $checkSql = "SELECT id FROM student_documents 
                    WHERE student_id = ? AND document_type_id = ?";
        
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $studentId, $docTypeId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult->num_rows == 0) {
            // Create new record
            $insertSql = "INSERT INTO student_documents 
                         (student_id, document_type_id, is_submitted, submission_date) 
                         VALUES (?, ?, 0, NULL)";
            
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("ii", $studentId, $docTypeId);
            $insertStmt->execute();
            $insertStmt->close();
            
            $created++;
        }
    }
    
    return $created;
}

/**
 * Example of how to use these functions in your form processing code:
 * 
 * // After inserting the student and getting the student ID
 * $studentId = $conn->insert_id;
 * $studentType = "Regular"; // or "Transferee"
 * 
 * // Create document records
 * createDocumentRecords($studentId, $studentType);
 * 
 * // Handle file uploads
 * $uploadedCount = 0;
 * $requiredDocs = 0;
 * 
 * // Get document types for this student
 * $docTypesSql = "SELECT id FROM document_types 
 *                WHERE (required_for = ? OR required_for = 'All')
 *                AND is_active = 1";
 * $docTypesStmt = $conn->prepare($docTypesSql);
 * $docTypesStmt->bind_param("s", $studentType);
 * $docTypesStmt->execute();
 * $docTypesResult = $docTypesStmt->get_result();
 * 
 * while ($docType = $docTypesResult->fetch_assoc()) {
 *     $requiredDocs++;
 *     $docTypeId = $docType["id"];
 *     
 *     // Check if a file was uploaded for this document
 *     $fileInputName = "document_" . $docTypeId;
 *     
 *     if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]["error"] == 0) {
 *         if (handleDocumentUpload($studentId, $docTypeId, $_FILES[$fileInputName])) {
 *             $uploadedCount++;
 *         }
 *     }
 * }
 * 
 * $docTypesStmt->close();
 * 
 * // Display success message
 * echo "Student added successfully! Uploaded " . $uploadedCount . " out of " . $requiredDocs . " required documents.";
 */
?>