<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['staff_user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$staffId = $_SESSION['staff_user_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!$currentPassword || !$newPassword || !$confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'All password fields are required']);
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
    exit();
}

if (strlen($newPassword) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long']);
    exit();
}

$stmt = $conn->prepare("SELECT password_hash FROM staff_users WHERE id = ?");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
    exit();
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE staff_users SET password_hash = ? WHERE id = ?");
$stmt->bind_param("si", $newHash, $staffId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update password']);
}
$stmt->close();
$conn->close();
?>