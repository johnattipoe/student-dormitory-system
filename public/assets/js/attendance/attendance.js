// attendance.js — bulk mark-all-present/absent helpers and an unsaved-changes
// guard for the "Mark Attendance" screen (public/views/admin/attendance/index/index.php).
let attendanceBeforeUnloadHandler = null;

function initializeAttendanceStudentOptions() {
  const selects = Array.from(document.querySelectorAll('select[data-attendance-student-options]'));
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

    if (!window.SDS.attendanceStudentOptionsPromise) {
      window.SDS.attendanceStudentOptionsPromise = fetch(pendingSelects[0].dataset.attendanceStudentOptions, {
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
          window.SDS.attendanceStudentOptionsPromise = null;
          throw error;
        });
    }

    window.SDS.attendanceStudentOptionsPromise
      .then(function (students) {
        pendingSelects.forEach(function (select) {
          const selectedValue = select.value || select.dataset.selectedStudentId || '';
          select.replaceChildren(new Option('Select student', ''));
          students.forEach(function (student) {
            select.add(new Option(student.label, student.id));
          });
          select.value = selectedValue;
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
        console.error('Unable to load attendance student options:', error);
        SDS.toast('error', 'Student options could not be loaded. Please try again.');
      });
  };

  selects.forEach(function (select) {
    select.addEventListener('focus', loadStudentOptions);
    const modal = select.closest('.modal');
    if (modal && modal.dataset.attendanceStudentOptionsBound !== 'true') {
      modal.dataset.attendanceStudentOptionsBound = 'true';
      modal.addEventListener('show.bs.modal', loadStudentOptions);
    }
  });
}

SDS.onPageLoad(function () {
  initializeAttendanceStudentOptions();
  if (attendanceBeforeUnloadHandler) {
    window.removeEventListener('beforeunload', attendanceBeforeUnloadHandler);
    attendanceBeforeUnloadHandler = null;
  }

  const form = document.querySelector('form.attendance-form');
  if (!form) return;

  let dirty = false;
  form.addEventListener('change', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  attendanceBeforeUnloadHandler = function (e) {
    if (dirty && form.isConnected) { e.preventDefault(); e.returnValue = ''; }
  };
  window.addEventListener('beforeunload', attendanceBeforeUnloadHandler);

  // "Mark all as X" buttons — id pattern: markAll-present / markAll-absent / markAll-late / markAll-excused
  document.querySelectorAll('[id^="markAll-"]').forEach(btn => {
    btn.addEventListener('click', function () {
      const status = this.id.replace('markAll-', '');
      form.querySelectorAll(`input[type="radio"][value="${status}"]`).forEach(radio => {
        radio.checked = true;
      });
      dirty = true;
      SDS.toast('info', `Marked everyone as ${status}.`);
    });
  });

  // Live summary counts as the user clicks through radios
  const summaryEl = document.getElementById('attendanceSummary');
  function updateSummary() {
    if (!summaryEl) return;
    const counts = { present: 0, absent: 0, late: 0, excused: 0 };
    form.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
      if (counts[radio.value] !== undefined) counts[radio.value]++;
    });
    summaryEl.textContent = `Present: ${counts.present} · Absent: ${counts.absent} · Late: ${counts.late} · Excused: ${counts.excused}`;
  }
  form.addEventListener('change', updateSummary);
  updateSummary();
});

SDS.onPageRestore(initializeAttendanceStudentOptions);
