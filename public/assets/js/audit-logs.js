document.addEventListener('DOMContentLoaded', () => {
  const BASE_URL = window.BASE_URL || '';
  let currentPage = 1;
  const itemsPerPage = 50;
  let totalPages = 1;
  let filterTimeout = null;
  let refreshInterval = null;

  // Initialize
  loadFilters();
  loadAuditLogs();
  
  // Auto-refresh audit logs every 2 minutes (120000 milliseconds)
  refreshInterval = setInterval(loadAuditLogs, 120000);
  
  // Clean up interval when page is hidden (saves resources)
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
      }
    } else {
      if (!refreshInterval) {
        refreshInterval = setInterval(loadAuditLogs, 120000);
      }
      // Reload when page becomes visible again to ensure fresh data
      loadAuditLogs();
    }
  });

  // Filter button
  const filterBtn = document.getElementById('filterBtn');
  if (filterBtn) {
    filterBtn.addEventListener('click', () => {
      currentPage = 1;
      loadAuditLogs();
    });
  }

  // Reset filters
  const resetFiltersBtn = document.getElementById('resetFiltersBtn');
  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
      resetAllFilters();
    });
  }

  // Auto-filter on select change (with debounce for better performance)
  const actionFilter = document.getElementById('actionFilter');
  const entityFilter = document.getElementById('entityFilter');
  const userFilter = document.getElementById('userFilter');
  
  if (actionFilter) {
    actionFilter.addEventListener('change', () => {
      currentPage = 1;
      debounceFilter();
    });
  }

  if (entityFilter) {
    entityFilter.addEventListener('change', () => {
      currentPage = 1;
      debounceFilter();
    });
  }

  if (userFilter) {
    userFilter.addEventListener('change', () => {
      currentPage = 1;
      debounceFilter();
    });
  }

  // Date filters - do not apply on change, only on filter button click
  const startDate = document.getElementById('startDate');
  const endDate = document.getElementById('endDate');
  // Removed automatic filtering on date change

  // Enter key support for date inputs
  if (startDate) {
    startDate.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        currentPage = 1;
        loadAuditLogs();
      }
    });
  }

  if (endDate) {
    endDate.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        currentPage = 1;
        loadAuditLogs();
      }
    });
  }

  // Debounce function to avoid too many API calls
  function debounceFilter() {
    if (filterTimeout) {
      clearTimeout(filterTimeout);
    }
    filterTimeout = setTimeout(() => {
      loadAuditLogs();
    }, 300); // Wait 300ms after last change
  }

  // Reset all filters function
  function resetAllFilters() {
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    const actionFilterEl = document.getElementById('actionFilter');
    const entityFilterEl = document.getElementById('entityFilter');
    const userFilterEl = document.getElementById('userFilter');

    if (startDateEl) {
      startDateEl.value = '';
      startDateEl.style.borderColor = '#1A1851';
    }
    if (endDateEl) {
      endDateEl.value = '';
      endDateEl.style.borderColor = '#1A1851';
    }
    if (actionFilterEl) {
      actionFilterEl.value = '';
      actionFilterEl.style.borderColor = '#1A1851';
    }
    if (entityFilterEl) {
      entityFilterEl.value = '';
      entityFilterEl.style.borderColor = '#1A1851';
    }
    if (userFilterEl) {
      userFilterEl.value = '';
      userFilterEl.style.borderColor = '#1A1851';
    }
    
    currentPage = 1;
    loadAuditLogs();
    
    // Restart auto-refresh interval
    if (refreshInterval) {
      clearInterval(refreshInterval);
    }
    refreshInterval = setInterval(loadAuditLogs, 120000);
  }

  // Pagination
  const prevPageBtn = document.getElementById('prevPageAudit');
  const nextPageBtn = document.getElementById('nextPageAudit');

  if (prevPageBtn) {
    prevPageBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        loadAuditLogs();
      }
    });
  }

  if (nextPageBtn) {
    nextPageBtn.addEventListener('click', () => {
      if (currentPage < totalPages) {
        currentPage++;
        loadAuditLogs();
      }
    });
  }

  function loadFilters() {
    fetch(`${BASE_URL}/superadmin/audit-logs-filters`)
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        if (data.success) {
          // Populate actions
          const actionFilter = document.getElementById('actionFilter');
          if (actionFilter && data.actions && data.actions.length > 0) {
            // Clear existing options except "All Actions"
            while (actionFilter.children.length > 1) {
              actionFilter.removeChild(actionFilter.lastChild);
            }
            data.actions.forEach(action => {
              const option = document.createElement('option');
              option.value = action;
              option.textContent = action.replace(/_/g, ' ');
              actionFilter.appendChild(option);
            });
          }

          // Populate entity types
          const entityFilter = document.getElementById('entityFilter');
          if (entityFilter && data.entity_types && data.entity_types.length > 0) {
            // Clear existing options except "All Entities"
            while (entityFilter.children.length > 1) {
              entityFilter.removeChild(entityFilter.lastChild);
            }
            data.entity_types.forEach(entity => {
              const option = document.createElement('option');
              option.value = entity;
              option.textContent = entity;
              entityFilter.appendChild(option);
            });
          }

          // Populate users
          const userFilter = document.getElementById('userFilter');
          if (userFilter && data.users && data.users.length > 0) {
            // Clear existing options except "All Users"
            while (userFilter.children.length > 1) {
              userFilter.removeChild(userFilter.lastChild);
            }
            data.users.forEach(user => {
              const option = document.createElement('option');
              option.value = user.user_id;
              
              // Format display text based on user type
              let displayText = `${user.full_name || 'Unknown'}`;
              if (user.user_type === 'Student') {
                displayText += ` (Student #${user.email || 'N/A'})`;
              } else {
                displayText += ` (${user.email || 'N/A'})`;
              }
              
              option.textContent = displayText;
              userFilter.appendChild(option);
            });
          }
        } else {
          console.error('Failed to load filters:', data.message);
        }
      })
      .catch(err => {
        console.error('Error loading filters:', err);
        // Show user-friendly error message
        const actionFilter = document.getElementById('actionFilter');
        if (actionFilter) {
          const errorOption = document.createElement('option');
          errorOption.value = '';
          errorOption.textContent = 'Error loading filters';
          errorOption.disabled = true;
          actionFilter.appendChild(errorOption);
        }
      });
  }

  function loadAuditLogs() {
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    const actionFilterEl = document.getElementById('actionFilter');
    const entityFilterEl = document.getElementById('entityFilter');
    const userFilterEl = document.getElementById('userFilter');

    const startDate = startDateEl?.value || '';
    const endDate = endDateEl?.value || '';
    const action = actionFilterEl?.value || '';
    const entityType = entityFilterEl?.value || '';
    const userId = userFilterEl?.value || '';

    // Validate date range
    if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
      alert('Start date cannot be after end date. Please correct the date range.');
      return;
    }

    const params = new URLSearchParams();
    params.append('page', currentPage);
    params.append('limit', itemsPerPage);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (action) params.append('action', action);
    if (entityType) params.append('entity_type', entityType);
    if (userId) params.append('user_id', userId);

    const tbody = document.getElementById('auditLogsBody');
    const filterBtn = document.getElementById('filterBtn');
    
    // Show loading state
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #888; padding: 40px;"><div style="display: inline-block;">Loading...</div></td></tr>';
    }
    
    if (filterBtn) {
      filterBtn.disabled = true;
      filterBtn.style.opacity = '0.6';
      filterBtn.style.cursor = 'not-allowed';
    }

    fetch(`${BASE_URL}/superadmin/audit-logs-data?${params.toString()}`)
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        if (data.success) {
          totalPages = data.total_pages || 1;
          renderAuditLogs(data.logs || [], data.total || 0);
          updatePagination(data.page || currentPage, data.total_pages || 1);
          
          // Show filter count if filters are active
          showActiveFilterCount(startDate, endDate, action, entityType, userId);
        } else {
          if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #e74c3c; padding: 40px;">${data.message || 'Error loading logs'}</td></tr>`;
          }
        }
      })
      .catch(err => {
        console.error('Error loading audit logs:', err);
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #e74c3c; padding: 40px;">Error loading audit logs. Please check your connection and try again.</td></tr>';
        }
      })
      .finally(() => {
        if (filterBtn) {
          filterBtn.disabled = false;
          filterBtn.style.opacity = '1';
          filterBtn.style.cursor = 'pointer';
        }
      });
  }

  // Show active filter count and visual feedback
  function showActiveFilterCount(startDate, endDate, action, entityType, userId) {
    let activeCount = 0;
    if (startDate) activeCount++;
    if (endDate) activeCount++;
    if (action) activeCount++;
    if (entityType) activeCount++;
    if (userId) activeCount++;

    // Add visual feedback to filter inputs when they have values
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    const actionFilterEl = document.getElementById('actionFilter');
    const entityFilterEl = document.getElementById('entityFilter');
    const userFilterEl = document.getElementById('userFilter');

    // Highlight active filters
    if (startDateEl) {
      startDateEl.style.borderColor = startDate ? '#FCB316' : '#1A1851';
    }
    if (endDateEl) {
      endDateEl.style.borderColor = endDate ? '#FCB316' : '#1A1851';
    }
    if (actionFilterEl) {
      actionFilterEl.style.borderColor = action ? '#FCB316' : '#1A1851';
    }
    if (entityFilterEl) {
      entityFilterEl.style.borderColor = entityType ? '#FCB316' : '#1A1851';
    }
    if (userFilterEl) {
      userFilterEl.style.borderColor = userId ? '#FCB316' : '#1A1851';
    }
  }

  function renderAuditLogs(logs, total) {
    const tbody = document.getElementById('auditLogsBody');
    const noData = document.getElementById('noDataMessage');

    if (!logs || logs.length === 0) {
      if (tbody) tbody.innerHTML = '';
      if (noData) noData.style.display = 'block';
      return;
    }

    if (noData) noData.style.display = 'none';
    
    if (tbody) {
      tbody.innerHTML = logs.map(log => {
        const actionClass = getActionClass(log.action);
        const hasChanges = log.old_values || log.new_values;
        const friendlyAction = formatActionLabel(log.action);
        const entityCellHtml = formatEntityCell(log);
        const descriptionText = formatDescription(log);

        return `
          <tr>
            <td>${escapeHtml(log.formatted_date || 'N/A')}</td>
            <td>
              <div><strong>${escapeHtml(log.user_name || 'System')}</strong></div>
              <div style="font-size: 12px; color: #666;">${escapeHtml(log.user_email || 'N/A')}</div>
              <div style="font-size: 11px; color: #999;">${escapeHtml(log.user_type || 'N/A')}</div>
            </td>
            <td><span class="action-badge ${actionClass}">${escapeHtml(friendlyAction)}</span></td>
            <td>${entityCellHtml}</td>
            <td>${escapeHtml(descriptionText)}</td>
            <td>${escapeHtml(log.ip_address || 'N/A')}</td>
            <td>
              ${hasChanges ? `<span class="details-toggle" onclick="showModal(${log.log_id})">View Changes</span>` : 'N/A'}
            </td>
          </tr>
        `;
      }).join('');
    }

    // Store change data for modal
    logs.forEach(log => {
      if (log.old_values || log.new_values) {
        window[`logData_${log.log_id}`] = {
          old: log.old_values,
          new: log.new_values
        };
      }
    });
  }

  function getActionClass(action) {
    if (!action) return 'action-other';
    const upperAction = action.toUpperCase();
    if (upperAction.includes('CREATE') || upperAction.includes('ADD')) return 'action-create';
    if (upperAction.includes('UPDATE') || upperAction.includes('EDIT')) return 'action-update';
    if (upperAction.includes('DELETE') || upperAction.includes('REMOVE')) return 'action-delete';
    if (upperAction.includes('LOGIN')) return 'action-login';
    if (upperAction.includes('LOGOUT')) return 'action-logout';
    return 'action-other';
  }

  function formatActionLabel(action) {
    if (!action) return 'Unknown Action';
    const key = action.toUpperCase();

    const map = {
      'CREATE_STUDENT': 'Created Student',
      'UPDATE_STUDENT': 'Updated Student',
      'DELETE_STUDENT': 'Deleted Student',
      'UPDATE_STUDENT_STATUS': 'Updated Student Status',
      'CREATE_ADMIN': 'Created Admin',
      'UPDATE_ADMIN': 'Updated Admin',
      'DELETE_ADMIN': 'Deleted Admin',
      'UPDATE_ADMIN_STATUS': 'Updated Admin Status',
      'CREATE_COURSE': 'Created Course',
      'UPDATE_COURSE': 'Updated Course',
      'DELETE_COURSE': 'Deleted Course',
      'CREATE_COLLEGE': 'Created College',
      'UPDATE_COLLEGE': 'Updated College',
      'DELETE_COLLEGE': 'Deleted College',
      'CREATE_SECTION': 'Created Section',
      'UPDATE_SECTION': 'Updated Section',
      'DELETE_SECTION': 'Deleted Section',
      'IMPORT_STUDENTS': 'Imported Students',
      'EXPORT_REPORTS': 'Exported Reports',
      'UPDATE_PROFILE': 'Updated Profile',
      'LOGIN': 'Login',
      'LOGOUT': 'Logout',
    };

    if (map[key]) return map[key];

    // Fallback: prettify the raw action string
    return action
      .toString()
      .toLowerCase()
      .split('_')
      .map(w => w.charAt(0).toUpperCase() + w.slice(1))
      .join(' ');
  }

  function formatEntityCell(log) {
    const type = (log.entity_type || '').toString();
    const id = log.entity_id;
    const oldVals = log.old_values || {};
    const newVals = log.new_values || {};

    const safe = (val) => escapeHtml(val ?? '');

    if (!type) {
      return '<span>N/A</span>';
    }

    // Helper to pull from new values, then old, then empty
    const fromNewOld = (key) => {
      if (newVals && newVals[key] !== undefined) return newVals[key];
      if (oldVals && oldVals[key] !== undefined) return oldVals[key];
      return '';
    };

    let main = safe(type);
    let meta = '';

    switch (type) {
      case 'Student': {
        const studNo = fromNewOld('student_number') || fromNewOld('number') || '';
        const name = fromNewOld('name') || `${fromNewOld('last_name') || 'N/A'}, ${fromNewOld('first_name') || 'N/A'}${fromNewOld('middle_initial') ? ' ' + fromNewOld('middle_initial') + '.' : ''}`.trim();
        const course = fromNewOld('course_code') || '';
        main = name ? `${safe(name)}` : 'Student';
        const parts = [];
        if (studNo) parts.push(`ID: ${safe(studNo)}`);
        if (course) parts.push(`Course: ${safe(course)}`);
        if (id) parts.push(`#${id}`);
        meta = parts.join(' • ');
        break;
      }
      case 'Admin': {
        const name = fromNewOld('full_name') || '';
        const email = fromNewOld('email') || '';
        const role = fromNewOld('role') || '';
        main = name ? `${safe(name)}` : 'Admin';
        const parts = [];
        if (email) parts.push(email);
        if (role) parts.push(role);
        if (id) parts.push(`Admin ID: ${id}`);
        meta = parts.map(safe).join(' • ');
        break;
      }
      case 'Course': {
        const code = fromNewOld('course_code') || '';
        const name = fromNewOld('course_name') || '';
        main = code ? `${safe(code)}` : 'Course';
        const parts = [];
        if (name) parts.push(name);
        if (id) parts.push(`Course ID: ${id}`);
        meta = parts.map(safe).join(' • ');
        break;
      }
      case 'College': {
        const code = fromNewOld('college_code') || '';
        const name = fromNewOld('college_name') || '';
        main = name ? `${safe(name)}` : 'College';
        const parts = [];
        if (code) parts.push(code);
        if (id) parts.push(`College ID: ${id}`);
        meta = parts.map(safe).join(' • ');
        break;
      }
      case 'Section': {
        const name = fromNewOld('section_name') || '';
        main = name ? `${safe(name)}` : 'Section';
        meta = id ? `Section ID: ${id}` : '';
        break;
      }
      case 'Report': {
        main = 'Report';
        meta = id ? `Report ID: ${id}` : '';
        break;
      }
      default: {
        main = safe(type);
        meta = id ? `ID: ${id}` : '';
      }
    }

    if (meta) {
      return `
        <div>
          <div><strong>${main}</strong></div>
          <div style="font-size: 12px; color: #666;">${meta}</div>
        </div>
      `;
    }

    return `<span><strong>${main}</strong>${id ? ' #' + safe(String(id)) : ''}</span>`;
  }

  function formatDescription(log) {
    if (log.description && log.description.trim() !== '') {
      return log.description;
    }

    // Fallback description built from action and entity when description is missing
    const friendlyAction = formatActionLabel(log.action || '');
    const type = log.entity_type || 'Item';
    const id = log.entity_id ? ` #${log.entity_id}` : '';

    const hasOld = log.old_values && Object.keys(log.old_values).length > 0;
    const hasNew = log.new_values && Object.keys(log.new_values).length > 0;

    if (hasOld || hasNew) {
      return `${friendlyAction} on ${type}${id} (details available in "View Changes")`;
    }

    return `${friendlyAction} on ${type}${id}`;
  }

  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function updatePagination(page, totalPages) {
    const pagination = document.getElementById('auditPagination');
    const prevBtn = document.getElementById('prevPageAudit');
    const nextBtn = document.getElementById('nextPageAudit');
    const pageInfo = document.getElementById('pageInfoAudit');

    if (totalPages > 1) {
      if (pagination) {
        pagination.style.display = 'flex';
        pagination.style.justifyContent = 'center';
        pagination.style.alignItems = 'center';
        pagination.style.gap = '15px';
      }
      if (prevBtn) {
        prevBtn.disabled = page === 1;
        prevBtn.style.cursor = page === 1 ? 'not-allowed' : 'pointer';
      }
      if (nextBtn) {
        nextBtn.disabled = page === totalPages;
        nextBtn.style.cursor = page === totalPages ? 'not-allowed' : 'pointer';
      }
      if (pageInfo) {
        pageInfo.textContent = `Page ${page} of ${totalPages}`;
        pageInfo.style.fontWeight = '600';
        pageInfo.style.color = '#1A1851';
      }
    } else {
      if (pagination) pagination.style.display = 'none';
    }
  }

  // Show modal with audit log details
  window.showModal = function(logId) {
    const data = window[`logData_${logId}`];
    if (!data) return;
    
    const modal = document.getElementById('changesModal');
    const modalBody = document.getElementById('modalBody');
    
    if (!modal || !modalBody) return;
    
    // Generate modal content
    let html = '';
    
    if (data.old) {
      html += '<div class="modal-change-item"><strong>Old Values:</strong>';
      Object.entries(data.old).forEach(([key, value]) => {
        html += `<span class="modal-old-value">${escapeHtml(key)}: ${escapeHtml(String(value))}</span>`;
      });
      html += '</div>';
    }
    
    if (data.new) {
      html += '<div class="modal-change-item"><strong>New Values:</strong>';
      Object.entries(data.new).forEach(([key, value]) => {
        html += `<span class="modal-new-value">${escapeHtml(key)}: ${escapeHtml(String(value))}</span>`;
      });
      html += '</div>';
    }
    
    if (!data.old && !data.new) {
      html = '<p>No detailed changes available for this log entry.</p>';
    }
    
    modalBody.innerHTML = html;
    modal.style.display = 'flex';
  };
  
  // Close modal when clicking the close button
  document.getElementById('closeModal')?.addEventListener('click', function() {
    const modal = document.getElementById('changesModal');
    if (modal) {
      modal.style.display = 'none';
    }
  });
  
  // Close modal when clicking outside the content
  document.getElementById('changesModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
      this.style.display = 'none';
    }
  });
});

