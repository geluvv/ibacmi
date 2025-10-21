<?php
require_once '../db_connect.php';

echo "=== ARCHIVAL SYSTEM DEBUG ===\n\n";

// Check archival settings
echo "1. ARCHIVAL SETTINGS:\n";
$result = $conn->query("SELECT * FROM archival_settings");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
    echo "   Timing: {$settings['timing_option']}\n";
    echo "   Enabled: {$settings['auto_archival_enabled']}\n";
    echo "   Last Run: {$settings['last_run']}\n";
} else {
    echo "   No settings found!\n";
}

echo "\n2. SCHOOL YEAR:\n";
$result = $conn->query("SELECT * FROM school_years WHERE is_active = 1");
if ($result && $result->num_rows > 0) {
    $sy = $result->fetch_assoc();
    echo "   Active SY: {$sy['school_year']}\n";
    echo "   End Date: {$sy['end_date']}\n";
    $today = date('Y-m-d');
    echo "   Today: $today\n";
    echo "   Has Ended: " . ($sy['end_date'] <= $today ? "YES" : "NO") . "\n";
} else {
    echo "   No active school year!\n";
}

echo "\n3. 4TH YEAR STUDENTS:\n";
$result = $conn->query("SELECT id, student_id, first_name, last_name, year_level, status, is_graduated, is_archived, graduation_date 
                        FROM students WHERE year_level = 4 ORDER BY student_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   Student: {$row['student_id']} - {$row['first_name']} {$row['last_name']}\n";
        echo "     Year: {$row['year_level']}, Status: {$row['status']}\n";
        echo "     Graduated: {$row['is_graduated']}, Archived: {$row['is_archived']}\n";
        echo "     Grad Date: {$row['graduation_date']}\n\n";
    }
} else {
    echo "   Query failed\n";
}

echo "\n4. ARCHIVE LOGS:\n";
$result = $conn->query("SELECT * FROM archive_logs ORDER BY archived_at DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   [{$row['archived_at']}] {$row['student_name']} - Status: {$row['status']}\n";
    }
} else {
    echo "   No archive logs found\n";
}

echo "\n=== END DEBUG ===\n";
?>
