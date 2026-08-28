// attendance.js — bulk mark-all-present/absent helpers and an unsaved-changes
// guard for the "Mark Attendance" screen (public/views/admin/attendance/index/index.php).
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.attendance-form');
  if (!form) return;

  let dirty = false;
  form.addEventListener('change', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

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
