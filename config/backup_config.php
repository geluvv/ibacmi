<?php
define('GOOGLE_CLIENT_SECRET', __DIR__ . '/google/client_secret.json');
define('BACKUP_FOLDER_NAME', 'IBACMI_Backups');
define('BACKUP_TEMP_PATH', __DIR__ . '/../temp/backups/');

// Create temp directory if it doesn't exist
if (!file_exists(BACKUP_TEMP_PATH)) {
    mkdir(BACKUP_TEMP_PATH, 0755, true);
}