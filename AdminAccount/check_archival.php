<?php
/**
 * Check Archival - Automatic trigger for archival system
 * This file is called to check and execute auto-archival
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/archival_check.log');

require_once '../db_connect.php';
require_once 'archival_api.php';

date_default_timezone_set('Asia/Manila');

// Log the check
error_log("=== ARCHIVAL CHECK TRIGGERED at " . date('Y-m-d H:i:s') . " ===");

try {
    // Get settings
    $settings = getArchivalSettings();
    
    if (!$settings['auto_archival_enabled']) {
        error_log("Auto-archival is disabled");
        echo json_encode(['status' => 'disabled', 'message' => 'Auto-archival is disabled']);
        exit;
    }
    
    error_log("Auto-archival is enabled with timing: {$settings['timing_option']}");
    
    // Get eligible students
    $eligibleStudents = getEligibleStudents();
    
    error_log("Found " . count($eligibleStudents) . " eligible students");
    
    if (count($eligibleStudents) > 0) {
        error_log("Starting auto-archival process...");
        
        // Run the archival
        $result = runAutoArchival();
        
        error_log("Archival completed: " . json_encode($result));
        
        echo json_encode($result);
    } else {
        error_log("No students eligible for archival at this time");
        echo json_encode([
            'status' => 'success',
            'message' => 'No students eligible for archival',
            'students_archived' => 0
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in check_archival: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

error_log("=== ARCHIVAL CHECK ENDED at " . date('Y-m-d H:i:s') . " ===\n");
?>
