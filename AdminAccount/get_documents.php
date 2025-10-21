<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Set response header
header('Content-Type: application/json');

// Include database connection
require_once '../db_connect.php';

// Get student ID from request
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student_type = isset($_GET['type']) ? $_GET['type'] : 'Regular';

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
    exit;
}

try {
    // Get student information including marriage certificate requirement
    $studentQuery = "SELECT student_type, marriage_cert_required FROM students WHERE id = ?";
    $studentStmt = $conn->prepare($studentQuery);
    
    if (!$studentStmt) {
        throw new Exception('Failed to prepare student query: ' . $conn->error);
    }
    
    $studentStmt->bind_param("i", $student_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $studentInfo = $studentResult->fetch_assoc();
    $studentStmt->close();
    
    if (!$studentInfo) {
        throw new Exception('Student not found');
    }
    
    $actualStudentType = $studentInfo['student_type'];
    $marriageCertRequired = isset($studentInfo['marriage_cert_required']) ? (int)$studentInfo['marriage_cert_required'] : 0;
    
    // Define required documents based on student type and marriage cert requirement
    $requiredDocs = [];
    if ($actualStudentType === 'Regular') {
        $requiredDocs = ['card138', 'moral', 'birth', 'id'];
        // Add marriage certificate only if required
        if ($marriageCertRequired == 1) {
            $requiredDocs[] = 'marriage';
        }
    } else {
        // Transferee
        $requiredDocs = ['moral', 'birth', 'tor', 'honorable', 'gradeslip', 'id'];
        // Add marriage certificate only if required
        if ($marriageCertRequired == 1) {
            $requiredDocs[] = 'marriage';
        }
    }
    
    // Document type names mapping
    $docNames = [
        'card138' => 'Card 138',
        'moral' => 'Certificate of Good Moral',
        'birth' => 'PSA Birth Certificate',
        'marriage' => 'PSA Marriage Certificate',
        'tor' => 'Transcript of Records',
        'honorable' => 'Honorable Dismissal',
        'gradeslip' => 'Grade Slip',
        'id' => '2x2 Picture'
    ];
    
    // Get all document types - FIXED: use doc_name instead of type_name
    $docTypesQuery = "SELECT id, doc_code, doc_name FROM document_types WHERE is_active = 1";
    $docTypesResult = $conn->query($docTypesQuery);
    
    if (!$docTypesResult) {
        throw new Exception('Failed to fetch document types: ' . $conn->error);
    }
    
    $docTypeMap = [];
    while ($docType = $docTypesResult->fetch_assoc()) {
        $docTypeMap[$docType['doc_code']] = [
            'id' => $docType['id'],
            'name' => $docType['doc_name']
        ];
    }
    
    // Get submitted documents for this student - FIXED: use doc_name instead of type_name
    $submittedQuery = "SELECT sd.*, dt.doc_code, dt.doc_name 
                      FROM student_documents sd 
                      JOIN document_types dt ON sd.document_type_id = dt.id 
                      WHERE sd.student_id = ? AND sd.is_submitted = 1";
    $submittedStmt = $conn->prepare($submittedQuery);
    
    if (!$submittedStmt) {
        throw new Exception('Failed to prepare submitted documents query: ' . $conn->error);
    }
    
    $submittedStmt->bind_param("i", $student_id);
    $submittedStmt->execute();
    $submittedResult = $submittedStmt->get_result();
    
    $submittedDocs = [];
    while ($doc = $submittedResult->fetch_assoc()) {
        $submittedDocs[$doc['doc_code']] = $doc;
    }
    $submittedStmt->close();
    
    // Build the documents array - only include required documents
    $documents = [];
    foreach ($requiredDocs as $docCode) {
        if (!isset($docTypeMap[$docCode])) {
            continue; // Skip if document type not found
        }
        
        $docName = $docNames[$docCode] ?? $docTypeMap[$docCode]['name'];
        
        if (isset($submittedDocs[$docCode])) {
            // Document is submitted
            $doc = $submittedDocs[$docCode];
            
            // Check if file exists
            $filePath = $doc['file_path'];
            $fullPath = '../' . $filePath;
            if (!file_exists($fullPath)) {
                // Try without ../ prefix
                $fullPath = $filePath;
            }
            
            $fileExists = file_exists($fullPath);
            
            $documents[] = [
                'name' => $docName,
                'doc_code' => $docCode,
                'submitted' => true,
                'exists' => $fileExists,
                'path' => $doc['file_path'],
                'original_filename' => $doc['original_filename'] ?? '',
                'file_size' => $doc['file_size'] ?? 0,
                'submission_date' => $doc['submission_date'] ?? ''
            ];
        } else {
            // Document not submitted
            $documents[] = [
                'name' => $docName,
                'doc_code' => $docCode,
                'submitted' => false,
                'exists' => false,
                'path' => '',
                'original_filename' => '',
                'file_size' => 0,
                'submission_date' => ''
            ];
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'student_type' => $actualStudentType,
        'marriage_cert_required' => $marriageCertRequired
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_documents.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
