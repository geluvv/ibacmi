<?php
/**
 * Test Archival System
 * This script helps debug the archival system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/archival_test.log');

require_once '../db_connect.php';
require_once 'archival_api.php';

echo "<html><head><title>Archival System Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #dc3545; border-bottom: 3px solid #dc3545; padding-bottom: 10px; }
    h2 { color: #495057; margin-top: 30px; border-left: 4px solid #dc3545; padding-left: 10px; }
    .info-box { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning-box { background: #fff3cd; border: 1px solid #ffecb5; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #dc3545; color: white; }
    tr:hover { background-color: #f5f5f5; }
    .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
    .btn:hover { background: #c82333; }
    .btn-info { background: #17a2b8; }
    .btn-info:hover { background: #138496; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔍 Archival System Diagnostic Test</h1>";
echo "<p><strong>Date/Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Check settings
echo "<h2>1. Archival Settings</h2>";
$settings = getArchivalSettings();
echo "<div class='info-box'>";
echo "<strong>Timing Option:</strong> " . htmlspecialchars($settings['timing_option']) . "<br>";
echo "<strong>Auto Archival Enabled:</strong> " . ($settings['auto_archival_enabled'] ? 'Yes' : 'No') . "<br>";
echo "<strong>Last Run:</strong> " . ($settings['last_run'] ?: 'Never') . "<br>";
echo "</div>";

// Test 2: Check all students with graduation info
echo "<h2>2. All Students with Graduation Data</h2>";
$query = "SELECT id, student_id, first_name, last_name, graduation_date, is_graduated, is_archived, status 
          FROM students 
          WHERE graduation_date IS NOT NULL 
          ORDER BY graduation_date DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Student ID</th><th>Name</th><th>Graduation Date</th><th>Is Graduated</th><th>Is Archived</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['graduation_date']) . "</td>";
        echo "<td>" . ($row['is_graduated'] ? '✅ Yes' : '❌ No') . "</td>";
        echo "<td>" . ($row['is_archived'] ? '✅ Yes' : '❌ No') . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning-box'>⚠️ No students found with graduation_date set.</div>";
}

// Test 3: Calculate threshold dates
echo "<h2>3. Threshold Dates by Timing Option</h2>";
echo "<table>";
echo "<tr><th>Timing Option</th><th>Threshold Date</th><th>Description</th></tr>";

$options = [
    'immediate' => ['date' => date('Y-m-d'), 'desc' => 'Today and earlier'],
    '6_months' => ['date' => date('Y-m-d', strtotime('-6 months')), 'desc' => '6 months ago and earlier'],
    '1_year' => ['date' => date('Y-m-d', strtotime('-1 year')), 'desc' => '1 year ago and earlier'],
    '2_years' => ['date' => date('Y-m-d', strtotime('-2 years')), 'desc' => '2 years ago and earlier']
];

foreach ($options as $option => $data) {
    $highlight = ($option === $settings['timing_option']) ? 'style="background: #ffffcc;"' : '';
    echo "<tr $highlight>";
    echo "<td><strong>" . htmlspecialchars($option) . "</strong>" . ($option === $settings['timing_option'] ? ' (CURRENT)' : '') . "</td>";
    echo "<td><code>" . $data['date'] . "</code></td>";
    echo "<td>" . $data['desc'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 4: Get eligible students
echo "<h2>4. Eligible Students for Archival</h2>";
$eligibleStudents = getEligibleStudents();

if (!empty($eligibleStudents)) {
    echo "<div class='success-box'>";
    echo "<strong>✅ Found " . count($eligibleStudents) . " eligible student(s)</strong>";
    echo "</div>";
    
    echo "<table>";
    echo "<tr><th>Student ID</th><th>Name</th><th>Graduation Date</th><th>Documents</th></tr>";
    foreach ($eligibleStudents as $student) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($student['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($student['graduation_date']) . "</td>";
        echo "<td>" . htmlspecialchars($student['document_count']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning-box'>";
    echo "⚠️ No eligible students found for archival with current settings.<br><br>";
    echo "<strong>Possible reasons:</strong><br>";
    echo "1. No students have <code>is_graduated = 1</code> OR <code>status = 'archived'</code><br>";
    echo "2. No students have <code>graduation_date</code> set<br>";
    echo "3. All students with graduation dates are already archived (<code>is_archived = 1</code>)<br>";
    echo "4. Graduation dates don't meet the threshold for the selected timing option<br>";
    echo "</div>";
}

// Test 5: Check students who should be marked as graduated
echo "<h2>5. Students Who Should Be Marked as Graduated</h2>";
$today = date('Y-m-d');
$checkQuery = "SELECT id, student_id, first_name, last_name, graduation_date, is_graduated, status 
               FROM students 
               WHERE graduation_date IS NOT NULL 
               AND graduation_date <= ?
               AND is_graduated = 0
               AND status != 'archived'";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("s", $today);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult && $checkResult->num_rows > 0) {
    echo "<div class='warning-box'>";
    echo "⚠️ Found " . $checkResult->num_rows . " student(s) with graduation date passed but not marked as graduated:";
    echo "</div>";
    
    echo "<table>";
    echo "<tr><th>Student ID</th><th>Name</th><th>Graduation Date</th><th>Current Status</th><th>Action Needed</th></tr>";
    while ($row = $checkResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['graduation_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>Set <code>is_graduated = 1</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='info-box'>";
    echo "<strong>💡 Tip:</strong> You need to mark these students as graduated. Run this SQL:<br>";
    echo "<code>UPDATE students SET is_graduated = 1 WHERE graduation_date IS NOT NULL AND graduation_date <= CURDATE() AND is_graduated = 0;</code>";
    echo "</div>";
} else {
    echo "<div class='success-box'>✅ All students with past graduation dates are properly marked as graduated.</div>";
}
$checkStmt->close();

// Test 6: Recent archive logs
echo "<h2>6. Recent Archive Logs</h2>";
$logQuery = "SELECT * FROM archive_logs ORDER BY archived_at DESC LIMIT 10";
$logResult = $conn->query($logQuery);

if ($logResult && $logResult->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Date</th><th>Type</th><th>Student Name</th><th>Status</th><th>Files</th><th>Error</th></tr>";
    while ($row = $logResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['archived_at']) . "</td>";
        echo "<td>" . htmlspecialchars($row['archival_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['file_count']) . "</td>";
        echo "<td>" . htmlspecialchars($row['error_message'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='info-box'>ℹ️ No archive logs found yet.</div>";
}

// Test 7: Actions
echo "<h2>7. Quick Actions</h2>";
echo "<form method='post' style='display:inline;'>";
echo "<button type='submit' name='action' value='mark_graduated' class='btn btn-info'>Mark Eligible Students as Graduated</button>";
echo "</form>";

if (isset($_POST['action']) && $_POST['action'] === 'mark_graduated') {
    $today = date('Y-m-d');
    $updateQuery = "UPDATE students SET is_graduated = 1 WHERE graduation_date IS NOT NULL AND graduation_date <= ? AND is_graduated = 0";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $today);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();
    
    echo "<div class='success-box'>";
    echo "✅ Successfully marked $affected student(s) as graduated!";
    echo "<br><br><a href='test_archival.php' class='btn'>Refresh Page</a>";
    echo "</div>";
}

echo "<br><br>";
echo "<a href='backup.html' class='btn'>← Back to Backup Page</a>";
echo "<a href='test_archival.php' class='btn btn-info'>🔄 Refresh Test</a>";

echo "</div></body></html>";
?>
