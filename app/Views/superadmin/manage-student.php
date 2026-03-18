<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Students | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">

  <!-- Styles -->
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/manage-student.css') ?>" />
  <!-- Scripts -->
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <script defer src="<?= base_url('assets/js/manage-student.js') ?>"></script>

  <!-- Early sidebar state check to prevent FOUC -->
  <script>
    if (localStorage.getItem('sidebar-hidden') === 'true') {
      document.documentElement.classList.add('js-sidebar-hidden');
    }
    const BASE_URL = "<?= base_url() ?>";
  </script>
  <script>
    function toggleStudentPassword(inputId, iconId) {
      const passwordInput = document.getElementById(inputId);
      const eyeIcon = document.getElementById(iconId);
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.src = '<?= base_url('assets/icons/eye-hide.png') ?>';
        eyeIcon.alt = 'Hide Password';
      } else {
        passwordInput.type = 'password';
        eyeIcon.src = '<?= base_url('assets/icons/eye-show.png') ?>';
        eyeIcon.alt = 'Show Password';
      }
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
                      <a href="<?= base_url('superadmin/profile') ?>" class="dropdown-item">
                        <img src="<?= base_url('assets/icons/profile.png') ?>" alt="Profile"> Profile
                      </a>
                      <a href="#" class="dropdown-item logout-trigger" data-logout-url="<?= base_url('logout') ?>">
                        <img src="<?= base_url('assets/icons/logout.png') ?>" alt="Logout"> Logout
                      </a>
                    </div>
                  </div>
                  <span class="navbar__user-role"><?= esc(session()->get('role') ?? $role ?? 'Super Administrator') ?></span>
                </div>
            </div>
        </div>
    </header>

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    
    <img src="<?= base_url('assets/icons/library.jpg') ?>" alt="USTP Logo" class="logo-img" />


    <ul class="nav-links">
      <li><a href="dashboard" class="active"><img src="<?= base_url('assets/icons/home.png') ?>" alt="Home">Home</a></li>
      <li><a href="manage-student"><img src="<?= base_url('assets/icons/manage.png') ?>" alt="Student">Manage Student</a></li>
      <li><a href="manage-admin"><img src="<?= base_url('assets/icons/administrator.png') ?>" alt="Admin">Manage Admins</a></li>
      <li><a href="student_attendance"><img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">Student Attendance</a></li>
      <li><a href="reports"><img src="<?= base_url('assets/icons/report.png') ?>" alt="Reports">Reports</a></li>
      <li><a href="audit-logs"><img src="<?= base_url('assets/icons/audit.png') ?>" alt="Audit Logs">Audit Logs</a></li>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="main-content-header">
      <div class="header-content">
        <div class="header-left">
          <button class="menu-toggle" id="menuToggle">☰</button>
          <h1>Manage Students</h1>
        </div>
        <div class="header-right">
          <!-- Search Bar -->
          <div class="search-bar">
            <button id="searchBtn">
              <img src="<?= base_url('assets/icons/search.png') ?>" alt="Search">
            </button>
            <input type="text" id="studentSearch" placeholder="Search student..." />
          </div>
        </div>
      </div>
    </header>

    <!-- Controls -->
    <div class="controls">
      <!-- Filters -->
      <div class="filters">
        
        <label for="collegeFilter">College:</label>
        <select id="collegeFilter"><option value="">All Colleges</option></select>

        <label for="courseFilter">Course:</label>
        <select id="courseFilter"><option value="">All Courses</option></select>

        <label for="yearFilter">Year Level:</label>
        <select id="yearFilter">
          <option value="">All</option>
          <option value="1st Year">1st Year</option>
          <option value="2nd Year">2nd Year</option>
          <option value="3rd Year">3rd Year</option>
          <option value="4th Year">4th Year</option>
        </select>

        <label for="statusFilter">Status:</label>
        <select id="statusFilter">
          <option value="">All</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
        </select>
        
        <label for="sexFilter">Sex:</label>
        <select id="sexFilter">
          <option value="">All</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>

        <button id="resetFiltersBtn" class="reset-btn" title="Reset Filters">
          ↻ Reset
        </button>
      </div>
    </div>

    <!-- Actions -->


    <div class="actions">
        <!-- Selected Count Indicator -->
        <div class="selected-count-indicator" id="selectedCountIndicator" style="display: none;">
          <span class="selected-count-text">
            <strong id="selectedCount">0</strong> student(s) selected
          </span>
          <button id="cancelSelected" class="cancel-selected-btn" title="Clear Selection">
            <span>✕</span>
          </button>
        </div>
        
        <!-- Bulk Actions (shown when students are selected) -->
        <div class="bulk-actions" id="bulkActions" style="display: none;">
          <button id="bulkActivate" class="bulk-btn activate-btn" title="Activate Selected">
            <img src="<?= base_url('assets/icons/activate.png') ?>" alt="Activate" class="btn-icon">
            Activate
          </button>
          <button id="bulkDeactivate" class="bulk-btn deactivate-btn" title="Deactivate Selected">
            <img src="<?= base_url('assets/icons/deactivate.png') ?>" alt="Deactivate" class="btn-icon">
            Deactivate
          </button>
          <button id="bulkDelete" class="bulk-btn delete-btn" title="Delete Selected">
            <img src="<?= base_url('assets/icons/trash-bin.png') ?>" alt="Delete" class="btn-icon">
            Delete
        </button>
      </div>
    </div>

    <!-- Student List -->
    <div class="card table-container">
      <div class="actions">
        <h2 class="table-title">Student List</h2>
        <div class="actions-right">
          <button type="button" id="importTrigger" class="import-btn">
            <img src="<?= base_url('assets/icons/import.png') ?>" alt="Import" class="btn-icon">
            Import File
          </button>
          <input type="file" id="importFile" accept=".xlsx,.xls,.csv,.pdf" hidden />
          <script>
            document.getElementById("importTrigger").addEventListener("click", function() {
              document.getElementById("importFile").click();
            });
          </script>

          <button id="openAddStudent" class="add-btn">
            <img src="<?= base_url('assets/icons/add-w.png') ?>" alt="Add" class="btn-icon">
            Add Student
          </button>
        </div>
    </div>
      <table class="student-table">
        <thead>
          <tr>
            <th class="checkbox-col">
              <input type="checkbox" id="selectAllCheckbox" title="Select All">
            </th>
            <th class="number-col">#</th>
            <th class="sortable" data-sort="student_id">
              Student ID
              <span class="sort-indicator"></span>
            </th>
            <th class="sortable name-col" data-sort="name">
              Name
              <span class="sort-indicator"></span>
            </th>
            <th>Sex</th>
            <th>Course</th>
            <th>Year Level</th>
            <th class="address-col">Address</th>
            <th>QR Code</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="studentData">
          <tr><td colspan="9" style="text-align:center;color:#888;">Loading...</td></tr>
        </tbody>
      </table>
      
      <!-- Pagination Controls -->
      <div class="pagination-container" id="studentPagination" style="display: none; margin-top: 20px; text-align: center;">
        <button id="prevPage" class="pagination-btn" disabled>Previous</button>
        <span id="pageInfo" style="margin: 0 15px;"></span>
        <button id="nextPage" class="pagination-btn">Next</button>
      </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal" id="addStudentModal">
      <div class="modal-content">
        <span class="close-btn" id="closeModal">&times;</span>
        <h2>Add New Student</h2>
        <form id="addStudentForm">
          <label>Student Number</label>
          <input type="text" name="student_number" required>
          
          <label>First Name</label>
          <input type="text" name="first_name" required>
          
          <label>Last Name</label>
          <input type="text" name="last_name" required>

          <label>Middle Initial</label>
          <input type="text" name="middle_initial" placeholder="Enter middle initial">
          
          <label>Sex</label>
          <select name="sex" id="modalSex">
            <option value="">Select Sex</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
          
          <label>College</label>
          <select name="college_id" id="modalCollege" required></select>
          
          <label>Course</label>
          <select name="course_id" id="modalCourse" required></select>
          
          <label>Year Level</label>
          <select name="year_level" required>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
          </select>

          <label>Address</label>
          <textarea name="address" rows="3" placeholder="Enter address"></textarea>

          <div id="passwordSection" style="display: none;">
            <hr class="form-separator" style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
            <p class="form-section-title" style="margin-bottom: 10px; font-weight: 600; color: #333;">Update Password</p>
            <p class="form-hint" style="margin-bottom: 15px; color: #666; font-size: 13px;">Leave password fields blank if you do not want to change the password.</p>

            <label>New Password</label>
            <div class="password-wrapper">
              <input type="password" name="password" id="studentPassword" placeholder="Enter new password (optional)">
              <span class="toggle-password" onclick="toggleStudentPassword('studentPassword', 'studentPasswordEye')">
                <img src="<?= base_url('assets/icons/eye-show.png') ?>" alt="Show Password" id="studentPasswordEye" width="20">
              </span>
            </div>

            <label>Confirm Password</label>
            <div class="password-wrapper">
              <input type="password" name="confirm_password" id="studentConfirmPassword" placeholder="Re-enter new password">
              <span class="toggle-password" onclick="toggleStudentPassword('studentConfirmPassword', 'studentConfirmPasswordEye')">
                <img src="<?= base_url('assets/icons/eye-show.png') ?>" alt="Show Password" id="studentConfirmPasswordEye" width="20">
              </span>
            </div>
          </div>
          
          <button type="submit" id="saveStudentBtn" class="save-btn">Save Student</button>
        </form>
      </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal" id="qrModal">
      <div class="modal-content">
        <span class="close-btn" id="closeQrModal">&times;</span>
        <img id="qrImage" src="" alt="QR Code" style="width:200px;height:200px;">
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteStudentModal">
      <div class="modal-content confirm-modal">
        <span class="close-btn" id="closeDeleteModal">&times;</span>
        <h3>Delete Student</h3>
        <p>Are you sure you want to delete <strong id="deleteStudentName">this student</strong>? This action cannot be undone.</p>
        <div class="modal-actions">
          <button type="button" id="confirmDeleteStudent" class="btn delete-btn">Delete</button>
          <button type="button" id="cancelDeleteStudent" class="btn cancel-btn">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal" id="bulkDeleteModal">
      <div class="modal-content confirm-modal">
        <span class="close-btn" id="closeBulkDeleteModal">&times;</span>
        <h3>Delete Selected Students</h3>
        <p>Are you sure you want to delete <strong id="bulkDeleteCount">0</strong> selected student(s)? This action cannot be undone.</p>
        <div class="modal-actions">
          <button type="button" id="confirmBulkDelete" class="btn delete-btn">Delete</button>
          <button type="button" id="cancelBulkDelete" class="btn cancel-btn">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Attendance History Modal -->
    <div class="modal" id="attendanceHistoryModal">
      <div class="modal-content history-modal">
        <span class="close-btn" id="closeHistoryModal">&times;</span>
        <h2 id="historyStudentName">Student Attendance History</h2>
        <div class="student-info" id="historyStudentInfo"></div>
        <div class="history-table-container">
          <table class="history-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Section</th>
              </tr>
            </thead>
            <tbody id="attendanceHistoryBody">
              <tr><td colspan="3" style="text-align: center; color: #888;">Loading...</td></tr>
            </tbody>
          </table>
          <div class="no-data" id="historyNoData" style="display: none;">No attendance records found.</div>
        </div>
      </div>
    </div>
  </main>

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
</body>
</html>
