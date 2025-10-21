<?php
// Staff Backup API Router - Redirects all requests to Admin Backup API
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/backup_api_errors.log');

// Start output buffering to catch any unwanted output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

session_start();

// ✅ CRITICAL FIX: Ensure staff session is available
if (!isset($_SESSION['staff_user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// ✅ STORE STAFF ID IN A VARIABLE THAT backup.php CAN ACCESS
$_SESSION['current_user_type'] = 'staff';
$_SESSION['current_user_id'] = $_SESSION['staff_user_id'];

error_log("🔐 Staff API called by staff user ID: " . $_SESSION['staff_user_id']);
error_log("Session vars: " . print_r($_SESSION, true));

// ✅ HANDLE OAUTH CALLBACK (Google redirects without action parameter)
if (isset($_GET['code']) && !isset($_GET['action'])) {
    $_GET['action'] = 'oauth_callback';
    error_log("🔄 [STAFF] Detected OAuth callback, auto-setting action to oauth_callback");
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

error_log("📋 [STAFF] Requested action: '$action'");

// ❌ BLOCK ADMIN-ONLY ACTIONS
$blockedActions = [
    'add_school_year',
    'update_school_year',
    'delete_school_year',
    'get_archival_settings',
    'save_archival_settings',
    'run_auto_archival',
    'save_backup_path',
    'delete_backup'
];

if (in_array($action, $blockedActions)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied: This feature is only available to administrators.'
    ]);
    exit();
}

// ✅ ADD NOTIFICATION SUPPORT - GET PENDING COUNT (DESTINATION-AWARE)
if ($action === 'get_pending_count') {
    ob_end_clean();
    
    require_once __DIR__ . '/../db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        // ✅ Get destination from request (default: google_drive)
        $destination = $_GET['destination'] ?? 'google_drive';
        
        // Validate destination
        $validDestinations = ['google_drive', 'local', 'manual', 'auto_sync'];
        if (!in_array($destination, $validDestinations)) {
            $destination = 'google_drive';
        }
        
        // ✅ DESTINATION-AWARE PENDING COUNT
        $joinCondition = '';
        $whereCondition = '';
        
        if ($destination === 'google_drive' || $destination === 'auto_sync') {
            // Check backup_manifest for Google Drive backups
            $joinCondition = "LEFT JOIN backup_manifest bm ON sd.student_id = bm.student_id AND sd.id = bm.document_id";
            $whereCondition = "AND bm.id IS NULL";
        } else {
            // Check local_backup_manifest for local backups
            $joinCondition = "LEFT JOIN local_backup_manifest lbm ON sd.student_id = lbm.student_id AND sd.id = lbm.document_id";
            $whereCondition = "AND lbm.id IS NULL";
        }
        
        $query = "SELECT COUNT(DISTINCT sd.id) as pending
                  FROM student_documents sd
                  $joinCondition
                  WHERE sd.is_submitted = 1 
                  AND sd.file_path IS NOT NULL 
                  AND sd.file_path != ''
                  $whereCondition";
        
        $result = $conn->query($query);
        
        if ($result && $row = $result->fetch_assoc()) {
            $pendingCount = (int)$row['pending'];
            
            error_log("✅ [STAFF] Pending count for $destination: $pendingCount");
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'pending_count' => $pendingCount,
                    'destination' => $destination
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'pending_count' => 0,
                    'destination' => $destination
                ]
            ]);
        }
        exit();
        
    } catch (Exception $e) {
        error_log("Error getting pending count: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get pending count: ' . $e->getMessage()
        ]);
        exit();
    }
}

// ✅ HANDLE GOOGLE TOKEN SAVING DIRECTLY (DON'T PASS TO ADMIN)
if ($action === 'save_google_token') {
    ob_end_clean();
    
    require_once __DIR__ . '/../db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        // Get the JSON payload
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception('Access token is required');
        }
        
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 3600;
        $tokenType = $data['token_type'] ?? 'Bearer';
        $refreshToken = $data['refresh_token'] ?? null;
        
        // Calculate expiration time
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        
        // ✅ USER-SPECIFIC TOKEN STORAGE FOR STAFF
        $userId = $_SESSION['staff_user_id'];
        $userType = 'staff';
        $settingPrefix = "google_drive_{$userType}_{$userId}";
        
        error_log("💾 Saving Google token for STAFF ID: {$userId}");
        error_log("Setting prefix: {$settingPrefix}");
        
        // Save access token
        $accessTokenKey = "{$settingPrefix}_access_token";
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                                VALUES (?, ?, NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $accessTokenKey, $accessToken, $accessToken);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Saved access token: {$accessTokenKey}");
        
        // Save refresh token if provided
        if ($refreshToken) {
            $refreshTokenKey = "{$settingPrefix}_refresh_token";
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                                   VALUES (?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param('sss', $refreshTokenKey, $refreshToken, $refreshToken);
            $stmt->execute();
            $stmt->close();
            
            error_log("✅ Saved refresh token: {$refreshTokenKey}");
        }
        
        // Save token expiration
        $expiresAtKey = "{$settingPrefix}_expires_at";
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                               VALUES (?, ?, NOW()) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $expiresAtKey, $expiresAt, $expiresAt);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Saved expiration: {$expiresAtKey}");
        
        // Mark as connected for THIS staff user
        $connectedKey = "{$settingPrefix}_connected";
        $connectedValue = '1';
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                               VALUES (?, ?, NOW()) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $connectedKey, $connectedValue, $connectedValue);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ Marked as connected: {$connectedKey}");
        error_log("✅✅✅ Google token COMPLETE for staff user: {$userId}");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Google Drive connected successfully',
            'data' => [
                'expires_at' => $expiresAt,
                'user_type' => 'staff',
                'user_id' => $userId
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("❌ Error saving Google token: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save Google credentials: ' . $e->getMessage()
        ]);
        exit();
    }
}

// ✅ HANDLE GOOGLE DISCONNECT DIRECTLY
if ($action === 'disconnect_google') {
    ob_end_clean();
    
    require_once __DIR__ . '/../db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        // ✅ USER-SPECIFIC DISCONNECT FOR STAFF
        $userId = $_SESSION['staff_user_id'];
        $userType = 'staff';
        $settingPrefix = "google_drive_{$userType}_{$userId}";
        
        error_log("🔌 Disconnecting Google Drive for STAFF ID: {$userId}");
        error_log("Deleting keys with prefix: {$settingPrefix}");
        
        // Delete user-specific Google Drive settings
        $stmt = $conn->prepare("DELETE FROM system_settings WHERE setting_name LIKE ?");
        $pattern = $settingPrefix . '%';
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("✅ Deleted {$affectedRows} settings for staff user: {$userId}");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Google Drive disconnected successfully',
            'user_type' => 'staff',
            'user_id' => $userId,
            'deleted_count' => $affectedRows
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("❌ Error disconnecting Google: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to disconnect Google Drive: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Debug endpoint
if ($action === 'debug_test') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Staff backup API is working',
        'staff_id' => $_SESSION['staff_user_id'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ✅ GET OAUTH AUTH URL FOR STAFF
if ($action === 'get_auth_url') {
    ob_end_clean();
    
    // ✅ FIX: Assign config to variable
    $config = require_once __DIR__ . '/../config/google_drive_config.php';
    
    header('Content-Type: application/json');
    
    try {
        $userId = $_SESSION['staff_user_id'];
        
        // ✅ Use STAFF-SPECIFIC redirect URI (WITHOUT query parameter)
        $redirectUri = 'http://localhost/ibacmi/StaffAccount/backup_api.php';
        
        error_log("📍 [STAFF] Auth URL redirect URI: $redirectUri");
        error_log("📍 [STAFF] User ID: $userId");
        error_log("📍 [STAFF] Client ID: " . substr($config['client_id'], 0, 30) . "...");
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => 'staff_' . $userId // ✅ Include staff context in state
        ]);
        
        echo json_encode([
            'status' => 'success',
            'data' => ['auth_url' => $authUrl]
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("❌ [STAFF] Error generating auth URL: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// ✅ OAUTH CALLBACK FOR STAFF
if ($action === 'oauth_callback') {
    error_log("=== [STAFF] OAUTH CALLBACK RECEIVED ===");
    error_log("GET data: " . print_r($_GET, true));
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // ✅ FIX: Assign config to variable
    $config = require_once __DIR__ . '/../config/google_drive_config.php';
    
    // Handle errors
    if (isset($_GET['error'])) {
        $errorType = $_GET['error'];
        $errorDesc = $_GET['error_description'] ?? 'Authorization denied';
        
        error_log("❌ [STAFF] OAuth error: $errorType - $errorDesc");
        
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Authorization Failed</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
                .error { color: #dc3545; margin: 20px 0; font-size: 16px; }
                button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; }
                button:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h2>❌ Authorization Failed</h2>
            <p class="error"><?php echo htmlspecialchars($errorDesc); ?></p>
            <p><button onclick="window.close()">Close Window</button></p>
            <script>
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'oauth_error',
                        message: <?php echo json_encode($errorDesc); ?>
                    }, '*');
                }
            </script>
        </body>
        </html>
        <?php
        exit();
    }
    
    // Validate authorization code
    if (!isset($_GET['code']) || empty(trim($_GET['code']))) {
        error_log("❌ [STAFF] No authorization code received");
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Authorization Failed</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
                .error { color: #dc3545; }
            </style>
        </head>
        <body>
            <h2>❌ Authorization Failed</h2>
            <p class="error">No authorization code received from Google</p>
            <p><button onclick="window.close()">Close Window</button></p>
            <script>
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'oauth_error',
                        message: 'No authorization code received'
                    }, '*');
                }
            </script>
        </body>
        </html>
        <?php
        exit();
    }
    
    $authCode = trim($_GET['code']);
    $state = $_GET['state'] ?? '';
    
    error_log("✅ [STAFF] Authorization code received");
    error_log("State: $state");
    
    // Extract staff ID from state
    $userId = null;
    if (strpos($state, 'staff_') === 0) {
        $userId = (int)substr($state, 6);
    } else {
        $userId = $_SESSION['staff_user_id'] ?? null;
    }
    
    if (!$userId) {
        error_log("❌ [STAFF] No staff user ID found");
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <body>
            <h2>❌ Session Error</h2>
            <p>Unable to identify staff user. Please try again.</p>
            <script>
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'oauth_error',
                        message: 'Session error. Please try again.'
                    }, '*');
                }
                setTimeout(function() { window.close(); }, 2000);
            </script>
        </body>
        </html>
        <?php
        exit();
    }
    
    try {
        require_once __DIR__ . '/../db_connect.php';
        
        // Exchange code for tokens - USE SAME REDIRECT URI AS AUTH URL (WITHOUT query parameter)
        $redirectUri = 'http://localhost/ibacmi/StaffAccount/backup_api.php';
        
        $postData = [
            'code' => $authCode,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        error_log("📤 [STAFF] Exchanging authorization code for tokens...");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
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
        curl_close($ch);
        
        error_log("📥 [STAFF] Token exchange HTTP code: $httpCode");
        
        if ($httpCode !== 200) {
            throw new Exception("Token exchange failed: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception("No access token in response");
        }
        
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        
        error_log("✅ [STAFF] Tokens received");
        
        // ✅ Store tokens with staff-specific prefix
        $userType = 'staff';
        $settingPrefix = "google_drive_{$userType}_{$userId}";
        
        // Save access token
        $accessTokenKey = "{$settingPrefix}_access_token";
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                                VALUES (?, ?, NOW()) 
                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $accessTokenKey, $accessToken, $accessToken);
        $stmt->execute();
        $stmt->close();
        
        // Save refresh token
        if ($refreshToken) {
            $refreshTokenKey = "{$settingPrefix}_refresh_token";
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                                   VALUES (?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param('sss', $refreshTokenKey, $refreshToken, $refreshToken);
            $stmt->execute();
            $stmt->close();
        }
        
        // Save expiration
        $expiresAtKey = "{$settingPrefix}_expires_at";
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                               VALUES (?, ?, NOW()) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $expiresAtKey, $expiresAt, $expiresAt);
        $stmt->execute();
        $stmt->close();
        
        // Mark as connected
        $connectedKey = "{$settingPrefix}_connected";
        $connectedValue = '1';
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                               VALUES (?, ?, NOW()) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $connectedKey, $connectedValue, $connectedValue);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅✅✅ [STAFF] Google Drive connected for staff ID: $userId");
        
        // Success page
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Authorization Success</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 40px; 
                    text-align: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                .success-icon { font-size: 64px; margin: 20px 0; animation: bounce 1s; }
                .message { font-size: 18px; margin: 20px 0; }
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-20px); }
                    60% { transform: translateY(-10px); }
                }
            </style>
        </head>
        <body>
            <div class="success-icon">✅</div>
            <h2>Authorization Successful!</h2>
            <p class="message">Google Drive has been connected successfully.</p>
            <p>This window will close automatically...</p>
            
            <script>
                // Send success message to parent window
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'oauth_success',
                        message: 'Google Drive connected successfully'
                    }, '*');
                }
                
                // Close window after delay
                setTimeout(function() {
                    window.close();
                }, 1500);
            </script>
        </body>
        </html>
        <?php
        exit();
        
    } catch (Exception $e) {
        error_log("❌ [STAFF] OAuth exception: " . $e->getMessage());
        
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html>
        <body>
            <h2>❌ Connection Failed</h2>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <script>
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'oauth_error',
                        message: <?php echo json_encode($e->getMessage()); ?>
                    }, '*');
                }
                setTimeout(function() { window.close(); }, 3000);
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}

// ✅ HANDLE GET SCHOOL YEARS (STAFF CAN VIEW)
if ($action === 'get_school_years') {
    error_log("📅 [STAFF] ENTERED get_school_years handler for staff ID: " . $_SESSION['staff_user_id']);
    
    ob_end_clean();
    
    require_once __DIR__ . '/../db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        error_log("📅 [STAFF] Fetching school years from database");
        
        // Check which column exists in the database
        $testQuery = "SHOW COLUMNS FROM school_years LIKE 'school_year'";
        $testResult = $conn->query($testQuery);
        $hasSchoolYearColumn = $testResult && $testResult->num_rows > 0;
        
        // Determine which column to use
        $yearColumn = $hasSchoolYearColumn ? 'school_year' : 'year_label';
        
        error_log("📊 [STAFF] Using column: {$yearColumn}");
        
        $query = "SELECT id, {$yearColumn} as school_year, is_active FROM school_years ORDER BY {$yearColumn} DESC";
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $schoolYears = [];
        $activeYear = null;
        
        while ($row = $result->fetch_assoc()) {
            $schoolYears[] = [
                'id' => $row['id'],
                'school_year' => $row['school_year'],
                'is_active' => (int)$row['is_active']
            ];
            
            if ($row['is_active'] == 1) {
                $activeYear = $row['school_year'];
            }
        }
        
        error_log("✅ [STAFF] Found " . count($schoolYears) . " school years, active: " . ($activeYear ?? 'none'));
        
        $response = [
            'status' => 'success',
            'data' => [
                'school_years' => $schoolYears,
                'active_year' => $activeYear
            ]
        ];
        
        error_log("📤 [STAFF] Sending response: " . json_encode($response));
        
        echo json_encode($response);
        exit();
        
    } catch (Exception $e) {
        error_log("❌ [STAFF] Error loading school years: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to load school years: ' . $e->getMessage()
        ]);
        exit();
    }
}

// ✅ HANDLE CHECK CONNECTION FOR STAFF DIRECTLY
if ($action === 'check_connection') {
    ob_end_clean();
    
    require_once __DIR__ . '/../db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        $userId = $_SESSION['staff_user_id'];
        $userType = 'staff';
        $settingPrefix = "google_drive_{$userType}_{$userId}";
        
        error_log("🔍 [STAFF] Checking connection for staff ID: {$userId}");
        error_log("Looking for settings with prefix: {$settingPrefix}");
        
        // Check if connected flag exists AND access token exists
        $connectedKey = "{$settingPrefix}_connected";
        $accessTokenKey = "{$settingPrefix}_access_token";
        
        $query = "SELECT setting_name, setting_value FROM system_settings 
                  WHERE setting_name IN (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $connectedKey, $accessTokenKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hasConnectedFlag = false;
        $hasAccessToken = false;
        
        while ($row = $result->fetch_assoc()) {
            error_log("Found: {$row['setting_name']} = " . substr($row['setting_value'], 0, 20) . "...");
            if ($row['setting_name'] === $connectedKey && $row['setting_value'] === '1') {
                $hasConnectedFlag = true;
            }
            if ($row['setting_name'] === $accessTokenKey && !empty($row['setting_value'])) {
                $hasAccessToken = true;
            }
        }
        
        $stmt->close();
        
        $isConnected = $hasConnectedFlag && $hasAccessToken;
        
        error_log("📊 [STAFF] Connection status:");
        error_log("  - Has connected flag: " . ($hasConnectedFlag ? 'YES' : 'NO'));
        error_log("  - Has access token: " . ($hasAccessToken ? 'YES' : 'NO'));
        error_log("  - Final result: " . ($isConnected ? 'CONNECTED' : 'NOT CONNECTED'));
        
        // Get auto-sync status
        $syncSettingName = "auto_sync_{$userType}_{$userId}_status";
        $syncQuery = "SELECT setting_value FROM system_settings WHERE setting_name = ?";
        $stmt = $conn->prepare($syncQuery);
        $stmt->bind_param("s", $syncSettingName);
        $stmt->execute();
        $syncResult = $stmt->get_result();
        $autoSyncStatus = 'disabled';
        
        if ($syncResult && $syncResult->num_rows > 0) {
            $row = $syncResult->fetch_assoc();
            $autoSyncStatus = $row['setting_value'];
        }
        $stmt->close();
        
        // ✅ FIX: Get user email if connected (same as admin version)
        $userEmail = null;
        
        if ($isConnected) {
            error_log("📧 [STAFF] Attempting to fetch user email...");
            try {
                // Get the access token
                $tokenQuery = "SELECT setting_value FROM system_settings WHERE setting_name = ?";
                $stmt = $conn->prepare($tokenQuery);
                $stmt->bind_param("s", $accessTokenKey);
                $stmt->execute();
                $tokenResult = $stmt->get_result();
                
                if ($tokenResult && $tokenResult->num_rows > 0) {
                    $tokenRow = $tokenResult->fetch_assoc();
                    $accessToken = $tokenRow['setting_value'];
                    
                    // Try to get user email (optional, non-blocking)
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/about?fields=user',
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 3, // Very short timeout - this is optional
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 200) {
                        $data = json_decode($response, true);
                        $userEmail = $data['user']['emailAddress'] ?? null;
                        error_log("   ✅ [STAFF] Got user email: $userEmail");
                    } else {
                        error_log("   ℹ️ [STAFF] Could not get user email (HTTP $httpCode) - but connection is still valid");
                        $userEmail = null;
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("   ℹ️ [STAFF] Email fetch failed: " . $e->getMessage() . " - but connection is still valid");
                $userEmail = null;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'connected' => $isConnected,
                'user_email' => $userEmail, // ✅ Added user email
                'sync_status' => $autoSyncStatus,
                'user_type' => 'staff',
                'user_id' => $userId
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("❌ [STAFF] Error checking connection: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to check connection: ' . $e->getMessage()
        ]);
        exit();
    }
}

// ✅ HANDLE TOGGLE SYNC FOR STAFF
if ($action === 'toggle_sync') {
    ob_end_clean();
    
    require_once __DIR__ . '/../db_connect.php';
    
    header('Content-Type: application/json');
    
    try {
        $enable = isset($_POST['enable']) ? (int)$_POST['enable'] : 0;
        $newStatus = $enable ? 'enabled' : 'disabled';
        
        $userId = $_SESSION['staff_user_id'];
        $userType = 'staff';
        
        error_log("🔄 [STAFF] Toggle sync for staff ID: {$userId} to: $newStatus");
        
        // Check if Google Drive is connected first
        $settingPrefix = "google_drive_{$userType}_{$userId}";
        $connectedKey = "{$settingPrefix}_connected";
        
        $query = "SELECT setting_value FROM system_settings WHERE setting_name = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $connectedKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $isConnected = false;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $isConnected = ($row['setting_value'] === '1');
        }
        $stmt->close();
        
        if (!$isConnected && $enable) {
            throw new Exception('Please connect to Google Drive first');
        }
        
        // Save auto-sync status for this staff user
        $syncSettingName = "auto_sync_{$userType}_{$userId}_status";
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at)
                               VALUES (?, ?, NOW())
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $syncSettingName, $newStatus, $newStatus);
        $stmt->execute();
        $stmt->close();
        
        error_log("✅ [STAFF] Auto-sync status updated to: $newStatus");
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'sync_status' => $newStatus,
                'user_type' => 'staff',
                'user_id' => $userId
            ]
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("❌ [STAFF] Error toggling sync: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// Set staff context flags
define('IS_STAFF_VIEW', true);
define('STAFF_USER_ID', $_SESSION['staff_user_id']);

// Log the action for debugging
error_log("📋 Staff backup API routing action '$action' to admin backup.php");
error_log("Staff ID: " . $_SESSION['staff_user_id']);

// Clear output buffer before including admin backup
ob_end_clean();

// Include the admin backup API for other actions
try {
    require_once __DIR__ . '/../AdminAccount/backup.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load backup system: ' . $e->getMessage()
    ]);
    exit();
}
?>