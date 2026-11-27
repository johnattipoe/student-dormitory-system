// dashboard.js — renders the admin dashboard chart from the page's metrics.
function initializeAdminDashboard() {
    var chartCanvas = document.getElementById('dashboardChart');
    if (!chartCanvas || chartCanvas.dataset.metricsLoaded === 'true') {
        return;
    }

    var metrics;
    try {
        metrics = JSON.parse(chartCanvas.dataset.metrics || '{}');
    } catch (error) {
        console.error('Admin dashboard metrics could not be parsed:', error);
        return;
    }

    if (!metrics || typeof metrics !== 'object') {
        console.error('Admin dashboard metrics were missing or malformed.');
        return;
    }

    var metricKeys = ['students', 'houses', 'rooms', 'incidents', 'attendance', 'allocations', 'activityLogs', 'notifications'];
    var labels = metricKeys.map(function (key) {
        return (key === 'activityLogs' ? 'Activity Logs' : key)
            .replace(/([A-Z])/g, ' $1')
            .replace(/^./, function (letter) { return letter.toUpperCase(); });
    });
    var values = metricKeys.map(function (key) {
        return Number(metrics[key] || 0);
    });

    new Chart(chartCanvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Live totals',
                data: values,
                backgroundColor: [
                    'rgba(13, 110, 253, 0.7)',
                    'rgba(25, 135, 84, 0.7)',
                    'rgba(13, 202, 240, 0.7)',
                    'rgba(220, 53, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(108, 117, 125, 0.7)',
                    'rgba(134, 95, 255, 0.7)',
                    'rgba(13, 110, 253, 0.3)'
                ],
                borderColor: [
                    'rgba(13, 110, 253, 1)',
                    'rgba(25, 135, 84, 1)',
                    'rgba(13, 202, 240, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(134, 95, 255, 1)',
                    'rgba(13, 110, 253, 0.6)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { drawBorder: false },
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
    chartCanvas.dataset.metricsLoaded = 'true';
}

SDS.onPageLoad(initializeAdminDashboard);
