<?php
// Prevent any output before headers
ob_start();

// Enable error reporting but log to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('error_log', __DIR__ . '/debug.log');

// Include database connection
require_once '../db_connect.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate login credentials
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        throw new Exception('Username and password are required');
    }

    // Debug logging for credentials
    error_log("Login attempt - Username: $username");
    
    // Get user from database with status check
    $stmt = $conn->prepare("SELECT * FROM staff_users WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Debug logging
    error_log("User found: " . ($user ? 'Yes' : 'No'));

    if (!$user) {
        throw new Exception('Invalid username or password');
    }

    // Check user status before allowing login
    if ($user['status'] !== 'approved') {
        switch ($user['status']) {
            case 'pending':
                throw new Exception('Your account is pending approval. Please wait for admin confirmation.');
                break;
            case 'denied':
                throw new Exception('Your account registration has been denied. Please contact the administrator.');
                break;
            default:
                throw new Exception('Account status is invalid. Please contact the administrator.');
        }
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        error_log("Password verification failed for user: $username");
        throw new Exception('Invalid username or password');
    }

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Set session variables
    $_SESSION['staff_user_id'] = $user['id'];
    $_SESSION['staff_username'] = $user['username'];
    $_SESSION['staff_role'] = $user['role'];

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'role' => $user['role']
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Clear any buffered output
ob_end_flush();
$conn->close();
?>
