// medical.js — nurse medical-record form helpers: emergency toggle styling
// and a confirmation step before saving a record marked as an emergency.
document.addEventListener('DOMContentLoaded', function () {
  const emergencyCheckbox = document.getElementById('isEmergency');
  const form = emergencyCheckbox ? emergencyCheckbox.closest('form') : null;

  function syncEmergencyStyle() {
    if (!emergencyCheckbox) return;
    const label = document.querySelector('label[for="isEmergency"]');
    if (label) label.closest('.col-12')?.classList.toggle('bg-danger', false); // reserved for future styling
    document.body.classList.toggle('emergency-armed', emergencyCheckbox.checked);
  }

  if (emergencyCheckbox) {
    emergencyCheckbox.addEventListener('change', syncEmergencyStyle);
    syncEmergencyStyle();
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      if (emergencyCheckbox && emergencyCheckbox.checked) {
        const proceed = confirm('This record is flagged as an EMERGENCY case. Continue saving?');
        if (!proceed) e.preventDefault();
      }
    });
  }

  // Highlight overdue follow-up dates in the medical records table (data-followup="Y-m-d" on <tr>)
  const today = new Date().toISOString().slice(0, 10);
  document.querySelectorAll('tr[data-followup]').forEach(row => {
    if (row.dataset.followup && row.dataset.followup < today) {
      row.classList.add('table-warning');
    }
  });
});
