(() => {
    const palette = ['#FF6B9D', '#5DADE2', '#228B57', '#A8E6CF'];
    const charts = new Map();
    let refreshTimer;

    function setEmptyState(canvas, message) {
        const cardBody = canvas.parentElement;
        let empty = cardBody.querySelector('[data-chart-empty]');

        if (!empty) {
            empty = document.createElement('p');
            empty.className = 'empty';
            empty.dataset.chartEmpty = 'true';
            empty.setAttribute('role', 'status');
            cardBody.appendChild(empty);
        }

        empty.textContent = message;
        canvas.hidden = true;
    }

    function clearEmptyState(canvas) {
        const empty = canvas.parentElement.querySelector('[data-chart-empty]');
        if (empty) empty.remove();
        canvas.hidden = false;
    }

    function renderChart(id, type, series, dataset, options = {}) {
        const canvas = document.getElementById(id);
        if (!canvas) return;

        if (charts.has(id)) {
            charts.get(id).destroy();
            charts.delete(id);
        }

        const labels = Array.isArray(series && series.labels) ? series.labels : [];
        const values = Array.isArray(series && series.values)
            ? series.values.map(value => Number(value) || 0)
            : [];

        if (!labels.length || !values.some(value => value !== 0)) {
            setEmptyState(canvas, 'No data yet');
            return;
        }

        if (typeof window.Chart !== 'function') {
            setEmptyState(canvas, 'Chart library is unavailable');
            return;
        }

        clearEmptyState(canvas);
        const colors = labels.map((_, index) => palette[index % palette.length]);
        const config = {
            type,
            data: {
                labels,
                datasets: [{
                    ...dataset,
                    data: values,
                    backgroundColor: type === 'line'
                        ? dataset.backgroundColor
                        : colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
                scales: type === 'doughnut' ? undefined : {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                },
                ...options
            }
        };

        charts.set(id, new window.Chart(canvas.getContext('2d'), config));
    }

    async function refreshCharts() {
        try {
            const response = await fetch(`api/dashboard/charts.php?t=${Date.now()}`, {
                cache: 'no-store',
                headers: { Accept: 'application/json' }
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to load dashboard charts.');
            }

            renderChart('youthTrendChart', 'line', result.youth_trend, {
                label: 'Registrations',
                borderColor: palette[2],
                backgroundColor: 'rgba(34,139,87,0.12)',
                pointBackgroundColor: palette[2],
                tension: 0.35,
                fill: true
            });
            renderChart('eventsPieChart', 'doughnut', result.events_by_category, {
                borderColor: '#fff',
                borderWidth: 2
            }, { cutout: '70%' });
            renderChart('resourcesBarChart', 'bar', result.resources_top, {
                label: 'Borrow records',
                backgroundColor: palette[2],
                borderRadius: 8
            });
            renderChart('healthLineChart', 'line', result.health_by_month, {
                label: 'Announcements',
                borderColor: palette[1],
                backgroundColor: 'rgba(93,173,226,0.12)',
                pointBackgroundColor: palette[1],
                tension: 0.35,
                fill: true
            });
        } catch (error) {
            console.error('Dashboard chart refresh failed:', error);
            document.querySelectorAll('#youthTrendChart, #eventsPieChart, #resourcesBarChart, #healthLineChart')
                .forEach(canvas => setEmptyState(canvas, 'Unable to load chart data'));
        }
    }

    function scheduleRefresh() {
        clearInterval(refreshTimer);
        refreshTimer = setInterval(refreshCharts, 60000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshCharts();
        scheduleRefresh();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refreshCharts();
    });
})();
