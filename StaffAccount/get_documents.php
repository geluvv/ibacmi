<?php
// Include database connection
require_once '../db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get student ID and type from request
    $studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $studentType = isset($_GET['type']) ? $_GET['type'] : '';

    if ($studentId <= 0) {
        throw new Exception('Invalid student ID');
    }

    // Get student information
    $studentSql = "SELECT student_type FROM students WHERE id = ?";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->bind_param("i", $studentId);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    
    if ($studentResult->num_rows === 0) {
        throw new Exception('Student not found');
    }
    
    $student = $studentResult->fetch_assoc();
    $actualStudentType = $student['student_type'];
    $studentStmt->close();

    // Get required document types for this student
    $docTypesSql = "SELECT id, doc_name, doc_code 
                   FROM document_types 
                   WHERE required_for = 'All' OR required_for = ?
                   ORDER BY doc_name";
    $docTypesStmt = $conn->prepare($docTypesSql);
    $docTypesStmt->bind_param("s", $actualStudentType);
    $docTypesStmt->execute();
    $docTypesResult = $docTypesStmt->get_result();

    $documents = [];
    
    while ($docType = $docTypesResult->fetch_assoc()) {
        // Check if document is submitted
        $docSql = "SELECT file_path, is_submitted, submission_date, file_name 
                   FROM student_documents 
                   WHERE student_id = ? AND document_type_id = ?";
        $docStmt = $conn->prepare($docSql);
        $docStmt->bind_param("ii", $studentId, $docType['id']);
        $docStmt->execute();
        $docResult = $docStmt->get_result();

        $document = [
            'name' => $docType['doc_name'],
            'code' => $docType['doc_code'],
            'submitted' => false,
            'path' => null,
            'exists' => false,
            'submission_date' => null
        ];

        if ($docResult->num_rows > 0) {
            $docData = $docResult->fetch_assoc();
            $document['submitted'] = ($docData['is_submitted'] == 1);
            $document['submission_date'] = $docData['submission_date'];
            
            if ($docData['file_path']) {
                // Clean up the path - handle both formats
                $cleanPath = str_replace(['../', '.\\', '\\'], ['', '', '/'], $docData['file_path']);
                if (strpos($cleanPath, 'uploads/') === 0) {
                    $document['path'] = '../' . $cleanPath;
                } else {
                    $document['path'] = '../uploads/' . basename($cleanPath);
                }
                
                // Check if file exists
                $fullPath = __DIR__ . '/' . $document['path'];
                $document['exists'] = file_exists($fullPath);
            }
        }

        $documents[] = $document;
        $docStmt->close();
    }

    $docTypesStmt->close();

    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'student_id' => $studentId,
        'student_type' => $actualStudentType,
        'documents' => $documents
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'student_id' => $studentId ?? 0
    ]);
} finally {
    // Close connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>
