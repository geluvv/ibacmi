<?php
// This file should be included AFTER defining $isStaffView
if (!isset($isStaffView)) {
    $isStaffView = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBACMI - Backup & Sync Management</title>
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $isStaffView ? '../AdminAccount/' : ''; ?>css/sidebar.css" rel="stylesheet">
    
    <!-- Add SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
    /* Include all your existing CSS from backup.html */
    <?php include($isStaffView ? '../AdminAccount/css/backup.css' : 'css/backup.css'); ?>
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
        // Render appropriate sidebar based on view type
        if ($isStaffView) {
            include '../StaffAccount/staffsidebar.php';
            renderSidebar('backup', 'staff', $staffInfo);
        } else {
            // Admin sidebar rendering
            ?>
            <nav class="sidebar">
                <!-- Your existing admin sidebar HTML -->
            </nav>
            <?php
        }
        ?>
        
        <div class="main-content">
            <!-- Alert container -->
            <div id="alertContainer"></div>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Backup & Sync Management</h1>
                <p class="page-subtitle">Manage backups, synchronization<?php echo !$isStaffView ? ', and school year data' : ''; ?></p>
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
                    <button class="btn btn-sm btn-outline-primary me-2" id="authenticateBtn" onclick="handleGoogleAuth()" disabled>
                        <i class="fas fa-link"></i> Connect to Google Drive
                    </button>
                    <button class="btn btn-sm btn-outline-danger" id="disconnectBtn" onclick="disconnectGoogle()" style="display: none;">
                        <i class="fas fa-unlink"></i> Disconnect
                    </button>
                </div>

                <div class="sync-stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Last Sync</span>
                        <span class="stat-number" id="lastSync">-</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pending Files</span>
                        <span class="stat-number" id="pendingFiles">0</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Status</span>
                        <div class="sync-status-badge sync-status-enabled" id="syncStatusBadge2">
                            <i class="fas fa-check-circle"></i>
                            Enabled
                        </div>
                    </div>
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
            <div class="stats-row mt-4">
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

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Manual Backup Section - VISIBLE TO BOTH -->
                <div class="backup-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div>
                            <h3 class="section-title">Manual Backup</h3>
                            <p class="section-subtitle">Create a backup of student documents and data</p>
                        </div>
                    </div>

                    <?php if (!$isStaffView): ?>
                    <!-- Backup directory setting - ADMIN ONLY -->
                    <div class="mb-4 p-3 border rounded" style="background: #f8f9fa;">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-folder-open text-primary me-2"></i>Local Backup Directory
                        </label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   id="backupDirectory" 
                                   readonly 
                                   placeholder="Default: AdminAccount/backups" 
                                   style="background: white; cursor: not-allowed;"
                                   value="AdminAccount/backups">
                            <button class="btn btn-outline-primary" 
                                    type="button"
                                    onclick="changeBackupDirectory()">
                                <i class="fas fa-edit me-1"></i> Change Location
                            </button>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Choose where local backups will be saved on your computer
                        </small>
                    </div>
                    <?php endif; ?>

                    <form class="backup-form" id="backupForm">
                        <input type="hidden" name="action" value="create_backup">
                        <div class="mb-3">
                            <label for="backupName" class="form-label">Backup Name</label>
                            <input type="text" class="form-control" id="backupName" name="backupName"
                                   placeholder="Enter backup name (optional)" value="IBACMI_Backup">
                        </div>
                        <div class="mb-3">
                            <label for="schoolYear" class="form-label">School Year</label>
                            <select class="form-select" id="schoolYear" name="schoolYear">
                                <option value="">All school years</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Destination</label>
                            <div class="backup-actions">
                                <button type="button" class="btn-google-drive" onclick="submitBackup('cloud')">
                                    <i class="fab fa-google-drive me-2"></i>Google Drive
                                </button>
                                <button type="button" class="btn-local-storage" onclick="submitBackup('local')">
                                    <i class="fas fa-hdd me-2"></i>Local Storage
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (!$isStaffView): ?>
                <!-- School Year Management - ADMIN ONLY -->
                <div class="school-year-section">
                    <!-- Your existing school year management HTML -->
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$isStaffView): ?>
            <!-- Archival System - ADMIN ONLY -->
            <div class="content-grid mt-4">
                <!-- Your existing archival system HTML -->
            </div>
            <?php endif; ?>

            <!-- Recent Backups - VISIBLE TO BOTH -->
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
            </div>
        </div>
    </div>

    <!-- Staff Profile Modal (only for staff view) -->
    <?php if ($isStaffView): ?>
        <?php include '../StaffAccount/staffprofile.php'; ?>
    <?php endif; ?>

    <!-- JavaScript -->
    <script src="<?php echo $isStaffView ? '../AdminAccount/' : ''; ?>js/local-bootstrap.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo $isStaffView ? '../StaffAccount/' : ''; ?>js/sidebar-notifications.js"></script>
    
    <script>
        // Pass context to JavaScript
        window.IS_STAFF_VIEW = <?php echo $isStaffView ? 'true' : 'false'; ?>;
        window.BASE_PATH = '<?php echo $isStaffView ? "../AdminAccount/" : ""; ?>';
    </script>
    
    <!-- Load Google APIs -->
    <script async defer src="https://apis.google.com/js/api.js" onload="loadGoogleAPIs()"></script>
    <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>
    
    <!-- Main backup script -->
    <script src="<?php echo $isStaffView ? '../AdminAccount/' : ''; ?>js/backup.js"></script>
    
    <script>
        // Hide admin-only functions for staff
        if (window.IS_STAFF_VIEW) {
            // Hide these functions by overriding them
            window.addSchoolYear = function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Access Denied',
                    text: 'This feature is only available to administrators.',
                    confirmButtonColor: '#a00000'
                });
            };
            
            window.loadArchivalSettings = function() { /* No-op for staff */ };
            window.loadArchivalStatistics = function() { /* No-op for staff */ };
            window.saveArchivalSettings = function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Access Denied',
                    text: 'This feature is only available to administrators.',
                    confirmButtonColor: '#a00000'
                });
            };
            window.runAutoArchival = function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Access Denied',
                    text: 'This feature is only available to administrators.',
                    confirmButtonColor: '#a00000'
                });
            };
        }
    </script>
</body>
</html>