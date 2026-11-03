(function () {
    const toastIcons = {
        success: '✓',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    window.esc = function (value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[character]));
    };

    window.showAlert = function (message, type = 'info') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'admin-toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        const toastType = Object.prototype.hasOwnProperty.call(toastIcons, type) ? type : 'info';
        const toast = document.createElement('div');
        toast.className = `toast ${toastType}`;
        toast.innerHTML = `<span class="toast-icon">${toastIcons[toastType]}</span><span class="toast-message">${window.esc(message)}</span>`;
        container.appendChild(toast);

        window.setTimeout(() => {
            toast.classList.add('removing');
            window.setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    window.confirmLogout = function (event) {
        if (event) event.preventDefault();
        if (document.getElementById('logoutModal')) return;

        const modal = document.createElement('div');
        modal.id = 'logoutModal';
        modal.className = 'admin-logout-overlay';
        modal.setAttribute('role', 'presentation');
        modal.innerHTML = '<section class="admin-logout-card" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" aria-describedby="logoutDescription"><h2 id="logoutTitle">Log out?</h2><p id="logoutDescription">You will need to log in again to access the dashboard.</p><div class="admin-logout-actions"><button type="button" class="admin-logout-cancel">Cancel</button><button type="button" class="admin-logout-confirm">Log out</button></div></section>';
        document.body.appendChild(modal);

        const cancelButton = modal.querySelector('.admin-logout-cancel');
        const confirmButton = modal.querySelector('.admin-logout-confirm');
        const closeModal = () => {
            document.removeEventListener('keydown', handleKeydown);
            modal.remove();
        };
        const handleKeydown = keyEvent => {
            if (keyEvent.key === 'Escape') closeModal();
        };

        cancelButton.addEventListener('click', closeModal);
        confirmButton.addEventListener('click', () => {
            window.location.href = 'api/logout.php';
        });
        modal.addEventListener('click', clickEvent => {
            if (clickEvent.target === modal) closeModal();
        });
        document.addEventListener('keydown', handleKeydown);
        cancelButton.focus();
    };
})();
