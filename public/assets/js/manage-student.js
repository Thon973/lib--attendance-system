document.addEventListener("DOMContentLoaded", () => {
  const studentTable = document.getElementById("studentData");
  const studentPagination = document.getElementById("studentPagination");
  const prevPageBtn = document.getElementById("prevPage");
  const nextPageBtn = document.getElementById("nextPage");
  const pageInfo = document.getElementById("pageInfo");

  // Pagination variables
  let currentPage = 1;
  const itemsPerPage = 50;
  let filteredStudents = [];

  // Selected students (persist across search/filter)
  let selectedStudentIds = new Set();

  // Sorting functionality
  let currentSort = { column: null, direction: null };

  // Filters
  const searchInput = document.getElementById("studentSearch");
  const collegeFilter = document.getElementById("collegeFilter");
  const courseFilter = document.getElementById("courseFilter");
  const yearFilter = document.getElementById("yearFilter");
  const statusFilter = document.getElementById("statusFilter");
  const sexFilter = document.getElementById("sexFilter");

  const addBtn = document.getElementById("openAddStudent");
  const modal = document.getElementById("addStudentModal");
  const closeModalBtn = document.getElementById("closeModal");
  const addStudentForm = document.getElementById("addStudentForm");
  const modalCollege = document.getElementById("modalCollege");
  const modalCourse = document.getElementById("modalCourse");
  const importLabel = document.querySelector(".import-btn");
  const importInput = document.getElementById("importFile");

  const qrModal = document.getElementById("qrModal");
  const qrImage = document.getElementById("qrImage");
  const closeQrModal = document.getElementById("closeQrModal");

  const deleteModal = document.getElementById("deleteStudentModal");
  const confirmDeleteBtn = document.getElementById("confirmDeleteStudent");
  const cancelDeleteBtn = document.getElementById("cancelDeleteStudent");
  const deleteStudentName = document.getElementById("deleteStudentName");

  const historyModal = document.getElementById("attendanceHistoryModal");
  const closeHistoryModal = document.getElementById("closeHistoryModal");
  const historyStudentName = document.getElementById("historyStudentName");
  const historyStudentInfo = document.getElementById("historyStudentInfo");
  const attendanceHistoryBody = document.getElementById("attendanceHistoryBody");
  const historyNoData = document.getElementById("historyNoData");

  let pendingDeleteId = null;

  document.body.appendChild(qrModal);

  closeQrModal.addEventListener("click", () => (qrModal.style.display = "none"));
  window.addEventListener("click", e => {
    // Removed the code that closes modal when clicking outside
    // Modal will only close when clicking the close button
  });

  if (typeof BASE_URL === "undefined") {
    console.error("BASE_URL not defined.");
    return;
  }

  // ===== Load Students =====
  async function loadStudents() {
    try {
      const res = await fetch(`${BASE_URL}/superadmin/getStudents`);
      const data = await res.json();
      window.allStudents = data.map(s => ({
        ...s,
        college_id: Number(s.college_id),
        course_id: Number(s.course_id),
        address: s.address || ""
      }));
      applyFilters();
    } catch (err) {
      console.error("Error loading students:", err);
      studentTable.innerHTML = `<tr><td colspan="9" style="text-align:center;color:#888;">Failed to load students</td></tr>`;
    }
  }

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
    
    // Apply sorting and re-render table
    applyFilters();
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

  function sortStudentsData() {
    if (!currentSort.column || !currentSort.direction || !filteredStudents || filteredStudents.length === 0) {
      return;
    }
    
    filteredStudents.sort((a, b) => {
      let aValue, bValue;
      
      switch (currentSort.column) {
        case 'student_id':
          // Sort by student number (alphanumeric)
          aValue = (a.student_number || '').toLowerCase();
          bValue = (b.student_number || '').toLowerCase();
          break;
        case 'name':
          // Sort by full name (last name, first name + middle initial)
          const aMiddleInitial = a.middle_initial ? ' ' + a.middle_initial + '.' : '';
          const bMiddleInitial = b.middle_initial ? ' ' + b.middle_initial + '.' : '';
          const aName = `${a.last_name || ''}, ${a.first_name || ''}${aMiddleInitial}`.trim().toLowerCase();
          const bName = `${b.last_name || ''}, ${b.first_name || ''}${bMiddleInitial}`.trim().toLowerCase();
          aValue = aName;
          bValue = bName;
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
  }

  // ===== Render Table with Pagination =====
  function renderTable(students) {
    // Store filtered students for pagination
    filteredStudents = students;
    
    // Apply sorting if any
    if (currentSort.column && currentSort.direction) {
      sortStudentsData();
    }
    
    // Calculate pagination
    const totalItems = students.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    
    // Show pagination controls if there are multiple pages
    if (totalPages > 1) {
      studentPagination.style.display = "flex";
      
      // Disable/enable buttons
      prevPageBtn.disabled = currentPage === 1;
      nextPageBtn.disabled = currentPage === totalPages;
      
      // Update page info
      pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    } else {
      studentPagination.style.display = "none";
    }
    
    // Get current page items
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentStudents = students.slice(startIndex, endIndex);
    
    studentTable.innerHTML = "";
    if (!currentStudents || currentStudents.length === 0) {
      studentTable.innerHTML = `<tr><td colspan="10" style="text-align:center;color:#888;">No student data found</td></tr>`;
      return;
    }

    currentStudents.forEach((st, index) => {
      const displayNumber = startIndex + index + 1;
      const isActive = st.status === "Active";
      const isSelected = selectedStudentIds.has(String(st.student_id));
      const row = document.createElement("tr");
      row.classList.add("clickable-row");
      row.setAttribute("data-student-id", st.student_id);
      row.innerHTML = `
        <td class="checkbox-cell">
          <input type="checkbox" class="student-checkbox" data-student-id="${st.student_id}" data-student-name="${st.last_name}, ${st.first_name}${st.middle_initial ? ' ' + st.middle_initial + '.' : ''}" ${isSelected ? 'checked' : ''}>
        </td>
        <td class="number-cell">${displayNumber}</td>
        <td>${st.student_number}</td>
        <td class="name-cell">${st.last_name}, ${st.first_name}${st.middle_initial ? ' ' + st.middle_initial + '.' : ''}</td>
        <td>${st.sex || "N/A"}</td>
        <td>${st.course_code || "N/A"}</td>
        <td>${st.year_level || "N/A"}</td>
        <td class="address-cell">${st.address ? st.address : "N/A"}</td>
        <td>${st.qr_code ? `<a href="#" class="view-qr" data-student-id="${st.student_id}">View QR Code</a>` : "N/A"}</td>
        <td>
          <label class="switch">
            <input type="checkbox" class="status-toggle" data-id="${st.student_id}" ${isActive ? "checked" : ""}>
            <span class="slider"></span>
          </label>
          <span class="status-label">${isActive ? "Active" : "Inactive"}</span>
        </td>
        <td class="actions">
          <button class="icon-btn edit" data-id="${st.student_id}" title="Edit Student">
            <img src="${BASE_URL}/assets/icons/edit.png" alt="Edit">
          </button>
          <button class="icon-btn delete" data-id="${st.student_id}" data-name="${st.last_name}, ${st.first_name}${st.middle_initial ? ' ' + st.middle_initial + '.' : ''}" title="Delete Student">
            <img src="${BASE_URL}/assets/icons/trash-bin.png" alt="Delete">
          </button>
        </td>
      `;
      studentTable.appendChild(row);
    });

    attachStatusToggles();
    attachQRClicks();
    attachEditDelete();
    attachRowClicks();
    attachSelectAll();
    updateBulkActions();
  }

  // ===== Pagination Event Listeners =====
  prevPageBtn.addEventListener("click", () => {
    if (currentPage > 1) {
      currentPage--;
      renderTable(filteredStudents);
    }
  });

  nextPageBtn.addEventListener("click", () => {
    const totalPages = Math.ceil(filteredStudents.length / itemsPerPage);
    if (currentPage < totalPages) {
      currentPage++;
      renderTable(filteredStudents);
    }
  });

  // ===== Status Toggle =====
  function attachStatusToggles() {
    document.querySelectorAll(".status-toggle").forEach(toggle => {
      toggle.addEventListener("change", function () {
        // Prevent multiple clicks
        if (this.disabled) return;
        
        const studentId = this.dataset.id;
        const newStatus = this.checked ? "Active" : "Inactive";
        const label = this.closest("td").querySelector(".status-label");
        const switchContainer = this.closest("td");
        const originalStatus = label.textContent;
        
        // Disable toggle and show loading
        this.disabled = true;
        switchContainer.classList.add("status-updating");
        label.textContent = "Updating...";

        fetch(`${BASE_URL}/superadmin/updateStudentStatus/${studentId}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ status: newStatus }),
        })
        .then(res => res.json())
        .then(res => {
          // Remove loading state
          this.disabled = false;
          switchContainer.classList.remove("status-updating");
          
          if (!res.success) {
            showToast("Failed to update status.", "error");
            this.checked = !this.checked;
            label.textContent = originalStatus;
          } else {
            label.textContent = newStatus;
            showToast(`Status updated to ${newStatus}`, "success");
            // Update the student data in allStudents array
            const student = window.allStudents?.find(s => s.student_id == studentId);
            if (student) {
              student.status = newStatus;
            }
          }
        })
        .catch(() => {
          // Remove loading state
          this.disabled = false;
          switchContainer.classList.remove("status-updating");
          showToast("Server error occurred.", "error");
          this.checked = !this.checked;
          label.textContent = originalStatus;
        });
      });
    });
  }

  // ===== QR Modal =====
  function attachQRClicks() {
    document.querySelectorAll(".view-qr").forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        const studentId = link.dataset.studentId;
        if (!studentId) {
          console.error("Student ID not found");
          alert("Error: Student ID not found");
          return;
        }
        
        const qrUrl = `${BASE_URL}/student/qrcode/${studentId}`;
        console.log("Loading QR code from:", qrUrl);
        
        // Handle image load error
        qrImage.onerror = function() {
          console.error("Failed to load QR code image");
          this.alt = "QR Code not available";
          alert("Failed to load QR code. Please ensure the student has a QR code generated.");
        };
        
        qrImage.onload = function() {
          console.log("QR code loaded successfully");
        };
        
        qrImage.src = qrUrl;
        qrModal.style.display = "block";
      });
    });
  }

  // ===== Edit/Delete Buttons =====
  function attachEditDelete() {
    document.querySelectorAll(".edit").forEach(btn => {
      btn.addEventListener("click", async (e) => {
        e.stopPropagation(); // Prevent row click
        const studentId = btn.dataset.id;
        try {
          const res = await fetch(`${BASE_URL}/superadmin/get_student/${studentId}`);
          const data = await res.json();
          if (data.status === "success") {
            const s = data.student;
            modal.style.display = "block";
            addStudentForm.student_number.value = s.student_number;
            addStudentForm.first_name.value = s.first_name;
            addStudentForm.last_name.value = s.last_name;
            document.getElementById('modalSex').value = s.sex || '';
            addStudentForm.middle_initial.value = s.middle_initial || '';
            modalCollege.value = s.college_id;
            await loadCourses(s.college_id);
            modalCourse.value = s.course_id;
            addStudentForm.year_level.value = s.year_level;
            addStudentForm.address.value = s.address || "";
            addStudentForm.dataset.editId = studentId;
            
            // Show password fields when editing
            const passwordSection = document.getElementById('passwordSection');
            if (passwordSection) {
              passwordSection.style.display = 'block';
            }
            
            // Clear password fields
            const passwordInput = addStudentForm.querySelector('input[name="password"]');
            const confirmPasswordInput = addStudentForm.querySelector('input[name="confirm_password"]');
            if (passwordInput) passwordInput.value = '';
            if (confirmPasswordInput) confirmPasswordInput.value = '';
          } else {
            showToast(data.message, "error");
          }
        } catch {
          showToast("Failed to fetch student data.", "error");
        }
      });
    });

    document.querySelectorAll(".delete").forEach(btn => {
      btn.addEventListener("click", async (e) => {
        e.stopPropagation(); // Prevent row click
        pendingDeleteId = btn.dataset.id;
        // Format the name as Last Name, First Name Middle Initial.
        const studentName = btn.dataset.name || "this student";
        deleteStudentName.textContent = studentName;
        deleteModal.style.display = "block";
      });
    });
  }

  // ===== Row Click Handler =====
  function attachRowClicks() {
    document.querySelectorAll(".clickable-row").forEach(row => {
      row.addEventListener("click", async (e) => {
        // Don't trigger if clicking on action buttons, status toggle, QR link, or checkboxes
        if (e.target.closest(".actions") || 
            e.target.closest(".switch") || 
            e.target.closest(".view-qr") ||
            e.target.closest(".checkbox-cell") ||
            e.target.tagName === "BUTTON" ||
            (e.target.tagName === "INPUT" && e.target.type === "checkbox")) {
          return;
        }
        
        const studentId = row.getAttribute("data-student-id");
        if (studentId) {
          await loadAttendanceHistory(studentId);
        }
      });
    });
  }

  // ===== Select All Checkbox =====
  function attachSelectAll() {
    const selectAllCheckbox = document.getElementById("selectAllCheckbox");
    if (selectAllCheckbox) {
      // Remove existing listeners
      const newSelectAll = selectAllCheckbox.cloneNode(true);
      selectAllCheckbox.parentNode.replaceChild(newSelectAll, selectAllCheckbox);
      
      newSelectAll.addEventListener("change", function() {
        const checkboxes = document.querySelectorAll(".student-checkbox");
        checkboxes.forEach(checkbox => {
          const studentId = checkbox.dataset.studentId;
          checkbox.checked = this.checked;
          if (this.checked) {
            selectedStudentIds.add(studentId);
          } else {
            selectedStudentIds.delete(studentId);
          }
        });
        updateBulkActions();
      });
    }

    // Update select all when individual checkboxes change
    document.querySelectorAll(".student-checkbox").forEach(checkbox => {
      checkbox.addEventListener("change", function() {
        const studentId = this.dataset.studentId;
        if (this.checked) {
          selectedStudentIds.add(studentId);
        } else {
          selectedStudentIds.delete(studentId);
        }
        updateSelectAllState();
        updateBulkActions();
      });
    });
  }

  function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById("selectAllCheckbox");
    if (selectAllCheckbox) {
      const checkboxes = document.querySelectorAll(".student-checkbox");
      const checkedCount = document.querySelectorAll(".student-checkbox:checked").length;
      selectAllCheckbox.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
      selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }
  }

  function updateBulkActions() {
    const selectedCount = selectedStudentIds.size;
    const bulkActions = document.getElementById("bulkActions");
    const selectedCountIndicator = document.getElementById("selectedCountIndicator");
    const selectedCountText = document.getElementById("selectedCount");
    
    if (selectedCount > 0) {
      bulkActions.style.display = "flex";
      selectedCountIndicator.style.display = "flex";
      selectedCountText.textContent = selectedCount;
    } else {
      bulkActions.style.display = "none";
      selectedCountIndicator.style.display = "none";
    }
  }

  function getSelectedStudentIds() {
    // Return IDs from the Set (persists across search/filter)
    return Array.from(selectedStudentIds);
  }
  
  function clearSelectedStudents() {
    selectedStudentIds.clear();
    // Uncheck all checkboxes
    document.querySelectorAll(".student-checkbox").forEach(checkbox => {
      checkbox.checked = false;
    });
    // Uncheck select all checkbox
    const selectAllCheckbox = document.getElementById("selectAllCheckbox");
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = false;
      selectAllCheckbox.indeterminate = false;
    }
    updateBulkActions();
  }

  // ===== Load Attendance History =====
  async function loadAttendanceHistory(studentId) {
    historyModal.style.display = "block";
    attendanceHistoryBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #888;">Loading...</td></tr>';
    historyNoData.style.display = "none";

    try {
      const res = await fetch(`${BASE_URL}/superadmin/student-attendance-history/${studentId}`);
      const data = await res.json();
      
      if (data.success && data.student) {
        const student = data.student;
        historyStudentName.textContent = `${student.first_name} ${student.last_name} - Attendance History`;
        historyStudentInfo.innerHTML = `
          <div class="info-item">
            <strong>Student Number:</strong> ${student.student_number || 'N/A'}
          </div>
          <div class="info-item">
            <strong>Name:</strong> ${student.last_name || 'N/A'}, ${student.first_name || 'N/A'}${(student.middle_initial ? ' ' + student.middle_initial + '.' : '')}
          </div>
          <div class="info-item">
            <strong>Sex:</strong> ${student.sex || 'N/A'}
          </div>
          <div class="info-item">
            <strong>Course:</strong> ${student.course_code || 'N/A'}
          </div>
          <div class="info-item">
            <strong>Year Level:</strong> ${student.year_level || 'N/A'}
          </div>
        `;

        if (data.attendance && data.attendance.length > 0) {
          historyNoData.style.display = "none";
          attendanceHistoryBody.innerHTML = data.attendance.map(entry => {
            const scanDate = entry.scan_datetime || entry.created_at || '';
            const dateObj = new Date(scanDate);
            const formattedDate = !isNaN(dateObj.getTime()) 
              ? dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
              : 'N/A';
            const formattedTime = !isNaN(dateObj.getTime())
              ? dateObj.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
              : 'N/A';

            return `
              <tr>
                <td>${formattedDate}</td>
                <td>${formattedTime}</td>
                <td>${entry.section_name || 'N/A'}</td>
              </tr>
            `;
          }).join('');
        } else {
          attendanceHistoryBody.innerHTML = '';
          historyNoData.style.display = "block";
        }
      } else {
        attendanceHistoryBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #e74c3c;">Failed to load attendance history</td></tr>';
      }
    } catch (err) {
      console.error("Error loading attendance history:", err);
      attendanceHistoryBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #e74c3c;">Error loading attendance history</td></tr>';
    }
  }

  // ===== Close History Modal =====
  closeHistoryModal.addEventListener("click", () => {
    historyModal.style.display = "none";
  });

  window.addEventListener("click", (e) => {
    // Removed the code that closes modal when clicking outside
    // Modal will only close when clicking the close button
  });

  // ===== Toast =====
  function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  }

  // ===== Load Colleges =====
  async function loadColleges() {
    try {
      const res = await fetch(`${BASE_URL}/superadmin/stdColleges`);
      const data = await res.json();
      modalCollege.innerHTML = `<option value="">Select College</option>`;
      collegeFilter.innerHTML = `<option value="">All Colleges</option>`;
      data.forEach(c => {
        modalCollege.innerHTML += `<option value="${c.college_id}">${c.college_code}</option>`;
        collegeFilter.innerHTML += `<option value="${c.college_id}">${c.college_code}</option>`;
      });
      courseFilter.innerHTML = `<option value="">All Courses</option>`;
    } catch (err) {
      console.error("Error loading colleges:", err);
    }
  }

  // ===== Load Courses =====
  async function loadCourses(collegeId = "", targetSelect = modalCourse, { includeAllOption = false } = {}) {
    if (!collegeId) {
      targetSelect.innerHTML = includeAllOption
        ? `<option value="">All Courses</option>`
        : `<option value="">Select College first</option>`;
      return;
    }
    try {
      const res = await fetch(`${BASE_URL}/superadmin/stdCourses/${collegeId}`);
      const data = await res.json();
      targetSelect.innerHTML = includeAllOption
        ? `<option value="">All Courses</option>`
        : `<option value="">Select Course</option>`;
      data.forEach(c => {
        targetSelect.innerHTML += `<option value="${c.course_id}">${c.course_code}</option>`;
      });
    } catch (err) {
      console.error("Error loading courses:", err);
    }
  }

  // ===== Modal Logic =====
  addBtn.addEventListener("click", () => {
    modal.style.display = "block";
    modalCourse.innerHTML = `<option value="">Select College first</option>`;
    delete addStudentForm.dataset.editId; // clear edit
    addStudentForm.reset();
    // Hide password fields when adding
    const passwordSection = document.getElementById('passwordSection');
    if (passwordSection) {
      passwordSection.style.display = 'none';
    }
  });

  closeModalBtn.addEventListener("click", () => (modal.style.display = "none"));
  window.addEventListener("click", e => {
    // Removed the code that closes modal when clicking outside
    // Modal will only close when clicking the close button
  });

  modalCollege.addEventListener("change", () => {
    if (modalCollege.value) loadCourses(modalCollege.value, modalCourse);
  });

  cancelDeleteBtn.addEventListener("click", () => {
    deleteModal.style.display = "none";
    pendingDeleteId = null;
  });

  // Close button for individual delete modal
  document.getElementById("closeDeleteModal")?.addEventListener("click", () => {
    deleteModal.style.display = "none";
    pendingDeleteId = null;
  });

  window.addEventListener("click", e => {
    // Removed the code that closes modal when clicking outside
    // Modal will only close when clicking the close button or cancel button
  });

  confirmDeleteBtn.addEventListener("click", async () => {
    if (!pendingDeleteId) return;
    
    // Prevent multiple clicks
    if (confirmDeleteBtn.disabled) return;
    
    const studentId = pendingDeleteId;
    const originalText = confirmDeleteBtn.innerHTML;
    
    confirmDeleteBtn.classList.add("loading");
    confirmDeleteBtn.disabled = true;
    confirmDeleteBtn.innerHTML = "Deleting...";
    
    try {
      const res = await fetch(`${BASE_URL}/superadmin/delete_student/${studentId}`, {
        method: "DELETE"
      });
      const data = await res.json();
      if (data.status === "success") {
        showToast("Student deleted successfully", "success");
        deleteModal.style.display = "none";
        pendingDeleteId = null;
        loadStudents();
      } else {
        showToast(data.message || "Failed to delete student.", "error");
      }
    } catch {
      showToast("Failed to delete student.", "error");
    } finally {
      confirmDeleteBtn.classList.remove("loading");
      confirmDeleteBtn.disabled = false;
      confirmDeleteBtn.innerHTML = originalText;
    }
  });

  // ===== Import Students =====
  if (importLabel && importInput) {
    // Note: The label's 'for' attribute already triggers the input click,
    // so we don't need to add a click listener on the label
    
    importInput.addEventListener("change", async () => {
      const file = importInput.files?.[0];
      if (!file) return;

      const validTypes = [
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/vnd.ms-excel",
        "text/csv",
        "application/csv",
        "application/pdf",
      ];

      if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls|csv|pdf)$/i)) {
        showToast("Please select a valid Excel (.xlsx, .xls), CSV, or PDF file.", "error");
        importInput.value = "";
        return;
      }

      // Validate file size (max 30MB)
      const maxSize = 30 * 1024 * 1024; // 30MB in bytes
      if (file.size > maxSize) {
        showToast("File size exceeds the maximum limit of 30MB.", "error");
        importInput.value = "";
        return;
      }

      const formData = new FormData();
      formData.append("import_file", file);

      importLabel.classList.add("loading");
      importLabel.textContent = "Importing...";

      try {
        const res = await fetch(`${BASE_URL}/superadmin/importStudents`, {
          method: "POST",
          body: formData,
        });
        const data = await res.json();

        if (res.ok && data.success) {
          const summary = [
            data.created ? `${data.created} added` : null,
            data.updated ? `${data.updated} updated` : null,
            data.skipped ? `${data.skipped} skipped` : null,
          ]
            .filter(Boolean)
            .join(", ");
          
          // Show summary
          showToast(`Import complete (${summary || "no changes"})`, "success");
          
          // Show errors/warnings if any
          if (Array.isArray(data.errors) && data.errors.length > 0) {
            const errorCount = data.errors.length;
            const errorPreview = data.errors.slice(0, 5).join("; ");
            const errorMsg = errorCount <= 5 
              ? `Errors: ${errorPreview}`
              : `Errors (showing first 5 of ${errorCount}): ${errorPreview}`;
            
            setTimeout(() => {
              showToast(errorMsg, "error");
              console.warn("Import errors:", data.errors);
            }, 2000);
          }
          
          loadStudents();
        } else {
          const errorMsg = data.message || "Failed to import students.";
          showToast(errorMsg, "error");
          
          if (Array.isArray(data.errors) && data.errors.length > 0) {
            console.warn("Import errors:", data.errors);
            // Show first few errors in a second toast
            setTimeout(() => {
              const errorPreview = data.errors.slice(0, 3).join("; ");
              showToast(`Details: ${errorPreview}`, "error");
            }, 2000);
          }
        }
      } catch (err) {
        console.error("Import error:", err);
        showToast("Server error during import.", "error");
      } finally {
        importLabel.classList.remove("loading");
        // Restore button content
        importLabel.innerHTML = `<img src="${BASE_URL}/assets/icons/import.png" alt="Import" class="btn-icon"> Import File`;
        importInput.value = "";
      }
    });
  }

  // ===== Filters =====
  function applyFilters() {
    // Reset to first page when applying filters
    currentPage = 1;
    
    if (!Array.isArray(window.allStudents)) {
      return;
    }

    const searchTerm = searchInput.value.trim().toLowerCase();
    const selectedCollege = collegeFilter.value;
    const selectedCourse = courseFilter.value;
    const selectedYear = yearFilter.value;
    const selectedStatus = statusFilter.value;
    const selectedSex = sexFilter.value;

    const filtered = window.allStudents.filter(st => {
      const fullName = `${st.first_name || ""} ${st.last_name || ""}`.trim().toLowerCase();
      const reversedName = `${st.last_name || ""}, ${st.first_name || ""}`.trim().toLowerCase();
      const matchesSearch =
        !searchTerm ||
        (st.student_number && st.student_number.toLowerCase().includes(searchTerm)) ||
        fullName.includes(searchTerm) ||
        reversedName.includes(searchTerm);

      const matchesCollege =
        !selectedCollege || String(st.college_id ?? "") === selectedCollege;

      const matchesCourse =
        !selectedCourse || String(st.course_id ?? "") === selectedCourse;

      const matchesYear =
        !selectedYear || (st.year_level || "") === selectedYear;

      const matchesStatus =
        !selectedStatus || (st.status || "") === selectedStatus;
        
      const matchesSex =
        !selectedSex || (st.sex || "") === selectedSex;

      return matchesSearch && matchesCollege && matchesCourse && matchesYear && matchesStatus && matchesSex;
    });

    renderTable(filtered);
  }

  searchInput.addEventListener("input", applyFilters);

  collegeFilter.addEventListener("change", async () => {
    const collegeId = collegeFilter.value;
    if (collegeId) {
      await loadCourses(collegeId, courseFilter, { includeAllOption: true });
    } else {
      courseFilter.innerHTML = `<option value="">All Courses</option>`;
    }
    applyFilters();
  });

  courseFilter.addEventListener("change", applyFilters);
  yearFilter.addEventListener("change", applyFilters);
  statusFilter.addEventListener("change", applyFilters);
  sexFilter.addEventListener("change", applyFilters);

  // ===== Reset Filters =====
  const resetFiltersBtn = document.getElementById("resetFiltersBtn");
  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener("click", () => {
      // Reset all filter inputs
      searchInput.value = "";
      collegeFilter.value = "";
      courseFilter.innerHTML = `<option value="">All Courses</option>`;
      yearFilter.value = "";
      statusFilter.value = "";
      sexFilter.value = "";
      
      // Reset to first page
      currentPage = 1;
      
      // Reset sorting
      currentSort = { column: null, direction: null };
      updateSortIndicators();
      
      // Apply filters (which will show all students)
      applyFilters();
    });
  }

  // ===== Add / Edit Student Submit =====
  addStudentForm.addEventListener("submit", async e => {
    e.preventDefault();
    const formData = new FormData(addStudentForm);
    const editId = addStudentForm.dataset.editId;

    const url = editId ? `${BASE_URL}/superadmin/update_student/${editId}` : `${BASE_URL}/superadmin/addStudent`;
    const method = editId ? "POST" : "POST";

    try {
      const res = await fetch(url, { method, body: formData });
      const data = await res.json();
      if (data.status === "success") {
        showToast(editId ? "Student updated successfully" : "Student added successfully", "success");
        modal.style.display = "none";
        addStudentForm.reset();
        loadStudents();
      } else {
        showToast(data.message || "Operation failed", "error");
      }
    } catch {
      showToast("Server error occurred", "error");
    }
  });

  // ===== Bulk Actions =====
  document.getElementById("bulkActivate")?.addEventListener("click", async () => {
    const selectedIds = getSelectedStudentIds();
    if (selectedIds.length === 0) {
      showToast("Please select at least one student.", "error");
      return;
    }

    const btn = document.getElementById("bulkActivate");
    const originalText = btn.innerHTML;
    
    // Add loading state
    btn.classList.add("loading");
    btn.disabled = true;
    
    // Add visual feedback that processing has started
    setTimeout(() => {
      if (btn.classList.contains("loading")) {
        // Ensure the loading state is still active
        btn.classList.add("loading");
      }
    }, 50);

    try {
      // Small delay to ensure UI updates before API calls
      await new Promise(resolve => setTimeout(resolve, 100));
      
      const promises = selectedIds.map(id => 
        fetch(`${BASE_URL}/superadmin/updateStudentStatus/${id}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ status: "Active" }),
        })
      );

      await Promise.all(promises);
      showToast(`${selectedIds.length} student(s) activated successfully.`, "success");
      document.getElementById("selectAllCheckbox").checked = false;
      clearSelectedStudents();
      loadStudents();
    } catch (err) {
      showToast("Failed to activate students.", "error");
    } finally {
      // Ensure loading state is removed
      btn.classList.remove("loading");
      btn.disabled = false;
      btn.innerHTML = originalText;
      
      // Add a small delay to ensure UI updates properly
      setTimeout(() => {
        if (!btn.classList.contains("loading")) {
          btn.innerHTML = originalText;
        }
      }, 10);
    }
  });

  document.getElementById("bulkDeactivate")?.addEventListener("click", async () => {
    const selectedIds = getSelectedStudentIds();
    if (selectedIds.length === 0) {
      showToast("Please select at least one student.", "error");
      return;
    }

    const btn = document.getElementById("bulkDeactivate");
    const originalText = btn.innerHTML;
    
    // Add loading state
    btn.classList.add("loading");
    btn.disabled = true;
    
    // Add visual feedback that processing has started
    setTimeout(() => {
      if (btn.classList.contains("loading")) {
        // Ensure the loading state is still active
        btn.classList.add("loading");
      }
    }, 50);

    try {
      // Small delay to ensure UI updates before API calls
      await new Promise(resolve => setTimeout(resolve, 100));
      
      const promises = selectedIds.map(id => 
        fetch(`${BASE_URL}/superadmin/updateStudentStatus/${id}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ status: "Inactive" }),
        })
      );

      await Promise.all(promises);
      showToast(`${selectedIds.length} student(s) deactivated successfully.`, "success");
      document.getElementById("selectAllCheckbox").checked = false;
      clearSelectedStudents();
      loadStudents();
    } catch (err) {
      showToast("Failed to deactivate students.", "error");
    } finally {
      // Ensure loading state is removed
      btn.classList.remove("loading");
      btn.disabled = false;
      btn.innerHTML = originalText;
      
      // Add a small delay to ensure UI updates properly
      setTimeout(() => {
        if (!btn.classList.contains("loading")) {
          btn.innerHTML = originalText;
        }
      }, 10);
    }
  });

  document.getElementById("bulkDelete")?.addEventListener("click", () => {
    const selectedIds = getSelectedStudentIds();
    if (selectedIds.length === 0) {
      showToast("Please select at least one student.", "error");
      return;
    }

    const bulkDeleteModal = document.getElementById("bulkDeleteModal");
    const bulkDeleteCount = document.getElementById("bulkDeleteCount");
    bulkDeleteCount.textContent = selectedIds.length;
    bulkDeleteModal.style.display = "block";
  });

  document.getElementById("confirmBulkDelete")?.addEventListener("click", async () => {
    const selectedIds = getSelectedStudentIds();
    const bulkDeleteModal = document.getElementById("bulkDeleteModal");
    const confirmBtn = document.getElementById("confirmBulkDelete");
    const originalText = confirmBtn.innerHTML;
    
    // Prevent multiple clicks
    if (confirmBtn.disabled) return;
    
    confirmBtn.classList.add("loading");
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = "Deleting...";
    
    try {
      const promises = selectedIds.map(id => 
        fetch(`${BASE_URL}/superadmin/delete_student/${id}`, {
          method: "DELETE",
        })
      );

      await Promise.all(promises);
      showToast(`${selectedIds.length} student(s) deleted successfully.`, "success");
      bulkDeleteModal.style.display = "none";
      document.getElementById("selectAllCheckbox").checked = false;
      clearSelectedStudents();
      loadStudents();
    } catch (err) {
      showToast("Failed to delete students.", "error");
    } finally {
      confirmBtn.classList.remove("loading");
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
    }
  });

  document.getElementById("cancelBulkDelete")?.addEventListener("click", () => {
    document.getElementById("bulkDeleteModal").style.display = "none";
  });

  // Close button for bulk delete modal
  document.getElementById("closeBulkDeleteModal")?.addEventListener("click", () => {
    document.getElementById("bulkDeleteModal").style.display = "none";
  });

  // ===== Cancel Selected Button =====
  document.getElementById("cancelSelected")?.addEventListener("click", () => {
    clearSelectedStudents();
    showToast("Selection cleared", "success");
  });

  // ===== Initial Load =====
  // Initialize sorting functionality
  initSorting();
  
  loadStudents();
  loadColleges();
  
  // ===== Horizontal Scroll Functionality =====
  const tableContainer = document.querySelector('.table-container');
  if (tableContainer) {
    // Create scroll indicator element
    const scrollIndicator = document.createElement('div');
    scrollIndicator.className = 'scroll-indicator';
    scrollIndicator.innerHTML = '→';
    scrollIndicator.style.cssText = `
      position: absolute;
      right: 10px;
      top: 10px;
      background: rgba(26, 24, 81, 0.7);
      color: white;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      z-index: 10;
      pointer-events: none;
      opacity: 0.7;
      transition: opacity 0.3s;
    `;
    tableContainer.appendChild(scrollIndicator);
    
    // Update scroll indicator visibility
    function updateScrollIndicator() {
      const scrollLeft = tableContainer.scrollLeft;
      const scrollWidth = tableContainer.scrollWidth;
      const clientWidth = tableContainer.clientWidth;
      const scrollRight = scrollWidth - (scrollLeft + clientWidth);
      
      // Show scroll indicator when content is scrollable to the right
      if (scrollRight > 10) {
        scrollIndicator.style.display = 'flex';
      } else {
        scrollIndicator.style.display = 'none';
      }
    }
    
    // Add scroll event listener
    tableContainer.addEventListener('scroll', updateScrollIndicator);
    
    // Initialize on load
    setTimeout(updateScrollIndicator, 500);
  }
});