<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>USTP Library Attendance | Admin Login</title>
  <link rel="icon" type="image/png" href="<?= base_url('assets/icons/logo1.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>">
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="logo-container">
        <img src="<?= base_url('assets/icons/logo1.png') ?>" alt="USTP Logo" class="logo" id="logo">
      </div>

      <h3>Admin Login Portal</h3>

      <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= session()->getFlashdata('error'); ?></div>
      <?php endif; ?>

      <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success'); ?></div>
      <?php endif; ?>

      <form action="<?= base_url('login/check') ?>" method="POST">
        <div class="input-group">
          <label for="username">Email</label>
          <input type="text" id="username" name="username" placeholder="Enter your email" required>
        </div>

        <div class="input-group">
          <label for="password">Password</label>
          <div class="password-wrapper">
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <span class="toggle-password" onclick="togglePassword()">
              <img src="<?= base_url('assets/icons/eye-show.png') ?>" alt="Show Password" id="eyeIcon" width="20">
            </span>
          </div>
        </div>

        <button type="submit" class="btn-login">Login</button>
      </form>

    </div>
  </div>

  <script src="<?= base_url('assets/js/script.js') ?>"></script>
  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      
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
</body>
</html>
