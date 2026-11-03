(() => {
    const currentFile = window.location.pathname.split('/').pop() || 'youth-portal.html';
    const navItems = [
        ['youth-portal.html', 'Home'],
        ['events.html', 'Events'],
        ['borrow.html', 'Borrow'],
        ['print.html', 'Print'],
        ['health.html', 'Health'],
        ['report.html', 'Report'],
        ['public-disclosure.html', 'Documents'],
        ['profile.html', 'Profile']
    ];
    const activeItem = navItems.find(([href]) => href === currentFile);
    const serviceItems = [
        ['events.html', 'Events', 'calendar'],
        ['borrow.html', 'Borrow', 'box'],
        ['print.html', 'Print', 'print'],
        ['health.html', 'Health', 'heart'],
        ['report.html', 'Report', 'alert'],
        ['public-disclosure.html', 'Documents', 'file']
    ];
    const iconNames = ['home', 'calendar', 'box', 'print', 'heart', 'alert', 'file', 'id', 'arrow', 'help', 'close', 'pin', 'search', 'check'];
    const iconPaths = {
        home: '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1V10Z"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        box: '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"/>',
        print: '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6zM18 12h.01"/>',
        heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/><path d="M3 12h5l2-4 4 8 2-4h5"/>',
        alert: '<path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2ZM18 16v-5a6 6 0 0 0-5-5.9V4a1 1 0 0 0-2 0v1.1A6 6 0 0 0 6 11v5l-2 2h16l-2-2Z"/>',
        file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        id: '<rect x="2.5" y="5" width="19" height="14" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M5.5 16a3.8 3.8 0 0 1 7 0M15 10h3M15 14h3"/>',
        arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
        help: '<circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.4 1.6c-1.1 1.1-2 1.4-2 3M12 17h.01"/>',
        close: '<path d="m6 6 12 12M18 6 6 18"/>',
        pin: '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        check: '<path d="m5 12 4 4L19 6"/>'
    };
    const symbol = name => `<symbol id="icon-${name}" viewBox="0 0 24 24">${iconPaths[name]}</symbol>`;
    const icon = name => `<svg class="icon" aria-hidden="true"><use href="#icon-${name}"></use></svg>`;
    const menuLink = ([href, label]) => `<a href="${href}"${href === currentFile ? ' aria-current="page"' : ''}>${label}</a>`;

    function addSprite() {
        if (document.querySelector('.icon-sprite')) return;
        document.body.insertAdjacentHTML('afterbegin', `<svg class="icon-sprite" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">${iconNames.map(symbol).join('')}</svg>`);
    }

    function buildTopbar() {
        const header = document.createElement('header');
        header.className = 'topbar';
        header.innerHTML = `
            <a class="brand" href="youth-portal.html" aria-label="SKalinga Youth Portal home">
                <span class="brand-logos">
                    <img src="assets/images/logosanantonio.jpg" alt="San Antonio community logo" width="38" height="38">
                    <img src="assets/images/sklogo.jpg" alt="Sangguniang Kabataan logo" width="38" height="38">
                </span>
                <span class="brand-copy"><strong>SKalinga</strong><span>Youth Portal</span></span>
            </a>
            <nav class="desktop-nav" aria-label="Main navigation">${navItems.map(menuLink).join('')}</nav>
            <div class="youth-nav-actions">
                <a class="user-chip" href="profile.html" aria-label="Open profile">
                    <span id="nav-user-name">My profile</span>
                    ${icon('arrow')}
                </a>
            </div>`;
        document.body.insertBefore(header, document.body.firstChild);
    }

    function buildMobileTabs() {
        const tabs = document.querySelector('.bottom-tabs') || document.createElement('nav');
        tabs.className = 'bottom-tabs';
        tabs.setAttribute('aria-label', 'Bottom navigation');
        tabs.innerHTML = `
            <a class="bottom-tab-link" href="youth-portal.html"${currentFile === 'youth-portal.html' ? ' aria-current="page"' : ''}>${icon('home')}<span>Home</span></a>
            <a class="bottom-tab-link" href="events.html"${currentFile === 'events.html' ? ' aria-current="page"' : ''}>${icon('calendar')}<span>Events</span></a>
            <div class="services-dock">
                <div class="services-dock-menu" id="youth-services-menu" role="group" aria-label="All services" hidden>
                    ${serviceItems.map(([href, label, iconName]) => `<a class="service-dock-item${href === currentFile ? ' is-current' : ''}" href="${href}"${href === currentFile ? ' aria-current="page"' : ''}>${icon(iconName)}<span>${label}</span></a>`).join('')}
                </div>
                <button class="services-dock-toggle" type="button" aria-expanded="false" aria-controls="youth-services-menu" aria-label="Open services">
                    ${icon('box')}<span>Services</span>
                </button>
            </div>
            <a class="bottom-tab-link" href="health.html"${currentFile === 'health.html' ? ' aria-current="page"' : ''}>${icon('heart')}<span>Health</span></a>
            <a class="bottom-tab-link" href="profile.html"${currentFile === 'profile.html' ? ' aria-current="page"' : ''}>${icon('id')}<span>Profile</span></a>`;
        if (!tabs.isConnected) document.body.appendChild(tabs);

        const toggle = tabs.querySelector('.services-dock-toggle');
        const menu = tabs.querySelector('.services-dock-menu');
        const setMenuOpen = open => {
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close services' : 'Open services');
            tabs.classList.toggle('services-open', open);
            toggle.querySelector('use').setAttribute('href', open ? '#icon-close' : '#icon-box');
            menu.hidden = !open;
        };

        toggle.addEventListener('click', () => {
            setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        document.addEventListener('click', event => {
            if (!tabs.contains(event.target)) setMenuOpen(false);
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setMenuOpen(false);
                toggle.focus();
            }
        });
    }

    function buildHelp() {
        const container = document.createElement('div');
        container.className = 'fab-container';
        container.innerHTML = `
            <div class="fab-menu" id="youth-help-menu" aria-label="Help shortcuts" aria-hidden="true">
                <a class="fab-menu-item" href="events.html">${icon('calendar')}<span>Browse events</span></a>
                <a class="fab-menu-item" href="report.html">${icon('alert')}<span>Report an issue</span></a>
                <a class="fab-menu-item" href="profile.html">${icon('id')}<span>View my profile</span></a>
                <button class="fab-menu-item" type="button" data-youth-help>${icon('help')}<span>How can we help?</span></button>
            </div>
            <button class="fab-main" type="button" aria-expanded="false" aria-controls="youth-help-menu" aria-label="Open help shortcuts">
                ${icon('help')}<span>Help</span>
            </button>
            <div class="modal" data-youth-help-modal role="dialog" aria-modal="true" aria-labelledby="youth-help-title" hidden>
                <section class="modal-content">
                    <div class="modal-header"><div><p class="eyebrow">SKalinga support</p><h2 id="youth-help-title">Need a hand?</h2></div><button class="modal-close" type="button" data-youth-help-close aria-label="Close help dialog">${icon('close')}</button></div>
                    <div class="modal-body"><p>Browse events, use a youth service, or send the SK team a report through the portal.</p></div>
                </section>
            </div>`;
        document.body.appendChild(container);

        const toggle = container.querySelector('.fab-main');
        const menu = container.querySelector('.fab-menu');
        const modal = container.querySelector('[data-youth-help-modal]');
        const close = container.querySelector('[data-youth-help-close]');
        const setMenuOpen = open => {
            toggle.setAttribute('aria-expanded', String(open));
            menu.setAttribute('aria-hidden', String(!open));
            container.classList.toggle('active', open);
        };

        toggle.addEventListener('click', () => setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        container.querySelector('[data-youth-help]').addEventListener('click', () => {
            modal.hidden = false;
            setMenuOpen(false);
            close.focus();
        });
        close.addEventListener('click', () => modal.hidden = true);
        modal.addEventListener('click', event => {
            if (event.target === modal) modal.hidden = true;
        });
        document.addEventListener('click', event => {
            if (!container.contains(event.target)) setMenuOpen(false);
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                modal.hidden = true;
                setMenuOpen(false);
            }
        });
    }

    function init() {
        addSprite();
        if (currentFile === 'login.html' || currentFile === 'youth-register.html') return;
        const existingTopbar = document.querySelector('.topbar');
        if (existingTopbar) {
            existingTopbar.querySelectorAll('[data-youth-logout], .youth-nav-logout, a[href*="logout"], button').forEach(control => {
                if (
                    control.matches('[data-youth-logout], .youth-nav-logout, a[href*="logout"]') ||
                    /^(log\s*out|logout)$/i.test(control.textContent.trim())
                ) {
                    control.remove();
                }
            });
            const desktopNav = existingTopbar.querySelector('.desktop-nav');
            if (desktopNav) {
                desktopNav.innerHTML = navItems.map(menuLink).join('');
                desktopNav.setAttribute('aria-label', 'Main navigation');
            }
        } else {
            buildTopbar();
        }
        buildMobileTabs();
        if (!document.querySelector('.fab-container')) buildHelp();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
