<?php
// Sidebar for Staff Dashboard
function renderSidebar($activePage = 'dashboard', $userType = 'staff', $userInfo = []) {
    // Default user info if not provided
    $defaultInfo = [
        'first_name' => 'Staff',
        'last_name' => 'Member',
        'role' => 'Staff',
        'profile_picture' => ''
    ];
    
    $userInfo = array_merge($defaultInfo, $userInfo);
    $fullName = trim($userInfo['first_name'] . ' ' . $userInfo['last_name']);
    $role = $userInfo['role'] ?? 'Staff';
    
    // Get the correct profile picture path
    $profilePicture = '';
    if (!empty($userInfo['profile_picture'])) {
        // The path is already stored as relative from StaffAccount folder (../uploads/staff_profiles/)
        $profilePicture = htmlspecialchars($userInfo['profile_picture']);
    }
?>
    <!-- Sidebar -->
    <nav class="sidebar">
        <!-- Profile Section (replaces brand-container for staff) -->
        <div class="profile-section">
            <div class="profile-icon">
                <?php if (!empty($profilePicture)): ?>
                    <img src="<?php echo $profilePicture; ?>" 
                         alt="<?php echo htmlspecialchars($fullName); ?>"
                         style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                         onerror="this.onerror=null; this.src='../photos/default-avatar.png';">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($fullName); ?></div>
            <div class="profile-role"><?php echo htmlspecialchars($role); ?></div>
            <a href="javascript:void(0);" class="profile-edit-btn" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="fas fa-edit"></i>
                Edit Profile
            </a>
        </div>

        <!-- Menu -->
        <div class="menu-bar">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>" href="staffdashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'students' ? 'active' : ''; ?>" href="staffdocument.php">
                        <i class="fas fa-file-alt"></i>
                        <span class="nav-text">Student Records</span>
                    </a>
                </li>

                <li class="nav-item <?php echo in_array($activePage, ['newstudent', 'transferee']) ? 'active' : ''; ?>">
                    <a class="nav-link" href="#studentManagementSubmenu" data-bs-toggle="collapse" role="button" 
                       aria-expanded="<?php echo in_array($activePage, ['newstudent', 'transferee']) ? 'true' : 'false'; ?>" 
                       aria-controls="studentManagementSubmenu">
                        <i class="fas fa-user-graduate"></i>
                        <span class="nav-text">Student Management</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <div class="collapse submenu <?php echo in_array($activePage, ['newstudent', 'transferee']) ? 'show' : ''; ?>" 
                         id="studentManagementSubmenu">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'newstudent' ? 'active' : ''; ?>" 
                                   href="staffnewstudent.php">
                                    <i class="fas fa-user-plus"></i>
                                    <span class="nav-text">New Student</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activePage === 'transferee' ? 'active' : ''; ?>" 
                                   href="stafftransferee.php">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span class="nav-text">Transferee</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'lacking' ? 'active' : ''; ?>" 
                       href="stafflackingdoc.php">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="nav-text">Lacking Documents</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'complete' ? 'active' : ''; ?>" 
                       href="staffcompletedoc.php">
                        <i class="fas fa-check-circle"></i>
                        <span class="nav-text">Complete Documents</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'backup' ? 'active' : ''; ?>" href="staffbackup.php">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span class="nav-text">Backup</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Bottom Content -->
        <div class="bottom-content">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'usermanual' ? 'active' : ''; ?>" href="staffusermanual.php">
                        <i class="fas fa-book"></i>
                        <span class="nav-text">User Manual</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="stafflogin.html">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
<?php
}
?>