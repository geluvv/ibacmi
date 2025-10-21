<?php
// This file contains the shared form HTML and JavaScript for new student registration
// It can be included by both admin and staff pages
?>

<div class="form-container">
    <div class="form-header">
        <h2><i class="fas fa-user-plus"></i> Add New Student</h2>
        <p>Complete the form below to register a new student</p>
    </div>

    <form id="newStudentForm" action="<?php echo $form_action ?? 'newstudent.php'; ?>" method="POST" enctype="multipart/form-data">
        <div class="form-content">
            <!-- Student Information Section -->
            <div class="info-section">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3>Student Information</h3>
                </div>

                <div class="form-grid">
                    <div class="form-field full-width">
                        <label class="form-label">
                            Student ID <span class="required-indicator">*</span>
                        </label>
                        <input type="text" name="studentID" placeholder="Enter student ID" required class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            Course <span class="required-indicator">*</span>
                        </label>
                        <select name="studentCourse" required class="form-select">
                            <option value="">Select course</option>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCRIM">BSCRIM</option>
                            <option value="BEED">BEED</option>
                            <option value="BECED">BECED</option>
                            <option value="BPA">BPA</option>
                            <option value="BSE">BSE</option>
                            <option value="BSHM">BSHM</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            Year Level <span class="required-indicator">*</span>
                        </label>
                        <select name="yearLevel" required class="form-select">
                            <option value="">Select year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>

                    <!-- Name Fields Column -->
                    <div class="name-column">
                        <div class="form-field">
                            <label class="form-label">
                                First Name <span class="required-indicator">*</span>
                            </label>
                            <input type="text" name="firstName" placeholder="First name" required class="form-input">
                        </div>

                        <div class="form-field">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middleName" placeholder="Middle name" class="form-input">
                        </div>

                        <div class="form-field">
                            <label class="form-label">
                                Last Name <span class="required-indicator">*</span>
                            </label>
                            <input type="text" name="lastName" placeholder="Last name" required class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="upload-section">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h3>Required Documents</h3>
                </div>

                <div class="upload-grid">
                    <div class="upload-item" data-type="card138">
                        <input type="file" name="card138File" accept=".pdf,.docx,.jpg,.png" style="display: none;">
                        <div class="upload-title">Card 138</div>
                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="upload-text">Click to upload</div>
                    </div>

                    <div class="upload-item" data-type="moral">
                        <input type="file" name="moralFile" accept=".pdf,.docx,.jpg,.png" style="display: none;">
                        <div class="upload-title">Good Moral</div>
                        <div class="upload-icon"><i class="fas fa-certificate"></i></div>
                        <div class="upload-text">Click to upload</div>
                    </div>

                    <div class="upload-item" data-type="birth">
                        <input type="file" name="birthFile" accept=".pdf,.docx,.jpg,.png" style="display: none;">
                        <div class="upload-title">Birth Certificate</div>
                        <div class="upload-icon"><i class="fas fa-id-card"></i></div>
                        <div class="upload-text">Click to upload</div>
                    </div>

                    <!-- Marriage Certificate with checkbox -->
                    <div class="upload-item" data-type="marriage">
                        <input type="file" name="marriageFile" accept=".pdf,.docx,.jpg,.png" style="display: none;">
                        <input type="checkbox" name="marriageRequired" value="1" style="position: absolute; top: 8px; right: 8px; z-index: 10;" title="Check if marriage certificate is required">
                        <div class="upload-title">Marriage Certificate</div>
                        <div class="upload-icon"><i class="fas fa-heart"></i></div>
                        <div class="upload-text">Click to upload</div>
                    </div>

                    <div class="upload-item photo-upload" data-type="id">
                        <input type="file" name="idFile" accept=".jpg,.png" style="display: none;">
                        <div class="upload-title">2x2 Picture</div>
                        <div class="upload-icon"><i class="fas fa-camera"></i></div>
                        <div class="upload-text">Click to upload photo</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="submit-container">
            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Submit New Student
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const uploadItems = document.querySelectorAll(".upload-item");

    uploadItems.forEach((item) => {
        const fileInput = item.querySelector('input[type="file"]');
        const uploadText = item.querySelector('.upload-text');
        const uploadIcon = item.querySelector('.upload-icon i');
        const marriageCheckbox = item.querySelector('input[name="marriageRequired"]');

        // Click to upload (avoid triggering on checkbox click)
        item.addEventListener("click", (e) => {
            if (e.target.type === 'checkbox') return;
            fileInput.click();
        });

        // Handle file selection
        fileInput.addEventListener("change", (e) => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                item.classList.remove('error');
                item.classList.add('uploaded');
                uploadText.textContent = file.name;
                uploadIcon.className = 'fas fa-check-circle';
            }
        });

        // Handle marriage certificate checkbox
        if (marriageCheckbox) {
            // Set initial state
            marriageCheckbox.addEventListener('change', function() {
                if (!this.checked) {
                    // If not required, reset the upload
                    fileInput.value = '';
                    item.classList.remove('uploaded');
                    uploadText.textContent = 'Not required';
                    uploadIcon.className = 'fas fa-times-circle';
                    item.style.opacity = '0.5';
                } else {
                    // If required, enable upload
                    uploadText.textContent = 'Click to upload';
                    uploadIcon.className = 'fas fa-heart';
                    item.style.opacity = '1';
                }
            });
        }

        // Drag and drop functionality
        item.addEventListener("dragover", (e) => {
            e.preventDefault();
            item.style.borderColor = 'var(--primary)';
            item.style.background = 'rgba(128, 0, 0, 0.05)';
        });

        item.addEventListener("dragleave", () => {
            item.style.borderColor = 'var(--border-light)';
            item.style.background = 'white';
        });

        item.addEventListener("drop", (e) => {
            e.preventDefault();
            item.style.borderColor = 'var(--border-light)';
            item.style.background = 'white';

            if (e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                
                item.classList.remove('error');
                item.classList.add('uploaded');
                uploadText.textContent = file.name;
                uploadIcon.className = 'fas fa-check-circle';
            }
        });
    });

    // Form submission handling
    const form = document.getElementById('newStudentForm');
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            submitButton.disabled = true;

            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });

                const rawResponse = await response.text();
                console.log('Raw server response:', rawResponse);

                let data;
                try {
                    data = JSON.parse(rawResponse);
                } catch (error) {
                    console.error('JSON parse error:', error);
                    throw new Error('Server response was not valid JSON');
                }

                if (data.status === 'success') {
                    await Swal.fire({
                        title: 'SUCCESS! 🎉',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'Continue',
                        confirmButtonColor: '#800000',
                        background: '#fff'
                    });
                    
                    // Reset form
                    this.reset();
                    
                    // Reset upload items visual state
                    const uploadItems = document.querySelectorAll('.upload-item');
                    uploadItems.forEach(item => {
                        item.classList.remove('uploaded', 'error');
                        item.style.opacity = '1';
                        const uploadText = item.querySelector('.upload-text');
                        const uploadIcon = item.querySelector('.upload-icon i');
                        const dataType = item.getAttribute('data-type');
                        
                        // Reset text based on upload type
                        if (dataType === 'id') {
                            uploadText.textContent = 'Click to upload photo';
                        } else {
                            uploadText.textContent = 'Click to upload';
                        }
                        
                        // Reset icon based on upload type
                        switch(dataType) {
                            case 'card138':
                                uploadIcon.className = 'fas fa-file-alt';
                                break;
                            case 'moral':
                                uploadIcon.className = 'fas fa-certificate';
                                break;
                            case 'birth':
                                uploadIcon.className = 'fas fa-id-card';
                                break;
                            case 'marriage':
                                uploadIcon.className = 'fas fa-heart';
                                break;
                            case 'id':
                                uploadIcon.className = 'fas fa-camera';
                                break;
                        }
                    });
                } else if (data.type === 'invalid_documents') {
                    // Handle invalid document errors
                    let errorMessage = 'The following documents are invalid:\n\n';
                    data.invalid_documents.forEach(doc => {
                        errorMessage += `• ${doc.document}: ${doc.error}\n`;
                    });
                    errorMessage += '\nPlease upload the correct document types and try again.';
                    
                    await Swal.fire({
                        title: 'Invalid Documents',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'Fix Documents',
                        confirmButtonColor: '#800000'
                    });
                    
                    // Reset invalid document upload items
                    data.invalid_documents.forEach(invalidDoc => {
                        const fileInput = document.querySelector(`input[name="${invalidDoc.document}File"]`);
                        if (fileInput) {
                            const uploadItem = fileInput.closest('.upload-item');
                            if (uploadItem) {
                                uploadItem.classList.remove('uploaded');
                                uploadItem.classList.add('error');
                                const uploadText = uploadItem.querySelector('.upload-text');
                                const uploadIcon = uploadItem.querySelector('.upload-icon i');
                                uploadText.textContent = 'Invalid file - please re-upload';
                                uploadIcon.className = 'fas fa-exclamation-triangle';
                            }
                            fileInput.value = '';
                        }
                    });
                } else if (data.type === 'duplicate_id') {
                    await Swal.fire({
                        title: 'Duplicate Student ID',
                        text: data.message,
                        icon: 'warning',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#800000'
                    });
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            } catch (error) {
                console.error('Submission error:', error);
                await Swal.fire({
                    title: 'Oops...',
                    text: error.message || 'An unexpected error occurred',
                    icon: 'error',
                    confirmButtonColor: '#800000'
                });
            } finally {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            }
        });
    }
});
</script>