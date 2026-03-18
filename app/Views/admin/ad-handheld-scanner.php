<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Handheld Scanner | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/attendance.css') ?>" />
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <style>
    .scanner-card {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .scan-input-wrapper {
      /* Hidden visually but still focusable for the scanner */
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
    .hint-text {
      font-size: 0.9rem;
      color: #666;
      max-width: 520px;
    }
    .feedback-banner {
      margin-top: 12px;
      padding: 10px 14px;
      border-radius: 6px;
      font-size: 0.95rem;
      display: none;
    }
    .feedback-banner.success {
      display: block;
      background: #e6f4ea;
      color: #146c2e;
      border: 1px solid #a5d6a7;
    }
    .feedback-banner.error {
      display: block;
      background: #fdecea;
      color: #b71c1c;
      border: 1px solid #f5c6cb;
    }
    .scanner-content{
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .scan-icon{
      width: 250px;
      height: 250px;
      object-fit: contain;
      display: block;
      margin: 0 auto;
      margin-top: 20px;
      margin-bottom: 20px;
    }

  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="logo">
      <img src="<?= base_url('assets/icons/logo.png') ?>" alt="USTP Logo" class="logo-img" />
    </div>

    <ul class="nav-links">
      <li><a href="<?= base_url('admin/ad-dashboard') ?>"><img src="<?= base_url('assets/icons/attendance.png') ?>" alt="Attendance">Student Attendance</a></li>
      <li><a href="<?= base_url('admin/ad-reports') ?>"><img src="<?= base_url('assets/icons/report.png') ?>" alt="Reports">Reports</a></li>
      <li><a href="<?= base_url('admin/ad-profile') ?>"><img src="<?= base_url('assets/icons/profile.png') ?>" alt="Profile">Profile</a></li>
    </ul>

    <a href="<?= base_url('logout') ?>" class="logout-btn">
      <img src="<?= base_url('assets/icons/logout.png') ?>" alt="Logout">Logout
    </a>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <header>
      <button class="menu-toggle" id="menuToggle">☰</button>
      <h1><?= esc($sectionName ?? 'Assigned Section') ?> &mdash; Handheld Scanner</h1>
      <p class="welcome-msg">Welcome, <?= esc($fullName ?? '') ?></p>
    </header>

    <div class="card summary-card scanner-card">
      <div class="scanner-content">
        <h2>Handheld QR Scanner</h2>
        <p class="hint-text">
          Scan the student's QR code to record attendance.
        </p>
        <img src="<?= base_url('assets/icons/qr-scan.png') ?>" alt="QR Scanner" class="scan-icon">
      </div>
      <div>
        <div class="scan-input-wrapper">
          <input
            id="scanInput"
            type="text"
            class="scan-input"
            autocomplete="off"
            autofocus
          />
        </div>
        <div id="scanFeedback" class="feedback-banner"></div>
      </div>
      <div>
        <button class="tab-btn" onclick="window.location.href='<?= base_url('admin/ad-dashboard') ?>'">
          Back to Dashboard
        </button>
      </div>
    </div>
  </main>

  <script>
    const SCAN_ENDPOINT = "<?= base_url('admin/scan') ?>";

    const inputEl = document.getElementById('scanInput');
    const feedbackEl = document.getElementById('scanFeedback');
    let scanTimer = null;

    // Always keep focus on the input so the handheld scanner can type into it
    function ensureFocus() {
      if (document.activeElement !== inputEl) {
        inputEl.focus();
      }
    }
    window.addEventListener('load', ensureFocus);
    window.addEventListener('click', ensureFocus);

    function showFeedback(message, type) {
      feedbackEl.textContent = message;
      feedbackEl.classList.remove('success', 'error');
      if (type === 'success') feedbackEl.classList.add('success');
      if (type === 'error') feedbackEl.classList.add('error');
      
      feedbackEl.style.display = 'block';
      
      // Auto-hide success messages after 3 seconds
      if (type === 'success') {
        setTimeout(() => {
          feedbackEl.classList.remove('success', 'error');
          feedbackEl.style.display = 'none';
        }, 3000);
      }
    }

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

      feedbackEl.style.display = 'block';
      showFeedback('Processing scan...', 'success');

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
            showFeedback(body.message || 'Attendance recorded successfully.', 'success');
          } else if (status === 429) {
            showFeedback(body.message || 'Student has already scanned within the last 10 minutes.', 'error');
          } else {
            showFeedback(body.message || 'Could not record attendance. Please try again.', 'error');
          }
        })
        .catch(error => {
          console.error('Scan error:', error);
          showFeedback('System error while recording attendance. Please check your connection.', 'error');
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
  </script>
</body>
</html>


