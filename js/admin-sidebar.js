(function () {
    const icon = {
        dashboard: '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>',
        users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        masterlist: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path><path d="M4 5.5v16"></path><path d="M8 7h8M8 11h8M8 15h5"></path>',
        events: '<rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
        resources: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>',
        printing: '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
        freedom: '<path d="M4 5h16M4 10h10M4 15h16M4 20h10"></path>',
        borrow: '<path d="M3 7h18l-2 13H5L3 7z"></path><path d="M8 7a4 4 0 0 1 8 0"></path><path d="M9 12v4M15 12v4"></path>',
        incidents: '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>',
        health: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>',
        disclosure: '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        verify: '<path d="M20 7 10 17l-5-5"></path><path d="M21 12a9 9 0 1 1-4-7.5"></path>',
        logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>'
    };

    const navGroups = [
        {
            title: 'Overview',
            items: [{ label: 'Dashboard', href: 'admin-dashboard.html', icon: 'dashboard' }]
        },
        {
            title: 'Management',
            items: [
                { label: 'Youth Registry', href: 'youth-registry.html', icon: 'users' },
                { label: 'KK Masterlist', href: 'admin-masterlist.html', icon: 'masterlist' },
                { label: 'Events', href: 'dashboard-events.html', icon: 'events', badge: 'nav_events' },
                { label: 'Resources', href: 'dashboard-resources.html', icon: 'resources' },
                { label: 'Printing', href: 'dashboard-printing.html', icon: 'printing', badge: 'nav_printing' },
                { label: 'Freedom Wall', href: 'dashboard-freedom-wall.html', icon: 'freedom', badge: 'nav_freedom_wall' }
            ]
        },
        {
            title: 'Emergency',
            items: [
                { label: 'Incidents', href: 'dashboard-incidents.html', icon: 'incidents', badge: 'nav_incidents' },
                { label: 'Health Services', href: 'admin-health-announcements.html', icon: 'health' }
            ]
        },
        {
            title: 'Public',
            items: [{ label: 'Public Disclosure', href: 'dashboard-public_disclosure.html', icon: 'disclosure' }]
        },
        {
            title: 'Service desk',
            items: [{ label: 'Verify service claim', href: 'admin-service-verification.html', icon: 'verify' }]
        },
        {
            title: 'System',
            items: [
                { label: 'Logout', icon: 'logout', action: 'logout' }
            ]
        }
    ];

    function iconMarkup(name) {
        return `<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">${icon[name]}</svg>`;
    }

    function renderSidebar(sidebar) {
        const currentPath = window.location.pathname.replace(/\/+$/, '');
        const groups = navGroups.map(group => {
            const items = group.items.map(item => {
                const isLogout = item.action === 'logout';
                const targetPath = item.href ? new URL(item.href, window.location.href).pathname.replace(/\/+$/, '') : '';
                const isActive = !isLogout && currentPath.toLowerCase() === targetPath.toLowerCase();
                const href = isLogout ? '#' : item.href;
                const badge = item.badge ? `<span class="nav-badge" data-bind="${item.badge}" hidden></span>` : '';
                const current = isActive ? ' aria-current="page"' : '';
                const click = isLogout ? ' onclick="confirmLogout(event)"' : '';
                return `<a href="${href}" class="nav-item${isActive ? ' active' : ''}"${current}${click}>${iconMarkup(item.icon)}<span>${item.label}</span>${badge}</a>`;
            }).join('');
            return `<div class="nav-group"><div class="nav-group-title">${group.title}</div>${items}</div>`;
        }).join('');

        sidebar.innerHTML = `<div class="logo-section"><div class="logo-content"><div class="logo-icons-group"><img src="assets/images/logosanantonio.jpg" alt="San Antonio Logo" class="official-logo"><img src="assets/images/sklogo.jpg" alt="SK Logo" class="official-logo"></div><div class="logo-text"><h1>SKalinga Admin</h1><p>San Antonio</p></div></div></div><nav class="nav-section" aria-label="Admin navigation">${groups}</nav>`;
    }

    function initializeSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        if (sidebar) renderSidebar(sidebar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSidebar, { once: true });
    } else {
        initializeSidebar();
    }
})();
