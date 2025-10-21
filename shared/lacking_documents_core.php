<?php
/**
 * Core functionality for handling lacking documents
 * Shared between admin and staff interfaces
 */

function getLackingDocumentsData($conn, $searchTerm = '') {
    // Search functionality
    $searchCondition = '';
    $searchParams = [];

    if (!empty($searchTerm)) {
        $searchTerm = "%{$searchTerm}%";
        $searchCondition = "AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.course LIKE ?)";
        $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    // Get students with missing documents
    $sql = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.course, s.year_level, 
            s.student_type, s.status, s.date_added,
            (SELECT COUNT(*) FROM document_types dt 
             WHERE (dt.required_for = 'All' OR dt.required_for = s.student_type)) as required_docs,
            (SELECT COUNT(*) FROM student_documents sd 
             JOIN document_types dt ON sd.document_type_id = dt.id
             WHERE sd.student_id = s.id AND sd.is_submitted = 1 
             AND (dt.required_for = 'All' OR dt.required_for = s.student_type)) as submitted_docs
            FROM students s
            WHERE s.status = 'incomplete' $searchCondition
            ORDER BY s.date_added DESC";

    $stmt = $conn->prepare($sql);

    if (!empty($searchParams)) {
        $types = str_repeat('s', count($searchParams));
        $stmt->bind_param($types, ...$searchParams);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Prepare data for display
    $incompleteStudents = [];

    while ($row = $result->fetch_assoc()) {
        // Get missing documents for this student using the function from db_connect.php
        if (function_exists('getMissingDocuments')) {
            $missingDocs = getMissingDocuments($conn, $row['id']);
        } else {
            // Fallback implementation if the function doesn't exist
            $missingDocs = fetchMissingDocuments($conn, $row['id']);
        }
        
        if (!empty($missingDocs)) {
            $row['missing_docs'] = $missingDocs;
            $incompleteStudents[] = $row;
        }
    }

    $stmt->close();
    
    return $incompleteStudents;
}

// Fallback function to get missing documents if getMissingDocuments is not available
function fetchMissingDocuments($conn, $studentId) {
    // Get student type
    $studentTypeSql = "SELECT student_type FROM students WHERE id = ?";
    $studentTypeStmt = $conn->prepare($studentTypeSql);
    $studentTypeStmt->bind_param("i", $studentId);
    $studentTypeStmt->execute();
    $studentTypeResult = $studentTypeStmt->get_result();
    $studentType = $studentTypeResult->fetch_assoc()['student_type'];
    $studentTypeStmt->close();
    
    // Get required documents for this student type
    $requiredDocsSql = "SELECT id, doc_name, doc_code FROM document_types 
                        WHERE required_for = 'All' OR required_for = ?";
    $requiredDocsStmt = $conn->prepare($requiredDocsSql);
    $requiredDocsStmt->bind_param("s", $studentType);
    $requiredDocsStmt->execute();
    $requiredDocsResult = $requiredDocsStmt->get_result();
    
    $requiredDocs = [];
    while ($row = $requiredDocsResult->fetch_assoc()) {
        $requiredDocs[] = $row;
    }
    $requiredDocsStmt->close();
    
    // Get submitted documents for this student
    $submittedDocsSql = "SELECT document_type_id FROM student_documents 
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

// Process document upload functionality
function processDocumentUpdate($conn, $postData, $files) {
    $updateSuccess = false;
    $updateMessage = '';
    $updateError = '';
    
    if (isset($postData['update_documents'])) {
        $studentId = $postData['student_id'];
        $recordId = $postData['record_id'];
        
        // Define upload directory
        $upload_dir = "../uploads/";
        
        // Ensure the directory exists
        if (!file_exists($upload_dir) && !is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Get document types that were updated
        $updatedDocs = [];
        $uploadedFiles = [];
        $atLeastOneFileUploaded = false;
        
        // Process each document upload
        foreach ($postData as $key => $value) {
            // Check if this is a document submission flag
            if (strpos($key, '_submitted') !== false && $value == 1) {
                $docCode = str_replace('_submitted', '', $key);
                $fileInputName = $docCode . '_file';
                
                // Continue with existing document upload logic...
                // Get document type ID
                $docTypeSql = "SELECT id FROM document_types WHERE doc_code = ?";
                $docTypeStmt = $conn->prepare($docTypeSql);
                $docTypeStmt->bind_param('s', $docCode);
                $docTypeStmt->execute();
                $docTypeResult = $docTypeStmt->get_result();
                
                if ($docTypeResult->num_rows > 0) {
                    $docTypeId = $docTypeResult->fetch_assoc()['id'];
                    $updatedDocs[] = $docTypeId;
                    
                    // Handle file upload if provided
                    if (isset($files[$fileInputName]) && $files[$fileInputName]['error'] == 0) {
                        $atLeastOneFileUploaded = true;
                        
                        // Generate unique filename
                        $fileExtension = pathinfo($files[$fileInputName]['name'], PATHINFO_EXTENSION);
                        $newFileName = $studentId . '_' . $docCode . '_' . time() . '.' . $fileExtension;
                        $targetFile = $upload_dir . $newFileName;
                        
                        // Get file info
                        $fileName = $files[$fileInputName]['name'];
                        $fileTmpName = $files[$fileInputName]['tmp_name'];
                        $fileSize = $files[$fileInputName]['size'];
                        $fileType = $files[$fileInputName]['type'];
                        
                        // Handle the rest of the file upload logic...
                        // Check if file already exists using MD5 hash
                        $fileHash = md5_file($fileTmpName);
                        
                        // Check if this hash already exists in the database
                        $hashSql = "SELECT file_path FROM document_files WHERE file_hash = ?";
                        $hashStmt = $conn->prepare($hashSql);
                        $hashStmt->bind_param("s", $fileHash);
                        $hashStmt->execute();
                        $hashResult = $hashStmt->get_result();
                        
                        $filePath = '';
                        
                        if ($hashResult->num_rows > 0) {
                            // File already exists, use the existing path
                            $existingFile = $hashResult->fetch_assoc();
                            $filePath = $existingFile['file_path'];
                        } else {
                            // Move uploaded file
                            if (move_uploaded_file($fileTmpName, $targetFile)) {
                                // Store file hash in database
                                $hashInsertSql = "INSERT INTO document_files (file_hash, file_path, file_size) VALUES (?, ?, ?)";
                                $hashInsertStmt = $conn->prepare($hashInsertSql);
                                // Store normalized path format
                                $normalizedPath = "uploads/" . basename($targetFile);
                                $hashInsertStmt->bind_param("ssi", $fileHash, $normalizedPath, $fileSize);
                                $hashInsertStmt->execute();
                                $hashInsertStmt->close();
                                
                                $filePath = $normalizedPath;
                            }
                        }
                        
                        $hashStmt->close();
                        
                        if (!empty($filePath)) {
                            // Update or insert record logic...
                            // Check if document record already exists
                            $checkSql = "SELECT id FROM student_documents WHERE student_id = ? AND document_type_id = ?";
                            $checkStmt = $conn->prepare($checkSql);
                            $checkStmt->bind_param("ii", $recordId, $docTypeId);
                            $checkStmt->execute();
                            $checkResult = $checkStmt->get_result();
                            
                            if ($checkResult->num_rows > 0) {
                                // Update existing record
                                $docId = $checkResult->fetch_assoc()['id'];
                                $updateSql = "UPDATE student_documents SET 
                                              file_name = ?, 
                                              file_path = ?, 
                                              original_filename = ?, 
                                              file_size = ?, 
                                              file_type = ?, 
                                              is_submitted = 1, 
                                              submission_date = NOW() 
                                              WHERE id = ?";
                                
                                $updateStmt = $conn->prepare($updateSql);
                                $updateStmt->bind_param("sssssi", 
                                    $newFileName, 
                                    $filePath, 
                                    $fileName, 
                                    $fileSize, 
                                    $fileType, 
                                    $docId
                                );
                                
                                if ($updateStmt->execute()) {
                                    $uploadedFiles[] = $docCode;
                                }
                                
                                $updateStmt->close();
                            } else {
                                // Insert new record
                                $insertSql = "INSERT INTO student_documents 
                                             (student_id, document_type_id, file_name, file_path, original_filename, file_size, file_type, is_submitted, submission_date) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
                                
                                $insertStmt = $conn->prepare($insertSql);
                                $insertStmt->bind_param("iisssss", 
                                    $recordId, 
                                    $docTypeId, 
                                    $newFileName, 
                                    $filePath, 
                                    $fileName, 
                                    $fileSize, 
                                    $fileType
                                );
                                
                                if ($insertStmt->execute()) {
                                    $uploadedFiles[] = $docCode;
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
        
        // Update student status based on document completeness
        if ($atLeastOneFileUploaded) {
            // Use the updateStudentStatus function from db_connect.php
            if (function_exists('updateStudentStatus')) {
                updateStudentStatus($conn, $recordId);
            } else {
                // Fallback if the function doesn't exist
                $status = checkStudentDocumentStatus($conn, $recordId);
                $updateSql = "UPDATE students SET status = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("si", $status, $recordId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            $updateSuccess = true;
            $updateMessage = "Documents updated successfully!";
        } else if (!empty($updatedDocs)) {
            // No files were uploaded but form was submitted
            $updateSuccess = false;
            $updateError = "No files were uploaded. Please select at least one file to upload.";
        }
    }
    
    return [
        'updateSuccess' => $updateSuccess,
        'updateMessage' => $updateMessage,
        'updateError' => $updateError
    ];
}

// Helper function to check student document status
function checkStudentDocumentStatus($conn, $studentId) {
    // Get required documents count
    $studentTypeSql = "SELECT student_type FROM students WHERE id = ?";
    $studentTypeStmt = $conn->prepare($studentTypeSql);
    $studentTypeStmt->bind_param("i", $studentId);
    $studentTypeStmt->execute();
    $studentTypeResult = $studentTypeStmt->get_result();
    $studentType = $studentTypeResult->fetch_assoc()['student_type'];
    $studentTypeStmt->close();
    
    $requiredDocsSql = "SELECT COUNT(*) as total FROM document_types 
                        WHERE required_for = 'All' OR required_for = ?";
    $requiredDocsStmt = $conn->prepare($requiredDocsSql);
    $requiredDocsStmt->bind_param("s", $studentType);
    $requiredDocsStmt->execute();
    $requiredDocsResult = $requiredDocsStmt->get_result();
    $requiredDocsCount = $requiredDocsResult->fetch_assoc()['total'];
    $requiredDocsStmt->close();
    
    // Get submitted documents count
    $submittedDocsSql = "SELECT COUNT(*) as total FROM student_documents 
                         WHERE student_id = ? AND is_submitted = 1";
    $submittedDocsStmt = $conn->prepare($submittedDocsSql);
    $submittedDocsStmt->bind_param("i", $studentId);
    $submittedDocsStmt->execute();
    $submittedDocsResult = $submittedDocsStmt->get_result();
    $submittedDocsCount = $submittedDocsResult->fetch_assoc()['total'];
    $submittedDocsStmt->close();
    
    // Return status
    return ($submittedDocsCount >= $requiredDocsCount) ? 'complete' : 'incomplete';
}
?>