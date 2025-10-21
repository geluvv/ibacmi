class DocumentViewer {
    constructor() {
        this.initializeElements();
        this.bindEvents();
    }

    initializeElements() {
        this.documentModal = document.getElementById("documentModal");
        this.documentList = document.getElementById("documentList");
        this.viewerSection = document.getElementById("documentViewerSection");
        this.viewer = document.getElementById("documentViewer");
        this.viewerTitle = document.getElementById("documentViewerTitle");
    }

    bindEvents() {
        // View documents buttons
        document.querySelectorAll(".view-docs").forEach(button => {
            button.addEventListener("click", (e) => {
                this.handleViewDocuments(e.currentTarget);
            });
        });
    }

    handleViewDocuments(button) {
        const studentId = button.getAttribute("data-student-id");
        const studentName = button.getAttribute("data-student-name");
        const studentType = button.getAttribute("data-student-type");

        this.documentModal.setAttribute("data-student-id", studentId);
        this.documentModal.setAttribute("data-student-name", studentName);
        this.documentModal.setAttribute("data-student-type", studentType);

        document.getElementById("documentModalLabel").textContent = `Documents for ${studentName}`;
        this.showLoading();
        this.fetchStudentDocuments(studentId, studentType);
    }

    showLoading() {
        this.documentList.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading documents...</p>
            </div>
        `;
    }

    async fetchStudentDocuments(studentId, studentType) {
        try {
            const response = await fetch(`get_documents.php?id=${studentId}&type=${encodeURIComponent(studentType)}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            this.displayDocuments(data, studentType);
        } catch (error) {
            this.documentList.innerHTML = `<div class="alert alert-danger">Error loading documents: ${error.message}</div>`;
        }
    }

    displayDocuments(data, studentType) {
        if (!data.documents || data.documents.length === 0) {
            this.documentList.innerHTML = '<div class="alert alert-info">No documents found</div>';
            return;
        }

        let html = '';
        html += this.getDocumentStyles();
        html += '<div class="document-grid">';
        data.documents.forEach(doc => {
            html += this.createDocumentCard(doc);
        });
        html += '</div>';
        html += this.createLightbox(); // Always add the lightbox to the DOM

        this.documentList.innerHTML = html;
        this.initializeDocumentEvents();
    }

    getDocumentStyles() {
        return `
            <style>
                .document-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    padding: 20px;
                }
                .document-card {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    transition: transform 0.2s, box-shadow 0.2s;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    height: 280px;
                    cursor: pointer;
                }
                .document-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                }
                .document-thumbnail {
                    width: 100%;
                    height: 160px;
                    background-color: #f8f9fa;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-bottom: 1px solid #eee;
                    overflow: hidden;
                }
                .document-thumbnail img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .document-thumbnail i {
                    font-size: 3rem;
                    color: #800000;
                }
                .document-info {
                    padding: 15px;
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
                .document-name {
                    font-size: 0.9rem;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #333;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                .document-status {
                    margin-bottom: 8px;
                }
                .document-actions {
                    display: flex;
                    gap: 8px;
                    margin-top: auto;
                }
                .btn-sm {
                    padding: 0.25rem 0.5rem;
                    font-size: 0.875rem;
                }
                /* Lightbox styles */
                .document-lightbox {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.9);
                    display: none;
                    z-index: 9999;
                    padding: 2rem;
                    opacity: 1;
                    transition: opacity 0.3s ease;
                }
                .lightbox-content {
                    max-width: 90%;
                    max-height: 90vh;
                    margin: auto;
                    position: relative;
                }
                .lightbox-close {
                    position: absolute;
                    top: -2rem;
                    right: 0;
                    color: white;
                    font-size: 1.5rem;
                    cursor: pointer;
                    padding: 0.5rem;
                    z-index: 10000;
                }
                .lightbox-viewer {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .lightbox-image {
                    max-width: 100%;
                    max-height: 85vh;
                    object-fit: contain;
                }
                .lightbox-iframe {
                    width: 100%;
                    height: 85vh;
                    border: none;
                    background: white;
                }
                @media (max-width: 992px) {
                    .document-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }
                @media (max-width: 576px) {
                    .document-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        `;
    }

    createDocumentCard(doc) {
        const fileExists = doc.exists;
        const isSubmitted = doc.submitted;
        const fileExtension = doc.path ? doc.path.split('.').pop().toLowerCase() : '';
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
        const isPdf = fileExtension === 'pdf';

        return `
            <div class="document-card" data-path="${doc.path || ''}" data-name="${doc.name}">
                <div class="document-thumbnail">
                    ${isImage ? `
                        <img src="${doc.path}" alt="${doc.name}" loading="lazy">
                    ` : `
                        <i class="fas ${isPdf ? 'fa-file-pdf' : 'fa-file-alt'}"></i>
                    `}
                </div>
                <div class="document-info">
                    <div class="document-name">${doc.name}</div>
                    <span class="badge ${isSubmitted ? 'bg-success' : 'bg-warning'} document-status">
                        ${isSubmitted ? 'Submitted' : 'Not Submitted'}
                    </span>
                    ${doc.path ? `
                        <div class="document-actions">
                            <a href="${doc.path}" class="btn btn-sm btn-secondary" download onclick="event.stopPropagation();">
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); documentViewer.printDocument('${doc.path}', '${doc.name}')">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    ` : '<div class="text-muted small">No file uploaded</div>'}
                </div>
            </div>
        `;
    }

    createLightbox() {
        // Only one lightbox per modal
        return `
            <div class="document-lightbox" style="display:none;">
                <div class="lightbox-content">
                    <div class="lightbox-close"><i class="fas fa-times"></i></div>
                    <div class="lightbox-viewer"></div>
                </div>
            </div>
        `;
    }

    initializeDocumentEvents() {
        // Card click: open lightbox
        this.documentList.querySelectorAll('.document-card').forEach(card => {
            card.addEventListener('click', (e) => {
                // Prevent if clicking on download/print buttons
                if (e.target.closest('.document-actions')) return;
                const path = card.getAttribute('data-path');
                const name = card.getAttribute('data-name');
                if (path) this.openLightbox(path, name);
            });
        });

        // Lightbox close button
        const lightbox = this.documentList.querySelector('.document-lightbox');
        if (lightbox) {
            lightbox.querySelector('.lightbox-close').onclick = () => this.closeLightbox();
            // Close on background click
            lightbox.onclick = (e) => {
                if (e.target === lightbox) this.closeLightbox();
            };
        }
    }

    openLightbox(path, name) {
        const lightbox = this.documentList.querySelector('.document-lightbox');
        if (!lightbox) return;

        const fileExt = path.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
        const isPdf = fileExt === 'pdf';

        const viewer = lightbox.querySelector('.lightbox-viewer');
        viewer.innerHTML = isImage
            ? `<img src="${path}" alt="${name}" class="lightbox-image">`
            : isPdf
                ? `<iframe src="${path}" class="lightbox-iframe" title="${name}"></iframe>`
                : `<div class="text-center text-white p-5">
                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                        <p>This document cannot be previewed directly.</p>
                        <a href="${path}" download class="btn btn-light mt-2">
                            <i class="fas fa-download me-2"></i> Download Document
                        </a>
                    </div>`;

        lightbox.style.display = 'flex';

        // Escape key closes lightbox
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                this.closeLightbox();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    closeLightbox() {
        const lightbox = this.documentList.querySelector('.document-lightbox');
        if (lightbox) lightbox.style.display = 'none';
    }

    // Optional: implement printDocument if needed
    printDocument(path, name) {
        const fileExt = path.split('.').pop().toLowerCase();
        const printFrame = document.createElement('iframe');
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        document.body.appendChild(printFrame);

        const frameDoc = printFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print - ${name}</title>
                <style>
                    body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                    .print-container { max-width: 100%; margin: 0 auto; }
                    .document-title { text-align: center; margin-bottom: 20px; font-size: 18px; font-weight: bold; }
                    img { max-width: 100%; height: auto; display: block; margin: 0 auto; }
                    iframe { width: 100%; height: 100vh; border: none; }
                    @media print { body { padding: 0; } .print-container { page-break-after: always; } }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <div class="document-title">${name}</div>
                    ${['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)
                        ? `<img src="${path}" alt="${name}">`
                        : fileExt === 'pdf'
                            ? `<iframe src="${path}"></iframe>`
                            : '<div>Document cannot be displayed</div>'
                    }
                </div>
            </body>
            </html>
        `);
        frameDoc.close();

        printFrame.onload = function() {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
            setTimeout(() => {
                document.body.removeChild(printFrame);
            }, 500);
        };
    }
}

// Make sure to create a global instance if you use inline onclick handlers
window.documentViewer = new DocumentViewer();