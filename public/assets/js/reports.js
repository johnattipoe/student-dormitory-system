// reports.js — reusable chart bootstrapper for report pages. Any element like:
//   <canvas class="report-chart" data-type="bar" data-labels="A,B,C" data-values="1,2,3"></canvas>
// gets turned into a Chart.js chart automatically. Also wires up print buttons.
document.addEventListener('DOMContentLoaded', function () {
  if (window.Chart) {
    document.querySelectorAll('canvas.report-chart').forEach(canvas => {
      const type = canvas.dataset.type || 'bar';
      const labels = (canvas.dataset.labels || '').split(',').filter(Boolean);
      const values = (canvas.dataset.values || '').split(',').filter(Boolean).map(Number);
      const label = canvas.dataset.label || 'Total';

      new Chart(canvas, {
        type,
        data: { labels, datasets: [{ label, data: values, backgroundColor: '#2f5fbb' }] },
        options: { responsive: true, plugins: { legend: { display: type === 'doughnut' || type === 'pie' } } },
      });
    });
  }

  document.querySelectorAll('.btn-print').forEach(btn => {
    btn.addEventListener('click', () => window.print());
  });
});
