document.addEventListener("DOMContentLoaded", () => {
  // =========================
  // ===== BASE SETTINGS =====
  // =========================
  if (typeof BASE_URL === 'undefined') {
    console.error('BASE_URL not defined. Please expose BASE_URL in dashboard.php');
    return;
  }

  // ====== Create Toast Container ======
  const toastContainer = document.createElement('div');
  toastContainer.id = 'toastContainer';
  document.body.appendChild(toastContainer);

  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }

  const modalButtons = {
    addCollegeBtn: "collegeModal",
    addCourseBtn: "courseModal",
    addSectionBtn: "sectionModal",
  };

  // =========================
  // ===== MODAL CONTROL =====
  // =========================
  Object.entries(modalButtons).forEach(([btnId, modalId]) => {
    document.getElementById(btnId)?.addEventListener("click", () => openModal(modalId));
  });

  document.querySelectorAll(".cancel").forEach(btn =>
    btn.addEventListener("click", e => closeModal(e.target.dataset.close))
  );

  // Modal will only close when clicking the close button or cancel button
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("active");
    modal.dataset.editing = "false";
    modal.dataset.editId = "";
    clearInputs(modal);
    
    // Remove any existing click listener to prevent duplicates
    modal.removeEventListener('click', modalClickHandler);
    // Add click listener to prevent closing when clicking outside the modal content
    modal.addEventListener('click', modalClickHandler);
  }
  
  // Define the click handler as a named function so we can remove it later
  function modalClickHandler(e) {
    // Only close if the click is directly on the modal background, not on modal-content
    if (e.target === this) {
      // Don't close - modals should only close with explicit buttons
      e.stopPropagation();
    }
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove("active");
      // Remove the click listener when closing the modal
      modal.removeEventListener('click', modalClickHandler);
    }
  }

  function clearInputs(modal) {
    modal.querySelectorAll("input, select").forEach(el => {
      if (el.tagName.toLowerCase() === 'select') el.selectedIndex = 0;
      else el.value = "";
    });
  }

  // =========================
  // ===== PAGINATION EVENTS =
  // =========================
  document.getElementById('collegePrevPage')?.addEventListener('click', () => {
    if (pagination.college.currentPage > 1) {
      pagination.college.currentPage--;
      renderColleges();
    }
  });

  document.getElementById('collegeNextPage')?.addEventListener('click', () => {
    const totalPages = Math.ceil(pagination.college.allData.length / pagination.college.itemsPerPage);
    if (pagination.college.currentPage < totalPages) {
      pagination.college.currentPage++;
      renderColleges();
    }
  });

  document.getElementById('coursePrevPage')?.addEventListener('click', () => {
    if (pagination.course.currentPage > 1) {
      pagination.course.currentPage--;
      renderCourses();
    }
  });

  document.getElementById('courseNextPage')?.addEventListener('click', () => {
    const totalPages = Math.ceil(pagination.course.allData.length / pagination.course.itemsPerPage);
    if (pagination.course.currentPage < totalPages) {
      pagination.course.currentPage++;
      renderCourses();
    }
  });

  document.getElementById('sectionPrevPage')?.addEventListener('click', () => {
    if (pagination.section.currentPage > 1) {
      pagination.section.currentPage--;
      renderSections();
    }
  });

  document.getElementById('sectionNextPage')?.addEventListener('click', () => {
    const totalPages = Math.ceil(pagination.section.allData.length / pagination.section.itemsPerPage);
    if (pagination.section.currentPage < totalPages) {
      pagination.section.currentPage++;
      renderSections();
    }
  });

  // =========================
  // ===== INITIAL LOAD ======
  // =========================
  loadColleges();
  loadCourses();
  loadSections();
  // initSorting(); // Removed sorting functionality

  // =========================
  // ===== SAVE HANDLERS =====
  // =========================

  // ---- College ----
  document.getElementById('saveCollege')?.addEventListener('click', async () => {
    const modal = document.getElementById('collegeModal');
    const isEdit = modal.dataset.editing === "true";
    const id = modal.dataset.editId;
    const name = document.getElementById('collegeName').value.trim();
    const code = document.getElementById('collegeCode').value.trim();

    if (!name || !code) return showToast('Please enter both College Name and Code.', 'warning');

    const url = `${BASE_URL}/${isEdit ? 'updateCollege/' + id : 'addCollege'}`;
    const body = new URLSearchParams({ college_name: name, college_code: code }).toString();

    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Request failed');
      closeModal('collegeModal');
      showToast(isEdit ? 'College updated successfully!' : 'College added successfully!', 'success');
      loadColleges();
      loadCourses();
    } catch (err) {
      console.error(err);
      showToast(err.message || 'Failed to save college.', 'error');
    }
  });

  // ---- Course ----
  document.getElementById('saveCourse')?.addEventListener('click', async () => {
    const modal = document.getElementById('courseModal');
    const isEdit = modal.dataset.editing === "true";
    const id = modal.dataset.editId;
    const name = document.getElementById('courseName').value.trim();
    const code = document.getElementById('courseCode').value.trim();
    const college = document.getElementById('courseCollege').value;

    if (!name || !code || !college) return showToast('Please fill all fields.', 'warning');

    const url = `${BASE_URL}/${isEdit ? 'updateCourse/' + id : 'addCourse'}`;
    const body = new URLSearchParams({
      course_name: name,
      course_code: code,
      college_id: college
    }).toString();

    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Request failed');
      closeModal('courseModal');
      showToast(isEdit ? 'Course updated successfully!' : 'Course added successfully!', 'success');
      loadCourses();
    } catch (err) {
      console.error(err);
      showToast(err.message || 'Failed to save course.', 'error');
    }
  });

  // ---- Section ----
  document.getElementById('saveSection')?.addEventListener('click', async () => {
    const modal = document.getElementById('sectionModal');
    const isEdit = modal.dataset.editing === "true";
    const id = modal.dataset.editId;
    const name = document.getElementById('sectionName').value.trim();

    if (!name) return showToast('Please enter Section name.', 'warning');

    const url = `${BASE_URL}/${isEdit ? 'updateSection/' + id : 'addSection'}`;
    const body = new URLSearchParams({ section_name: name }).toString();

    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Request failed');
      closeModal('sectionModal');
      showToast(isEdit ? 'Section updated successfully!' : 'Section added successfully!', 'success');
      loadSections();
    } catch (err) {
      console.error(err);
      showToast(err.message || 'Failed to save section.', 'error');
    }
  });

  // =========================
  // ===== PAGINATION =======
  // =========================
  const pagination = {
    college: { currentPage: 1, itemsPerPage: 10, allData: [] },
    course: { currentPage: 1, itemsPerPage: 10, allData: [] },
    section: { currentPage: 1, itemsPerPage: 10, allData: [] }
  };

  function renderPagination(type) {
    const pag = pagination[type];
    const totalPages = Math.ceil(pag.allData.length / pag.itemsPerPage);
    const paginationContainer = document.getElementById(`${type}Pagination`);
    const prevBtn = document.getElementById(`${type}PrevPage`);
    const nextBtn = document.getElementById(`${type}NextPage`);
    const pageInfo = document.getElementById(`${type}PageInfo`);

    // If current page is beyond available pages, go to last page
    if (pag.currentPage > totalPages && totalPages > 0) {
      pag.currentPage = totalPages;
      // Re-render the table with the corrected page
      if (type === 'college') renderColleges();
      else if (type === 'course') renderCourses();
      else if (type === 'section') renderSections();
      return;
    }

    if (totalPages <= 1) {
      paginationContainer.style.display = 'none';
      return;
    }

    paginationContainer.style.display = 'flex';
    prevBtn.disabled = pag.currentPage === 1;
    nextBtn.disabled = pag.currentPage === totalPages;
    pageInfo.textContent = `Page ${pag.currentPage} of ${totalPages}`;
  }

  function getPaginatedData(type) {
    const pag = pagination[type];
    const startIndex = (pag.currentPage - 1) * pag.itemsPerPage;
    const endIndex = startIndex + pag.itemsPerPage;
    return pag.allData.slice(startIndex, endIndex);
  }

  // =========================
  // ===== LOAD DATA ========
  // =========================
  async function loadColleges() {
    try {
      const res = await fetch(`${BASE_URL}/getColleges`);
      const data = await res.json();
      const tbody = document.getElementById('collegeTableBody');
      if (!Array.isArray(data)) throw new Error('Invalid response');

      pagination.college.allData = data;
      pagination.college.currentPage = 1;
      
      renderColleges();
      document.getElementById('totalColleges').innerText = data.length;
      populateCollegeSelect(data);
    } catch (err) {
      console.error('loadColleges:', err);
    }
  }

  function renderColleges() {
    const tbody = document.getElementById('collegeTableBody');
    const data = getPaginatedData('college');
    const startIndex = (pagination.college.currentPage - 1) * pagination.college.itemsPerPage;
    
    tbody.innerHTML = data.length ? data.map((c, index) => `
      <tr>
        <td>${startIndex + index + 1}</td>
        <td>${escapeHtml(c.college_name)}</td>
        <td>${escapeHtml(c.college_code)}</td>
        <td class="actions">
          <button class="edit" data-id="${c.college_id}" data-name="${escapeHtml(c.college_name)}" data-code="${escapeHtml(c.college_code)}" data-type="college">Edit</button>
          <button class="delete" data-id="${c.college_id}" data-type="college">Delete</button>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="4" class="no-data">No colleges found</td></tr>`;

    renderPagination('college');
    bindActionButtons();
  }

  async function loadCourses() {
    try {
      const res = await fetch(`${BASE_URL}/getCourses`);
      const data = await res.json();
      if (!Array.isArray(data)) throw new Error('Invalid response');

      pagination.course.allData = data;
      pagination.course.currentPage = 1;
      
      renderCourses();
      document.getElementById('totalCourses').innerText = data.length;
    } catch (err) {
      console.error('loadCourses:', err);
    }
  }

  function renderCourses() {
    const tbody = document.getElementById('courseTableBody');
    const data = getPaginatedData('course');
    const startIndex = (pagination.course.currentPage - 1) * pagination.course.itemsPerPage;
    
    tbody.innerHTML = data.length ? data.map((c, index) => `
      <tr>
        <td>${startIndex + index + 1}</td>
        <td>${escapeHtml(c.course_name)}</td>
        <td>${escapeHtml(c.course_code)}</td>
        <td>${escapeHtml(c.college_name || '-')}</td>
        <td class="actions">
          <button class="edit" data-id="${c.course_id}" data-name="${escapeHtml(c.course_name)}" data-code="${escapeHtml(c.course_code)}" data-college="${c.college_id}" data-type="course">Edit</button>
          <button class="delete" data-id="${c.course_id}" data-type="course">Delete</button>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="5" class="no-data">No courses found</td></tr>`;

    renderPagination('course');
    bindActionButtons();
  }

  async function loadSections() {
    try {
      const res = await fetch(`${BASE_URL}/getSections`);
      const data = await res.json();
      if (!Array.isArray(data)) throw new Error('Invalid response');

      pagination.section.allData = data;
      pagination.section.currentPage = 1;
      
      renderSections();
      document.getElementById('totalSections').innerText = data.length;
    } catch (err) {
      console.error('loadSections:', err);
    }
  }

  function renderSections() {
    const tbody = document.getElementById('sectionTableBody');
    const data = getPaginatedData('section');
    const startIndex = (pagination.section.currentPage - 1) * pagination.section.itemsPerPage;
    
    tbody.innerHTML = data.length ? data.map((s, index) => `
      <tr>
        <td>${startIndex + index + 1}</td>
        <td>${escapeHtml(s.section_name)}</td>
        <td class="actions">
          <button class="edit" data-id="${s.section_id}" data-name="${escapeHtml(s.section_name)}" data-type="section">Edit</button>
          <button class="delete" data-id="${s.section_id}" data-type="section">Delete</button>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="3" class="no-data">No sections found</td></tr>`;

    renderPagination('section');
    bindActionButtons();
  }

  function populateCollegeSelect(colleges) {
    const select = document.getElementById('courseCollege');
    if (!select) return;
    select.innerHTML = '<option value="">Select College</option>';
    colleges.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.college_id;
      opt.textContent = c.college_name;
      select.appendChild(opt);
    });
  }

  // =========================
  // ===== EDIT / DELETE =====
  // =========================
  function bindActionButtons() {
    document.querySelectorAll('.edit').forEach(btn =>
      btn.addEventListener('click', e => handleEdit(e.currentTarget))
    );
    document.querySelectorAll('.delete').forEach(btn =>
      btn.addEventListener('click', e => handleDeleteModal(e.currentTarget))
    );
  }

  function handleEdit(target) {
    const { id, name, code, type } = target.dataset;
    const modal = document.getElementById(`${type}Modal`);
    modal.dataset.editing = "true";
    modal.dataset.editId = id;

    if (type === 'college') {
      document.getElementById('collegeName').value = name;
      document.getElementById('collegeCode').value = code;
    } else if (type === 'course') {
      document.getElementById('courseName').value = name;
      document.getElementById('courseCode').value = code;
      document.getElementById('courseCollege').value = target.dataset.college;
    } else {
      document.getElementById('sectionName').value = name;
    }

    modal.classList.add('active');
    
    // Remove any existing click listener to prevent duplicates
    modal.removeEventListener('click', modalClickHandler);
    // Add click listener to prevent closing when clicking outside the modal content
    modal.addEventListener('click', modalClickHandler);
  }

  // =========================
  // ===== DELETE MODAL =====
  // =========================
  function handleDeleteModal(target) {
    const { id, type } = target.dataset;
    const confirmModal = document.getElementById('deleteConfirmModal');
    confirmModal.classList.add('active');

    // Remove any existing click listener to prevent duplicates
    confirmModal.removeEventListener('click', modalClickHandler);
    // Add click listener to prevent closing when clicking outside the modal content
    confirmModal.addEventListener('click', modalClickHandler);

    const confirmBtn = confirmModal.querySelector('#confirmDelete');
    const cancelBtn = confirmModal.querySelector('#cancelDelete');

    // Remove previous listeners
    confirmBtn.replaceWith(confirmBtn.cloneNode(true));
    cancelBtn.replaceWith(cancelBtn.cloneNode(true));

    confirmModal.querySelector('#confirmDelete').addEventListener('click', async () => {
      confirmModal.classList.remove('active');
      try {
        const res = await fetch(`${BASE_URL}/delete${capitalize(type)}/${id}`, { method: 'POST' });
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Delete failed');
        showToast('Record deleted successfully!', 'success');
        if (type === 'college') { loadColleges(); loadCourses(); }
        if (type === 'course') loadCourses();
        if (type === 'section') loadSections();
      } catch (err) {
        console.error(err);
        showToast(err.message || 'Failed to delete record.', 'error');
      }
    });

    confirmModal.querySelector('#cancelDelete').addEventListener('click', () => {
      confirmModal.classList.remove('active');
    });
  }


  // =========================
  // ===== UTILITIES ========
  // =========================
  const capitalize = s => s.charAt(0).toUpperCase() + s.slice(1);
  const escapeHtml = str =>
    String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
});