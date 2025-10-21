<?php
// CRITICAL: No output before this point
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Start output buffering IMMEDIATELY
ob_start();

// Check if this is being included from staff or admin context
$isStaffView = defined('IS_STAFF_VIEW') && IS_STAFF_VIEW;
$staffInfo = $isStaffView && defined('STAFF_INFO') ? STAFF_INFO : array();

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only include database connection if not already included
if (!isset($conn)) {
    require_once '../db_connect.php';
}

// Include the document validator
require_once __DIR__ . '/document_validator.php';

// Change timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check authentication based on view type
if (!$isStaffView) {
    // Admin authentication check
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.html");
        exit();
    }
}

error_log("=== LACKINGOFDOC.PHP LOADED ===");
error_log("Is Staff View: " . ($isStaffView ? 'YES' : 'NO'));

// Include the logic file
require_once __DIR__ . '/lackingofdoc_logic.php';

// If we reach here, display the HTML
ob_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isStaffView ? 'Lacking Documents - Staff' : 'Lacking Documents - Admin'; ?> - IBACMI</title>
    
    <!-- Core CSS Libraries -->
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
     <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png">
    
    <!-- Sidebar CSS -->
    <?php if ($isStaffView): ?>
        <link href="../AdminAccount/css/sidebar.css" rel="stylesheet">
    <?php else: ?>
        <link href="css/sidebar.css" rel="stylesheet">
    <?php endif; ?>
    
    <!-- External Libraries for Document Processing -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.2/mammoth.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #800000;
            --primary-hover: #6a0000;
            --sidebar-bg: #ffffff;
            --text-dark: #343a40;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .welcome-section {
            background: linear-gradient(135deg, #800000, #a00000);
            border-radius: 16px;
            padding: 2.5rem;
            color: #ffffff;
            margin-bottom: 2.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTAwIDAgQzY5LjQgMCA0NSAyNC40IDQ1IDU1YzAgMTkuOSAxMC40IDM3LjIgMjYgNDcuMkM1Ni40IDExMi4yIDQ1IDEyOS41IDQ1IDE0OS40YzAgMzAuNiAyNC40IDU1IDU1IDU1czU1LTI0LjQgNTUtNTVjMC0xOS45LTEwLjQtMzcuMi0yNi00Ny4yIDE1LjYtMTAgMjYtMjcuMyAyNi00Ny4yIDAtMzAuNi0yNC40LTU1LTU1LTU1eiIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9zdmc+') repeat;
            opacity: 0.1;
        }

        .user-welcome h4 {
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
            letter-spacing: -0.5px;
        }

        .user-welcome p {
            opacity: 0.9;
            font-size: 1.1rem;
            font-weight: 300;
        }

        .search-bar {
            position: relative;
            max-width: 300px;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 3rem;
            border: none;
            border-radius: 50px;
            background-color: rgba(255,255,255,0.15);
            color: #ffffff;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .search-input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .search-input:focus {
            background-color: rgba(255,255,255,0.25);
            outline: none;
            box-shadow: 0 0 0 5px rgba(255,255,255,0.1);
        }

        .search-button {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #ffffff;
            padding: 0;
            font-size: 1.1rem;
        }

        /* Alert Styles */
        .alert {
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 2rem;
            border: none;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert i {
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .alert-success {
            background-color: rgba(82, 196, 26, 0.1);
            color: #389e0d;
            border-left: 4px solid #52c41a;
        }

        .alert-danger {
            background-color: rgba(255, 77, 79, 0.1);
            color: #cf1322;
            border-left: 4px solid #ff4d4f;
        }

        /* Table Styles */
        .table-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table-responsive {
            overflow-x: auto;
            min-height: 300px;
        }

        .student-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .student-table th {
            background-color: #800000;
            color: #ffffff;
            padding: 1.25rem 1rem;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
            position: sticky;
            top: 0;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .student-table th:first-child {
            border-top-left-radius: 8px;
        }

        .student-table th:last-child {
            border-top-right-radius: 8px;
        }

        .student-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
            transition: all 0.3s ease;
        }

        .student-table tr:last-child td {
            border-bottom: none;
        }

        .student-table tr:hover td {
            background-color: rgba(128,0,0,0.02);
        }

        /* Badge Styles */
        .badge-missing {
            background-color: rgba(255, 77, 79, 0.1);
            color: #ff4d4f;
            border: 1px solid rgba(255, 77, 79, 0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 0.25rem 0.25rem 0.25rem 0;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .badge-missing:hover {
            background-color: rgba(255, 77, 79, 0.15);
            transform: translateY(-2px);
        }

        .student-type {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .type-regular {
            background-color: rgba(24, 144, 255, 0.1);
            color: #1890ff;
            border: 1px solid rgba(24, 144, 255, 0.2);
        }

        .type-transferee {
            background-color: rgba(82, 196, 26, 0.1);
            color: #52c41a;
            border: 1px solid rgba(82, 196, 26, 0.2);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            background: #ffffff;
            border-radius: 16px;
            padding: 4rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .empty-state i {
            font-size: 4rem;
            color: #800000;
            margin-bottom: 1.5rem;
            opacity: 0.8;
        }

        .empty-state h4 {
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .empty-state p {
            color: #666;
            max-width: 500px;
            margin: 0 auto;
            font-size: 1.1rem;
        }

        /* Action Buttons */
        .update-btn {
            background-color: #800000;
            color: #ffffff;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .update-btn:hover {
            background-color: #600000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,0,0.2);
        }

        .export-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .export-btn {
            background-color: #ffffff;
            color: #800000;
            border: 2px solid #800000;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn:hover {
            background-color: #800000;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,0,0.1);
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            background: #800000 !important;
            background-color: #800000 !important;
            color: #ffffff !important;
            border-bottom: none;
            padding: 1.5rem;
        }


        .modal-title {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #ffffff !important;
            font-size: 1.25rem;
        }

        .modal-title i {
            color: #ffffff !important;
        }

        .modal-body {
            padding: 2rem;
        }

        .document-item {
            background-color: #f9f9f9;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            border-left: 4px solid #ff4d4f;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
            position: relative;
        }
        

        .document-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.07);
        }

        .document-item.submitted {
            border-left: 4px solid #52c41a;
            background-color: rgba(82, 196, 26, 0.05);
        }

        .document-item.file-selected {
            border-left: 4px solid #1890ff;
            background-color: rgba(24, 144, 255, 0.05);
        }

        .document-item.valid-document {
            border-left: 4px solid #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }

        .document-item.invalid-document {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }

        .document-item.validating {
            border-left: 4px solid #ffa500;
            background-color: rgba(255, 165, 0, 0.05);
        }

        .document-status {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.7rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .status-submitted {
            background-color: rgba(82, 196, 26, 0.1);
            color: #52c41a;
        }

        .status-missing {
            background-color: rgba(255, 77, 79, 0.1);
            color: #ff4d4f;
        }

        .status-valid {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-invalid {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .status-validating {
            background-color: rgba(255, 165, 0, 0.1);
            color: #ffa500;
        }

        .validation-indicator {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
            z-index: 10;
            animation: fadeIn 0.3s ease;
        }

        .validation-indicator.valid {
            background-color: #28a745;
            animation: checkPulse 0.6s ease;
        }

        .validation-indicator.invalid {
            background-color: #dc3545;
            animation: xShake 0.6s ease;
        }

        .validation-indicator.validating {
            background-color: #ffa500;
            animation: spin 1s linear infinite;
        }

        @keyframes checkPulse {
            0% { transform: translateY(-50%) scale(0.8); opacity: 0; }
            50% { transform: translateY(-50%) scale(1.2); }
            100% { transform: translateY(-50%) scale(1); opacity: 1; }
        }

        @keyframes xShake {
            0% { transform: translateY(-50%) scale(0.8); opacity: 0; }
            25% { transform: translateY(-50%) scale(1.2); }
            50% { transform: translateY(-50%) scale(1); }
            75% { transform: translateY(-50%) translateX(-2px); }
            100% { transform: translateY(-50%) translateX(0); opacity: 1; }
        }

        @keyframes spin {
            from { transform: translateY(-50%) rotate(0deg); }
            to { transform: translateY(-50%) rotate(360deg); }
        }

        .form-control {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
            margin-top: 0.2rem;
        }

        .form-check-input:checked {
            background-color: #800000;
            border-color: #800000;
        }

        .form-check-label {
            padding-left: 0.3rem;
        }

        .btn-primary {
            background-color: #800000;
            border: none;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover, 
        .btn-primary:focus {
            background-color: #600000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,0,0.2);
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #444;
            border: none;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover, 
        .btn-secondary:focus {
            background-color: #e0e0e0;
            color: #333;
            transform: translateY(-2px);
        }

        .quick-upload-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .quick-upload-title {
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: #800000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .upload-area:hover {
            border-color: #800000;
            background-color: rgba(128, 0, 0, 0.02);
        }

        .upload-area.dragover {
            border-color: #800000;
            background-color: rgba(128, 0, 0, 0.05);
        }

        .upload-icon {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        /* Add validation-specific styles */
        .upload-item.validating {
            border-color: #17a2b8 !important;
            background-color: rgba(23, 162, 184, 0.05) !important;
            border-style: dashed !important;
        }
        
        .validation-status {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
        
        .validation-status.valid {
            background-color: #28a745;
        }
        
        .validation-status.invalid {
            background-color: #dc3545;
        }
        
        .validation-status.warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .progress {
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .file-info {
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            right: 0.5rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 6px;
            padding: 0.25rem;
            font-size: 0.7rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            max-height: 60px;
            overflow: hidden;
        }
        
        .upload-item.uploaded .file-info {
            display: block;
        }
        
        .upload-item:not(.uploaded) .file-info {
            display: none;
        }
        
        /* Animation for validation states */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .upload-item.uploaded {
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .upload-item.invalid {
            animation: shake 0.5s ease-in-out;
        }

        /* New styles for validation indicators */
        .validation-indicator-inline {
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .validation-indicator-inline i.fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        .validation-indicator-inline i.fa-check-circle {
            color: #28a745;
            animation: checkPulse 0.5s ease;
        }
        
        .validation-indicator-inline i.fa-times-circle,
        .validation-indicator-inline i.fa-exclamation-circle {
            color: #dc3545;
            animation: xShake 0.5s ease;
        }
        
        .validation-indicator-inline i.fa-exclamation-triangle {
            color: #ffc107;
        }
        
        .validation-message {
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .swal-wide {
            width: 600px !important;
        }

        .swal2-html-container ul {
            max-height: 200px;
            overflow-y: auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .welcome-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .user-welcome {
                max-width: 100%;
            }

            .search-bar {
                max-width: 100%;
                width: 100%;
            }

            .student-table th,
            .student-table td {
                padding: 0.85rem 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
        // Render appropriate sidebar based on view type
        if ($isStaffView) {
            include '../StaffAccount/staffsidebar.php';
            renderSidebar('lacking', 'staff', $staffInfo);
        } else {
            include 'sidebar.php';
            renderSidebar('lacking');
        }
        ?>
        
        <div class="main-content">
            <div class="welcome-section">
                <div class="user-welcome">
                    <h4>Students lacking documents</h4>
                    <p class="mb-0">Efficiently monitor and update missing student documents with real-time validation</p>
                </div>
                <form class="search-bar" method="GET" action="<?php echo $isStaffView ? 'stafflackingdoc.php' : 'lackingofdoc.php'; ?>">
                    <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                    <input type="text" name="search" class="search-input" placeholder="Search by ID, name or course..." value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>">
                </form>
            </div>
            
            <?php if (isset($updateSuccess) && $updateSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $updateMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (count($incompleteStudents) > 0): ?>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="student-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Year</th>
                                    <th>Type</th>
                                    <th>Missing Documents</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incompleteStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td>
                                            <?php 
                                                echo htmlspecialchars($student['first_name'] . ' ');
                                                if (!empty($student['middle_name'])) {
                                                    echo htmlspecialchars($student['middle_name'][0] . '. ');
                                                }
                                                echo htmlspecialchars($student['last_name']);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['course']); ?></td>
                                        <td><?php echo htmlspecialchars($student['year_level']); ?></td>
                                        <td>
                                            <span class="student-type <?php echo $student['student_type'] === 'Regular' ? 'type-regular' : 'type-transferee'; ?>">
                                                <?php echo htmlspecialchars($student['student_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php foreach ($student['missing_docs'] as $doc): ?>
                                                <span class="badge-missing"><?php echo htmlspecialchars($doc['doc_name']); ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['date_added'])); ?></td>
                                        <td>
                                            <button class="update-btn" onclick="openUpdateModal('<?php echo $student['student_type']; ?>', <?php echo $student['id']; ?>, '<?php echo $student['student_id']; ?>', '<?php echo htmlspecialchars(json_encode($student['missing_docs'])); ?>')">
                                                <i class="fas fa-upload"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Export Button -->
                <div class="export-container">
                    <button id="exportExcel" class="export-btn">
                        <i class="fas fa-file-export"></i> Export to Excel
                    </button>
                </div>
            <?php else: ?>
               <div class="empty-state">
                   <i class="fas fa-check-circle"></i>
                   <h4>No students with lacking documents</h4>
                   <p>All students have submitted their required documents.</p>
               </div>
           <?php endif; ?>
        </div>
    </div>
    
    <!-- Update Documents Modal -->
    <div class="modal fade" id="updateDocumentsModal" tabindex="-1" aria-labelledby="updateDocumentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateDocumentsModalLabel">
                        <i class="fas fa-file-upload me-2"></i>
                        Update Student Documents
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateDocumentsForm" method="POST" enctype="multipart/form-data" action="<?php echo $isStaffView ? '../AdminAccount/lackingofdoc_logic.php' : 'lackingofdoc_logic.php'; ?>">
                    <div class="modal-body">
                        <div class="mb-4">
                            <h6 class="fw-bold">Student ID: <span id="modalStudentId"></span></h6>
                            <p class="text-muted mb-0">Upload the missing documents below. Documents will be validated instantly.</p>
                        </div>
                        
                        <div id="documentsList" class="mt-4">
                            <!-- Document items will be added here dynamically -->
                        </div>
                        
                        <input type="hidden" name="update_documents" value="1">
                        <input type="hidden" name="student_type" id="studentType">
                        <input type="hidden" name="record_id" id="recordId">
                        <input type="hidden" name="student_id" id="studentIdInput">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                    <input type="hidden" name="is_staff_view" value="<?php echo $isStaffView ? 'true' : 'false'; ?>">
                </form>
            </div>
        </div>
    </div>

    <!-- Staff Profile Modal (only for staff view) -->
    <?php if ($isStaffView): ?>
        <?php include '../StaffAccount/staffprofile.php'; ?>
    <?php endif; ?>

    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../bootstrap/js/xlsx.full.min.js"></script>
    
    <!-- Main lackingofdoc functionality -->
    <script src="<?php echo $isStaffView ? '../AdminAccount/' : ''; ?>js/lackingofdoc.js"></script>
    
    <!-- Sidebar notifications -->
    <?php if ($isStaffView): ?>
    <script src="../StaffAccount/js/sidebar-notifications.js"></script>
    <?php else: ?>
    <script src="js/sidebar-notifications.js"></script>
    <?php endif; ?>
    
    <!-- PHP-injected SweetAlert messages -->
    <script>
        // Set flags for the external JS to know if PHP already handled the alert
        window.phpAlertShown = <?php echo (isset($updateSuccess) || isset($updateError)) ? 'true' : 'false'; ?>;
        window.isStaffView = <?php echo $isStaffView ? 'true' : 'false'; ?>;
        
        document.addEventListener("DOMContentLoaded", function () {
            // Check for success/error messages in session and show SweetAlert
            <?php if (isset($updateSuccess) && $updateSuccess): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Upload Successful!',
                    html: '<?php echo addslashes(str_replace("\n", "<br>", $updateMessage)); ?>',
                    confirmButtonColor: '#800000',
                    timer: 5000,
                    timerProgressBar: true
                }).then(() => {
                    // Reload the page to refresh the data
                    window.location.href = '<?php echo $isStaffView ? "../StaffAccount/stafflackingdoc.php" : "lackingofdoc.php"; ?>';
                });
            <?php endif; ?>

            <?php if (isset($updateError) && !empty($updateError)): ?>
                <?php if ($errorType === 'duplicate'): ?>
                    // Special handling for duplicate file errors
                    Swal.fire({
                        icon: 'warning',
                        title: 'Duplicate File Detected',
                        html: `
                            <div style="text-align: left; padding: 1rem;">
                                <p style="margin-bottom: 1rem; font-weight: 600; color: #856404;">
                                    The following file(s) have already been uploaded:
                                </p>
                                <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; border-radius: 4px;">
                                    <?php echo nl2br(str_replace("Duplicate file(s) detected:\n\n", "", addslashes($updateError))); ?>
                                </div>
                                <p style="margin-top: 1rem; color: #666; font-size: 0.95rem;">
                                    <i class="fas fa-info-circle"></i> Please verify that you're uploading the correct file or remove the duplicate from the system first.
                                </p>
                            </div>
                        `,
                        confirmButtonColor: '#800000',
                        confirmButtonText: 'I Understand',
                        width: '600px',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    });
                <?php else: ?>
                    // Regular error handling
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        html: '<?php echo addslashes(str_replace("\n", "<br>", $updateError)); ?>',
                        confirmButtonColor: '#800000'
                    });
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Only close connection if we're not in staff view
if (!$isStaffView && $conn) {
    $conn->close();
}
ob_end_flush();
?>