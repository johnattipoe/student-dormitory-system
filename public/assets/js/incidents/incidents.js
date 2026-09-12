// incidents.js — report/view screen helpers: live severity preview and a
// simple character counter on the description field.
document.addEventListener('DOMContentLoaded', function () {
  const severitySelect = document.querySelector('select[name="severity"]');
  const preview = document.getElementById('severityPreview');
  const severityColors = { low: 'secondary', medium: 'info', high: 'warning', critical: 'danger' };

  function renderPreview() {
    if (!severitySelect || !preview) return;
    const val = severitySelect.value;
    preview.className = `badge bg-${severityColors[val] || 'secondary'} text-capitalize`;
    preview.textContent = val;
  }
  if (severitySelect) {
    severitySelect.addEventListener('change', renderPreview);
    renderPreview();
  }

  const description = document.querySelector('textarea[name="description"]');
  const counter = document.getElementById('descriptionCounter');
  if (description && counter) {
    const update = () => { counter.textContent = `${description.value.length} characters`; };
    description.addEventListener('input', update);
    update();
  }

  // Status select on the incident detail/update form: warn before marking closed.
  const statusSelect = document.querySelector('select[name="status"]');
  const statusForm = statusSelect ? statusSelect.closest('form') : null;
  if (statusSelect && statusForm) {
    statusForm.addEventListener('submit', function (e) {
      if (statusSelect.value === 'closed') {
        const proceed = confirm('Mark this incident as closed? Make sure resolution notes are filled in.');
        if (!proceed) e.preventDefault();
      }
    });
  }
});
