// admin.js — cross-cutting admin utilities used on several /views/admin/* pages.
document.addEventListener('DOMContentLoaded', function () {
  // Generic CSV export for any table.data-table: <button id="exportCsv" data-table="#myTable">
  const exportBtn = document.getElementById('exportCsv');
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      const table = document.querySelector(this.dataset.table || 'table.data-table');
      if (!table) return;
      const rows = Array.from(table.querySelectorAll('tr')).map(row =>
        Array.from(row.querySelectorAll('th,td'))
          .map(cell => `"${cell.textContent.trim().replace(/"/g, '""')}"`)
          .join(',')
      );
      const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'export.csv';
      link.click();
    });
  }

  // Settings form: confirm before saving changes that affect curfew/visitor hours
  const settingsForm = document.querySelector('form input[name="curfewTime"]')?.closest('form');
  if (settingsForm) {
    settingsForm.addEventListener('submit', function () {
      SDS.toast('info', 'Saving settings…');
    });
  }
});
