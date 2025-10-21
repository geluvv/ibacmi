<?php
// filepath: c:\xampp\htdocs\ibacmi\StaffAccount\staffbackup.php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once '../db_connect.php';

// Start session
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION['staff_user_id'])) {
    header("Location: stafflogin.html");
    exit();
}

// ✅ ADD THIS: Set session variables for backup.php to use
$_SESSION['current_user_type'] = 'staff';
$_SESSION['current_user_id'] = $_SESSION['staff_user_id'];

// Fetch staff information - IMPROVED QUERY
$staffInfo = array();
$userId = $_SESSION['staff_user_id'];

// First, get data from staff_users table
$stmt = $conn->prepare("SELECT username, email, role, first_name, last_name FROM staff_users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $staffInfo = $userData;
}
$stmt->close();

// Then, get profile data from staff_profiles table (if exists)
$stmt = $conn->prepare("SELECT * FROM staff_profiles WHERE staff_user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $profileData = $result->fetch_assoc();
    // Merge profile data, giving priority to profile table (this includes profile_picture!)
    $staffInfo = array_merge($staffInfo, $profileData);
}
$stmt->close();


// Ensure we have default values
if (empty($staffInfo['first_name'])) {
    $staffInfo['first_name'] = explode(' ', $staffInfo['username'] ?? 'Staff')[0];
}
if (empty($staffInfo['last_name'])) {
    $staffInfo['last_name'] = explode(' ', $staffInfo['username'] ?? 'Member')[1] ?? 'Member';
}

$isStaffView = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - Backup & Sync Management - IBACMI</title>
     <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png">
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../AdminAccount/css/sidebar.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
    :root {
        --primary: #a00000;
        --success-color: #28a745;
        --text-dark: #343a40;
        --border-radius: 12px;
        --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    body {
        font-family: BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: #f8f9fa;
        color: var(--text-dark);
        margin: 0;
        padding: 0;
    }

    .main-content {
        margin-left: 280px;
        padding: 2rem;
        min-height: 100vh;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .page-subtitle {
        color: #6c757d;
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .auto-sync-panel {
        background: linear-gradient(135deg, var(--primary), #a00000);
        color: white;
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
    }

    .auto-sync-panel::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        transform: translate(50%, -50%);
    }

    .sync-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 2;
    }

    .sync-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sync-subtitle {
        opacity: 0.8;
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .google-drive-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--success-color);
    }

    .sync-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        position: relative;
        z-index: 2;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.1);
        padding: 1.25rem;
        border-radius: 10px;
        text-align: left;
        backdrop-filter: blur(10px);
    }

    .stat-label {
        font-size: 0.8rem;
        opacity: 0.8;
        margin-bottom: 0.5rem;
        display: block;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        display: block;
        line-height: 1;
    }

    .sync-controls {
        display: flex;
        gap: 1rem;
        position: relative;
        z-index: 2;
        flex-wrap: wrap;
    }

    .sync-controls .btn {
        border-radius: 25px;
        padding: 0.5rem 1.25rem;
        font-weight: 500;
        border: 1.5px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.1);
        color: white;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .sync-controls .btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-1px);
    }

    .sync-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 1rem;
    }

    .sync-status-enabled {
        background: rgba(40, 167, 69, 0.2);
        color: var(--success-color);
        border: 2px solid var(--success-color);
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stats-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: none;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
    }

    .stats-icon.backups { background: linear-gradient(135deg, #3b82f6, #1e40af); }
    .stats-icon.students { background: linear-gradient(135deg, #10b981, #059669); }
    .stats-icon.files { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .stats-icon.storage { background: linear-gradient(135deg, #f59e0b, #d97706); }

    .stats-content h3 {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        color: var(--text-dark);
    }

    .stats-content p {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 0;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
        align-items: start;
    }

    .backup-section {
        background: white;
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: none;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }

    .section-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--primary), #a00000);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-dark);
    }

    .section-subtitle {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 0;
    }

    .backup-form .form-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.4rem;
        font-size: 0.85rem;
    }

    .backup-form .form-control, .backup-form .form-select {
        border-radius: 8px;
        border: 1px solid #e9ecef;
        padding: 0.65rem 0.9rem;
        font-size: 0.85rem;
    }

    .backup-form .form-control:focus, .backup-form .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.1);
    }
    
    .backup-form .mb-4 {
        margin-bottom: 1rem !important;
    }
    
    .backup-form small.text-muted {
        font-size: 0.7rem;
    }

    .backup-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .btn-google-drive {
        background: #4285f4;
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.9rem;
        flex: 1;
    }

    .btn-local-storage {
        background: #6c757d;
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.9rem;
        flex: 1;
    }

    .recent-backups {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    .table-header {
        background: var(--primary);
        color: white;
        padding: 1.5rem 2rem;
        margin: 0;
    }

    .table-header h5 {
        margin: 0;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .table {
        margin-bottom: 0;
    }

    .table th {
        background: #f8f9fa;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem 1.5rem;
        border: none;
    }

    .table td {
        padding: 1rem 1.5rem;
        vertical-align: middle;
        border-top: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }

    .table tr:hover {
        background-color: #f8f9fa;
    }

    .badge-success {
        background: var(--success-color);
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .btn-download {
        background: var(--primary);
        border: none;
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.8rem;
    }

    .pagination-container {
        background: #f8f9fa;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pagination-info {
        font-weight: 500;
    }

    .pagination {
        gap: 0.25rem;
        margin: 0;
    }

    .page-link {
        border-radius: 6px;
        border: 1px solid #dee2e6;
        color: var(--primary);
        padding: 0.375rem 0.75rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .page-link:hover {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .page-item.active .page-link {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
        font-weight: 600;
    }

    .page-item.disabled .page-link {
        color: #6c757d;
        background-color: #fff;
        border-color: #dee2e6;
    }

    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 992px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 1rem;
        }

        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .table {
            font-size: 0.85rem;
        }
        
        .pagination-container {
            flex-direction: column;
            gap: 1rem;
        }
    }

    /* ===== NEW MODERN BACKUP UI STYLES ===== */
    
    /* Modern Input Styling - Compact */
    .modern-input {
        border: 2px solid #e9ecef !important;
        border-radius: 8px !important;
        padding: 0.65rem 0.9rem !important;
        font-size: 0.85rem !important;
        transition: all 0.3s ease !important;
    }

    .modern-input:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.1) !important;
        transform: translateY(-1px);
    }

    /* Backup Directory Modern Design - Compact Version */
    .backup-directory-modern {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 1rem;
        transition: all 0.3s ease;
    }

    .backup-directory-modern:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 15px rgba(128, 0, 0, 0.1);
    }

    .directory-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .directory-icon-wrapper {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, #ffc107, #ff9800);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
        box-shadow: 0 3px 10px rgba(255, 193, 7, 0.3);
    }

    .directory-title-wrapper {
        flex: 1;
    }

    .directory-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.1rem;
    }

    .directory-subtitle {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .current-path-box {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 0.75rem;
        margin-top: 0.75rem;
    }

    .path-label-wrapper {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #6c757d;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.4rem;
    }

    .path-value {
        font-family: 'Courier New', monospace;
        font-size: 0.8rem;
        color: var(--text-dark);
        font-weight: 600;
        padding: 0.4rem;
        background: #f8f9fa;
        border-radius: 5px;
        word-break: break-all;
    }

    /* Storage Options Grid - Compact */
    .storage-options-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .storage-option {
        cursor: pointer;
        position: relative;
        transition: transform 0.2s ease;
    }

    .storage-option:hover {
        transform: translateY(-2px);
    }

    .storage-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .storage-option-label {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        background: white;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        margin-bottom: 0;
    }

    .storage-option-label::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(128, 0, 0, 0.05), rgba(128, 0, 0, 0.02));
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .storage-option input:checked + .storage-option-label {
        border-color: var(--primary);
        background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
        box-shadow: 0 4px 15px rgba(128, 0, 0, 0.15);
    }

    .storage-option input:checked + .storage-option-label::before {
        opacity: 1;
    }

    .storage-option-label:hover {
        border-color: var(--primary);
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
    }

    .storage-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
        flex-shrink: 0;
    }

    .storage-icon.local-storage {
        background: linear-gradient(135deg, #6c757d, #495057);
        box-shadow: 0 3px 10px rgba(108, 117, 125, 0.3);
    }

    .storage-icon.cloud-storage {
        background: linear-gradient(135deg, #4285f4, #1a73e8);
        box-shadow: 0 3px 10px rgba(66, 133, 244, 0.3);
    }

    .storage-option input:checked + .storage-option-label .storage-icon {
        transform: scale(1.05);
    }

    .storage-details {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.15rem;
        position: relative;
        z-index: 1;
        flex: 1;
    }

    .storage-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--text-dark);
    }

    .storage-desc {
        font-size: 0.75rem;
        color: #6c757d;
        text-align: left;
    }

    .storage-check {
        position: relative;
        color: var(--primary);
        font-size: 1.1rem;
        opacity: 0;
        transition: all 0.3s ease;
        z-index: 1;
        flex-shrink: 0;
    }

    .storage-option input:checked + .storage-option-label .storage-check {
        opacity: 1;
        transform: scale(1.1);
    }

    /* Backup Submit Button - Compact */
    .backup-submit-btn {
        background: linear-gradient(135deg, var(--primary), #a00000) !important;
        border: none !important;
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.5px !important;
        text-transform: uppercase !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 3px 12px rgba(128, 0, 0, 0.3) !important;
        padding: 0.75rem 1.5rem !important;
    }

    .backup-submit-btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 5px 20px rgba(128, 0, 0, 0.4) !important;
    }

    .backup-submit-btn:active {
        transform: translateY(0) !important;
    }

    /* Responsive Adjustments */
    @media (max-width: 576px) {
        .storage-options-grid {
            grid-template-columns: 1fr;
        }
        
        .backup-directory-modern {
            padding: 1rem;
        }
        
        .directory-icon-wrapper {
            width: 40px;
            height: 40px;
            font-size: 1.25rem;
        }
    }
    </style>
</head>
<body>
    <?php 
    include 'staffsidebar.php';
    renderSidebar('backup', 'staff', $staffInfo);
    ?>
    
    <div class="main-content">
        <div id="alertContainer"></div>
        
        <div class="page-header">
            <h1 class="page-title">Backup & Sync Management</h1>
            <p class="page-subtitle">Manage backups and synchronization</p>
        </div>

        <!-- Auto-Sync Control Panel -->
        <div class="auto-sync-panel">
            <div class="sync-header">
                <div>
                    <h4 class="sync-title">
                        <i class="fas fa-sync-alt"></i>
                        Auto-Sync Control
                    </h4>
                    <p class="sync-subtitle">Google Drive Integration</p>
                    <div class="sync-status-badge sync-status-enabled" id="syncStatusBadge" style="display: inline-flex;">
                        <i class="fas fa-check-circle"></i>
                        Enabled
                    </div>
                </div>
                <div class="google-drive-status" id="googleDriveStatus">
                    <div class="status-indicator" id="statusIndicator" style="background: #dc3545;"></div>
                    <span id="connectionStatus">Checking connection...</span>
                </div>
            </div>
            
            <div class="auth-buttons mb-3" id="authButtons">
                <button class="btn btn-sm btn-outline-primary me-2" id="authenticateBtn" onclick="handleGoogleAuth()">
                    <i class="fas fa-link"></i> Connect to Google Drive
                </button>
                <button class="btn btn-sm btn-outline-danger" id="disconnectBtn" onclick="disconnectGoogle()" style="display: none;">
                    <i class="fas fa-unlink"></i> Disconnect
                </button>
            </div>

            <div class="sync-controls">
                <button class="btn" onclick="toggleAutoSync('enabled')" id="enableSyncBtn" style="display: none;">
                    <i class="fas fa-play"></i> Enable Sync
                </button>
                <button class="btn" onclick="toggleAutoSync('paused')" id="pauseSyncBtn">
                    <i class="fas fa-pause"></i> Pause Sync
                </button>
                <button class="btn" onclick="toggleAutoSync('disabled')" id="disableSyncBtn">
                    <i class="fas fa-times"></i> Disable
                </button>
            </div>
        </div>

        <!-- Statistics Cards Row -->
        <div class="stats-row">
            <div class="stats-card">
                <div class="stats-icon backups">
                    <i class="fas fa-archive"></i>
                </div>
                <div class="stats-content">
                    <h3 id="totalBackups">-</h3>
                    <p>Total Backups</p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon students">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <h3 id="totalStudents">-</h3>
                    <p>Students</p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon files">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stats-content">
                    <h3 id="totalFiles">-</h3>
                    <p>Total Files</p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon storage">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stats-content">
                    <h3 id="storageUsed">-</h3>
                    <p>Storage Used</p>
                </div>
            </div>
            
            <div class="stats-card" style="border-left: 4px solid #28a745;">
                <div class="stats-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-content">
                    <h3 id="pendingFilesCount" data-stat="pending" style="color: #28a745; font-weight: 700;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </h3>
                    <p>Pending Files</p>
                </div>
            </div>
        </div>

        <!-- Manual Backup & Recent Backups Grid -->
        <div class="content-grid">
            <!-- Manual Backup Section (Left Column) -->
            <div class="backup-section" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-left: 4px solid var(--primary); height: fit-content;">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #dc3545, #c82333); box-shadow: 0 3px 12px rgba(220, 53, 69, 0.3);">
                        <i class="fas fa-download"></i>
                    </div>
                    <div>
                        <h2 class="section-title" style="background: linear-gradient(135deg, var(--primary), #dc3545); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700;">Manual Backup</h2>
                        <p class="section-subtitle">Create backup on-demand</p>
                    </div>
                </div>

                <!-- ✨ REDESIGNED Backup Directory Configuration (Read-Only for Staff) -->
                <div class="backup-directory-modern mb-3">
                    <div class="directory-header">
                        <div class="directory-icon-wrapper">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="directory-title-wrapper">
                            <h6 class="directory-title">Backup Directory</h6>
                            <small class="directory-subtitle">Local storage path (Admin configured)</small>
                        </div>
                    </div>
                    
                    <div class="current-path-box">
                        <div class="path-label-wrapper">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Current Path</span>
                        </div>
                        <div class="path-value" id="currentBackupPath">Loading...</div>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center mt-3 mb-0" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Only administrators can change the backup directory path.</small>
                    </div>
                </div>

                <form class="backup-form" id="backupForm">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tag me-1"></i>Backup Name
                        </label>
                        <input 
                            type="text" 
                            class="form-control modern-input" 
                            id="backupName" 
                            name="backupName"
                            value="IBACMI_Backup" 
                            required
                            placeholder="Enter backup name..."
                        >
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            A descriptive name for your backup
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-alt me-1"></i>School Year
                        </label>
                        <select class="form-select modern-input" id="schoolYear" name="schoolYear" required>
                            <option value="">Loading school years...</option>
                        </select>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Select the school year to backup
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-hdd me-1"></i>Storage Type
                        </label>
                        <div class="storage-options-grid">
                            <div class="storage-option" onclick="selectStorage('local')">
                                <input 
                                    class="form-check-input" 
                                    type="radio" 
                                    name="storageType" 
                                    id="localStorage" 
                                    value="local" 
                                    checked
                                >
                                <label class="storage-option-label" for="localStorage">
                                    <div class="storage-icon local-storage">
                                        <i class="fas fa-server"></i>
                                    </div>
                                    <div class="storage-details">
                                        <span class="storage-name">Local Storage</span>
                                        <span class="storage-desc">Save to server directory</span>
                                    </div>
                                    <div class="storage-check">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </label>
                            </div>

                            <div class="storage-option" onclick="selectStorage('cloud')">
                                <input 
                                    class="form-check-input" 
                                    type="radio" 
                                    name="storageType" 
                                    id="cloudStorage" 
                                    value="cloud"
                                >
                                <label class="storage-option-label" for="cloudStorage">
                                    <div class="storage-icon cloud-storage">
                                        <i class="fab fa-google-drive"></i>
                                    </div>
                                    <div class="storage-details">
                                        <span class="storage-name">Google Drive</span>
                                        <span class="storage-desc">Upload to cloud storage</span>
                                    </div>
                                    <div class="storage-check">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary w-100 backup-submit-btn" onclick="createBackup()">
                        <i class="fas fa-cloud-upload-alt me-2"></i>
                        Create Backup
                    </button>
                </form>
            </div>

            <!-- Recent Backups Table (Right Column) -->
            <div class="recent-backups">
            <div class="table-header">
                <h5>Recent Backups</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Backup Name</th>
                            <th>Type</th>
                            <th>Storage</th>
                            <th>Files</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backupTableBody">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading backups...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Container -->
            <div class="pagination-container" id="paginationContainer" style="display: none;">
                <div class="pagination-info">
                    Showing <span id="paginationStart">0</span> to <span id="paginationEnd">0</span> of <span id="paginationTotal">0</span> backups
                </div>
                <nav>
                    <ul class="pagination mb-0" id="paginationNav">
                        <!-- Pagination buttons will be inserted here -->
                    </ul>
                </nav>
            </div>
            </div>
        </div>
    </div>

    <?php include 'staffprofile.php'; ?>

    <!-- ⭐ ADD DEBUG SCRIPT RIGHT HERE ⭐ -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== PROFILE MODAL DEBUG ===');
        
        // Check if modal exists
        const profileModal = document.getElementById('profileModal');
        console.log('1. Profile modal exists:', !!profileModal);
        if (profileModal) {
            console.log('   Modal HTML found');
        } else {
            console.error('   ❌ Modal HTML NOT found!');
        }
        
        // Check if Bootstrap is loaded
        console.log('2. Bootstrap loaded:', typeof bootstrap !== 'undefined');
        if (typeof bootstrap !== 'undefined') {
            console.log('   Bootstrap.Modal:', typeof bootstrap.Modal);
        } else {
            console.error('   ❌ Bootstrap NOT loaded!');
        }
        
        // Check if edit button exists
        const editBtn = document.querySelector('.profile-edit-btn');
        console.log('3. Edit button exists:', !!editBtn);
        
        if (editBtn) {
            console.log('   Edit button found');
            console.log('   Attributes:', {
                'data-bs-toggle': editBtn.getAttribute('data-bs-toggle'),
                'data-bs-target': editBtn.getAttribute('data-bs-target'),
                'href': editBtn.getAttribute('href'),
                'class': editBtn.className
            });
            
            // Check computed styles
            const styles = window.getComputedStyle(editBtn);
            console.log('   Styles:', {
                'display': styles.display,
                'visibility': styles.visibility,
                'pointer-events': styles.pointerEvents,
                'cursor': styles.cursor,
                'opacity': styles.opacity
            });
            
            // Check position and if anything covers it
            const rect = editBtn.getBoundingClientRect();
            console.log('   Position:', rect);
            const elementAtPoint = document.elementFromPoint(
                rect.left + rect.width/2, 
                rect.top + rect.height/2
            );
            console.log('   Element at button center:', elementAtPoint);
            console.log('   Is button on top?', elementAtPoint === editBtn || editBtn.contains(elementAtPoint));
            
            // Add manual click listener to test
            editBtn.addEventListener('click', function(e) {
                console.log('4. ✅ CLICK EVENT FIRED!');
                console.log('   Event:', e);
                console.log('   Current target:', e.currentTarget);
                console.log('   Default prevented:', e.defaultPrevented);
                
                // Try to manually trigger modal
                if (profileModal && typeof bootstrap !== 'undefined') {
                    console.log('   Attempting manual modal open...');
                    try {
                        const modal = new bootstrap.Modal(profileModal);
                        modal.show();
                        console.log('   ✅ Manual modal opened successfully!');
                    } catch (error) {
                        console.error('   ❌ Error opening modal:', error);
                    }
                }
            }, true); // Use capture phase
            
            console.log('   Click listener added');
        } else {
            console.error('   ❌ Edit button NOT found!');
        }
        
        // Test manual modal trigger
        setTimeout(() => {
            console.log('5. Testing manual modal trigger...');
            if (profileModal && typeof bootstrap !== 'undefined') {
                try {
                    const testModal = new bootstrap.Modal(profileModal);
                    console.log('   ✅ Modal object created:', testModal);
                    console.log('   Try clicking "Edit Profile" now or run: testModal.show()');
                    window.testModal = testModal; // Make it available globally
                } catch (error) {
                    console.error('   ❌ Error creating modal:', error);
                }
            } else {
                console.error('   ❌ Cannot test - modal or Bootstrap missing');
            }
        }, 2000);
        
        console.log('=========================');
    });
    
    // Manual trigger function you can call from console
    window.openProfileModal = function() {
        const modal = document.getElementById('profileModal');
        if (modal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            console.log('✅ Modal opened manually');
        } else {
            console.error('❌ Cannot open modal - missing elements');
        }
    };
    </script>

    <?php 
    // Close connection after all includes that need it
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ?>

    <!-- ✅ CORRECT SCRIPT ORDER -->
    <script src="../AdminAccount/js/local-bootstrap.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/sidebar-notifications.js"></script>

    <!-- ✅ SET GLOBAL VARIABLES BEFORE LOADING BACKUP.JS -->
    <script>
        window.IS_STAFF_VIEW = true;
        window.BACKUP_API_PATH = "backup_api.php"; // ✅ UPDATED TO CORRECT NAME
        window.BASE_PATH = "../AdminAccount/";
        
        console.log('✅ Staff environment variables set');
        console.log('API Path:', window.BACKUP_API_PATH);
        console.log('Current URL:', window.location.href);
    </script>

    <!-- ✅ LOAD BACKUP.JS - It handles Google Drive via PHP OAuth -->
    <script src="../AdminAccount/js/backup.js"></script>

    <!-- ✅ STAFF-SPECIFIC CONFIGURATION -->
    <script>
        // Override fetch to route everything through backup_api.php
        (function() {
            const originalFetch = window.fetch;
            
            window.fetch = function(url, options) {
                if (typeof url !== 'string') {
                    return originalFetch(url, options);
                }
                
                console.log('📡 [STAFF FETCH OVERRIDE] Original URL:', url);
                
                let modifiedUrl = url;
                
                // ✅ FIX: Route ALL backup.php calls to backup_api.php (same directory)
                if (url.includes('backup.php') || url.startsWith('backup.php')) {
                    // Extract query string
                    const queryString = url.includes('?') ? url.substring(url.indexOf('?')) : '';
                    modifiedUrl = 'backup_api.php' + queryString;
                    console.log('🔄 [STAFF] Routing backup.php → backup_api.php');
                }
                
                // Route school_year_api.php calls
                if (url.includes('school_year_api.php')) {
                    const queryString = url.includes('?') ? url.substring(url.indexOf('?')) : '';
                    modifiedUrl = 'backup_api.php' + queryString;
                    console.log('🔄 [STAFF] Routing school_year_api.php → backup_api.php');
                }
                
                // Block archival_api.php
                if (url.includes('archival_api.php')) {
                    console.warn('⚠️ Archival API blocked for staff');
                    return Promise.resolve(new Response(JSON.stringify({
                        status: 'error',
                        message: 'Access denied'
                    }), {
                        status: 403,
                        headers: { 'Content-Type': 'application/json' }
                    }));
                }
                
                console.log('✅ [STAFF FETCH] Final URL:', modifiedUrl);
                
                return originalFetch(modifiedUrl, options)
                    .then(response => {
                        console.log('✅ Response status:', response.status, 'for:', modifiedUrl);
                        return response;
                    })
                    .catch(error => {
                        console.error('❌ Fetch error:', error, 'for:', modifiedUrl);
                        throw error;
                    });
            };
        })();

        // ✅ Google Drive authentication is handled by backup.js using PHP OAuth flow
        // backup.js provides: handleGoogleAuth(), disconnectGoogle(), checkGoogleDriveConnection()
        // No custom overrides needed - the functions work the same for both admin and staff
        
        console.log('✅ [Staff] Google Drive functions inherited from backup.js');

        // Block admin-only functions
        if (window.IS_STAFF_VIEW) {
            window.addSchoolYear = window.updateSchoolYear = window.deleteSchoolYear = 
            window.saveArchivalSettings = window.runAutoArchival = window.changeBackupDirectory = function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Access Denied',
                    text: 'This feature is only available to administrators.',
                    confirmButtonColor: '#a00000'
                });
                return false;
            };
        }
        
        // ✅ INITIALIZE WHEN READY
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 Initializing Staff Backup Management System...');
            
            let attempts = 0;
            const maxAttempts = 50;
            
            const checkBackupJsLoaded = setInterval(() => {
                attempts++;
                
                const functionsLoaded = 
                    typeof loadBackupStatistics === 'function' &&
                    typeof loadPendingBackupCount === 'function' &&
                    typeof loadRecentBackups === 'function' &&
                    typeof checkGoogleDriveConnection === 'function';
                
                if (functionsLoaded) {
                    clearInterval(checkBackupJsLoaded);
                    console.log('✅ backup.js loaded successfully (attempt ' + attempts + ')');
                    
                    setTimeout(() => {
                        console.log('🎯 Calling loadBackupStatistics()...');
                        try {
                            loadBackupStatistics();
                        } catch (e) {
                            console.error('❌ Error in loadBackupStatistics:', e);
                        }
                        
                        console.log('🎯 Calling loadPendingBackupCount()...');
                        try {
                            loadPendingBackupCount();
                        } catch (e) {
                            console.error('❌ Error in loadPendingBackupCount:', e);
                        }
                        
                        console.log('🎯 Calling loadActiveSchoolYearForStaff()...');
                        try {
                            loadActiveSchoolYearForStaff();
                        } catch (e) {
                            console.error('❌ Error in loadActiveSchoolYearForStaff:', e);
                        }
                        
                        console.log('🎯 Calling loadRecentBackups(1)...');
                        try {
                            loadRecentBackups(1);
                        } catch (e) {
                            console.error('❌ Error in loadRecentBackups:', e);
                        }
                        
                        // ✅ CHECK GOOGLE DRIVE CONNECTION (this also loads sync status)
                        console.log('🎯 Calling checkGoogleDriveConnection()...');
                        try {
                            checkGoogleDriveConnection();
                        } catch (e) {
                            console.error('❌ Error in checkGoogleDriveConnection:', e);
                        }
                        
                        console.log('✅ All initialization functions called');
                    }, 1000);
                }
                
                if (attempts >= maxAttempts) {
                    clearInterval(checkBackupJsLoaded);
                    console.error('❌ Failed to load backup.js functions after ' + maxAttempts + ' attempts');
                    console.log('Available functions:', {
                        loadBackupStatistics: typeof loadBackupStatistics,
                        loadPendingBackupCount: typeof loadPendingBackupCount,
                        loadRecentBackups: typeof loadRecentBackups,
                        checkGoogleDriveConnection: typeof checkGoogleDriveConnection
                    });
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Loading Error',
                        text: 'Backup system failed to load. Please refresh the page.',
                        confirmButtonColor: '#a00000'
                    });
                }
            }, 100);
            
            setInterval(() => {
                console.log('🔄 Auto-refreshing data...');
                try {
                    if (typeof loadBackupStatistics === 'function') loadBackupStatistics();
                    if (typeof loadPendingBackupCount === 'function') loadPendingBackupCount();
                    if (typeof loadRecentBackups === 'function') loadRecentBackups(getCurrentPage());
                } catch (e) {
                    console.error('❌ Auto-refresh error:', e);
                }
            }, 120000);
        });
        
        function getCurrentPage() {
            const activePage = document.querySelector('.pagination .page-item.active .page-link');
            return activePage ? parseInt(activePage.textContent) : 1;
        }
        
        window.refreshBackupData = function() {
            console.log('🔄 Manual refresh triggered');
            try {
                loadBackupStatistics();
                loadPendingBackupCount();
                loadRecentBackups(getCurrentPage());
                checkGoogleDriveConnection();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Refreshed',
                    text: 'Backup data refreshed successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            } catch (e) {
                console.error('❌ Manual refresh error:', e);
            }
        };
        
        window.debugStaffBackup = function() {
            console.log('=== STAFF BACKUP DEBUG ===');
            console.log('API Path:', window.BACKUP_API_PATH);
            console.log('Is Staff View:', window.IS_STAFF_VIEW);
            console.log('Functions available:', {
                loadBackupStatistics: typeof loadBackupStatistics,
                loadPendingBackupCount: typeof loadPendingBackupCount,
                loadActiveSchoolYearForStaff: typeof loadActiveSchoolYearForStaff,
                loadRecentBackups: typeof loadRecentBackups,
                checkGoogleDriveConnection: typeof checkGoogleDriveConnection
            });
            
            fetch('backup_api.php?action=get_school_years')
                .then(r => r.json())
                .then(data => console.log('✅ Test school years fetch:', data))
                .catch(e => console.error('❌ Test fetch error:', e));
            
            console.log('========================');
        };

        // ========================================
        // ✅ NOTIFICATION SYSTEM - FIXED VERSION
        // ========================================
        
        console.log('🔔 [Staff] Initializing notification system...');
        
        // Check if the notification function exists
        if (typeof updateSidebarNotification === 'function') {
            console.log('✅ updateSidebarNotification function found');
        } else {
            console.error('❌ updateSidebarNotification function NOT found!');
            console.log('Available functions:', Object.keys(window).filter(k => k.includes('Sidebar') || k.includes('notification')));
        }
        
        // Override loadPendingBackupCount to include notification update
        const originalLoadPendingBackupCount = window.loadPendingBackupCount;
        
        window.loadPendingBackupCount = function() {
            console.log('🔄 [Staff] Loading pending backup count...');
            
            fetch('backup_api.php?action=get_pending_count')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const pendingCount = data.data.pending_count || 0;
                        console.log('📊 [Staff] Pending backup count:', pendingCount);
                        
                        // Update the UI elements
                        updatePendingCountDisplay(pendingCount);
                        
                        // ✅ FORCE UPDATE SIDEBAR NOTIFICATION
                        console.log('🎯 [Staff] Calling updateSidebarNotification with count:', pendingCount);
                        
                        if (typeof updateSidebarNotification === 'function') {
                            updateSidebarNotification(pendingCount);
                            console.log('✅ [Staff] updateSidebarNotification called successfully');
                        } else {
                            console.error('❌ [Staff] updateSidebarNotification not available');
                            // Manual fallback
                            manualUpdateNotification(pendingCount);
                        }
                    } else {
                        console.error('❌ Failed to get pending count:', data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error loading pending count:', error);
                });
        };
        
        // ✅ MANUAL NOTIFICATION UPDATE (FALLBACK)
        function manualUpdateNotification(count) {
            console.log('🔧 [Staff] Manual notification update, count:', count);
            
            const backupNavItem = document.querySelector('a[href*="staffbackup.php"]');
            if (!backupNavItem) {
                console.error('❌ Backup nav item not found');
                return;
            }
            
            console.log('✓ Found backup nav item:', backupNavItem);
            
            // Remove existing notification
            let existingNotif = backupNavItem.querySelector('.sidebar-notification');
            if (existingNotif) {
                existingNotif.remove();
                console.log('✓ Removed existing notification');
            }
            
            // Add new notification if count > 0
            if (count > 0) {
                const notificationDot = document.createElement('span');
                notificationDot.className = 'sidebar-notification';
                notificationDot.style.cssText = `
                    position: absolute;
                    top: 50%;
                    right: 15px;
                    transform: translateY(-50%);
                    width: 8px;
                    height: 8px;
                    background: #28a745;
                    border-radius: 50%;
                    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
                    animation: pulse 2s infinite;
                `;
                
                backupNavItem.style.position = 'relative';
                backupNavItem.appendChild(notificationDot);
                
                console.log('✅ [Staff] Green dot added manually!');
            } else {
                console.log('ℹ️ [Staff] No pending files, notification hidden');
            }
        }
        
        // Override updatePendingCountDisplay
        window.updatePendingCountDisplay = function(count) {
            console.log('📝 [Staff] Updating pending count display:', count);
            
            // Update the "Pending Files" stat card
            const pendingElement = document.getElementById('pendingFilesCount');
            if (pendingElement) {
                pendingElement.textContent = count;
                console.log('✓ Updated #pendingFilesCount');
            }
            
            // Update data-stat attribute
            const pendingDataStat = document.querySelector('[data-stat="pending"]');
            if (pendingDataStat) {
                pendingDataStat.textContent = count;
                console.log('✓ Updated [data-stat="pending"]');
            }
            
            // Update sync panel pending files
            const pendingFilesElement = document.getElementById('pendingFiles');
            if (pendingFilesElement) {
                pendingFilesElement.textContent = count;
                console.log('✓ Updated #pendingFiles');
            }
            
            // ✅ UPDATE NOTIFICATION
            console.log('🎯 [Staff] Updating notification from updatePendingCountDisplay');
            if (typeof updateSidebarNotification === 'function') {
                updateSidebarNotification(count);
            } else {
                manualUpdateNotification(count);
            }
        };
        
        // ✅ TEST THE NOTIFICATION IMMEDIATELY
        setTimeout(() => {
            console.log('🧪 [Staff] Testing notification system after 3 seconds...');
            
            fetch('backup_api.php?action=get_pending_count')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const testCount = data.data.pending_count || 0;
                        console.log('🧪 Test count:', testCount);
                        
                        if (typeof updateSidebarNotification === 'function') {
                            updateSidebarNotification(testCount);
                            console.log('✅ Test: updateSidebarNotification called');
                        } else {
                            manualUpdateNotification(testCount);
                            console.log('✅ Test: Manual notification called');
                        }
                    }
                })
                .catch(error => {
                    console.error('❌ Test failed:', error);
                });
        }, 3000);
        
        console.log('✅ Staff notification system initialized with fallback');
    </script>

    <!-- ✅ NEW STAFF BACKUP UI FUNCTIONS -->
    <script>
        /**
         * Select storage option (visual toggle for radio buttons)
         */
        function selectStorage(type) {
            console.log('📦 Storage type selected:', type);
            
            // Update radio buttons
            if (type === 'local') {
                document.getElementById('localStorage').checked = true;
                document.getElementById('cloudStorage').checked = false;
            } else if (type === 'cloud') {
                document.getElementById('localStorage').checked = false;
                document.getElementById('cloudStorage').checked = true;
            }
        }

        /**
         * ✅ STAFF-SPECIFIC: Load school years into dropdown
         * This function populates the <select> dropdown with school years
         * and selects the active one automatically
         */
        window.loadActiveSchoolYearForStaff = async function() {
            console.log('📅 [STAFF] Loading school years for dropdown...');
            
            const schoolYearSelect = document.getElementById('schoolYear');
            if (!schoolYearSelect) {
                console.error('❌ School year dropdown not found');
                return;
            }
            
            try {
                // Show loading state
                schoolYearSelect.innerHTML = '<option value="">Loading school years...</option>';
                
                // Fetch school years from staff API
                const response = await fetch('backup_api.php?action=get_school_years');
                const result = await response.json();
                
                console.log('📊 [STAFF] School years response:', result);
                
                if (result.status === 'success') {
                    const schoolYears = result.data.school_years || [];
                    const activeYear = result.data.active_year;
                    
                    if (schoolYears.length === 0) {
                        schoolYearSelect.innerHTML = '<option value="">No school years available</option>';
                        console.warn('⚠️ No school years found in database');
                        return;
                    }
                    
                    // Clear and populate dropdown
                    schoolYearSelect.innerHTML = '<option value="">Select a school year</option>';
                    
                    schoolYears.forEach(year => {
                        const option = document.createElement('option');
                        option.value = year.school_year;
                        option.textContent = year.school_year;
                        
                        // Mark active year
                        if (year.is_active == 1 || year.school_year === activeYear) {
                            option.textContent += ' (Active)';
                            option.selected = true;
                        }
                        
                        schoolYearSelect.appendChild(option);
                    });
                    
                    console.log('✅ [STAFF] Populated dropdown with', schoolYears.length, 'school years');
                    console.log('✅ [STAFF] Active year selected:', activeYear);
                    
                } else {
                    throw new Error(result.message || 'Failed to load school years');
                }
                
            } catch (error) {
                console.error('❌ [STAFF] Error loading school years:', error);
                schoolYearSelect.innerHTML = '<option value="">Error loading school years</option>';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Load School Years',
                    text: error.message || 'Could not retrieve school years from database',
                    confirmButtonColor: '#a00000'
                });
            }
        };

        /**
         * ✅ Auto-Sync functions are inherited from backup.js
         * backup.js provides: toggleAutoSync(), updateSyncStatus(), checkGoogleDriveConnection()
         * These functions work the same for both admin and staff
         */
        
        console.log('✅ [Staff] Auto-Sync functions inherited from backup.js');

        // ✅ Load backup directory on page load (read-only display for staff)
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                if (typeof loadBackupDirectory === 'function') {
                    console.log('📁 Loading backup directory from backup.js...');
                    loadBackupDirectory();
                }
            }, 1500);
        });

        console.log('✅ Staff backup UI functions loaded');
        console.log('   - selectStorage() for visual radio button toggle');
        console.log('   - createBackup() from backup.js (routed through backup_api.php)');
        console.log('   - loadBackupDirectory() from backup.js (for read-only display)');
        console.log('   - loadActiveSchoolYearForStaff() for dropdown population');
        console.log('   - handleGoogleAuth() from backup.js (PHP OAuth flow)');
        console.log('   - disconnectGoogle() from backup.js');
        console.log('   - toggleAutoSync() from backup.js');
        console.log('   - checkGoogleDriveConnection() from backup.js');
        
        // ✅ VERIFY FUNCTIONS ARE ACCESSIBLE
        console.log('🔍 Function accessibility check:');
        console.log('   - toggleAutoSync:', typeof toggleAutoSync);
        console.log('   - handleGoogleAuth:', typeof handleGoogleAuth);
        console.log('   - disconnectGoogle:', typeof disconnectGoogle);
        console.log('   - checkGoogleDriveConnection:', typeof checkGoogleDriveConnection);
        console.log('   - updateConnectionStatus:', typeof updateConnectionStatus);
        console.log('   - updateSyncStatus:', typeof updateSyncStatus);
    </script>
</body>
</html>