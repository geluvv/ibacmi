<?php
// Brevo (Sendinblue) SMTP Configuration
define('SMTP_HOST', 'smtp-relay.brevo.com'); // ✅ CORRECT
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '99924f001@smtp-brevo.com'); // Your Brevo login email
define('SMTP_PASSWORD', 'bskPyEe3q4S4BEm'); // The SMTP key from Brevo dashboard
define('SMTP_FROM_EMAIL', 'ibacmiorddb@gmail.com'); // Must be verified in Brevo
define('SMTP_FROM_NAME', 'ORDDB Registration System');

// Setup Instructions:
// 1. Sign up at: https://www.brevo.com/
// 2. Go to Settings → SMTP & API
// 3. Generate SMTP key
// 4. Add sender email (ibacmiorddb@gmail.com) in Settings → Senders
// 5. Paste your SMTP key above
?>