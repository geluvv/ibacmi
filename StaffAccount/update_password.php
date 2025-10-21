<?php
session_start();
require_once '../db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in - support both session variables
$staffId = $_SESSION['staff_user_id'] ?? $_SESSION['staff_id'] ?? null;
if (!$staffId) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

try {
    // Get form data
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['status' => 'error', 'message' => 'All password fields are required.']);
        exit;
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit;
    }
    
    if (strlen($new_password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }
    
    // Get current password from database
    $stmt = $conn->prepare("SELECT password FROM staff_users WHERE id = ?");
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        exit;
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
        exit;
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_stmt = $conn->prepare("UPDATE staff_users SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $staffId);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update password: ' . $update_stmt->error);
    }
    
    $update_stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Password updated successfully!'
    ]);
    
} catch (Exception $e) {
    error_log("Password update error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while updating your password. Please try again.'
    ]);
}
?>
