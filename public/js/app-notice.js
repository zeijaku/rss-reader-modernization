(function (window, document) {
    'use strict';

    var timer = null;
    var delayMs = 6000;

    function cancelTimer() {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    function scheduleDangerNoticeClose() {
        var notice = document.getElementById('app-notice');
        var text;
        if (!notice) {
            return;
        }

        cancelTimer();
        if (notice.hidden || !notice.classList.contains('alert-danger')) {
            return;
        }

        text = String(notice.textContent || '');
        if (text === '') {
            return;
        }

        timer = window.setTimeout(function () {
            var current = document.getElementById('app-notice');
            timer = null;
            if (!current
                || current.hidden
                || !current.classList.contains('alert-danger')
                || String(current.textContent || '') !== text) {
                return;
            }
            current.hidden = true;
            current.textContent = '';
        }, delayMs);
    }

    function init() {
        var notice = document.getElementById('app-notice');
        var observer;
        if (!notice || typeof window.MutationObserver !== 'function') {
            return;
        }

        observer = new window.MutationObserver(scheduleDangerNoticeClose);
        observer.observe(notice, {
            attributes: true,
            attributeFilter: ['class', 'hidden'],
            childList: true,
            characterData: true,
            subtree: true
        });
        scheduleDangerNoticeClose();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
