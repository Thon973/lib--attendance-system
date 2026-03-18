<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Admin | USTP Library Dashboard</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">

  <!-- Styles -->
  <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/manage-admin.css') ?>" />

  <script>
    const BASE_URL = "<?= base_url() ?>";
  </script>

  <!-- Scripts -->
  <script defer src="<?= base_url('assets/js/script.js') ?>"></script>
  <script defer src="<?= base_url('assets/js/manage-admin.js') ?>"></script>
  <script>
    function togglePassword(inputId, iconId) {
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
  <style>
    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-wrapper input {
      width: 100%;
      padding-right: 40px;
    }
    .toggle-password {
      position: absolute;
      right: 10px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .toggle-password img {
      width: 20px;
      height: 20px;
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

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <header class="main-content-header">
      <div class="header-content">
        <div class="header-left">
          <button class="menu-toggle" id="menuToggle">☰</button>
          <h1>Manage Admins</h1>
        </div>
        <div class="header-right">
          <!-- Search Bar -->
          <div class="search-bar">
            <button id="searchBtn">
              <img src="<?= base_url('assets/icons/search.png') ?>" alt="Search">
            </button>
            <input type="text" id="adminSearch" placeholder="Search admins..." />
          </div>
        </div>
      </div>
    </header>

    <!-- MANAGEMENT SECTION -->
    <section class="management-section">
      <div class="actions">
          <h2 class="table-title">Admins List</h2>
          <div class="actions-right">
            <button id="addAdminBtn" class="btn add-btn">
              <img src="<?= base_url('assets/icons/add-w.png') ?>" alt="Add" class="btn-icon">
              Add Admin
            </button>
          </div>
      </div>

      <div class="management-content">
        <?php $currentAdminId = session()->get('admin_id'); ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th class="sortable" data-sort="name">
                Full Name
                <span class="sort-indicator"></span>
              </th>
              <th>Email</th>
              <th>Role</th>
              <th>Assigned Section</th>
              <th>Status</th>
              <th class="last-login-header">Last Login</th>
              <th style="text-align: center;">Action</th>
            </tr>
          </thead>
          <tbody id="adminTableBody" data-current-admin-id="<?= esc($currentAdminId) ?>">
            <?php if (!empty($admins)): ?>
            <?php foreach ($admins as $a): ?>
              <?php
                $statusValue = $a['status'] ?? 'Active';
                $isActive = $statusValue === 'Active';
                $isSelf = (string) ($a['admin_id'] ?? '') === (string) ($currentAdminId ?? '');
              ?>
              <tr
                data-admin-id="<?= $a['admin_id'] ?>"
                data-full-name="<?= esc($a['full_name']) ?>"
                data-email="<?= esc($a['email']) ?>"
                data-role="<?= esc($a['role']) ?>"
                data-section-id="<?= esc($a['section_id'] ?? '') ?>"
                data-status="<?= esc($a['status'] ?? 'Active') ?>"
              >
                <td></td>
                <td><?= esc($a['full_name']) ?></td>
                <td><?= esc($a['email']) ?></td>
                <td><?= esc($a['role']) ?></td>
                  <td>
                    <?php
                      $sectionName = '-';
                      if ($a['role'] === 'Admin' && !empty($a['section_id']) && isset($sections[$a['section_id']])) {
                        $sectionName = esc($sections[$a['section_id']]['section_name']);
                      }
                      echo $sectionName;
                    ?>
                  </td>
                  <td class="status-cell">
                    <div class="status-wrapper">
                      <label class="switch">
                        <input type="checkbox"
                               class="status-toggle"
                               data-id="<?= $a['admin_id'] ?>"
                               <?= $isActive ? 'checked' : '' ?>
                               <?= $isSelf ? 'disabled' : '' ?>>
                        <span class="slider"></span>
                      </label>
                      <span class="status-label"><?= esc($statusValue) ?></span>
                    </div>
                  </td>
                  <td class="last-login-cell">
                    <?php if (!empty($a['last_login'])): ?>
                      <?= esc(date('M d, Y h:i A', strtotime($a['last_login']))) ?>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td style="text-align:center;">
                    <button class="edit-btn">Edit</button>
                    <button class="delete-btn" data-name="<?= esc($a['full_name']) ?>">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align:center; color:#888;">No admin data available</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <!-- ADD ADMIN MODAL -->
  <div id="addAdminModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" id="closeAddAdminModal">&times;</span>
      <h3>Add New Admin</h3>

      <form id="addAdminForm" method="POST" action="<?= base_url('superadmin/addAdminAjax') ?>">
        <?= csrf_field() ?>

        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="full_name" id="adminName" placeholder="Enter full name" required />
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" id="adminEmail" placeholder="Enter email address" required />
        </div>

        <div class="form-group">
          <label>Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" id="adminPassword" placeholder="Enter password" required />
            <span class="toggle-password" onclick="togglePassword('adminPassword', 'adminPasswordEye')">
              <img src="<?= base_url('assets/icons/eye-show.png') ?>" alt="Show Password" id="adminPasswordEye" width="20">
            </span>
          </div>
        </div>

        <div class="form-group">
          <label>Role</label>
          <select name="role" id="adminRole" required>
            <option value="">Select Role</option>
            <option value="SuperAdmin">SuperAdmin</option>
            <option value="Admin">Admin</option>
          </select>
        </div>

        <div class="form-group" id="adminSectionGroup" style="display:none;">
          <label>Section</label>
          <select name="section_id" id="adminSection">
            <option value="">Select Section</option>
            <?php foreach($sections as $s): ?>
              <option value="<?= $s['section_id'] ?>"><?= esc($s['section_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status" id="adminStatus" required>
            <option value="Active" selected>Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>

        <button type="submit" class="btn save-btn">Save Admin</button>
      </form>
    </div>
  </div>

  <!-- ✅ EDIT ADMIN MODAL (moved OUTSIDE Add form) -->
  <div id="editAdminModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" id="closeEditAdminModal">&times;</span>
      <h3>Edit Admin</h3>

      <form id="editAdminForm" method="POST" action="<?= base_url('superadmin/updateAdminAjax') ?>">
        <?= csrf_field() ?>
        <input type="hidden" id="editAdminId" name="admin_id" />

        <div class="form-group">
          <label>Full Name</label>
          <input type="text" id="editAdminName" name="full_name" required />
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" id="editAdminEmail" name="email" required />
        </div>

        <hr class="form-separator">
        <p class="form-section-title">Change Password</p>
        <p class="form-hint">Leave the password fields blank if you do not want to change the password.</p>

        <div class="form-group">
          <label>New Password</label>
          <div class="password-wrapper">
            <input type="password" id="editAdminPassword" name="password" placeholder="Enter new password (optional)" />
            <span class="toggle-password" onclick="togglePassword('editAdminPassword', 'editAdminPasswordEye')">
              <img src="<?= base_url('assets/icons/eye-show.png') ?>" alt="Show Password" id="editAdminPasswordEye" width="20">
            </span>
          </div>
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <div class="password-wrapper">
            <input type="password" id="editAdminConfirmPassword" name="confirm_password" placeholder="Re-enter new password" />
            <span class="toggle-password" onclick="togglePassword('editAdminConfirmPassword', 'editAdminConfirmPasswordEye')">
              <img src="<?= base_url('assets/icons/eye-show.png') ?>" alt="Show Password" id="editAdminConfirmPasswordEye" width="20">
            </span>
          </div>
        </div>

        <div class="form-group">
          <label>Role</label>
          <select id="editAdminRole" name="role" required>
            <option value="SuperAdmin">SuperAdmin</option>
            <option value="Admin">Admin</option>
          </select>
        </div>

        <div class="form-group" id="editAdminSectionGroup" style="display:none;">
          <label>Section</label>
          <select id="editAdminSection" name="section_id">
            <option value="">Select Section</option>
            <?php foreach($sections as $s): ?>
              <option value="<?= $s['section_id'] ?>"><?= esc($s['section_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select id="editAdminStatus" name="status" required>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>

        <button type="submit" class="btn save-btn">Update Admin</button>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteAdminModal" class="modal">
    <div class="modal-content confirm-modal">
      <h3>Delete Admin</h3>
      <p>Are you sure you want to delete <strong id="deleteAdminName">this admin</strong>? This action cannot be undone.</p>
      <div class="modal-actions">
        <button type="button" id="confirmDeleteAdmin" class="btn delete-btn">Delete</button>
        <button type="button" id="cancelDeleteAdmin" class="btn cancel-btn">Cancel</button>
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
</body>
</html>
