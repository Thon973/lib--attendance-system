<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Audit Logs | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/audit-logs.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/home.css') ?>" />
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
        <img src="<?= base_url('assets/icons/ustp-logo1.png') ?>" alt="USTP Logo" class="navbar__logo" />
        <img src="<?= base_url('assets/icons/logo2.png') ?>" alt="USTP Logo" class="navbar__logo" />
        <div class="navbar__text">
          <span class="navbar__title">USTP LIBRARY</span>
          <span class="navbar__subtitle">ATTENDANCE</span>
        </div>
      </div>
      
      <div class="navbar__user">
        <img src="<?= base_url('assets/icons/profile.png') ?>" alt="User Profile" class="navbar__user-avatar" />
        <div class="navbar__user-info">
          <span class="navbar__user-name"><?= esc(session()->get('full_name') ?? $user_name ?? 'Guest User') ?></span>
          <span class="navbar__user-role"><?= esc(session()->get('role') ?? $role ?? 'Super Administrator') ?></span>
        </div>
      </div>
    </div>
  </header>

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    <img src="<?= base_url('assets/icons/library.jpg') ?>" alt="USTP Logo" class="logo-img" />

    <ul class="nav-links">
      <li><a href="dashboard"><img src="<?= base_url('assets/icons/home.png') ?>" alt="Home">Home</a></li>
      <li><a href="manage-student"><img src="<?= base_url('assets/icons/manage.png') ?>" alt="Student">Manage Student</a></li>
      <li><a href="manage-admin"><img src="<?= base_url('assets/icons/administrator.png') ?>" alt="Admin">Manage Admins</a></li>
      <li><a href="student_attendance"><img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">Student Attendance</a></li>
      <li><a href="reports"><img src="<?= base_url('assets/icons/report.png') ?>" alt="Reports">Reports</a></li>
      <li><a href="audit-logs" class="active"><img src="<?= base_url('assets/icons/audit.png') ?>" alt="Audit Logs">Audit Logs</a></li>
    </ul>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <header class="main-content-header">
      <button class="menu-toggle" id="menuToggle">☰</button>
      <h1>Audit Logs</h1>
    </header>

    <div class="audit-logs-container">
      <!-- FILTER BAR -->
      <!-- First Row: Action, Entity Type, User -->
      <div class="filter-control-bar">
        <div class="filter-row">
          <div class="filter-group">
            <label for="actionFilter">Action:</label>
            <select id="actionFilter">
              <option value="">All Actions</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="entityFilter">Entity Type:</label>
            <select id="entityFilter">
              <option value="">All Entities</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="userFilter">User:</label>
            <select id="userFilter">
              <option value="">All Users</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Second Row: Start Date, End Date, Filter Button, Reset Button -->
      <div class="filter-control-bar filter-second-row">
        <div class="date-filter-group">
          <div class="date-filter">
            <label for="startDate">Start Date:</label>
            <input type="date" id="startDate" placeholder="Start Date">
          </div>

          <div class="date-filter">
            <label for="endDate">End Date:</label>
            <input type="date" id="endDate" placeholder="End Date">
          </div>

          <button id="filterBtn" class="add-btn"><img src="<?= base_url('assets/icons/filter.png') ?>" alt="Filter">Filter</button>
          <button id="resetFiltersBtn" class="reset-btn" title="Reset Filters">
            ↻ Reset
          </button>
        </div>
      </div>

      <!-- AUDIT LOGS TABLE -->
      <table class="audit-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Description</th>
            <th>IP Address</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody id="auditLogsBody">
          <tr>
            <td colspan="7" style="text-align: center; color: #888;">Loading audit logs...</td>
          </tr>
        </tbody>
      </table>

      <div class="no-data" id="noDataMessage" style="display: none;">No audit logs found.</div>

      <!-- Pagination -->
      <div class="pagination-container" id="auditPagination" style="display: none; margin-top: 20px; text-align: center;">
        <button id="prevPageAudit" class="pagination-btn" disabled>Previous</button>
        <span id="pageInfoAudit" style="margin: 0 15px;"></span>
        <button id="nextPageAudit" class="pagination-btn">Next</button>
      </div>

      <!-- Modal for View Changes -->
      <div id="changesModal" class="modal" style="display: none;">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Audit Log Details</h2>
            <span class="close" id="closeModal">&times;</span>
          </div>
          <div class="modal-body" id="modalBody">
            <!-- Changes will be loaded here -->
          </div>
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

  <script>
    // Define BASE_URL for JS
    window.BASE_URL = "<?= base_url() ?>";
  </script>
  <script defer src="<?= base_url('assets/js/audit-logs.js') ?>"></script>
</body>
</html>

