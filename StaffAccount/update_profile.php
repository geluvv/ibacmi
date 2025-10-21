<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once '../db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['staff_user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit();
}

$staffUserId = $_SESSION['staff_user_id'];

try {
    // Get form data
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'First name, last name, and email are required'
        ]);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    // Handle profile picture upload
    $profilePicturePath = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        // Use absolute path for upload directory
        $uploadDir = __DIR__ . '/../uploads/staff_profiles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to create upload directory'
                ]);
                exit();
            }
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $fileType = $_FILES['profile_picture']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed'
            ]);
            exit();
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            echo json_encode([
                'status' => 'error',
                'message' => 'File size too large. Maximum 5MB allowed'
            ]);
            exit();
        }
        
        // Generate unique filename
        $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = 'staff_' . $staffUserId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            // Store relative path for database (from StaffAccount folder)
            $profilePicturePath = '../uploads/staff_profiles/' . $filename;
            
            // Delete old profile picture if exists
            $stmt = $conn->prepare("SELECT profile_picture FROM staff_profiles WHERE staff_user_id = ?");
            $stmt->bind_param("i", $staffUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['profile_picture'])) {
                    $oldFilePath = __DIR__ . '/' . $row['profile_picture'];
                    if (file_exists($oldFilePath)) {
                        @unlink($oldFilePath);
                    }
                }
            }
            $stmt->close();
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to upload profile picture. Error code: ' . $_FILES['profile_picture']['error']
            ]);
            exit();
        }
    }
    
    // Check if profile exists
    $stmt = $conn->prepare("SELECT id FROM staff_profiles WHERE staff_user_id = ?");
    $stmt->bind_param("i", $staffUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileExists = $result->num_rows > 0;
    $stmt->close();
    
    // Begin transaction for data consistency
    $conn->begin_transaction();
    
    try {
        if ($profileExists) {
            // Update existing profile
            if ($profilePicturePath !== null) {
                // Update with new profile picture
                $sql = "UPDATE staff_profiles SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        birthday = ?, 
                        email = ?, 
                        phone = ?, 
                        address = ?, 
                        department = ?, 
                        position = ?,
                        profile_picture = ?, 
                        updated_at = NOW()
                        WHERE staff_user_id = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("ssssssssssi", 
                    $firstName, $middleName, $lastName, 
                    $birthday, $email, $phone, 
                    $address, $department, $position,
                    $profilePicturePath, $staffUserId
                );
            } else {
                // Update without changing profile picture
                $sql = "UPDATE staff_profiles SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        birthday = ?, 
                        email = ?, 
                        phone = ?, 
                        address = ?, 
                        department = ?, 
                        position = ?,
                        updated_at = NOW()
                        WHERE staff_user_id = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssssssssi", 
                    $firstName, $middleName, $lastName, 
                    $birthday, $email, $phone, 
                    $address, $department, $position,
                    $staffUserId
                );
            }
        } else {
            // Insert new profile
            $sql = "INSERT INTO staff_profiles 
                    (staff_user_id, first_name, middle_name, last_name, 
                     birthday, email, phone, address, department, position, 
                     profile_picture, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("issssssssss", 
                $staffUserId, $firstName, $middleName, $lastName, 
                $birthday, $email, $phone, $address, $department, $position,
                $profilePicturePath
            );
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
        
        // Also update first_name, last_name, and email in staff_users table
        $updateUser = $conn->prepare("UPDATE staff_users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        if (!$updateUser) {
            throw new Exception("Prepare user update failed: " . $conn->error);
        }
        $updateUser->bind_param("sssi", $firstName, $lastName, $email, $staffUserId);
        if (!$updateUser->execute()) {
            throw new Exception("Execute user update failed: " . $updateUser->error);
        }
        $updateUser->close();
        
        // Commit transaction
        $conn->commit();
        
        // Fetch updated profile data
        $stmt = $conn->prepare("
            SELECT 
                sp.first_name, sp.middle_name, sp.last_name, 
                sp.email, sp.position, sp.profile_picture,
                su.role 
            FROM staff_profiles sp
            LEFT JOIN staff_users su ON sp.staff_user_id = su.id
            WHERE sp.staff_user_id = ?
        ");
        $stmt->bind_param("i", $staffUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();
        
        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'first_name' => $profileData['first_name'] ?? $firstName,
                'middle_name' => $profileData['middle_name'] ?? $middleName,
                'last_name' => $profileData['last_name'] ?? $lastName,
                'email' => $profileData['email'] ?? $email,
                'position' => $profileData['position'] ?? $position,
                'profile_picture' => $profileData['profile_picture'] ?? $profilePicturePath
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while updating profile: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>