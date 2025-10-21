class LackingDocuments {
    constructor() {
        this.initializeSidebar();
        this.initializeExcelExport();
        this.initializeUpdateForm();
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
            item.addEventListener("click", function(e) {
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
        
        const range = XLSX.utils.decode_range(ws['!ref']);
        
        // Apply styles to header row
        this.applyHeaderStyles(ws, range);
        
        // Apply row styles
        this.applyRowStyles(ws, range);
        
        // Set column widths
        ws['!cols'] = this.getColumnWidths();
        
        XLSX.utils.book_append_sheet(wb, ws, "Lacking Documents");
        
        const currentDate = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, `IBACMI_Lacking_Documents_${currentDate}.xlsx`);
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
            { wch: 40 }, // Missing Documents
            { wch: 15 }, // Date Added
            { wch: 15 }  // Actions
        ];
    }

    initializeUpdateForm() {
        const form = document.getElementById('updateDocumentsForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }

    handleFormSubmit(e) {
        const fileInputs = e.target.querySelectorAll('.document-file');
        let hasFiles = false;
        
        fileInputs.forEach(input => {
            if (input.files.length > 0) {
                hasFiles = true;
            }
        });
        
        if (!hasFiles) {
            e.preventDefault();
            alert('Please select at least one file to upload.');
        }
    }

    openUpdateModal(studentType, recordId, studentId, missingDocsJson) {
        const missingDocs = JSON.parse(missingDocsJson);
        
        document.getElementById('modalStudentId').textContent = studentId;
        document.getElementById('studentType').value = studentType;
        document.getElementById('recordId').value = recordId;
        document.getElementById('studentIdInput').value = studentId;
        
        const documentsList = document.getElementById('documentsList');
        documentsList.innerHTML = '';
        
        if (missingDocs.length === 0) {
            this.showNoMissingDocsMessage(documentsList);
            return;
        }
        
        this.createDocumentItems(documentsList, missingDocs);
        this.initializeFileInputListeners();
        
        const modal = new bootstrap.Modal(document.getElementById('updateDocumentsModal'));
        modal.show();
    }

    showNoMissingDocsMessage(container) {
        container.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                All required documents have been submitted.
            </div>
        `;
    }

    createDocumentItems(container, docs) {
        docs.forEach(doc => {
            const docItem = this.createDocumentItem(doc);
            container.appendChild(docItem);
        });
    }

    createDocumentItem(doc) {
        const docItem = document.createElement('div');
        docItem.className = 'document-item';
        docItem.dataset.docCode = doc.doc_code;
        
        docItem.innerHTML = `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="${doc.doc_code}_submitted" id="${doc.doc_code}_submitted" value="1">
                <label class="form-check-label fw-bold" for="${doc.doc_code}_submitted">
                    ${doc.doc_name}
                    <span class="document-status status-missing">Missing</span>
                </label>
            </div>
            <div class="mt-2">
                <label for="${doc.doc_code}_file" class="form-label">Upload Document:</label>
                <input class="form-control document-file" type="file" id="${doc.doc_code}_file" name="${doc.doc_code}_file">
                <small class="text-muted">Upload the scanned copy or photo of the document.</small>
            </div>
        `;
        
        return docItem;
    }

    initializeFileInputListeners() {
        document.querySelectorAll('.document-file').forEach(fileInput => {
            fileInput.addEventListener('change', (e) => this.handleFileSelection(e));
        });
    }

    handleFileSelection(e) {
        const fileInput = e.target;
        const docItem = fileInput.closest('.document-item');
        const checkbox = docItem.querySelector('.form-check-input');
        
        if (fileInput.files.length > 0) {
            checkbox.checked = true;
            docItem.classList.add('file-selected');
        } else {
            checkbox.checked = false;
            docItem.classList.remove('file-selected');
        }
    }

    handleSuccessfulUpload(docCode) {
        const docItem = document.querySelector(`.document-item[data-doc-code="${docCode}"]`);
        
        if (docItem) {
            docItem.classList.add('submitted');
            
            const statusLabel = docItem.querySelector('.document-status');
            if (statusLabel) {
                statusLabel.className = 'document-status status-submitted';
                statusLabel.textContent = 'Submitted';
            }
            
            const fileInput = docItem.querySelector('.document-file');
            if (fileInput) {
                fileInput.disabled = true;
            }
            
            const checkbox = docItem.querySelector('.form-check-input');
            if (checkbox) {
                checkbox.disabled = true;
                checkbox.checked = true;
            }
        }
    }
}

// Initialize when DOM is loaded
let lackingDocuments;
document.addEventListener('DOMContentLoaded', () => {
    lackingDocuments = new LackingDocuments();
});

// Make openUpdateModal globally available
window.openUpdateModal = function(studentType, recordId, studentId, missingDocsJson) {
    lackingDocuments.openUpdateModal(studentType, recordId, studentId, missingDocsJson);
};