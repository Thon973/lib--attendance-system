<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Attendance Reports | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/reports.css') ?>" />
  <script src="<?= base_url('assets/js/chart.umd.min.js') ?>"></script>
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <script defer src="<?= base_url('assets/js/reports.js') ?>"></script>
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
    <header>
      <button class="menu-toggle" id="menuToggle">☰</button>
      <h1>Attendance Reports</h1>
    </header>

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
      <div class="summary-card total-attendance">
        <img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">
        <div class="summary-info">
          <h3>Total Attendance This Week</h3>
          <p id="totalAttendanceWeek" aria-label="Total attendance this week" data-type="number">0</p>
        </div>
      </div>
      <div class="summary-card busiest-hour">
        <img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">
        <div class="summary-info">
          <h3>Busiest Hour</h3>
          <p id="busiestHourToday" aria-label="Busiest hour" data-type="text">Loading...</p>
        </div>
      </div>
      <div class="summary-card active-course" id="mostActiveCourseCard">
        <img src="<?= base_url('assets/icons/course.png') ?>" alt="Course">
        <div class="summary-info">
          <h3>Most Active Course</h3>
          <p id="mostActiveCourse" aria-label="Most active course" data-type="text">Loading...</p>
        </div>
      </div>
      <div class="summary-card active-gender">
        <img src="<?= base_url('assets/icons/person.png') ?>" alt="Gender">
        <div class="summary-info">
          <h3>Most Active Student Gender</h3>
          <p id="mostActiveGender" aria-label="Most Active Student" data-type="text">Loading...</p>
        </div>
      </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="charts-section">
      <div class="charts-carousel">
        <button class="chart-nav-btn prev-btn" id="prevChartBtn">‹</button>
        <div class="charts-container">
          <div class="chart-panel active" data-chart="0">
            <h2>Attendance by Section</h2>
            <canvas id="sectionChart"></canvas>
          </div>
          <div class="chart-panel" data-chart="1">
            <h2>Attendance Over Time</h2>
            <canvas id="dateChart"></canvas>
          </div>
          <div class="chart-panel" data-chart="2">
            <h2>Top Courses</h2>
            <canvas id="courseChart"></canvas>
          </div>
          <div class="chart-panel" data-chart="3">
            <h2>Top Students Visited to this Section</h2>
            <div id="studentList" class="student-list">
              <div class="list-header">
                <span class="rank">#</span>
                <span class="student-info">Student</span>
                <span class="visit-count">Visits</span>
              </div>
              <div id="studentListContent" class="list-content">
                <!-- Student list will be populated here -->
              </div>
            </div>
          </div>
        </div>
        <button class="chart-nav-btn next-btn" id="nextChartBtn">›</button>
      </div>
      <div class="chart-indicators">
        <span class="indicator active" data-indicator="0"></span>
        <span class="indicator" data-indicator="1"></span>
        <span class="indicator" data-indicator="2"></span>
        <span class="indicator" data-indicator="3"></span>
      </div>
    </div>

    <!-- ATTENDANCE PANEL -->
    <div class="management-panels">
      <div class="panel active">
        <h2>Attendance Records</h2>
        <div class="management-content active">
          <!-- FILTER & CONTROL BAR -->
          <div class="filter-control-bar">
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

          <div class="panel-buttons">
            <div class="export-dropdown">
              <button id="exportBtn" class="export-main-btn"><img src="<?= base_url('assets/icons/export.png') ?>" alt="Export">Export</button>
              <div class="export-menu" id="exportMenu">
                <button class="export-option" data-format="pdf"><img src="<?= base_url('assets/icons/export.png') ?>" alt="PDF">Export to PDF</button>
                <button class="export-option" data-format="excel"><img src="<?= base_url('assets/icons/export.png') ?>" alt="Excel">Export to Excel</button>
              </div>
            </div>
            <button id="printBtn"><img src="<?= base_url('assets/icons/print.png') ?>" alt="Print">Print</button>
          </div>

          <table>
            <thead>
              <tr>
                <th class="sortable" data-sort-col="0" data-sort-type="number">
                  #
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="1" data-sort-type="number">
                  Student ID
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="2" data-sort-type="text">
                  Student Name
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="3" data-sort-type="text">
                  Sex
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="4" data-sort-type="text">
                  Course
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="5" data-sort-type="text">
                  College
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="6" data-sort-type="text">
                  Time In
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="7" data-sort-type="text">
                  Section
                  <span class="sort-indicator"></span>
                </th>
                <th class="sortable" data-sort-col="8" data-sort-type="text">
                  Date
                  <span class="sort-indicator"></span>
                </th>
              </tr>
            </thead>
            <tbody id="attendanceTableBody">
              <tr>
                <td colspan="9" style="text-align: center; color: #888;">Loading data...</td>
              </tr>
            </tbody>
          </table>

          <div class="no-data" id="noDataMessage" style="display: none;">No attendance records found.</div>
          
          <!-- Pagination Controls -->
          <div class="pagination-container" id="reportsPagination" style="display: none; margin-top: 20px; text-align: center;">
            <button id="prevPageReports" class="pagination-btn" disabled>Previous</button>
            <span id="pageInfoReports" style="margin: 0 15px;"></span>
            <button id="nextPageReports" class="pagination-btn">Next</button>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
    // Pass BASE_URL to JavaScript
    window.BASE_URL = "<?= base_url() ?>";
  </script>
  <style>
    .reset-btn {
      padding: 8px 16px;
      background: #6c757d;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background 0.3s;
    }
    .reset-btn:hover {
      background: #5a6268;
    }
  </style>
</body>
</html>
