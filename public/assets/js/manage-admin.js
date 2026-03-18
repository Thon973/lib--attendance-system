document.addEventListener("DOMContentLoaded", () => {
  const addAdminBtn = document.getElementById("addAdminBtn");
  const addAdminModal = document.getElementById("addAdminModal");
  const closeAddAdminModal = document.getElementById("closeAddAdminModal");
  const editAdminModal = document.getElementById("editAdminModal");
  const closeEditAdminModal = document.getElementById("closeEditAdminModal");
  const deleteAdminModal = document.getElementById("deleteAdminModal");
  const confirmDeleteBtn = document.getElementById("confirmDeleteAdmin");
  const cancelDeleteBtn = document.getElementById("cancelDeleteAdmin");
  const deleteAdminName = document.getElementById("deleteAdminName");

  const addAdminForm = document.getElementById("addAdminForm");
  const editAdminForm = document.getElementById("editAdminForm");
  const adminTableBody = document.getElementById("adminTableBody");

  const adminRole = document.getElementById("adminRole");
  const adminSectionGroup = document.getElementById("adminSectionGroup");
  const adminSectionSelect = document.getElementById("adminSection");

  const editAdminId = document.getElementById("editAdminId");
  const editAdminName = document.getElementById("editAdminName");
  const editAdminEmail = document.getElementById("editAdminEmail");
  const editAdminPassword = document.getElementById("editAdminPassword");
  const editAdminConfirmPassword = document.getElementById("editAdminConfirmPassword");
  const editAdminRole = document.getElementById("editAdminRole");
  const editAdminSectionGroup = document.getElementById("editAdminSectionGroup");
  const editAdminSectionSelect = document.getElementById("editAdminSection");
  const editAdminStatus = document.getElementById("editAdminStatus");
  
  const adminSearch = document.getElementById("adminSearch");
  const searchAdminBtn = document.getElementById("searchAdminBtn");

  let pendingDeleteId = null;
  let pendingDeleteRow = null;
  let originalAdminRows = []; // Store original rows for search reset
  
  // Ensure page is fully loaded before processing
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize after DOM is fully loaded
      setTimeout(() => {
        // Store original rows for search functionality
        if (adminTableBody) {
          originalAdminRows = Array.from(adminTableBody.querySelectorAll('tr')).filter(row => {
            const firstCell = row.querySelector('td');
            return firstCell && !firstCell.hasAttribute('colspan');
          });
          
          // Also store the initial dataset for each row if it exists
          originalAdminRows.forEach(row => {
            const adminId = row.dataset.adminId || row.getAttribute('data-admin-id');
            const fullName = row.dataset.fullName || row.getAttribute('data-full-name') || row.cells[0]?.textContent;
            const email = row.dataset.email || row.getAttribute('data-email') || row.cells[1]?.textContent;
            const role = row.dataset.role || row.getAttribute('data-role') || row.cells[2]?.textContent;
            const sectionId = row.dataset.sectionId || row.getAttribute('data-section-id') || row.cells[3]?.textContent;
            const status = row.dataset.status || row.getAttribute('data-status') || row.querySelector('.status-label')?.textContent || 'Active';
            
            // Update row dataset if not already set
            if (adminId) row.dataset.adminId = adminId;
            if (fullName) row.dataset.fullName = fullName;
            if (email) row.dataset.email = email;
            if (role) row.dataset.role = role;
            if (sectionId !== undefined) row.dataset.sectionId = sectionId;
            if (status) row.dataset.status = status;
          });
          
          // Reattach status toggles after initialization
          attachStatusToggles();
        }
        
        // Initialize sorting after page load
        initSorting();
        
        // Make sure all rows are visible initially
        if (adminTableBody) {
          const allRows = adminTableBody.querySelectorAll('tr');
          allRows.forEach(row => {
            const firstCell = row.querySelector('td');
            if (!firstCell || !firstCell.hasAttribute('colspan')) {
              row.style.display = ''; // Ensure visibility
            }
          });
        }
      }, 100); // Small delay to ensure DOM is ready
    });
  } else {
    // DOM is already loaded
    setTimeout(() => {
      // Store original rows for search functionality
      if (adminTableBody) {
        originalAdminRows = Array.from(adminTableBody.querySelectorAll('tr')).filter(row => {
          const firstCell = row.querySelector('td');
          return firstCell && !firstCell.hasAttribute('colspan');
        });
        
        // Also store the initial dataset for each row if it exists
        originalAdminRows.forEach(row => {
          const adminId = row.dataset.adminId || row.getAttribute('data-admin-id');
          const fullName = row.dataset.fullName || row.getAttribute('data-full-name') || row.cells[0]?.textContent;
          const email = row.dataset.email || row.getAttribute('data-email') || row.cells[1]?.textContent;
          const role = row.dataset.role || row.getAttribute('data-role') || row.cells[2]?.textContent;
          const sectionId = row.dataset.sectionId || row.getAttribute('data-section-id') || row.cells[3]?.textContent;
          const status = row.dataset.status || row.getAttribute('data-status') || row.querySelector('.status-label')?.textContent || 'Active';
          
          // Update row dataset if not already set
          if (adminId) row.dataset.adminId = adminId;
          if (fullName) row.dataset.fullName = fullName;
          if (email) row.dataset.email = email;
          if (role) row.dataset.role = role;
          if (sectionId !== undefined) row.dataset.sectionId = sectionId;
          if (status) row.dataset.status = status;
        });
        
        // Reattach status toggles after initialization
        attachStatusToggles();
      }
      
      // Initialize sorting after page load
      initSorting();
      
      // Make sure all rows are visible initially
      if (adminTableBody) {
        const allRows = adminTableBody.querySelectorAll('tr');
        allRows.forEach(row => {
          const firstCell = row.querySelector('td');
          if (!firstCell || !firstCell.hasAttribute('colspan')) {
            row.style.display = ''; // Ensure visibility
          }
        });
      }
    }, 100); // Small delay to ensure DOM is ready
  }

  // Sorting functionality
  let currentSort = { column: null, direction: null };

  if (!addAdminForm || !adminTableBody) {
    return;
  }

  const currentAdminId = adminTableBody.dataset.currentAdminId || "";
  const isSelfAdmin = adminId => currentAdminId !== "" && String(adminId) === String(currentAdminId);

  if (typeof BASE_URL === "undefined") {
    console.error("BASE_URL is not defined. Please ensure it is set in the view.");
    return;
  }

  const openModal = modal => { if (modal) modal.style.display = "block"; };
  const closeModal = modal => { if (modal) modal.style.display = "none"; };

  const toggleSectionField = (role, group, select) => {
    if (!group || !select) return;
    if (role === "Admin") {
      group.style.display = "block";
    } else {
      group.style.display = "none";
      select.value = "";
    }
  };

  // Initialize toast container if it doesn't exist
  let toastContainer = document.getElementById("toastContainer");
  if (!toastContainer) {
    toastContainer = document.createElement("div");
    toastContainer.id = "toastContainer";
    document.body.appendChild(toastContainer);
  }

  const showToast = (message, type = "success") => {
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add("show"), 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  };

  const getSectionName = (select, sectionId) => {
    if (!select) return "-";
    if (!sectionId) return "-";
    const option = [...select.options].find(opt => opt.value === sectionId);
    return option ? option.text : "-";
  };

  const createStatusHTML = (status, adminId, isDisabled = false) => {
    const isActive = status === "Active";
    const disabledAttr = isDisabled ? "disabled" : "";
    const checkedAttr = isActive ? "checked" : "";
    return `
      <div class="status-wrapper">
        <label class="switch">
          <input type="checkbox"
                 class="status-toggle"
                 data-id="${adminId}"
                 ${checkedAttr}
                 ${disabledAttr}>
          <span class="slider"></span>
        </label>
        <span class="status-label">${status}</span>
      </div>
    `;
  };

  const attachStatusToggles = (root = document) => {
    root.querySelectorAll(".status-toggle").forEach(toggle => {
      if (toggle.dataset.listenerAttached === "1") return;
      toggle.dataset.listenerAttached = "1";

      toggle.addEventListener("change", function () {
        if (this.disabled) return;

        const adminId = this.dataset.id;
        const newStatus = this.checked ? "Active" : "Inactive";
        const label = this.closest(".status-wrapper")?.querySelector(".status-label");
        if (label) label.textContent = newStatus;

        fetch(`${BASE_URL}/superadmin/updateAdminStatus/${adminId}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ status: newStatus }),
        })
          .then(res => res.json())
          .then(result => {
            if (!result.success) {
              this.checked = !this.checked;
              if (label) {
                label.textContent = this.checked ? "Active" : "Inactive";
              }
              showToast(result.message || "Failed to update status.", "error");
            } else {
              showToast(`Status updated to ${newStatus}.`, "success");
              const row = this.closest("tr");
              if (row) {
                row.dataset.status = newStatus;
              }
            }
          })
          .catch(() => {
            this.checked = !this.checked;
            if (label) {
              label.textContent = this.checked ? "Active" : "Inactive";
            }
            showToast("Server error while updating status.", "error");
          });
      });
    });
  };

  const updateRowDataset = (row, data) => {
    if (!row || !data) return;
    if (data.admin_id !== undefined) row.dataset.adminId = data.admin_id;
    if (data.full_name !== undefined) row.dataset.fullName = data.full_name;
    if (data.email !== undefined) row.dataset.email = data.email;
    if (data.role !== undefined) row.dataset.role = data.role;
    if (data.section_id !== undefined) row.dataset.sectionId = data.section_id ?? "";
    if (data.status !== undefined) row.dataset.status = data.status ?? "Active";
  };

  // ===== Modal Controls =====
  addAdminBtn?.addEventListener("click", () => openModal(addAdminModal));
  closeAddAdminModal?.addEventListener("click", () => closeModal(addAdminModal));
  closeEditAdminModal?.addEventListener("click", () => closeModal(editAdminModal));
  cancelDeleteBtn?.addEventListener("click", () => {
    closeModal(deleteAdminModal);
    pendingDeleteId = null;
    pendingDeleteRow = null;
  });

  window.addEventListener("click", e => {
    // Modal will only close when clicking the close button or cancel button
  });

  // Ensure section field visibility reflects current role
  toggleSectionField(adminRole?.value, adminSectionGroup, adminSectionSelect);

  adminRole?.addEventListener("change", () => toggleSectionField(adminRole.value, adminSectionGroup, adminSectionSelect));
  editAdminRole?.addEventListener("change", () => toggleSectionField(editAdminRole.value, editAdminSectionGroup, editAdminSectionSelect));

  // ===== Add Admin =====
  addAdminForm.addEventListener("submit", async e => {
    e.preventDefault();

    const fullName = document.getElementById("adminName").value.trim();
    const email = document.getElementById("adminEmail").value.trim();
    const password = document.getElementById("adminPassword").value.trim();
    const role = adminRole.value;
    const section = adminSectionSelect.value;
    const status = document.getElementById("adminStatus").value;

    if (!fullName || !email || !password || !role) {
      showToast("Please fill in all required fields.", "error");
      return;
    }

    if (role === "Admin" && !section) {
      showToast("Please select a section for Admin role.", "error");
      return;
    }

    try {
      const formData = new FormData();
      formData.append("full_name", fullName);
      formData.append("email", email);
      formData.append("password", password);
      formData.append("role", role);
      formData.append("section_id", role === "Admin" ? section : "");
      formData.append("status", status);

      const response = await fetch(`${BASE_URL}/superadmin/addAdminAjax`, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (!result.success) {
        showToast(result.message || "Failed to add admin.", "error");
        return;
      }

      const sectionName = role === "Admin"
        ? getSectionName(adminSectionSelect, section)
        : "-";

      // Remove "No admin data available" row if it exists
      const noDataRow = adminTableBody.querySelector("tr td[colspan]");
      if (noDataRow) {
        noDataRow.closest("tr").remove();
      }

      const tr = document.createElement("tr");
      tr.setAttribute("data-admin-id", result.admin_id);
      tr.setAttribute("data-full-name", fullName);
      tr.setAttribute("data-email", email);
      tr.setAttribute("data-role", role);
      tr.setAttribute("data-section-id", role === "Admin" ? section : "");
      tr.setAttribute("data-status", status);
      
      const statusMarkup = createStatusHTML(status, result.admin_id, isSelfAdmin(result.admin_id));

      tr.innerHTML = `
        <td></td>
        <td>${fullName}</td>
        <td>${email}</td>
        <td>${role}</td>
        <td>${sectionName}</td>
        <td class="status-cell">${statusMarkup}</td>
        <td class="last-login-cell">-</td>
        <td style="text-align:center;">
          <button class="edit-btn">Edit</button>
          <button class="delete-btn" data-name="${fullName}">Delete</button>
        </td>
      `;

      adminTableBody.appendChild(tr);
      attachStatusToggles(tr);
      
      // Update original rows array to include the new admin
      originalAdminRows.push(tr);
      
      // Update row numbers
      updateRowNumbers();

      // Re-sort table if sorting is active
      if (currentSort.column && currentSort.direction) {
        sortTableRows();
      }

      // Re-run search if there's an active search term
      if (adminSearch && adminSearch.value.trim()) {
        performSearch();
      }
      
      addAdminForm.reset();
      toggleSectionField(adminRole.value, adminSectionGroup, adminSectionSelect);
      closeModal(addAdminModal);
      showToast("Admin added successfully.", "success");
    } catch (err) {
      console.error("Error adding admin:", err);
      showToast("Server error while adding admin.", "error");
    }
  });

  // ===== Table Actions (Edit / Delete) =====
  adminTableBody.addEventListener("click", e => {
    const target = e.target;
    if (target.classList.contains("edit-btn")) {
      const row = target.closest("tr");
      if (!row) return;

      const adminId = row.dataset.adminId;
      editAdminId.value = adminId;
      editAdminName.value = row.dataset.fullName || row.children[0].innerText;
      editAdminEmail.value = row.dataset.email || row.children[1].innerText;
      editAdminRole.value = row.dataset.role || "Admin";
      editAdminStatus.value = row.dataset.status || "Active";
      
      // Clear password fields when opening edit modal
      if (editAdminPassword) editAdminPassword.value = "";
      if (editAdminConfirmPassword) editAdminConfirmPassword.value = "";

      toggleSectionField(editAdminRole.value, editAdminSectionGroup, editAdminSectionSelect);
      if (editAdminRole.value === "Admin") {
        editAdminSectionSelect.value = row.dataset.sectionId || "";
      }

      openModal(editAdminModal);
    } else if (target.classList.contains("delete-btn")) {
      const row = target.closest("tr");
      if (!row) return;

      pendingDeleteId = row.dataset.adminId;
      pendingDeleteRow = row;
      deleteAdminName.textContent = target.dataset.name || row.dataset.fullName || "this admin";
      openModal(deleteAdminModal);
    }
  });

  // ===== Update Admin =====
  editAdminForm.addEventListener("submit", async e => {
    e.preventDefault();

    const adminId = editAdminId.value;
    const fullName = editAdminName.value.trim();
    const email = editAdminEmail.value.trim();
    const password = editAdminPassword ? editAdminPassword.value.trim() : "";
    const confirmPassword = editAdminConfirmPassword ? editAdminConfirmPassword.value.trim() : "";
    const role = editAdminRole.value;
    const section = editAdminSectionSelect.value;
    const status = editAdminStatus.value;

    if (!fullName || !email || !role) {
      showToast("Please fill in all required fields.", "error");
      return;
    }

    if (role === "Admin" && !section) {
      showToast("Please select a section for Admin role.", "error");
      return;
    }

    // Validate password if provided
    if (password) {
      if (password.length < 6) {
        showToast("Password must be at least 6 characters.", "error");
        return;
      }
      if (password !== confirmPassword) {
        showToast("Password and confirmation do not match.", "error");
        return;
      }
    }

    try {
      const formData = new FormData();
      formData.append("admin_id", adminId);
      formData.append("full_name", fullName);
      formData.append("email", email);
      if (password) {
        formData.append("password", password);
        formData.append("confirm_password", confirmPassword);
      }
      formData.append("role", role);
      formData.append("section_id", role === "Admin" ? section : "");
      formData.append("status", status);

      const response = await fetch(`${BASE_URL}/superadmin/updateAdminAjax/${adminId}`, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (!result.success) {
        showToast(result.message || "Failed to update admin.", "error");
        return;
      }

      const row = adminTableBody.querySelector(`tr[data-admin-id="${adminId}"]`);
      if (row) {
        const sectionName = role === "Admin"
          ? getSectionName(editAdminSectionSelect, section)
          : "-";

        row.children[1].innerText = fullName;
        row.children[2].innerText = email;
        row.children[3].innerText = role;
        row.children[4].innerText = sectionName;

        const statusCell = row.querySelector(".status-cell");
        if (statusCell) {
          statusCell.innerHTML = createStatusHTML(status, adminId, isSelfAdmin(adminId));
          attachStatusToggles(statusCell);
        }

        updateRowDataset(row, {
          full_name: fullName,
          email,
          role,
          section_id: role === "Admin" ? section : "",
          status,
        });

        row.querySelector(".delete-btn")?.setAttribute("data-name", fullName);
      }

      // Update row numbers
      updateRowNumbers();

      // Re-sort table if sorting is active (in case name changed)
      if (currentSort.column && currentSort.direction) {
        sortTableRows();
      }

      // Re-run search if there's an active search term
      if (adminSearch && adminSearch.value.trim()) {
        performSearch();
      }
      
      // Clear password fields after successful update
      if (editAdminPassword) editAdminPassword.value = "";
      if (editAdminConfirmPassword) editAdminConfirmPassword.value = "";
      
      closeModal(editAdminModal);
      showToast("Admin updated successfully.", "success");
    } catch (err) {
      console.error("Error updating admin:", err);
      showToast("Server error while updating admin.", "error");
    }
  });

  // ===== Delete Admin =====
  confirmDeleteBtn?.addEventListener("click", async () => {
    if (!pendingDeleteId || !pendingDeleteRow) return;

    confirmDeleteBtn.disabled = true;

    try {
      const response = await fetch(`${BASE_URL}/superadmin/deleteAdminAjax/${pendingDeleteId}`, {
        method: "POST",
      });
      const result = await response.json();

      if (!result.success) {
        showToast(result.message || "Failed to delete admin.", "error");
        confirmDeleteBtn.disabled = false;
        return;
      }

      // Remove from original rows array
      originalAdminRows = originalAdminRows.filter(row => row !== pendingDeleteRow);
      pendingDeleteRow.remove();
      
      // Update row numbers
      updateRowNumbers();
      
      // Show "No admin data available" message if table is empty
      if (adminTableBody.children.length === 0) {
        const noDataRow = document.createElement("tr");
        noDataRow.innerHTML = '<td colspan="8" style="text-align:center; color:#888;">No admin data available</td>';
        adminTableBody.appendChild(noDataRow);
      }
      
      showToast("Admin deleted successfully.", "success");
      closeModal(deleteAdminModal);
      pendingDeleteId = null;
      pendingDeleteRow = null;
    } catch (err) {
      console.error("Error deleting admin:", err);
      showToast("Server error while deleting admin.", "error");
    } finally {
      confirmDeleteBtn.disabled = false;
    }
  });
  attachStatusToggles();
   
  // ===== Sorting Functions =====
  function initSorting() {
    const sortableHeaders = document.querySelectorAll('th.sortable');
    sortableHeaders.forEach(header => {
      header.addEventListener('click', () => {
        const sortType = header.dataset.sort;
        handleSort(sortType);
      });
    });
  }

  function handleSort(sortType) {
    // Update sort state
    if (currentSort.column === sortType) {
      // Toggle direction
      currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
      // New column, default to ascending
      currentSort.column = sortType;
      currentSort.direction = 'asc';
    }
    
    // Update UI indicators
    updateSortIndicators();
    
    // Apply sorting
    sortTableRows();
  }

  function updateSortIndicators() {
    // Clear all indicators
    document.querySelectorAll('th.sortable').forEach(th => {
      th.classList.remove('asc', 'desc');
    });
    
    // Set indicator for current sort
    if (currentSort.column) {
      const currentHeader = document.querySelector(`th.sortable[data-sort="${currentSort.column}"]`);
      if (currentHeader) {
        currentHeader.classList.add(currentSort.direction);
      }
    }
  }

  function sortTableRows() {
    if (!currentSort.column || !currentSort.direction) {
      return;
    }

    // Get all rows that are currently visible
    const rows = Array.from(adminTableBody.querySelectorAll('tr')).filter(row => {
      const firstCell = row.querySelector('td');
      return firstCell && !firstCell.hasAttribute('colspan') && row.style.display !== 'none';
    });

    if (rows.length === 0) {
      return;
    }

    // Sort rows
    rows.sort((a, b) => {
      let aValue, bValue;
      
      switch (currentSort.column) {
        case 'name':
          // Sort by full name (first column)
          aValue = (a.dataset.fullName || a.children[0]?.innerText || '').trim().toLowerCase();
          bValue = (b.dataset.fullName || b.children[0]?.innerText || '').trim().toLowerCase();
          break;
        default:
          return 0;
      }
      
      // Compare values
      if (currentSort.direction === 'asc') {
        if (aValue < bValue) return -1;
        if (aValue > bValue) return 1;
        return 0;
      } else {
        if (aValue > bValue) return -1;
        if (aValue < bValue) return 1;
        return 0;
      }
    });

    // Remove all visible rows from table
    rows.forEach(row => row.remove());

    // Re-append sorted rows
    rows.forEach(row => adminTableBody.appendChild(row));

    // Reattach status toggles after reordering
    attachStatusToggles();
    
    // Update row numbers
    updateRowNumbers();
  }

  // Numbering functionality
  const updateRowNumbers = () => {
    const rows = adminTableBody.querySelectorAll('tr');
    let number = 1;
    
    rows.forEach(row => {
      const firstCell = row.querySelector('td');
      if (firstCell && !firstCell.hasAttribute('colspan')) {
        row.children[0].textContent = number;
        number++;
      }
    });
  };

  // Search functionality
  const performSearch = () => {
    if (!adminSearch || !adminTableBody) return;
    
    const searchTerm = adminSearch.value.trim().toLowerCase();
    
    if (!searchTerm) {
      // If search is empty, show all rows
      originalAdminRows.forEach(row => {
        row.style.display = '';
        adminTableBody.appendChild(row);
      });
      
      // Update row numbers
      updateRowNumbers();
      return;
    }
    
    // Hide all rows first
    originalAdminRows.forEach(row => {
      row.style.display = 'none';
    });
    
    // Show rows that match the search term
    originalAdminRows.forEach(row => {
      const cells = row.querySelectorAll('td');
      let match = false;
      
      for (let cell of cells) {
        if (cell.textContent.toLowerCase().includes(searchTerm)) {
          match = true;
          break;
        }
      }
      
      if (match) {
        row.style.display = '';
        adminTableBody.appendChild(row);
      }
      
      // Update row numbers after search
      updateRowNumbers();
    });
    
    // Show "No results" message if no rows match
    const visibleRows = Array.from(adminTableBody.querySelectorAll('tr')).filter(row => {
      const firstCell = row.querySelector('td');
      return row.style.display !== 'none' && firstCell && !firstCell.hasAttribute('colspan');
    });
    
    const noDataRow = adminTableBody.querySelector('tr td[colspan]');
    if (noDataRow) {
      noDataRow.closest('tr').remove();
    }
    
    if (visibleRows.length === 0 && originalAdminRows.length > 0) {
      const noDataRow = document.createElement('tr');
      noDataRow.innerHTML = '<td colspan="8" style="text-align:center; color:#888;">No matching admin data found</td>';
      adminTableBody.appendChild(noDataRow);
    }
  };
  
  // Add event listeners for search
  if (adminSearch) {
    adminSearch.addEventListener('input', performSearch);
  }
  
  if (searchAdminBtn) {
    searchAdminBtn.addEventListener('click', performSearch);
  }
  
  // Initialize sorting
  initSorting();
  
  // Initialize row numbering
  updateRowNumbers();
});
