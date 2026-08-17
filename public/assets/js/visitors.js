// visitors.js — visitors list helpers: quick phone/name search and a
// live "who's on premises" refresh where the security dashboard shows it.
document.addEventListener('DOMContentLoaded', function () {
  const search = document.getElementById('visitorQuickSearch');
  const table = document.querySelector('table.data-table');
  if (search && table && window.jQuery && jQuery.fn.DataTable) {
    search.addEventListener('input', function () {
      jQuery(table).DataTable().search(this.value).draw();
    });
  }

  // Format phone inputs as the user types (light touch — just strips non-digits, keeps it simple)
  document.querySelectorAll('input[name="phone"], input[name="visitorPhone"]').forEach(input => {
    input.addEventListener('blur', function () {
      this.value = this.value.replace(/[^\d+\-\s]/g, '').trim();
    });
  });
});
