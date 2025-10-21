<?php
// At the top of your file, after session_start()

// Define upload directory
define('UPLOAD_DIR', __DIR__ . '/uploads/ids/');

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        die(json_encode(['status' => 'error', 'message' => 'Failed to create upload directory']));
    }
}

// In your file upload handling section, update to:
if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['id_document']['tmp_name'];
    $fileName = $_FILES['id_document']['name'];
    $fileSize = $_FILES['id_document']['size'];
    $fileType = $_FILES['id_document']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    
    // Allowed extensions
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (in_array($fileExtension, $allowedExts)) {
        // Generate unique filename
        $newFileName = 'id_' . uniqid() . '.' . $fileExtension;
        $uploadPath = UPLOAD_DIR . $newFileName;
        
        // Move file
        if (move_uploaded_file($fileTmpPath, $uploadPath)) {
            // Store relative path in database
            $id_document = 'uploads/ids/' . $newFileName;
            
            // Log success
            error_log("✅ File uploaded successfully: " . $uploadPath);
            error_log("📁 Database path: " . $id_document);
        } else {
            throw new Exception('Failed to move uploaded file');
        }
    } else {
        throw new Exception('Invalid file type. Allowed: JPG, JPEG, PNG, PDF');
    }
}

// ...rest of your code...
?>