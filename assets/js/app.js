(function () {
    'use strict';

    function markReady() {
        requestAnimationFrame(function () {
            document.body.classList.add('is-ready');
        });
    }

    function setActiveNav() {
        var path = window.location.pathname.split('/').pop() || 'dashboard.php';
        document.querySelectorAll('#sidebarOffcanvas .nav-link, #sidebar .nav-link').forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href) {
                return;
            }
            if (href.split('/').pop() === path) {
                link.classList.add('active');
            }
        });
    }

    function enhanceButtons() {
        document.querySelectorAll('form:not([data-no-loading])').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var submitter = event.submitter;
                if (!submitter || submitter.tagName !== 'BUTTON') {
                    return;
                }
                if (submitter.dataset.noLoading === 'true') {
                    return;
                }
                if (submitter.classList.contains('btn-link')) {
                    return;
                }
                var original = submitter.innerHTML;
                // Defer disable so the browser finishes serializing the POST first.
                window.setTimeout(function () {
                    submitter.disabled = true;
                    submitter.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Please wait…';
                }, 0);
                window.setTimeout(function () {
                    submitter.disabled = false;
                    submitter.innerHTML = original;
                }, 15000);
            });
        });
    }

    function initConfirmModals() {
        document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm-message');
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('button[data-confirm-message], a[data-confirm-message]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                var message = el.getAttribute('data-confirm-message');
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        markReady();
        setActiveNav();
        enhanceButtons();
        initConfirmModals();
    });
})();
