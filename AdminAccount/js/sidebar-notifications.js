/**
 * Centralized Sidebar Notification System
 * Manages the green dot indicator for pending backups across all admin pages
 */

console.log('📢 Sidebar Notifications Module Loaded');

/**
 * Update sidebar notification badge - Green Dot (Centralized)
 */
function updateSidebarBackupNotification(count) {
    console.log('🔔 Updating backup notification:', count);
    
    // Find the Backup link in the sidebar
    const backupLink = document.querySelector('a[href*="backup.html"], a[href*="backup.php"], a[href*="staffbackup.php"]');
    
    if (!backupLink) {
        console.warn('⚠️ Backup link not found in sidebar');
        return;
    }
    
    // Ensure link has position: relative
    backupLink.style.position = 'relative';
    
    // Remove existing badge
    const existingBadge = backupLink.querySelector('.sidebar-notification-badge');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Add green dot only if count > 0
    if (count > 0) {
        const dot = document.createElement('span');
        dot.className = 'sidebar-notification-badge';
        dot.title = `${count} file${count > 1 ? 's' : ''} pending backup`;
        
        backupLink.appendChild(dot);
        console.log('✅ Green dot added (count:', count, ')');
    } else {
        console.log('✓ No dot (count is 0)');
    }
}

/**
 * Load pending backup count from server
 */
function loadPendingBackupCountForSidebar() {
    fetch('backup.php?action=get_pending_count')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const count = data.data.pending_count || 0;
                updateSidebarBackupNotification(count);
            }
        })
        .catch(error => {
            console.error('Error loading pending count:', error);
        });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Initializing sidebar notifications...');
        loadPendingBackupCountForSidebar();
        
        // Refresh every 2 minutes
        setInterval(loadPendingBackupCountForSidebar, 120000);
    });
} else {
    // DOM already loaded
    console.log('🚀 Initializing sidebar notifications (late load)...');
    loadPendingBackupCountForSidebar();
    
    // Refresh every 2 minutes
    setInterval(loadPendingBackupCountForSidebar, 120000);
}

// Export for manual triggering
window.refreshBackupNotification = loadPendingBackupCountForSidebar;