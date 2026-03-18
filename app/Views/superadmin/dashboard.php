<?php
// app/Views/superadmin/dashboard.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard | USTP Library</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  
  <!-- STYLES -->
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/home.css') ?>" />

  <!-- Scripts -->
  <script>
    // Early sidebar state check to prevent FOUC
    if (localStorage.getItem('sidebar-hidden') === 'true') {
      document.documentElement.classList.add('js-sidebar-hidden');
    }
  </script>
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>

  <!-- Provide BASE_URL for JS -->
  <script>
    const BASE_URL = "<?= site_url('superadmin') ?>";
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
    </ul>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <main class="main-content">
    <header class="main-content-header">
      <button class="menu-toggle" id="menuToggle">☰</button>
      <h1>Dashboard Overview</h1>
    </header>

    <!-- ===== SUMMARY BOXES ===== -->
    <section class="summary-boxes">
      <div class="summary-card students">
        <img src="<?= base_url('assets/icons/students.png') ?>" alt="Students" />
        <div class="summary-info">
          <h3>Total Students</h3>
          <p id="totalStudents"><?= esc($totalStudents ?? 0) ?></p>
        </div>
      </div>

      <div class="summary-card courses">
        <img src="<?= base_url('assets/icons/course.png') ?>" alt="Courses" />
        <div class="summary-info">
          <h3>Total Courses</h3>
          <p id="totalCourses"><?= esc($totalCourses ?? 0) ?></p>
        </div>
      </div>

      <div class="summary-card colleges">
        <img src="<?= base_url('assets/icons/college.png') ?>" alt="Colleges" />
        <div class="summary-info">
          <h3>Total Colleges</h3>
          <p id="totalColleges"><?= esc($totalColleges ?? 0) ?></p>
        </div>
      </div>

      <div class="summary-card sections">
        <img src="<?= base_url('assets/icons/section.png') ?>" alt="Sections" />
        <div class="summary-info">
          <h3>Total Sections</h3>
          <p id="totalSections"><?= esc($totalSections ?? 0) ?></p>
        </div>
      </div>
    </section>

    <!-- ===== MANAGEMENT PANELS ===== -->
    <section class="management-panels">

      <!-- === COLLEGES === -->
      <div class="panel" id="collegePanel">
        <h2>Manage Colleges <button class="add-btn-header" id="addCollegeBtn">+ Add College</button></h2>
        <div class="management-content active">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>College Name</th>
                <th>College Code</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="collegeTableBody">
              <tr><td colspan="4" class="no-data">Loading...</td></tr>
            </tbody>
          </table>
          <!-- Pagination for Colleges -->
          <div class="pagination-container" id="collegePagination" style="display: none;">
            <button id="collegePrevPage" class="pagination-btn" disabled>Previous</button>
            <span id="collegePageInfo" class="page-info"></span>
            <button id="collegeNextPage" class="pagination-btn">Next</button>
          </div>
        </div>
      </div>

      <!-- === COURSES === -->
      <div class="panel" id="coursePanel">
        <h2>Manage Courses <button class="add-btn-header" id="addCourseBtn">+ Add Course</button></h2>
        <div class="management-content active">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Course Name</th>
                <th>Course Code</th>
                <th>College</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="courseTableBody">
              <tr><td colspan="5" class="no-data">Loading...</td></tr>
            </tbody>
          </table>
          <!-- Pagination for Courses -->
          <div class="pagination-container" id="coursePagination" style="display: none;">
            <button id="coursePrevPage" class="pagination-btn" disabled>Previous</button>
            <span id="coursePageInfo" class="page-info"></span>
            <button id="courseNextPage" class="pagination-btn">Next</button>
          </div>
        </div>
      </div>

      <!-- === SECTIONS === -->
      <div class="panel" id="sectionPanel">
        <h2>Manage Sections <button class="add-btn-header" id="addSectionBtn">Add Section</button></h2>
        <div class="management-content active">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Section Name</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="sectionTableBody">
              <tr><td colspan="3" class="no-data">Loading...</td></tr>
            </tbody>
          </table>
          <!-- Pagination for Sections -->
          <div class="pagination-container" id="sectionPagination" style="display: none;">
            <button id="sectionPrevPage" class="pagination-btn" disabled>Previous</button>
            <span id="sectionPageInfo" class="page-info"></span>
            <button id="sectionNextPage" class="pagination-btn">Next</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== MODALS ===== -->
    <!-- College Modal -->
    <div class="modal" id="collegeModal">
      <div class="modal-content">
        <h3>Add / Edit College</h3>
        <label>College Name</label>
        <input type="text" id="collegeName" placeholder="Enter college name">
        <label>College Code</label>
        <input type="text" id="collegeCode" placeholder="e.g. CICT">
        <div class="modal-actions">
          <button id="saveCollege" class="btn">Save</button>
          <button class="btn cancel" data-close="collegeModal">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Course Modal -->
    <div class="modal" id="courseModal">
      <div class="modal-content">
        <h3>Add / Edit Course</h3>
        <label>Course Name</label>
        <input type="text" id="courseName" placeholder="Enter course name">
        <label>Course Code</label>
        <input type="text" id="courseCode" placeholder="e.g. BSIT">
        <label>College</label>
        <select id="courseCollege">
          <option value="">Select College</option>
          <?php foreach ($colleges as $college): ?>
            <option value="<?= esc($college['college_id']) ?>"><?= esc($college['college_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="modal-actions">
          <button id="saveCourse" class="btn">Save</button>
          <button class="btn cancel" data-close="courseModal">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Section Modal -->
    <div class="modal" id="sectionModal">
      <div class="modal-content">
        <h3>Add / Edit Section</h3>
        <label>Section Name</label>
        <input type="text" id="sectionName" placeholder="Enter section name">
        <div class="modal-actions">
          <button id="saveSection" class="btn">Save</button>
          <button class="btn cancel" data-close="sectionModal">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
      <div class="modal-content">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this record?</p>
        <div class="modal-actions">
          <button id="confirmDelete" class="btn">Yes</button>
          <button class="btn cancel" id="cancelDelete" data-close="deleteConfirmModal">No</button>
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

  <!-- JS -->
  <script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
