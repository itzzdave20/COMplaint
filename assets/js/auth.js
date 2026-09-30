(function () {
    'use strict';

    function initPasswordToggle() {
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-toggle-password');
                var input = document.getElementById(targetId);
                if (!input) {
                    return;
                }
                var isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
                }
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });
    }

    function initOtpInputs() {
        var form = document.getElementById('otpForm');
        if (!form) {
            return;
        }
        var hidden = document.getElementById('otp_code');
        var inputs = form.querySelectorAll('.otp-digit');
        if (!hidden || !inputs.length) {
            return;
        }

        function syncHidden() {
            var code = '';
            inputs.forEach(function (inp) {
                code += (inp.value || '').replace(/\D/g, '').slice(0, 1);
            });
            hidden.value = code;
        }

        inputs.forEach(function (input, index) {
            input.addEventListener('input', function () {
                input.value = input.value.replace(/\D/g, '').slice(0, 1);
                syncHidden();
                if (input.value && inputs[index + 1]) {
                    inputs[index + 1].focus();
                }
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !input.value && inputs[index - 1]) {
                    inputs[index - 1].focus();
                }
            });
            input.addEventListener('paste', function (event) {
                event.preventDefault();
                var text = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                text.split('').forEach(function (ch, i) {
                    if (inputs[i]) {
                        inputs[i].value = ch;
                    }
                });
                syncHidden();
                if (inputs[Math.min(text.length, inputs.length - 1)]) {
                    inputs[Math.min(text.length, inputs.length - 1)].focus();
                }
            });
        });

        form.addEventListener('submit', function () {
            syncHidden();
        });
    }

    function initResendTimer() {
        var resendBtn = document.getElementById('resendOtpBtn');
        var timerEl = document.getElementById('resendOtpTimer');
        if (!resendBtn || !timerEl) {
            return;
        }
        var seconds = parseInt(resendBtn.getAttribute('data-cooldown') || '60', 10);
        var remaining = seconds;
        resendBtn.disabled = true;

        function tick() {
            timerEl.textContent = 'Resend available in ' + remaining + 's';
            if (remaining <= 0) {
                resendBtn.disabled = false;
                timerEl.textContent = '';
                return;
            }
            remaining -= 1;
            window.setTimeout(tick, 1000);
        }
        tick();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggle();
        initOtpInputs();
        initResendTimer();
    });
})();
