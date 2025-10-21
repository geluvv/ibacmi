<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only include db_connect if $conn is not already defined
if (!isset($conn)) {
    require_once '../db_connect.php';
}

// Check for both possible session variables for compatibility
$staffId = $_SESSION['staff_user_id'] ?? $_SESSION['staff_id'] ?? null;
$staffInfo = [];

if ($staffId) {
    try {
        // First try to get from staff_profiles table with user info
        $stmt = $conn->prepare("
            SELECT 
                sp.first_name, sp.last_name, sp.middle_name, sp.birthday, 
                sp.email, sp.address, sp.department, sp.position, sp.phone,
                sp.profile_picture, sp.id_document,
                su.username, su.email as user_email, su.role
            FROM staff_users su
            LEFT JOIN staff_profiles sp ON su.id = sp.staff_user_id 
            WHERE su.id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if ($data) {
            // Merge profile and user data
            $staffInfo = [
                'first_name' => $data['first_name'] ?: explode(' ', $data['username'])[0],
                'last_name' => $data['last_name'] ?: (explode(' ', $data['username'])[1] ?? 'Member'),
                'middle_name' => $data['middle_name'] ?: '',
                'birthday' => $data['birthday'] ?: '',
                'email' => $data['email'] ?: $data['user_email'] ?: '',
                'phone' => $data['phone'] ?: '',
                'address' => $data['address'] ?: '',
                'department' => $data['department'] ?: '',
                'position' => $data['position'] ?: 'Staff',
                'profile_picture' => $data['profile_picture'] ?: '',
                'id_document' => $data['id_document'] ?: '',
                'role' => $data['role'] ?: 'Staff'
            ];
        } else {
            // Fallback: create default profile
            $staffInfo = [
                'first_name' => 'Staff',
                'last_name' => 'Member',
                'middle_name' => '',
                'birthday' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'department' => '',
                'position' => 'Staff',
                'profile_picture' => '',
                'id_document' => '',
                'role' => 'Staff'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error fetching staff profile: " . $e->getMessage());
        // Provide default values on error
        $staffInfo = [
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'middle_name' => '',
            'birthday' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'department' => '',
            'position' => 'Staff',
            'profile_picture' => '',
            'id_document' => '',
            'role' => 'Staff'
        ];
    }
} else {
    // No staff ID in session - redirect to login
    if (!headers_sent()) {
        header('Location: stafflogin.php');
        exit();
    }
}
?>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="profileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-2">
                <form id="profileForm" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Left Column - Profile Picture -->
                        <div class="col-md-4 text-center">
                            <div class="profile-upload-container">
                                <div class="profile-picture-wrapper" onclick="document.getElementById('profilePictureInput').click()">
                                    <img id="profilePreview"
                                         src="<?php echo !empty($staffInfo['profile_picture']) ? htmlspecialchars($staffInfo['profile_picture']) : '../photos/default-avatar.png'; ?>"
                                         class="profile-preview-compact"
                                         alt="Profile Picture">
                                    <div class="profile-overlay-compact">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                </div>
                                <input type="file" class="d-none" id="profilePictureInput" name="profile_picture" accept="image/*">
                                <p class="text-muted small mt-2 mb-0 cursor-pointer" onclick="document.getElementById('profilePictureInput').click()">
                                    Click to change
                                </p>
                            </div>
                            
                            <!-- ID Document Section -->
                            <div class="mt-3">
                                <label class="form-label text-muted small fw-medium">Valid ID</label>
                                <div class="id-display-section">
                                    <?php if (!empty($staffInfo['id_document'])): ?>
                                        <div class="current-id-display-compact mb-2">
                                            <img src="<?php echo htmlspecialchars($staffInfo['id_document']); ?>"
                                                 class="id-preview-compact" alt="Valid ID Document"
                                                 onclick="openImageModal(this.src)">
                                            <small class="text-success mt-1 d-block">
                                                <i class="fas fa-check-circle me-1"></i>ID Verified
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-id-card text-muted mb-2" style="font-size: 2rem;"></i>
                                            <p class="text-muted small mb-0">No ID document on file</p>
                                            <small class="text-muted">Contact administrator if ID update is needed</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column - Form Fields -->
                        <div class="col-md-8">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label-compact">First Name *</label>
                                    <input type="text" class="form-control profile-input-compact" name="first_name"
                                           value="<?php echo htmlspecialchars($staffInfo['first_name'] ?? ''); ?>"
                                           placeholder="John" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Middle Name</label>
                                    <input type="text" class="form-control profile-input-compact" name="middle_name"
                                           value="<?php echo htmlspecialchars($staffInfo['middle_name'] ?? ''); ?>"
                                           placeholder="Middle Name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Last Name *</label>
                                    <input type="text" class="form-control profile-input-compact" name="last_name"
                                           value="<?php echo htmlspecialchars($staffInfo['last_name'] ?? ''); ?>"
                                           placeholder="Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Birthday</label>
                                    <input type="date" class="form-control profile-input-compact" name="birthday"
                                           value="<?php echo htmlspecialchars($staffInfo['birthday'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Email Address *</label>
                                    <input type="email" class="form-control profile-input-compact" name="email"
                                           value="<?php echo htmlspecialchars($staffInfo['email'] ?? ''); ?>"
                                           placeholder="john.doe@example.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Phone Number</label>
                                    <input type="tel" class="form-control profile-input-compact" name="phone"
                                           value="<?php echo htmlspecialchars($staffInfo['phone'] ?? ''); ?>"
                                           placeholder="+63 912 345 6789">
                                </div>
                                <div class="col-12">
                                    <label class="form-label-compact">Address</label>
                                    <textarea class="form-control profile-input-compact" name="address" rows="2"
                                              placeholder="Enter your complete address"><?php echo htmlspecialchars($staffInfo['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Department</label>
                                    <input type="text" class="form-control profile-input-compact" name="department"
                                           value="<?php echo htmlspecialchars($staffInfo['department'] ?? ''); ?>"
                                           placeholder="Department">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-compact">Position</label>
                                    <input type="text" class="form-control profile-input-compact" name="position"
                                           value="<?php echo htmlspecialchars($staffInfo['position'] ?? ''); ?>"
                                           placeholder="Staff Position">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Change Password Link -->
                    <div class="text-center mt-3">
                        <a href="#" class="text-decoration-none" style="color: #8B4513;" id="forgotPasswordLink">
                            Change Password?
                        </a>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 px-4 py-3">
                <div class="d-grid gap-2 d-md-flex w-100">
                    <button type="button" class="btn btn-light-compact flex-fill" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary-compact flex-fill" id="saveProfile">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="forgotPasswordModalLabel">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control profile-input" name="current_password"
                                   placeholder="Enter current password" required>
                            <button class="btn btn-outline-secondary toggle-password-modern" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control profile-input" name="new_password"
                                   placeholder="Enter new password" required>
                            <button class="btn btn-outline-secondary toggle-password-modern" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Password must be at least 8 characters long</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control profile-input" name="confirm_password"
                                   placeholder="Confirm new password" required>
                            <button class="btn btn-outline-secondary toggle-password-modern" type="button">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <div class="d-grid gap-2 d-md-flex w-100">
                    <button type="button" class="btn btn-light-modern flex-fill" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary-modern flex-fill" id="changePassword">
                        Change Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal for ID Preview -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="imageModalLabel">ID Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="/placeholder.svg" class="img-fluid rounded-3" alt="ID Document">
            </div>
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<style>
/* Profile Modal Styles */
.modal-lg {
    max-width: 900px !important;
}

.modal-content {
    border-radius: 15px !important;
    border: none !important;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
    max-height: 85vh !important;
    overflow: hidden !important;
}

.modal-header {
    padding: 1.5rem 2rem 0.75rem !important;
    background: white !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    border-bottom: none !important;
}

.modal-body {
    padding: 1rem 2rem !important;
    max-height: calc(85vh - 140px) !important;
    overflow-y: auto !important;
}

.modal-footer {
    padding: 1rem 2rem 1.5rem !important;
}

/* Profile Picture Styles */
.profile-upload-container {
    margin-bottom: 1rem !important;
}

.profile-picture-wrapper {
    position: relative !important;
    width: 100px !important;
    height: 100px !important;
    margin: 0 auto !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
}

.profile-picture-wrapper:hover {
    transform: scale(1.05) !important;
}

.profile-preview-compact {
    width: 100px !important;
    height: 100px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 3px solid #8B4513 !important;
    background-color: #f8f9fa !important;
    transition: all 0.3s ease !important;
}

.profile-overlay-compact {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(139, 69, 19, 0.7) !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    opacity: 0 !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
}

.profile-upload-container:hover .profile-overlay-compact {
    opacity: 1 !important;
}

.profile-overlay-compact i {
    color: white !important;
    font-size: 1.4rem !important;
}

.cursor-pointer {
    cursor: pointer !important;
}

.cursor-pointer:hover {
    color: #8B4513 !important;
}

/* Form Input Styles */
.form-label-compact {
    margin-bottom: 0.5rem !important;
    font-size: 0.9rem !important;
    color: #6c757d !important;
    font-weight: 500 !important;
}

.profile-input-compact {
    border: 1px solid #e9ecef !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    background-color: #fff !important;
    color: #495057 !important;
    height: auto !important;
}

.profile-input-compact:focus {
    border-color: #8B4513 !important;
    box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.15) !important;
    outline: none !important;
}

.profile-input {
    border: 1px solid #e9ecef !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    background-color: #fff !important;
    color: #495057 !important;
}

.profile-input:focus {
    border-color: #8B4513 !important;
    box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.15) !important;
    outline: none !important;
}

/* ID Document Styles */
.id-display-section {
    background: #f8f9fa !important;
    border-radius: 8px !important;
    border: 1px solid #e9ecef !important;
    padding: 1rem !important;
}

.current-id-display-compact {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-direction: column !important;
    gap: 0.5rem !important;
    background: white !important;
    border-radius: 8px !important;
    border: 1px solid #dee2e6 !important;
    padding: 0.75rem !important;
}

.id-preview-compact {
    width: 120px !important;
    height: 80px !important;
    object-fit: cover !important;
    border-radius: 6px !important;
    border: 2px solid #28a745 !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
}

.id-preview-compact:hover {
    transform: scale(1.05) !important;
    border-color: #8B4513 !important;
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.2) !important;
}

.text-success {
    color: #28a745 !important;
    font-weight: 500 !important;
}

/* Password Toggle Styles */
.input-group .profile-input {
    border-right: none !important;
}

.toggle-password-modern {
    border-left: none !important;
    background: transparent !important;
    color: #6c757d !important;
    border: 1px solid #e9ecef !important;
    border-radius: 0 8px 8px 0 !important;
    transition: all 0.3s ease !important;
}

.toggle-password-modern:hover {
    color: #8B4513 !important;
    background: rgba(139, 69, 19, 0.05) !important;
}

.input-group .profile-input:focus + .toggle-password-modern {
    border-color: #8B4513 !important;
}

/* Button Styles */
.btn-light-modern, .btn-light-compact {
    background-color: #f8f9fa !important;
    border: 1px solid #e9ecef !important;
    color: #495057 !important;
    padding: 0.75rem 2rem !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    font-size: 1rem !important;
    transition: all 0.3s ease !important;
}

.btn-light-modern:hover, .btn-light-compact:hover {
    background-color: #e9ecef !important;
    border-color: #dee2e6 !important;
    color: #495057 !important;
    transform: translateY(-1px) !important;
}

.btn-primary-modern, .btn-primary-compact {
    background-color: #8B4513 !important;
    border: 1px solid #8B4513 !important;
    color: white !important;
    padding: 0.75rem 2rem !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    font-size: 1rem !important;
    transition: all 0.3s ease !important;
}

.btn-primary-modern:hover, .btn-primary-compact:hover {
    background-color: #7a3d11 !important;
    border-color: #7a3d11 !important;
    color: white !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3) !important;
}

#forgotPasswordLink {
    font-size: 0.9rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

#forgotPasswordLink:hover {
    color: #7a3d11 !important;
    text-decoration: underline !important;
}

/* Alert Styles */
.alert {
    border-radius: 8px !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

.alert-success {
    background-color: #d4edda !important;
    color: #155724 !important;
}

.alert-danger {
    background-color: #f8d7da !important;
    color: #721c24 !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-lg {
        max-width: 95% !important;
        margin: 0.5rem !important;
    }
    
    .modal-content {
        border-radius: 12px !important;
        max-height: 90vh !important;
    }
    
    .modal-body {
        max-height: calc(90vh - 120px) !important;
        padding: 1rem 1.5rem !important;
    }
}
</style>

<script>
// Staff Profile Management System
(function() {
    'use strict';
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeProfileSystem();
    });
    
    function initializeProfileSystem() {
        // Profile picture preview
        const profilePictureInput = document.getElementById('profilePictureInput');
        const profilePreview = document.getElementById('profilePreview');
        
        if (profilePictureInput && profilePreview) {
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
        
        // Modal management
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const profileModalElement = document.getElementById('profileModal');
        const forgotPasswordModalElement = document.getElementById('forgotPasswordModal');
        
        if (forgotPasswordLink && profileModalElement && forgotPasswordModalElement) {
            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                const profileModal = bootstrap.Modal.getInstance(profileModalElement);
                const forgotPasswordModal = new bootstrap.Modal(forgotPasswordModalElement);
                
                if (profileModal) {
                    profileModal.hide();
                }
                forgotPasswordModal.show();
            });
        }
        
        // Change password handler
        const changePasswordBtn = document.getElementById('changePassword');
        if (changePasswordBtn) {
            changePasswordBtn.addEventListener('click', handlePasswordChange);
        }
        
        // Save profile handler
        const saveProfileBtn = document.getElementById('saveProfile');
        if (saveProfileBtn) {
            saveProfileBtn.addEventListener('click', handleProfileSave);
        }
    }
    
    async function handlePasswordChange() {
        const form = document.getElementById('passwordForm');
        const formData = new FormData(form);
        const button = this;
        
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
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing...';
            
            const response = await fetch('update_password.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                const forgotPasswordModal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
                if (forgotPasswordModal) {
                    forgotPasswordModal.hide();
                }
                showAlert('Password updated successfully!', 'success');
                form.reset();
            } else {
                showAlert(result.message || 'Failed to update password', 'danger');
            }
        } catch (error) {
            console.error('Password update error:', error);
            showAlert('Error updating password. Please try again.', 'danger');
        } finally {
            button.disabled = false;
            button.innerHTML = 'Change Password';
        }
    }
    
    async function handleProfileSave() {
        const form = document.getElementById('profileForm');
        const formData = new FormData(form);
        const button = this;
        
        // Basic validation
        const firstName = formData.get('first_name');
        const lastName = formData.get('last_name');
        const email = formData.get('email');
        
        if (!firstName || !lastName || !email) {
            showAlert('Please fill in all required fields', 'danger');
            return;
        }
        
        try {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            
            const response = await fetch('update_profile.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                if (profileModal) {
                    profileModal.hide();
                }
                showAlert('Profile updated successfully!', 'success');
                
                // Update sidebar profile info if elements exist
                updateSidebarProfile(result.data);
                
                // Reload page after a short delay to reflect changes
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert(result.message || 'Failed to update profile', 'danger');
            }
        } catch (error) {
            console.error('Profile update error:', error);
            showAlert('Error updating profile. Please try again.', 'danger');
        } finally {
            button.disabled = false;
            button.innerHTML = 'Save Changes';
        }
    }
    
    function updateSidebarProfile(data) {
        if (!data) return;
        
        // Update profile name
        const profileName = document.querySelector('.profile-info h6');
        if (profileName && data.first_name && data.last_name) {
            profileName.textContent = `${data.first_name} ${data.last_name}`;
        }
        
        // Update profile role
        const profileRole = document.querySelector('.profile-role');
        if (profileRole && data.position) {
            profileRole.textContent = data.position;
        }
        
        // Update profile image
        const profileImage = document.querySelector('.profile-image img');
        if (profileImage && data.profile_picture) {
            profileImage.src = data.profile_picture;
        }
    }
    
    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.cssText = 'margin-bottom: 1rem; min-width: 300px;';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                <div>${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.classList.remove('show');
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 150);
            }
        }, 5000);
    }
    
    // Global functions for external access
    window.openImageModal = function(imageSrc) {
        const modalImage = document.getElementById('modalImage');
        const imageModalElement = document.getElementById('imageModal');
        
        if (modalImage && imageModalElement) {
            modalImage.src = imageSrc;
            const imageModal = new bootstrap.Modal(imageModalElement);
            imageModal.show();
        }
    };
    
    window.showProfileAlert = showAlert;
    
})();
</script>
