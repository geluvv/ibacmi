class DocumentHandler {
    constructor() {
        this.initializeElements();
        this.bindEvents();
        // Make DocumentValidator optional and handle if it fails to load
        try {
            this.documentValidator = new DocumentValidator();
            console.log('DocumentValidator initialized successfully');
        } catch (error) {
            console.warn('DocumentValidator failed to initialize:', error);
            this.documentValidator = null;
        }
    }

    initializeElements() {
        this.dropAreas = document.querySelectorAll(".document-area");
        // Fix form ID detection to work with both newstudent.php and transferee.html
        this.form = document.getElementById("newStudentForm") || document.getElementById("transfereeForm");
        this.submitBtn = document.getElementById("submitBtn");
        
        // Add logging to help debug
        if (!this.form) {
            console.warn("Form not found - neither newStudentForm nor transfereeForm exists");
        } else {
            console.log("Form found:", this.form.id);
        }
        
        console.log(`Found ${this.dropAreas.length} drop areas`);
    }

    bindEvents() {
        this.setupDropAreas();
        this.setupFormSubmission();
    }

    setupDropAreas() {
        this.dropAreas.forEach((area) => {
            const fileInput = area.querySelector('input[type="file"]');
            const docType = area.dataset.type;

            if (!fileInput) {
                console.warn(`No file input found for ${docType}`);
                return;
            }
            
            console.log(`Setting up drop area for ${docType}`);

            // File input change event with validation
            fileInput.addEventListener("change", async (e) => {
                const file = e.target.files[0];
                if (file) {
                    console.log(`File selected for ${docType}: ${file.name}`);
                    await this.handleFileWithValidation(file, docType, fileInput);
                } else {
                    this.updateUploadStatus(docType, null);
                }
            });

            // Keyboard events for accessibility
            area.addEventListener("keydown", (e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    fileInput.click();
                }
            });
            
            // Drag and drop events
            area.addEventListener("dragover", (e) => {
                e.preventDefault();
                area.classList.add("highlight");
                area.closest('.uploader-container').classList.add('drag-over');
            });
            
            area.addEventListener("dragleave", () => {
                area.classList.remove("highlight");
                area.closest('.uploader-container').classList.remove('drag-over');
            });
            
            area.addEventListener("drop", (e) => {
                e.preventDefault();
                area.classList.remove("highlight");
                area.closest('.uploader-container').classList.remove('drag-over');
                
                if (e.dataTransfer.files.length > 0) {
                    const file = e.dataTransfer.files[0];
                    console.log(`File dropped on ${docType}: ${file.name}`);
                    
                    // Set the file to the input
                    try {
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;
                        
                        // Handle the file with validation
                        this.handleFileWithValidation(file, docType, fileInput);
                    } catch (error) {
                        console.error('Error handling dropped file:', error);
                    }
                }
            });
        });
    }

    async handleFileWithValidation(file, docType, fileInput) {
        try {
            console.log(`Processing file: ${file.name} for ${docType}`);
            
            // Basic file size check (10MB limit)
            if (file.size > 10 * 1024 * 1024) {
                console.log(`File ${file.name} too large`);
                fileInput.value = '';
                this.updateUploadStatus(docType, null);
                return;
            }
            
            // For 2x2 pictures, only check if it's an image file
            if (docType === 'id') {
                if (file.type.startsWith('image/')) {
                    this.updateUploadStatus(docType, file);
                    this.animateCheckbox(docType);
                    console.log(`✓ ${file.name} accepted for 2x2 picture`);
                } else {
                    fileInput.value = '';
                    this.updateUploadStatus(docType, null);
                    console.log(`✗ ${file.name} rejected for 2x2 picture: not an image`);
                }
                return;
            }
            
            // For other documents, use validator if available
            if (this.documentValidator) {
                this.showValidationProgress(docType);
                
                const validationResult = await this.documentValidator.validateDocument(file, docType);
                
                if (validationResult && validationResult.isValid) {
                    this.updateUploadStatus(docType, file);
                    this.animateCheckbox(docType);
                    console.log(`✓ ${file.name} validated for ${docType}`);
                } else {
                    fileInput.value = '';
                    this.updateUploadStatus(docType, null);
                    const message = validationResult ? validationResult.message : 'Unknown validation error';
                    console.log(`✗ ${file.name} rejected for ${docType}: ${message}`);
                }
            } else {
                // If no validator, accept all files (fail-safe)
                this.updateUploadStatus(docType, file);
                this.animateCheckbox(docType);
                console.log(`✓ ${file.name} accepted for ${docType} (no validation)`);
            }
            
        } catch (error) {
            console.error('File handling error:', error);
            // On error, accept the file (fail-safe approach)
            this.updateUploadStatus(docType, file);
            this.animateCheckbox(docType);
        }
    }

    showValidationProgress(docType) {
        console.log(`Showing validation progress for ${docType}`);
        const checkbox = document.getElementById(`checkbox-${docType}`);
        if (checkbox) {
            checkbox.innerHTML = '⟳';
            checkbox.classList.remove('checked');
            checkbox.style.display = 'flex';
            checkbox.style.animation = 'spin 0.5s linear';
            
            setTimeout(() => {
                if (checkbox) {
                    checkbox.style.animation = '';
                }
            }, 500);
        } else {
            console.warn(`Checkbox element not found for ${docType}`);
        }
    }

    animateCheckbox(docType) {
        console.log(`Animating checkbox for ${docType}`);
        const checkbox = document.getElementById(`checkbox-${docType}`);
        if (!checkbox) {
            console.warn(`Checkbox element not found for ${docType}`);
            return;
        }
        
        // First remove any existing animation
        checkbox.style.animation = 'none';
        
        // Force reflow to ensure animation restart
        void checkbox.offsetWidth;
        
        // Add the checkmark and show with animation
        checkbox.innerHTML = '✓';
        checkbox.classList.add('checked');
        checkbox.style.display = 'flex';
        checkbox.style.animation = 'checkboxPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
        
        // Reset animation after it completes
        setTimeout(() => {
            if (checkbox) {
                checkbox.style.animation = '';
            }
        }, 500);
    }

    updateUploadStatus(docType, file) {
        console.log(`Updating upload status for ${docType}: ${file ? file.name : 'no file'}`);
        const container = document.getElementById(`${docType}Container`);
        const checkbox = document.getElementById(`checkbox-${docType}`);
        const fileInfo = document.getElementById(`${docType}Info`);
        
        if (!container) {
            console.warn(`Container not found for ${docType}`);
            return;
        }
        
        if (!checkbox) {
            console.warn(`Checkbox not found for ${docType}`);
            return;
        }
        
        if (!fileInfo) {
            console.warn(`File info not found for ${docType}`);
            return;
        }
        
        if (file) {
            // File uploaded successfully
            container.classList.add('uploaded');
            
            // Show file info
            const fileName = fileInfo.querySelector('.file-name');
            const fileSize = fileInfo.querySelector('.file-size');
            
            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = this.formatFileSize(file.size);
            fileInfo.classList.add('show');
        } else {
            // No file or file removed
            container.classList.remove('uploaded');
            checkbox.classList.remove('checked');
            checkbox.style.display = 'none';
            fileInfo.classList.remove('show');
        }
    }

    formatFileSize(size) {
        // Format file size as human-readable string (e.g., "2 MB", "500 KB")
        if (size >= 1024 * 1024) {
            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        } else if (size >= 1024) {
            return `${(size / 1024).toFixed(1)} KB`;
        } else {
            return `${size} bytes`;
        }
    }

    setupFormSubmission() {
        if (!this.form) {
            console.warn('No form found, skipping submission setup');
            return;
        }

        console.log('Setting up form submission for', this.form.id);
        
        this.form.addEventListener("submit", (e) => {
            e.preventDefault();
            console.log("Form submission triggered");
            
            // Clear any previous validation errors
            this.dropAreas.forEach((area) => {
                const docType = area.dataset.type;
                this.clearValidationError(docType);
            });
            
            // No validation check for required documents - all documents are optional
            console.log("Proceeding with form submission - documents are optional");
            
            // Submit the form with whatever files are provided
            this.submitForm(e);
        });
    }

    submitForm(e) {
        if (!this.submitBtn) {
            console.warn('Submit button not found');
        } else {
            this.submitBtn.disabled = true;
            this.submitBtn.textContent = "Submitting...";
        }
        
        // Use FormData to collect all form inputs including files
        const formData = new FormData(this.form);
        
        // Submit via fetch API
        fetch(this.form.action, {
            method: "POST",
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log("Server response:", text);
            
            // Try to parse as JSON
            try {
                const result = JSON.parse(text);
                if (result.status === "success") {
                    this.showSuccess(result.message || "Form submitted successfully!");
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    }
                } else {
                    this.showError(result.message || "An error occurred.");
                }
            } catch (e) {
                // If not JSON, just show the response
                this.showSuccess("Form submitted successfully!");
            }
        })
        .catch(error => {
            console.error("Submission error:", error);
            this.showError(error.message);
        })
        .finally(() => {
            if (this.submitBtn) {
                this.submitBtn.disabled = false;
                this.submitBtn.textContent = "Submit";
            }
        });
    }

    showValidationError(docType, message) {
        const container = document.getElementById(`${docType}Container`);
        if (container) {
            container.classList.add('error');
            
            // Find or create error message element
            let errorMsg = container.querySelector('.error-message');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                container.appendChild(errorMsg);
            }
            errorMsg.textContent = message;
        }
    }

    clearValidationError(docType) {
        const container = document.getElementById(`${docType}Container`);
        if (container) {
            container.classList.remove('error');
            const errorMsg = container.querySelector('.error-message');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    }

    showSuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: "Success!",
                text: message,
                icon: "success",
                confirmButtonColor: "#800000",
            });
        } else {
            alert(`Success: ${message}`);
        }
    }

    showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: "Error",
                text: message,
                icon: "error",
                confirmButtonColor: "#800000",
            });
        } else {
            alert(`Error: ${message}`);
        }
    }
}
