<?php
// filepath: c:\xampp\htdocs\ibacmi-admin\ibacmi-admin\pages\completedoc.php

// Include database connection
require_once '../db_connect.php';

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchTerm = "%{$searchTerm}%";
    $searchCondition = "AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.course LIKE ?)";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Get students with complete documents
$sql = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.course, s.year_level, 
        s.student_type, s.status, s.date_added,
        (SELECT COUNT(*) FROM document_types dt 
         WHERE (dt.required_for = 'All' OR dt.required_for = s.student_type)) as required_docs,
        (SELECT COUNT(*) FROM student_documents sd 
         JOIN document_types dt ON sd.document_type_id = dt.id
         WHERE sd.student_id = s.id AND sd.is_submitted = 1 
         AND (dt.required_for = 'All' OR dt.required_for = s.student_type)) as submitted_docs
        FROM students s
        WHERE s.status = 'complete' $searchCondition
        ORDER BY s.date_added DESC";

$stmt = $conn->prepare($sql);

if (!empty($searchParams)) {
    $types = str_repeat('s', count($searchParams));
    $stmt->bind_param($types, ...$searchParams);
}

$stmt->execute();
$result = $stmt->get_result();

// Prepare data for display
$completeStudents = [];

while ($row = $result->fetch_assoc()) {
    $completeStudents[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBACMI - Complete Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/completedoc.css">
</head>
<body>
    <div class="container-fluid p-0">
        <?php include '../components/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="welcome-section">
                <div class="user-welcome">
                    <h4>Students with complete documents</h4>
                    <p class="mb-0">View all students who have submitted all required documents</p>
                </div>
                <form class="search-bar" method="GET" action="completedoc.php">
                    <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>">
                    <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                </form>
            </div>
            
            <?php if (count($completeStudents) > 0): ?>
                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Year Level</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completeStudents as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td>
                                        <?php 
                                            echo htmlspecialchars($student['first_name'] . ' ');
                                            if (!empty($student['middle_name'])) {
                                                echo htmlspecialchars($student['middle_name'] . ' ');
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
                                    <td><span class="badge-complete">Complete</span></td>
                                    <td><?php echo date('M d, Y', strtotime($student['date_added'])); ?></td>
                                    <td>
                                        <a href="Document.php?view=<?php echo $student['id']; ?>" class="view-btn">
                                            <i class="fas fa-folder-open"></i> View Documents
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Export Button -->
                <div class="export-container">
                    <button id="exportExcel" class="export-btn">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h4>No students with complete documents</h4>
                    <p>Students with complete document submissions will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="../js/complete-documents.js"></script>
</body>
</html>