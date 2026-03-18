// Reports Page JavaScript
const BASE_URL = window.BASE_URL || '';

// Helper function to parse dates consistently
function parseDate(dateStr) {
  if (!dateStr || dateStr === '' || dateStr === null || dateStr === undefined) {
    return new Date(0); // Return epoch for invalid dates
  }
  
  // Convert to string if not already
  const str = String(dateStr).trim();
  if (str === '') return new Date(0);
  
  // First try direct parsing (handles ISO 8601, RFC 2822, etc.)
  let parsed = new Date(str);
  if (!isNaN(parsed.getTime()) && parsed.getFullYear() > 1970) {
    return parsed;
  }
  
  // Try parsing as ISO string if it contains T (datetime format)
  if (str.includes('T')) {
    // Extract date part before T
    const datePart = str.split('T')[0];
    parsed = new Date(datePart + 'T00:00:00');
    if (!isNaN(parsed.getTime())) return parsed;
  }
  
  // Try parsing date-only strings (YYYY-MM-DD or similar)
  const dateOnlyMatch = str.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
  if (dateOnlyMatch) {
    const [, year, month, day] = dateOnlyMatch;
    parsed = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
    if (!isNaN(parsed.getTime())) return parsed;
  }
  
  // Try parsing with common separators (MM/DD/YYYY format)
  const parts = str.split(/[-/\s:]/);
  if (parts.length >= 3) {
    const firstPart = parts[0];
    const lastPart = parts[parts.length - 1];
    
    // If first part is 4 digits, assume YYYY-MM-DD
    if (firstPart.length === 4) {
      parsed = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    } 
    // If last part is 4 digits, assume MM/DD/YYYY
    else if (lastPart.length === 4) {
      parsed = new Date(parseInt(lastPart), parseInt(parts[0]) - 1, parseInt(parts[1]));
    }
    
    if (!isNaN(parsed.getTime()) && parsed.getFullYear() > 1970) {
      return parsed;
    }
  }
  
  // If all else fails, return epoch (will sort to bottom)
  return new Date(0);
}
// Detect if we're on admin or superadmin page
// Check multiple patterns: /admin/, admin/, ad-reports, etc.
const pathname = window.location.pathname.toLowerCase();
const isAdminPage = pathname.includes('/admin/') || pathname.includes('ad-reports') || pathname.includes('ad-dashboard');
const reportsEndpoint = BASE_URL + (isAdminPage ? '/admin/reports-data' : '/superadmin/reports-data');

// Function to load courses based on selected college
function loadCoursesByCollege(collegeId) {
  const courseFilter = document.getElementById('courseFilter');
  if (!courseFilter) return;
  
  // Clear existing options except "All Courses"
  courseFilter.innerHTML = '<option value="">All Courses</option>';
  
  if (!collegeId || collegeId === '') {
    return;
  }
  
  // Fetch courses for the selected college
  fetch(`${BASE_URL}/superadmin/stdCourses/${collegeId}`)
    .then(res => res.json())
    .then(courses => {
      if (Array.isArray(courses) && courses.length > 0) {
        courses.forEach(course => {
          const option = document.createElement('option');
          option.value = course.course_id;
          // Only show course code, remove college name
          option.textContent = course.course_code || course.course_name || '';
          courseFilter.appendChild(option);
        });
      }
    })
    .catch(err => {
      console.error('Error loading courses:', err);
    });
}

// ===== PAGINATION VARIABLES =====
let currentPage = 1;
const itemsPerPage = 50; // Changed from 10 to 50
let filteredAttendance = [];

let sectionChart = null;
let dateChart = null;
let courseChart = null;
let currentData = [];
let currentChartIndex = 0;
const totalCharts = 4;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  // Add sorting functionality
  initSorting();
  // ===== PAGINATION ELEMENTS =====
  const reportsPagination = document.getElementById('reportsPagination');
  const prevPageReportsBtn = document.getElementById('prevPageReports');
  const nextPageReportsBtn = document.getElementById('nextPageReports');
  const pageInfoReports = document.getElementById('pageInfoReports');
  
  loadReportsData();
  
  // Section filter - auto-update on change (only for superadmin)
  const sectionFilter = document.getElementById('sectionFilter');
  if (sectionFilter && !isAdminPage) {
    sectionFilter.addEventListener('change', loadReportsData);
  }
  
  // College filter - auto-update on change and load courses (only for superadmin)
  const collegeFilter = document.getElementById('collegeFilter');
  if (collegeFilter && !isAdminPage) {
    collegeFilter.addEventListener('change', () => {
      loadCoursesByCollege(collegeFilter.value);
      loadReportsData();
    });
  }
  
  // Course filter - auto-update on change (only for superadmin)
  const courseFilter = document.getElementById('courseFilter');
  if (courseFilter && !isAdminPage) {
    courseFilter.addEventListener('change', loadReportsData);
  }
  
  // Year level filter - auto-update on change (only for superadmin)
  const yearFilter = document.getElementById('yearFilter');
  if (yearFilter && !isAdminPage) {
    yearFilter.addEventListener('change', loadReportsData);
  }
  
  // Sex filter - auto-update on change (only for superadmin)
  const sexFilter = document.getElementById('sexFilter');
  if (sexFilter && !isAdminPage) {
    sexFilter.addEventListener('change', loadReportsData);
  }
  
  // Load courses when page loads if college is selected
  if (collegeFilter && !isAdminPage && collegeFilter.value) {
    loadCoursesByCollege(collegeFilter.value);
  }
  
  // Time period filter - auto-update on change (only for superadmin)
  const timePeriodFilter = document.getElementById('timePeriodFilter');
  if (timePeriodFilter && !isAdminPage) {
    timePeriodFilter.addEventListener('change', () => {
      // Auto-set dates based on time period
      const timePeriod = timePeriodFilter.value;
      const startDateInput = document.getElementById('startDate');
      const endDateInput = document.getElementById('endDate');
      
      if (timePeriod) {
        const today = new Date();
        let startDate, endDate;
        
        switch (timePeriod) {
          case 'daily':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
          case 'weekly':
            const monday = new Date(today);
            monday.setDate(today.getDate() - today.getDay() + 1); // Get Monday
            startDate = monday.toISOString().split('T')[0];
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6); // Get Sunday
            endDate = sunday.toISOString().split('T')[0];
            break;
          case 'monthly':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
          default:
            return;
        }
        
        startDateInput.value = startDate;
        endDateInput.value = endDate;
      }
      
      loadReportsData();
    });
  }
  
  // Filter button event - for date filtering only
  document.getElementById('filterBtn').addEventListener('click', loadReportsData);
  
  // Reset filters button
  const resetFiltersBtn = document.getElementById('resetFiltersBtn');
  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
      // Reset section filter (if exists - superadmin only)
      const sectionFilter = document.getElementById('sectionFilter');
      if (sectionFilter) {
        sectionFilter.value = '';
      }
      
      // Reset college filter (if exists - superadmin only)
      const collegeFilter = document.getElementById('collegeFilter');
      if (collegeFilter) {
        collegeFilter.value = '';
      }
      
      // Reset course filter (if exists - superadmin only)
      const courseFilter = document.getElementById('courseFilter');
      if (courseFilter) {
        courseFilter.innerHTML = '<option value="">All Courses</option>';
      }
      
      // Reset year level filter (if exists - superadmin only)
      const yearFilter = document.getElementById('yearFilter');
      if (yearFilter) {
        yearFilter.value = '';
      }
      
      // Reset sex filter (if exists - superadmin only)
      const sexFilter = document.getElementById('sexFilter');
      if (sexFilter) {
        sexFilter.value = '';
      }
      
      // Reset time period filter (if exists - superadmin only)
      const timePeriodFilter = document.getElementById('timePeriodFilter');
      if (timePeriodFilter) {
        timePeriodFilter.value = '';
      }
      
      // Reset date filters
      document.getElementById('startDate').value = '';
      document.getElementById('endDate').value = '';
      
      // Reset to first page
      currentPage = 1;
      
  // Reset sorting to default (date descending - column 8)
  currentSort = { column: 8, direction: 'desc', type: 'text' };
  updateSortIndicators();
      
      // Reload data with cleared filters
      loadReportsData();
    });
  }
  
  // Print button event
  document.getElementById('printBtn').addEventListener('click', printReport);
  
  // Export dropdown functionality
  const exportBtn = document.getElementById('exportBtn');
  const exportMenu = document.getElementById('exportMenu');
  const exportOptions = document.querySelectorAll('.export-option');
  
  // Toggle dropdown
  exportBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    exportMenu.classList.toggle('show');
  });
  
  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
      exportMenu.classList.remove('show');
    }
  });
  
  // Handle export option clicks
  exportOptions.forEach(option => {
    option.addEventListener('click', (e) => {
      e.stopPropagation();
      const format = option.getAttribute('data-format');
      exportMenu.classList.remove('show');
      if (format === 'excel') {
        exportToExcel();
      } else {
        exportToPdf();
      }
    });
  });
  
  // Chart navigation
  setupChartNavigation();
  
  // ===== PAGINATION EVENT LISTENERS =====
  if (prevPageReportsBtn) {
    prevPageReportsBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        updateTable(filteredAttendance);
      }
    });
  }
  
  if (nextPageReportsBtn) {
    nextPageReportsBtn.addEventListener('click', () => {
      const totalPages = Math.ceil(filteredAttendance.length / itemsPerPage);
      if (currentPage < totalPages) {
        currentPage++;
        updateTable(filteredAttendance);
      }
    });
  }
});

// Sorting functionality
// Default sort: by date descending (most recent first)
let currentSort = { column: 8, direction: 'desc', type: 'text' }; // Default to Date column (index 8 after adding # column)

function initSorting() {
  const sortableHeaders = document.querySelectorAll('th.sortable');
  sortableHeaders.forEach(header => {
    // Check if listener is already attached
    if (header.dataset.listenerAttached === 'true') {
      return;
    }
    
    header.dataset.listenerAttached = 'true';
    header.addEventListener('click', () => {
      const colIndex = parseInt(header.dataset.sortCol ?? '0', 10);
      const sortType = header.dataset.sortType || 'text';
      handleSort(colIndex, sortType);
    });
  });
}

function handleSort(colIndex, sortType) {
  // Update sort state
  if (currentSort.column === colIndex) {
    // Toggle direction
    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    // New column, default to ascending
    currentSort.column = colIndex;
    currentSort.direction = 'asc';
    currentSort.type = sortType;
  }
  
  // Update UI indicators
  updateSortIndicators();
  
  // Update the table (it will handle sorting internally)
  updateTable(filteredAttendance);
}

function updateSortIndicators() {
  // Clear all indicators
  document.querySelectorAll('th.sortable .sort-indicator').forEach(ind => {
    ind.textContent = '';
  });
  
  // Set indicator for current sort
  if (currentSort.column !== undefined) {
    const currentHeader = document.querySelector(`th.sortable[data-sort-col="${currentSort.column}"]`);
    if (currentHeader) {
      const ind = currentHeader.querySelector('.sort-indicator');
      if (ind) {
        ind.textContent = currentSort.direction === 'asc' ? '▲' : '▼';
      }
    }
  }
}

function sortAttendanceData() {
  if (currentSort.column === undefined || !currentSort.direction || !filteredAttendance || filteredAttendance.length === 0) {
    return;
  }
  
  const colIndex = currentSort.column;
  const sortType = currentSort.type || 'text';
  
  // Sort the data array
  filteredAttendance.sort((a, b) => {
    let aValue, bValue;
    
    // Map column index to data field (adjusted for # column)
    switch (colIndex) {
      case 0: // # (Numbering column - special sorting)
        // For numbering column, we maintain the original order
        // This is handled by the table pagination, so we return 0 to preserve order
        return 0;
      case 1: // Student ID
        aValue = a.student_number || '';
        bValue = b.student_number || '';
        // Extract numeric part for proper sorting
        const aNum = parseInt(aValue.replace(/\D/g, '')) || 0;
        const bNum = parseInt(bValue.replace(/\D/g, '')) || 0;
        if (currentSort.direction === 'asc') {
          return aNum - bNum;
        } else {
          return bNum - aNum;
        }
      case 2: // Student Name
        aValue = ((a.last_name || '') + ', ' + (a.first_name || '') + (a.middle_initial ? ' ' + a.middle_initial + '.' : '')).toLowerCase();
        bValue = ((b.last_name || '') + ', ' + (b.first_name || '') + (b.middle_initial ? ' ' + b.middle_initial + '.' : '')).toLowerCase();
        break;
      case 3: // Sex
        aValue = (a.sex || '').toLowerCase();
        bValue = (b.sex || '').toLowerCase();
        break;
      case 4: // Course
        aValue = (a.course_code || '').toLowerCase();
        bValue = (b.course_code || '').toLowerCase();
        break;
      case 5: // Department/College
        aValue = (a.college_name || '').toLowerCase();
        bValue = (b.college_name || '').toLowerCase();
        break;
      case 6: // Time In
        const aTimeStr = a.scan_datetime || a.created_at || '';
        const bTimeStr = b.scan_datetime || b.created_at || '';
        aValue = parseDate(aTimeStr);
        bValue = parseDate(bTimeStr);
        if (isNaN(aValue.getTime())) aValue = new Date(0);
        if (isNaN(bValue.getTime())) bValue = new Date(0);
        if (currentSort.direction === 'asc') {
          return aValue.getTime() - bValue.getTime();
        } else {
          return bValue.getTime() - aValue.getTime();
        }
      case 7: // Section
        aValue = (a.sections_text || a.section_name || '').toLowerCase();
        bValue = (b.sections_text || b.section_name || '').toLowerCase();
        break;
      case 8: // Date
        const aDateStr = a.visit_date || a.scan_datetime || a.created_at || '';
        const bDateStr = b.visit_date || b.scan_datetime || b.created_at || '';
        aValue = parseDate(aDateStr);
        bValue = parseDate(bDateStr);
        if (isNaN(aValue.getTime())) aValue = new Date(0);
        if (isNaN(bValue.getTime())) bValue = new Date(0);
        if (currentSort.direction === 'asc') {
          return aValue.getTime() - bValue.getTime();
        } else {
          return bValue.getTime() - aValue.getTime();
        }
      default:
        return 0;
    }
    
    // For text comparisons
    if (sortType === 'text') {
      if (currentSort.direction === 'asc') {
        if (aValue < bValue) return -1;
        if (aValue > bValue) return 1;
        return 0;
      } else {
        if (aValue > bValue) return -1;
        if (aValue < bValue) return 1;
        return 0;
      }
    }
    
    return 0;
  });
}

// ===== Chart Carousel Navigation =====
function setupChartNavigation() {
  const prevBtn = document.getElementById('prevChartBtn');
  const nextBtn = document.getElementById('nextChartBtn');
  const indicators = document.querySelectorAll('.chart-indicators .indicator');
  
  // Previous button
  prevBtn.addEventListener('click', () => {
    currentChartIndex = (currentChartIndex - 1 + totalCharts) % totalCharts;
    showChart(currentChartIndex);
  });
  
  // Next button
  nextBtn.addEventListener('click', () => {
    currentChartIndex = (currentChartIndex + 1) % totalCharts;
    showChart(currentChartIndex);
  });
  
  // Indicator clicks
  indicators.forEach((indicator, index) => {
    indicator.addEventListener('click', () => {
      currentChartIndex = index;
      showChart(currentChartIndex);
    });
  });
}

function showChart(index) {
  const panels = document.querySelectorAll('.chart-panel');
  const indicators = document.querySelectorAll('.chart-indicators .indicator');
  
  // Update panels
  panels.forEach((panel, i) => {
    panel.classList.remove('active', 'prev');
    if (i === index) {
      panel.classList.add('active');
    } else if (i < index) {
      panel.classList.add('prev');
    }
  });
  
  // Update indicators
  indicators.forEach((indicator, i) => {
    indicator.classList.toggle('active', i === index);
  });
  
  // Update current index
  currentChartIndex = index;
}

function loadReportsData() {
  // Reset to first page when loading new data
  currentPage = 1;
  
  const sectionFilter = document.getElementById('sectionFilter');
  const collegeFilter = document.getElementById('collegeFilter');
  const courseFilter = document.getElementById('courseFilter');
  const yearFilter = document.getElementById('yearFilter');
  const sexFilter = document.getElementById('sexFilter');
  const timePeriodFilter = document.getElementById('timePeriodFilter');
  const sectionId = sectionFilter ? sectionFilter.value : '';
  const collegeId = collegeFilter ? collegeFilter.value : '';
  const courseId = courseFilter ? courseFilter.value : '';
  const yearLevel = yearFilter ? yearFilter.value : '';
  const sex = sexFilter ? sexFilter.value : '';
  const timePeriod = timePeriodFilter ? timePeriodFilter.value : '';
  const startDate = document.getElementById('startDate').value;
  const endDate = document.getElementById('endDate').value;
  
  // Build URL - only include non-empty parameters
  // For admin pages, don't send section_id (it's automatically filtered by their assigned section)
  const params = new URLSearchParams();
  if (!isAdminPage && sectionId && sectionId !== '') {
    params.append('section_id', sectionId);
  }
  if (!isAdminPage && collegeId && collegeId !== '') {
    params.append('college_id', collegeId);
  }
  if (!isAdminPage && courseId && courseId !== '') {
    params.append('course_id', courseId);
  }
  if (!isAdminPage && yearLevel && yearLevel !== '') {
    params.append('year_level', yearLevel);
  }
  if (!isAdminPage && sex && sex !== '') {
    params.append('sex', sex);
  }
  if (!isAdminPage && timePeriod && timePeriod !== '') {
    params.append('time_period', timePeriod);
  }
  if (startDate && startDate !== '') {
    params.append('start_date', startDate);
  }
  if (endDate && endDate !== '') {
    params.append('end_date', endDate);
  }
  
  const url = reportsEndpoint + (params.toString() ? '?' + params.toString() : '');
  
  // Show loading state
  // Determine colspan based on whether sex column exists (10 cols with sex, 9 without)
  const hasSexColumn = document.querySelector('th.sortable[data-sort-col="3"]') !== null;
  const colspan = hasSexColumn ? 10 : 9;
  document.getElementById('attendanceTableBody').innerHTML = 
    `<tr><td colspan="${colspan}" style="text-align: center; color: #888;">Loading data...</td></tr>`;
  
  // Don't reset sorting when loading new data - it should persist
  // Save current sort state
  const savedSort = {...currentSort};
  
  fetch(url)
    .then(res => {
      if (!res.ok) {
        return res.json().then(errData => {
          throw new Error(errData.message || 'HTTP error! status: ' + res.status);
        }).catch(() => {
          throw new Error('HTTP error! status: ' + res.status);
        });
      }
      return res.json();
    })
    .then(data => {
      if (data.success && data.attendance !== undefined) {
        currentData = data.attendance;
        filteredAttendance = data.attendance; // Store for pagination
        updateSummaryCards(data);
        if (data.summary) {
          updateCharts(data.summary);
        }
        // Restore sort state and apply sorting
        if (savedSort.column !== undefined) {
          currentSort = {...savedSort};
        }
        updateSortIndicators();
        // updateTable will handle sorting internally
        updateTable(data.attendance);
      } else {
        showError(data.message || 'Failed to load reports data.');
      }
    })
    .catch(err => {
      console.error('Error fetching reports:', err);
      showError(err.message || 'Error loading reports data. Please try again.');
    });
}

function updateSummaryCards(data) {
  const attendance = data.attendance || [];
  const summary = data.summary || {};
  
  // For Super Admin:
  // - "Most Visited Section" should show the actual section name
  // - "Busiest Hour" should show the time range of the busiest hour (across all records)
  // - "Total Attendance This Week" should show total attendance records
  // - "Most Active Course" should show the actual course name
  
  // For Admin:
  // - "Total Attendance This Week" should show total attendance records
  // - "Busiest Hour" should show the time range of the busiest hour (across all records)
  // - "Most Active Course" should show the actual course name
  
  // Calculate total attendance records
  const totalRecords = attendance.length;
  
  // Get the most visited section name (for Super Admin)
  let mostVisitedSection = 'No data';
  if (summary.by_section && summary.by_section.length > 0) {
    mostVisitedSection = summary.by_section[0].section_name || 'Unknown';
  }
  
  // Get the most active course name (for both Admin and Super Admin)
  let mostActiveCourse = 'No data';
  if (summary.by_course && summary.by_course.length > 0) {
    mostActiveCourse = summary.by_course[0].course_code || 'Unknown';
  }
  
  // Calculate busiest hour (across all records, not just today)
  let busiestHourRange = 'No data';
  
  // Group all attendance by hour and find the peak
  if (attendance.length > 0) {
    const hourlyCounts = {};
    
    attendance.forEach(a => {
      try {
        // Extract hour from scan_datetime
        const scanDate = new Date(a.scan_datetime);
        const hour = scanDate.getHours();
        
        // Initialize count for this hour if not exists
        if (hourlyCounts[hour] === undefined) {
          hourlyCounts[hour] = 0;
        }
        
        // Increment count for this hour
        hourlyCounts[hour]++;
      } catch (e) {
        console.warn('Error parsing date for attendance record:', a);
      }
    });
    
    // Find the hour with maximum count
    if (Object.keys(hourlyCounts).length > 0) {
      const peakHour = Object.keys(hourlyCounts).reduce((a, b) => 
        hourlyCounts[a] > hourlyCounts[b] ? a : b
      );
      
      // Convert to 12-hour format with AM/PM
      const startHour = parseInt(peakHour);
      const endHour = (startHour + 1) % 24;
      
      // Format start time
      const startPeriod = startHour >= 12 ? 'PM' : 'AM';
      const startDisplayHour = startHour === 0 ? 12 : (startHour > 12 ? startHour - 12 : startHour);
      
      // Format end time
      const endPeriod = endHour >= 12 ? 'PM' : 'AM';
      const endDisplayHour = endHour === 0 ? 12 : (endHour > 12 ? endHour - 12 : endHour);
      
      // Create the time range string
      busiestHourRange = `${startDisplayHour}:00${startPeriod} - ${endDisplayHour}:00${endPeriod}`;
    }
  }
  
  // Update the summary cards based on their labels
  const totalAttendanceEl = document.getElementById('totalAttendance');
  const uniqueStudentsEl = document.getElementById('uniqueStudents');
  const totalVisitsEl = document.getElementById('totalVisits');
  const mostActiveCourseEl = document.getElementById('mostActiveCourse');
  
  // For Admin view, use different IDs
  const totalAttendanceWeekEl = document.getElementById('totalAttendanceWeek');
  const busiestHourTodayEl = document.getElementById('busiestHourToday');
  
  // New element for most active gender
  const mostActiveGenderEl = document.getElementById('mostActiveGender');
  
  // Check which labels are being used to determine the correct mapping
  // Get the h3 elements that contain the labels
  const totalAttendanceLabel = totalAttendanceEl?.closest('.summary-card')?.querySelector('h3')?.textContent || '';
  const uniqueStudentsLabel = uniqueStudentsEl?.closest('.summary-card')?.querySelector('h3')?.textContent || '';
  const totalVisitsLabel = totalVisitsEl?.closest('.summary-card')?.querySelector('h3')?.textContent || '';
  const mostActiveCourseLabel = mostActiveCourseEl?.closest('.summary-card')?.querySelector('h3')?.textContent || '';
  
  // Update cards based on their actual labels
  if (totalAttendanceEl) {
    if (totalAttendanceLabel.includes('Most Visited Section')) {
      // Super Admin - Most Visited Section (show actual section name)
      totalAttendanceEl.textContent = mostVisitedSection;
      totalAttendanceEl.setAttribute('data-type', 'text');
    } else if (totalAttendanceLabel.includes('Total Attendance')) {
      // Super Admin - Total Attendance
      totalAttendanceEl.textContent = totalRecords;
      totalAttendanceEl.setAttribute('data-type', 'number');
    }
  }
  
  // For Admin view, update using the new IDs
  if (totalAttendanceWeekEl) {
    totalAttendanceWeekEl.textContent = totalRecords;
    totalAttendanceWeekEl.setAttribute('data-type', 'number');
  }
  
  // Busiest Hour (across all records, not just today)
  if (uniqueStudentsEl) {
    uniqueStudentsEl.textContent = busiestHourRange;
    uniqueStudentsEl.setAttribute('data-type', 'text');
  }
  
  // For Admin view, update using the new ID
  if (busiestHourTodayEl) {
    busiestHourTodayEl.textContent = busiestHourRange;
    busiestHourTodayEl.setAttribute('data-type', 'text');
  }
  
  if (totalVisitsEl) {
    if (totalVisitsLabel.includes('Total Attendance')) {
      // Super Admin - Total Attendance This Week
      totalVisitsEl.textContent = totalRecords;
      totalVisitsEl.setAttribute('data-type', 'number');
    } else if (totalVisitsLabel.includes('Most Active Course')) {
      // This condition should not be reached now since we've updated the ID
      totalVisitsEl.textContent = mostActiveCourse;
      totalVisitsEl.setAttribute('data-type', 'text');
    }
  }
  
  // Most Active Course (for both Admin and Super Admin)
  if (mostActiveCourseEl) {
    if (mostActiveCourseLabel.includes('Most Active Course')) {
      mostActiveCourseEl.textContent = mostActiveCourse;
      mostActiveCourseEl.setAttribute('data-type', 'text');
    }
  }
  
  // Most Active Student Gender (for Admin view)
  if (mostActiveGenderEl) {
    if (summary.by_gender && summary.by_gender.most_active_gender) {
      const mostActiveGender = summary.by_gender.most_active_gender;
      mostActiveGenderEl.textContent = `${mostActiveGender.gender}`;
    } else {
      mostActiveGenderEl.textContent = 'No data';
    }
    mostActiveGenderEl.setAttribute('data-type', 'text');
  }
}

function updateCharts(summary) {
  if (!summary) return;
  
  // Check if Chart is available
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js is not loaded. Charts will not be displayed.');
    return;
  }
  
  // Section Chart (Pie Chart)
  updateSectionChart(summary.by_section || []);
  
  // Date Chart (Line Chart)
  updateDateChart(summary.by_date || []);
  
  // Course Chart (Bar Chart)
  updateCourseChart(summary.by_course || []);
  
  // Student Chart (Bar Chart)
  updateStudentChart(summary.by_student || []);
}

function updateSectionChart(data) {
  const ctx = document.getElementById('sectionChart');
  if (!ctx || typeof Chart === 'undefined') return;
  
  if (sectionChart) {
    sectionChart.destroy();
  }
  
  const labels = data.map(item => item.section_name || 'Unknown');
  const values = data.map(item => parseInt(item.count) || 0);
  
  sectionChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: labels,
      datasets: [{
        data: values,
        backgroundColor: [
          '#1A1851',
          '#FCB316',
          '#292776',
          '#4A90E2',
          '#50C878',
          '#FF6B6B',
          '#9B59B6',
          '#3498DB',
          '#E67E22',
          '#1ABC9C'
        ],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          position: 'right',
        },
        title: {
          display: false
        }
      }
    }
  });
}

function updateDateChart(data) {
  const ctx = document.getElementById('dateChart');
  if (!ctx || typeof Chart === 'undefined') return;
  
  if (dateChart) {
    dateChart.destroy();
  }
  
  const labels = data.map(item => {
    const date = new Date(item.date);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  });
  const values = data.map(item => parseInt(item.count) || 0);
  
  dateChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Attendance Count',
        data: values,
        borderColor: '#1A1851',
        backgroundColor: 'rgba(26, 24, 81, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#FCB316',
        pointBorderColor: '#1A1851',
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: true,
          position: 'top'
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });
}

function updateCourseChart(data) {
  const ctx = document.getElementById('courseChart');
  if (!ctx || typeof Chart === 'undefined') return;
  
  if (courseChart) {
    courseChart.destroy();
  }
  
  const labels = data.map(item => item.course_code || 'Unknown');
  const values = data.map(item => parseInt(item.count) || 0);
  
  courseChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Attendance Count',
        data: values,
        backgroundColor: '#FCB316',
        borderColor: '#1A1851',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });
}

function updateStudentChart(data) {
  const listContainer = document.getElementById('studentListContent');
  if (!listContainer) return;
  
  // Get top 5 students by visit count
  const top5Data = data.slice(0, 5);
  
  if (top5Data.length === 0) {
    listContainer.innerHTML = '<div class="no-data">No students found</div>';
    return;
  }
  
  // Create list items
  const listItems = top5Data.map((item, index) => {
    // Format the student name as LastName, FirstName MiddleInitial
    const studentName = (item.last_name || 'N/A') + ', ' + (item.first_name || 'N/A') + (item.middle_initial ? ' ' + item.middle_initial + '.' : '');
    const visitCount = parseInt(item.visit_count) || 0;
    
    return `
      <div class="list-item">
        <span class="rank">${index + 1}</span>
        <span class="student-info">${studentName}</span>
        <span class="visit-count">${visitCount}</span>
      </div>
    `;
  }).join('');
  
  listContainer.innerHTML = listItems;
}

// ===== Render Table with Pagination =====
function updateTable(attendance) {
  const tbody = document.getElementById('attendanceTableBody');
  const noDataMsg = document.getElementById('noDataMessage');
  const reportsPagination = document.getElementById('reportsPagination');
  const prevPageReportsBtn = document.getElementById('prevPageReports');
  const nextPageReportsBtn = document.getElementById('nextPageReports');
  const pageInfoReports = document.getElementById('pageInfoReports');
  
  // Update filteredAttendance if new data is provided
  if (attendance && Array.isArray(attendance)) {
    filteredAttendance = attendance;
  }
  
  // Apply current sorting if any (before pagination)
  if (currentSort.column !== undefined && currentSort.direction) {
    sortAttendanceData();
  }
  
  if (!filteredAttendance || filteredAttendance.length === 0) {
    tbody.innerHTML = '';
    noDataMsg.style.display = 'block';
    if (reportsPagination) reportsPagination.style.display = 'none';
    return;
  }
  
  noDataMsg.style.display = 'none';
  
  // Calculate pagination
  const totalItems = filteredAttendance.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  
  // Show pagination controls if there are multiple pages
  if (reportsPagination) {
    if (totalPages > 1) {
      reportsPagination.style.display = 'flex';
      
      // Disable/enable buttons
      if (prevPageReportsBtn) prevPageReportsBtn.disabled = currentPage === 1;
      if (nextPageReportsBtn) nextPageReportsBtn.disabled = currentPage === totalPages;
      
      // Update page info
      if (pageInfoReports) pageInfoReports.textContent = `Page ${currentPage} of ${totalPages}`;
    } else {
      reportsPagination.style.display = 'none';
    }
  }
  
  // Get current page items
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentAttendance = filteredAttendance.slice(startIndex, endIndex);
  
  tbody.innerHTML = currentAttendance.map((entry, index) => {
    const studentName = (entry.last_name || 'N/A') + ', ' + (entry.first_name || 'N/A') + (entry.middle_initial ? ' ' + entry.middle_initial + '.' : '');
    
    // Format Time In
    const timeInStr = entry.scan_datetime || entry.created_at || '';
    const timeInObj = new Date(timeInStr);
    let formattedTimeIn = 'N/A';
    if (!isNaN(timeInObj.getTime())) {
      formattedTimeIn = timeInObj.toLocaleString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    }
    
    // Use visit_date if available, otherwise use scan_datetime
    const dateStr = entry.visit_date || entry.scan_datetime || entry.created_at || '';
    const dateObj = new Date(dateStr);
    let formattedDate = 'N/A';
    
    if (!isNaN(dateObj.getTime())) {
      formattedDate = dateObj.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      });
    }
    
    // Get all sections visited - use sections_text if available, otherwise build from sections_visited array
    let sectionsText = entry.sections_text || 'N/A';
    if (sectionsText === 'N/A' && Array.isArray(entry.sections_visited) && entry.sections_visited.length > 0) {
      sectionsText = entry.sections_visited.map(s => s.section_name).join(', ');
    }
    
    // Calculate the global row number (across all pages)
    const globalIndex = startIndex + index + 1;
    
    return `
      <tr>
        <td>${globalIndex}</td>
        <td>${entry.student_number || 'N/A'}</td>
        <td>${studentName.trim() || 'N/A'}</td>
        <td>${entry.sex || 'N/A'}</td>
        <td>${entry.course_code || 'N/A'}</td>
        <td>${entry.college_name || 'N/A'}</td>
        <td>${formattedTimeIn}</td>
        <td>${sectionsText}</td>
        <td>${formattedDate}</td>
      </tr>
    `;
  }).join('');
}

function showError(message) {
  // Determine colspan based on whether sex column exists (10 cols with sex, 9 without)
  const hasSexColumn = document.querySelector('th.sortable[data-sort-col="3"]') !== null;
  const colspan = hasSexColumn ? 10 : 9;
  document.getElementById('attendanceTableBody').innerHTML = 
    `<tr><td colspan="${colspan}" style="text-align: center; color: #e74c3c;">${message}</td></tr>`;
  document.getElementById('noDataMessage').style.display = 'none';
  // Hide pagination on error
  const reportsPagination = document.getElementById('reportsPagination');
  if (reportsPagination) reportsPagination.style.display = 'none';
}

// Print the attendance table
function printReport() {
  const tableContent = document.querySelector('.panel.active table').outerHTML;
  
  // Get summary data based on page type
  let totalAttendance, mostActiveCourse;
  if (isAdminPage) {
    // Admin page elements
    const totalAttendanceElement = document.getElementById('totalAttendanceWeek');
    const mostActiveCourseElement = document.getElementById('mostActiveCourse');
    
    totalAttendance = totalAttendanceElement ? totalAttendanceElement.textContent : '0';
    mostActiveCourse = mostActiveCourseElement ? mostActiveCourseElement.textContent : 'N/A';
  } else {
    // Superadmin page elements - use totalVisits for actual attendance count
    const totalAttendanceElement = document.getElementById('totalVisits');
    const mostActiveCourseElement = document.getElementById('mostActiveCourse');
    
    totalAttendance = totalAttendanceElement ? totalAttendanceElement.textContent : '0';
    mostActiveCourse = mostActiveCourseElement ? mostActiveCourseElement.textContent : 'N/A';
  }
  
  // Also get the busiest hour for more complete information
  let busiestHour = 'N/A';
  if (isAdminPage) {
    const busiestHourElement = document.getElementById('busiestHourToday');
    busiestHour = busiestHourElement ? busiestHourElement.textContent : 'N/A';
  } else {
    const busiestHourElement = document.getElementById('uniqueStudents');
    busiestHour = busiestHourElement ? busiestHourElement.textContent : 'N/A';
  }
  
  // Get most active gender for admin page
  let mostActiveGender = 'N/A';
  if (isAdminPage) {
    const mostActiveGenderElement = document.getElementById('mostActiveGender');
    mostActiveGender = mostActiveGenderElement ? mostActiveGenderElement.textContent : 'N/A';
  }
  
  const newWindow = window.open('', '', 'width=1200,height=800');
  newWindow.document.write(`
    <html>
    <head>
      <title>Print Attendance Report</title>
      <style>
        body { 
          font-family: Arial, sans-serif; 
          padding: 20px; 
          margin: 0;
        }
        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
          padding-bottom: 10px;
          border-bottom: 2px solid #1A1851;
        }
        .logo {
          width: 50px;
          height: 50px;
        }
        .header-text {
          text-align: center;
          flex-grow: 1;
          margin: 0 20px;
        }
        .header-text h1 {
          margin: 0 0 5px 0;
          font-size: 18px;
          color: #1A1851;
        }
        .header-text h2 {
          margin: 0;
          font-size: 16px;
          color: #333;
        }
        .summary-info {
          display: flex;
          justify-content: space-between;
          margin-bottom: 20px;
          padding: 10px;
          background-color: #f5f5f5;
          border-radius: 5px;
        }
        .summary-item {
          text-align: center;
        }
        .summary-item h3 {
          margin: 0 0 5px 0;
          font-size: 14px;
          color: #1A1851;
        }
        .summary-item p {
          margin: 0;
          font-size: 16px;
          font-weight: bold;
          color: #FCB316;
        }
        table { 
          width: 100%; 
          border-collapse: collapse; 
          margin-top: 20px; 
        }
        th, td { 
          border: 1px solid #000; 
          padding: 8px; 
          text-align: left; 
        }
        th { 
          background-color: #1A1851; 
          color: #fff; 
        }
        .charts-section { 
          display: none; 
        }
        @media print {
          .charts-section { 
            display: none !important; 
          }
          body {
            padding: 10px;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <img src="${window.BASE_URL}/assets/icons/logo1.png" alt="Logo 1" class="logo" onerror="this.style.display='none'">
        <div class="header-text">
          <h1>University of Science and Technology</h1>
          <h1>of Southern Philippines</h1>
          <h2>Library Attendance Report</h2>
        </div>
        <img src="${window.BASE_URL}/assets/icons/ustp-logo.png" alt="USTP Logo" class="logo" onerror="this.style.display='none'">
      </div>
      
      <div class="summary-info">
        <div class="summary-item">
          <h3>Total Attendance</h3>
          <p>${totalAttendance}</p>
        </div>
        <div class="summary-item">
          <h3>Most Active Course</h3>
          <p>${mostActiveCourse}</p>
        </div>
        <div class="summary-item">
          <h3>Busiest Hour</h3>
          <p>${busiestHour}</p>
        </div>
        ${isAdminPage ? `
        <div class="summary-item">
          <h3>Most Active Student Gender</h3>
          <p>${mostActiveGender}</p>
        </div>` : ''}
      </div>
      
      ${tableContent}
    </body>
    </html>
  `);
  newWindow.document.close();
  newWindow.print();
}

// Export to Excel
function exportToExcel() {
  const sectionFilter = document.getElementById('sectionFilter');
  const collegeFilter = document.getElementById('collegeFilter');
  const courseFilter = document.getElementById('courseFilter');
  const yearFilter = document.getElementById('yearFilter');
  const sexFilter = document.getElementById('sexFilter');
  const timePeriodFilter = document.getElementById('timePeriodFilter');
  const sectionId = sectionFilter ? sectionFilter.value : '';
  const collegeId = collegeFilter ? collegeFilter.value : '';
  const courseId = courseFilter ? courseFilter.value : '';
  const yearLevel = yearFilter ? yearFilter.value : '';
  const sex = sexFilter ? sexFilter.value : '';
  const timePeriod = timePeriodFilter ? timePeriodFilter.value : '';
  const startDate = document.getElementById('startDate').value;
  const endDate = document.getElementById('endDate').value;
  
  // Build URL with filters
  const params = new URLSearchParams();
  params.append('format', 'excel');
  if (!isAdminPage && sectionId && sectionId !== '') {
    params.append('section_id', sectionId);
  }
  if (!isAdminPage && collegeId && collegeId !== '') {
    params.append('college_id', collegeId);
  }
  if (!isAdminPage && courseId && courseId !== '') {
    params.append('course_id', courseId);
  }
  if (!isAdminPage && yearLevel && yearLevel !== '') {
    params.append('year_level', yearLevel);
  }
  if (!isAdminPage && sex && sex !== '') {
    params.append('sex', sex);
  }
  if (!isAdminPage && timePeriod && timePeriod !== '') {
    params.append('time_period', timePeriod);
  }
  if (startDate && startDate !== '') {
    params.append('start_date', startDate);
  }
  if (endDate && endDate !== '') {
    params.append('end_date', endDate);
  }
  
  const exportEndpoint = BASE_URL + (isAdminPage ? '/admin/export-reports' : '/superadmin/export-reports');
  const url = exportEndpoint + '?' + params.toString();
  
  // Open in new window to trigger download
  window.location.href = url;
}

// Export table to PDF using jsPDF
function exportToPdf() {
  // First, log the export action on the server
  const sectionFilter = document.getElementById('sectionFilter');
  const collegeFilter = document.getElementById('collegeFilter');
  const courseFilter = document.getElementById('courseFilter');
  const yearFilter = document.getElementById('yearFilter');
  const sexFilter = document.getElementById('sexFilter');
  const timePeriodFilter = document.getElementById('timePeriodFilter');
  const sectionId = sectionFilter ? sectionFilter.value : '';
  const collegeId = collegeFilter ? collegeFilter.value : '';
  const courseId = courseFilter ? courseFilter.value : '';
  const yearLevel = yearFilter ? yearFilter.value : '';
  const sex = sexFilter ? sexFilter.value : '';
  const timePeriod = timePeriodFilter ? timePeriodFilter.value : '';
  const startDate = document.getElementById('startDate').value;
  const endDate = document.getElementById('endDate').value;
  
  // Build URL with filters for logging
  const params = new URLSearchParams();
  params.append('format', 'pdf');
  if (!isAdminPage && sectionId && sectionId !== '') {
    params.append('section_id', sectionId);
  }
  if (!isAdminPage && collegeId && collegeId !== '') {
    params.append('college_id', collegeId);
  }
  if (!isAdminPage && courseId && courseId !== '') {
    params.append('course_id', courseId);
  }
  if (!isAdminPage && yearLevel && yearLevel !== '') {
    params.append('year_level', yearLevel);
  }
  if (!isAdminPage && sex && sex !== '') {
    params.append('sex', sex);
  }
  if (!isAdminPage && timePeriod && timePeriod !== '') {
    params.append('time_period', timePeriod);
  }
  if (startDate && startDate !== '') {
    params.append('start_date', startDate);
  }
  if (endDate && endDate !== '') {
    params.append('end_date', endDate);
  }
  
  const exportEndpoint = BASE_URL + (isAdminPage ? '/admin/export-reports' : '/superadmin/export-reports');
  const url = exportEndpoint + '?' + params.toString();
  
  // Call server to log the export (fire and forget)
  fetch(url, {
    method: 'GET',
    headers: {
      'Accept': 'application/json'
    }
  }).catch(err => {
    console.error('Error logging PDF export:', err);
    // Continue with PDF generation even if logging fails
  });
  
  // Use the current filtered data from the table
  // Check if jsPDF is already loaded
  if (window.jspdf) {
    generatePdf();
    return;
  }
  
  // Include jsPDF and autoTable from local files
  const script1 = document.createElement('script');
  script1.src = BASE_URL + '/assets/js/jspdf.umd.min.js';
  script1.onload = () => {
    const script2 = document.createElement('script');
    script2.src = BASE_URL + '/assets/js/jspdf.plugin.autotable.min.js';
    script2.onload = generatePdf;
    document.body.appendChild(script2);
  };
  script1.onerror = () => {
    alert('Error loading PDF library. Please check your connection.');
  };
  document.body.appendChild(script1);
}

function generatePdf() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  
  // Add university logos and header
  const logo1Url = window.BASE_URL + '/assets/icons/logo1.png';
  const ustpLogoUrl = window.BASE_URL + '/assets/icons/ustp-logo.png';
  
  // Create image objects to preload the logos
  const logo1Img = new Image();
  const ustpLogoImg = new Image();
  logo1Img.crossOrigin = 'Anonymous';
  ustpLogoImg.crossOrigin = 'Anonymous';
  logo1Img.src = logo1Url;
  ustpLogoImg.src = ustpLogoUrl;
  
  let logo1Loaded = false;
  let ustpLogoLoaded = false;
  
  // Function to create PDF content when both images are loaded
  function createPdfContent() {
    const pageWidth = doc.internal.pageSize.width;
    
    // Add left logo (logo1.png)
    if (logo1Loaded) {
      doc.addImage(logo1Img, 'PNG', 14, 10, 25, 25);
    }
    
    // Add right logo (ustp-logo.png)
    if (ustpLogoLoaded) {
      doc.addImage(ustpLogoImg, 'PNG', pageWidth - 39, 10, 25, 25);
    }
    
    // Add university name centered between the logos
    doc.setFontSize(16);
    doc.setFont(undefined, 'bold');
    // Calculate center position accounting for logo widths
    const centerPosition = pageWidth / 2;
    doc.text("University of Science and Technology", centerPosition, 18, { align: 'center' });
    doc.text("of Southern Philippines", centerPosition, 25, { align: 'center' });
    
    doc.setFontSize(14);
    doc.setFont(undefined, 'normal');
    doc.text("Library Attendance Report", centerPosition, 33, { align: 'center' });
    
    // Add summary based on page type (admin or superadmin)
    let totalAttendance, mostActiveCourse, busiestHour, mostActiveGender;
    if (isAdminPage) {
      // Admin page elements
      const totalAttendanceElement = document.getElementById('totalAttendanceWeek');
      const mostActiveCourseElement = document.getElementById('mostActiveCourse');
      const busiestHourElement = document.getElementById('busiestHourToday');
      const mostActiveGenderElement = document.getElementById('mostActiveGender');
      
      totalAttendance = totalAttendanceElement ? totalAttendanceElement.textContent : '0';
      mostActiveCourse = mostActiveCourseElement ? mostActiveCourseElement.textContent : 'N/A';
      busiestHour = busiestHourElement ? busiestHourElement.textContent : 'N/A';
      mostActiveGender = mostActiveGenderElement ? mostActiveGenderElement.textContent : 'N/A';
    } else {
      // Superadmin page elements - use totalVisits for actual attendance count
      const totalAttendanceElement = document.getElementById('totalVisits');
      const mostActiveCourseElement = document.getElementById('mostActiveCourse');
      const busiestHourElement = document.getElementById('uniqueStudents');
      
      totalAttendance = totalAttendanceElement ? totalAttendanceElement.textContent : '0';
      mostActiveCourse = mostActiveCourseElement ? mostActiveCourseElement.textContent : 'N/A';
      busiestHour = busiestHourElement ? busiestHourElement.textContent : 'N/A';
      mostActiveGender = 'N/A'; // SuperAdmin doesn't have this card yet
    }
    
    doc.setFontSize(12);
    const startY = 45;
    
    // Add summary info to the first page before the table
    doc.text(`Total Attendance: ${totalAttendance}`, 14, startY);
    doc.text(`Most Active Course: ${mostActiveCourse}`, 14, startY + 7);
    doc.text(`Busiest Hour: ${busiestHour}`, 14, startY + 14);
    if (isAdminPage) {
      doc.text(`Most Active Student Gender: ${mostActiveGender}`, 14, startY + 21);
    }
    
    // Get table data
    const table = document.querySelector('#attendanceTableBody');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    if (rows.length === 0) {
      doc.text("No data available", 14, startY + 35);
      doc.save("attendance_report.pdf");
      return;
    }
    
    // Prepare table data
    const tableData = rows.map(row => {
      const cells = Array.from(row.querySelectorAll('td'));
      return cells.map(cell => cell.textContent.trim());
    });
    
    // Store header info for reuse on all pages
    const headerInfo = {
      logo1Img: logo1Img,
      ustpLogoImg: ustpLogoImg,
      logo1Loaded: logo1Loaded,
      ustpLogoLoaded: ustpLogoLoaded
    };
    
    // Function to add header on each page
    function addHeaderToPage(doc, data) {
      const pageWidth = doc.internal.pageSize.width;
      const centerPosition = pageWidth / 2;
      
      // Add logos
      if (headerInfo.logo1Loaded) {
        doc.addImage(headerInfo.logo1Img, 'PNG', 14, 10, 25, 25);
      }
      if (headerInfo.ustpLogoLoaded) {
        doc.addImage(headerInfo.ustpLogoImg, 'PNG', pageWidth - 39, 10, 25, 25);
      }
      
      // Add university name
      doc.setFontSize(16);
      doc.setFont(undefined, 'bold');
      doc.text("University of Science and Technology", centerPosition, 18, { align: 'center' });
      doc.text("of Southern Philippines", centerPosition, 25, { align: 'center' });
      
      doc.setFontSize(14);
      doc.setFont(undefined, 'normal');
      doc.text("Library Attendance Report", centerPosition, 33, { align: 'center' });
      
      // Don't add summary info on subsequent pages to avoid empty space
    }
    
    // Convert table to PDF
    // Start table after the summary info
    doc.autoTable({ 
      head: [['#', 'Student ID', 'Student Name', 'Sex', 'Course', 'Department', 'Time In', 'Section', 'Date']],
      body: tableData,
      startY: startY + 21, // Start after the summary info (45 + 14 + 7 = 66, so 21 more)
      headStyles: { fillColor: [26, 24, 81] },
      styles: { fontSize: 8 },
      margin: { top: startY + 21 },
      didDrawPage: function(data) {
        addHeaderToPage(doc, data);
      }
    });
    
    doc.save("attendance_report.pdf");
  }
  
  // Handle image loading
  logo1Img.onload = function() {
    logo1Loaded = true;
    if (ustpLogoLoaded) {
      createPdfContent();
    }
  };
  
  ustpLogoImg.onload = function() {
    ustpLogoLoaded = true;
    if (logo1Loaded) {
      createPdfContent();
    }
  };
  
  // Handle image loading errors
  logo1Img.onerror = function() {
    logo1Loaded = false;
    if (ustpLogoLoaded) {
      createPdfContent();
    }
  };
  
  ustpLogoImg.onerror = function() {
    ustpLogoLoaded = false;
    if (logo1Loaded) {
      createPdfContent();
    }
  };
  
  // Fallback in case both images fail to load
  setTimeout(() => {
    if (!logo1Loaded && !ustpLogoLoaded) {
      createPdfContent();
    }
  }, 3000);
}
