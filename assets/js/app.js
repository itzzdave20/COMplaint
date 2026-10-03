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

    // Copy each column header onto its cells so tables can stack into
    // labelled cards on phones (see .nemsu-table--stack in style.css).
    function labelStackTables() {
        document.querySelectorAll('table.nemsu-table--stack').forEach(function (table) {
            var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
                return {
                    label: th.textContent.trim(),
                    full: th.textContent.trim() === '' || th.classList.contains('nemsu-stack-full')
                };
            });
            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.prototype.forEach.call(row.cells, function (cell, index) {
                    var header = headers[index];
                    if (cell.colSpan > 1 || !header || header.full) {
                        cell.classList.add('nemsu-td-full');
                    } else {
                        cell.setAttribute('data-label', header.label);
                    }
                });
            });
        });
    }

    // Pages can't use inline <script> (the Content-Security-Policy in
    // config.php only allows script files), so small page behaviours are
    // switched on with data- attributes and handled here.

    // <div data-auto-refresh="60">: reload every N seconds, but not while
    // the tab is hidden or the user is typing in a field.
    function initAutoRefresh() {
        var el = document.querySelector('[data-auto-refresh]');
        var seconds = el ? parseInt(el.getAttribute('data-auto-refresh'), 10) : 0;
        if (!seconds) {
            return;
        }
        setInterval(function () {
            if (document.visibilityState === 'visible' && !document.querySelector('input:focus, select:focus, textarea:focus')) {
                window.location.reload();
            }
        }, seconds * 1000);
    }

    // <button data-confirm-when="someCheckboxId" data-confirm-when-message="...">:
    // ask for confirmation only when that radio/checkbox is selected.
    function initConditionalConfirm() {
        document.querySelectorAll('[data-confirm-when]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var option = document.getElementById(button.getAttribute('data-confirm-when'));
                if (option && option.checked && !window.confirm(button.getAttribute('data-confirm-when-message'))) {
                    event.preventDefault();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        markReady();
        setActiveNav();
        enhanceButtons();
        initConfirmModals();
        labelStackTables();
        initAutoRefresh();
        initConditionalConfirm();
    });
})();
