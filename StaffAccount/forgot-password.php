<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

ob_start();

// Load required files first
require_once '../db_connect.php';
require_once 'email_config.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

// Use statements must be at the top level, not inside try block
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
    exit;
}

try {
    if (!isset($conn) && !isset($connection) && !isset($mysqli)) {
        throw new Exception("Database connection not established");
    }
    
    $db = $conn ?? $connection ?? $mysqli;
    
    if (!($db instanceof mysqli)) {
        throw new Exception("Invalid database connection type");
    }
    
    if ($db->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $tablesResult = $db->query("SHOW TABLES");
    if (!$tablesResult) {
        throw new Exception("Failed to get tables");
    }
    
    $tables = [];
    while ($row = $tablesResult->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $staffTable = null;
    foreach (['staff_users', 'staff', 'users', 'tbl_staff', 'tblstaff'] as $tableName) {
        if (in_array($tableName, $tables)) {
            $staffTable = $tableName;
            break;
        }
    }
    
    if (!$staffTable) {
        throw new Exception("Staff table not found");
    }
    
    $stmt = $db->prepare("SELECT * FROM `$staffTable` WHERE email = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'If your email exists in our system, you will receive a password reset link.'
        ]);
        exit;
    }
    
    if (!in_array('password_reset_tokens', $tables)) {
        throw new Exception("password_reset_tokens table not found. Please create it first.");
    }
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $userId = null;
    $possibleIdColumns = ['id', 'staff_id', 'user_id', 'StaffID', 'ID'];
    foreach ($possibleIdColumns as $col) {
        if (isset($user[$col])) {
            $userId = $user[$col];
            break;
        }
    }
    
    if (!$userId) {
        throw new Exception("Could not find user ID");
    }
    
    $username = 'User';
    $possibleNameColumns = ['username', 'name', 'fullname', 'full_name', 'Username', 'Name'];
    foreach ($possibleNameColumns as $col) {
        if (isset($user[$col]) && !empty($user[$col])) {
            $username = $user[$col];
            break;
        }
    }
    
    $stmt = $db->prepare("INSERT INTO password_reset_tokens (user_type, user_id, email, token, expires_at) VALUES ('staff', ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare insert failed: " . $db->error);
    }
    
    $stmt->bind_param("isss", $userId, $email, $token, $expiresAt);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute insert failed: " . $stmt->error);
    }
    $stmt->close();
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . "://" . $host . "/ibacmi/StaffAccount/reset-password.html?token=" . $token;
    
    // Send email using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - IBACMI Staff';
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .email-wrapper { background-color: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { 
                    background: linear-gradient(135deg, #8E1616 0%, #700D0D 100%);
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                    border-radius: 10px 10px 0 0; 
                }
                .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
                .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                .icon { 
                    display: inline-block;
                    width: 60px;
                    height: 60px;
                    background: rgba(255,255,255,0.2);
                    border-radius: 50%;
                    line-height: 60px;
                    font-size: 30px;
                    margin-bottom: 15px;
                }
                .content { 
                    background: #ffffff; 
                    padding: 40px 30px; 
                    border-left: 1px solid #e0e0e0;
                    border-right: 1px solid #e0e0e0;
                }
                .greeting { font-size: 18px; color: #333; margin-bottom: 20px; }
                .message { font-size: 15px; color: #555; margin-bottom: 30px; line-height: 1.8; }
                .button-container { text-align: center; margin: 35px 0; }
                .button { 
                    display: inline-block;
                    padding: 16px 40px;
                    background: linear-gradient(135deg, #8E1616 0%, #700D0D 100%);
                    color: white !important;
                    text-decoration: none;
                    border-radius: 30px;
                    font-weight: 600;
                    font-size: 16px;
                    box-shadow: 0 4px 15px rgba(142, 22, 22, 0.3);
                    transition: all 0.3s ease;
                }
                .button:hover { box-shadow: 0 6px 20px rgba(142, 22, 22, 0.4); }
                .link-text { font-size: 13px; color: #666; margin: 20px 0 10px 0; }
                .link-box {
                    background: #f9f9f9;
                    padding: 15px;
                    border-radius: 8px;
                    word-break: break-all;
                    font-size: 13px;
                    color: #8E1616;
                    margin: 15px 0;
                    border: 1px solid #e0e0e0;
                    font-family: monospace;
                }
                .warning {
                    background: linear-gradient(135deg, #fff3cd 0%, #ffe8a1 100%);
                    border-left: 4px solid #ffc107;
                    padding: 20px;
                    margin: 30px 0;
                    border-radius: 8px;
                }
                .warning-title {
                    color: #856404;
                    font-size: 16px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .warning ul { margin: 10px 0; padding-left: 20px; color: #856404; }
                .warning li { margin: 8px 0; }
                .footer { 
                    text-align: center; 
                    padding: 30px; 
                    background: linear-gradient(135deg, #f8f8f8 0%, #f0f0f0 100%);
                    border-radius: 0 0 10px 10px;
                    border-top: 3px solid #8E1616;
                }
                .footer p { margin: 5px 0; color: #666; font-size: 13px; }
                .footer .brand { font-weight: bold; color: #8E1616; font-size: 15px; }
                .security-note {
                    background: #e3f2fd;
                    padding: 15px;
                    border-radius: 8px;
                    margin-top: 25px;
                    border-left: 4px solid #2196F3;
                    font-size: 13px;
                    color: #1565C0;
                }
                .security-title {
                    font-weight: bold;
                    margin-bottom: 5px;
                }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='container'>
                    <div class='header'>
                        <div class='icon'>&#128274;</div>
                        <h1>Password Reset Request</h1>
                        <p>IBACMI Registrar Document Online Data Bank</p>
                    </div>
                    <div class='content'>
                        <p class='greeting'>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                        <p class='message'>
                            We received a request to reset your password for your <strong>IBACMI Staff Account</strong>. 
                            If you made this request, click the button below to create a new password.
                        </p>
                        
                        <div class='button-container'>
                            <a href='" . $resetLink . "' class='button'>Reset My Password</a>
                        </div>
                        
                        <p class='link-text'>Or copy and paste this link into your browser:</p>
                        <div class='link-box'>" . $resetLink . "</div>
                        
                        <div class='warning'>
                            <div class='warning-title'>&#9888; Important Security Information:</div>
                            <ul>
                                <li>This password reset link will <strong>expire in 1 hour</strong></li>
                                <li>The link can only be used <strong>once</strong></li>
                                <li>If you didn't request this password reset, please <strong>ignore this email</strong></li>
                                <li>Your password will remain unchanged unless you click the link above</li>
                            </ul>
                        </div>
                        
                        <div class='security-note'>
                            <div class='security-title'>&#128274; Security Tip:</div>
                            Never share your password reset link with anyone. 
                            IBACMI staff will never ask for your password or reset link via email or phone.
                        </div>
                    </div>
                    <div class='footer'>
                        <p class='brand'>IBACMI Registrar Document Online Data Bank</p>
                        <p>This is an automated email. Please do not reply to this message.</p>
                        <p style='color: #999; margin-top: 15px;'>&copy; " . date('Y') . " IBACMI. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $username,\n\nWe received a request to reset your password for your IBACMI Staff Account.\n\nReset your password by visiting this link:\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.\n\nIBACMI ORDDB System";
        
        $mail->send();
        
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Password reset link has been sent to your email. Please check your inbox and spam folder.'
        ]);
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        ob_end_clean();
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send email. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>