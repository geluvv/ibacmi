<?php
// This script will update the is_submitted flag for all documents that have a file path
// It will help fix cases where documents have files but are not marked as submitted

// Include database connection
require_once 'db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Document Status Updater</h1>";
echo "<p>This script will update the is_submitted flag for all documents that have a file path.</p>";

// Get all document records with file paths but not marked as submitted
$sql = "SELECT id, student_id, document_type_id, file_path FROM student_documents 
        WHERE file_path IS NOT NULL AND file_path != '' AND is_submitted = 0";
$result = $conn->query($sql);

if (!$result) {
    die("Error fetching documents: " . $conn->error);
}

$totalRecords = $result->num_rows;
$updatedRecords = 0;
$errorRecords = 0;

echo "<p>Found $totalRecords document records with file paths but not marked as submitted.</p>";

if ($totalRecords > 0) {
    echo "<ul>";
    
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $studentId = $row['student_id'];
        $documentTypeId = $row['document_type_id'];
        $filePath = $row['file_path'];
        
        // Update the record to mark it as submitted
        $updateSql = "UPDATE student_documents SET is_submitted = 1, submission_date = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $id);
        
        if ($updateStmt->execute()) {
            echo "<li>Updated document ID $id for student ID $studentId, document type ID $documentTypeId</li>";
            $updatedRecords++;
        } else {
            echo "<li style='color:red'>Error updating document ID $id: " . $updateStmt->error . "</li>";
            $errorRecords++;
        }
        
        $updateStmt->close();
    }
    
    echo "</ul>";
}

// Now update student status based on document completeness
echo "<h2>Updating Student Status</h2>";

$studentsSql = "SELECT id, student_type FROM students";
$studentsResult = $conn->query($studentsSql);

$totalStudents = $studentsResult->num_rows;
$updatedStudents = 0;
$errorStudents = 0;

echo "<p>Checking status for $totalStudents students.</p>";

if ($totalStudents > 0) {
    echo "<ul>";
    
    while ($student = $studentsResult->fetch_assoc()) {
        $studentId = $student['id'];
        $studentType = $student['student_type'];
        
        // Get required documents count
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
        
        // Determine status
        $status = ($submittedDocsCount >= $requiredDocsCount) ? 'complete' : 'incomplete';
        
        // Update student status
        $updateStatusSql = "UPDATE students SET status = ? WHERE id = ?";
        $updateStatusStmt = $conn->prepare($updateStatusSql);
        $updateStatusStmt->bind_param("si", $status, $studentId);
        
        if ($updateStatusStmt->execute()) {
            echo "<li>Updated student ID $studentId status to $status ($submittedDocsCount/$requiredDocsCount documents)</li>";
            $updatedStudents++;
        } else {
            echo "<li style='color:red'>Error updating student ID $studentId: " . $updateStatusStmt->error . "</li>";
            $errorStudents++;
        }
        
        $updateStatusStmt->close();
    }
    
    echo "</ul>";
}

echo "<h2>Summary</h2>";
echo "<p>Document Status Updates:</p>";
echo "<p>Total records: $totalRecords</p>";
echo "<p>Updated: $updatedRecords</p>";
echo "<p>Errors: $errorRecords</p>";

echo "<p>Student Status Updates:</p>";
echo "<p>Total students: $totalStudents</p>";
echo "<p>Updated: $updatedStudents</p>";
echo "<p>Errors: $errorStudents</p>";

if ($updatedRecords > 0 || $updatedStudents > 0) {
    echo "<p style='color:green;font-weight:bold'>Document statuses have been successfully updated!</p>";
    echo "<p>You should now be able to view all documents in the system.</p>";
} else {
    echo "<p style='color:blue;font-weight:bold'>No records needed updating.</p>";
    echo "<p>If you're still having issues, check the file permissions and make sure the uploads directory is web-accessible.</p>";
}

echo "<p><a href='Document.php'>Return to Student Records</a></p>";

$conn->close();
?>
