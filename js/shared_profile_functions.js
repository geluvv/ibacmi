// Enhanced Profile Management
document.addEventListener('DOMContentLoaded', function() {
    // Profile picture preview
    const profilePictureInput = document.getElementById('profilePictureInput');
    const profilePreview = document.getElementById('profilePreview');
    
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePreview.src = e.target.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }

    // Toggle password visibility
    document.querySelectorAll('.toggle-password-modern').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // Forgot password modal
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal')) || new bootstrap.Modal(document.getElementById('profileModal'));
    const forgotPasswordModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
    
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            profileModal.hide();
            forgotPasswordModal.show();
        });
    }

    // Change password handler
    const changePasswordBtn = document.getElementById('changePassword');
    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', async function() {
            const form = document.getElementById('passwordForm');
            const formData = new FormData(form);
            
            // Validate passwords match
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');
            
            if (newPassword !== confirmPassword) {
                showAlert('New passwords do not match!', 'danger');
                return;
            }
            
            if (newPassword.length < 8) {
                showAlert('Password must be at least 8 characters long!', 'danger');
                return;
            }

            try {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing...';

                const response = await fetch('update_password.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    forgotPasswordModal.hide();
                    showAlert('Password updated successfully!', 'success');
                    form.reset();
                } else {
                    showAlert(result.message || 'Failed to update password', 'danger');
                }
            } catch (error) {
                showAlert('Error updating password. Please try again.', 'danger');
            } finally {
                this.disabled = false;
                this.innerHTML = 'Change Password';
            }
        });
    }

    // Enhanced save profile handler
    const saveProfileBtn = document.getElementById('saveProfile');
    if (saveProfileBtn) {
        saveProfileBtn.addEventListener('click', async function() {
            const form = document.getElementById('profileForm');
            const formData = new FormData(form);

            try {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    profileModal.hide();
                    showAlert('Profile updated successfully!', 'success');
                    
                    // Update sidebar profile info
                    if (result.data) {
                        const profileName = document.querySelector('.profile-info h6');
                        const profileRole = document.querySelector('.profile-role');
                        const profileImage = document.querySelector('.profile-image img');
                        
                        if (profileName) {
                            profileName.textContent = `${result.data.first_name} ${result.data.last_name}`;
                        }
                        if (profileRole) {
                            profileRole.textContent = result.data.position || result.data.role;
                        }
                        if (profileImage && result.data.profile_picture) {
                            profileImage.src = result.data.profile_picture;
                        }
                    }
                    
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Failed to update profile', 'danger');
                }
            } catch (error) {
                showAlert('Error updating profile. Please try again.', 'danger');
            } finally {
                this.disabled = false;
                this.innerHTML = 'Save Changes';
            }
        });
    }
});

// Image modal for ID preview
function openImageModal(imageSrc) {
    const modalImage = document.getElementById('modalImage');
    const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    
    modalImage.src = imageSrc;
    imageModal.show();
}

// Alert function
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer') || document.body;
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
            <div>${message}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 150);
    }, 5000);
}