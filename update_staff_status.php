<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$staff_id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

try {
    $status = ($action === 'approve') ? 'approved' : 
              ($action === 'deny' ? 'denied' : '');

    if (!$status) {
        throw new Exception('Invalid action');
    }

    $sql = "UPDATE staff_users SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $staff_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No staff found with that ID']);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}

$conn->close();
?>