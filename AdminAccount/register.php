<?php
// Start output buffering to prevent any output before JSON
ob_start();

session_start();

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get the correct base path
$basePath = __DIR__;

// Check if PHPMailer exists
$phpmailerLoaded = false;

if (file_exists($basePath . '/vendor/autoload.php')) {
    require $basePath . '/vendor/autoload.php';
    $phpmailerLoaded = true;
} elseif (file_exists($basePath . '/PHPMailer/src/PHPMailer.php')) {
    require $basePath . '/PHPMailer/src/Exception.php';
    require $basePath . '/PHPMailer/src/PHPMailer.php';
    require $basePath . '/PHPMailer/src/SMTP.php';
    $phpmailerLoaded = true;
}

if (!$phpmailerLoaded && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'PHPMailer is not installed.'
    ]);
    exit;
}

// Check if email config exists
if (!file_exists($basePath . '/email_config.php')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Email configuration file is missing.'
        ]);
        exit;
    }
}

require_once $basePath . '/email_config.php';

// Database connection
$host = 'localhost';
$dbname = 'iba_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_clean();
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Handle initial registration (send verification code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username_input = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = 'admin';
        $terms = isset($_POST['terms']);

        $errors = [];

        if (empty($firstName)) $errors[] = 'First name is required';
        if (empty($lastName)) $errors[] = 'Last name is required';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        if (empty($username_input) || strlen($username_input) < 4) {
            $errors[] = 'Username must be at least 4 characters';
        }
        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        if (!$terms) {
            $errors[] = 'You must agree to the terms and conditions';
        }

        // Check if email already exists and is verified
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND is_verified = 1");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email already exists';
        }

        // Check if username already exists and is verified
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND is_verified = 1");
        $stmt->execute([$username_input]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Username already exists';
        }

        if (!empty($errors)) {
            echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
            exit;
        }

        // Generate 6-digit verification code
        $verificationCode = sprintf("%06d", mt_rand(1, 999999));
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Store user data temporarily in session
        $_SESSION['pending_user'] = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'username' => $username_input,
            'password' => $hashedPassword,
            'role' => $role,
            'verification_code' => $verificationCode,
            'code_expires_at' => $expiresAt
        ];

        // Send verification email via Brevo
        $mail = new PHPMailer(true);
        
        // ENABLE DEBUG MODE - REMOVE AFTER TESTING
        $mail->SMTPDebug = 0; // 0 = off, 2 = detailed debug
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Server settings for Brevo
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName . ' ' . $lastName);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'IBACMI - Email Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f5f5f5; padding: 20px;'>
                <div style='background-color: #8E1616; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>IBACMI Registration</h1>
                </div>
                <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    <h2 style='color: #333; margin-top: 0;'>Hello {$firstName}!</h2>
                    <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                        Thank you for registering with <strong>IBACMI Registrar Document Online Data Bank</strong>.
                    </p>
                    <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                        Your verification code is:
                    </p>
                    <div style='background: linear-gradient(135deg, #8E1616 0%, #6b1111 100%); padding: 25px; text-align: center; font-size: 36px; font-weight: bold; color: white; letter-spacing: 8px; border-radius: 10px; margin: 30px 0; box-shadow: 0 4px 15px rgba(142, 22, 22, 0.3);'>
                        {$verificationCode}
                    </div>
                    <p style='color: #e74c3c; font-size: 14px; text-align: center; font-weight: bold;'>
                        This code will expire in 15 minutes
                    </p>
                    <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                        Please enter this code on the verification page to complete your registration.
                    </p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 13px; text-align: center;'>
                        If you didn't request this code, please ignore this email.
                    </p>
                </div>
                <div style='text-align: center; padding: 20px; color: #999; font-size: 12px;'>
                    <p>&copy; 2025 IBACMI. All rights reserved.</p>
                    <p>Registrar Document Online Data Bank</p>
                </div>
            </div>
        ";

        $mail->AltBody = "Hello {$firstName}!\n\n"
                       . "Thank you for registering with IBACMI.\n\n"
                       . "Your verification code is: {$verificationCode}\n\n"
                       . "This code will expire in 15 minutes.\n\n"
                       . "If you didn't request this code, please ignore this email.\n\n"
                       . "© 2025 IBACMI. All rights reserved.";

        $mail->send();

        echo json_encode([
            'status' => 'success',
            'message' => 'Verification code sent to your email',
            'show_verification' => true
        ]);
    } catch (Exception $e) {
        // SHOW DETAILED ERROR - REMOVE IN PRODUCTION
        echo json_encode([
            'status' => 'error',
            'message' => 'Email Error: ' . $e->getMessage(),
            'debug_info' => $mail->ErrorInfo ?? 'No additional info'
        ]);
    }
    exit;
}

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $submittedCode = trim($_POST['code'] ?? '');
        
        if (!isset($_SESSION['pending_user'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expired. Please register again.']);
            exit;
        }

        $userData = $_SESSION['pending_user'];

        if ($submittedCode !== $userData['verification_code']) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid verification code']);
            exit;
        }

        if (strtotime($userData['code_expires_at']) < time()) {
            echo json_encode(['status' => 'error', 'message' => 'Verification code has expired']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, middle_name, last_name, email, username, password, role, is_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $userData['first_name'],
            $userData['middle_name'],
            $userData['last_name'],
            $userData['email'],
            $userData['username'],
            $userData['password'],
            $userData['role']
        ]);

        unset($_SESSION['pending_user']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful! You can now login.'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle resend verification code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        if (!isset($_SESSION['pending_user'])) {
            echo json_encode(['status' => 'error', 'message' => 'Session expired. Please register again.']);
            exit;
        }

        $userData = $_SESSION['pending_user'];
        $verificationCode = sprintf("%06d", mt_rand(1, 999999));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $_SESSION['pending_user']['verification_code'] = $verificationCode;
        $_SESSION['pending_user']['code_expires_at'] = $expiresAt;

        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($userData['email'], $userData['first_name'] . ' ' . $userData['last_name']);

        $mail->isHTML(true);
        $mail->Subject = 'IBACMI - New Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f5f5f5; padding: 20px;'>
                <div style='background-color: #8E1616; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>IBACMI Registration</h1>
                </div>
                <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    <h2 style='color: #333; margin-top: 0;'>Hello {$userData['first_name']}!</h2>
                    <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                        You requested a new verification code.
                    </p>
                    <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                        Your new verification code is:
                    </p>
                    <div style='background: linear-gradient(135deg, #8E1616 0%, #6b1111 100%); padding: 25px; text-align: center; font-size: 36px; font-weight: bold; color: white; letter-spacing: 8px; border-radius: 10px; margin: 30px 0; box-shadow: 0 4px 15px rgba(142, 22, 22, 0.3);'>
                        {$verificationCode}
                    </div>
                    <p style='color: #e74c3c; font-size: 14px; text-align: center; font-weight: bold;'>
                        This code will expire in 15 minutes
                    </p>
                </div>
                <div style='text-align: center; padding: 20px; color: #999; font-size: 12px;'>
                    <p>&copy; 2025 IBACMI. All rights reserved.</p>
                </div>
            </div>
        ";

        $mail->send();

        echo json_encode([
            'status' => 'success',
            'message' => 'New verification code sent to your email'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send verification email: ' . $e->getMessage()
        ]);
    }
    exit;
}

ob_end_flush();
?>