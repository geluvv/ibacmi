<?php
/**
 * Google OAuth 2.0 Callback Handler
 * This file receives the authorization code from Google and exchanges it for tokens
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/AdminAccount/backup_errors.log');

session_start(); // ✅ Start session to detect user type

require_once 'db_connect.php';

// Load Google Drive configuration
$config = require_once 'config/google_drive_config.php';
define('GOOGLE_CLIENT_ID', $config['client_id']);
define('GOOGLE_CLIENT_SECRET', $config['client_secret']);
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');

// ✅ Detect user type from session or state parameter
$userType = 'admin'; // default
$userId = null;

if (isset($_SESSION['staff_user_id'])) {
    $userType = 'staff';
    $userId = $_SESSION['staff_user_id'];
    error_log("🔐 Detected STAFF user (ID: $userId)");
} elseif (isset($_GET['state']) && strpos($_GET['state'], 'staff_') === 0) {
    $userType = 'staff';
    $userId = (int)substr($_GET['state'], 6);
    error_log("🔐 Detected STAFF user from state (ID: $userId)");
}

error_log("=== OAUTH CALLBACK RECEIVED ===");
error_log("User type: $userType" . ($userId ? " (ID: $userId)" : ""));
error_log("Full URL: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
error_log("GET data: " . print_r($_GET, true));

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

/**
 * Store tokens persistently - SUPPORTS BOTH ADMIN AND STAFF
 */
function storeTokensPersistent($accessToken, $refreshToken = null, $userType = 'admin', $userId = null) {
    global $conn;
    
    try {
        // ✅ Determine setting prefix based on user type
        if ($userType === 'staff' && $userId) {
            $prefix = "google_drive_staff_{$userId}_";
            error_log("💾 Storing STAFF tokens for user ID: $userId");
        } else {
            $prefix = "google_drive_";
            error_log("💾 Storing ADMIN tokens (shared system)");
        }
        
        // Store access token
        $accessTokenKey = $prefix . 'access_token';
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                                VALUES (?, ?, NOW())
                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("sss", $accessTokenKey, $accessToken, $accessToken);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Saved: $accessTokenKey");
        
        // Store refresh token
        if ($refreshToken) {
            $refreshTokenKey = $prefix . 'refresh_token';
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                                   VALUES (?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("sss", $refreshTokenKey, $refreshToken, $refreshToken);
            $stmt->execute();
            $stmt->close();
            
            error_log("✅ Saved: $refreshTokenKey");
        }
        
        // Mark as connected
        $connectedKey = $prefix . 'connected';
        $connectedValue = '1';
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                               VALUES (?, ?, NOW())
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("sss", $connectedKey, $connectedValue, $connectedValue);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Saved: $connectedKey");
        error_log("✅✅✅ All tokens stored successfully!");
        return true;
    } catch (Exception $e) {
        error_log("❌ Error storing tokens: " . $e->getMessage());
        return false;
    }
}

// ✅ Handle Google's error responses
if (isset($_GET['error'])) {
    $errorType = $_GET['error'];
    $errorDesc = $_GET['error_description'] ?? 'Authorization denied';
    
    error_log("❌ OAuth error from Google: $errorType - $errorDesc");
    
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Authorization Failed</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .container { background: white; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #dc3545; }
            .error { color: #666; margin: 20px 0; padding: 15px; background: #ffebee; border-radius: 5px; }
            button { background: #dc3545; color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; }
            button:hover { background: #c82333; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>❌ Authorization Failed</h2>
            <p class="error"><?php echo htmlspecialchars($errorDesc); ?></p>
            
            <?php if ($errorType === 'access_denied' && (strpos($errorDesc, 'verification') !== false || strpos($errorDesc, '403') !== false)): ?>
            <div style="text-align: left; margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 5px;">
                <p><strong>Your app needs to be verified by Google.</strong></p>
                <p>To use this app before verification:</p>
                <ol>
                    <li>Go to Google Cloud Console → OAuth consent screen</li>
                    <li>Add your email as a "Test User"</li>
                    <li>Try connecting again</li>
                </ol>
            </div>
            <?php endif; ?>
            
            <button onclick="window.close()">Close Window</button>
        </div>
        
        <script>
            // Send error message to parent window
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_error',
                    message: '<?php echo addslashes($errorDesc); ?>'
                }, '*');
            }
            
            // Auto-close after 8 seconds
            setTimeout(function() { window.close(); }, 8000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ✅ Validate authorization code
if (!isset($_GET['code']) || empty(trim($_GET['code']))) {
    error_log("❌ No authorization code in callback");
    
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Authorization Error</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .container { background: white; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #dc3545; }
            button { background: #dc3545; color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>❌ Authorization Error</h2>
            <p>No authorization code received from Google.</p>
            <p>Please try again or contact support.</p>
            <button onclick="window.close()">Close Window</button>
        </div>
        <script>
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_error',
                    message: 'No authorization code received'
                }, '*');
            }
            setTimeout(function() { window.close(); }, 5000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

$authCode = trim($_GET['code']);
error_log("✅ Authorization code received: " . substr($authCode, 0, 30) . "...");

try {
    // ✅ CRITICAL: Use redirect URI from config (must match Google Console exactly)
    $redirectUri = $config['redirect_uri'];
    
    error_log("📍 Redirect URI: $redirectUri");
    error_log("📍 Client ID: " . substr(GOOGLE_CLIENT_ID, 0, 30) . "...");
    error_log("📍 Client Secret: " . (GOOGLE_CLIENT_SECRET ? 'Present' : 'Missing'));
    
    // Exchange authorization code for tokens
    $postData = [
        'code' => $authCode,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    error_log("📤 Exchanging authorization code for tokens...");
    error_log("Token URL: " . GOOGLE_TOKEN_URL);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => GOOGLE_TOKEN_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    error_log("📥 Token exchange HTTP code: $httpCode");
    
    if ($curlError) {
        throw new Exception("Network error: $curlError (Code: $curlErrno)");
    }
    
    if (empty($response)) {
        throw new Exception("Empty response from Google token endpoint");
    }
    
    error_log("📥 Token response (first 200 chars): " . substr($response, 0, 200));
    
    // Parse response
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ JSON parse error: " . json_last_error_msg());
        throw new Exception("Invalid JSON response: " . json_last_error_msg());
    }
    
    // Check for error in response
    if (isset($data['error'])) {
        $errorMsg = $data['error_description'] ?? $data['error'];
        error_log("❌ Google API error: $errorMsg");
        error_log("Full error response: " . $response);
        throw new Exception("Google API error: $errorMsg");
    }
    
    if ($httpCode !== 200) {
        $errorMsg = isset($data['error_description']) ? $data['error_description'] : 
                   (isset($data['error']) ? $data['error'] : "HTTP $httpCode");
        error_log("❌ HTTP error $httpCode: $errorMsg");
        throw new Exception("Token exchange failed: $errorMsg");
    }
    
    if (!isset($data['access_token'])) {
        error_log("❌ No access token in response");
        throw new Exception("No access token in response");
    }
    
    $accessToken = $data['access_token'];
    $refreshToken = $data['refresh_token'] ?? null;
    
    error_log("✅ Access token received: " . substr($accessToken, 0, 30) . "...");
    if ($refreshToken) {
        error_log("✅ Refresh token received: " . substr($refreshToken, 0, 30) . "...");
    } else {
        error_log("⚠️ No refresh token received (may occur if reconnecting)");
    }
    
    // ✅ Store tokens with user type context
    if (!storeTokensPersistent($accessToken, $refreshToken, $userType, $userId)) {
        throw new Exception("Failed to save tokens to database");
    }
    
    error_log("✅ OAuth flow completed successfully for $userType" . ($userId ? " (ID: $userId)" : "") . "!");
    
    // Success response
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Authorization Success</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
            .container { background: white; max-width: 500px; margin: 0 auto; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
            .success-icon { font-size: 64px; margin-bottom: 20px; }
            h2 { color: #28a745; margin: 20px 0; }
            .message { color: #666; font-size: 16px; margin: 20px 0; }
            .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #28a745; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h2>Authorization Successful!</h2>
            <p class="message">Google Drive has been connected successfully.</p>
            <div class="spinner"></div>
            <p style="color: #999;">This window will close automatically...</p>
        </div>
        
        <script>
            console.log('✅ OAuth success - sending message to parent window');
            
            // Send success message to parent window
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_success',
                    message: 'Google Drive connected successfully'
                }, '*');
                
                console.log('✅ Message sent to parent window');
            }
            
            // Close window after 2 seconds
            setTimeout(function() {
                console.log('Closing popup window...');
                window.close();
            }, 2000);
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    error_log("❌ OAuth callback exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Connection Error</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .container { background: white; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h2 { color: #dc3545; }
            .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 14px; }
            button { background: #dc3545; color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 20px; }
            button:hover { background: #c82333; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>❌ Connection Failed</h2>
            <div class="error">
                <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            
            <p>Please try again or contact support if the problem persists.</p>
            <button onclick="window.close()">Close Window</button>
        </div>
        
        <script>
            if (window.opener) {
                window.opener.postMessage({
                    type: 'oauth_error',
                    message: '<?php echo addslashes($e->getMessage()); ?>'
                }, '*');
            }
        </script>
    </body>
    </html>
    <?php
}

exit;
?>
