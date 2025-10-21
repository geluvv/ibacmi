<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($token) || empty($password) || empty($confirmPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || 
    !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers'
    ]);
    exit;
}

try {
    if (!isset($conn) && !isset($connection) && !isset($mysqli)) {
        throw new Exception("Database connection not established");
    }
    
    $db = $conn ?? $connection ?? $mysqli;
    
    // Verify token for STAFF
    $stmt = $db->prepare("
        SELECT user_id, email, expires_at, used
        FROM password_reset_tokens
        WHERE token = ? AND user_type = 'staff'
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $resetToken = $result->fetch_assoc();
    $stmt->close();
    
    if (!$resetToken) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid reset token']);
        exit;
    }
    
    if ($resetToken['used']) {
        echo json_encode(['status' => 'error', 'message' => 'This reset link has already been used']);
        exit;
    }
    
    if (strtotime($resetToken['expires_at']) < time()) {
        echo json_encode(['status' => 'error', 'message' => 'This reset link has expired']);
        exit;
    }
    
    // Update password in staff_users table (column is password_hash)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE staff_users SET password_hash = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Update prepare failed: " . $db->error);
    }
    
    $stmt->bind_param("si", $hashedPassword, $resetToken['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Update failed: " . $stmt->error);
    }
    $stmt->close();
    
    // Mark token as used
    $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Password has been reset successfully. Redirecting to login...'
    ]);
    
} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$stmt = $db->prepare("SELECT * FROM `staff_users` WHERE email = ? LIMIT 1");
?>