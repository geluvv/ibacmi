<?php
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

class BackupHandler {
    private $conn;
    private $backupDir;
    private $uploadsDir;
    private $timestamp;
    private $username;
    
    public function __construct($conn, $timestamp, $username) {
        $this->conn = $conn;
        $this->timestamp = $timestamp;
        $this->username = $username;
        $this->backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
        $this->uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
        
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
    }

    public function createBackup($useGoogleDrive = false, $googleAccessToken = null) {
        try {
            // Enable error logging
            error_log("Starting backup process...");
            
            // Create timestamp-based backup folder
            $backupName = 'backups' . date('Y-m-d_H-i-s', strtotime($this->timestamp));
            $backupPath = $this->backupDir . DIRECTORY_SEPARATOR . $backupName;
            mkdir($backupPath, 0777, true);

            // Get all student documents
            $students = $this->getStudents();
            error_log("Found " . count($students) . " students");
            
            // Create backup structure
            foreach ($students as $student) {
                error_log("Processing student: {$student['first_name']} {$student['last_name']}");
                error_log("Document count: " . count($student['documents']));
                
                $studentDir = $backupPath . DIRECTORY_SEPARATOR . 
                             $this->sanitizeFileName($student['first_name'] . '_' . 
                             $student['last_name'] . '_' . 
                             $student['student_id']);
                
                if (!mkdir($studentDir, 0777, true)) {
                    throw new Exception("Failed to create directory: " . $studentDir);
                }
                
                // Copy student documents
                $this->copyStudentFiles($student, $studentDir);
            }

            // Create ZIP file
            $zipFile = $this->backupDir . DIRECTORY_SEPARATOR . $backupName . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($backupPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($backupPath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                
                $zip->close();
                
                // Log the backup
                $this->logBackup($backupName . '.zip', $useGoogleDrive ? 'cloud' : 'local', 'success');
                
                // Clean up the temporary directory
                $this->removeDirectory($backupPath);
                
                if ($useGoogleDrive && $googleAccessToken) {
                    require_once 'GoogleDriveHandler.php';
                    $driveHandler = new GoogleDriveHandler($googleAccessToken);
                    
                    $uploadResult = $driveHandler->uploadBackup($zipFile);
                    
                    if ($uploadResult['success']) {
                        // Log successful cloud backup
                        $this->logBackup($backupName . '.zip', 'cloud', 'success');
                        
                        return [
                            'status' => 'success',
                            'message' => 'Backup uploaded to Google Drive successfully',
                            'file' => basename($zipFile),
                            'driveFileId' => $uploadResult['fileId'],
                            'webViewLink' => $uploadResult['webViewLink']
                        ];
                    } else {
                        throw new Exception('Google Drive upload failed: ' . $uploadResult['message']);
                    }
                }

                // Return local backup result
                $this->logBackup($backupName . '.zip', 'local', 'success');
                return [
                    'status' => 'success',
                    'message' => 'Local backup created successfully',
                    'file' => basename($zipFile)
                ];

            }
            
            throw new Exception("Failed to create ZIP file");

        } catch (Exception $e) {
            if (isset($backupPath) && file_exists($backupPath)) {
                $this->removeDirectory($backupPath);
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getStudents() {
        // Modified query to get complete student and document information
        $query = "SELECT s.*, 
                  sd.file_path, 
                  sd.document_type_id,
                  dt.doc_name,
                  dt.doc_code
                  FROM students s 
                  LEFT JOIN student_documents sd ON s.id = sd.student_id 
                  LEFT JOIN document_types dt ON sd.document_type_id = dt.id
                  WHERE sd.is_submitted = 1 AND sd.file_path IS NOT NULL
                  ORDER BY s.id";
        
        $result = $this->conn->query($query);
        if (!$result) {
            throw new Exception("Database query failed: " . $this->conn->error);
        }
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $studentId = $row['id'];
            if (!isset($students[$studentId])) {
                $students[$studentId] = [
                    'id' => $row['id'],
                    'student_id' => $row['student_id'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'course' => $row['course'],
                    'year_level' => $row['year_level'],
                    'documents' => []
                ];
            }
            
            if ($row['file_path']) {
                $students[$studentId]['documents'][] = [
                    'file_path' => $row['file_path'],
                    'doc_name' => $row['doc_name'],
                    'doc_code' => $row['doc_code']
                ];
            }
        }
        
        return array_values($students);
    }

    private function copyStudentFiles($student, $destDir) {
        // Create documents directory
        $documentsDir = $destDir . DIRECTORY_SEPARATOR . 'documents';
        if (!file_exists($documentsDir)) {
            mkdir($documentsDir, 0777, true);
        }

        // Counter for successful copies
        $copiedFiles = 0;

        // Debug log
        error_log("Processing student: {$student['first_name']} {$student['last_name']}");
        
        foreach ($student['documents'] as $document) {
            // Construct source path - use uploads\ as base directory
            $sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 
                         str_replace(['/', '\\uploads\\'], ['\\', ''], $document['file_path']);
            
            // Debug log
            error_log("Trying to copy file from: {$sourcePath}");

            if (file_exists($sourcePath) && is_file($sourcePath)) {
                // Create descriptive filename with document type
                $fileName = $document['doc_code'] . '_' . basename($sourcePath);
                $destPath = $documentsDir . DIRECTORY_SEPARATOR . $fileName;

                // Copy the file
                if (copy($sourcePath, $destPath)) {
                    $copiedFiles++;
                    error_log("Successfully copied file to: {$destPath}");
                } else {
                    error_log("Failed to copy file: {$sourcePath} to {$destPath}");
                    throw new Exception("Failed to copy document: {$fileName}");
                }
            } else {
                // Try alternative path without 'uploads' in the middle
                $altPath = str_replace('uploads\\uploads\\', 'uploads\\', $sourcePath);
                error_log("File not found, trying alternative path: {$altPath}");
                
                if (file_exists($altPath) && is_file($altPath)) {
                    $fileName = $document['doc_code'] . '_' . basename($altPath);
                    $destPath = $documentsDir . DIRECTORY_SEPARATOR . $fileName;
                    
                    if (copy($altPath, $destPath)) {
                        $copiedFiles++;
                        error_log("Successfully copied file from alternative path to: {$destPath}");
                    }
                } else {
                    error_log("File not found at either path: {$sourcePath} or {$altPath}");
                }
            }
        }

        // Create info file only if we have copied files
        if ($copiedFiles > 0) {
            $info = "Student Information:\n";
            $info .= "Student ID: {$student['student_id']}\n";
            $info .= "Name: {$student['first_name']} {$student['middle_name']} {$student['last_name']}\n";
            $info .= "Course: {$student['course']}\n";
            $info .= "Year Level: {$student['year_level']}\n";
            file_put_contents($destDir . DIRECTORY_SEPARATOR . 'student_info.txt', $info);
        }

        return $copiedFiles > 0;
    }

    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    if (is_dir("$dir/$file")) {
                        $this->removeDirectory("$dir/$file");
                    } else {
                        unlink("$dir/$file");
                    }
                }
            }
            rmdir($dir);
        }
    }

    private function logBackup($filename, $type, $status) {
        $sql = "INSERT INTO backup_logs (filename, storage_type, created_by, created_at, status) 
                VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssss', $filename, $type, $this->username, $status);
        return $stmt->execute();
    }

    private function sanitizeFileName($filename) {
        // Remove any characters that aren't allowed in filenames
        $filename = preg_replace('/[^a-zA-Z0-9-_.]/', '_', $filename);
        return trim($filename, '._');
    }
}
?>