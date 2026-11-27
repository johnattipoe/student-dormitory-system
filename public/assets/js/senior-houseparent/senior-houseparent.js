// houseparent.js — progressively enhances the Visitor Requests approve/reject
// buttons to remove the row on success instead of a full page reload.
function initializeSeniorHouseparentPage() {
  document.querySelectorAll('form').forEach(form => {
    const hasDecisionButtons = form.querySelector('button[name="decision"]');
    if (!hasDecisionButtons) return;

    form.querySelectorAll('button[name="decision"]').forEach(btn => {
      btn.addEventListener('click', function (e) {
        // Let the normal POST happen (simplest, most reliable); just show
        // immediate feedback so the action doesn't feel unresponsive.
        SDS.toast('info', this.value === 'approve' ? 'Approving…' : 'Rejecting…');
      });
    });
  });
}

function initializeSeniorHouseparentStudentOptions() {
  const selects = Array.from(document.querySelectorAll('select[data-house-student-options]'));
  selects.forEach(function (select) {
    const submitButton = select.form ? select.form.querySelector('[data-house-student-options-submit]') : null;
    if (submitButton) {
      select.addEventListener('change', function () {
        if (select.dataset.sdsStudentsLoaded === 'true') {
          submitButton.disabled = !select.value;
        }
      });
    }

    const loadStudentOptions = function () {
      if (select.dataset.sdsStudentsLoaded === 'true' || select.dataset.sdsStudentsLoading === 'true') return;

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
          select.replaceChildren(new Option(select.dataset.placeholder || 'Select student', ''));
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

    const modal = select.closest('.modal');
    if (modal) {
      if (modal.dataset.houseStudentOptionsBound !== 'true') {
        modal.dataset.houseStudentOptionsBound = 'true';
        modal.addEventListener('show.bs.modal', loadStudentOptions);
      }
    } else {
      select.addEventListener('focus', loadStudentOptions);
    }
  });
}

SDS.onPageLoad(initializeSeniorHouseparentPage);
SDS.onPageLoad(initializeSeniorHouseparentStudentOptions);
