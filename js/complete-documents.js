class CompleteDocuments {
    constructor() {
        this.initializeSidebar();
        this.initializeExcelExport();
    }

    initializeSidebar() {
        const currentLocation = window.location.pathname.split("/").pop();
        const menuItems = document.querySelectorAll(".sidebar .nav-link");

        // Set active based on current page
        menuItems.forEach(item => {
            if (item.getAttribute("href") === currentLocation) {
                item.classList.add("active");
            } else {
                item.classList.remove("active");
            }
        });

        // Set up click event handlers
        menuItems.forEach(item => {
            item.addEventListener("click", function (e) {
                menuItems.forEach(link => link.classList.remove("active"));
                this.classList.add("active");
            });
        });
    }

    initializeExcelExport() {
        const exportBtn = document.getElementById('exportExcel');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportToExcel());
        }
    }

    exportToExcel() {
        const table = document.querySelector('.student-table');
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.table_to_sheet(table);
        
        // Style the worksheet
        const range = XLSX.utils.decode_range(ws['!ref']);
        
        // Apply header styles
        this.applyHeaderStyles(ws, range);
        
        // Apply row styles
        this.applyRowStyles(ws, range);
        
        // Set column widths
        ws['!cols'] = this.getColumnWidths();
        
        // Create and download the file
        XLSX.utils.book_append_sheet(wb, ws, "Complete Documents");
        const currentDate = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, `IBACMI_Complete_Documents_${currentDate}.xlsx`);
    }

    applyHeaderStyles(ws, range) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const cell_address = XLSX.utils.encode_cell({r: 0, c: C});
            if (!ws[cell_address]) continue;
            
            ws[cell_address].s = {
                fill: { fgColor: { rgb: "800000" } },
                font: { color: { rgb: "FFFFFF" }, bold: true },
                alignment: { horizontal: "left", vertical: "center" }
            };
        }
    }

    applyRowStyles(ws, range) {
        for (let R = range.s.r + 1; R <= range.e.r; ++R) {
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cell_address = XLSX.utils.encode_cell({r: R, c: C});
                if (!ws[cell_address]) continue;
                
                ws[cell_address].s = {
                    fill: { fgColor: { rgb: R % 2 ? "FFFFFF" : "F9F9F9" } },
                    font: { color: { rgb: "000000" } },
                    alignment: { horizontal: "left", vertical: "center" },
                    border: {
                        top: { style: "thin", color: { rgb: "EEEEEE" } },
                        bottom: { style: "thin", color: { rgb: "EEEEEE" } }
                    }
                };
            }
        }
    }

    getColumnWidths() {
        return [
            { wch: 15 }, // Student ID
            { wch: 25 }, // Name
            { wch: 20 }, // Course
            { wch: 12 }, // Year Level
            { wch: 15 }, // Type
            { wch: 15 }, // Status
            { wch: 15 }, // Date Added
            { wch: 15 }  // Actions
        ];
    }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
    new CompleteDocuments();
});