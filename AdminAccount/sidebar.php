<?php
function renderSidebar($activePage = 'dashboard', $headerType = 'logo', $staffInfo = null) {
    $navItems = [
        'dashboard' => ['href' => 'dashboard.php', 'icon' => 'fa-th-large', 'text' => 'Dashboard'],
        'document' => ['href' => 'Document.php', 'icon' => 'fa-file-alt', 'text' => 'Student Records'],
        'student_management' => ['submenu' => true],
        'lacking' => ['href' => 'lackingofdoc.php', 'icon' => 'fa-exclamation-circle', 'text' => 'Lacking Documents'],
        'complete' => ['href' => 'completedoc.php', 'icon' => 'fa-clipboard-check', 'text' => 'Complete Documents'],
        'backup' => ['href' => 'backup.html', 'icon' => 'fa-cloud-upload-alt', 'text' => 'Backup'],
    ];

    // Add user request only for admin
    if ($headerType !== 'profile') {
        $navItems['userrequest'] = ['href' => 'userrequest.html', 'icon' => 'fa-inbox', 'text' => 'User Request'];
    }

    $bottomItems = [
        'manual' => ['href' => 'usersmanual.html', 'icon' => 'fa-book', 'text' => 'User Manual'],
        'logout' => ['href' => 'landingpage.html', 'icon' => 'fa-sign-out-alt', 'text' => 'Logout'],
    ];

    echo '<nav class="sidebar">';

    if ($headerType === 'profile' && $staffInfo) {
        echo '<div class="brand-container">
            <div class="profile-container">
                <div class="profile-image">
                    ' . (!empty($staffInfo['profile_picture']) ? '<img src="' . htmlspecialchars($staffInfo['profile_picture']) . '" alt="Profile">' : '<i class="fas fa-user-circle"></i>') . '
                </div>
                <div class="profile-info">
                    <h6 class="mb-0">' . htmlspecialchars($staffInfo['first_name'] ?? '') . ' ' . htmlspecialchars($staffInfo['last_name'] ?? '') . '</h6>
                    <span class="profile-role">' . htmlspecialchars($staffInfo['position'] ?? '') . '</span>
                </div>
                <button class="btn btn-sm btn-outline-primary edit-profile-btn" data-bs-toggle="modal" data-bs-target="#profileModal">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>';
    } else {
        echo '<div class="brand-container d-flex align-items-center">
            <img src="../photos/IBAlogo.png" alt="IBACMI Logo" class="me-3">
            <div class="brand-text">
                <h5 class="mb-0 fw-bold">IBACMI</h5>
                <span class="text-muted fs-6">Registrar Document Online Data Bank</span>
            </div>
        </div>';
    }

    echo '<div class="menu-bar mt-4">
            <ul class="nav flex-column">';

    foreach ($navItems as $key => $item) {
        if ($key === 'student_management') {
            echo '<li class="nav-item position-relative">
                <a class="nav-link" href="#">
                    <div class="nav-link-content">
                        <i class="fas fa-user-graduate"></i>
                        <span class="nav-text" style="white-space: nowrap;">Student Management</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="nav flex-column submenu">
                    <li class="nav-item">
                        <a class="nav-link" href="newstudent.html">
                            <i class="fas fa-user-plus"></i>
                            <span class="nav-text">New Student</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transferee.html">
                            <i class="fas fa-user-plus"></i>
                            <span class="nav-text">Transferee</span>
                        </a>
                    </li>
                </ul>
            </li>';
        } else {
            $active = ($activePage === $key) ? ' active' : '';
            echo '<li class="nav-item">
                <a class="nav-link' . $active . '" href="' . $item['href'] . '">
                    <div class="nav-link-content">
                        <i class="fas ' . $item['icon'] . '"></i>
                        <span class="nav-text">' . $item['text'] . '</span>
                    </div>
                </a>
            </li>';
        }
    }

    echo '</ul>
        </div>

        <div class="bottom-content mt-auto">
            <ul class="nav flex-column">';

    foreach ($bottomItems as $key => $item) {
        $active = ($activePage === $key) ? ' active' : '';
        echo '<li class="nav-item">
            <a class="nav-link' . $active . '" href="' . $item['href'] . '">
                <div class="nav-link-content">
                    <i class="fas ' . $item['icon'] . '"></i>
                    <span class="nav-text">' . $item['text'] . '</span>
                </div>
            </a>
        </li>';
    }

    echo '</ul>
        </div>
    </nav>';
}
?>