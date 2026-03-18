<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>QR Scanner | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/attendance.css') ?>" />
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <script src="<?= base_url('assets/js/jsQR.js') ?>"></script>
  <style>
    .permission-note {
      font-size: 14px;
      color: #666;
      margin-top: 10px;
      font-style: italic;
    }
    
    .permission-instructions ul {
      text-align: left;
      padding-left: 20px;
      margin: 10px 0;
    }
    
    .permission-instructions li {
      margin-bottom: 5px;
    }
    
    .retry-btn:hover {
      background: #0056b3;
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
      <h1><?= esc($sectionName ?? 'Assigned Section') ?> &mdash; QR Scanner</h1>
      <p class="welcome-msg">Welcome, <?= esc($fullName ?? '') ?></p>
    </header>

    <div class="card summary-card">
      <div>
        <h2>Live Scanner</h2>
        <p>Keep this tab open to continuously record attendance.</p>
      </div>
      <div class="tab-controls">
        <select id="cameraSelect" class="tab-btn" style="min-width: 180px;"></select>
        <button class="tab-btn" onclick="window.open('<?= base_url('admin/ad-dashboard') ?>', '_self')">
          Back to Dashboard
        </button>
      </div>
    </div>

    <section class="scanner-section">
      <video id="cameraPreview" autoplay playsinline muted></video>
      <canvas id="qrCanvas" style="display:none;"></canvas>
      <div class="scanner-hint">
        <p>Position the student's QR code inside the frame. Attendance records automatically once detected.</p>
        <p class="permission-note">Note: You must allow camera access when prompted by your browser. If you've previously denied access, you may need to reset permissions in your browser settings.</p>
      </div>
      <div id="scanFeedback" class="scan-feedback hidden">
        <div class="feedback-content">
          <h3 id="feedbackTitle"></h3>
          <p id="feedbackMessage"></p>
          <div id="permissionInstructions" class="permission-instructions" style="display: none; margin-top: 10px;">
            <p><strong>To enable camera access:</strong></p>
            <ul>
              <li>Click the camera icon in your browser's address bar</li>
              <li>Select "Always allow" or "Allow" for camera permissions</li>
              <li>Refresh this page</li>
            </ul>
          </div>
          <button id="retryCameraBtn" class="retry-btn" style="display: none; margin-top: 15px; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Retry Camera Access
          </button>
        </div>
      </div>
    </section>
  </main>

  <script>
    const SCAN_ENDPOINT = "<?= base_url('admin/scan') ?>";

    const feedbackEl = document.getElementById('scanFeedback');
    const feedbackTitle = document.getElementById('feedbackTitle');
    const feedbackMessage = document.getElementById('feedbackMessage');
    const videoEl = document.getElementById('cameraPreview');
    const canvasEl = document.getElementById('qrCanvas');
    const canvasCtx = canvasEl.getContext('2d');
    const cameraSelect = document.getElementById('cameraSelect');

    let mediaStream = null;
    let animationId = null;
    let scanning = false;
    let resumeTimer = null;
    let feedbackTimeout = null;
    let lastScanValue = null;
    let lastScanTime = 0;
    let availableCameras = [];
    let activeCameraId = null;

    cameraSelect.disabled = true;
    cameraSelect.innerHTML = '<option>Loading cameras...</option>';

    const showFeedback = (title, message, success = true, duration = 3000, animate = false) => {
      if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
        feedbackTimeout = null;
      }
      feedbackTitle.textContent = title;
      feedbackMessage.textContent = message;
      feedbackEl.classList.remove('hidden', 'error', 'loading');
      feedbackEl.classList.toggle('error', !success);
      feedbackEl.classList.toggle('loading', animate);

      // Show permission instructions for camera-related errors
      const permissionInstructions = document.getElementById('permissionInstructions');
      const retryBtn = document.getElementById('retryCameraBtn');
      if (title === "Permission Needed" || title === "Camera Error") {
        permissionInstructions.style.display = 'block';
        retryBtn.style.display = 'inline-block';
      } else {
        permissionInstructions.style.display = 'none';
        retryBtn.style.display = 'none';
      }

      if (duration > 0) {
        feedbackTimeout = setTimeout(() => {
          feedbackEl.classList.add('hidden');
          feedbackTimeout = null;
        }, duration);
      }
    };

    const stopStream = () => {
      if (resumeTimer) {
        clearTimeout(resumeTimer);
        resumeTimer = null;
      }
      if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
      }
      if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
      }
      scanning = false;
    };

    const scanFrame = () => {
      if (!scanning) return;

      if (videoEl.readyState === videoEl.HAVE_ENOUGH_DATA) {
        canvasEl.width = videoEl.videoWidth;
        canvasEl.height = videoEl.videoHeight;
        canvasCtx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
        const imageData = canvasCtx.getImageData(0, 0, canvasEl.width, canvasEl.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });

        if (code?.data) {
          handleScan(code.data);
          return;
        }
      }

      animationId = requestAnimationFrame(scanFrame);
    };

    const parseQrPayload = (text) => {
      const lines = text.split(/\r?\n/);
      const payload = {};
      lines.forEach(line => {
        const [label, value] = line.split(':').map(str => str?.trim());
        if (!label || !value) return;
        const key = label.toLowerCase();
        if (key.includes('student no')) payload.student_number = value;
        if (key === 'name') payload.name = value;
        if (key === 'course') payload.course = value;
      });
      return payload;
    };

    const handleScan = (decodedText) => {
      const now = Date.now();
      if (decodedText === lastScanValue && (now - lastScanTime) < 2000) {
        animationId = requestAnimationFrame(scanFrame);
        return;
      }

      lastScanValue = decodedText;
      lastScanTime = now;

      const payload = parseQrPayload(decodedText);
      if (!payload.student_number) {
        showFeedback("Invalid QR Code", "This QR code does not contain a valid student number. Please ensure you're scanning a proper student QR code.", false);
        animationId = requestAnimationFrame(scanFrame);
        return;
      }

      scanning = false;
      showFeedback("Processing...", "Recording attendance for " + (payload.name || payload.student_number) + "...", true, 0, true);

      fetch(SCAN_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ student_number: payload.student_number })
      })
        .then(res => res.json().then(data => ({ status: res.status, body: data })))
        .then(({ status, body }) => {
          if (status >= 200 && status < 300 && body.success) {
            showFeedback("Attendance Recorded", body.message || "Attendance successfully recorded for " + (payload.name || payload.student_number) + ".", true);
            
            // Store scan result for dashboard notification
            const scanResult = {
              type: 'success',
              message: body.message || "Attendance successfully recorded for " + (payload.name || payload.student_number) + ".",
              timestamp: new Date().toISOString(),
              student: {
                name: payload.name || payload.student_number,
                student_number: payload.student_number
              }
            };
            
            // Save to localStorage and dispatch event
            localStorage.setItem('lastScanResult', JSON.stringify(scanResult));
            window.dispatchEvent(new CustomEvent('scanResult', { detail: scanResult }));
          } else if (status === 429) {
            // Handle rate limiting (10-minute restriction)
            showFeedback("Scan Failed", body.message || "This student has already been scanned.", false);
            
            // Store scan result for dashboard notification
            const scanResult = {
              type: 'error',
              message: body.message || "This student has already been scanned.",
              timestamp: new Date().toISOString(),
              student: {
                name: payload.name || payload.student_number,
                student_number: payload.student_number
              }
            };
            
            // Save to localStorage and dispatch event
            localStorage.setItem('lastScanResult', JSON.stringify(scanResult));
            window.dispatchEvent(new CustomEvent('scanResult', { detail: scanResult }));
          } else {
            showFeedback("Scan Failed", body.message || "Could not record attendance. Please try again.", false);
            
            // Store scan result for dashboard notification
            const scanResult = {
              type: 'error',
              message: body.message || "Could not record attendance. Please try again.",
              timestamp: new Date().toISOString(),
              student: {
                name: payload.name || payload.student_number,
                student_number: payload.student_number
              }
            };
            
            // Save to localStorage and dispatch event
            localStorage.setItem('lastScanResult', JSON.stringify(scanResult));
            window.dispatchEvent(new CustomEvent('scanResult', { detail: scanResult }));
          }
        })
        .catch(err => {
          console.error(err);
          showFeedback("System Error", "An error occurred while recording attendance. Please check your connection and try again.", false);
        })
        .finally(() => {
          resumeTimer = setTimeout(() => {
            feedbackEl.classList.add('hidden');
            scanning = true;
            scanFrame();
          }, 3000);
        });
    };

    const startStream = async () => {
      try {
        stopStream();

        const constraints = {
          audio: false,
          video: activeCameraId ? { deviceId: { exact: activeCameraId } } : { facingMode: 'environment' }
        };

        mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
        videoEl.srcObject = mediaStream;
        videoEl.setAttribute('playsinline', true);

        await videoEl.play();

        // Clear any previous error messages when camera starts successfully
        if (feedbackEl && !feedbackEl.classList.contains('hidden') && 
            (feedbackTitle.textContent === "Permission Needed" || 
             feedbackTitle.textContent === "Camera Error" || 
             feedbackTitle.textContent === "Camera Not Found" || 
             feedbackTitle.textContent === "Unsupported Browser")) {
          feedbackEl.classList.add('hidden');
        }

        scanning = true;
        scanFrame();
      } catch (err) {
        console.error('Unable to start camera stream:', err);
        if (err.name === 'NotAllowedError' || err.name === 'SecurityError') {
          showFeedback("Permission Needed", "Allow camera access in your browser to start scanning.", false, 0);
        } else if (err.name === 'NotFoundError' || err.name === 'OverconstrainedError') {
          showFeedback("Camera Error", "Selected camera is unavailable.", false, 0);
        } else {
          showFeedback("Camera Error", "Failed to access the camera: " + err.message, false, 0);
        }
      }
    };

    const initCameras = async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        showFeedback("Unsupported Browser", "Camera access is not supported in this browser.", false, 0);
        cameraSelect.innerHTML = '<option>Camera not supported</option>';
        return;
      }

      try {
        // Show loading state
        cameraSelect.disabled = true;
        cameraSelect.innerHTML = '<option>Loading cameras...</option>';
        
        const tempStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        tempStream.getTracks().forEach(track => track.stop());

        const devices = await navigator.mediaDevices.enumerateDevices();
        availableCameras = devices.filter(d => d.kind === 'videoinput');

        if (!availableCameras.length) {
          cameraSelect.innerHTML = '<option>No camera found</option>';
          showFeedback("Camera Not Found", "No camera devices were detected on this device.", false, 0);
          return;
        }

        cameraSelect.innerHTML = '';
        availableCameras.forEach((cam, index) => {
          const option = document.createElement('option');
          option.value = cam.deviceId;
          option.textContent = cam.label || `Camera ${index + 1}`;
          cameraSelect.appendChild(option);
        });

        const backCamera = availableCameras.find(cam => cam.label?.toLowerCase().includes('back'));
        activeCameraId = backCamera ? backCamera.deviceId : availableCameras[availableCameras.length - 1].deviceId;
        cameraSelect.value = activeCameraId;
        cameraSelect.disabled = false;

        cameraSelect.addEventListener('change', () => {
          activeCameraId = cameraSelect.value;
          showFeedback("Switching Camera", "Loading camera feed...", true, 1500, true);
          setTimeout(() => {
            startStream();
          }, 600);
        });

        await startStream();
      } catch (err) {
        console.error('Camera initialization error:', err);
        cameraSelect.innerHTML = '<option>Camera access denied</option>';
        cameraSelect.disabled = false;
        
        // Only show permission error if we haven't successfully started the stream
        if (!mediaStream) {
          showFeedback("Permission Needed", "Allow camera access in your browser to start scanning.", false, 0);
        }
      }
    };

    // Add event listener for retry button
    document.getElementById('retryCameraBtn').addEventListener('click', () => {
      showFeedback("Retrying...", "Attempting to access camera...", true, 0, true);
      setTimeout(() => {
        initCameras();
      }, 1000);
    });

    initCameras();

    // Clean up old scan results periodically
    setInterval(() => {
      try {
        const lastScan = localStorage.getItem('lastScanResult');
        if (lastScan) {
          const scanResult = JSON.parse(lastScan);
          // Clean up scan results older than 1 minute
          if (scanResult && new Date() - new Date(scanResult.timestamp) > 60000) {
            localStorage.removeItem('lastScanResult');
          }
        }
      } catch (err) {
        console.error('Error cleaning up old scan results:', err);
      }
    }, 30000); // Check every 30 seconds

    window.addEventListener('beforeunload', () => {
      stopStream();
    });
  </script>
</body>
</html>

