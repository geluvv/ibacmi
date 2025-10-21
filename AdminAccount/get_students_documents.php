<?php
require_once '../db_connect.php';
header('Content-Type: application/json');

// Check if student_id parameter is provided
if (!isset($_GET['student_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Student ID is required'
    ]);
    exit;
}

$studentId = intval($_GET['student_id']);
$status = isset($_GET['status']) ? $_GET['status'] : null;

try {
    // Update column names to match your actual database structure
    // Changed dt.name to dt.doc_name based on your get_documents.php
    $sql = "SELECT 
                sd.id,
                sd.document_type_id,
                sd.student_id,
                sd.file_path,
                sd.file_name,
                sd.original_filename,
                sd.is_submitted,
                sd.submission_date as date_submitted,
                sd.notes,
                dt.doc_name as document_name
            FROM student_documents sd
            JOIN document_types dt ON sd.document_type_id = dt.id
            WHERE sd.student_id = ?";
    
    // If status filter is applied, add it to the query
    if ($status === 'complete') {
        $sql .= " AND sd.is_submitted = 1";
    }
    
    $sql .= " ORDER BY dt.doc_name"; // Also updated here
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    // Add these lines at the end of the file to properly return the data:
    echo json_encode([
        'status' => 'success',
        'documents' => $documents
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>