<?php
// filepath: c:\xampp\htdocs\ibacmi\AdminAccount\school_year_api.php
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/school_year_errors.log');

require_once '../db_connect.php';
date_default_timezone_set('Asia/Manila');
session_start();

/**
 * Send JSON response
 */
function sendJsonResponse($data) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Get all school years
 */
function getSchoolYears() {
    global $conn;
    
    $query = "SELECT * FROM school_years ORDER BY school_year DESC";
    $result = $conn->query($query);
    
    $schoolYears = [];
    while ($row = $result->fetch_assoc()) {
        $schoolYears[] = $row;
    }
    
    return $schoolYears;
}

/**
 * Add new school year - FIXED
 */
function addSchoolYear($schoolYear, $endDate = null, $autoAdvanceEnabled = 1) {
    global $conn;
    
    // Validate format (YYYY-YYYY)
    if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {
        throw new Exception('Invalid school year format. Use YYYY-YYYY (e.g., 2024-2025)');
    }
    
    // Check if already exists
    $checkStmt = $conn->prepare("SELECT id FROM school_years WHERE school_year = ?");
    $checkStmt->bind_param("s", $schoolYear);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        throw new Exception('School year already exists');
    }
    $checkStmt->close();
    
    // ✅ FIX: Properly insert with all required fields
    $stmt = $conn->prepare("INSERT INTO school_years 
        (school_year, is_active, end_date, auto_advance_enabled, created_at, updated_at)
        VALUES (?, 0, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssi", $schoolYear, $endDate, $autoAdvanceEnabled);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    }
    
    $error = $stmt->error;
    $stmt->close();
    throw new Exception('Failed to add school year: ' . $error);
}

/**
 * Set active school year
 */
function setActiveSchoolYear($schoolYearId) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deactivate all school years
        $conn->query("UPDATE school_years SET is_active = 0");
        
        // Activate selected school year
        $stmt = $conn->prepare("UPDATE school_years SET is_active = 1, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $schoolYearId);
        $stmt->execute();
        $stmt->close();
        
        // Update system settings
        $getYear = $conn->prepare("SELECT school_year FROM school_years WHERE id = ?");
        $getYear->bind_param("i", $schoolYearId);
        $getYear->execute();
        $result = $getYear->get_result();
        $yearData = $result->fetch_assoc();
        $getYear->close();
        
        if ($yearData) {
            $updateSetting = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value, updated_at) 
                VALUES ('current_school_year', ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $updateSetting->bind_param("ss", $yearData['school_year'], $yearData['school_year']);
            $updateSetting->execute();
            $updateSetting->close();
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Update school year settings
 */
function updateSchoolYear($schoolYearId, $endDate, $autoAdvanceEnabled) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE school_years 
        SET end_date = ?, 
            auto_advance_enabled = ?,
            updated_at = NOW()
        WHERE id = ?");
    $stmt->bind_param("sii", $endDate, $autoAdvanceEnabled, $schoolYearId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    }
    
    $error = $stmt->error;
    $stmt->close();
    throw new Exception('Failed to update school year: ' . $error);
}

/**
 * Delete school year
 */
function deleteSchoolYear($schoolYearId) {
    global $conn;
    
    // Check if it's the active school year
    $checkStmt = $conn->prepare("SELECT is_active FROM school_years WHERE id = ?");
    $checkStmt->bind_param("i", $schoolYearId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        throw new Exception('School year not found');
    }
    
    $row = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($row['is_active'] == 1) {
        throw new Exception('Cannot delete the active school year');
    }
    
    // Delete the school year
    $stmt = $conn->prepare("DELETE FROM school_years WHERE id = ?");
    $stmt->bind_param("i", $schoolYearId);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    }
    
    $error = $stmt->error;
    $stmt->close();
    throw new Exception('Failed to delete school year: ' . $error);
}

// ========================================
// API ENDPOINTS
// ========================================

// Get all school years
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_school_years') {
    try {
        $schoolYears = getSchoolYears();
        sendJsonResponse(['status' => 'success', 'data' => $schoolYears]);
    } catch (Exception $e) {
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Add school year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_school_year') {
    try {
        $schoolYear = $_POST['school_year'] ?? '';
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $autoAdvanceEnabled = isset($_POST['auto_advance_enabled']) ? (int)$_POST['auto_advance_enabled'] : 1;
        
        addSchoolYear($schoolYear, $endDate, $autoAdvanceEnabled);
        sendJsonResponse(['status' => 'success', 'message' => 'School year added successfully']);
    } catch (Exception $e) {
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Set active school year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_active') {
    try {
        $schoolYearId = (int)$_POST['school_year_id'];
        setActiveSchoolYear($schoolYearId);
        sendJsonResponse(['status' => 'success', 'message' => 'Active school year updated']);
    } catch (Exception $e) {
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Update school year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_school_year') {
    try {
        $schoolYearId = (int)$_POST['school_year_id'];
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $autoAdvanceEnabled = isset($_POST['auto_advance_enabled']) ? (int)$_POST['auto_advance_enabled'] : 1;
        
        updateSchoolYear($schoolYearId, $endDate, $autoAdvanceEnabled);
        sendJsonResponse(['status' => 'success', 'message' => 'School year updated successfully']);
    } catch (Exception $e) {
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Delete school year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_school_year') {
    try {
        $schoolYearId = (int)$_POST['school_year_id'];
        deleteSchoolYear($schoolYearId);
        sendJsonResponse(['status' => 'success', 'message' => 'School year deleted successfully']);
    } catch (Exception $e) {
        sendJsonResponse(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

sendJsonResponse(['status' => 'error', 'message' => 'Invalid request']);
?>