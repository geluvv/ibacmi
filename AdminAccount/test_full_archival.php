<?php
require_once '../db_connect.php';

echo "=== FULL ARCHIVAL SYSTEM TEST ===\n\n";

// Step 1: Check current settings
echo "1. CURRENT SETTINGS:\n";
$result = $conn->query("SELECT * FROM archival_settings");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
    echo "   Timing: {$settings['timing_option']}\n";
    echo "   Enabled: " . ($settings['auto_archival_enabled'] ? 'YES' : 'NO') . "\n";
    echo "   Last Run: " . ($settings['last_run'] ?: 'Never') . "\n";
} else {
    echo "   ❌ No settings found!\n";
}

// Step 2: Check school year
echo "\n2. SCHOOL YEAR:\n";
$result = $conn->query("SELECT * FROM school_years WHERE is_active = 1");
if ($result && $result->num_rows > 0) {
    $sy = $result->fetch_assoc();
    echo "   Active: {$sy['school_year']}\n";
    echo "   End Date: {$sy['end_date']}\n";
    $today = date('Y-m-d');
    $hasEnded = $sy['end_date'] <= $today;
    echo "   Status: " . ($hasEnded ? '✅ ENDED' : '⏳ ONGOING') . "\n";
} else {
    echo "   ❌ No active school year!\n";
}

// Step 3: Count 4th year students
echo "\n3. STUDENTS:\n";
$result = $conn->query("SELECT COUNT(*) as total FROM students WHERE year_level = 4 AND is_archived = 0");
if ($result) {
    $count = $result->fetch_assoc()['total'];
    echo "   4th Year (Not Archived): $count\n";
}

$result = $conn->query("SELECT COUNT(*) as total FROM students WHERE year_level = 4 AND is_graduated = 1 AND is_archived = 0");
if ($result) {
    $count = $result->fetch_assoc()['total'];
    echo "   Graduated (Not Archived): $count\n";
}

// Step 4: Test eligibility check
echo "\n4. ELIGIBILITY CHECK:\n";
require_once 'archival_api.php';

$eligibleStudents = getEligibleStudents();
echo "   Eligible for archival: " . count($eligibleStudents) . "\n";

if (count($eligibleStudents) > 0) {
    echo "\n   Students eligible:\n";
    foreach ($eligibleStudents as $student) {
        echo "   - {$student['student_id']}: {$student['first_name']} {$student['last_name']}\n";
        echo "     Year: {$student['year_level']}, Graduated: {$student['is_graduated']}, Grad Date: {$student['graduation_date']}\n";
    }
    
    // Step 5: Simulate what would happen
    echo "\n5. SIMULATED ARCHIVAL (NOT EXECUTING):\n";
    echo "   If archival runs now:\n";
    echo "   - " . count($eligibleStudents) . " students would be deleted from the system\n";
    echo "   - Their documents would be removed\n";
    echo "   - Archive logs would be created\n";
    
    // Ask if user wants to actually run it
    echo "\n6. DO YOU WANT TO RUN ARCHIVAL NOW? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) === 'y' || trim($line) === 'Y') {
        echo "\n   Running archival...\n";
        $result = runAutoArchival();
        
        echo "\n   RESULT:\n";
        echo "   Status: {$result['status']}\n";
        echo "   Students Archived: {$result['students_archived']}\n";
        echo "   Files Deleted: {$result['total_files']}\n";
        
        if (!empty($result['errors'])) {
            echo "   Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "   - $error\n";
            }
        }
        
        echo "\n   ✅ ARCHIVAL COMPLETED!\n";
    } else {
        echo "\n   Archival cancelled.\n";
    }
} else {
    echo "   ✅ No students eligible for archival at this time.\n";
    echo "\n   This means:\n";
    echo "   - Either all 4th year students are already archived\n";
    echo "   - Or the school year hasn't ended yet\n";
    echo "   - Or not enough time has passed since graduation based on your timing settings\n";
}

// Step 7: Check archive logs
echo "\n7. ARCHIVE HISTORY:\n";
$result = $conn->query("SELECT * FROM archive_logs ORDER BY archived_at DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   [{$row['archived_at']}] {$row['student_name']} - {$row['status']}\n";
    }
} else {
    echo "   No archive logs found\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
