(function () {
    'use strict';

    function mapType(type) {
        if (type === 'danger') {
            return 'danger';
        }
        if (type === 'success') {
            return 'success';
        }
        if (type === 'warning') {
            return 'warning';
        }
        return 'primary';
    }

    window.NemsuToast = {
        show: function (message, type) {
            var host = document.getElementById('nemsu-toast-host');
            if (!host || !message) {
                return;
            }
            var bsType = mapType(type || 'info');
            var toastEl = document.createElement('div');
            toastEl.className = 'toast align-items-center text-bg-' + bsType + ' border-0';
            toastEl.setAttribute('role', 'alert');
            toastEl.innerHTML =
                '<div class="d-flex">' +
                '<div class="toast-body"></div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
                '</div>';
            toastEl.querySelector('.toast-body').textContent = message;
            host.appendChild(toastEl);
            var toast = window.bootstrap && window.bootstrap.Toast
                ? new window.bootstrap.Toast(toastEl, { delay: 5500 })
                : null;
            if (toast) {
                toast.show();
                toastEl.addEventListener('hidden.bs.toast', function () {
                    toastEl.remove();
                });
            }
        },
        initFlash: function () {
            var node = document.getElementById('nemsu-flash-data');
            if (!node) {
                return;
            }
            window.NemsuToast.show(node.getAttribute('data-message') || '', node.getAttribute('data-type') || 'info');
            node.remove();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.NemsuToast.initFlash();
    });
})();
