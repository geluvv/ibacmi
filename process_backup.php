<?php
// Prevent any output before headers
ob_start();

// Set error handling to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $errstr
    ]);
    exit;
});

try {
    session_start();
    require_once 'db_connect.php';
    require_once 'classes/BackupHandler.php';

    // Disable display errors but enable error logging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/backup_error.log');

    // Increase limits for large backups
    ini_set('memory_limit', '2048M');
    ini_set('max_execution_time', 1800);
    set_time_limit(1800);
    ini_set('post_max_size', '4096M');
    ini_set('upload_max_filesize', '4096M');

    // Add CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }

    // Get POST data
    $storageOption = isset($_POST['storageOption']) ? $_POST['storageOption'] : 'local';
    $timestamp = isset($_POST['timestamp']) ? $_POST['timestamp'] : date('Y-m-d H:i:s');
    $user = isset($_POST['user']) ? $_POST['user'] : 'ibacmi2025';
    $googleAccount = isset($_POST['googleAccount']) ? json_decode($_POST['googleAccount'], true) : null;
    $googleAccessToken = isset($_POST['googleAccessToken']) ? $_POST['googleAccessToken'] : null;

    // Initialize backup handler
    $backupHandler = new BackupHandler($conn, $timestamp, $user);
    
    // Create backup based on storage option
    if ($storageOption === 'local') {
        // Ensure the backup directory exists
        $backupDir = __DIR__ . '/backups';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Generate backup filename
        $backupFileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . '/' . $backupFileName;

        // Create backup using BackupHandler
        $result = $backupHandler->createBackup(false);

        if ($result['status'] === 'success') {
            // Insert backup record into database using correct column names
            $sql = "INSERT INTO backup_logs (filename, storage_type, created_by, created_at, status, file_id) 
                    VALUES (?, 'local', ?, NOW(), 'success', ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $file_id = uniqid('backup_', true);
                $stmt->bind_param('sss', $backupFileName, $user, $file_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    } else {
        $useGoogleDrive = ($storageOption === 'cloud');
        $result = $backupHandler->createBackup($useGoogleDrive, $googleAccessToken);

        // If successful, add the backup record to the database
        if ($result['status'] === 'success') {
            $storage_type = $useGoogleDrive ? 'cloud' : 'local';
            $sql = "INSERT INTO backup_logs (filename, storage_type, created_by, created_at, status, file_id) 
                   VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $filename = $result['filename'] ?? 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $file_id = $result['file_id'] ?? uniqid('backup_', true);
                $status = 'success';
                $stmt->bind_param('sssss', $filename, $storage_type, $user, $status, $file_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Clean output buffer and send response
    ob_clean();
    echo json_encode($result);

} catch (Exception $e) {
    // Log the error
    error_log("Backup error: " . $e->getMessage());
    
    // Clean output buffer and send error response
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
?>
