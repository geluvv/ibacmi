<?php
// filepath: c:\xampp\htdocs\ibacmi\AdminAccount\archival_api.php
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/archival_errors.log');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 0);

require_once '../db_connect.php';

date_default_timezone_set('Asia/Manila');
session_start();

/**
 * Send JSON response
 */
function sendJsonResponse($data) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Get archival settings
 */
function getArchivalSettings() {
    global $conn;
    
    // Create archival_settings table if it doesn't exist
    $tableCheck = $conn->query("SHOW TABLES LIKE 'archival_settings'");
    if ($tableCheck->num_rows == 0) {
        $createTable = "
            CREATE TABLE IF NOT EXISTS `archival_settings` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `timing_option` ENUM('immediate','6_months','1_year','2_years') DEFAULT '1_year',
                `auto_archival_enabled` TINYINT(1) DEFAULT 1,
                `last_run` DATETIME DEFAULT NULL,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $conn->query($createTable);
        $conn->query("INSERT INTO archival_settings (timing_option, auto_archival_enabled) 
                     VALUES ('1_year', 1)");
    }
    
    // Create archive_logs table if it doesn't exist
    $archiveLogsCheck = $conn->query("SHOW TABLES LIKE 'archive_logs'");
    if ($archiveLogsCheck->num_rows == 0) {
        $createArchiveLogsTable = "
            CREATE TABLE IF NOT EXISTS `archive_logs` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `school_year` VARCHAR(20) NOT NULL,
                `archival_type` ENUM('school_year','graduation') DEFAULT 'school_year',
                `student_id` INT(11) DEFAULT NULL,
                `student_name` VARCHAR(255) DEFAULT NULL,
                `graduation_date` DATE DEFAULT NULL,
                `student_count` INT(11) DEFAULT 0,
                `file_count` INT(11) DEFAULT 0,
                `archive_size` BIGINT(20) DEFAULT 0,
                `storage_type` ENUM('local','cloud','deleted') DEFAULT 'cloud',
                `archive_path` VARCHAR(500) DEFAULT NULL,
                `status` ENUM('success','failed','in_progress') DEFAULT 'in_progress',
                `error_message` TEXT DEFAULT NULL,
                `archived_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $conn->query($createArchiveLogsTable);
        error_log("✅ Created archive_logs table");
    }
    
    $query = "SELECT * FROM archival_settings LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return [
        'timing_option' => '1_year',
        'auto_archival_enabled' => 1,
        'last_run' => null
    ];
}

/**
 * Update archival settings
 */
function updateArchivalSettings($timingOption, $autoEnabled) {
    global $conn;
    
    $checkQuery = "SELECT id FROM archival_settings LIMIT 1";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE archival_settings SET 
            timing_option = ?, 
            auto_archival_enabled = ?,
            updated_at = NOW()
            WHERE id = 1");
        $stmt->bind_param("si", $timingOption, $autoEnabled);
    } else {
        $stmt = $conn->prepare("INSERT INTO archival_settings 
            (timing_option, auto_archival_enabled) 
            VALUES (?, ?)");
        $stmt->bind_param("si", $timingOption, $autoEnabled);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get students eligible for archival
 * Based on school year end dates - 4th year students graduate when school year ends
 */
function getEligibleStudents() {
    global $conn;
    
    try {
        $settings = getArchivalSettings();
        $timingOption = $settings['timing_option'];
        
        error_log("=== CHECKING ELIGIBLE STUDENTS ===");
        error_log("Timing option: $timingOption");
        
        $today = date('Y-m-d');
        error_log("Today's date: $today");
        
        // Get the active school year's end date (this is when students graduate)
        $syQuery = "SELECT school_year, end_date FROM school_years WHERE is_active = 1 LIMIT 1";
        $syResult = $conn->query($syQuery);
        
        if (!$syResult || $syResult->num_rows == 0) {
            error_log("⚠️ No active school year found");
            return [];
        }
        
        $schoolYear = $syResult->fetch_assoc();
        $graduationDate = $schoolYear['end_date'];
        
        error_log("Active school year: {$schoolYear['school_year']}, End date (graduation): $graduationDate");
        
        // Check if school year has ended
        if ($graduationDate > $today) {
            $daysUntil = floor((strtotime($graduationDate) - time()) / 86400);
            error_log("School year hasn't ended yet. Days remaining: $daysUntil");
            return [];
        }
        
        // Calculate days since graduation
        $daysSinceGraduation = floor((time() - strtotime($graduationDate)) / 86400);
        error_log("School year ended $daysSinceGraduation days ago");
        
        // Determine eligibility based on timing option
        $daysRequired = 0;
        switch ($timingOption) {
            case 'immediate':
                $daysRequired = 0;
                break;
            case '6_months':
                $daysRequired = 180;
                break;
            case '1_year':
                $daysRequired = 365;
                break;
            case '2_years':
                $daysRequired = 730;
                break;
            default:
                $daysRequired = 365;
        }
        
        error_log("Days required for archival: $daysRequired, Days since graduation: $daysSinceGraduation");
        
        // Check if enough time has passed
        if ($daysSinceGraduation < $daysRequired) {
            $daysRemaining = $daysRequired - $daysSinceGraduation;
            error_log("Not enough time has passed. Days remaining: $daysRemaining");
            return [];
        }
        
        // Get all 4th year students who are not yet archived
        $query = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.year_level,
                  s.is_graduated, s.is_archived, s.status,
                  COALESCE(s.graduation_date, ?) as graduation_date,
                  COUNT(sd.id) as document_count
                  FROM students s
                  LEFT JOIN student_documents sd ON s.id = sd.student_id AND sd.is_submitted = 1
                  WHERE s.year_level = 4
                  AND s.is_archived = 0
                  GROUP BY s.id
                  ORDER BY s.student_id ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $graduationDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            // Auto-mark as graduated and set graduation date if not already
            if ($row['is_graduated'] == 0 || is_null($row['graduation_date'])) {
                $updateStmt = $conn->prepare("UPDATE students 
                    SET is_graduated = 1, 
                        graduation_date = COALESCE(graduation_date, ?),
                        status = 'graduated'
                    WHERE id = ?");
                $updateStmt->bind_param("si", $graduationDate, $row['id']);
                $updateStmt->execute();
                $updateStmt->close();
                $row['is_graduated'] = 1;
                $row['graduation_date'] = $graduationDate;
                $row['status'] = 'graduated';
                error_log("Auto-marked student {$row['student_id']} as graduated with date {$graduationDate}");
            }
            
            $students[] = $row;
            error_log("Found eligible student: {$row['first_name']} {$row['last_name']} (ID: {$row['student_id']}, Year: {$row['year_level']}, Graduation: {$row['graduation_date']}, Documents: {$row['document_count']})");
        }
        
        $stmt->close();
        
        error_log("Total eligible students found: " . count($students));
        
        return $students;
        
    } catch (Exception $e) {
        error_log("Error in getEligibleStudents: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete student and all associated data (NO FILE ARCHIVING)
 * This function permanently removes the student from the active system
 */
function deleteStudentData($studentId) {
    global $conn;
    
    try {
        error_log("Starting deletion for student ID: $studentId");
        
        // Get student info for logging before deletion
        $query = "SELECT student_id, first_name, last_name, graduation_date, is_graduated 
                  FROM students WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
        if (!$student) {
            throw new Exception("Student not found");
        }
        
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
        $studentNumber = $student['student_id'];
        
        error_log("Student details: Name=$studentName, Number=$studentNumber, Graduated={$student['is_graduated']}, GradDate={$student['graduation_date']}");
        
        // Count documents before deletion
        $countQuery = "SELECT COUNT(*) as doc_count FROM student_documents WHERE student_id = ?";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $docCount = $result->fetch_assoc()['doc_count'];
        $stmt->close();
        
        error_log("Deleting student: $studentName ($studentNumber) with $docCount documents");
        
        // First, mark as archived before deletion (for audit trail)
        $markStmt = $conn->prepare("UPDATE students SET is_archived = 1, status = 'archived' WHERE id = ?");
        $markStmt->bind_param("i", $studentId);
        $markStmt->execute();
        $markStmt->close();
        
        error_log("✓ Marked student as archived");
        
        // Delete from student_documents table
        $stmt = $conn->prepare("DELETE FROM student_documents WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $documentsDeleted = $stmt->affected_rows;
        $stmt->close();
        
        error_log("✓ Deleted $documentsDeleted documents");
        
        // Delete from backup_manifest (if exists)
        $stmt = $conn->prepare("DELETE FROM backup_manifest WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $stmt->close();
        
        // Delete from local_backup_manifest (if exists)
        $stmt = $conn->prepare("DELETE FROM local_backup_manifest WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $stmt->close();
        
        // Delete from students table
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentsDeleted = $stmt->affected_rows;
        $stmt->close();
        
        error_log("✓ Deleted student record");
        
        return [
            'student_name' => $studentName,
            'student_number' => $studentNumber,
            'documents_deleted' => $documentsDeleted,
            'graduation_date' => $student['graduation_date']
        ];
        
    } catch (Exception $e) {
        error_log("Error deleting student data: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Archive a single student (DELETE ONLY - NO FILE STORAGE)
 */
function archiveStudent($studentId) {
    global $conn;
    
    $query = "SELECT id, student_id, first_name, last_name, graduation_date 
              FROM students WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    if (!$student['graduation_date']) {
        throw new Exception('Student has no graduation date set');
    }
    
    $studentName = $student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_id'] . ')';
    $schoolYear = date('Y', strtotime($student['graduation_date']));
    
    // Create archive log for tracking purposes only
    $logStmt = $conn->prepare("INSERT INTO archive_logs 
        (school_year, archival_type, student_id, student_name, graduation_date, 
         student_count, file_count, storage_type, status, archived_at) 
        VALUES (?, 'graduation', ?, ?, ?, 1, 0, 'deleted', 'in_progress', NOW())");
    $logStmt->bind_param("siss", $schoolYear, $studentId, $studentName, 
        $student['graduation_date']);
    $logStmt->execute();
    $logId = $conn->insert_id;
    $logStmt->close();
    
    try {
        // DELETE student and all associated data (NO FILE ARCHIVING)
        $result = deleteStudentData($studentId);
        
        // Update archive log with deletion info
        $updateStmt = $conn->prepare("UPDATE archive_logs SET 
            file_count = ?, 
            archive_size = 0, 
            archive_path = 'DELETED - Data removed from system', 
            status = 'success' 
            WHERE id = ?");
        $updateStmt->bind_param("ii", 
            $result['documents_deleted'], 
            $logId);
        $updateStmt->execute();
        $updateStmt->close();
        
        error_log("✅ Student archived (deleted): {$result['student_name']}");
        
        return $result;
        
    } catch (Exception $e) {
        $errorStmt = $conn->prepare("UPDATE archive_logs SET 
            status = 'failed', 
            error_message = ? 
            WHERE id = ?");
        $errorMsg = $e->getMessage();
        $errorStmt->bind_param("si", $errorMsg, $logId);
        $errorStmt->execute();
        $errorStmt->close();
        
        throw $e;
    }
}

/**
 * Run automatic archival (DELETE ELIGIBLE STUDENTS)
 */
function runAutoArchival() {
    global $conn;
    
    error_log("=== AUTO ARCHIVAL STARTED ===");
    
    $settings = getArchivalSettings();
    error_log("Settings: timing_option={$settings['timing_option']}, auto_enabled={$settings['auto_archival_enabled']}");
    
    if (!$settings['auto_archival_enabled']) {
        error_log("Auto-archival is disabled, skipping");
        return [
            'status' => 'skipped',
            'message' => 'Auto-archival is disabled'
        ];
    }
    
    $students = getEligibleStudents();
    error_log("Eligible students count: " . count($students));
    
    if (empty($students)) {
        error_log("No students eligible for archival");
        return [
            'status' => 'success',
            'message' => 'No students eligible for archival',
            'students_archived' => 0,
            'total_files' => 0,
            'total_size' => 0
        ];
    }
    
    $studentsDeleted = 0;
    $totalFilesDeleted = 0;
    $errors = [];
    
    error_log("Processing " . count($students) . " eligible students");
    
    foreach ($students as $student) {
        try {
            error_log("Processing student ID: {$student['id']} - {$student['first_name']} {$student['last_name']}");
            $result = archiveStudent($student['id']);
            $studentsDeleted++;
            $totalFilesDeleted += $result['documents_deleted'];
            
            error_log("✓ Deleted: {$result['student_name']} ({$result['documents_deleted']} documents)");
            
        } catch (Exception $e) {
            $errorMsg = $student['first_name'] . ' ' . $student['last_name'] . ': ' . $e->getMessage();
            $errors[] = $errorMsg;
            error_log("❌ Error: $errorMsg");
        }
    }
    
    // Update last run timestamp
    $conn->query("UPDATE archival_settings SET last_run = NOW() WHERE id = 1");
    
    error_log("=== AUTO ARCHIVAL COMPLETED ===");
    error_log("Students deleted: $studentsDeleted");
    error_log("Documents deleted: $totalFilesDeleted");
    
    return [
        'status' => 'success',
        'students_archived' => $studentsDeleted,
        'total_files' => $totalFilesDeleted,
        'total_size' => 0, // No files stored, size is 0
        'errors' => $errors
    ];
}

/**
 * Get archival statistics
 */
function getArchivalStatistics() {
    global $conn;
    
    $stats = [
        'pending_count' => 0,
        'archived_count' => 0,
        'last_run' => null,
        'next_eligible_date' => null
    ];
    
    // Get pending count (eligible for deletion)
    $students = getEligibleStudents();
    $stats['pending_count'] = count($students);
    
    // Get archived count (total deleted via archival) - count individual student records
    $result = $conn->query("SELECT COUNT(*) as count FROM archive_logs 
                           WHERE status = 'success' AND archival_type = 'graduation'");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['archived_count'] = $row['count'];
    }
    
    // Get last run - format it nicely if it exists
    $settings = getArchivalSettings();
    if (!empty($settings['last_run']) && $settings['last_run'] !== '0000-00-00 00:00:00') {
        $stats['last_run'] = date('M d, Y g:i A', strtotime($settings['last_run']));
    } else {
        $stats['last_run'] = 'Never';
    }
    
    // Get next eligible date
    $result = $conn->query("SELECT MIN(graduation_date) as next_date 
                           FROM students 
                           WHERE is_graduated = 1 
                           AND is_archived = 0 
                           AND graduation_date IS NOT NULL");
    if ($result) {
        $row = $result->fetch_assoc();
        if (!empty($row['next_date'])) {
            $stats['next_eligible_date'] = date('M d, Y', strtotime($row['next_date']));
        }
    }
    
    return $stats;
}

// === API ENDPOINTS ===

// Get settings
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_settings') {
    try {
        $settings = getArchivalSettings();
        sendJsonResponse(['status' => 'success', 'data' => $settings]);
    } catch (Exception $e) {
        error_log("Error getting settings: " . $e->getMessage());
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        $timingOption = $_POST['timing_option'] ?? '1_year';
        $autoEnabled = isset($_POST['auto_enabled']) ? (int)$_POST['auto_enabled'] : 1;
        
        $result = updateArchivalSettings($timingOption, $autoEnabled);
        
        if ($result) {
            sendJsonResponse(['status' => 'success', 'message' => 'Settings updated successfully']);
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Failed to update settings']);
        }
    } catch (Exception $e) {
        error_log("Error updating settings: " . $e->getMessage());
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Get statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_statistics') {
    try {
        $stats = getArchivalStatistics();
        sendJsonResponse(['status' => 'success', 'data' => $stats]);
    } catch (Exception $e) {
        error_log("Error getting statistics: " . $e->getMessage());
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Run auto-archival
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_auto_archival') {
    try {
        $result = runAutoArchival();
        sendJsonResponse($result);
    } catch (Exception $e) {
        error_log("Error running auto-archival: " . $e->getMessage());
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Get logs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    try {
        $query = "SELECT * FROM archive_logs 
                  WHERE archival_type = 'graduation' 
                  ORDER BY archived_at DESC LIMIT 50";
        $result = $conn->query($query);
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        sendJsonResponse(['status' => 'success', 'data' => $logs]);
    } catch (Exception $e) {
        error_log("Error getting logs: " . $e->getMessage());
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Get eligible students (for debugging)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_eligible_students') {
    try {
        $students = getEligibleStudents();
        sendJsonResponse(['status' => 'success', 'data' => $students, 'count' => count($students)]);
    } catch (Exception $e) {
        error_log("Error getting eligible students: " . $e->getMessage());
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Only send error response if this file is accessed directly with an action parameter
if (isset($_REQUEST['action'])) {
    sendJsonResponse(['status' => 'error', 'message' => 'Invalid request']);
}
?>