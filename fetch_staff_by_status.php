<?php
// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Clear any existing buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json');

try {
    require_once 'db_connect.php';
    
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
    
    // Validate status
    $allowedStatuses = ['pending', 'approved', 'denied'];
    if (!in_array($status, $allowedStatuses)) {
        throw new Exception('Invalid status parameter');
    }
    
    // ✅ FIXED: Changed 'staff' to 'staff_users'
    $query = "SELECT id, first_name, middle_name, last_name, email, role, id_document, status 
              FROM staff_users 
              WHERE status = ? 
              ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staffList = [];
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // Clear any unwanted output
    ob_clean();
    
    echo json_encode([
        'status' => 'success',
        'data' => $staffList,
        'count' => count($staffList)
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit;
?>