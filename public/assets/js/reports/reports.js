// reports.js — reusable chart bootstrapper for report pages. Any element like:
//   <canvas class="report-chart" data-type="bar" data-labels="A,B,C" data-values="1,2,3"></canvas>
// gets turned into a Chart.js chart automatically. Also wires up print buttons.
SDS.initializeReportCharts = function (root) {
  if (!window.Chart) return;
  root.querySelectorAll('canvas.report-chart').forEach(canvas => {
    if (canvas.dataset.sdsReportChartInitialized === 'true') return;
    if (typeof Chart.getChart === 'function' && Chart.getChart(canvas)) {
      canvas.dataset.sdsReportChartInitialized = 'true';
      return;
    }

    const type = canvas.dataset.type || 'bar';
    const labels = (canvas.dataset.labels || '').split(',').filter(Boolean);
    const values = (canvas.dataset.values || '').split(',').filter(Boolean).map(Number);
    const label = canvas.dataset.label || 'Total';

    new Chart(canvas, {
      type,
      data: { labels, datasets: [{ label, data: values, backgroundColor: '#2f5fbb' }] },
      options: { responsive: true, plugins: { legend: { display: type === 'doughnut' || type === 'pie' } } },
    });
    canvas.dataset.sdsReportChartInitialized = 'true';
  });
};

function initializeReportPage() {
  SDS.initializeReportCharts(document);
  document.querySelectorAll('.btn-print').forEach(btn => {
    if (btn.dataset.sdsPrintBound === 'true') return;
    btn.dataset.sdsPrintBound = 'true';
    btn.addEventListener('click', () => window.print());
  });
}

SDS.onPageLoad(initializeReportPage);
SDS.onPageRestore(() => SDS.initializeReportCharts(document));
