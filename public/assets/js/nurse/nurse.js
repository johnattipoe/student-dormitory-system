// nurse.js — nurse dashboard and medical-record modal helpers.
function initializeNurseStudentOptions() {
  const selects = Array.from(document.querySelectorAll('select[data-nurse-student-options]'));
  if (!selects.length) return;

  const loadStudentOptions = function () {
    const pendingSelects = selects.filter(function (select) {
      return select.dataset.sdsStudentsLoaded !== 'true' && select.dataset.sdsStudentsLoading !== 'true';
    });
    if (!pendingSelects.length) return;

    pendingSelects.forEach(function (select) {
      select.dataset.sdsStudentsLoading = 'true';
      select.disabled = true;
    });

    if (!window.SDS.nurseStudentOptionsPromise) {
      window.SDS.nurseStudentOptionsPromise = fetch(pendingSelects[0].dataset.nurseStudentOptions, {
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
          window.SDS.nurseStudentOptionsPromise = null;
          throw error;
        });
    }

    window.SDS.nurseStudentOptionsPromise
      .then(function (students) {
        pendingSelects.forEach(function (select) {
          const selectedValue = select.value || select.dataset.selectedStudentId || '';
          const placeholder = new Option('Select student', '');
          select.replaceChildren(placeholder);
          students.forEach(function (student) {
            const option = new Option(student.label, student.id);
            option.dataset.guardianName = student.guardianName || '';
            option.dataset.guardianPhone = student.guardianPhone || '';
            select.add(option);
          });
          select.value = selectedValue;
          select.dataset.sdsStudentsLoaded = 'true';
          delete select.dataset.sdsStudentsLoading;
          select.disabled = false;
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });
      })
      .catch(function (error) {
        pendingSelects.forEach(function (select) {
          delete select.dataset.sdsStudentsLoading;
          select.disabled = false;
        });
        console.error('Unable to load nurse dashboard student options:', error);
        SDS.toast('error', 'Student options could not be loaded. Please try again.');
      });
  };

  selects.forEach(function (select) {
    select.addEventListener('focus', loadStudentOptions);
    const modal = select.closest('.modal');
    if (modal && modal.dataset.sdsStudentOptionsBound !== 'true') {
      modal.dataset.sdsStudentOptionsBound = 'true';
      modal.addEventListener('show.bs.modal', loadStudentOptions);
    }
  });
  document.querySelectorAll('[data-nurse-student-search]').forEach(function (searchInput) {
    searchInput.addEventListener('focus', loadStudentOptions);
  });

  if (selects.some(function (select) { return select.dataset.selectedStudentId; })) {
    loadStudentOptions();
  }
}

SDS.onPageLoad(function () {
  initializeNurseStudentOptions();
  document.querySelectorAll('.stat-card .text-danger').forEach(el => {
    const card = el.closest('.stat-card');
    if (card && parseInt(el.textContent, 10) > 0) {
      card.classList.add('border-danger');
    }
  });
});

SDS.onPageRestore(initializeNurseStudentOptions);
