<?php
require_once '../db_connect.php';
require_once 'backup.php';

header('Content-Type: application/json');

/**
 * Archive students who graduated 6+ years ago
 */
function archiveOldStudents() {
    global $conn;
    
    $sixYearsAgo = date('Y') - 6;
    $archiveSchoolYear = ($sixYearsAgo - 1) . '-' . $sixYearsAgo;
    
    error_log("=== ARCHIVING STUDENTS FROM $archiveSchoolYear AND EARLIER ===");
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("INSERT INTO backup_logs 
                               (backup_type, storage_type, status, created_at) 
                               VALUES ('automatic', 'cloud', 'in_progress', NOW())");
        $stmt->execute();
        $archiveLogId = $conn->insert_id;
        $stmt->close();
        
        $query = "SELECT DISTINCT s.id, s.student_id, s.first_name, s.last_name,
                         s.year_level, s.date_added
                  FROM students s
                  INNER JOIN student_documents sd ON s.id = sd.student_id
                  WHERE YEAR(s.date_added) <= ?
                  AND sd.is_submitted = 1
                  AND s.status != 'archived'";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $sixYearsAgo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $archivedStudents = 0;
        $archivedFiles = 0;
        $totalSize = 0;
        
        $archiveFolderName = "IBACMI Archive - {$archiveSchoolYear} and Earlier";
        $archiveFolderId = createGoogleDriveFolder($archiveFolderName);
        
        if (!$archiveFolderId) {
            throw new Exception("Failed to create archive folder on Google Drive");
        }
        
        while ($student = $result->fetch_assoc()) {
            $studentFolderName = "{$student['last_name']}_{$student['first_name']}_{$student['student_id']}";
            $studentArchiveFolderId = createGoogleDriveFolder($studentFolderName, $archiveFolderId);
            
            if (!$studentArchiveFolderId) {
                error_log("Failed to create archive folder for student: {$student['student_id']}");
                continue;
            }
            
            $docsQuery = "SELECT sd.id, sd.file_path, sd.original_filename, dt.doc_name
                         FROM student_documents sd
                         INNER JOIN document_types dt ON sd.document_type_id = dt.id
                         WHERE sd.student_id = ? AND sd.is_submitted = 1";
            $docsStmt = $conn->prepare($docsQuery);
            $docsStmt->bind_param("i", $student['id']);
            $docsStmt->execute();
            $docsResult = $docsStmt->get_result();
            
            $studentFileCount = 0;
            
            while ($doc = $docsResult->fetch_assoc()) {
                $localPath = findLocalFile($doc['file_path']);
                
                if ($localPath && file_exists($localPath)) {
                    $fileExtension = pathinfo($doc['original_filename'], PATHINFO_EXTENSION);
                    $fileName = $doc['doc_name'] . '.' . $fileExtension;
                    
                    $archivedFileId = uploadFileToGoogleDrive($localPath, $fileName, $studentArchiveFolderId);
                    
                    if ($archivedFileId) {
                        $fileSize = filesize($localPath);
                        
                        recordGoogleDriveUpload($archiveLogId, $student['id'], 
                            $doc['id'], $archivedFileId, $studentArchiveFolderId, 
                            $localPath, $fileSize, 'archived');
                        
                        $archivedFiles++;
                        $totalSize += $fileSize;
                        $studentFileCount++;
                        
                        $updateDoc = "UPDATE student_documents SET 
                                     google_drive_file_id = ?, 
                                     notes = CONCAT(IFNULL(notes, ''), '\nArchived on: ', NOW())
                                     WHERE id = ?";
                        $updateStmt = $conn->prepare($updateDoc);
                        $updateStmt->bind_param("si", $archivedFileId, $doc['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }
            }
            
            $docsStmt->close();
            
            if ($studentFileCount > 0) {
                $updateStudent = "UPDATE students SET 
                                 status = 'archived'
                                 WHERE id = ?";
                $updateStmt = $conn->prepare($updateStudent);
                $updateStmt->bind_param("i", $student['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                $archivedStudents++;
                error_log("✓ Archived student: {$studentFolderName} ({$studentFileCount} files)");
            }
        }
        
        $stmt->close();
        
        $updateLog = "UPDATE backup_logs SET 
                     student_count = ?, 
                     file_count = ?, 
                     backup_size = ?,
                     backup_path = ?,
                     status = 'success',
                     completed_at = NOW()
                     WHERE id = ?";
        $stmt = $conn->prepare($updateLog);
        $stmt->bind_param("iiisi", $archivedStudents, $archivedFiles, 
                          $totalSize, $archiveFolderName, $archiveLogId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        error_log("=== ARCHIVAL COMPLETE ===");
        
        return [
            'success' => true,
            'students' => $archivedStudents,
            'files' => $archivedFiles,
            'size' => $totalSize,
            'folder_name' => $archiveFolderName
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Archival failed: " . $e->getMessage());
        
        $stmt = $conn->prepare("UPDATE backup_logs SET status = 'failed', error_message = ? WHERE id = ?");
        $errorMsg = $e->getMessage();
        $stmt->bind_param("si", $errorMsg, $archiveLogId);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_old_students') {
    $result = archiveOldStudents();
    echo json_encode($result);
    exit();
}
?>