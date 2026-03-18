<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/profile.css') ?>" />
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <!-- Early sidebar state check to prevent FOUC -->
  <script>
    if (localStorage.getItem('sidebar-hidden') === 'true') {
      document.documentElement.classList.add('js-sidebar-hidden');
    }
  </script>
</head>
<body>
<!-- ===== NAVBAR ===== -->
    <header class="navbar">
        <div class="navbar__container">
            <button class="navbar__toggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <div class="navbar__brand">
                <a href="<?= base_url('superadmin/dashboard') ?>"><img src="<?= base_url('assets/icons/ustp-logo1.png') ?>" alt="USTP Logo" class="navbar__logo" />
                <a href="<?= base_url('superadmin/dashboard') ?>"><img src="<?= base_url('assets/icons/logo2.png') ?>" alt="USTP Logo" class="navbar__logo" /></a>
                <div class="navbar__text">
                    <span class="navbar__title">USTP LIBRARY</span>
                    <span class="navbar__subtitle">ATTENDANCE</span>
                </div>
            </div>
            
            <div class="navbar__user">
              <img src="<?= esc(session()->get('profile_picture') ?? $profile_picture ?? base_url('assets/icons/profile.png')) ?>" alt="User Profile" class="navbar__user-avatar" />
                <div class="navbar__user-info">
                  <div class="navbar__user-name-dropdown">
                    <span class="navbar__user-name"><?= esc(session()->get('full_name') ?? $user_name ?? 'Guest User') ?> &#x25BE</span>
                    <div class="user-dropdown-menu">
                      <a href="<?= base_url('admin/ad-profile') ?>" class="dropdown-item">
                        <img src="<?= base_url('assets/icons/profile.png') ?>" alt="Profile"> Profile
                      </a>
                      <a href="#" class="dropdown-item logout-trigger" data-logout-url="<?= base_url('logout') ?>">
                        <img src="<?= base_url('assets/icons/logout.png') ?>" alt="Logout"> Logout
                      </a>
                    </div>
                  </div>
                  <span class="navbar__user-role"><?= esc(session()->get('role') ?? $role ?? 'Administrator') ?></span>
                </div>
            </div>
        </div>
    </header>

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    <img src="<?= base_url('assets/icons/library.jpg') ?>" alt="USTP Logo" class="logo-img" />
    <ul class="nav-links">
      <li><a href="ad-dashboard"><img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">Student Attendance</a></li>
      <li><a href="ad-reports" class="active"><img src="<?= base_url('assets/icons/report.png') ?>" alt="Reports">Reports</a></li>
    </ul>
  </aside>
  
  <!-- MAIN CONTENT -->
  <main class="main-content">
    <header class="main-content-header">
      <button class="menu-toggle" id="menuToggle">☰</button>
      <h1>My Profile</h1>
    </header>

      <!-- Profile Content -->
      <div class="profile-card">
        <!-- Left Panel - User Info -->
        <div class="profile-left-panel">  
          <img src="<?= base_url('assets/icons/library.jpg') ?>" alt="USTP Logo" class="profile-img" />
          <div class="profile-avatar-wrapper">
            <a href="#" id="viewProfilePictureLink"><img src="<?= esc(session()->get('profile_picture') ?? $profile_picture ?? base_url('assets/icons/profile.png')) ?>" alt="User Profile" class="profile-avatar" id="profileAvatar"/></a>
            <a href="#" class="edit-profile-link" id="editProfilePictureBtn"><img src="<?= base_url('assets/icons/camera.png') ?>" alt="Edit Profile"></a>
            <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" style="display: none;">
          </div>
          <h3 class="profile-name"><?= esc($admin['full_name'] ?? session()->get('full_name')) ?></h3>
          <p class="profile-email"><?= esc($admin['email'] ?? session()->get('email')) ?></p>
          <span class="profile-role"><?= esc($admin['role'] ?? session()->get('role')) ?></span>
        </div>

        <!-- Right Panel - Edit Form -->
        <div class="profile-right-panel">
          <h2 class="form-title">Edit Profile</h2>
          
          <script>
            // Function to show flash messages
            function showFlashMessage(message, type) {
                const messageContainer = document.getElementById('profile-message-container');
                
                // Remove any existing messages
                while (messageContainer.firstChild) {
                    messageContainer.removeChild(messageContainer.firstChild);
                }
                
                // Create new message element
                const messageDiv = document.createElement('div');
                messageDiv.className = `alert alert-${type}`;
                messageDiv.textContent = message;
                
                // Add animation
                messageDiv.style.animation = 'slideDown 0.3s ease';
                
                // Insert the message at the top of the container
                messageContainer.appendChild(messageDiv);
                
                // Auto-remove the message after 5 seconds
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }
                }, 5000);
            }
            
            document.addEventListener('DOMContentLoaded', function() {
              // Show server-side flash messages
              <?php if (session()->getFlashdata('success')): ?>
                showFlashMessage('<?= addslashes(session()->getFlashdata('success')) ?>', 'success');
              <?php endif; ?>
              <?php if (session()->getFlashdata('error')): ?>
                showFlashMessage('<?= addslashes(session()->getFlashdata('error')) ?>', 'error');
              <?php endif; ?>
            });
          </script>

          <form action="<?= base_url('admin/profile/update') ?>" method="post" class="profile-form">
            <?= csrf_field() ?>
            
            <div id="profile-message-container"></div>
            
            <div class="form-group">
              <label for="fullName" class="form-label">Full Name</label>
              <input
                type="text"
                id="fullName"
                name="full_name"
                class="form-input"
                placeholder="Enter your name"
                value="<?= esc(old('full_name', $admin['full_name'] ?? '')) ?>"
                required
              />
            </div>

            <div class="form-group">
              <label for="email" class="form-label">Email</label>
              <input
                type="email"
                id="email"
                name="email"
                class="form-input"
                placeholder="Enter your email"
                value="<?= esc(old('email', $admin['email'] ?? '')) ?>"
                required
              />
            </div>

            <div class="form-group">
              <label for="role" class="form-label">Role</label>
              <input
                type="text"
                id="role"
                class="form-input"
                value="<?= esc($admin['role'] ?? 'Admin') ?>"
                readonly
              />
            </div>

            <div class="password-section">
              <h3 class="password-title">Change Password</h3>
              <p class="password-hint">Leave the fields below blank if you do not want to change your password.</p>

              <div class="form-group">
                <label for="currentPassword" class="form-label">Current Password</label>
                <input
                  type="password"
                  id="currentPassword"
                  name="current_password"
                  class="form-input"
                  placeholder="Enter current password"
                />
              </div>

              <div class="form-group">
                <label for="newPassword" class="form-label">New Password</label>
                <input
                  type="password"
                  id="newPassword"
                  name="new_password"
                  class="form-input"
                  placeholder="Enter new password"
                />
              </div>

              <div class="form-group">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input
                  type="password"
                  id="confirmPassword"
                  name="confirm_password"
                  class="form-input"
                  placeholder="Re-enter new password"
                />
              </div>
            </div>

            <button type="submit" class="submit-btn">Save Changes</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="logout-modal">
      <div class="logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout from your account?</p>
        <div class="logout-modal-actions">
          <button class="btn logout-confirm-btn" id="confirmLogout">Logout</button>
          <button class="btn logout-cancel-btn" id="cancelLogout">Cancel</button>
        </div>
      </div>
    </div>
  </main>
    <!-- Profile Picture Preview Modal -->
    <div id="profilePicturePreviewModal" class="modal">
        <div class="modal-content-large">
            <span class="close" id="closeProfilePicturePreviewModal">&times;</span>
            <img id="profilePicturePreview" src="" alt="Profile Preview" style="max-width: 100%; height: auto;">
        </div>
    </div>
    
    <!-- Profile Picture Upload Modal -->
    <div id="profilePictureModal" class="modal">
        <div class="modal-content">
            <span id="closeProfilePictureModal">&times;</span>
            <h3>Upload Profile Picture</h3>
            <div class="upload-area" id="uploadArea">
                <p>Drag & drop your image here</p>
                <p>OR</p>
                <button class="browse-btn" id="browseBtn">Browse Files</button>
                <input type="file" id="fileInput" name="profile_picture" accept="image/*" style="display: none;">
            </div>
            <div class="preview-container" id="previewContainer" style="display: none;">
                <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; height: auto; margin-top: 10px;">
            </div>
            <div class="modal-actions">
                <button class="btn submit-btn" id="uploadPictureBtn">Upload</button>
                <button class="btn cancel-btn" id="cancelPictureUpload">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editProfilePictureBtn = document.getElementById('editProfilePictureBtn');
            const profilePictureInput = document.getElementById('profilePictureInput');
            const modal = document.getElementById('profilePictureModal');
            const closeBtn = document.getElementById('closeProfilePictureModal');
            const fileInput = document.getElementById('fileInput');
            const browseBtn = document.getElementById('browseBtn');
            const uploadArea = document.getElementById('uploadArea');
            const imagePreview = document.getElementById('imagePreview');
            const previewContainer = document.getElementById('previewContainer');
            const uploadPictureBtn = document.getElementById('uploadPictureBtn');
            const cancelPictureUpload = document.getElementById('cancelPictureUpload');
            const profileAvatar = document.getElementById('profileAvatar');
            
            // Open file dialog when clicking the camera icon
            editProfilePictureBtn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'block';
            });
            
            // Browse button click
            browseBtn.addEventListener('click', function() {
                fileInput.click();
            });
            
            // File input change
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        alert('Please select a valid image file (JPEG, PNG, GIF)');
                        return;
                    }
                    
                    // Validate file size (max 2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        alert('File size exceeds 2MB limit');
                        return;
                    }
                    
                    // Display preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    const file = e.dataTransfer.files[0];
                    
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        alert('Please select a valid image file (JPEG, PNG, GIF)');
                        return;
                    }
                    
                    // Validate file size (max 2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        alert('File size exceeds 2MB limit');
                        return;
                    }
                    
                    // Set file to input
                    fileInput.files = e.dataTransfer.files;
                    
                    // Display preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Upload button click
            uploadPictureBtn.addEventListener('click', function() {
                const file = fileInput.files[0];
                if (!file) {
                    alert('Please select an image file');
                    return;
                }
                
                const formData = new FormData();
                formData.append('profile_picture', file);
                
                // Show loading indicator
                uploadPictureBtn.disabled = true;
                uploadPictureBtn.textContent = 'Uploading...';
                
                fetch('<?= base_url("admin/profile/upload-picture") ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the profile picture in the UI
                        profileAvatar.src = data.profile_picture;
                        document.querySelector('.navbar__user-avatar').src = data.profile_picture;
                        
                        // Show success message
                        showFlashMessage(data.message, 'success');
                        
                        // Close modal
                        modal.style.display = 'none';
                        
                        // Reset form
                        fileInput.value = '';
                        previewContainer.style.display = 'none';
                    } else {
                        showFlashMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showFlashMessage('An error occurred while uploading the image', 'error');
                })
                .finally(() => {
                    // Re-enable upload button
                    uploadPictureBtn.disabled = false;
                    uploadPictureBtn.textContent = 'Upload';
                });
            });
            
            // Close modal functions
            closeBtn.addEventListener('click', closeModal);
            cancelPictureUpload.addEventListener('click', closeModal);
            
            function closeModal() {
                modal.style.display = 'none';
                fileInput.value = '';
                previewContainer.style.display = 'none';
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            
            // Profile Picture Preview Modal
            const viewProfilePictureLink = document.getElementById('viewProfilePictureLink');
            const profilePicturePreviewModal = document.getElementById('profilePicturePreviewModal');
            const profilePicturePreview = document.getElementById('profilePicturePreview');
            const closeProfilePicturePreviewModal = document.getElementById('closeProfilePicturePreviewModal');
            
            // Open profile picture preview when clicking on the profile avatar
            viewProfilePictureLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the current profile picture source
                const currentSrc = profileAvatar.src;
                
                // Set the preview image source
                profilePicturePreview.src = currentSrc;
                
                // Show the preview modal
                profilePicturePreviewModal.style.display = 'block';
            });
            
            // Close profile picture preview modal
            closeProfilePicturePreviewModal.addEventListener('click', function() {
                profilePicturePreviewModal.style.display = 'none';
            });
            
            // Close preview modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === profilePicturePreviewModal) {
                    profilePicturePreviewModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
