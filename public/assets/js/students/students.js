// students.js — client-side helpers for the students list/create/edit screens.
document.addEventListener('DOMContentLoaded', function () {
  // Live-filter the students table by name/admission no. as a lightweight
  // alternative to DataTables' built-in search box (kept in sync with it).
  const quickSearch = document.getElementById('studentQuickSearch');
  const table = document.querySelector('table.data-table');
  if (quickSearch && table) {
    quickSearch.addEventListener('input', function () {
      if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(table)) {
        jQuery(table).DataTable().search(this.value).draw();
      } else {
        const term = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
      }
    });
  }

  // Admission number: uppercase-as-you-type for consistency
  const admissionInput = document.querySelector('input[name="admissionNo"]');
  if (admissionInput) {
    admissionInput.addEventListener('input', function () {
      const pos = this.selectionStart;
      this.value = this.value.toUpperCase();
      this.setSelectionRange(pos, pos);
    });
  }

  // Create/edit form: keep Course + Level select2 fields in sync visually (no-op hook
  // kept simple since course/level are free-text inputs in this build).

  // Print button on the student profile view, if present
  const printBtn = document.getElementById('printProfile');
  if (printBtn) printBtn.addEventListener('click', () => window.print());
});
