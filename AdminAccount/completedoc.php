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

// Determine the correct base path for assets
$basePath = $isStaffView ? '../AdminAccount/' : '';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only include database connection if not already included
if (!isset($conn)) {
    require_once '../db_connect.php';
}

// Check authentication based on view type
if (!$isStaffView) {
    // Admin authentication check
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.html");
        exit();
    }
}

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchTerm = "%{$searchTerm}%";
    $searchCondition = "AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.course LIKE ?)";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Get all students first, then filter in PHP for more accurate results
$sql = "SELECT 
            s.id, 
            s.student_id, 
            s.first_name, 
            s.middle_name, 
            s.last_name, 
            s.course, 
            s.year_level, 
            s.student_type, 
            s.status, 
            s.date_added
        FROM students s
        WHERE 1=1
        $searchCondition
        ORDER BY s.date_added DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $completeStudents = [];
} else {
    if (!empty($searchParams)) {
        $types = str_repeat('s', count($searchParams));
        $stmt->bind_param($types, ...$searchParams);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Prepare data for display
    $completeStudents = [];

    while ($row = $result->fetch_assoc()) {
        // Get ALL document types that could apply to this student
        // Check if is_required field exists in document_types table
        $checkColumnSql = "SHOW COLUMNS FROM document_types LIKE 'is_required'";
        $checkResult = $conn->query($checkColumnSql);
        $hasIsRequired = $checkResult && $checkResult->num_rows > 0;
        
        if ($hasIsRequired) {
            // Use is_required field if it exists
            $allDocsSql = "SELECT dt.id, dt.doc_name, dt.required_for, dt.is_required
                           FROM document_types dt 
                           WHERE dt.is_active = 1 
                           AND dt.is_required = 1
                           AND (dt.required_for = 'All' OR dt.required_for = ?)
                           ORDER BY dt.doc_name";
        } else {
            // Fallback to old logic, but exclude PSA Marriage Certificate from being strictly required
            $allDocsSql = "SELECT dt.id, dt.doc_name, dt.required_for
                           FROM document_types dt 
                           WHERE dt.is_active = 1 
                           AND (dt.required_for = 'All' OR dt.required_for = ?)
                           AND dt.doc_name != 'PSA Marriage Certificate'
                           ORDER BY dt.doc_name";
        }
        
        $allDocsStmt = $conn->prepare($allDocsSql);
        if (!$allDocsStmt) {
            error_log("All docs prepare failed: " . $conn->error);
            continue;
        }
        
        $allDocsStmt->bind_param('s', $row['student_type']);
        $allDocsStmt->execute();
        $allDocsResult = $allDocsStmt->get_result();
        
        $requiredDocIds = [];
        $totalRequired = 0;
        
        while ($docType = $allDocsResult->fetch_assoc()) {
            $requiredDocIds[] = $docType['id'];
            $totalRequired++;
        }
        $allDocsStmt->close();
        
        if ($totalRequired == 0) {
            continue; // Skip students with no required documents
        }
        
        // Now check how many of these required documents have been submitted
        if (!empty($requiredDocIds)) {
            $placeholders = implode(',', array_fill(0, count($requiredDocIds), '?'));
            $submittedSql = "SELECT COUNT(DISTINCT sd.document_type_id) as total
                            FROM student_documents sd 
                            WHERE sd.student_id = ? 
                            AND sd.is_submitted = 1 
                            AND sd.document_type_id IN ($placeholders)";
            
            $submittedStmt = $conn->prepare($submittedSql);
            if (!$submittedStmt) {
                error_log("Submitted count prepare failed: " . $conn->error);
                continue;
            }
            
            // Bind student ID first, then all document type IDs
            $types = 'i' . str_repeat('i', count($requiredDocIds));
            $params = array_merge([$row['id']], $requiredDocIds);
            $submittedStmt->bind_param($types, ...$params);
            
            $submittedStmt->execute();
            $submittedResult = $submittedStmt->get_result();
            $submittedData = $submittedResult->fetch_assoc();
            $totalSubmitted = $submittedData['total'];
            $submittedStmt->close();
        } else {
            $totalSubmitted = 0;
        }
        
        // Debug logging
        error_log("Student ID: {$row['student_id']}, Type: {$row['student_type']}, Required: {$totalRequired}, Submitted: {$totalSubmitted}");
        
        // Only include if truly complete and has required documents
        if ($totalRequired > 0 && $totalRequired == $totalSubmitted) {
            $row['required_docs'] = $totalRequired;
            $row['submitted_docs'] = $totalSubmitted;
            $completeStudents[] = $row;
        }
    }

    $stmt->close();
}

// If we reach here, display the HTML
ob_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isStaffView ? 'Complete Documents - Staff' : 'Complete Documents - Admin'; ?> - IBACMI</title>
    
     <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-touch-icon.png">
    <link href="<?php echo $basePath; ?>../bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Sidebar CSS - FIXED PATH -->
    <link href="<?php echo $basePath; ?>css/sidebar.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Complete Doc CSS - FIXED PATH -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>css/completedoc.css">
</head>
<body>
    <div class="container-fluid p-0">
        <?php 
        // Render appropriate sidebar based on view type
        if ($isStaffView) {
            include '../StaffAccount/staffsidebar.php';
            renderSidebar('complete', 'staff', $staffInfo);
        } else {
            include 'sidebar.php';
            renderSidebar('complete');
        }
        ?>
        
        <div class="main-content">
            <div class="header-card">
                <div class="header-content">
                    <div class="header-info">
                        <h2><i class="fas fa-clipboard-check me-2"></i>Complete Documents</h2>
                        <p>Students who have submitted all required documents</p>
                    </div>
                    <div class="stats-container">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count($completeStudents); ?></span>
                            <div class="stat-label">Complete</div>
                        </div>
                    </div>
                </div>
                
                <div class="header-search mt-3">
                    <form class="search-form" method="GET" action="<?php echo $isStaffView ? 'staffcompletedoc.php' : 'completedoc.php'; ?>">
                        <input type="text" name="search" class="search-input" placeholder="Search by Student ID, Name, or Course..." value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (count($completeStudents) > 0): ?>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Course & Year</th>
                                    <th>Student Type</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completeStudents as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                        <td>
                                            <?php 
                                                echo htmlspecialchars($student['first_name'] . ' ');
                                                if (!empty($student['middle_name'])) {
                                                    echo htmlspecialchars($student['middle_name'] . ' ');
                                                }
                                                echo htmlspecialchars($student['last_name']);
                                            ?>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($student['course']); ?></strong></div>
                                            <small class="text-muted">Year <?php echo htmlspecialchars($student['year_level']); ?></small>
                                        </td>
                                        <td>
                                            <span class="student-type-badge <?php echo $student['student_type'] === 'Regular' ? 'type-regular' : 'type-transferee'; ?>">
                                                <?php echo htmlspecialchars($student['student_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-complete">
                                                <i class="fas fa-check-circle"></i>
                                                Complete (<?php echo $student['submitted_docs']; ?>/<?php echo $student['required_docs']; ?>)
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['date_added'])); ?></td>
                                        <td>
                                            <button class="view-documents-btn" onclick="viewStudentDocuments(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', '<?php echo htmlspecialchars($student['student_type']); ?>')">
                                                <i class="fas fa-folder-open"></i>
                                                View Documents
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3 class="empty-title">No Complete Documents Found</h3>
                    <p class="empty-description">
                        <?php if (!empty($searchTerm)): ?>
                            No students found matching your search criteria.
                        <?php else: ?>
                            Students with complete document submissions will appear here.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Viewing Modal -->
    <div class="modal fade document-modal" id="documentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Student Documents - <span id="studentNameModal">Loading...</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="documentsContainer">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Profile Modal (only for staff view) -->
    <?php if ($isStaffView): ?>
        <?php include '../StaffAccount/staffprofile.php'; ?>
    <?php endif; ?>

    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <!-- Sidebar notifications -->
    <?php if ($isStaffView): ?>
    <script src="../StaffAccount/js/sidebar-notifications.js"></script>
    <?php else: ?>
    <script src="js/sidebar-notifications.js"></script>
    <?php endif; ?>
    
    <script>
        async function viewStudentDocuments(studentId, studentName, studentType) {
            const modal = new bootstrap.Modal(document.getElementById('documentsModal'));
            document.getElementById('studentNameModal').textContent = studentName;
            document.getElementById('documentsContainer').innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                </div>
            `;
            modal.show();

            try {
                const xhr = new XMLHttpRequest();
                const getDocsPath = <?php echo $isStaffView ? "'../AdminAccount/get_documents.php'" : "'get_documents.php'"; ?>;
                xhr.open("GET", `${getDocsPath}?id=${studentId}&type=${studentType}`, true);
                xhr.responseType = "json";

                xhr.onload = function () {
                    if (this.status === 200) {
                        try {
                            let response = typeof this.response === 'object' ? this.response : JSON.parse(this.responseText);
                            
                            console.log("Full response:", response); // Debug log
                            
                            // Filter to show only submitted documents
                            if (response.documents) {
                                response.documents = response.documents.filter(doc => doc.submitted === true);
                            }
                            
                            console.log("Filtered documents:", response.documents); // Debug log
                            
                            displayDocuments(response, studentType);
                        } catch (e) {
                            console.error("Error parsing JSON:", e);
                            document.getElementById('documentsContainer').innerHTML = `
                                <div class="alert alert-danger">
                                    <h5>Error loading documents</h5>
                                    <p>Could not parse the server response.</p>
                                </div>
                            `;
                        }
                    } else {
                        document.getElementById('documentsContainer').innerHTML = `
                            <div class="alert alert-danger">
                                <h5>Error loading documents</h5>
                                <p>Server returned status code: ${this.status}</p>
                            </div>
                        `;
                    }
                };

                xhr.onerror = function() {
                    document.getElementById('documentsContainer').innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h5>Connection Error</h5>
                            <p>Unable to connect to the server.</p>
                        </div>
                    `;
                };

                xhr.send();
            } catch (error) {
                console.error("Error fetching documents:", error);
            }
        }

        function displayDocuments(data, studentType) {
            if (!data.documents || data.documents.length === 0) {
                document.getElementById('documentsContainer').innerHTML = '<div class="alert alert-info">No completed documents found</div>';
                return;
            }

            const isStaffView = <?php echo $isStaffView ? 'true' : 'false'; ?>;

            console.log("Is staff view:", isStaffView);
            console.log("Raw documents data:", data.documents);

            let html = `
                <style>
                    .document-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; padding: 1rem; }
                    .document-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s; overflow: hidden; cursor: pointer; }
                    .document-card:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
                    .document-thumbnail { width: 100%; height: 160px; background-color: #f8f9fa; display: flex; flex-direction: column; align-items: center; justify-content: center; border-bottom: 1px solid #eee; position: relative; overflow: hidden; }
                    .document-thumbnail i { font-size: 3rem; color: #800000; z-index: 1; }
                    .document-thumbnail img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 2; }
                    .document-info { padding: 1rem; }
                    .document-name { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: #333; word-break: break-word; }
                    .document-actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
                    .document-lightbox { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); display: none; z-index: 9999; padding: 2rem; }
                    .document-lightbox.active { display: flex; align-items: center; justify-content: center; }
                    .lightbox-content { max-width: 95%; max-height: 95vh; position: relative; }
                    .lightbox-image { max-width: 100%; max-height: 90vh; object-fit: contain; display: block; margin: 0 auto; }
                    .lightbox-iframe { width: 90vw; height: 90vh; border: none; background: white; display: block; }
                    .lightbox-close { position: fixed; top: 1rem; right: 1rem; color: white; font-size: 2rem; cursor: pointer; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 4px; z-index: 10000; transition: background 0.3s; }
                    .lightbox-close:hover { background: rgba(255,255,255,0.3); }
                    .error-text { font-size: 0.7rem; color: #dc3545; margin-top: 0.5rem; text-align: center; padding: 0.5rem; word-break: break-all; }
                </style>
                <div class="document-grid">
            `;

            data.documents.forEach(doc => {
                if (!doc.path) {
                    console.warn("Document has no path:", doc);
                    return;
                }

                const fileExtension = doc.path.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension);
                const isPdf = fileExtension === 'pdf';
                
                // FIXED: Simplified path logic based on your database structure
                let displayPath = '';
                let originalPath = doc.path;
                
                // Clean backslashes to forward slashes
                let cleanPath = originalPath.replace(/\\/g, '/');
                
                console.log("Processing:", doc.name);
                console.log("  Original path from DB:", originalPath);
                console.log("  Clean path:", cleanPath);
                
                // Your database stores paths like: uploads/2025/Jessica_Bayang/196_2_1760670447.png
                // So we need to handle this correctly
                if (isStaffView) {
                    // Staff view needs ../ to go up one directory
                    if (cleanPath.startsWith('uploads/')) {
                        displayPath = '../' + cleanPath;
                    } else {
                        displayPath = '../uploads/' + cleanPath;
                    }
                } else {
                    // Admin view is in AdminAccount folder, so uploads/ is at ../uploads/
                    if (cleanPath.startsWith('uploads/')) {
                        displayPath = '../' + cleanPath;
                    } else {
                        displayPath = '../uploads/' + cleanPath;
                    }
                }
                
                console.log("  Final display path:", displayPath);
                
                html += `
                    <div class="document-card" data-path="${displayPath}" data-name="${doc.name}" data-type="${fileExtension}">
                        <div class="document-thumbnail">
                            ${isImage ? `
                                <i class="fas fa-image"></i>
                                <img src="${displayPath}" 
                                     alt="${doc.name}" 
                                     loading="lazy" 
                                     onerror="console.error('Failed to load image:', '${displayPath}'); this.style.display='none'; var err = document.createElement('p'); err.className='error-text'; err.innerHTML='Image not found<br><small>${displayPath.replace(/'/g, '&apos;')}</small>'; this.parentElement.appendChild(err);">
                            ` : `<i class="fas ${isPdf ? 'fa-file-pdf' : 'fa-file-alt'}"></i>`}
                        </div>
                        <div class="document-info">
                            <div class="document-name">${doc.name}</div>
                            <span class="badge bg-success">Submitted</span>
                            <div class="document-actions">
                                <a href="${displayPath}" class="btn btn-sm btn-secondary" download title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="btn btn-sm btn-info view-btn" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning print-doc-btn" title="Print">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                </div>
                <div class="document-lightbox" id="lightbox">
                    <div class="lightbox-close" onclick="closeLightbox()">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="lightbox-content">
                        <div class="lightbox-viewer"></div>
                    </div>
                </div>
            `;
            
            document.getElementById('documentsContainer').innerHTML = html;

            // Attach event listeners
            document.querySelectorAll('.document-card').forEach(card => {
                // Click on card or view button
                const viewBtn = card.querySelector('.view-btn');
                const clickHandler = function(e) {
                    if (e.target.closest('.btn') && !e.target.closest('.view-btn')) {
                        return; // Let other buttons work normally
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    const path = card.dataset.path;
                    const name = card.dataset.name;
                    const type = card.dataset.type;
                    console.log("Opening:", name, "Path:", path, "Type:", type);
                    openLightbox(path, name, type);
                };
                
                card.addEventListener('click', clickHandler);
                if (viewBtn) {
                    viewBtn.addEventListener('click', clickHandler);
                }
                
                // Print button
                const printBtn = card.querySelector('.print-doc-btn');
                if (printBtn) {
                    printBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const path = card.dataset.path;
                        const name = card.dataset.name;
                        printDocument(path, name);
                    });
                }
            });
        }

        function openLightbox(path, name, type) {
            const lightbox = document.getElementById('lightbox');
            const viewer = lightbox.querySelector('.lightbox-viewer');
            
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(type);
            const isPdf = type === 'pdf';

            console.log("Lightbox opening:", {path, name, type, isImage, isPdf});

            viewer.innerHTML = '';
            
            if (isImage) {
                const img = document.createElement('img');
                img.src = path;
                img.alt = name;
                img.className = 'lightbox-image';
                img.onerror = function() {
                    console.error('Lightbox image failed to load:', path);
                    viewer.innerHTML = `
                        <div class="text-center text-white p-5">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h5>Image failed to load</h5>
                            <p style="font-size: 0.9rem; opacity: 0.8; word-break: break-all; max-width: 600px;">${path}</p>
                            <a href="${path}" download class="btn btn-light mt-3">
                                <i class="fas fa-download me-2"></i> Try Download
                            </a>
                        </div>
                    `;
                };
                viewer.appendChild(img);
            } else if (isPdf) {
                const iframe = document.createElement('iframe');
                iframe.src = path;
                iframe.className = 'lightbox-iframe';
                viewer.appendChild(iframe);
            } else {
                viewer.innerHTML = `
                    <div class="text-center text-white p-5">
                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                        <h5>Cannot preview this file type</h5>
                        <p style="opacity: 0.8;">${name}</p>
                        <a href="${path}" download class="btn btn-light mt-3">
                            <i class="fas fa-download me-2"></i> Download File
                        </a>
                    </div>
                `;
            }
            
            lightbox.classList.add('active');
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            if (lightbox) {
                lightbox.classList.remove('active');
                const viewer = lightbox.querySelector('.lightbox-viewer');
                if (viewer) {
                    viewer.innerHTML = '';
                }
            }
        }

        // Close lightbox on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
        
        function printDocument(path, name) {
            console.log("Printing:", name, "Path:", path);
            
            const fileExt = path.split('.').pop().toLowerCase();
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            if (!printWindow) {
                alert('Please allow popups to print documents');
                return;
            }
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print - ${name}</title>
                    <style>
                        * { 
                            margin: 0; 
                            padding: 0; 
                            box-sizing: border-box; 
                        }
                        body { 
                            margin: 0;
                            padding: 0;
                            background: white;
                        }
                        .print-container {
                            width: 100%;
                            height: 100vh;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            padding: 0;
                        }
                        img { 
                            max-width: 100%;
                            max-height: 100vh;
                            width: auto;
                            height: auto;
                            display: block;
                            margin: 0 auto;
                        }
                        iframe { 
                            width: 100%; 
                            height: 100vh; 
                            border: none;
                            display: block;
                        }
                        @media print {
                            @page {
                                margin: 0;
                                size: auto;
                            }
                            body { 
                                margin: 0;
                                padding: 0;
                            }
                            .print-container {
                                padding: 0;
                                margin: 0;
                                page-break-after: avoid;
                                page-break-inside: avoid;
                            }
                            img { 
                                max-width: 100%;
                                max-height: 100vh;
                                width: auto;
                                height: auto;
                                page-break-inside: avoid;
                                page-break-after: avoid;
                                display: block;
                                margin: 0 auto;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-container">
                        ${['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExt) 
                            ? `<img src="${path}" alt="${name}" onload="setTimeout(() => window.print(), 500);" onerror="alert('Error loading image for printing');">`
                            : fileExt === 'pdf' 
                                ? `<iframe src="${path}" onload="setTimeout(() => window.print(), 1000);"></iframe>`
                                : `<p style="text-align:center; padding:50px;">Cannot print this file type. <a href="${path}" download>Download instead</a></p>`
                        }
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Optional: Close the print window after printing
            printWindow.onafterprint = function() {
                printWindow.close();
            };
        }
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