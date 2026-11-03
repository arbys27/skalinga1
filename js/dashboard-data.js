(() => {
    const listNames = ['attention', 'activity', 'registrations'];
    let refreshTimer;

    function formatRelativeTime(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Unknown date';

        const seconds = Math.round((date.getTime() - Date.now()) / 1000);
        const absoluteSeconds = Math.abs(seconds);
        const units = [
            ['year', 31536000],
            ['month', 2592000],
            ['week', 604800],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60]
        ];

        for (const [unit, size] of units) {
            if (absoluteSeconds >= size) {
                const amount = Math.round(absoluteSeconds / size);
                const phrase = `${amount} ${unit}${amount === 1 ? '' : 's'}`;
                return seconds < 0 ? `${phrase} ago` : `in ${phrase}`;
            }
        }

        return seconds < 0 ? 'just now' : 'in a moment';
    }

    function createEmpty(message, parent) {
        const empty = document.createElement('p');
        empty.className = 'empty';
        empty.textContent = message;
        parent.appendChild(empty);
    }

    function renderAttention(items) {
        const container = document.querySelector('[data-list="attention"]');
        container.replaceChildren();
        if (!Array.isArray(items) || items.length === 0) {
            createEmpty('Nothing needs attention right now', container);
            return;
        }

        items.forEach(item => {
            const link = document.createElement('a');
            link.className = 'list-item';
            link.href = item.href;

            const dot = document.createElement('span');
            dot.className = 'dot';
            dot.setAttribute('aria-hidden', 'true');

            const label = document.createElement('span');
            label.textContent = String(item.label ?? '');

            const count = document.createElement('span');
            count.className = 'count';
            count.textContent = Number(item.count || 0).toLocaleString();

            link.append(dot, label, count);
            container.appendChild(link);
        });
    }

    function renderActivity(items) {
        const container = document.querySelector('[data-list="activity"]');
        container.replaceChildren();
        if (!Array.isArray(items) || items.length === 0) {
            createEmpty('No recent activity', container);
            return;
        }

        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'list-item';

            const text = document.createElement('span');
            text.textContent = String(item.text ?? '');

            const relative = document.createElement('small');
            relative.textContent = formatRelativeTime(item.created_at);
            const date = new Date(item.created_at);
            if (!Number.isNaN(date.getTime())) relative.title = date.toLocaleString();

            row.append(text, relative);
            container.appendChild(row);
        });
    }

    function renderRegistrations(items) {
        const container = document.querySelector('[data-list="registrations"]');
        container.replaceChildren();
        if (!Array.isArray(items) || items.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'empty';
            cell.textContent = 'No youth registrations yet';
            row.appendChild(cell);
            container.appendChild(row);
            return;
        }

        items.forEach(item => {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            name.textContent = String(item.name ?? '');

            const age = document.createElement('td');
            age.textContent = item.age == null ? '—' : String(item.age);

            const barangay = document.createElement('td');
            barangay.textContent = String(item.barangay || '—');

            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            const statusValue = String(item.status ?? '');
            status.className = `status-badge ${statusValue.toLowerCase() === 'active' ? 'active' : 'pending'}`;
            status.textContent = statusValue || 'Unknown';
            statusCell.appendChild(status);

            const created = document.createElement('td');
            created.textContent = formatRelativeTime(item.created_at);
            const date = new Date(item.created_at);
            if (!Number.isNaN(date.getTime())) created.title = date.toLocaleString();

            row.append(name, age, barangay, statusCell, created);
            container.appendChild(row);
        });
    }

    function renderError(error) {
        console.error('Dashboard summary refresh failed:', error);
        for (const listName of listNames) {
            const container = document.querySelector(`[data-list="${listName}"]`);
            container.replaceChildren();
            const message = document.createElement('p');
            message.className = 'empty';
            message.textContent = 'Dashboard data could not be loaded.';

            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn';
            retry.textContent = 'Retry';
            retry.addEventListener('click', refreshDashboard, { once: true });
            container.append(message, retry);
        }
    }

    function renderMetrics(metrics) {
        document.querySelectorAll('[data-bind]').forEach(element => {
            const key = element.dataset.bind;
            const value = metrics[key];
            if (key.startsWith('nav_')) {
                const visible = value !== null && value !== undefined && Number(value) > 0;
                element.hidden = !visible;
                element.textContent = visible ? Number(value).toLocaleString() : '';
                return;
            }
            element.textContent = Number(value ?? 0).toLocaleString();
        });
    }

    async function refreshDashboard() {
        document.body.classList.add('is-loading');
        try {
            const response = await fetch(`api/dashboard/summary.php?t=${Date.now()}`, {
                cache: 'no-store',
                headers: { Accept: 'application/json' }
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.metrics) {
                throw new Error(result.message || 'Unable to load dashboard data.');
            }

            renderMetrics(result.metrics);
            renderAttention(result.attention);
            renderActivity(result.activity);
            renderRegistrations(result.registrations);
        } catch (error) {
            renderError(error);
        } finally {
            document.body.classList.remove('is-loading');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshDashboard();
        refreshTimer = setInterval(refreshDashboard, 60000);
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refreshDashboard();
    });
})();
