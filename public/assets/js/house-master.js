// house-master.js — house master dashboard helpers (mostly relies on the
// shared admin/attendance/rooms pages via redirects; this covers dashboard polish).
document.addEventListener('DOMContentLoaded', function () {
  // Warn if open incidents exist, as a gentle nudge from the dashboard
  const incidentStat = document.querySelector('.stat-icon.bg-danger')?.closest('.stat-card');
  if (incidentStat) {
    const count = incidentStat.querySelector('.fs-4')?.textContent.trim();
    if (count && parseInt(count, 10) > 0) incidentStat.classList.add('border-danger');
  }
});
