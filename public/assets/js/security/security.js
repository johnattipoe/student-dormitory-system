// security.js — security dashboard/check-in-out helpers.
function initializeSecurityStudentOptions() {
  const selects = Array.from(document.querySelectorAll('select[data-security-student-options]'));
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

    if (!window.SDS.securityStudentOptionsPromise) {
      const endpoint = selects[0].dataset.securityStudentOptions;
      window.SDS.securityStudentOptionsPromise = fetch(endpoint, {
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
          window.SDS.securityStudentOptionsPromise = null;
          throw error;
        });
    }

    window.SDS.securityStudentOptionsPromise
      .then(function (students) {
        pendingSelects.forEach(function (select) {
          const placeholder = select.querySelector('option[value=""]');
          const placeholderOption = placeholder
            ? placeholder.cloneNode(true)
            : new Option('Select a student', '');
          select.replaceChildren(placeholderOption);
          students.forEach(function (student) {
            select.add(new Option(student.label, student.id));
          });
          select.dataset.sdsStudentsLoaded = 'true';
          delete select.dataset.sdsStudentsLoading;
          select.disabled = false;
        });
      })
      .catch(function (error) {
        pendingSelects.forEach(function (select) {
          delete select.dataset.sdsStudentsLoading;
          select.disabled = false;
        });
        console.error('Unable to load security dashboard student options:', error);
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
}

SDS.onPageLoad(function () {
  initializeSecurityStudentOptions();

  // Check-in / check-out select2 dropdowns: focus immediately for fast scanning workflows
  const firstSelect = document.querySelector('select.select2');
  if (firstSelect && window.jQuery) {
    setTimeout(() => jQuery(firstSelect).select2('open'), 300);
  }
});

SDS.onPageRestore(initializeSecurityStudentOptions);
