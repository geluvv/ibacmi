<?php
// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log login attempts
error_log('Login attempt started at ' . date('Y-m-d H:i:s'));

// Start session with proper settings
session_set_cookie_params([
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Database connection
$host = 'localhost';
$dbname = 'iba_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test the connection and check for users table
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    error_log("Database tables: " . implode(", ", $tables));
    
    if (!in_array('users', $tables)) {
        error_log("ERROR: 'users' table not found in database!");
    } else {
        // Check if any users exist
        $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        error_log("Number of users in database: " . $userCount);
    }
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Function to send JSON response safely
function sendJsonResponse($data) {
    ob_end_clean(); // Clear any unexpected output
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate input
    if (empty($username) || empty($password)) {
        sendJsonResponse(['status' => 'error', 'message' => 'Username and password are required']);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            sendJsonResponse(['status' => 'error', 'message' => 'Invalid username or password']);
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        // Handle "Remember Me"
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

            $stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);

            setcookie('remember_token', $token, time() + 2592000, '/', '', false, true);
        }

        sendJsonResponse([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect' => 'dashboard.php',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
    } catch (PDOException $e) {
        sendJsonResponse(['status' => 'error', 'message' => 'Login failed']);
    }
} else {
    // Handle Remember Me
    if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        try {
            $stmt = $pdo->prepare("SELECT u.* FROM users u JOIN sessions s ON u.id = s.user_id WHERE s.token = ? AND s.expires_at > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;

                ob_end_clean();
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            // Silent fail
        }
    }

    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        ob_end_clean();
        header('Location: dashboard.php');
        exit;
    }

    // If the request is AJAX, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        sendJsonResponse(['status' => 'error', 'message' => 'Invalid request']);
    } else {
        // If accessed directly, redirect to login page
        ob_end_clean();
        header('Location: dashboard.php');
        exit;
    }
}
?>
