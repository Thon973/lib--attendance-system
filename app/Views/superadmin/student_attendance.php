<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Students Attendance | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/attendance.css') ?>" />

  <!-- Early sidebar state check to prevent FOUC -->
  <script>
    if (localStorage.getItem('sidebar-hidden') === 'true') {
      document.documentElement.classList.add('js-sidebar-hidden');
    }
  </script>
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
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
      <li><a href="student_attendance" class="active"><img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">Student Attendance</a></li>
      <li><a href="reports"><img src="<?= base_url('assets/icons/report.png') ?>" alt="Reports">Reports</a></li>
      <li><a href="audit-logs"><img src="<?= base_url('assets/icons/audit.png') ?>" alt="Audit Logs">Audit Logs</a></li>
    </ul>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <header class="main-content-header">
      <button class="menu-toggle" id="menuToggle">☰</button>
      <h1>Student Attendance</h1>
    </header>

    <div class="card">
      <div class="summary-header">
        <div class="summary-info">
          <p>Today's Attendance</p>
          <h2><span id="todayDate"></span></h2>
        </div>
      </div>
    </div>

    <!-- ATTENDANCE SECTION -->
    <div class="attendance-section">
      <!-- FILTER BAR on the right side -->
        <div class="filter-bar">
          <div class="filters">
            <label for="sectionFilter">Section:</label>
            <select id="sectionFilter">
              <option value="">All Sections</option>
              <?php if (!empty($sections)): ?>
                <?php foreach ($sections as $section): ?>
                  <option value="<?= esc($section['section_id']) ?>"><?= esc($section['section_name']) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>

            <label for="collegeFilter">College:</label>
            <select id="collegeFilter">
              <option value="">All Colleges</option>
              <?php if (!empty($colleges)): ?>
                <?php foreach ($colleges as $college): ?>
                  <option value="<?= esc($college['college_id']) ?>"><?= esc($college['college_code']) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>

            <label for="courseFilter">Course:</label>
            <select id="courseFilter">
              <option value="">All Courses</option>
            </select>

            <label for="yearFilter">Year Level:</label>
            <select id="yearFilter">
              <option value="">All</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
            </select>
            
            <label for="sexFilter">Sex:</label>
            <select id="sexFilter">
              <option value="">All</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>

            <!-- Reset Filter Button -->
            <button id="resetFiltersBtn" class="reset-btn" title="Reset Filters">
              ↻ Reset
            </button>
          </div>
        </div>
          
      <div class="table-container">
        <table class="attendance-table">
          <thead>
            <tr>
              <th>#</th>
              <th class="sortable" data-sort-col="0" data-sort-type="number">
                Student ID
                <span class="sort-indicator"></span>
              </th>
              <th class="sortable" data-sort-col="1" data-sort-type="text">
                Student Name
                <span class="sort-indicator"></span>
              </th>
              <th class="sortable" data-sort-col="2" data-sort-type="text">
                Sex
                <span class="sort-indicator"></span>
              </th>
              <th class="sortable" data-sort-col="3" data-sort-type="text">
                Course
                <span class="sort-indicator"></span>
              </th>
              <th class="sortable" data-sort-col="4" data-sort-type="text">
                Section
                <span class="sort-indicator"></span>
              </th>
              <th class="sortable" data-sort-col="5" data-sort-type="text">
                Time In
                <span class="sort-indicator"></span>
              </th>
              <th class="sortable" data-sort-col="6" data-sort-type="number">
                Visit Counts
                <span class="sort-indicator"></span>
              </th>
            </tr>
          </thead>
          <tbody id="attendanceTableBody">
            <tr>
              <td colspan="7" style="text-align: center; color: #888;">Loading attendance data...</td>
            </tr>
          </tbody>
        </table>
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
  // Display today's date dynamically
  let currentDate = new Date();
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  const dateElement = document.getElementById('todayDate');

  function updateDateDisplay() {
    currentDate = new Date();
    const formattedDate = currentDate.toLocaleDateString('en-US', options);
    dateElement.textContent = `(${formattedDate})`;
  }

  updateDateDisplay();

    // Auto-load attendance data
    const BASE_URL = "<?= base_url() ?>";
    const attendanceEndpoint = BASE_URL + '/superadmin/attendance-data';
    const tableBody = document.getElementById('attendanceTableBody');
    const sectionFilter = document.getElementById('sectionFilter');
    const collegeFilter = document.getElementById('collegeFilter');
    const courseFilter = document.getElementById('courseFilter');
    const yearFilter = document.getElementById('yearFilter');
    const sexFilter = document.getElementById('sexFilter');
    let refreshInterval = null;
    let lastRefreshDate = new Date().toDateString();

    function formatDateTime(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return 'N/A';
      return date.toLocaleString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    }

    // Load courses by college
    async function loadCoursesByCollege(collegeId = "") {
      const courseFilter = document.getElementById('courseFilter');
      courseFilter.innerHTML = '<option value="">All Courses</option>';
      
      if (!collegeId || collegeId === '') {
        return;
      }
      
      try {
        const res = await fetch(`${BASE_URL}/superadmin/stdCourses/${collegeId}`);
        const courses = await res.json();
        if (Array.isArray(courses) && courses.length > 0) {
          courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.course_id;
            option.textContent = course.course_code;
            courseFilter.appendChild(option);
          });
        }
      } catch (err) {
        console.error('Error loading courses by college:', err);
      }
    }

    function loadAttendance() {
      const sectionId = sectionFilter.value;
      const collegeId = collegeFilter.value;
      const courseId = courseFilter.value;
      const yearLevel = yearFilter.value;
      const sex = sexFilter.value;
      
      const params = new URLSearchParams();
      if (sectionId) params.append('section_id', sectionId);
      if (collegeId) params.append('college_id', collegeId);
      if (courseId) params.append('course_id', courseId);
      if (yearLevel) params.append('year_level', yearLevel);
      if (sex) params.append('sex', sex);
      
      const url = attendanceEndpoint + (params.toString() ? '?' + params.toString() : '');
      
      tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #888;">Loading...</td></tr>';
      
      fetch(url)
        .then(res => {
          if (!res.ok) {
            throw new Error('HTTP error! status: ' + res.status);
          }
          return res.json();
        })
        .then(data => {
          if (data.success !== false && data.attendance) {
            if (data.attendance.length === 0) {
              tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #888;">No attendance records found for today.</td></tr>';
              return;
            }

            tableBody.innerHTML = data.attendance.map((entry, index) => {
              const studentName = (entry.last_name || 'N/A') + ', ' + (entry.first_name || 'N/A') + (entry.middle_initial ? ' ' + entry.middle_initial + '.' : '');
              return `
                <tr>
                  <td>${index + 1}</td>
                  <td>${entry.student_number || 'N/A'}</td>
                  <td>${studentName.trim() || 'N/A'}</td>
                  <td>${entry.sex || 'N/A'}</td>
                  <td>${entry.course_code || 'N/A'}</td>
                  <td>${entry.section_name || 'N/A'}</td>
                  <td>${formatDateTime(entry.scan_datetime || entry.created_at)}</td>
                  <td>${entry.total_visits || 1}</td>
                </tr>
              `;
            }).join('');
          } else {
            const errorMsg = data.message || 'Failed to load attendance data.';
            console.error('Attendance error:', errorMsg);
            tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #888;">' + errorMsg + '</td></tr>';
          }
        })
        .catch(err => {
          console.error('Error fetching attendance:', err);
          tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #888;">Error loading attendance data. Check console for details.</td></tr>';
        });
    }

    function checkDayChange() {
      const now = new Date();
      const currentDateString = now.toDateString();
      
      // If day has changed, reload attendance and update date display
      if (currentDateString !== lastRefreshDate) {
        lastRefreshDate = currentDateString;
        updateDateDisplay();
        loadAttendance();
      }
    }

    // Load on page load
    loadAttendance();

    // Reload when filters change
    sectionFilter.addEventListener('change', loadAttendance);
    collegeFilter.addEventListener('change', function() {
      loadCoursesByCollege(this.value);
      loadAttendance();
    });
    courseFilter.addEventListener('change', loadAttendance);
    yearFilter.addEventListener('change', loadAttendance);
    sexFilter.addEventListener('change', loadAttendance);

    // Check for day change every minute
    // This will automatically refresh when a new day begins and reset attendance
    setInterval(checkDayChange, 60000);
    
    // Optional: Refresh attendance data every 2 minutes (much less aggressive than 5 seconds)
    // This keeps the data updated for new scans without being too intrusive
    // You can increase this interval or remove it entirely if not needed
    refreshInterval = setInterval(loadAttendance, 120000);
    
    // Clean up interval when page is hidden (saves resources)
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        if (refreshInterval) {
          clearInterval(refreshInterval);
          refreshInterval = null;
        }
      } else {
        if (!refreshInterval) {
          refreshInterval = setInterval(loadAttendance, 120000);
        }
        // Reload when page becomes visible again to ensure fresh data
        checkDayChange();
        loadAttendance();
      }
    });

    // Reset filters button functionality
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');
    if (resetFiltersBtn) {
      resetFiltersBtn.addEventListener('click', function() {
        // Reset all filter selects to their default values
        sectionFilter.value = '';
        collegeFilter.value = '';
        courseFilter.innerHTML = '<option value="">All Courses</option>';
        yearFilter.value = '';
        sexFilter.value = '';
        
        // Reload attendance data with reset filters
        loadAttendance();
      });
    }

    // Simple client-side sorting for the attendance table
    (function () {
      const tableBody = document.getElementById('attendanceTableBody');
      const headers = document.querySelectorAll('th.sortable');
      if (!tableBody || !headers.length) return;

      const sortState = {};

      headers.forEach((header) => {
        header.addEventListener('click', () => {
          const colIndex = parseInt(header.dataset.sortCol ?? '0', 10);
          const type = header.dataset.sortType || 'text';
          const stateKey = colIndex.toString();
          const ascending = !(sortState[stateKey] === true);
          sortState[stateKey] = ascending;

          const rows = Array.from(tableBody.querySelectorAll('tr'));
          if (!rows.length) return;

          // If there's a single "no data" row, do nothing
          if (rows.length === 1 && rows[0].querySelector('td[colspan]')) {
            return;
          }

          rows.sort((a, b) => {
            // Adjust colIndex to account for the # column
            const adjustedColIndex = colIndex + 1;
            const aCell = a.cells[adjustedColIndex];
            const bCell = b.cells[adjustedColIndex];
            const aVal = (aCell?.dataset.sortValue || aCell?.textContent || '').trim();
            const bVal = (bCell?.dataset.sortValue || bCell?.textContent || '').trim();

            if (type === 'number') {
              const aNum = parseFloat(aVal) || 0;
              const bNum = parseFloat(bVal) || 0;
              return ascending ? aNum - bNum : bNum - aNum;
            } else {
              const aText = aVal.toLowerCase();
              const bText = bVal.toLowerCase();
              if (aText < bText) return ascending ? -1 : 1;
              if (aText > bText) return ascending ? 1 : -1;
              return 0;
            }
          });

          rows.forEach(row => tableBody.appendChild(row));

          // Update numbering after sorting
          updateRowNumbers();

          // Reset indicators then set on active header
          headers.forEach(h => {
            const ind = h.querySelector('.sort-indicator');
            if (ind) ind.textContent = '';
          });
          const ind = header.querySelector('.sort-indicator');
          if (ind) ind.textContent = ascending ? '▲' : '▼';
        });
      });

      // Numbering functionality
      function updateRowNumbers() {
        const rows = tableBody.querySelectorAll('tr');
        let number = 1;
        
        rows.forEach(row => {
          const firstCell = row.querySelector('td');
          if (firstCell && !firstCell.hasAttribute('colspan')) {
            row.children[0].textContent = number;
            number++;
          }
        });
      }

      // Initialize numbering
      updateRowNumbers();
    })();
  </script>
</body>
</html>