(function () {
    'use strict';

    var storageKey = 'nemsu-theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        var btn = document.getElementById('themeToggle');
        if (btn) {
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
        }
    }

    function initTheme() {
        var saved = localStorage.getItem(storageKey);
        var theme = saved === 'dark' ? 'dark' : 'light';
        applyTheme(theme);

        var btn = document.getElementById('themeToggle');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem(storageKey, next);
            applyTheme(next);
        });
    }

    document.addEventListener('DOMContentLoaded', initTheme);
})();
