(function () {
    'use strict';

    function parseChartData(id) {
        var el = document.getElementById(id);
        if (!el) {
            return null;
        }
        var raw = el.getAttribute('data-chart');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function buildChart(canvasId, type, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !window.Chart || !data) {
            return;
        }
        var colors = ['#0a3d7a', '#1565c0', '#c9a227', '#198754', '#dc3545', '#6c757d', '#0dcaf0'];
        new window.Chart(canvas, {
            type: type,
            data: {
                labels: data.labels,
                datasets: [{
                    label: data.label || 'Count',
                    data: data.values,
                    backgroundColor: colors.slice(0, data.labels.length),
                    borderColor: type === 'line' ? '#0a3d7a' : undefined,
                    tension: 0.25,
                    fill: type === 'line' ? false : undefined,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: type !== 'line' },
                },
                scales: type === 'line' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : undefined,
            },
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        buildChart('chartOverTime', 'line', parseChartData('chartOverTimeData'));
        buildChart('chartCategory', 'doughnut', parseChartData('chartCategoryData'));
        buildChart('chartStatus', 'bar', parseChartData('chartStatusData'));
    });
})();
