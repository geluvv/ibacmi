<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Load email config
require 'email_config.php';

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';

    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->AuthType = 'LOGIN';

    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('your-email@gmail.com', 'Test User'); // Replace with your email

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Staff Account';
    $mail->Body = '<h1>Test Email</h1><p>Staff email configuration is working!</p>';

    $mail->send();
    echo '<div style="padding: 20px; background: #4CAF50; color: white; margin: 20px; border-radius: 5px;">✅ Email sent successfully!</div>';
} catch (Exception $e) {
    echo '<div style="padding: 20px; background: #f44336; color: white; margin: 20px; border-radius: 5px;">❌ Email Error: ' . $mail->ErrorInfo . '</div>';
}
?>