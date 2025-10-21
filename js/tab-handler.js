class TabHandler {
    constructor() {
        this.initializeTransfereesTab();
        this.initializeTabState();
    }

    initializeTransfereesTab() {
        const transfereesTab = document.getElementById('transferees-tab');
        if (transfereesTab) {
            transfereesTab.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchToTransfereesTab();
            });
        }
    }

    initializeTabState() {
        if (window.location.hash === '#transferees') {
            document.getElementById('transferees-tab')?.click();
        }
    }

    switchToTransfereesTab() {
        document.getElementById('regular-students').classList.remove('show', 'active');
        document.getElementById('transferees').classList.add('show', 'active');
        document.getElementById('regular-students-tab').classList.remove('active');
        document.getElementById('transferees-tab').classList.add('active');
        window.location.hash = 'transferees';
    }
}