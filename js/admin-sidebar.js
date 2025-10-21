class AdminSidebar {
    constructor() {
        this.initializeSidebar();
    }

    initializeSidebar() {
        const currentLocation = window.location.pathname.split("/").pop() || "dashboard.php";
        const menuItems = document.querySelectorAll(".sidebar .nav-link");
        let isActiveSet = false;

        menuItems.forEach(item => {
            item.classList.remove('active');

            if (item.getAttribute("href") === currentLocation) {
                this.setActiveMenuItem(item);
                isActiveSet = true;
            }

            // Handle submenu toggles
            if (!item.getAttribute("href") || item.getAttribute("href") === "#") {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const parentItem = item.closest('.nav-item');
                    if (parentItem) {
                        parentItem.classList.toggle('active');
                    }
                });
            }
        });

        if (!isActiveSet && currentLocation === "dashboard.php") {
            const dashboardLink = document.querySelector('.nav-link[href="dashboard.php"]');
            if (dashboardLink) {
                this.setActiveMenuItem(dashboardLink);
            }
        }
    }

    setActiveMenuItem(item) {
        item.classList.add('active');
        
        const parentLi = item.closest('li.nav-item');
        if (parentLi && parentLi.parentElement.classList.contains('submenu')) {
            const parentSubmenu = parentLi.parentElement;
            parentSubmenu.style.display = 'block';
            const parentNavItem = parentSubmenu.closest('.nav-item');
            if (parentNavItem) {
                parentNavItem.classList.add('active');
            }
        }
    }

    loadSidebar() {
        const sidebarContainer = document.getElementById('adminSidebarContainer');
        if (sidebarContainer) {
            fetch('templates/admin-sidebar.html')
                .then(response => response.text())
                .then(html => {
                    sidebarContainer.innerHTML = html;
                    this.initializeSidebar();
                })
                .catch(error => console.error('Error loading admin sidebar:', error));
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const adminSidebar = new AdminSidebar();
    adminSidebar.loadSidebar();
});