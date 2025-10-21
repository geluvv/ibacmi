<?php
// CRITICAL: No output before this point - not even whitespace or BOM
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Start output buffering IMMEDIATELY
ob_start();

// Increase file upload limits
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_file_uploads', '20');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');

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

// Include the document validator (SERVER-SIDE)
require_once __DIR__ . '/document_validator.php';

// Include auto-sync trigger
require_once __DIR__ . '/../includes/trigger_auto_sync.php';

// Change timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check authentication based on view type
if (!$isStaffView) {
    // Admin authentication check
    if (!isset($_SESSION['user_id'])) {
        // If this is an AJAX request, return JSON error instead of redirecting
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'type' => 'authentication_required',
                'message' => 'You must be logged in to perform this action.'
            ]);
            exit();
        }
        // For regular requests, redirect to login
        header("Location: ../login.html");
        exit();
    }
}

error_log("=== NEWSTUDENT.PHP LOADED ===");
error_log("POST data available: " . (isset($_POST) && count($_POST) > 0 ? 'YES' : 'NO'));
error_log("Is Staff View: " . ($isStaffView ? 'YES' : 'NO'));

// Helper function to format file size
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Helper function to get document type ID by code
function getDocumentTypeId($conn, $docCode) {
    $sql = "SELECT id FROM document_types WHERE doc_code = ? AND is_active = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Failed to prepare statement for document type lookup: " . $conn->error);
        return null;
    }

    $stmt->bind_param("s", $docCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        error_log("Found existing document type ID: " . $row['id'] . " for " . $docCode);
        return $row['id'];
    }

    $stmt->close();
    error_log("Document type not found for code: " . $docCode);
    return null;
}

// Enhanced duplicate checking function
function checkDuplicateStudent($conn, $studentID, $firstName, $lastName) {
    $sql = "SELECT student_id, first_name, last_name FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return ['isDuplicate' => false, 'message' => ''];
    }

    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'isDuplicate' => true,
            'message' => "Student ID '{$studentID}' already exists for {$row['first_name']} {$row['last_name']}"
        ];
    }
    $stmt->close();

    $sql = "SELECT student_id, first_name, last_name FROM students
            WHERE LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return ['isDuplicate' => false, 'message' => ''];
    }

    $stmt->bind_param("ss", $firstName, $lastName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'isDuplicate' => true,
            'message' => "A student named {$firstName} {$lastName} (ID: {$row['student_id']}) already exists in the system"
        ];
    }
    $stmt->close();

    return ['isDuplicate' => false, 'message' => ''];
}

// Duplicate file checker
function checkDuplicateFile($conn, $fileName, $fileSize, $fileType) {
    $sql = "SELECT sd.id, sd.file_name, s.student_id, s.first_name, s.last_name
            FROM student_documents sd
            JOIN students s ON sd.student_id = s.id
            WHERE sd.original_filename = ?
            AND sd.file_size = ?
            AND sd.file_type = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for duplicate file check: " . $conn->error);
        return ['isDuplicate' => false, 'message' => ''];
    }

    $stmt->bind_param("sis", $fileName, $fileSize, $fileType);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'isDuplicate' => true,
            'message' => "File '{$fileName}' already exists for student {$row['first_name']} {$row['last_name']} (ID: {$row['student_id']})",
            'student_info' => $row
        ];
    }

    $stmt->close();
    return ['isDuplicate' => false, 'message' => ''];
}

// VALIDATION ENDPOINT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'validate_document') {
    ob_clean();
    header('Content-Type: application/json');

    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode([
                'valid' => false,
                'message' => 'File upload error',
                'error' => 'No file was uploaded or upload failed'
            ]);
            exit();
        }

        $fileName = $_FILES['file']['name'];
        $docType = $_POST['doc_type'] ?? 'unknown';

        error_log("=== VALIDATION REQUEST for $docType: $fileName ===");

        $validation = validateDocumentType($_FILES['file'], $docType);

        error_log("Document validation result: " . json_encode($validation));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($validation, JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        error_log("Validation endpoint error: " . $e->getMessage());
        echo json_encode([
            'valid' => false,
            'message' => 'Error during validation',
            'error' => $e->getMessage()
        ]);
    }
    exit();
}

// Define upload directory
$absolute_upload_dir = dirname(__DIR__) . "/uploads/";
if (!file_exists($absolute_upload_dir)) {
    if (!mkdir($absolute_upload_dir, 0755, true)) {
        error_log("Failed to create upload directory: " . $absolute_upload_dir);
    }
}

// Main form processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    error_log("=== MAIN FORM PROCESSING STARTED ===");

    $conn->begin_transaction();

    try {
        $studentID = trim($_POST['studentID']);
        $firstName = trim($_POST['firstName']);
        $middleName = trim($_POST['middleName']);
        $lastName = trim($_POST['lastName']);
        $course = trim($_POST['studentCourse']);
        $yearLevel = (int)$_POST['yearLevel'];

        error_log("Student data - ID: $studentID, Name: $firstName $lastName");

        $duplicateCheck = checkDuplicateStudent($conn, $studentID, $firstName, $lastName);
        if ($duplicateCheck['isDuplicate']) {
            throw new Exception($duplicateCheck['message']);
        }

        $marriageCertRequired = isset($_POST['marriageRequired']) && $_POST['marriageRequired'] == '1' ? 1 : 0;

        $sql = "INSERT INTO students (student_id, first_name, middle_name, last_name, course, year_level, student_type, marriage_cert_required, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Regular', ?, 'incomplete')";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Failed to prepare student insert: " . $conn->error);
        }

        $stmt->bind_param("sssssii", $studentID, $firstName, $middleName, $lastName, $course, $yearLevel, $marriageCertRequired);

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert student: " . $stmt->error);
        }

        $studentDbId = $conn->insert_id;
        $stmt->close();

        error_log("Student inserted successfully. DB ID: $studentDbId");

        $uploadedDocs = [];
        $uploadErrors = [];

        $docTypeMapping = [
            'card138' => 'card138',
            'moralCert' => 'moral',
            'birthCert' => 'birth',
            'marriageCert' => 'marriage',
            'idPhoto' => 'id'
        ];

        foreach ($docTypeMapping as $fieldName => $docCode) {
            error_log("Processing field: $fieldName (doc code: $docCode)");

            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fieldName];
                error_log("File found: " . $file['name'] . " (size: " . $file['size'] . ")");

                $duplicateFileCheck = checkDuplicateFile($conn, $file['name'], $file['size'], $file['type']);
                if ($duplicateFileCheck['isDuplicate']) {
                    throw new Exception($duplicateFileCheck['message']);
                }

                $docTypeId = getDocumentTypeId($conn, $docCode);

                if (!$docTypeId) {
                    error_log("Document type not found for code: $docCode");
                    $uploadErrors[] = "Document type '$docCode' not found in system";
                    continue;
                }

                error_log("Document type ID: $docTypeId");

                $validation = validateDocumentType($file, $docCode);

                $allowedTypes = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
                $fileExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

                if (!in_array($fileExt, $allowedTypes)) {
                    $uploadErrors[] = "Invalid file type for $docCode";
                    continue;
                }

                $year = date('Y');
                $studentFolder = preg_replace('/[^\w\s-]/', '', $firstName . '_' . $lastName);
                $uploadPath = dirname(__DIR__) . "/uploads/{$year}/{$studentFolder}/";

                if (!is_dir($uploadPath)) {
                    if (!mkdir($uploadPath, 0755, true)) {
                        error_log("Failed to create directory: $uploadPath");
                        $uploadErrors[] = "Failed to create upload directory for $docCode";
                        continue;
                    }
                }

                $newFileName = $studentDbId . '_' . $docTypeId . '_' . time() . '.' . $fileExt;
                $fullPath = $uploadPath . $newFileName;
                $relativePath = "uploads/{$year}/{$studentFolder}/" . $newFileName;

                error_log("Attempting to move file to: $fullPath");

                if (!move_uploaded_file($file["tmp_name"], $fullPath)) {
                    error_log("Failed to move uploaded file");
                    $uploadErrors[] = "Failed to upload $docCode";
                    continue;
                }

                error_log("File moved successfully");

                $validationStatus = $validation['valid'] ? 'valid' : 'needs_review';
                $validationConfidence = isset($validation['confidence']) ? (int)$validation['confidence'] : 0;

                $insertSql = "INSERT INTO student_documents
                              (student_id, document_type_id, file_name, file_path, original_filename,
                               file_size, file_type, validation_status, validation_confidence, is_submitted, submission_date)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

                $insertStmt = $conn->prepare($insertSql);
                if (!$insertStmt) {
                    error_log("Failed to prepare document insert: " . $conn->error);
                    unlink($fullPath);
                    $uploadErrors[] = "Database error for $docCode";
                    continue;
                }

                $insertStmt->bind_param("iissssssi",
                    $studentDbId,
                    $docTypeId,
                    $newFileName,
                    $relativePath,
                    $file["name"],
                    $file["size"],
                    $file["type"],
                    $validationStatus,
                    $validationConfidence
                );

                if (!$insertStmt->execute()) {
                    error_log("Failed to insert document record: " . $insertStmt->error);
                    $insertStmt->close();
                    unlink($fullPath);
                    $uploadErrors[] = "Failed to save $docCode record";
                    continue;
                }

                $documentId = $conn->insert_id;
                error_log("Document record inserted successfully. Document ID: $documentId");
                $insertStmt->close();
                
                // ✅ TRIGGER AUTO-SYNC after document insertion
                $checkSyncSql = "SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sync_status'";
                $syncResult = $conn->query($checkSyncSql);
                
                if ($syncResult && $syncResult->num_rows > 0) {
                    $syncRow = $syncResult->fetch_assoc();
                    if ($syncRow['setting_value'] === 'enabled') {
                        error_log("🚀 Triggering auto-sync for student $studentDbId, document $documentId");
                        
                        // Call auto_sync_processor.php asynchronously
                        $autoSyncUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . 
                                      '://' . $_SERVER['HTTP_HOST'] . 
                                      dirname($_SERVER['SCRIPT_NAME']) . '/auto_sync_processor.php';
                        
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $autoSyncUrl,
                            CURLOPT_POST => true,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT_MS => 500,
                            CURLOPT_NOSIGNAL => 1,
                            CURLOPT_POSTFIELDS => http_build_query([
                                'trigger' => 'document_upload',
                                'student_id' => $studentDbId,
                                'document_id' => $documentId
                            ])
                        ]);
                        
                        curl_exec($ch);
                        $curlError = curl_error($ch);
                        curl_close($ch);
                        
                        if ($curlError) {
                            error_log("⚠️ Auto-sync trigger warning: " . $curlError);
                        } else {
                            error_log("✅ Auto-sync triggered successfully");
                        }
                    }
                }

                if (!$validation['valid']) {
                    $uploadedDocs[] = $docCode . " (needs review)";
                } else {
                    $uploadedDocs[] = $docCode;
                }
                
                // ✅ NEW: Trigger auto-sync for this newly uploaded document
                triggerAutoSyncNow($studentDbId, $documentId);
            } else {
                error_log("No file uploaded for $fieldName");
            }
        }

        $conn->commit();

        error_log("=== TRANSACTION COMMITTED SUCCESSFULLY ===");
        error_log("Uploaded docs: " . implode(', ', $uploadedDocs));

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Student registered successfully',
            'student_id' => $studentDbId,
            'uploaded_documents' => $uploadedDocs,
            'upload_results' => $uploadedDocs,
            'upload_errors' => $uploadErrors,
            'errors' => $uploadErrors
        ]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("ERROR: " . $e->getMessage());

        ob_clean();
        header('Content-Type: application/json');

        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo json_encode([
                'status' => 'error',
                'type' => 'duplicate_id',
                'message' => $e->getMessage()
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit();
    }
}

// If we reach here, display the HTML form
ob_clean();

// Load the HTML content
$htmlContent = file_get_contents(__DIR__ . '/newstudent.html');

// Extract head content (styles and meta tags)
$headStyles = '';
$headLinks = '';
if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $htmlContent, $headMatches)) {
    // Extract styles
    if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $headMatches[1], $styleMatches)) {
        foreach ($styleMatches[0] as $style) {
            $headStyles .= $style . "\n";
        }
    }
    // Extract link tags (excluding already included ones)
    if (preg_match_all('/<link[^>]*>/is', $headMatches[1], $linkMatches)) {
        foreach ($linkMatches[0] as $link) {
            if (strpos($link, 'bootstrap') === false && 
                strpos($link, 'font-awesome') === false && 
                strpos($link, 'sidebar.css') === false) {
                $headLinks .= $link . "\n";
            }
        }
    }
}

// Extract body content
$bodyContent = '';
if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $htmlContent, $bodyMatches)) {
    $bodyContent = $bodyMatches[1];
    
    // Remove ONLY the sidebar nav element (keep the rest of the structure intact)
    $bodyContent = preg_replace('/<nav\s+class=["\']sidebar["\'][^>]*>.*?<\/nav>/is', '', $bodyContent);
    
    // FIX: Update form action based on context
    if ($isStaffView) {
        $bodyContent = str_replace('action="newstudent.php"', 'action="staffnewstudent.php"', $bodyContent);
        $bodyContent = str_replace("action='newstudent.php'", "action='staffnewstudent.php'", $bodyContent);
    }
}

// Extract ALL scripts EXCEPT inline ones (we'll use external newstudent.js instead)
$externalScripts = '';
if (preg_match_all('/<script[^>]+src=[^>]+><\/script>/is', $htmlContent, $scriptMatches)) {
    foreach ($scriptMatches[0] as $script) {
        // Skip already included libraries
        if (strpos($script, 'tesseract') === false && 
            strpos($script, 'pdf.js') === false && 
            strpos($script, 'mammoth') === false &&
            strpos($script, 'sweetalert') === false &&
            strpos($script, 'sidebar-notifications') === false) {
            $externalScripts .= $script . "\n";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isStaffView ? 'New Student - Staff' : 'New Student - Admin'; ?> - IBACMI</title>
    
    <!-- Core CSS Libraries -->
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
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
    
    <!-- Shared Form Styles -->
    <link rel="stylesheet" href="../shared/form_styles.css">
    
    <!-- Additional links from HTML -->
    <?php echo $headLinks; ?>
    
    <!-- Page-specific styles from HTML -->
    <?php echo $headStyles; ?>
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
        // Render appropriate sidebar based on view type
        if ($isStaffView) {
            include '../StaffAccount/staffsidebar.php';
            renderSidebar('newstudent', 'staff', $staffInfo);
        } else {
            include 'sidebar.php';
            renderSidebar('newstudent');
        }
        ?>
        
        <!-- Main content from HTML file -->
        <?php echo $bodyContent; ?>
    </div>

    <!-- Staff Profile Modal (only for staff view) -->
    <?php if ($isStaffView): ?>
        <?php include '../StaffAccount/staffprofile.php'; ?>
    <?php endif; ?>

    <!-- Core JavaScript -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- All external scripts from the HTML file -->
    <?php echo $externalScripts; ?>
    
    <!-- Main newstudent functionality -->
    <script src="<?php echo $isStaffView ? '../AdminAccount/' : ''; ?>js/newstudent.js"></script>
    
    <!-- Sidebar notifications -->
    <?php if ($isStaffView): ?>
    <script src="../StaffAccount/js/sidebar-notifications.js"></script>
    <?php else: ?>
    <script src="js/sidebar-notifications.js"></script>
    <?php endif; ?>
</body>
</html>

<?php
// Only close connection if we're not in staff view
if (!$isStaffView && $conn) {
    $conn->close();
}
ob_end_flush();
?>