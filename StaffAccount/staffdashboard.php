<?php
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

// Function to get course distribution
function getCourseDistribution($conn) {
    $courseData = array();

    // Define colors for each course with the new color scheme
    $colors = array(
        'BSIT' => '#28a745',    // Green
        'BEED' => '#007bff',    // Blue
        'BSHM' => '#e83e8c',    // Pink
        'BSCRIM' => '#6f42c1',  // Violet
        'BECED' => '#ffc107',   // Yellow
        'BSE' => '#dc3545',     // Red
        'BPA' => '#fd7e14'      // Orange
    );

    // Get all courses regardless of whether they have students or not
    $allCourses = array('BSIT', 'BEED', 'BSHM', 'BSCRIM', 'BECED', 'BSE', 'BPA');

    // Query to count students by course from the students table
    $sql = "SELECT course, COUNT(*) as count FROM students WHERE course IS NOT NULL AND course != '' GROUP BY course";
    $result = $conn->query($sql);

    // Create array of course counts
    $courseCounts = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $course = strtoupper(trim($row["course"])); // Convert to uppercase and trim
            $courseCounts[$course] = $row["count"];
        }
    }

    // Build courseData array with all courses
    foreach ($allCourses as $course) {
        $count = isset($courseCounts[$course]) ? $courseCounts[$course] : 0;
        $color = isset($colors[$course]) ? $colors[$course] : '#' . substr(md5($course), 0, 6);

        $courseData[] = array(
            "label" => $course,
            "value" => $count,
            "color" => $color
        );
    }

    return $courseData;
}

// Function to get student statistics
function getStudentStats($conn) {
    $stats = array();

    // Get total students count
    $sql = "SELECT COUNT(*) as total FROM students";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['totalStudents'] = $row['total'];
    } else {
        $stats['totalStudents'] = 0;
    }

    // Get new students count (Regular)
    $sql = "SELECT COUNT(*) as count FROM students WHERE student_type = 'Regular'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['newStudents'] = $row['count'];
    } else {
        $stats['newStudents'] = 0;
    }

    // Get transferees count
    $sql = "SELECT COUNT(*) as count FROM students WHERE student_type = 'Transferee'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stats['transferees'] = $row['count'];
    } else {
        $stats['transferees'] = 0;
    }

    return $stats;
}

// Function to get document statistics
function getDocumentStats($conn) {
    $stats = array();

    // Initialize counters
    $completeCount = 0;
    $incompleteCount = 0;

    // Get all students
    $sql = "SELECT id, student_type FROM students";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $studentId = $row['id'];
            $studentType = $row['student_type'];

            // Check if is_required field exists in document_types table
            $checkColumnSql = "SHOW COLUMNS FROM document_types LIKE 'is_required'";
            $checkResult = $conn->query($checkColumnSql);
            $hasIsRequired = $checkResult && $checkResult->num_rows > 0;

            // Get required documents for this student type
            if ($hasIsRequired) {
                $allDocsSql = "SELECT dt.id
                               FROM document_types dt
                               WHERE dt.is_active = 1
                               AND dt.is_required = 1
                               AND (dt.required_for = 'All' OR dt.required_for = ?)";
            } else {
                $allDocsSql = "SELECT dt.id
                               FROM document_types dt
                               WHERE dt.is_active = 1
                               AND (dt.required_for = 'All' OR dt.required_for = ?)
                               AND dt.doc_name != 'PSA Marriage Certificate'";
            }

            $allDocsStmt = $conn->prepare($allDocsSql);
            if (!$allDocsStmt) {
                continue;
            }

            $allDocsStmt->bind_param('s', $studentType);
            $allDocsStmt->execute();
            $allDocsResult = $allDocsStmt->get_result();

            $requiredDocIds = [];
            while ($docType = $allDocsResult->fetch_assoc()) {
                $requiredDocIds[] = $docType['id'];
            }
            $allDocsStmt->close();

            $totalRequired = count($requiredDocIds);

            if ($totalRequired == 0) {
                continue;
            }

            // Check submitted documents
            $totalSubmitted = 0;
            if (!empty($requiredDocIds)) {
                $placeholders = implode(',', array_fill(0, count($requiredDocIds), '?'));
                $submittedSql = "SELECT COUNT(DISTINCT sd.document_type_id) as total
                                FROM student_documents sd
                                WHERE sd.student_id = ?
                                AND sd.is_submitted = 1
                                AND sd.document_type_id IN ($placeholders)";

                $submittedStmt = $conn->prepare($submittedSql);
                if ($submittedStmt) {
                    $types = 'i' . str_repeat('i', count($requiredDocIds));
                    $params = array_merge([$studentId], $requiredDocIds);
                    $submittedStmt->bind_param($types, ...$params);
                    $submittedStmt->execute();
                    $submittedResult = $submittedStmt->get_result();
                    $submittedData = $submittedResult->fetch_assoc();
                    $totalSubmitted = $submittedData['total'];
                    $submittedStmt->close();
                }
            }

            // Determine if complete or incomplete
            if ($totalRequired > 0 && $totalRequired == $totalSubmitted) {
                $completeCount++;
            } else {
                $incompleteCount++;
            }
        }
    }

    $stats['completeDocuments'] = $completeCount;
    $stats['incompleteDocuments'] = $incompleteCount;

    return $stats;
}

// Function to get recent activities
function getRecentActivities($conn) {
    $activities = array();

    // Check if activities table exists
    $tableExists = false;
    $checkTable = $conn->query("SHOW TABLES LIKE 'activities'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $tableExists = true;
    }

    if ($tableExists) {
        // Query to get recent activities from last 24 hours only
        $sql = "SELECT * FROM activities WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY timestamp DESC LIMIT 5";
        $result = $conn->query($sql);

        // If there are records
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }
        }
    }

    // If no activities table or no records, use recent student additions from last 24 hours
    if (empty($activities)) {
        $sql = "SELECT id, student_id, first_name, last_name, student_type, date_added
                FROM students
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ORDER BY date_added DESC
                LIMIT 5";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $action = $row['student_type'] === 'Regular' ? 'New Student Added' : 'Transferee Added';
                $activities[] = array(
                    "action" => $action,
                    "description" => "Added " . $row['student_type'] . " record for " . $row['first_name'] . " " . $row['last_name'] . " (ID: " . $row['student_id'] . ")",
                    "timestamp" => $row['date_added']
                );
            }
        }
    }

    return $activities;
}

// Get data for the dashboard
$courseDistribution = getCourseDistribution($conn);
$studentStats = getStudentStats($conn);
$documentStats = getDocumentStats($conn);
$recentActivities = getRecentActivities($conn);

// Convert PHP arrays to JSON for JavaScript
$courseDistributionJSON = json_encode($courseDistribution);
$studentStatsJSON = json_encode($studentStats);
$documentStatsJSON = json_encode($documentStats);
$recentActivitiesJSON = json_encode($recentActivities);

// DON'T CLOSE CONNECTION YET - staffprofile.php needs it
// $conn->close(); // REMOVE THIS LINE
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBACMI - Registrar Document Online Data Bank</title>

    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png">
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../AdminAccount/css/sidebar.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
            include 'staffsidebar.php'; 
            renderSidebar('dashboard', 'staff', $staffInfo); 
        ?>
        
        <div class="main-content">
            <div class="dashboard-container">
                <!-- Welcome Banner -->
                <div class="welcome-banner fade-in">
                    <h2 class="welcome-title">
                        <i class="fas fa-chart-line"></i>
                        IBACMI Document Databank Dashboard
                    </h2>
                    <p class="welcome-description">Welcome to the document management system. Monitor student records and document status in real-time.</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card fade-in delay-1">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value" id="totalStudents"><?php echo $studentStats['totalStudents']; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    
                    <div class="stat-card fade-in delay-2">
                        <div class="stat-icon info">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-value" id="newStudents"><?php echo $studentStats['newStudents']; ?></div>
                        <div class="stat-label">Regular Students</div>
                    </div>
                    
                    <div class="stat-card fade-in delay-3">
                        <div class="stat-icon warning">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-value" id="transferees"><?php echo $studentStats['transferees']; ?></div>
                        <div class="stat-label">Transferees</div>
                    </div>
                    
                    <div class="stat-card fade-in delay-4">
                        <div class="stat-icon success">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-value" id="completeDocuments"><?php echo $documentStats['completeDocuments']; ?></div>
                        <div class="stat-label">Complete Documents</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card fade-in delay-1">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-chart-pie" style="color: var(--primary);"></i>
                                Students by Course
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="courseDistributionChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card fade-in delay-2">
                        <div class="chart-header">
                            <h3 class="chart-title">
                                <i class="fas fa-clipboard-list" style="color: var(--primary);"></i>
                                Document Status
                            </h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="documentStatusChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div class="activity-card fade-in delay-3">
                    <div class="activity-header">
                        <h3 class="activity-title">
                            <i class="fas fa-history" style="color: var(--primary);"></i>
                            Recent Activities
                        </h3>
                    </div>
                    <div class="activity-body">
                        <ul class="activity-list" id="activityList">
                            <!-- Activities will be populated by JavaScript -->
                        </ul>
                    </div>
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
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Get data from PHP
            const courseData = <?php echo $courseDistributionJSON; ?>;
            const studentStats = <?php echo $studentStatsJSON; ?>;
            const documentStats = <?php echo $documentStatsJSON; ?>;
            const recentActivities = <?php echo $recentActivitiesJSON; ?>;
            
            // Course Distribution Chart
            const drawCourseDistributionChart = () => {
                const ctx = document.getElementById('courseDistributionChart').getContext('2d');
                
                // Extract data for Chart.js
                const labels = courseData.map(item => item.label);
                const values = courseData.map(item => item.value);
                const colors = courseData.map(item => item.color);
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '65%',
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        }
                    }
                });
            };
            
            // Document Status Chart
            const drawDocumentStatusChart = () => {
                const ctx = document.getElementById('documentStatusChart').getContext('2d');
                
                // Document status data
                const docStatusData = [
                    {
                        label: 'Complete',
                        value: documentStats.completeDocuments || 0,
                        color: '#10b981' // success color
                    },
                    {
                        label: 'Incomplete',
                        value: documentStats.incompleteDocuments || 0,
                        color: '#f59e0b' // warning color
                    }
                ];
                
                // Extract data for Chart.js
                const labels = docStatusData.map(item => item.label);
                const values = docStatusData.map(item => item.value);
                const colors = docStatusData.map(item => item.color);
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        }
                    }
                });
            };
            
            // Populate activity log
            const populateActivityLog = () => {
                const activityList = document.getElementById('activityList');
                activityList.innerHTML = ''; // Clear existing content
                
                if (recentActivities.length === 0) {
                    activityList.innerHTML = `
                        <li class="text-center py-4">
                            <i class="fas fa-info-circle fa-2x mb-3" style="color: var(--gray-400);"></i>
                            <p style="color: var(--gray-500);">No recent activities to display.</p>
                        </li>
                    `;
                    return;
                }
                
                recentActivities.forEach((activity, index) => {
                    // Format the timestamp
                    let timestamp = activity.timestamp;
                    const activityDate = new Date(timestamp);
                    const now = new Date();
                    
                    let formattedTime;
                    const diffDays = Math.floor((now - activityDate) / (1000 * 60 * 60 * 24));
                    
                    if (diffDays === 0) {
                        formattedTime = "Today, " + activityDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    } else if (diffDays === 1) {
                        formattedTime = "Yesterday, " + activityDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    } else {
                        formattedTime = activityDate.toLocaleDateString() + ", " + 
                                        activityDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    }
                    
                    const activityItem = document.createElement('li');
                    activityItem.className = 'activity-item';
                    activityItem.style.animationDelay = `${0.1 * index}s`;
                    
                    activityItem.innerHTML = `
                        <div class="activity-header-row">
                            <span class="activity-action">${activity.action}</span>
                            <span class="activity-time">${formattedTime}</span>
                        </div>
                        <div class="activity-description">${activity.description}</div>
                    `;
                    
                    activityList.appendChild(activityItem);
                });
            };
            
            // Initialize charts and activity log
            if (courseData && courseData.length > 0) {
                drawCourseDistributionChart();
            }
            
            if (documentStats) {
                drawDocumentStatusChart();
            }
            
            populateActivityLog();
            
            // Counter animation for stats
            const animateCounter = (elementId, targetValue) => {
                const element = document.getElementById(elementId);
                const duration = 1500; // Animation duration in milliseconds
                const frameDuration = 1000 / 60; // 60fps
                const totalFrames = Math.round(duration / frameDuration);
                let frame = 0;
                
                const startValue = 0;
                const valueIncrement = (targetValue - startValue) / totalFrames;
                
                const counter = setInterval(() => {
                    frame++;
                    const currentValue = Math.round(startValue + valueIncrement * frame);
                    element.textContent = currentValue;
                    
                    if (frame === totalFrames) {
                        clearInterval(counter);
                        element.textContent = targetValue;
                    }
                }, frameDuration);
            };
            
            // Animate counters
            setTimeout(() => {
                animateCounter('totalStudents', studentStats.totalStudents || 0);
                animateCounter('newStudents', studentStats.newStudents || 0);
                animateCounter('transferees', studentStats.transferees || 0);
                animateCounter('completeDocuments', documentStats.completeDocuments || 0);
            }, 500);
        });
    </script>
</body>
</html>