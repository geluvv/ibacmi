class TransfereeHandler {
    constructor() {
        this.initializeElements();
        this.setupEventListeners();
    }

    initializeElements() {
        this.dropAreas = document.querySelectorAll('.document-area');
        this.uploadBtns = document.querySelectorAll('.upload-btn');
        this.docList = document.getElementById('documentList');
        this.submitBtn = document.getElementById('submitBtn');
        this.form = document.getElementById('transfereeForm');

        // Track uploaded documents
        this.uploadedDocs = {
            moral: false,
            birth: false,
            marriage: false,
            id: false,
            tor: false,
            honorable: false,
            gradeslip: false
        };

        // Enable submit button by default
        this.submitBtn.disabled = false;
    }

    setupEventListeners() {
        // Setup drag and drop for each document area
        this.dropAreas.forEach(area => {
            const fileInput = area.querySelector('input[type="file"]');
            const docType = area.dataset.type;

            area.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    this.handleFileUpload(fileInput.files[0], docType, fileInput);
                }
            });
            area.addEventListener('dragover', (e) => this.handleDragOver(e, area));
            area.addEventListener('dragleave', () => this.handleDragLeave(area));
            area.addEventListener('drop', (e) => this.handleDrop(e, area, fileInput, docType));
        });

        // Form submission
        this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));

        // Check for success parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success') && urlParams.get('success') === '1') {
            alert('Transferee Added Successfully');
        }
    }

    handleDragOver(e, area) {
        e.preventDefault();
        area.classList.add('highlight');
    }

    handleDragLeave(area) {
        area.classList.remove('highlight');
    }

    handleDrop(e, area, fileInput, docType) {
        e.preventDefault();
        area.classList.remove('highlight');

        if (e.dataTransfer.files.length > 0) {
            this.handleFileUpload(e.dataTransfer.files[0], docType, fileInput);
        }
    }

    handleFileUpload(file, docType, fileInput) {
        const docCard = document.createElement('div');
        docCard.className = 'document-card';
        docCard.id = `card-${docType}`;

        let docIcon = this.getDocumentIcon(file.type);

        docCard.innerHTML = this.createDocumentCardHTML(file, docType, docIcon);

        // Remove existing card
        const existingCard = document.getElementById(`card-${docType}`);
        if (existingCard) {
            existingCard.remove();
        }

        this.docList.appendChild(docCard);
        this.simulateUpload(docType, file, fileInput);
    }

    getDocumentIcon(fileType) {
        if (fileType.includes('pdf')) return '📕';
        if (fileType.includes('image')) return '🖼️';
        if (fileType.includes('word')) return '📘';
        return '📄';
    }

    createDocumentCardHTML(file, docType, docIcon) {
        return `
            <div class="document-icon">${docIcon}</div>
            <div class="document-info">
                <div class="document-name">${file.name}</div>
                <div class="document-type">${this.formatBytes(file.size)}</div>
                <div class="document-type-tag">${this.getDocTypeLabel(docType)}</div>
                <div class="progress-container">
                    <div class="progress-bar" id="progress-${docType}"></div>
                </div>
            </div>
            <button class="document-remove" onclick="transfereeHandler.removeDocument('${docType}', this)">✕</button>
        `;
    }

    getDocTypeLabel(type) {
        const labels = {
            moral: 'Certificate of Good Moral',
            birth: 'PSA Birth Certificate',
            marriage: 'PSA Marriage Certificate',
            id: '2x2 Picture',
            tor: 'Transcript of Record',
            honorable: 'Honorable Dismissal',
            gradeslip: 'Grade Slip'
        };
        return labels[type] || 'Document';
    }

    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    simulateUpload(docType, file, fileInput) {
        let progress = 0;
        const progressBar = document.getElementById(`progress-${docType}`);

        const interval = setInterval(() => {
            progress += 5;
            progressBar.style.width = `${progress}%`;

            if (progress >= 100) {
                clearInterval(interval);
                this.uploadedDocs[docType] = true;
                this.updateDocumentStatus(docType);
            }
        }, 100);
    }

    updateDocumentStatus(docType) {
        const statusEl = document.getElementById(`status-${docType}`);
        if (statusEl) {
            statusEl.classList.remove('status-pending');
            statusEl.classList.add('status-complete');
        }

        if (docType === 'honorable') {
            const honorableStatus = document.getElementById('status-honoarable');
            if (honorableStatus) {
                honorableStatus.classList.remove('status-pending');
                honorableStatus.classList.add('status-complete');
            }
        }
    }

    removeDocument(docType, button) {
        const card = button.parentElement;
        card.remove();

        this.uploadedDocs[docType] = false;
        
        const statusEl = document.getElementById(`status-${docType}`);
        if (statusEl) {
            statusEl.classList.remove('status-complete');
            statusEl.classList.add('status-pending');
        }

        if (docType === 'honorable') {
            const honorableStatus = document.getElementById('status-honoarable');
            if (honorableStatus) {
                honorableStatus.classList.remove('status-complete');
                honorableStatus.classList.add('status-pending');
            }
        }

        const fileInput = document.getElementById(`${docType}File`);
        if (fileInput) {
            fileInput.value = '';
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();
        const submitButton = this.form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        
        try {
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            submitButton.disabled = true;

            const formData = new FormData(this.form);
            const response = await fetch('transferee.php', {
                method: 'POST',
                body: formData
            });

            const rawResponse = await response.text();
            console.log('Raw server response:', rawResponse);

            const data = JSON.parse(rawResponse);

            if (data.status === 'success') {
                await this.showSuccessMessage(data.message);
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'Unknown error occurred');
            }
        } catch (error) {
            console.error('Submission error:', error);
            await this.showErrorMessage(error.message);
        } finally {
            submitButton.innerHTML = originalButtonText;
            submitButton.disabled = false;
        }
    }

    async showSuccessMessage(message) {
        await Swal.fire({
            title: 'SUCCESS! 🎉',
            text: message,
            icon: 'success',
            confirmButtonText: 'Continue',
            confirmButtonColor: '#800000',
            background: '#fff',
            showClass: { popup: 'animate__animated animate__fadeInDown' },
            hideClass: { popup: 'animate__animated animate__fadeOutUp' }
        });
    }

    async showErrorMessage(message) {
        await Swal.fire({
            title: 'Oops...',
            text: message || 'An unexpected error occurred',
            icon: 'error',
            confirmButtonColor: '#800000'
        });
    }
}

// Initialize when DOM is loaded
let transfereeHandler;
document.addEventListener('DOMContentLoaded', () => {
    transfereeHandler = new TransfereeHandler();
});