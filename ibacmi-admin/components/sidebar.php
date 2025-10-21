<?php
// filepath: c:\xampp\htdocs\ibacmi-admin\ibacmi-admin\components\sidebar.php
?>

<div class="sidebar">
    <div class="brand-container">
        <h2>Admin Panel</h2>
    </div>
    <nav>
        <a href="completedoc.php" class="nav-link">
            <i class="fas fa-folder-open"></i>
            Complete Documents
        </a>
        <a href="otherpage.php" class="nav-link">
            <i class="fas fa-users"></i>
            Other Page
        </a>
        <a href="settings.php" class="nav-link">
            <i class="fas fa-cog"></i>
            Settings
        </a>
        <div class="nav-item">
            <a href="#" class="nav-link">
                <i class="fas fa-file"></i>
                Documents
            </a>
            <div class="submenu">
                <a href="submitted-docs.php" class="nav-link">Submitted Docs</a>
                <a href="pending-docs.php" class="nav-link">Pending Docs</a>
            </div>
        </div>
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </nav>
</div>