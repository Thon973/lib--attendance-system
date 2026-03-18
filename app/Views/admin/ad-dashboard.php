<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/attendance.css') ?>" />
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <style>
    .tab-btn img {
      width: 16px;
      height: 16px;
      vertical-align: middle;
      margin-right: 8px;
      filter: brightness(0); /* Start with black icon */
    }
    
    .tab-btn:hover .scan-icon {
      filter: brightness(0) invert(1); /* Turn to white on hover */
    }
    
    .scanner-section {
      margin: 20px 0;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .scanner-title {
      margin-top: 0;
      color: #333;
      font-size: 1.2em;
    }
    
    .scan-input-wrapper {
      position: absolute;
      left: -9999px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
    
    .scan-input {
      width: 1px;
      height: 1px;
      border: none;
      padding: 0;
      margin: 0;
      outline: none;
      background: transparent;
      color: transparent;
      caret-color: transparent;
    }
    
    .toast-message {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 4px;
      color: white;
      font-weight: bold;
      z-index: 1000;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      transform: translateX(200%);
      transition: transform 0.3s ease-in-out;
    }
    
    .toast-message.show {
      transform: translateX(0);
    }
    
    .toast-success {
      background-color: #28a745;
    }
    
    .toast-error {
      background-color: #dc3545;
    }
    
    .scanner-hint {
      font-size: 0.9rem;
      color: #666;
      margin-top: 10px;
    }
  </style>
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
                <a href="<?= base_url('admin/ad-dashboard') ?>"><img src="<?= base_url('assets/icons/ustp-logo1.png') ?>" alt="USTP Logo" class="navbar__logo" />
                <a href="<?= base_url('admin/ad-dashboard') ?>"><img src="<?= base_url('assets/icons/logo2.png') ?>" alt="USTP Logo" class="navbar__logo" /></a>
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
      <h1><?= esc($sectionName ?? 'Assigned Section') ?></h1>
    </header>

    <div class="card summary-card">
      <div>
        <h2><span id="todayDate"></span></h2>
        <p>Today's Attendance</p>
      </div>
      <!-- Add this button in the scanner section, right after the scanner hint paragraph -->
      <div class="tab-controls">
        <button class="tab-btn" id="openScannerBtn">
          <img src="<?= base_url('assets/icons/qr-scan.png') ?>" alt="Scan" class="scan-icon">
          Open Camera Scanner
        </button>
      </div>
    </div>
    
    <!-- Handheld Scanner Integration -->
    <div class="scanner-section">
      <p class="scanner-hint">Scan a student's QR code to record attendance.</p>
      
      <div class="scan-input-wrapper">
        <input
          id="scanInput"
          type="text"
          class="scan-input"
          autocomplete="off"
          autofocus
        />
      </div>
    </div>

    <section class="attendance-section">
      <div class="table-container">
        <table class="attendance-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Student ID</th>
              <th>Student Name</th>
              <th>Sex</th>
              <th>Course</th>
              <th>College</th>
              <th>Time In</th>
              <th>Visit Counts</th>
            </tr>
          </thead>
          <tbody id="attendanceTableBody">
            <?php if (!empty($attendance)): ?>
              <?php foreach ($attendance as $entry): ?>
                <tr>
                  <td><?= esc($entry['student_number'] ?? 'N/A') ?></td>
                  <td><?= esc(($entry['last_name'] ?? 'N/A') . ', ' . ($entry['first_name'] ?? 'N/A') . ($entry['middle_initial'] ? ' ' . $entry['middle_initial'] . '.' : '')) ?></td>
                  <td><?= esc($entry['sex'] ?? 'N/A') ?></td>
                  <td><?= esc($entry['course_code'] ?? 'N/A') ?></td>
                  <td><?= esc($entry['college_name'] ?? 'N/A') ?></td>
                  <td><?= esc(date('h:i A', strtotime($entry['scan_datetime'] ?? 'now'))) ?></td>
                  <td><?= esc($entry['total_visits'] ?? 1) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align: center; color: #888;">No attendance records found yet.</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

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

  <script>
    // Toast message function
    function showToast(message, type) {
      // Remove any existing toast
      const existingToast = document.getElementById('scannerToast');
      if (existingToast) {
        existingToast.remove();
      }
      
      // Create toast element
      const toast = document.createElement('div');
      toast.id = 'scannerToast';
      toast.className = `toast-message toast-${type}`;
      toast.textContent = message;
      
      document.body.appendChild(toast);
      
      // Show toast
      setTimeout(() => {
        toast.classList.add('show');
      }, 10);
      
      // Hide toast after 3 seconds
      setTimeout(() => {
        toast.classList.remove('show');
        // Remove toast after transition
        setTimeout(() => {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 300);
      }, 3000);
    }
    
    // Handheld scanner functionality
    const SCAN_ENDPOINT = "<?= base_url('admin/scan') ?>";
    const inputEl = document.getElementById('scanInput');
    let scanTimer = null;
    
    // Always keep focus on the input so the handheld scanner can type into it
    function ensureFocus() {
      if (document.activeElement !== inputEl) {
        inputEl.focus();
      }
    }
    
    window.addEventListener('load', ensureFocus);
    window.addEventListener('click', ensureFocus);
    
    function submitScan(rawValue) {
      const raw = (rawValue || '').trim();
      if (!raw) {
        return;
      }
      
      // If the QR contains only student number, use directly.
      // If it contains a label (e.g. "Student No: 123456"), try to extract digits.
      let studentNumber = raw;
      const match = raw.match(/(\d{6,})/); // common student number pattern
      if (match) {
        studentNumber = match[1];
      }
      
      // Include admin_id in the request
      const adminId = '<?= session()->get('admin_id') ?>';
      
      fetch(SCAN_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
          student_number: studentNumber,
          admin_id: adminId
        })
      })
        .then(res => res.json().then(body => ({ status: res.status, body })))
        .then(({ status, body }) => {
          if (status >= 200 && status < 300 && body.success) {
            showToast(body.message || 'Attendance recorded successfully.', 'success');
            // Refresh attendance table
            updateAttendanceTable();
          } else if (status === 429) {
            showToast(body.message || 'Student has already scanned within the last 10 minutes.', 'error');
          } else {
            showToast(body.message || 'Could not record attendance. Please try again.', 'error');
          }
        })
        .catch(error => {
          console.error('Scan error:', error);
          showToast('System error while recording attendance. Please check your connection.', 'error');
        })
        .finally(() => {
          inputEl.value = '';
          ensureFocus();
        });
    }
    
    // Auto-submit when scanner finishes typing (no need to press Enter manually)
    inputEl.addEventListener('input', function () {
      if (scanTimer) {
        clearTimeout(scanTimer);
      }
      // Most scanners type very fast; a short pause means scan finished
      scanTimer = setTimeout(() => {
        submitScan(inputEl.value);
      }, 300); // 300ms after last character
    });
    
    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitScan(inputEl.value);
      }
    });
    
    // Display today's date dynamically
    let currentDate = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const dateElement = document.getElementById('todayDate');
    
    function updateDateDisplay() {
      currentDate = new Date();
      dateElement.textContent = currentDate.toLocaleDateString('en-US', options);
    }
    
    updateDateDisplay();

    // Listen for scan results from camera scanner
    window.addEventListener('storage', function(e) {
      if (e.key === 'lastScanResult') {
        try {
          const scanResult = JSON.parse(e.newValue);
          if (scanResult) {
            showToast(scanResult.message, scanResult.type);
            // Refresh attendance table when we get a successful scan
            if (scanResult.type === 'success') {
              setTimeout(updateAttendanceTable, 1000);
            }
          }
        } catch (err) {
          console.error('Error parsing scan result:', err);
        }
      }
    });

    // Also listen for custom events (for same-tab communication)
    window.addEventListener('scanResult', function(e) {
      const scanResult = e.detail;
      if (scanResult) {
        showToast(scanResult.message, scanResult.type);
        // Refresh attendance table when we get a successful scan
        if (scanResult.type === 'success') {
          setTimeout(updateAttendanceTable, 1000);
        }
      }
    });

    // Check for any recent scan results on page load
    try {
      const lastScan = localStorage.getItem('lastScanResult');
      if (lastScan) {
        const scanResult = JSON.parse(lastScan);
        // Only show if it's recent (within last 10 seconds)
        if (scanResult && new Date() - new Date(scanResult.timestamp) < 10000) {
          showToast(scanResult.message, scanResult.type);
        }
        // Clean up old scan results
        if (scanResult && new Date() - new Date(scanResult.timestamp) > 60000) {
          localStorage.removeItem('lastScanResult');
        }
      }
    } catch (err) {
      console.error('Error checking for recent scan results:', err);
    }

    // Auto-refresh attendance table
    const BASE_URL = "<?= base_url() ?>";
    const attendanceEndpoint = BASE_URL + '/admin/attendance-data';
    const tableBody = document.getElementById('attendanceTableBody');
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

    function updateAttendanceTable() {
      fetch(attendanceEndpoint)
        .then(res => {
          if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
          }
          return res.json();
        })
        .then(data => {
          if (data.success && data.attendance) {
            if (data.attendance.length === 0) {
              tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #888;">No attendance records found for today.</td></tr>';
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
                  <td>${entry.college_name || 'N/A'}</td>
                  <td>${formatDateTime(entry.scan_datetime)}</td>
                  <td>${entry.total_visits || 1}</td>
                </tr>
              `;
            }).join('');
          } else {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #888;">No attendance records found for today.</td></tr>';
          }
        })
        .catch(err => {
          console.error('Error fetching attendance:', err);
          tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #888;">Error loading attendance data. Check console for details.</td></tr>';
        });
    }

    function checkDayChange() {
      const now = new Date();
      const currentDateString = now.toDateString();
      
      // If day has changed, reload attendance and update date display
      if (currentDateString !== lastRefreshDate) {
        lastRefreshDate = currentDateString;
        updateDateDisplay();
        updateAttendanceTable();
      }
    }

    // Load on page load
    updateAttendanceTable();

    // Check for day change every minute
    // This will automatically refresh when a new day begins and reset attendance
    setInterval(checkDayChange, 60000);
    
    // Optional: Refresh attendance data every 2 minutes (much less aggressive than 3 seconds)
    // This keeps the data updated for new scans without being too intrusive
    // You can increase this interval or remove it entirely if not needed
    refreshInterval = setInterval(updateAttendanceTable, 120000);
    
    // Clean up interval when page is hidden (saves resources)
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        if (refreshInterval) {
          clearInterval(refreshInterval);
          refreshInterval = null;
        }
      } else {
        if (!refreshInterval) {
          refreshInterval = setInterval(updateAttendanceTable, 120000);
        }
        // Reload when page becomes visible again to ensure fresh data
        checkDayChange();
        updateAttendanceTable();
      }
    });
    
    // Ensure scanner input has focus when page is clicked
    document.addEventListener('click', function() {
      setTimeout(ensureFocus, 100);
    });

    // Add this at the end of the script section, before the closing 
    document.addEventListener('DOMContentLoaded', function() {
      const openScannerBtn = document.getElementById('openScannerBtn');
      if (openScannerBtn) {
        openScannerBtn.addEventListener('click', function() {
          window.open('<?= base_url('admin/ad-scanner') ?>', '_blank');
        });
      }
    });
  </script>
</body>
</html>
