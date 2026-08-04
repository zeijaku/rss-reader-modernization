(function () {
    'use strict';

    function setPasswordVisibility(button) {
        var inputId = button.getAttribute('aria-controls');
        var input = inputId ? document.getElementById(inputId) : null;
        if (!input) {
            return;
        }
        var reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        button.setAttribute('aria-label', reveal ? 'パスワードを隠す' : 'パスワードを表示');
        var icon = button.querySelector('[data-password-icon]');
        if (icon) {
            icon.className = reveal ? 'fas fa-eye-slash' : 'fas fa-eye';
        }
    }

    function showPanel(name) {
        var panels = document.querySelectorAll('[data-auth-panel]');
        var active = null;
        panels.forEach(function (panel) {
            var selected = panel.getAttribute('data-auth-panel') === name;
            panel.hidden = !selected;
            if (selected) {
                active = panel;
            }
        });
        if (active) {
            var input = active.querySelector('input:not([type="hidden"]):not([tabindex="-1"])');
            if (input) {
                input.focus();
            }
        }
    }

    document.addEventListener('click', function (event) {
        var passwordButton = event.target.closest('[data-password-toggle]');
        if (passwordButton) {
            setPasswordVisibility(passwordButton);
            return;
        }
        var switchButton = event.target.closest('[data-auth-switch]');
        if (switchButton) {
            showPanel(switchButton.getAttribute('data-auth-switch'));
        }
    });

    document.querySelectorAll('[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            var submit = form.querySelector('[type="submit"]');
            if (submit) {
                submit.disabled = true;
                var label = submit.querySelector('[data-submit-label]');
                if (label) {
                    label.textContent = '送信中…';
                }
            }
        });
    });
}());
