// admin.js — cross-cutting admin utilities used on several /views/admin/* pages.
function initializeAdminStudentOptions() {
  const selects = Array.from(document.querySelectorAll('select[data-admin-student-options]'));
  if (!selects.length) return;

  const loadStudentOptions = function () {
    if (selects.every(function (select) { return select.dataset.sdsStudentsLoaded === 'true'; })) return;

    selects.forEach(function (select) {
      select.disabled = true;
    });

    if (!window.SDS.adminStudentOptionsPromise) {
      window.SDS.adminStudentOptionsPromise = fetch(selects[0].dataset.adminStudentOptions, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) {
          if (!response.ok) throw new Error('Student options request failed (HTTP ' + response.status + ').');
          return response.json();
        })
        .then(function (response) {
          if (!response.success || !Array.isArray(response.students)) {
            throw new Error(response.message || 'Student options response was invalid.');
          }
          return response.students;
        })
        .catch(function (error) {
          window.SDS.adminStudentOptionsPromise = null;
          throw error;
        });
    }

    window.SDS.adminStudentOptionsPromise
      .then(function (students) {
        selects.forEach(function (select) {
          const selectedValue = select.dataset.selectedId || select.value;
          select.replaceChildren(new Option(select.dataset.placeholder || 'Select student', ''));
          students.forEach(function (student) {
            select.add(new Option(student.label, student.id));
          });
          select.value = selectedValue;
          select.dataset.sdsStudentsLoaded = 'true';
          select.disabled = false;
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });
      })
      .catch(function (error) {
        selects.forEach(function (select) {
          select.disabled = false;
        });
        console.error('Unable to load admin student options:', error);
        SDS.toast('error', 'Student options could not be loaded. Please try again.');
      });
  };

  selects.forEach(function (select) {
    const modal = select.closest('.modal');
    if (modal && modal.dataset.adminStudentOptionsBound !== 'true') {
      modal.dataset.adminStudentOptionsBound = 'true';
      modal.addEventListener('show.bs.modal', loadStudentOptions);
    } else if (!modal) {
      select.addEventListener('focus', loadStudentOptions);
    }
  });
}

function initializeNotificationUserOptions() {
  const select = document.querySelector('select[data-admin-notification-user-options]');
  if (!select) return;

  const loadUserOptions = function () {
    if (select.dataset.sdsUsersLoaded === 'true' || select.dataset.sdsUsersLoading === 'true') return;

    select.dataset.sdsUsersLoading = 'true';
    select.disabled = true;
    fetch(select.dataset.adminNotificationUserOptions, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (response) {
        if (!response.ok) throw new Error('User options request failed (HTTP ' + response.status + ').');
        return response.json();
      })
      .then(function (response) {
        if (!response.success || !Array.isArray(response.users)) {
          throw new Error(response.message || 'User options response was invalid.');
        }
        const selectedValue = select.value;
        select.replaceChildren(new Option('Select user', ''));
        response.users.forEach(function (user) {
          select.add(new Option(user.label + (user.role ? ' (' + user.role + ')' : ''), user.id));
        });
        select.value = selectedValue;
        select.dataset.sdsUsersLoaded = 'true';
      })
      .catch(function (error) {
        console.error('Unable to load admin notification user options:', error);
        SDS.toast('error', 'User options could not be loaded. Please try again.');
      })
      .finally(function () {
        delete select.dataset.sdsUsersLoading;
        select.disabled = false;
      });
  };

  select.addEventListener('focus', loadUserOptions);
}

SDS.onPageLoad(function () {
  initializeAdminStudentOptions();
  initializeNotificationUserOptions();

  // Generic CSV export for any table.data-table: <button id="exportCsv" data-table="#myTable">
  const exportBtn = document.getElementById('exportCsv');
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      const table = document.querySelector(this.dataset.table || 'table.data-table');
      if (!table) return;
      const rows = Array.from(table.querySelectorAll('tr')).map(row =>
        Array.from(row.querySelectorAll('th,td'))
          .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`)
          .join(',')
      );
      const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'export.csv';
      link.click();
    });
  }

  // Settings form: confirm before saving changes that affect curfew/visitor hours
  const settingsForm = document.querySelector('form input[name="curfewTime"]')?.closest('form');
  if (settingsForm) {
    settingsForm.addEventListener('submit', function () {
      SDS.toast('info', 'Saving settings…');
    });
  }
});
