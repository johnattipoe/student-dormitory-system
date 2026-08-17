// dashboard.js — live-refreshes the admin dashboard's stat cards + chart
// from /views/admin/dashboard-metrics.php without a full page reload.
document.addEventListener('DOMContentLoaded', function () {
    var chartCanvas = document.getElementById('dashboardChart');
    if (!chartCanvas) {
        return;
    }

    var metricMap = {
        students: 'studentCount',
        houses: 'houseCount',
        rooms: 'roomCount',
        incidents: 'incidentCount',
        attendance: 'attendanceCount',
        allocations: 'allocationCount',
        activityLogs: 'activityLogCount',
        notifications: 'notificationCount'
    };

    // NOTE: this app doesn't have an index.php?route= dispatcher — every view
    // is reachable at its real path, so the metrics endpoint is fetched directly.
    fetch('/views/admin/dashboard-metrics.php', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Failed to load dashboard metrics');
            }
            return response.json();
        })
        .then(function (data) {
            var metrics = data.metrics || (data.data && data.data.metrics);

            if (!data.success || !metrics) {
                throw new Error((data && data.message) || 'Malformed dashboard response');
            }

            var labels = [];
            var values = [];
            Object.keys(metricMap).forEach(function (metricKey) {
                var label = metricKey === 'activityLogs' ? 'Activity Logs' : metricKey.charAt(0).toUpperCase() + metricKey.slice(1);
                if (metricKey === 'allocations') {
                    label = 'Allocations';
                }
                labels.push(label.replace(/([A-Z])/g, ' $1').trim());
                values.push(metrics[metricKey] || 0);

                var element = document.getElementById(metricMap[metricKey]);
                if (element) {
                    element.textContent = metrics[metricKey] ?? '0';
                }
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
        })
        .catch(function (error) {
            console.error('Dashboard metrics error:', error);
        });
});
