class SearchFilter {
    constructor() {
        this.initializeSearch();
        this.initializeFilters();
    }

    initializeSearch() {
        this.setupSearch('regularStudentSearch', '#regular-students tbody tr');
        this.setupSearch('transfereeSearch', '#transferees tbody tr');
    }

    setupSearch(inputId, rowSelector) {
        const searchInput = document.getElementById(inputId);
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll(rowSelector);
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    }

    initializeFilters() {
        this.setupFilter('regularStudentCourseFilter', '#regular-students tbody tr');
        this.setupFilter('transfereeCourseFilter', '#transferees tbody tr');
    }

    setupFilter(filterId, rowSelector) {
        const filter = document.getElementById(filterId);
        if (filter) {
            filter.addEventListener('change', () => {
                const selectedCourse = filter.value;
                const rows = document.querySelectorAll(rowSelector);
                
                rows.forEach(row => {
                    row.style.display = (selectedCourse === 'all' || 
                                       row.getAttribute('data-course') === selectedCourse) 
                                      ? '' : 'none';
                });
            });
        }
    }
}