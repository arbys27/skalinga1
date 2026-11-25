(function () {
    const alertTypes = {
        success: { icon: 'success', title: 'Success' },
        error: { icon: 'error', title: 'Error' },
        warning: { icon: 'warning', title: 'Warning' },
        info: { icon: 'info', title: 'Notice' }
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
        const alertType = alertTypes[type] || alertTypes.info;
        const text = String(message ?? '');

        if (!window.Swal) {
            window.alert(text);
            return Promise.resolve();
        }

        const isTimed = alertType.icon === 'success' || alertType.icon === 'info';
        return window.Swal.fire({
            title: alertType.title,
            text,
            icon: alertType.icon,
            confirmButtonText: 'OK',
            buttonsStyling: true,
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'skalinga-swal-popup',
                confirmButton: 'skalinga-swal-confirm',
                cancelButton: 'skalinga-swal-cancel'
            },
            confirmButtonColor: '#228B57',
            timer: isTimed ? 2800 : undefined,
            timerProgressBar: isTimed,
            allowOutsideClick: alertType.icon !== 'error'
        });
    };

    window.showConfirm = function (message, options = {}) {
        const settings = options || {};
        const confirmButtonText = settings.confirmButtonText || 'Confirm';
        const cancelButtonText = settings.cancelButtonText || 'Cancel';

        if (!window.Swal) {
            return Promise.resolve(window.confirm(String(message ?? '')));
        }

        return window.Swal.fire({
            title: settings.title || 'Are you sure?',
            text: String(message ?? ''),
            icon: settings.icon || 'warning',
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText,
            buttonsStyling: true,
            customClass: {
                popup: 'skalinga-swal-popup',
                confirmButton: 'skalinga-swal-confirm',
                cancelButton: 'skalinga-swal-cancel'
            },
            confirmButtonColor: settings.confirmButtonColor || '#228B57',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            allowOutsideClick: false
        }).then(result => result.isConfirmed);
    };

    window.confirmLogout = function (event) {
        if (event) event.preventDefault();

        const logout = () => {
            window.location.href = 'api/logout.php';
        };

        if (!window.Swal) {
            if (window.confirm('You will need to log in again to access the dashboard.')) logout();
            return Promise.resolve();
        }

        return window.Swal.fire({
            title: 'Log out?',
            text: 'You will need to log in again to access the dashboard.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Log out',
            cancelButtonText: 'Cancel',
            buttonsStyling: true,
            customClass: {
                popup: 'skalinga-swal-popup',
                confirmButton: 'skalinga-swal-confirm',
                cancelButton: 'skalinga-swal-cancel'
            },
            confirmButtonColor: '#228B57',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            allowOutsideClick: false
        }).then(result => {
            if (result.isConfirmed) logout();
        });
    };
})();
