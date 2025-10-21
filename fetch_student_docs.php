<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connect.php';
header('Content-Type: application/json');

// Get student ID from request
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student_type = isset($_GET['type']) ? $_GET['type'] : '';

if (!$student_id) {
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

try {
    // Get student info first
    $sql_student = "SELECT student_id as student_number, first_name, last_name, student_type 
                    FROM students WHERE id = ?";
    $stmt_student = $conn->prepare($sql_student);
    $stmt_student->bind_param('i', $student_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();
    $student_info = $result_student->fetch_assoc();
    
    if (!$student_info) {
        throw new Exception("Student not found");
    }
    
    $studentInfo = [
        'name' => $student_info['first_name'] . ' ' . $student_info['last_name'],
        'student_id' => $student_info['student_number'],
        'type' => $student_info['student_type']
    ];
    
    // Get all documents for this student
    $sql = "SELECT 
                dt.id as doc_type_id,
                dt.doc_name,
                dt.doc_code,
                dt.description,
                sd.id as doc_id,
                sd.file_name,
                sd.original_filename,
                sd.file_path,
                sd.is_submitted,
                sd.submission_date,
                sd.notes,
                sd.file_size,
                sd.file_type
            FROM document_types dt
            LEFT JOIN student_documents sd 
                ON dt.id = sd.document_type_id 
                AND sd.student_id = ?
            WHERE (dt.required_for = 'All' OR dt.required_for = ?)
            AND dt.is_active = 1
            ORDER BY dt.doc_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $student_id, $student_info['student_type']);
    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    
    while ($row = $result->fetch_assoc()) {
        $fileExists = false;
        $webPath = '';
        
        if (!empty($row['file_path'])) {
            // Check various path possibilities for the file
            $pathVariations = [
                $row['file_path'],
                __DIR__ . '/' . $row['file_path'],
                __DIR__ . '/uploads/' . basename($row['file_path'])
            ];
            
            foreach ($pathVariations as $path) {
                if (file_exists($path)) {
                    $fileExists = true;
                    // Standardize web path to be relative to the application root
                    $webPath = 'uploads/' . basename($path);
                    break;
                }
            }
        }

        $documents[] = [
            'id' => $row['doc_id'],
            'typeId' => $row['doc_type_id'],
            'name' => $row['doc_name'],
            'code' => $row['doc_code'],
            'description' => $row['description'],
            'submitted' => $row['is_submitted'] == 1,
            'fileName' => $row['file_name'],
            'originalName' => $row['original_filename'],
            'path' => $webPath,
            'exists' => $fileExists,
            'submissionDate' => $row['submission_date'],
            'notes' => $row['notes']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'student' => $studentInfo,
        'documents' => $documents
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

$stmt->close();
$conn->close();
?>
