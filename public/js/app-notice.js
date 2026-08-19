(function (window, document) {
    'use strict';

    var timer = null;
    var delayMs = {
        success: 2500,
        info: 3000,
        danger: 6000
    };

    function cancelTimer() {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    function scheduleNoticeClose() {
        var notice = document.getElementById('app-notice');
        var text;
        var noticeType;
        var closeDelay;
        if (!notice) {
            return;
        }

        cancelTimer();
        if (notice.hidden) {
            return;
        }

        noticeType = notice.classList.contains('alert-success')
            ? 'success'
            : (notice.classList.contains('alert-info')
                ? 'info'
                : (notice.classList.contains('alert-danger') ? 'danger' : ''));
        if (noticeType === '') {
            return;
        }
        closeDelay = delayMs[noticeType];

        text = String(notice.textContent || '');
        if (text === '') {
            return;
        }

        timer = window.setTimeout(function () {
            var current = document.getElementById('app-notice');
            timer = null;
            if (!current
                || current.hidden
                || !current.classList.contains('alert-' + noticeType)
                || String(current.textContent || '') !== text) {
                return;
            }
            current.hidden = true;
            current.textContent = '';
        }, closeDelay);
    }

    function init() {
        var notice = document.getElementById('app-notice');
        var observer;
        if (!notice || typeof window.MutationObserver !== 'function') {
            return;
        }

        observer = new window.MutationObserver(scheduleNoticeClose);
        observer.observe(notice, {
            attributes: true,
            attributeFilter: ['class', 'hidden'],
            childList: true,
            characterData: true,
            subtree: true
        });
        scheduleNoticeClose();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
