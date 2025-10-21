// filepath: c:\xampp\htdocs\ibacmi-admin\ibacmi-admin\js\complete-documents.js

document.addEventListener('DOMContentLoaded', function() {
    const exportButton = document.getElementById('exportExcel');

    if (exportButton) {
        exportButton.addEventListener('click', function() {
            // Logic to export the table data to Excel
            exportToExcel();
        });
    }

    function exportToExcel() {
        // Get the table data
        const table = document.querySelector('.student-table');
        const workbook = XLSX.utils.table_to_book(table, { sheet: "Complete Documents" });
        XLSX.writeFile(workbook, 'Complete_Documents.xlsx');
    }
});