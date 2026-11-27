// house-master.js — house master dashboard helpers (mostly relies on the
// shared admin/attendance/rooms pages via redirects; this covers dashboard polish).
function initializeHouseMasterDashboard() {
  // Warn if open incidents exist, as a gentle nudge from the dashboard
  const incidentStat = document.querySelector('.stat-icon.bg-danger')?.closest('.stat-card');
  if (incidentStat) {
    const count = incidentStat.querySelector('.fs-4')?.textContent.trim();
    if (count && parseInt(count, 10) > 0) incidentStat.classList.add('border-danger');
  }
}

function initializeHouseStudentOptions() {
  const selects = Array.from(document.querySelectorAll('select[data-house-student-options]'));
  if (!selects.length) return;

  const loadStudentOptions = function () {
    const select = selects[0];
    if (select.dataset.sdsStudentsLoaded === 'true' || select.dataset.sdsStudentsLoading === 'true') return;

    const submitButton = select.form ? select.form.querySelector('[data-house-student-options-submit]') : null;
    select.dataset.sdsStudentsLoading = 'true';
    select.disabled = true;
    if (submitButton) submitButton.disabled = true;
    fetch(select.dataset.houseStudentOptions, {
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

        const selectedValue = select.dataset.selectedId || select.value;
        select.replaceChildren(new Option(select.dataset.placeholder || 'Select resident student', ''));
        response.students.forEach(function (student) {
          select.add(new Option(student.label, student.id));
        });
        select.value = selectedValue;
        if (!response.students.length) {
          select.add(new Option('No students assigned to your house', '', true, true));
        }
        select.dataset.sdsStudentsLoaded = 'true';
        select.disabled = response.students.length === 0;
        if (submitButton) submitButton.disabled = response.students.length === 0 || !select.value;
      })
      .catch(function (error) {
        select.disabled = false;
        if (submitButton) submitButton.disabled = true;
        console.error('Unable to load house student options:', error);
        SDS.toast('error', 'Student options could not be loaded. Please try again.');
      })
      .finally(function () {
        select.dataset.sdsStudentsLoading = 'false';
      });
  };

  selects.forEach(function (select) {
    const submitButton = select.form ? select.form.querySelector('[data-house-student-options-submit]') : null;
    if (submitButton) {
      select.addEventListener('change', function () {
        if (select.dataset.sdsStudentsLoaded === 'true') {
          submitButton.disabled = !select.value;
        }
      });
    }

    const modal = select.closest('.modal');
    if (modal && modal.dataset.houseStudentOptionsBound !== 'true') {
      modal.dataset.houseStudentOptionsBound = 'true';
      modal.addEventListener('show.bs.modal', loadStudentOptions);
    } else if (!modal) {
      select.addEventListener('focus', loadStudentOptions);
    }
  });
}

SDS.onPageLoad(initializeHouseMasterDashboard);
SDS.onPageLoad(initializeHouseStudentOptions);
