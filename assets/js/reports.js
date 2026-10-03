(function () {
    'use strict';

    // Navy palette matching the navbar and container headers, with the
    // NEMSU gold as the accent. Darkest first so the biggest slice is navy.
    var NAVY = ['#052654', '#0a3d7a', '#0d4f9f', '#1565c0', '#4a8fd8', '#8fb8e8', '#c9a227', '#e8c547'];

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

    /** Soft navy fill under the line: strong at the top, fading to clear. */
    function navyAreaFill(context) {
        var chart = context.chart;
        if (!chart.chartArea) {
            return 'rgba(10, 61, 122, 0.15)';
        }
        var gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
        gradient.addColorStop(0, 'rgba(10, 61, 122, 0.35)');
        gradient.addColorStop(1, 'rgba(10, 61, 122, 0.02)');
        return gradient;
    }

    function buildChart(canvasId, type, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !window.Chart || !data) {
            return;
        }

        // A line needs at least 3 points to show a trend; with fewer
        // (e.g. a single month of data) a bar reads much better.
        if (type === 'line' && data.labels.length < 3) {
            type = 'bar';
        }

        var dataset = {
            label: data.label || 'Count',
            data: data.values,
        };
        if (type === 'line') {
            dataset.borderColor = '#0a3d7a';
            dataset.borderWidth = 2.5;
            dataset.backgroundColor = navyAreaFill;
            dataset.fill = true;
            dataset.tension = 0.35;
            dataset.pointBackgroundColor = '#c9a227';
            dataset.pointBorderColor = '#052654';
            dataset.pointRadius = 4;
            dataset.pointHoverRadius = 6;
        } else if (type === 'bar') {
            dataset.backgroundColor = NAVY.slice(0, data.labels.length);
            dataset.borderRadius = 6;
            dataset.maxBarThickness = 56;
        } else {
            dataset.backgroundColor = NAVY.slice(0, data.labels.length);
            dataset.borderColor = '#ffffff';
            dataset.borderWidth = 2;
        }

        var axes = {
            x: { grid: { display: false }, ticks: { color: '#4a5d78' } },
            y: {
                beginAtZero: true,
                ticks: { precision: 0, color: '#4a5d78' },
                grid: { color: 'rgba(10, 61, 122, 0.08)' },
            },
        };

        new window.Chart(canvas, {
            type: type,
            data: { labels: data.labels, datasets: [dataset] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type === 'doughnut',
                        position: 'bottom',
                        labels: { color: '#1f3354', usePointStyle: true, boxWidth: 8 },
                    },
                    tooltip: {
                        backgroundColor: '#052654',
                        titleColor: '#e8c547',
                        bodyColor: '#ffffff',
                        padding: 10,
                        cornerRadius: 6,
                    },
                },
                scales: type === 'doughnut' ? undefined : axes,
            },
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        buildChart('chartOverTime', 'line', parseChartData('chartOverTimeData'));
        buildChart('chartCategory', 'doughnut', parseChartData('chartCategoryData'));
        buildChart('chartStatus', 'bar', parseChartData('chartStatusData'));
    });
})();
