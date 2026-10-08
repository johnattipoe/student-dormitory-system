// students.js — client-side helpers for the students list/create/edit screens.
document.addEventListener('DOMContentLoaded', function () {
  const filterForm = document.getElementById('studentListFilterForm');
  const filterInput = document.getElementById('studentSearchField');
  const filterButton = document.getElementById('studentFilterButton');
  const table = document.querySelector('table.data-table');

  const applyStudentTableFilter = () => {
    const term = (filterInput?.value ?? '').trim().toLowerCase();

    if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(table)) {
      jQuery(table).DataTable().search(term).draw();
      return;
    }

    if (!table) return;

    table.querySelectorAll('tbody tr').forEach(row => {
      const haystack = (row.textContent || '').toLowerCase();
      row.style.display = term === '' || haystack.includes(term) ? '' : 'none';
    });
  };

  if (filterForm && filterInput && filterButton) {
    filterButton.addEventListener('click', function (event) {
      event.preventDefault();
      applyStudentTableFilter();
    });

    filterInput.addEventListener('input', function () {
      applyStudentTableFilter();
    });

    filterInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyStudentTableFilter();
      }
    });

    filterForm.addEventListener('submit', function (event) {
      event.preventDefault();
      applyStudentTableFilter();
    });
  }

  // Live-filter the students table by name/admission no. as a lightweight
  // alternative to DataTables' built-in search box (kept in sync with it).
  const quickSearch = document.getElementById('studentQuickSearch');
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
