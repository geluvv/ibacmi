<?php
// Ensure error reporting is enabled during development
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Include database connection
require_once '../db_connect.php';

// Start session
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION['staff_user_id'])) {
    header("Location: stafflogin.html");
    exit();
}

// Fetch staff information - IMPROVED QUERY
$staffInfo = array();
$userId = $_SESSION['staff_user_id'];

// First, get data from staff_users table
$stmt = $conn->prepare("SELECT username, email, role, first_name, last_name FROM staff_users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $staffInfo = $userData;
}
$stmt->close();

// Then, get profile data from staff_profiles table (if exists)
$stmt = $conn->prepare("SELECT * FROM staff_profiles WHERE staff_user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $profileData = $result->fetch_assoc();
    // Merge profile data, giving priority to profile table (this includes profile_picture!)
    $staffInfo = array_merge($staffInfo, $profileData);
}
$stmt->close();

// Ensure we have default values
if (empty($staffInfo['first_name'])) {
    $staffInfo['first_name'] = explode(' ', $staffInfo['username'] ?? 'Staff')[0];
}
if (empty($staffInfo['last_name'])) {
    $staffInfo['last_name'] = explode(' ', $staffInfo['username'] ?? 'Member')[1] ?? 'Member';
}

// Define constants to customize the Document.php behavior for staff
define('IS_STAFF_VIEW', true);
define('STAFF_INFO', $staffInfo);

// Now include Document.php directly - it will output its content
// The IS_STAFF_VIEW constant will make it use staff-specific paths
// Document.php will NOT close body/html tags when IS_STAFF_VIEW is true
include '../AdminAccount/Document.php';

// After Document.php is done, append the profile modal before closing tags
include 'staffprofile.php';

// Now close the body and html tags
echo "\n</body>\n</html>";

// Close connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>
