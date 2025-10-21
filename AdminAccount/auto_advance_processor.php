<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/auto_advance_errors.log');

require_once '../db_connect.php';
date_default_timezone_set('Asia/Manila');

function logAdvancement($message) {
    $logFile = __DIR__ . '/advancement_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    error_log($message);
}

function checkAndAdvanceStudents() {
    global $conn;
    
    $today = date('Y-m-d');
    logAdvancement("=== Automatic Advancement Check Started ===");
    logAdvancement("Current Date: $today");
    
    // First, deactivate ALL school years where end_date has passed
    $deactivateQuery = "UPDATE school_years 
                       SET is_active = 0, 
                           updated_at = NOW() 
                       WHERE end_date < ? 
                       AND is_active = 1";
    $deactivateStmt = $conn->prepare($deactivateQuery);
    $deactivateStmt->bind_param("s", $today);
    $deactivateStmt->execute();
    $deactivatedCount = $deactivateStmt->affected_rows;
    $deactivateStmt->close();
    
    if ($deactivatedCount > 0) {
        logAdvancement("🔴 Deactivated {$deactivatedCount} school year(s) with past end dates");
    }
    
    // Find school years that have reached their end date
    $query = "SELECT id, school_year, end_date, last_advancement_check, is_active 
              FROM school_years 
              WHERE end_date <= ? 
              AND auto_advance_enabled = 1
              AND (last_advancement_check IS NULL OR DATE(last_advancement_check) < ?)
              ORDER BY end_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logAdvancement("No school years ready for advancement");
        return [
            'status' => 'success',
            'message' => 'No advancements needed',
            'advanced_count' => 0,
            'deactivated_count' => $deactivatedCount
        ];
    }
    
    $totalAdvanced = 0;
    $advancementDetails = [];
    
    while ($schoolYear = $result->fetch_assoc()) {
        logAdvancement("Processing school year: {$schoolYear['school_year']}");
        
        $conn->begin_transaction();
        
        try {
            // First, mark all existing 4th year students as graduated with the end date
            $markGraduatedQuery = "UPDATE students 
                                   SET graduation_date = ?,
                                       is_graduated = 1,
                                       status = 'graduated',
                                       updated_at = NOW()
                                   WHERE year_level = 4
                                   AND (graduation_date IS NULL OR is_graduated = 0)";
            $markStmt = $conn->prepare($markGraduatedQuery);
            $markStmt->bind_param("s", $schoolYear['end_date']);
            $markStmt->execute();
            $graduatedCount = $markStmt->affected_rows;
            $markStmt->close();
            
            if ($graduatedCount > 0) {
                logAdvancement("Marked {$graduatedCount} existing 4th year students as graduated with date {$schoolYear['end_date']}");
            }
            
            // Get all active students (year level < 4)
            $studentsQuery = "SELECT id, student_id, first_name, last_name, year_level 
                             FROM students 
                             WHERE year_level < 4
                             ORDER BY year_level, last_name";
            
            $studentsResult = $conn->query($studentsQuery);
            $advancedInYear = 0;
            
            while ($student = $studentsResult->fetch_assoc()) {
                $currentLevel = $student['year_level'];
                $newLevel = $currentLevel + 1;
                
                // Advance to next year level
                $updateQuery = "UPDATE students 
                               SET year_level = ?,
                                   updated_at = NOW()
                               WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ii", $newLevel, $student['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                $advancedInYear++;
                logAdvancement("Student {$student['student_id']} - Advanced from Year {$currentLevel} to Year {$newLevel}");
            }
            
            // Update last_advancement_check
            $updateCheckQuery = "UPDATE school_years 
                                SET last_advancement_check = NOW() 
                                WHERE id = ?";
            $updateCheckStmt = $conn->prepare($updateCheckQuery);
            $updateCheckStmt->bind_param("i", $schoolYear['id']);
            $updateCheckStmt->execute();
            $updateCheckStmt->close();
            
            $conn->commit();
            
            $totalAdvanced += $advancedInYear;
            
            $advancementDetails[] = [
                'school_year' => $schoolYear['school_year'],
                'advanced' => $advancedInYear,
                'graduated' => $graduatedCount,
                'end_date' => $schoolYear['end_date']
            ];
            
            logAdvancement("School Year {$schoolYear['school_year']}: {$advancedInYear} advanced, {$graduatedCount} graduated");
            
        } catch (Exception $e) {
            $conn->rollback();
            logAdvancement("ERROR: " . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'Advancement failed: ' . $e->getMessage()
            ];
        }
    }
    
    $stmt->close();
    
    logAdvancement("=== Advancement Complete: $totalAdvanced students advanced ===");
    
    return [
        'status' => 'success',
        'message' => "Advanced $totalAdvanced students successfully",
        'advanced_count' => $totalAdvanced,
        'deactivated_count' => $deactivatedCount,
        'details' => $advancementDetails
    ];
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' || php_sapi_name() === 'cli') {
    $result = checkAndAdvanceStudents();
    
    if (php_sapi_name() === 'cli') {
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>