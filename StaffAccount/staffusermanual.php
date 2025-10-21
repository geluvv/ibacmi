<?php
// Include database connection
// Include database connection
require_once '../db_connect.php';

// Start session
session_start();

// Check if user is logged in and is a staff member
if (!isset($_SESSION['staff_user_id'])) {
    header("Location: stafflogin.html");
    exit();
}

// Fetch staff information - IMPROVED QUERY
$staffInfo = array();
$userId = $_SESSION['staff_user_id'];

// First, get data from staff_users table
$stmt = $conn->prepare("SELECT username, email, role, first_name, last_name FROM staff_users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $staffInfo = $userData;
}
$stmt->close();

// Then, get profile data from staff_profiles table (if exists)
$stmt = $conn->prepare("SELECT * FROM staff_profiles WHERE staff_user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $profileData = $result->fetch_assoc();
    // Merge profile data, giving priority to profile table (this includes profile_picture!)
    $staffInfo = array_merge($staffInfo, $profileData);
}
$stmt->close();

// Ensure we have default values
if (empty($staffInfo['first_name'])) {
    $staffInfo['first_name'] = explode(' ', $staffInfo['username'] ?? 'Staff')[0];
}
if (empty($staffInfo['last_name'])) {
    $staffInfo['last_name'] = explode(' ', $staffInfo['username'] ?? 'Member')[1] ?? 'Member';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBACMI - User Manual</title>
    <!-- Replace CDN with local -->
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../AdminAccount/css/sidebar.css" rel="stylesheet">
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
        
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        /* Updated Manual Content to match other pages */
        .manual-header {
            background: linear-gradient(135deg, var(--primary) 0%, #a00000 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
        }

        .manual-header h1 {
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .manual-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .section-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 1.4rem;
        }
        
        .section-title i {
            margin-right: 12px;
            font-size: 1.6rem;
        }
        
        .feature-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .feature-item:last-child {
            border-bottom: none;
        }
        
        .feature-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .step-number {
            background-color: var(--primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 12px;
        }
        
        .alert-info {
            background-color: #e3f2fd;
            border-color: #2196f3;
            color: #1976d2;
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
            include 'staffsidebar.php'; 
            renderSidebar('usermanual', 'staff', $staffInfo); 
        ?>
        
        <div class="main-content">
            <div class="manual-header">
                <h1><i class="fas fa-book me-3"></i>User Manual</h1>
                <p class="mb-0 fs-5">IBACMI Registrar Document Online Data Bank</p>
                <p class="mb-0">Staff Guide</p>
            </div>

            <!-- Getting Started -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-play-circle"></i>
                    Getting Started
                </h2>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Welcome to the IBACMI Registrar Document Online Data Bank. This system helps manage student records, documents, and administrative tasks efficiently.
                </div>
                <p>After successful login, administrators are directed to the Dashboard which provides an overview of the system with student charts and activity logs.</p>
            </div>

            <!-- Dashboard -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-th-large"></i>
                    Dashboard
                </h2>
                <p>The Dashboard is your main control center that displays:</p>
                <div class="feature-item">
                    <div class="feature-title">Student Statistics Charts</div>
                    <p>Visual representation of student enrollment, document completion rates, and other key metrics.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-title">Activity Logs</div>
                    <p>Recent system activities, document uploads, and user actions for monitoring purposes.</p>
                </div>
            </div>

            <!-- Student Records -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Student Records
                </h2>
                <p>Access and manage all student records through two main categories:</p>
                <div class="feature-item">
                    <div class="feature-title">Regular Students</div>
                    <p>View comprehensive list of all regular enrolled students with their complete information and document status.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-title">Transferee Students</div>
                    <p>Separate section for transfer students with their previous institution records and current status.</p>
                </div>
                <p class="mt-3"><strong>Features include:</strong> Search functionality, filtering options, document status tracking, and detailed student profiles.</p>
            </div>

            <!-- Student Management -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-user-graduate"></i>
                    Student Management
                </h2>
                <p>Add and manage student information through the dropdown menu options:</p>
                
                <h5 class="mt-4 mb-3"><i class="fas fa-user-plus me-2"></i>Add New Student</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><span class="step-number">1</span>Fill out student personal information</p>
                        <p><span class="step-number">2</span>Upload required scanned documents</p>
                        <p><span class="step-number">3</span>Verify information accuracy</p>
                    </div>
                    <div class="col-md-6">
                        <p><span class="step-number">4</span>Submit for processing</p>
                        <p><span class="step-number">5</span>Generate student ID and records</p>
                    </div>
                </div>

                <h5 class="mt-4 mb-3"><i class="fas fa-user-plus me-2"></i>Add Transferee</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><span class="step-number">1</span>Enter previous institution details</p>
                        <p><span class="step-number">2</span>Upload transfer credentials</p>
                        <p><span class="step-number">3</span>Add current enrollment information</p>
                    </div>
                    <div class="col-md-6">
                        <p><span class="step-number">4</span>Verify document authenticity</p>
                        <p><span class="step-number">5</span>Complete transfer process</p>
                    </div>
                </div>
            </div>

            <!-- Document Management -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-folder-open"></i>
                    Document Management
                </h2>
                
                <div class="feature-item">
                    <div class="feature-title"><i class="fas fa-exclamation-circle me-2"></i>Lacking Documents</div>
                    <p>View and manage list of students who are missing required documents. Features include:</p>
                    <ul>
                        <li>Detailed list of missing documents per student</li>
                        <li>Update Status anytime</li>
                    </ul>
                </div>

                <div class="feature-item">
                    <div class="feature-title"><i class="fas fa-clipboard-check me-2"></i>Complete Documents</div>
                    <p>Monitor students who have submitted all required documents:</p>
                    <ul>
                        <li>Generate completion certificates</li>
                    </ul>
                </div>
            </div>

            <!-- System Features -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-cogs"></i>
                    Additional System Features
                </h2>
                
                <div class="feature-item">
                    <div class="feature-title"><i class="fas fa-cloud-upload-alt me-2"></i>Backup System</div>
                    <p>Secure data backup and recovery options to ensure data integrity and system reliability.</p>
                </div>

                <div class="feature-item">
                    <div class="feature-title"><i class="fas fa-search me-2"></i>Search & Filter</div>
                    <p>Advanced search capabilities across all modules to quickly locate specific students or documents.</p>
                </div>
            </div>

            <!-- Support -->
            <div class="manual-section">
                <h2 class="section-title">
                    <i class="fas fa-question-circle"></i>
                    Need Help?
                </h2>
                <div class="alert alert-info">
                    <p class="mb-2"><strong>For technical support or questions:</strong></p>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i>Email: itsbo2024@gmail.com</p>
                    <p class="mb-1"><i class="fas fa-phone me-2"></i>Phone: 09609012716</p>
                    <p class="mb-0"><i class="fas fa-clock me-2"></i>Support Hours: Monday-Friday, 11:00 AM - 3:00 PM</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the modern profile modal -->
    <?php include 'staffprofile.php'; ?>

    <?php 
    // Close connection after all includes that need it
    $conn->close(); 
    ?>

    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>