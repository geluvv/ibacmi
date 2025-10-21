<?php
// Ensure error reporting is enabled during development
error_reporting(E_ALL);
ini_set("display_errors", 1);

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

// Define the document upload directory
$upload_dir = "../uploads/";

// Check if uploads directory exists and is writable
if (!file_exists($upload_dir)) {
    error_log("Creating uploads directory: $upload_dir");
    mkdir($upload_dir, 0755, true);
} elseif (!is_writable($upload_dir)) {
    error_log("Warning: Uploads directory is not writable: $upload_dir");
}

// Ensure the directory exists
if (!file_exists($upload_dir) && !is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get unique courses for the filter dropdown with error handling
$sql_courses = "SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' ORDER BY course";
$result_courses = $conn->query($sql_courses);
if (!$result_courses) {
    die("Error fetching courses: " . $conn->error);
}

// Store courses in an array for reuse
$courses = [];
if ($result_courses && $result_courses->num_rows > 0) {
    while ($course = $result_courses->fetch_assoc()) {
        $courses[] = $course['course'];
    }
}

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page_transferees = isset($_GET['page_transferees']) ? (int)$_GET['page_transferees'] : 1;
$limit = 25;

// Get regular students with pagination
$offset = ($page - 1) * $limit;
$sql_regular = "
    SELECT id, student_id, last_name, first_name, middle_name, course, year_level
    FROM students
    WHERE student_type = 'Regular'
    ORDER BY date_added DESC
    LIMIT $limit OFFSET $offset
";
$result_regular = $conn->query($sql_regular);
if (!$result_regular) {
    die("Error fetching regular students: " . $conn->error);
}

// Get transferees with pagination
$offset_transferees = ($page_transferees - 1) * $limit;
$sql_transferees = "
    SELECT id, student_id, last_name, first_name, middle_name, course, year_level
    FROM students
    WHERE student_type = 'Transferee'
    ORDER BY date_added DESC
    LIMIT $limit OFFSET $offset_transferees
";
$result_transferees = $conn->query($sql_transferees);
if (!$result_transferees) {
    die("Error fetching transferees: " . $conn->error);
}

// Calculate total pages for pagination
$sql_count_regular = "SELECT COUNT(*) as total FROM students WHERE student_type = 'Regular'";
$result_count_regular = $conn->query($sql_count_regular);
$row_count_regular = $result_count_regular->fetch_assoc();
$total_pages_regular = ceil($row_count_regular['total'] / $limit);

$sql_count_transferees = "SELECT COUNT(*) as total FROM students WHERE student_type = 'Transferee'";
$result_count_transferees = $conn->query($sql_count_transferees);
$row_count_transferees = $result_count_transferees->fetch_assoc();
$total_pages_transferees = ceil($row_count_transferees['total'] / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - IBACMI Document Databank</title>

     <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png"><!-- Replace CDN with local -->
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php if ($isStaffView): ?>
        <link href="../AdminAccount/css/sidebar.css" rel="stylesheet">
    <?php else: ?>
        <link href="css/sidebar.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        :root {
            --primary: #800000;
            --primary-light: #a53f3f;
            --primary-hover: #6a0000;
            --sidebar-bg: #ffffff;
            --text-dark: #343a40;
            --transition: all 0.3s ease;
            --border-radius: 12px;
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

        /* Page Header Container */
        .page-header-container {
            background: linear-gradient(45deg, #800000, #a53f3f);
            color: white;
            border-radius: var(--border-radius);
            padding: 1.1rem 2rem;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .page-header-left h1 {
            margin: 0;
            font-weight: 700;
            font-size: 1.8rem;
            white-space: nowrap;
        }

        .page-header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .page-header-controls .filter-container {
            display: none;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: nowrap;
        }

        .page-header-controls .filter-container.active {
            display: flex;
        }

        .page-header-controls .search-container {
            position: relative;
            min-width: 200px;
        }

        .page-header-controls .search-container input {
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 0.8rem 0.5rem 2.2rem;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #333;
            transition: all 0.3s ease;
            width: 100%;
        }

        .page-header-controls .search-container input:focus {
            background: white;
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.2);
            outline: none;
        }

        .page-header-controls .search-container input::placeholder {
            color: #666;
        }

        .page-header-controls .search-container i {
            color: #800000;
            font-size: 0.9rem;
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
        }

        .page-header-controls .form-select {
            border-radius: 20px;
            padding: 0.5rem 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.9);
            min-width: 140px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #333;
            transition: all 0.3s ease;
        }

        .page-header-controls .form-select:focus {
            background: white;
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.2);
            outline: none;
        }
        
        /* Tab Navigation Updates */
        .tab-navigation-container {
            margin-bottom: 1.5rem;
        }

        .nav-tabs {
            border: none;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            padding: 0;
        }

        .nav-tabs .nav-link {
            border: 2px solid transparent;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            color: #800000; 
            background-color: #f8f9fa; 
            transition: all 0.3s ease;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            margin-right: 0.25rem;
            position: relative;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background-color: #e9ecef !important;
            color: #800000 !important; 
            border-color: #800000 !important; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(128, 0, 0, 0.2);
        }

        .nav-tabs .nav-link.active {
            color: white !important; 
            background-color: #800000 !important; 
            border-color: #800000 #800000 transparent !important;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3); 
            z-index: 10;
        }
        
        /* Table Container Styles */
        .table-container {
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            margin-top: 1rem;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background: linear-gradient(45deg, #800000, #a53f3f);
            color: white;
            padding: 1.2rem 1rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
        }

        .data-table thead th:first-child {
            border-top-left-radius: 15px;
        }

        .data-table thead th:last-child {
            border-top-right-radius: 15px;
        }

        .data-table td {
            padding: 1.2rem 1rem;
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table tbody tr:hover {
            background: #fff5f5;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.1);
        }

        /* Document Modal Styles */
        .document-modal .modal-header {
            background: linear-gradient(45deg, #800000, #a53f3f);
            color: white;
        }
        
        .document-modal .modal-title {
            font-weight: bold;
        }

        .badge.bg-maroon {
            background-color: #800000;
            color: white;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                background-color: white !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
        
        .print-only {
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .page-header-container {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem 1.5rem;
            }

            .page-header-controls {
                width: 100%;
                justify-content: center;
            }

            .page-header-controls .search-container {
                min-width: 180px;
            }

            .page-header-controls .form-select {
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header-left h1 {
                font-size: 1.5rem;
            }

            .page-header-controls .filter-container {
                flex-direction: column;
                gap: 0.6rem;
                width: 100%;
            }

            .page-header-controls .search-container,
            .page-header-controls .form-select {
                width: 100%;
                min-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
        if ($isStaffView) {
            include '../StaffAccount/staffsidebar.php';
            renderSidebar('students', 'staff', $staffInfo);
        } else {
            include 'sidebar.php';
            renderSidebar('document');
        }
        ?>
    
        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="page-header-container no-print">
                <div class="page-header-left">
                    <h1><i class="fas fa-folder-open me-2"></i> Student Records</h1>
                </div>
                <div class="page-header-controls">
                    <!-- Regular Students Controls (shown by default) -->
                    <div class="filter-container active" id="regular-controls">
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" id="regularStudentSearch" placeholder="Search students..." class="form-control">
                        </div>
                        <select id="regularStudentCourseFilter" class="form-select">
                            <option value="all">All Courses</option>
                            <?php
                            foreach ($courses as $course) {
                                echo "<option value=\"" . htmlspecialchars($course) . "\">" . 
                                     htmlspecialchars($course) . "</option>";
                            }
                            ?>
                        </select>
                        <select id="regularYearLevelFilter" class="form-select">
                            <option value="all">All Year Levels</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                            <option value="5">Year 5</option>
                        </select>
                    </div>

                    <!-- Transferees Controls (hidden by default) -->
                    <div class="filter-container" id="transferee-controls">
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" id="transfereeSearch" placeholder="Search transferees..." class="form-control">
                        </div>
                        <select id="transfereeCourseFilter" class="form-select">
                            <option value="all">All Courses</option>
                            <?php
                            foreach ($courses as $course) {
                                echo "<option value=\"" . htmlspecialchars($course) . "\">" . 
                                     htmlspecialchars($course) . "</option>";
                            }
                            ?>
                        </select>
                        <select id="transfereeYearLevelFilter" class="form-select">
                            <option value="all">All Year Levels</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                            <option value="5">Year 5</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation-container no-print">
                <ul class="nav nav-tabs" id="studentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="regular-students-tab" data-bs-toggle="tab" data-bs-target="#regular-students" type="button" role="tab" aria-controls="regular-students" aria-selected="true">
                            Regular Students
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transferees-tab" data-bs-toggle="tab" data-bs-target="#transferees" type="button" role="tab" aria-controls="transferees" aria-selected="false">
                            Transferees
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="studentTabsContent">
                <!-- Regular Students Tab -->
                <div class="tab-pane fade show active" id="regular-students" role="tabpanel" aria-labelledby="regular-students-tab">
                    <div class="table-container no-print">
                        <div class="table-responsive">
                            <table class="table data-table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Course</th>
                                        <th>Year Level</th>
                                        <th>Documents</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result_regular && $result_regular->num_rows > 0) {
                                        while ($row = $result_regular->fetch_assoc()) {
                                            $studentId = $row['student_id'];
                                            $studentDbId = $row['id'];
                                            
                                            echo "<tr data-course='" . htmlspecialchars($row['course']) . "'>
                                                    <td><strong>" . htmlspecialchars($studentId) . "</strong></td>
                                                    <td>" . htmlspecialchars($row['last_name']) . "</td>
                                                    <td>" . htmlspecialchars($row['first_name']) . "</td>
                                                    <td>" . htmlspecialchars($row['middle_name']) . "</td>
                                                    <td><span class='badge bg-light text-dark'>" . htmlspecialchars($row['course']) . "</span></td>
                                                    <td><span class='badge bg-maroon'>" . htmlspecialchars($row['year_level']) . "</span></td>
                                                    <td>
                                                        <button type='button' class='btn btn-primary btn-sm view-docs' 
                                                                data-bs-toggle='modal' 
                                                                data-bs-target='#documentModal' 
                                                                data-student-id='" . htmlspecialchars($studentDbId) . "' 
                                                                data-student-name='" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "'
                                                                data-student-type='Regular'>
                                                            <i class='fas fa-folder-open me-1'></i> View Documents
                                                        </button>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center text-muted py-4'>No regular students found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <nav aria-label="Page navigation" class="mt-4 no-print">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages_regular; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Transferees Tab -->
                <div class="tab-pane fade" id="transferees" role="tabpanel" aria-labelledby="transferees-tab">
                    <div class="table-container no-print">
                        <div class="table-responsive">
                            <table class="table data-table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Course</th>
                                        <th>Year Level</th>
                                        <th>Documents</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result_transferees && $result_transferees->num_rows > 0) {
                                        while ($row = $result_transferees->fetch_assoc()) {
                                            $studentId = $row['student_id'];
                                            $studentDbId = $row['id'];
                                            
                                            echo "<tr data-course='" . htmlspecialchars($row['course']) . "'>
                                                    <td><strong>" . htmlspecialchars($studentId) . "</strong></td>
                                                    <td>" . htmlspecialchars($row['last_name']) . "</td>
                                                    <td>" . htmlspecialchars($row['first_name']) . "</td>
                                                    <td>" . htmlspecialchars($row['middle_name']) . "</td>
                                                    <td><span class='badge bg-light text-dark'>" . htmlspecialchars($row['course']) . "</span></td>
                                                    <td><span class='badge bg-maroon'>" . htmlspecialchars($row['year_level']) . "</span></td>
                                                    <td>
                                                        <button type='button' class='btn btn-primary btn-sm view-docs' 
                                                                data-bs-toggle='modal' 
                                                                data-bs-target='#documentModal' 
                                                                data-student-id='" . htmlspecialchars($studentDbId) . "' 
                                                                data-student-name='" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "'
                                                                data-student-type='Transferee'>
                                                            <i class='fas fa-folder-open me-1'></i> View Documents
                                                        </button>
                                                    </td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center text-muted py-4'>No transferees found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <nav aria-label="Page navigation" class="mt-4 no-print">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages_transferees; $i++): ?>
                                    <li class="page-item <?php echo $i == $page_transferees ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page_transferees=<?php echo $i; ?>#transferees"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Document Modal -->
<div class="modal fade document-modal" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentModalLabel">Student Documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="documentList">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading documents...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="printAllDocuments">
                    <i class="fas fa-print me-2"></i> Print All Documents
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Template for All Documents -->
<div id="printAllTemplate" class="print-only">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h2>IBACMI Student Documents</h2>
                <h4 id="printStudentName"></h4>
                <p id="printStudentId"></p>
            </div>
        </div>
        <div id="printDocumentsContainer">
            <!-- Documents will be added here for printing -->
        </div>
    </div>
</div>

<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<?php if ($isStaffView): ?>
<script src="../AdminAccount/js/sidebar-notifications.js"></script>
<?php else: ?>
<script src="js/sidebar-notifications.js"></script>
<?php endif; ?>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Tab switching with control visibility
        const regularTab = document.getElementById('regular-students-tab');
        const transfereesTab = document.getElementById('transferees-tab');
        const regularControls = document.getElementById('regular-controls');
        const transfereeControls = document.getElementById('transferee-controls');

        // Function to switch tab controls
        function switchTabControls(activeTab) {
            if (activeTab === 'regular') {
                regularControls.classList.add('active');
                transfereeControls.classList.remove('active');
            } else {
                transfereeControls.classList.add('active');
                regularControls.classList.remove('active');
            }
        }

        // Handle tab clicks
        if (transfereesTab) {
            transfereesTab.addEventListener('click', function() {
                switchTabControls('transferee');
            });
        }

        if (regularTab) {
            regularTab.addEventListener('click', function() {
                switchTabControls('regular');
            });
        }
        
        // Document viewer functionality
        const documentModal = document.getElementById("documentModal");
        const documentList = document.getElementById("documentList");

        // Add event listener for all "View Documents" buttons
        document.querySelectorAll(".view-docs").forEach((button) => {
            button.addEventListener("click", function () {
                const studentId = this.getAttribute("data-student-id");
                const studentName = this.getAttribute("data-student-name");
                const studentType = this.getAttribute("data-student-type");

                // Store data in modal for later use
                documentModal.setAttribute("data-student-id", studentId);
                documentModal.setAttribute("data-student-name", studentName);
                documentModal.setAttribute("data-student-type", studentType);

                // Update modal title with student name
                document.getElementById("documentModalLabel").textContent = `Documents for ${studentName}`;

                // Show loading indicator
                documentList.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading documents...</p>
                    </div>
                `;

                // Fetch documents via AJAX
                fetchStudentDocuments(studentId, studentType);
            });
        });

        // Function to fetch student documents
        function fetchStudentDocuments(studentId, studentType) {
            const xhr = new XMLHttpRequest();
            <?php if ($isStaffView): ?>
            const apiUrl = `../AdminAccount/get_documents.php?id=${studentId}&type=${encodeURIComponent(studentType)}`;
            console.log('🔍 Staff View - Fetching from:', apiUrl);
            xhr.open("GET", apiUrl, true);
            <?php else: ?>
            const apiUrl = `get_documents.php?id=${studentId}&type=${encodeURIComponent(studentType)}`;
            console.log('🔍 Admin View - Fetching from:', apiUrl);
            xhr.open("GET", apiUrl, true);
            <?php endif; ?>
            
            xhr.responseType = "json";

            xhr.onload = function () {
                console.log('📥 Response received - Status:', this.status);
                console.log('📥 Response type:', typeof this.response);
                console.log('📥 Response data:', this.response);
                
                if (this.status === 200) {
                    try {
                        let response;
                        
                        if (typeof this.response === 'object') {
                            response = this.response;
                        } else {
                            response = JSON.parse(this.responseText);
                        }
                        
                        console.log('✅ Parsed response:', response);
                        console.log('✅ Has success property:', 'success' in response);
                        console.log('✅ Success value:', response.success);
                        
                        displayDocuments(response, studentType);
                    } catch (e) {
                        console.error('❌ Parse error:', e);
                        console.error('❌ Response text:', this.responseText);
                        documentList.innerHTML = `
                            <div class="alert alert-danger">
                                <h5>Error loading documents</h5>
                                <p>Could not parse the server response. Details: ${e.message}</p>
                                <pre>${this.responseText.substring(0, 500)}</pre>
                            </div>
                        `;
                    }
                } else {
                    console.error('❌ HTTP error:', this.status, this.responseText);
                    documentList.innerHTML = `
                        <div class="alert alert-danger">
                            <h5>Error loading documents</h5>
                            <p>Server returned status code: ${this.status}</p>
                            <pre>${this.responseText ? this.responseText.substring(0, 500) : 'No response'}</pre>
                        </div>
                    `;
                }
            };

            xhr.onerror = function() {
                console.error('Network error');
                documentList.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Network Error</h5>
                        <p>Could not connect to the server. Please check your connection and try again.</p>
                    </div>
                `;
            };

            xhr.send();
        }

        // Updated displayDocuments function
        function displayDocuments(data, studentType) {
            console.log('Displaying documents:', data);
            
            if (!data.success) {
                documentList.innerHTML = `<div class="alert alert-danger">Error: ${data.error || 'Unknown error'}</div>`;
                return;
            }
            
            if (!data.documents || data.documents.length === 0) {
                documentList.innerHTML = '<div class="alert alert-info">No documents found for this student</div>';
                return;
            }

            let html = `
                <style>
                    .document-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                        gap: 1rem;
                        padding: 1rem;
                    }
                    
                    .document-card {
                        background: #fff;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        transition: transform 0.2s, box-shadow 0.2s;
                        overflow: hidden;
                        cursor: pointer;
                        position: relative;
                    }
                    
                    .document-card:hover {
                        transform: translateY(-5px);
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    }
                    
                    .document-card.clickable::after {
                        content: 'Click to view';
                        position: absolute;
                        top: 10px;
                        right: 10px;
                        background: rgba(128, 0, 0, 0.9);
                        color: white;
                        padding: 5px 10px;
                        border-radius: 5px;
                        font-size: 0.7rem;
                        opacity: 0;
                        transition: opacity 0.2s;
                    }
                    
                    .document-card.clickable:hover::after {
                        opacity: 1;
                    }
                    
                    .document-thumbnail {
                        width: 100%;
                        height: 160px;
                        background-color: #f8f9fa;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-bottom: 1px solid #eee;
                    }
                    
                    .document-thumbnail i {
                        font-size: 3rem;
                        color: #800000;
                    }
                    
                    .document-thumbnail img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                    
                    .document-info {
                        padding: 1rem;
                    }
                    
                    .document-name {
                        font-size: 0.9rem;
                        font-weight: 600;
                        margin-bottom: 0.5rem;
                        color: #333;
                    }
                    
                    .document-status {
                        font-size: 0.8rem;
                    }
                    
                    .document-actions {
                        display: flex;
                        gap: 0.5rem;
                        margin-top: 0.5rem;
                    }
                </style>
                
                <div class="document-grid">
            `;

            data.documents.forEach(doc => {
                const isSubmitted = doc.submitted && doc.exists;
                const fileExtension = doc.path ? doc.path.split('.').pop().toLowerCase() : '';
                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
                const isPdf = fileExtension === 'pdf';
                
                // Build the correct file path - FIXED
                let displayPath = '';
                if (isSubmitted && doc.path) {
                    // Always prepend ../ since we're in AdminAccount folder
                    if (doc.path.startsWith('uploads/')) {
                        displayPath = '../' + doc.path;
                    } else if (doc.path.startsWith('../uploads/')) {
                        displayPath = doc.path;
                    } else {
                        displayPath = '../' + doc.path;
                    }
                }
                
                html += `
                    <div class="document-card ${isSubmitted ? 'clickable' : ''}" 
                         data-path="${displayPath || ''}" 
                         data-name="${doc.name}"
                         data-submitted="${isSubmitted}">
                        <div class="document-thumbnail">
                            ${isImage && isSubmitted ? `
                                <img src="${displayPath}" alt="${doc.name}" loading="lazy" 
                                     onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\\'fas fa-image\\'></i>';">
                            ` : `
                                <i class="fas ${isPdf ? 'fa-file-pdf' : isSubmitted ? 'fa-file-check' : 'fa-file'}"></i>
                            `}
                        </div>
                        <div class="document-info">
                            <div class="document-name">${doc.name}</div>
                            <span class="badge ${isSubmitted ? 'bg-success' : 'bg-warning'} document-status">
                                ${isSubmitted ? 'Submitted' : 'Not Submitted'}
                            </span>
                            ${isSubmitted ? `
                                <div class="document-actions">
                                    <button class="btn btn-sm btn-outline-primary print-document" data-path="${displayPath}" title="Print document">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                                <div class="text-muted small mt-1">
                                    ${doc.original_filename || 'Uploaded file'}
                                    ${doc.file_size ? ` (${Math.round(doc.file_size/1024)} KB)` : ''}
                                </div>
                            ` : `
                                <div class="text-muted small mt-1">No file uploaded</div>
                            `}
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            documentList.innerHTML = html;
            
            // Add click event listeners to cards for preview
            document.querySelectorAll('.document-card.clickable').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on the print button
                    if (e.target.closest('.print-document')) {
                        return;
                    }
                    
                    const filePath = this.getAttribute('data-path');
                    const fileName = this.getAttribute('data-name');
                    
                    if (filePath) {
                        openDocumentPreview(filePath, fileName);
                    }
                });
            });
            
            // Add click event listeners to print buttons
            document.querySelectorAll('.print-document').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent card click
                    const filePath = this.getAttribute('data-path');
                    if (filePath) {
                        printDocument(filePath);
                    }
                });
            });
        }
        
        // Function to open document preview
        function openDocumentPreview(filePath, fileName) {
            const fileExtension = filePath.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
            const isPdf = fileExtension === 'pdf';
            
            // Create a modal for preview
            const previewModal = document.createElement('div');
            previewModal.className = 'modal fade';
            
            // Build content based on file type
            let contentHtml = '';
            if (isImage) {
                contentHtml = '<img src="' + filePath + '" style="width: 100%; height: auto; display: block; border-radius: 8px;" alt="' + fileName + '">';
            } else if (isPdf) {
                contentHtml = '<iframe src="' + filePath + '" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>';
            } else {
                contentHtml = '<div style="background: white; padding: 2rem; border-radius: 8px; text-align: center;"><p>Preview not available for this file type.</p><a href="' + filePath + '" target="_blank" class="btn btn-primary">Open in new tab</a></div>';
            }
            
            previewModal.innerHTML = '<div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">' +
                '<div class="modal-content" style="border: none; background: transparent;">' +
                '<div class="modal-body" style="padding: 0; background: transparent;">' +
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 10px; right: 10px; z-index: 1050; background: white; opacity: 1; border-radius: 50%; width: 35px; height: 35px;"></button>' +
                contentHtml +
                '</div></div></div>';
            
            document.body.appendChild(previewModal);
            const modal = new bootstrap.Modal(previewModal);
            modal.show();
            
            // Remove modal from DOM after it's hidden
            previewModal.addEventListener('hidden.bs.modal', function() {
                previewModal.remove();
            });
        }
        
        // Function to print document (image only, no text)
        function printDocument(filePath) {
            const fileExtension = filePath.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
            const isPdf = fileExtension === 'pdf';
            
            if (isPdf) {
                // For PDFs, create iframe and print
                const printFrame = document.createElement('iframe');
                printFrame.style.display = 'none';
                printFrame.src = filePath;
                document.body.appendChild(printFrame);
                
                printFrame.onload = function() {
                    try {
                        printFrame.contentWindow.focus();
                        printFrame.contentWindow.print();
                        setTimeout(() => {
                            document.body.removeChild(printFrame);
                        }, 1000);
                    } catch (e) {
                        console.error('Print error:', e);
                        document.body.removeChild(printFrame);
                        // Fallback to opening in new tab
                        window.open(filePath, '_blank');
                    }
                };
            } else if (isImage) {
                // For images, create a hidden iframe with print-optimized layout
                const img = new Image();
                img.onload = function() {
                    const printFrame = document.createElement('iframe');
                    printFrame.style.display = 'none';
                    document.body.appendChild(printFrame);
                    
                    const frameDoc = printFrame.contentWindow.document;
                    frameDoc.open();
                    frameDoc.write('<!DOCTYPE html><html><head><title>Print Document</title><style>' +
                        '@page { margin: 0; size: auto; }' +
                        '* { margin: 0; padding: 0; box-sizing: border-box; }' +
                        'html, body { margin: 0; padding: 0; width: 100%; height: 100%; }' +
                        'body { display: flex; justify-content: center; align-items: center; }' +
                        'img { display: block; max-width: 100%; max-height: 100%; width: auto; height: auto; }' +
                        '@media print {' +
                        '  html, body { margin: 0; padding: 0; width: 100%; height: 100%; }' +
                        '  body { display: flex; justify-content: center; align-items: center; }' +
                        '  img { max-width: 100%; max-height: 100%; width: auto; height: auto; page-break-inside: avoid; }' +
                        '}' +
                        '</style></head><body>' +
                        '<img src="' + filePath + '" alt="Document" />' +
                        '</body></html>'
                    );
                    frameDoc.close();
                    
                    // Wait for image to load in iframe, then print
                    setTimeout(() => {
                        try {
                            printFrame.contentWindow.focus();
                            printFrame.contentWindow.print();
                            
                            // Remove iframe after printing (with delay for print dialog)
                            setTimeout(() => {
                                if (printFrame.parentNode) {
                                    document.body.removeChild(printFrame);
                                }
                            }, 1000);
                        } catch (e) {
                            console.error('Print error:', e);
                            document.body.removeChild(printFrame);
                        }
                    }, 500);
                };
                img.onerror = function() {
                    alert('Error loading image for printing');
                };
                img.src = filePath;
            } else {
                // For other file types, create iframe and print
                const printFrame = document.createElement('iframe');
                printFrame.style.display = 'none';
                printFrame.src = filePath;
                document.body.appendChild(printFrame);
                
                printFrame.onload = function() {
                    try {
                        printFrame.contentWindow.focus();
                        printFrame.contentWindow.print();
                        setTimeout(() => {
                            document.body.removeChild(printFrame);
                        }, 1000);
                    } catch (e) {
                        console.error('Print error:', e);
                        document.body.removeChild(printFrame);
                    }
                };
            }
        }
        
        // Add search functionality for regular students
        const regularStudentSearch = document.getElementById('regularStudentSearch');
        if (regularStudentSearch) {
            regularStudentSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#regular-students tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Add search functionality for transferees
        const transfereeSearch = document.getElementById('transfereeSearch');
        if (transfereeSearch) {
            transfereeSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#transferees tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Add course filtering for regular students
        const regularStudentCourseFilter = document.getElementById('regularStudentCourseFilter');
        if (regularStudentCourseFilter) {
            regularStudentCourseFilter.addEventListener('change', function() {
                const selectedCourse = this.value;
                const rows = document.querySelectorAll('#regular-students tbody tr');
                
                rows.forEach(row => {
                    if (selectedCourse === 'all' || row.getAttribute('data-course') === selectedCourse) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Add course filtering for transferees
        const transfereeCourseFilter = document.getElementById('transfereeCourseFilter');
        if (transfereeCourseFilter) {
            transfereeCourseFilter.addEventListener('change', function() {
                const selectedCourse = this.value;
                const rows = document.querySelectorAll('#transferees tbody tr');
                
                rows.forEach(row => {
                    if (selectedCourse === 'all' || row.getAttribute('data-course') === selectedCourse) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Add year level filtering for regular students
        const regularYearLevelFilter = document.getElementById('regularYearLevelFilter');
        if (regularYearLevelFilter) {
            regularYearLevelFilter.addEventListener('change', function() {
                const selectedYearLevel = this.value;
                const rows = document.querySelectorAll('#regular-students tbody tr');
                
                rows.forEach(row => {
                    if (selectedYearLevel === 'all' || row.getAttribute('data-year-level') === selectedYearLevel) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Add year level filtering for transferees
        const transfereeYearLevelFilter = document.getElementById('transfereeYearLevelFilter');
        if (transfereeYearLevelFilter) {
            transfereeYearLevelFilter.addEventListener('change', function() {
                const selectedYearLevel = this.value;
                const rows = document.querySelectorAll('#transferees tbody tr');
                
                rows.forEach(row => {
                    if (selectedYearLevel === 'all' || row.getAttribute('data-year-level') === selectedYearLevel) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });
</script>

<?php
// Only close connection if we're not in staff view (staff view manages its own connection)
if (!$isStaffView && $conn) {
    $conn->close();
}

// If this is staff view, don't close body/html tags yet (staffprofile.php will be appended)
if (!$isStaffView): ?>
</body>
</html>
<?php endif; ?>