<?php
// filepath: c:\xampp\htdocs\ibacmi\AdminAccount\archival_cron.php
/**
 * Automatic Archival Cron Job
 * 
 * Run this script daily via cron to automatically archive eligible students
 * Example crontab entry (runs daily at 2 AM):
 * 0 2 * * * /usr/bin/php /path/to/archival_cron.php
 * 
 * For Windows Task Scheduler:
 * Program: C:\xampp\php\php.exe
 * Arguments: -f "C:\xampp\htdocs\ibacmi\AdminAccount\archival_cron.php"
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/archival_cron.log');

require_once '../db_connect.php';
require_once 'archival_api.php';

echo "=== Archival Cron Job Started at " . date('Y-m-d H:i:s') . " ===\n";

try {
    $result = runAutoArchival();
    
    if ($result['status'] === 'success') {
        echo "✓ Archival completed successfully\n";
        echo "  Students archived: " . $result['students_archived'] . "\n";
        echo "  Total files: " . $result['total_files'] . "\n";
        echo "  Total size: " . number_format($result['total_size']) . " bytes\n";
        
        if (!empty($result['errors'])) {
            echo "⚠ Errors encountered:\n";
            foreach ($result['errors'] as $error) {
                echo "  - " . $error . "\n";
            }
        }
    } else {
        echo "ℹ " . $result['message'] . "\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    error_log("Archival cron error: " . $e->getMessage());
}

echo "=== Archival Cron Job Ended at " . date('Y-m-d H:i:s') . " ===\n\n";
?>