document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.querySelector(".sidebar");
  const menuToggle = document.getElementById("menuToggle");

  const mainLinks = document.querySelectorAll(".sidebar > ul > li > a");
  const dropdownToggles = document.querySelectorAll(".dropdown-toggle");
  const dropdownLinks = document.querySelectorAll(".dropdown li a");

  // ===== DETECT CURRENT PAGE AND SET ACTIVE LINK =====
  const currentPage = window.location.pathname.split("/").pop();
  mainLinks.forEach(link => {
    const linkHref = link.getAttribute("href");
    link.classList.remove("active");

    if (linkHref && currentPage === linkHref) {
      link.classList.add("active");
    }
  });

  // ===== ACTIVE SECTION FROM URL =====
  const urlParams = new URLSearchParams(window.location.search);
  const section = urlParams.get("section");
  if (section) {
    const activeLink = document.querySelector(`.dropdown li a[href$="section=${section}"]`);
    if (activeLink) {
      dropdownLinks.forEach(l => l.classList.remove("active-section"));
      activeLink.classList.add("active-section");

      const parentDropdown = activeLink.closest(".dropdown");
      if (parentDropdown) {
        const parentToggle = parentDropdown.previousElementSibling;
        parentToggle.classList.add("active");
        parentDropdown.classList.add("show");
      }

      // Remove highlight from other main links except dropdown toggles
      mainLinks.forEach(link => {
        if (!link.classList.contains("dropdown-toggle")) {
          link.classList.remove("active");
        }
      });
    }
  }

  // ===== SIDEBAR TOGGLE FOR MOBILE =====
  if (menuToggle) {
    menuToggle.addEventListener("click", () => {
      sidebar.classList.toggle("show");
      document.body.classList.toggle("sidebar-open");
      menuToggle.classList.toggle("active");
    });
  }

  // ===== CLOSE SIDEBAR WHEN CLICKING OUTSIDE =====
  document.addEventListener("click", (e) => {
    if (
      sidebar.classList.contains("show") &&
      !sidebar.contains(e.target) &&
      !menuToggle.contains(e.target)
    ) {
      sidebar.classList.remove("show");
      document.body.classList.remove("sidebar-open");
      menuToggle.classList.remove("active");
    }
  });
  
  // ===== LOGOUT CONFIRMATION MODAL =====
  // Check if logout button exists on the page
  const logoutBtn = document.querySelector('.logout-btn');
  if (logoutBtn) {
    // Create logout modal dynamically
    const logoutModal = document.createElement('div');
    logoutModal.id = 'logoutModal';
    logoutModal.className = 'logout-modal';
    logoutModal.innerHTML = `
      <div class="logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout from your account?</p>
        <div class="logout-modal-actions">
          <button class="btn logout-confirm-btn" id="confirmLogout">Logout</button>
          <button class="btn logout-cancel-btn" id="cancelLogout">Cancel</button>
        </div>
      </div>
    `;
    document.body.appendChild(logoutModal);
    
    // Get modal elements
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');
    
    // Show modal when logout button is clicked
    logoutBtn.addEventListener('click', function(e) {
      e.preventDefault();
      logoutModal.style.display = 'block';
    });
    
    // Confirm logout
    confirmLogout.addEventListener('click', function() {
      window.location.href = logoutBtn.getAttribute('href');
    });
    
    // Cancel logout
    cancelLogout.addEventListener('click', function() {
      logoutModal.style.display = 'none';
    });
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(e) {
      if (e.target === logoutModal) {
        logoutModal.style.display = 'none';
      }
    });
  }
});

// ===== USER PROFILE DROPDOWN FUNCTIONALITY =====
function initUserProfileDropdown() {
  const userDropdown = document.querySelector('.navbar__user-name-dropdown');
  const dropdownMenu = document.querySelector('.user-dropdown-menu');
  const logoutTrigger = document.querySelector('.logout-trigger');
  const logoutModal = document.getElementById('logoutModal');
  
  if (userDropdown && dropdownMenu) {
    // Click outside to close dropdown
    document.addEventListener('click', (e) => {
      if (!userDropdown.contains(e.target)) {
        dropdownMenu.style.display = 'none';
      }
    });
    
    // Toggle dropdown on user name click
    userDropdown.addEventListener('click', (e) => {
      e.stopPropagation();
      const isVisible = dropdownMenu.style.display === 'block';
      dropdownMenu.style.display = isVisible ? 'none' : 'block';
    });
  }
  
  // Handle logout trigger
  if (logoutTrigger && logoutModal) {
    logoutTrigger.addEventListener('click', (e) => {
      e.preventDefault();
      dropdownMenu.style.display = 'none'; // Hide dropdown
      logoutModal.style.display = 'block'; // Show logout modal
    });
  }
  
  // Handle logout modal buttons
  const confirmLogout = document.getElementById('confirmLogout');
  const cancelLogout = document.getElementById('cancelLogout');
  
  if (confirmLogout && logoutTrigger) {
    confirmLogout.addEventListener('click', () => {
      const logoutUrl = logoutTrigger.getAttribute('data-logout-url');
      if (logoutUrl) {
        window.location.href = logoutUrl;
      }
    });
  }
  
  if (cancelLogout) {
    cancelLogout.addEventListener('click', () => {
      logoutModal.style.display = 'none';
    });
  }
  
  // Close modal when clicking outside
  if (logoutModal) {
    window.addEventListener('click', (e) => {
      if (e.target === logoutModal) {
        logoutModal.style.display = 'none';
      }
    });
  }
}

// Initialize dropdown when DOM is loaded
document.addEventListener('DOMContentLoaded', initUserProfileDropdown);

// ===== AUTO HIDE ALERT MESSAGES =====
const alerts = document.querySelectorAll('.alert');
if (alerts.length > 0) {
  setTimeout(() => {
    alerts.forEach(a => {
      a.style.transition = 'opacity 0.6s';
      a.style.opacity = '0';
      setTimeout(() => a.remove(), 600);
    });
  }, 3500);
}

// ===== SIDEBAR STATE MANAGEMENT =====
// Restore sidebar state on page load
const savedSidebarState = localStorage.getItem('sidebar-hidden');
if (savedSidebarState === 'true') {
  // Class already added in HTML head to prevent FOUC
  document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    if (sidebar && mainContent) {
      sidebar.classList.add('sidebar--hidden');
      mainContent.classList.add('content--expanded');
    }
  });
}

// Add event listener for navbar toggle
document.addEventListener('DOMContentLoaded', () => {
  const navbarToggle = document.querySelector('.navbar__toggle');
  const sidebar = document.querySelector('.sidebar');
  const mainContent = document.querySelector('.main-content');
  
  if (navbarToggle && sidebar && mainContent) {
    navbarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('sidebar--hidden');
      mainContent.classList.toggle('content--expanded');
      document.documentElement.classList.toggle('js-sidebar-hidden');
      document.body.classList.toggle('sidebar-open');
      
      // Save sidebar state to localStorage
      const isHidden = sidebar.classList.contains('sidebar--hidden');
      localStorage.setItem('sidebar-hidden', isHidden);
    });
  }
});