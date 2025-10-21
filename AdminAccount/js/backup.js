// ❌ DEPRECATED: Google JavaScript client library initialization
// Now using PHP OAuth flow instead
console.log('🚀 Backup.js loaded at:', new Date().toISOString());
console.log('Using PHP OAuth flow for Google Drive integration');

// Handle Google Auth - FIXED TO USE PHP OAUTH FLOW
function handleGoogleAuth() {
    console.log('=== Starting Google OAuth Flow ===');
    
    Swal.fire({
        title: 'Connecting to Google Drive',
        html: 'Opening authorization window...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch auth URL from PHP backend
    fetch('backup.php?action=get_auth_url')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data.auth_url) {
                Swal.close();
                
                console.log('Opening OAuth popup...');
                
                // Open OAuth popup
                const width = 600;
                const height = 700;
                const left = (screen.width / 2) - (width / 2);
                const top = (screen.height / 2) - (height / 2);
                
                const popup = window.open(
                    data.data.auth_url,
                    'Google OAuth',
                    `width=${width},height=${height},top=${top},left=${left}`
                );
                
                if (!popup) {
                    throw new Error('Popup blocked. Please allow popups for this site.');
                }
                
                // Listen for OAuth completion
                window.addEventListener('message', function handleOAuthMessage(event) {
                    console.log('📨 Received message:', event.data);
                    
                    if (event.data.type === 'oauth_success') {
                        window.removeEventListener('message', handleOAuthMessage);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Connected!',
                            text: 'Successfully connected to Google Drive',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Update UI
                        updateConnectionStatus(true);
                        updateSyncStatus('disabled');
                        
                        console.log('✅ Google Drive connected successfully');
                    } else if (event.data.type === 'oauth_error') {
                        window.removeEventListener('message', handleOAuthMessage);
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Failed',
                            text: event.data.message || 'Failed to connect to Google Drive',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
                
            } else {
                throw new Error(data.message || 'Failed to get authorization URL');
            }
        })
        .catch(error => {
            console.error('Error starting OAuth:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Failed',
                text: error.message || 'Failed to start authorization',
                confirmButtonColor: '#dc3545'
            });
        });
}

// ❌ DEPRECATED: This was for Google JavaScript client library
// Now using PHP OAuth flow instead
// Handle auth response - IMPROVED VERSION
/*
function handleAuthResponse(resp) {
    console.log('📥 Auth response received:', resp);
    
    if (resp.error) {
        console.error('Auth error:', resp.error);
        Swal.fire({
            icon: 'error',
            title: 'Authentication Failed',
            text: resp.error,
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    console.log('✅ Google auth successful, access token received');
    console.log('Token expires in:', resp.expires_in, 'seconds');
    
    Swal.fire({
        title: 'Connecting to Google Drive',
        html: 'Saving your credentials...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'store_token');
    formData.append('access_token', resp.access_token);
    formData.append('token_type', resp.token_type || 'Bearer');
    if (resp.refresh_token) {
        formData.append('refresh_token', resp.refresh_token);
        console.log('Refresh token included');
    }
    
    fetch('backup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            throw new Error('Invalid server response');
        }
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Connected!',
                text: 'Successfully connected to Google Drive',
                timer: 2000,
                showConfirmButton: false
            });
            
            // ✅ FIXED: Only update connection status, DON'T auto-enable sync
            updateConnectionStatus(true);
            // Keep sync disabled - user must enable manually
            updateSyncStatus('disabled');
            
            console.log('✅ Google Drive connected successfully');
        } else {
            throw new Error(data.message || 'Failed to store token');
        }
    })
    .catch(error => {
        console.error('Error storing token:', error);
        Swal.fire({
            icon: 'error',
            title: 'Connection Failed',
            text: error.message || 'Failed to connect to Google Drive',
            confirmButtonColor: '#dc3545'
        });
    });
}
*/

/**
 * Update connection status UI - FIXED WITH BUTTON STATE
 */
function updateConnectionStatus(isConnected, userEmail = null) {
    console.log('Updating connection status to:', isConnected, 'User:', userEmail);
    
    const statusIndicator = document.getElementById('statusIndicator');
    const connectionStatus = document.getElementById('connectionStatus');
    const authBtn = document.getElementById('authenticateBtn');
    const disconnectBtn = document.getElementById('disconnectBtn');
    const enableSyncBtn = document.getElementById('enableSyncBtn');
    
    if (!statusIndicator || !connectionStatus) {
        console.error('Status elements not found!');
        return;
    }
    
    if (isConnected) {
        statusIndicator.style.background = '#28a745';
        
        // Show user email if available
        if (userEmail) {
            connectionStatus.textContent = `Connected (${userEmail})`;
        } else {
            connectionStatus.textContent = 'Connected';
        }
        
        if (authBtn) authBtn.style.display = 'none';
        if (disconnectBtn) disconnectBtn.style.display = 'inline-block';
        
        // ✅ ENABLE the sync button when connected
        if (enableSyncBtn) {
            enableSyncBtn.disabled = false;
            enableSyncBtn.style.opacity = '1';
            enableSyncBtn.style.cursor = 'pointer';
            enableSyncBtn.title = '';
        }
        
        console.log('✅ UI updated to: Connected' + (userEmail ? ` (${userEmail})` : ''));
    } else {
        statusIndicator.style.background = '#dc3545';
        connectionStatus.textContent = 'Not Connected';
        
        if (authBtn) authBtn.style.display = 'inline-block';
        if (disconnectBtn) disconnectBtn.style.display = 'none';
        
        // ✅ DISABLE the sync button when not connected
        if (enableSyncBtn) {
            enableSyncBtn.disabled = true;
            enableSyncBtn.style.opacity = '0.5';
            enableSyncBtn.style.cursor = 'not-allowed';
            enableSyncBtn.title = 'Connect to Google Drive first';
        }
        
        console.log('❌ UI updated to: Not Connected');
    }
}

// Check Google Drive connection
function checkGoogleDriveConnection() {
    console.log('🔍 Checking Google Drive connection...');
    
    fetch('backup.php?action=check_connection')
        .then(response => {
            console.log('📡 Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📊 Connection check response:', JSON.stringify(data, null, 2));
            
            if (data.status === 'success') {
                const isConnected = data.data.connected;
                const syncStatus = data.data.sync_status || 'disabled';
                const userEmail = data.data.user_email;
                const debug = data.data.debug;
                
                console.log('✅ Connection status:', isConnected);
                console.log('� User email:', userEmail);
                console.log('�📊 Sync status:', syncStatus);
                
                if (debug) {
                    console.warn('⚠️ Debug info:', debug);
                }
                
                // ✅ Pass user email to updateConnectionStatus
                updateConnectionStatus(isConnected, userEmail);
                updateSyncStatus(syncStatus);
                
                // ✅ FIXED: Only start monitoring if sync is ALREADY enabled
                // Don't auto-enable when checking connection
                if (isConnected && syncStatus === 'enabled') {
                    console.log('🔄 Starting auto-sync monitoring...');
                    startAutoSyncMonitoring();
                } else {
                    console.log('⏹️ Stopping auto-sync monitoring...');
                    stopAutoSyncMonitoring();
                }
            } else {
                console.warn('⚠️ Check connection failed:', data.message);
                updateConnectionStatus(false);
                updateSyncStatus('disabled');
                stopAutoSyncMonitoring();
            }
        })
        .catch(error => {
            console.error('❌ Connection check failed:', error);
            console.error('Error details:', error.message, error.stack);
            // Don't update UI on error - this prevents showing as disconnected when there's a network issue
            console.warn('⚠️ Connection check error - UI state unchanged to prevent false disconnection');
        });
}

/**
 * Toggle auto-sync - WITH CONNECTION CHECK
 */
async function toggleAutoSync(status) {
    console.log('🔄 Toggle auto-sync:', status);
    
    // ✅ ALWAYS check connection when enabling
    if (status === 'enabled') {
        // First show loading
        Swal.fire({
            title: 'Checking Connection...',
            text: 'Please wait',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('backup.php?action=check_connection');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            Swal.close(); // Close loading
            
            if (!data.data || !data.data.connected) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Not Connected',
                    html: '<p>Please connect to Google Drive first before enabling auto-sync.</p>' +
                          '<p class="text-muted small mt-2">Click the "Connect to Google Drive" button above.</p>',
                    confirmButtonColor: '#3b82f6',
                    confirmButtonText: 'OK'
                });
                return; // Stop here
            }
        } catch (error) {
            Swal.close();
            console.error('Connection check failed:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Check Failed',
                text: 'Could not verify Google Drive connection: ' + error.message,
                confirmButtonColor: '#dc3545'
            });
            return;
        }
    }
    
    // ✅ Show loading while toggling
    Swal.fire({
        title: 'Updating...',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Proceed with toggle
    const formData = new FormData();
    formData.append('action', 'toggle_sync');
    formData.append('status', status);
    
    fetch('backup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    })
    .then(data => {  // ✅ FIXED: Added opening parenthesis
        Swal.close(); // Close loading
        
        console.log('📊 Toggle response:', data);
        
        if (data.status === 'success') {
            updateSyncStatus(status);
            
            if (status === 'enabled') {
                startAutoSyncMonitoring();
                Swal.fire({
                    icon: 'success',
                    title: 'Auto-Sync Enabled!',
                    text: 'Documents will now sync automatically to Google Drive.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                stopAutoSyncMonitoring();
                Swal.fire({
                    icon: 'info',
                    title: 'Auto-Sync Disabled',
                    text: 'Automatic syncing has been stopped.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('❌ Error toggling sync:', error);
        Swal.fire({
            icon: 'error',
            title: 'Failed to Toggle Sync',
            text: error.message || 'An error occurred. Please try again.',
            confirmButtonColor: '#dc3545'
        });
    });
}

/**
 * Actually perform the sync toggle (after validation)
 */
function proceedWithToggleSync(status) {
    console.log('Proceeding with toggle sync:', status);
    
    const formData = new FormData();
    formData.append('action', 'toggle_sync');
    formData.append('status', status);
    
    fetch('backup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('✅ Auto-sync status updated:', status);
            updateSyncStatus(status);
            
            // Show success message
            let message = '';
            if (status === 'enabled') {
                message = 'Auto-sync enabled! Documents will sync automatically.';
                startAutoSyncMonitoring();
            } else if (status === 'paused') {
                message = 'Auto-sync paused. You can resume anytime.';
                stopAutoSyncMonitoring();
            } else {
                message = 'Auto-sync disabled.';
                stopAutoSyncMonitoring();
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                confirmButtonColor: '#10b981',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            console.error('❌ Failed to update sync status');
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: 'Could not update auto-sync status. Please try again.',
                confirmButtonColor: '#ef4444'
            });
        }
    })
    .catch(error => {
        console.error('Error toggling sync:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred. Please try again.',
            confirmButtonColor: '#ef4444'
        });
    });
}

/**
 * Update sync status UI - FIXED BUTTON VISIBILITY
 */
function updateSyncStatus(status) {
    console.log('📊 Updating sync status UI:', status);
    
    const syncStatusBadge = document.getElementById('syncStatusBadge');
    const enableBtn = document.getElementById('enableSyncBtn');
    const pauseBtn = document.getElementById('pauseSyncBtn');
    const disableBtn = document.getElementById('disableSyncBtn');
    
    if (!syncStatusBadge) {
        console.warn('Sync status badge not found');
        return;
    }
    
    // Update badge
    syncStatusBadge.className = 'sync-status-badge';
    
    if (status === 'enabled') {
        syncStatusBadge.classList.add('sync-status-enabled');
        syncStatusBadge.innerHTML = '<i class="fas fa-check-circle"></i> Enabled';
        
        // Show pause and disable buttons, hide enable
        if (enableBtn) enableBtn.style.display = 'none';
        if (pauseBtn) pauseBtn.style.display = 'inline-block';
        if (disableBtn) disableBtn.style.display = 'inline-block';
        
        // ✅ NEW: Disable manual backup buttons when auto-sync is enabled
        disableManualBackupButtons(true);
        
    } else if (status === 'paused') {
        syncStatusBadge.innerHTML = '<i class="fas fa-pause-circle"></i> Paused';
        
        // Show enable and disable buttons, hide pause
        if (enableBtn) enableBtn.style.display = 'inline-block';
        if (pauseBtn) pauseBtn.style.display = 'none';
        if (disableBtn) disableBtn.style.display = 'inline-block';
        
        // ✅ NEW: Enable manual backup buttons when paused
        disableManualBackupButtons(false);
        
    } else {
        // disabled
        syncStatusBadge.innerHTML = '<i class="fas fa-times-circle"></i> Disabled';
        
        // Show ONLY enable button
        if (enableBtn) enableBtn.style.display = 'inline-block';
        if (pauseBtn) pauseBtn.style.display = 'none';
        if (disableBtn) disableBtn.style.display = 'none';
        
        // ✅ NEW: Enable manual backup buttons when disabled
        disableManualBackupButtons(false);
    }
}

/**
 * ✅ NEW FUNCTION: Disable/enable manual backup buttons based on auto-sync status
 * Prevents conflicts between auto-sync and manual backups
 */
function disableManualBackupButtons(disable) {
    console.log(`${disable ? '🔒' : '🔓'} ${disable ? 'Disabling' : 'Enabling'} manual backup buttons`);
    
    // Find all manual backup related buttons
    const createBackupBtn = document.getElementById('createBackupBtn');
    const localStorageRadio = document.getElementById('localStorage');
    const cloudStorageRadio = document.getElementById('cloudStorage');
    const backupNameInput = document.getElementById('backupName');
    
    // Apply disabled state to all elements
    const elementsToDisable = [
        createBackupBtn,
        localStorageRadio,
        cloudStorageRadio,
        backupNameInput
    ];
    
    elementsToDisable.forEach(element => {
        if (element) {
            element.disabled = disable;
            
            // Apply visual styling
            if (disable) {
                element.style.opacity = '0.5';
                element.style.cursor = 'not-allowed';
                
                // Add tooltip explaining why it's disabled
                if (element === createBackupBtn) {
                    element.title = 'Manual backup is disabled while auto-sync is enabled. Disable auto-sync first.';
                }
            } else {
                element.style.opacity = '1';
                element.style.cursor = 'pointer';
                
                if (element === createBackupBtn) {
                    element.title = 'Create a manual backup';
                }
            }
        }
    });
    
    // Also disable the storage option labels for better UX
    const storageOptions = document.querySelectorAll('.storage-option');
    storageOptions.forEach(option => {
        if (disable) {
            option.style.opacity = '0.5';
            option.style.cursor = 'not-allowed';
            option.style.pointerEvents = 'none';
        } else {
            option.style.opacity = '1';
            option.style.cursor = 'pointer';
            option.style.pointerEvents = 'auto';
        }
    });
    
    // Show/hide warning message
    let warningMsg = document.getElementById('autoSyncWarning');
    if (disable) {
        if (!warningMsg) {
            warningMsg = document.createElement('div');
            warningMsg.id = 'autoSyncWarning';
            warningMsg.className = 'alert alert-warning d-flex align-items-center mt-3';
            warningMsg.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div>
                    <strong>Manual Backup Disabled</strong><br>
                    <small>Auto-sync is currently enabled. To prevent conflicts, manual backup is disabled. 
                    Please disable auto-sync first if you want to create a manual backup.</small>
                </div>
            `;
            
            // Insert warning below the backup form
            const backupForm = document.getElementById('backupForm');
            if (backupForm) {
                backupForm.parentNode.insertBefore(warningMsg, backupForm.nextSibling);
            }
        }
    } else {
        // Remove warning message if it exists
        if (warningMsg) {
            warningMsg.remove();
        }
    }
}

// ========================================
// AUTO-SYNC MONITORING SYSTEM
// ========================================

let autoSyncInterval = null;
let autoSyncEnabled = false;

console.log('🔄 Auto-sync monitoring system loaded');

/**
 * Start auto-sync monitoring
 */
function startAutoSyncMonitoring() {
    if (autoSyncInterval) {
        clearInterval(autoSyncInterval);
    }
    
    autoSyncEnabled = true;
    console.log('🟢 Auto-sync monitoring STARTED');
    
    // ✅ Start activity indicator monitoring
    if (typeof startAutoSyncActivityMonitoring === 'function') {
        startAutoSyncActivityMonitoring();
    }
    
    // Run immediately
    runAutoSync();
    
    // Then run every 2 minutes
    autoSyncInterval = setInterval(function() {
        if (autoSyncEnabled) {
            runAutoSync();
        }
    }, 120000); // 120000ms = 2 minutes
}

/**
 * Stop auto-sync monitoring
 */
function stopAutoSyncMonitoring() {
    autoSyncEnabled = false;
    if (autoSyncInterval) {
        clearInterval(autoSyncInterval);
        autoSyncInterval = null;
        console.log('🔴 Auto-sync monitoring STOPPED');
    }
    
    // ✅ Stop activity indicator monitoring
    if (typeof stopAutoSyncActivityMonitoring === 'function') {
        stopAutoSyncActivityMonitoring();
    }
}

/**
 * Run auto-sync check
 */
function runAutoSync() {
    const now = new Date();
    console.log('🔄 Auto-sync check at:', now.toLocaleTimeString());
    console.log('Auto-sync enabled:', autoSyncEnabled);
    
    if (!autoSyncEnabled) {
        console.log('⏸️ Auto-sync is disabled, skipping');
        return;
    }
    
    // Check if connected
    fetch('backup.php?action=check_connection')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data.connected) {
                console.log('✅ Connected, running auto-sync...');
                // Trigger automatic backup here
                // You can call your backup function here
            } else {
                console.warn('⚠️ Not connected, skipping auto-sync');
                stopAutoSyncMonitoring();
            }
        })
        .catch(error => {
            console.error('❌ Auto-sync check error:', error);
        });
}

// Override toggleAutoSync to trigger immediate sync when enabled
if (typeof toggleAutoSync !== 'undefined') {
    const originalToggleAutoSync = toggleAutoSync;
    
    window.toggleAutoSync = function(status) {
        console.log('Toggle auto-sync called with status:', status);
        
        // Call original function (this saves status to database)
        originalToggleAutoSync(status);
        
        // If enabling, trigger immediate sync for existing unsynced documents
        if (status === 'enabled') {
            console.log('🚀 Auto-sync enabled - triggering initial sync for unsynced documents...');
            
            // Show loading notification
            Swal.fire({
                title: 'Syncing Documents...',
                html: 'Checking for documents to sync to Google Drive...<br><small>This may take a moment</small>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ✅ Wait for database to save 'enabled' status, then trigger sync
            setTimeout(() => {
                console.log('📤 Calling auto_sync_processor.php...');
                
                fetch('auto_sync_processor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'trigger=enable_sync'
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('✅ Sync response:', data);
                    
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Auto-Sync Enabled!',
                            html: `
                                <div class="text-start">
                                    <p><strong>Sync Complete:</strong></p>
                                    <ul>
                                        <li>📁 Students: ${data.student_count || 0}</li>
                                        <li>📄 Total Files: ${data.synced || 0}</li>
                                        <li>⬆️ Uploaded: ${data.uploaded || 0}</li>
                                        <li>🔄 Updated: ${data.updated || 0}</li>
                                        <li>⏭️ Skipped: ${data.skipped || 0}</li>
                                    </ul>
                                    <p class="text-muted small">Documents will continue syncing automatically.</p>
                                </div>
                            `,
                            confirmButtonColor: '#3b82f6'
                        });
                    } else if (data.status === 'skipped') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Auto-Sync Enabled',
                            text: data.reason || 'Auto-sync is now active',
                            confirmButtonColor: '#3b82f6'
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sync Issue',
                            text: data.message || 'Auto-sync enabled but sync had issues',
                            confirmButtonColor: '#f59e0b'
                        });
                    }
                    
                    // Start monitoring
                    setTimeout(() => {
                        console.log('� Starting auto-sync monitoring...');
                        startAutoSyncMonitoring();
                    }, 1000);
                })
                .catch(error => {
                    console.error('❌ Sync error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Sync Error',
                        html: `
                            <p>Failed to sync documents: ${error.message}</p>
                            <p class="text-muted small">Check browser console for details.</p>
                            <p class="text-muted small">Auto-sync is enabled and will retry automatically.</p>
                        `,
                        confirmButtonColor: '#dc3545'
                    });
                    
                    // Still start monitoring even if initial sync failed
                    setTimeout(() => {
                        startAutoSyncMonitoring();
                    }, 1000);
                });
            }, 1000); // Wait 1 second for database update
            
        } else {
            console.log('⏸️ Stopping auto-sync monitor...');
            stopAutoSyncMonitoring();
        }
    };
}

// Auto-start monitoring if enabled
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 Initializing Backup Management System...');
    
    // ✅ REMOVED: Don't enable backup button immediately - let checkGoogleDriveConnection handle it
    // The button state will be controlled by auto-sync status
    
    // Load school years immediately
    loadSchoolYears();
    loadActiveSchoolYear();
    
    // Load backup directory
    loadBackupDirectory();
    
    // ✅ NEW: Load recent backups table
    loadRecentBackups();
    
    // Check connection status and auto-sync status
    // This will also update manual backup buttons based on auto-sync state
    setTimeout(() => {
        checkGoogleDriveConnection();
    }, 1000);
    
    // Initialize other functions
    loadArchivalSettings();
    loadArchivalStatistics();
});

/**
 * Create backup function
 */
function createBackup() {
    console.log('🚀 createBackup function called');
    
    // ✅ NEW: Check if auto-sync is enabled before allowing manual backup
    const syncStatusBadge = document.getElementById('syncStatusBadge');
    if (syncStatusBadge && syncStatusBadge.innerHTML.includes('Enabled')) {
        Swal.fire({
            icon: 'warning',
            title: 'Auto-Sync Enabled',
            html: `
                <p><strong>Manual backup is currently disabled.</strong></p>
                <p class="text-muted mt-2">Auto-sync is active and handling backups automatically. 
                To prevent conflicts, manual backup is disabled.</p>
                <p class="text-muted mt-3"><small>If you need to create a manual backup, 
                please disable auto-sync first.</small></p>
            `,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Understood'
        });
        return;
    }
    
    const backupNameInput = document.getElementById('backupName');
    const schoolYearInput = document.getElementById('schoolYear');
    const storageTypeRadio = document.querySelector('input[name="storageType"]:checked');
    
    console.log('Form elements found:', {
        backupName: backupNameInput?.value,
        schoolYear: schoolYearInput?.value,
        storageType: storageTypeRadio?.value
    });
    
    const backupName = backupNameInput?.value.trim() || 'IBACMI_Backup';
    const schoolYear = schoolYearInput?.value.trim();
    const storageType = storageTypeRadio?.value || 'local';
    
    if (!schoolYear) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select a school year',
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    // Show confirmation
    Swal.fire({
        title: 'Create Backup?',
        html: `
            <p><strong>Backup Name:</strong> ${backupName}</p>
            <p><strong>School Year:</strong> ${schoolYear}</p>
            <p><strong>Storage Type:</strong> ${storageType === 'cloud' ? 'Google Drive' : 'Local Storage'}</p>
            <p class="text-muted small mt-3">This will backup all submitted student documents.</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, create backup',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            executeBackup(backupName, schoolYear, storageType);
        }
    });
}

/**
 * Execute backup process
 */
function executeBackup(backupName, schoolYear, storageType) {
    Swal.fire({
        title: 'Creating Backup...',
        html: '<p>Please wait while we backup your files...</p><p class="text-muted small">This may take a few minutes.</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'create_backup');
    formData.append('backupName', backupName);
    formData.append('schoolYear', schoolYear);
    formData.append('storageType', storageType);
    
    fetch('backup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.status === 'success') {
            const stats = data.data;
            let message = `
                <div class="text-start">
                    <p><strong>Students:</strong> ${stats.student_count || 0}</p>
                    <p><strong>Total Files:</strong> ${stats.file_count || 0}</p>
            `;
            
            if (storageType === 'cloud') {
                message += `
                    <p><strong>Uploaded:</strong> ${stats.files_uploaded || 0}</p>
                    <p><strong>Updated:</strong> ${stats.files_updated || 0}</p>
                    <p><strong>Skipped:</strong> ${stats.files_skipped || 0}</p>
                `;
            } else {
                message += `
                    <p><strong>Added:</strong> ${stats.files_added || 0}</p>
                    <p><strong>Updated:</strong> ${stats.files_updated || 0}</p>
                    <p><strong>Skipped:</strong> ${stats.files_skipped || 0}</p>
                    <p><strong>Location:</strong> ${stats.backup_path || 'N/A'}</p>
                `;
            }
            
            message += '</div>';
            
            Swal.fire({
                icon: 'success',
                title: 'Backup Complete!',
                html: message,
                confirmButtonColor: '#10b981'
            }).then(() => {
                // Reload statistics and recent backups after user closes the dialog
                console.log('🔄 Reloading backup statistics and recent backups...');
                loadBackupStatistics();
                loadRecentBackups();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Backup Failed',
                text: data.message || 'An error occurred during backup',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Backup error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while creating backup',
            confirmButtonColor: '#dc3545'
        });
    });
}

/**
 * Load backup statistics (helper function)
 */
function loadBackupStatistics() {
    console.log('📊 Loading backup statistics from server...');
    
    fetch('backup.php?action=get_backup_statistics')
        .then(response => response.json())
        .then(data => {
            console.log('📈 Backup statistics response:', data);
            
            if (data.status === 'success') {
                const stats = data.data;
                
                // Update all statistics cards
                document.getElementById('totalBackups').innerText = stats.total_backups || '0';
                document.getElementById('totalStudents').innerText = stats.total_students || '0';
                document.getElementById('totalFiles').innerText = stats.total_files || '0';
                document.getElementById('storageUsed').innerText = stats.storage_formatted || '0 B';
                document.getElementById('pendingFilesCount').innerText = stats.pending_files || '0';
                
                // Also update the pending files in sync stats if it exists
                const pendingFilesElement = document.getElementById('pendingFiles');
                if (pendingFilesElement) {
                    pendingFilesElement.innerText = stats.pending_files || '0';
                }
                
                console.log('✅ Backup statistics loaded and updated');
            } else {
                console.error('❌ Failed to load backup statistics:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ Error loading backup statistics:', error);
        });
}

/**
 * Load recent backups table
 */
function loadRecentBackups() {
    console.log('📊 Loading recent backups...');
    
    fetch('backup.php?action=get_recent_backups&limit=10')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                displayRecentBackups(data.data);
            } else {
                console.error('Failed to load recent backups:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading recent backups:', error);
        });
}

/**
 * Display recent backups in the table
 */
function displayRecentBackups(backups) {
    const tableBody = document.getElementById('backupTableBody');
    
    if (!tableBody) {
        console.error('Backup table body not found');
        return;
    }
    
    if (backups.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No backups found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tableBody.innerHTML = backups.map(backup => {
        const date = new Date(backup.created_at);
        const formattedDate = date.toLocaleDateString('en-US', { 
            month: '2-digit', 
            day: '2-digit', 
            year: 'numeric' 
        });
        
        // Format file size
        const formatSize = (bytes) => {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        };
        
        // Storage icon
        const storageIcon = backup.storage_type === 'cloud' 
            ? '<i class="fab fa-google-drive me-1"></i> Cloud' 
            : '<i class="fas fa-hdd me-1"></i> Local';
        
        // Status badge
        const statusBadge = backup.status === 'success' 
            ? '<span class="badge-success">Success</span>' 
            : '<span class="badge-danger">Failed</span>';
        
        // Action button
        const actionButton = backup.status === 'success' && backup.storage_type === 'local'
            ? `<button class="btn-download" onclick="downloadBackup(${backup.id})" title="Download backup">
                   <i class="fas fa-download"></i>
               </button>`
            : backup.status === 'success' && backup.storage_type === 'cloud'
            ? `<button class="btn-download" title="Stored in Google Drive">
                   <i class="fas fa-cloud"></i>
               </button>`
            : `<button class="btn-download" disabled title="${backup.error_message || 'Backup failed'}">
                   <i class="fas fa-exclamation-triangle"></i>
               </button>`;
        
        return `
            <tr>
                <td>${backup.backup_name}</td>
                <td>${backup.backup_type}</td>
                <td>${storageIcon}</td>
                <td>${backup.file_count}</td>
                <td>${formatSize(backup.total_size)}</td>
                <td>${statusBadge}</td>
                <td>${formattedDate}</td>
                <td>${actionButton}</td>
            </tr>
        `;
    }).join('');
    
    console.log(`✅ Loaded ${backups.length} recent backups`);
}

/**
 * Download backup file
 */
function downloadBackup(backupId) {
    console.log('📥 Downloading backup ID:', backupId);
    
    // Open download in new window
    window.open(`backup.php?action=download_backup&id=${backupId}`, '_blank');
}

// ❌ DEPRECATED: Auto-load Google APIs no longer needed
// Using PHP OAuth flow instead
/*
if (typeof gapi !== 'undefined') {
    gapi.load('client', initializeGapiClient);
}

window.onload = function() {
    if (typeof google !== 'undefined' && google.accounts) {
        gisLoaded();
    }
};
*/

/**
 * Disconnect from Google Drive
 */
function disconnectGoogle() {
    console.log('🔌 Disconnecting from Google Drive...');
    
    Swal.fire({
        title: 'Disconnect Google Drive?',
        text: 'This will remove your Google Drive connection and disable auto-sync.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, disconnect',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Disconnecting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('action', 'disconnect_google');
            
            fetch('backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Disconnected!',
                        text: 'Google Drive has been disconnected',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // ✅ IMPORTANT: Force update UI to disconnected/disabled
                    updateConnectionStatus(false);
                    updateSyncStatus('disabled');
                    stopAutoSyncMonitoring();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to disconnect',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                console.error('Error disconnecting:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while disconnecting',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

// ========================================
// SCHOOL YEAR MANAGEMENT FUNCTIONS
// ========================================

/**
 * Load school years from API
 */
function loadSchoolYears() {
    console.log('📅 Loading school years...');
    
    fetch('school_year_api.php?action=get_school_years')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                displaySchoolYears(data.data);
            } else {
                console.error('Failed to load school years:', data.message);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to load school years',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            console.error('Error loading school years:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load school years',
                confirmButtonColor: '#dc3545'
            });
        });
}

/**
 * Display school years in the list
 */
function displaySchoolYears(schoolYears) {
    const container = document.getElementById('schoolYearsList');
    
    if (!container) {
        console.error('School years container not found');
        return;
    }
    
    if (schoolYears.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-3">No school years found</div>';
        return;
    }
    
    container.innerHTML = schoolYears.map(year => `
        <div class="school-year-item">
            <div class="year-info">
                <span class="year-name">${year.school_year}</span>
                ${year.is_active == 1 ? 
                    '<span class="badge bg-success ms-2">Active</span>' : 
                    '<span class="badge bg-secondary ms-2">Inactive</span>'}
                ${year.auto_advance_enabled == 1 ? 
                    '<span class="badge bg-info ms-1">Auto-Advance</span>' : ''}
            </div>
            <div class="d-flex gap-2">
                ${year.is_active != 1 ? 
                    `<button class="btn btn-sm btn-primary" onclick="setActiveSchoolYear(${year.id})" title="Set as active">
                        <i class="fas fa-check"></i>
                    </button>` : ''}
                <button class="btn btn-sm btn-outline-primary" onclick="editSchoolYear(${year.id}, '${year.school_year}', '${year.end_date || ''}', ${year.auto_advance_enabled})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                ${year.is_active != 1 ? 
                    `<button class="btn btn-sm btn-outline-danger" onclick="deleteSchoolYear(${year.id}, '${year.school_year}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
            </div>
        </div>
    `).join('');
}

/**
 * Add new school year
 */
function addSchoolYear() {
    const schoolYear = document.getElementById('newSchoolYear').value.trim();
    const endDate = document.getElementById('schoolYearEndDate').value;
    const autoAdvance = document.getElementById('autoAdvanceEnabled').checked ? 1 : 0;
    
    if (!schoolYear) {
        Swal.fire({
            title: 'Missing Information',
            text: 'Please enter a school year (e.g., 2024-2025)',
            icon: 'warning'
        });
        return;
    }
    
    if (!endDate) {
        Swal.fire({
            title: 'Missing Information',
            text: 'Please select an end date',
            icon: 'warning'
        });
        return;
    }
    
    Swal.fire({
        title: 'Adding School Year...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'add_school_year');
    formData.append('school_year', schoolYear);
    formData.append('end_date', endDate);
    formData.append('auto_advance_enabled', autoAdvance);
    
    fetch('school_year_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                title: 'Success!',
                text: 'School year added successfully',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
            
            // Clear form
            document.getElementById('newSchoolYear').value = '';
            document.getElementById('schoolYearEndDate').value = '';
            document.getElementById('autoAdvanceEnabled').checked = false;
            
            // ✅ FIX: Reload school years after adding
            loadSchoolYears();
            
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to add school year',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'An error occurred while adding school year',
            icon: 'error'
        });
    });
}

/**
 * Set active school year
 */
function setActiveSchoolYear(schoolYearId) {
    Swal.fire({
        title: 'Set as Active?',
        text: 'This will deactivate the current active school year',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, set as active',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('action', 'set_active');
            formData.append('school_year_id', schoolYearId);
            
            fetch('school_year_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Active school year updated',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // ✅ FIX: Reload both school years list AND update backup form
                    loadSchoolYears();
                    loadActiveSchoolYear();
                    
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to update active school year',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred',
                    icon: 'error'
                });
            });
        }
    });
}

/**
 * Edit school year - IMPLEMENTED
 */
function editSchoolYear(id, schoolYear, endDate, autoAdvance) {
    console.log('Edit school year:', { id, schoolYear, endDate, autoAdvance });
    
    Swal.fire({
        title: 'Edit School Year',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">School Year</label>
                    <input type="text" class="form-control" id="edit_school_year" value="${schoolYear}" readonly style="background-color: #f8f9fa;">
                    <small class="text-muted">School year cannot be changed</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">End Date</label>
                    <input type="date" class="form-control" id="edit_end_date" value="${endDate || ''}">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="edit_auto_advance" ${autoAdvance == 1 ? 'checked' : ''}>
                    <label class="form-check-label" for="edit_auto_advance">
                        <i class="fas fa-robot me-1"></i>
                        Enable automatic student advancement on end date
                    </label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-save me-1"></i> Save Changes',
        cancelButtonText: 'Cancel',
        width: '500px',
        preConfirm: () => {
            const endDate = document.getElementById('edit_end_date').value;
            const autoAdvanceEnabled = document.getElementById('edit_auto_advance').checked ? 1 : 0;
            
            if (!endDate) {
                Swal.showValidationMessage('Please select an end date');
                return false;
            }
            
            return {
                school_year_id: id,
                end_date: endDate,
                auto_advance_enabled: autoAdvanceEnabled
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateSchoolYear(result.value);
        }
    });
}

/**
 * Update school year via API
 */
function updateSchoolYear(data) {
    Swal.fire({
        title: 'Updating...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'update_school_year');
    formData.append('school_year_id', data.school_year_id);
    formData.append('end_date', data.end_date);
    formData.append('auto_advance_enabled', data.auto_advance_enabled);
    
    fetch('school_year_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'School year updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
            loadSchoolYears();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: data.message || 'Failed to update school year',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error updating school year:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while updating',
            confirmButtonColor: '#dc3545'
        });
    });
}

/**
 * Delete school year
 */
function deleteSchoolYear(schoolYearId, schoolYear) {
    Swal.fire({
        title: 'Delete School Year?',
        text: `Are you sure you want to delete "${schoolYear}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('action', 'delete_school_year');
            formData.append('school_year_id', schoolYearId);
            
            fetch('school_year_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'School year deleted successfully',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // ✅ FIX: Reload school years after deleting
                    loadSchoolYears();
                    
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to delete school year',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred',
                    icon: 'error'
                });
            });
        }
    });
}

/**
 * Save archival settings
 */
function saveArchivalSettings() {
    const timing = document.getElementById('archivalTiming').value;
    const autoEnabled = document.getElementById('autoArchivalEnabled').checked ? 1 : 0;
    
    Swal.fire({
        title: 'Saving...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'save_archival_settings',
            timing: timing,
            enabled: autoEnabled
        })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Archival settings saved successfully',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            throw new Error(data.message || 'Failed to save settings');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error saving archival settings:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An error occurred while saving',
            confirmButtonColor: '#dc3545'
        });
    });
}

/**
 * Load archival settings on page load
 */
function loadArchivalSettings() {
    fetch('backup.php?action=get_archival_settings')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('archivalTiming').value = data.data.timing || '1_year';
                document.getElementById('autoArchivalEnabled').checked = data.data.enabled == 1;
            }
        })
        .catch(error => {
            console.error('Error loading archival settings:', error);
        });
}

/**
 * Load archival statistics and update UI
 */
function loadArchivalStatistics() {
    console.log('📊 Loading archival statistics...');
    
    // Add timestamp to prevent caching
    const timestamp = new Date().getTime();
    fetch(`backup.php?action=get_archival_statistics&_=${timestamp}`)
        .then(response => response.json())
        .then(data => {
            console.log('Archival stats:', data);
            
            if (data.status === 'success') {
                const pendingCount = data.data.pending_count || 0;
                const archivedCount = data.data.archived_count || 0;
                
                console.log(`Pending: ${pendingCount}, Archived: ${archivedCount}`);
                
                document.getElementById('pendingArchiveCount').innerText = pendingCount;
                document.getElementById('archivedCount').innerText = archivedCount;
                document.getElementById('lastRunTime').innerText = data.data.last_run || 'Never';
                document.getElementById('nextEligibleDate').innerText = data.data.next_eligible_date || 'N/A';
                
                // Enable/disable the "Run Now" button based on eligibility
                updateArchivalButton(pendingCount);
                
                console.log('✅ Archival statistics loaded');
            }
        })
        .catch(error => {
            console.error('❌ Error loading archival statistics:', error);
        });
}

/**
 * Update the archival button state based on pending count
 */
function updateArchivalButton(pendingCount) {
    const runButton = document.querySelector('button[onclick="runAutoArchival()"]');
    if (!runButton) return;
    
    if (pendingCount > 0) {
        // Enable button
        runButton.disabled = false;
        runButton.style.opacity = '1';
        runButton.style.cursor = 'pointer';
        runButton.title = `${pendingCount} student(s) ready for archival`;
    } else {
        // Disable button
        runButton.disabled = true;
        runButton.style.opacity = '0.5';
        runButton.style.cursor = 'not-allowed';
        runButton.title = 'No students eligible for archival';
    }
}

/**
 * Run auto-archival manually
 */
function runAutoArchival() {
    Swal.fire({
        title: 'Run Archival?',
        text: 'This will archive all eligible students. Are you sure?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, run archival',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Archiving...',
                text: 'Please wait while students are being archived',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Run archival
            fetch('backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'run_auto_archival'
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Archival Complete!',
                        html: `
                            <p><strong>Students Archived:</strong> ${data.students_archived || 0}</p>
                            <p><strong>Documents Deleted:</strong> ${data.total_files || 0}</p>
                            ${data.errors && data.errors.length > 0 ? '<p class="text-warning">Some errors occurred. Check logs for details.</p>' : ''}
                        `,
                        confirmButtonColor: '#dc3545'
                    });
                    
                    // Refresh statistics
                    loadArchivalStatistics();
                } else {
                    throw new Error(data.message || 'Archival failed');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error running auto-archival:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Archival Failed',
                    text: error.message || 'An error occurred while running archival',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

/**
 * Save backup directory path
 */
function saveBackupPath() {
    const pathInput = document.querySelector('input[placeholder*="backup directory"]');
    
    if (!pathInput) {
        console.error('Backup path input not found');
        return;
    }
    
    const backupPath = pathInput.value.trim();
    
    if (!backupPath) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Path',
            text: 'Please enter a backup directory path',
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    Swal.fire({
        title: 'Saving...',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const formData = new FormData();
    formData.append('action', 'save_backup_path');
    formData.append('path', backupPath);
    
    fetch('backup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Backup directory path saved successfully',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Update the current path display if it exists
            const currentPathElement = document.querySelector('.path-value');
            if (currentPathElement) {
                currentPathElement.textContent = data.data.directory;
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: data.message || 'Failed to save backup directory path',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error saving backup path:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving the path',
            confirmButtonColor: '#dc3545'
        });
    });
}

/**
 * Load current backup directory
 */
function loadBackupDirectory() {
    console.log('🔄 Loading backup directory from database...');
    
    fetch('backup.php?action=get_backup_directory')
        .then(response => {
            console.log('📡 Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('📂 Backup directory response:', data);
            
            if (data.status === 'success') {
                const currentPathElement = document.querySelector('.path-value');
                const pathInput = document.querySelector('input[placeholder*="backup directory"]');
                
                // ✅ Update display element
                if (currentPathElement) {
                    currentPathElement.textContent = data.data.directory;
                    console.log('✅ Updated display element with:', data.data.directory);
                }
                
                // ✅ Update input field
                if (pathInput) {
                    pathInput.value = data.data.directory;
                    console.log('✅ Updated input field with:', data.data.directory);
                } else {
                    // Try alternative selector
                    const altInput = document.getElementById('backupPath');
                    if (altInput) {
                        altInput.value = data.data.directory;
                        console.log('✅ Updated input field (alt) with:', data.data.directory);
                    } else {
                        console.error('❌ Input field not found - check HTML structure');
                    }
                }
            } else {
                console.error('❌ Failed to load directory:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('❌ Error loading backup directory:', error);
        });
}

// ✅ MAKE SURE THIS RUNS ON PAGE LOAD
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Page loaded, initializing backup system...');
    
    // Load backup directory first
    loadBackupDirectory();
    
    // Load other data
    if (typeof loadBackupStatistics === 'function') {
        loadBackupStatistics();
    }
    
    // ✅ Load recent backups table
    if (typeof loadRecentBackups === 'function') {
        loadRecentBackups();
    }
    
    // ✅ Check Google Drive connection ONCE (this function already handles everything)
    checkGoogleDriveConnection();
});

// ✅ ADD THIS FUNCTION - it's missing!
function submitBackup(storageType) {
    console.log('submitBackup called with:', storageType);
    createBackup(); // Call the actual backup function
}

// ✅ ALSO UPDATE createBackup to ensure it reads the form correctly
function createBackup() {
    console.log('🚀 createBackup function called');
    
    const backupNameInput = document.getElementById('backupName');
    const schoolYearInput = document.getElementById('schoolYear');
    const storageTypeRadio = document.querySelector('input[name="storageType"]:checked');
    
    console.log('Form elements found:', {
        backupName: backupNameInput?.value,
        schoolYear: schoolYearInput?.value,
        storageType: storageTypeRadio?.value
    });
    
    const backupName = backupNameInput?.value.trim() || 'IBACMI_Backup';
    const schoolYear = schoolYearInput?.value.trim();
    const storageType = storageTypeRadio?.value || 'local';
    
    if (!schoolYear) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select a school year',
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    // Show confirmation
    Swal.fire({
        title: 'Create Backup?',
        html: `
            <p><strong>Backup Name:</strong> ${backupName}</p>
            <p><strong>School Year:</strong> ${schoolYear}</p>
            <p><strong>Storage Type:</strong> ${storageType === 'cloud' ? 'Google Drive' : 'Local Storage'}</p>
            <p class="text-muted small mt-3">This will backup all submitted student documents.</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, create backup',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            executeBackup(backupName, schoolYear, storageType);
        }
    });
}

// Make sure DOMContentLoaded calls it
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 Initializing Backup Management System...');
    
    // Enable button
    const createBackupBtn = document.querySelector('.modern-primary-btn');
    if (createBackupBtn) {
        createBackupBtn.disabled = false;
        createBackupBtn.style.opacity = '1';
        createBackupBtn.style.cursor = 'pointer';
        console.log('✅ Create backup button enabled');
    }
    
    // ✅ Load active school year FIRST
    loadActiveSchoolYear();
    
    // Load other data
    loadSchoolYears();
    loadBackupDirectory();
    
    // ✅ Load recent backups table
    if (typeof loadRecentBackups === 'function') {
        loadRecentBackups();
    }
    
    setTimeout(() => {
        checkGoogleDriveConnection();
    }, 1000);
    
    loadArchivalSettings();
    loadArchivalStatistics();
});

/**
 * Load active school year - FIXED TO USE EXISTING INPUT
 */
async function loadActiveSchoolYear() {
    console.log('📅 Loading active school year...');
    
    try {
        const response = await fetch('backup.php?action=get_school_years');
        const result = await response.json();
        
        console.log('School years response:', result);
        
        if (result.status === 'success' && result.data.active_year) {
            const activeYear = result.data.active_year;
            
            // ✅ Set the school year in the EXISTING input
            const schoolYearInput = document.getElementById('schoolYear');
            if (schoolYearInput) {
                schoolYearInput.value = activeYear;
                console.log('✅ Set active school year:', activeYear);
            } else {
                console.error('❌ schoolYear input not found');
            }
            
        } else {
            console.warn('⚠️ No active school year found');
            
            Swal.fire({
                icon: 'warning',
                title: 'No Active School Year',
                text: 'Please set an active school year in School Year Management first',
                confirmButtonColor: '#dc3545'
            });
        }
        
    } catch (error) {
        console.error('❌ Error loading active school year:', error);
    }
}
