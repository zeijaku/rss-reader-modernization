(function (root) {
    'use strict';

    var MEMO_MAX_LENGTH = 4000;

    function normalizeMemoText(value) {
        return String(value == null ? '' : value).replace(/\r\n|\r/g, '\n');
    }

    function memoTextLength(value) {
        return Array.from(normalizeMemoText(value)).length;
    }

    function memoCounterText(value) {
        return String(memoTextLength(value)) + '/' + String(MEMO_MAX_LENGTH);
    }

    function updateCounter(counter, value) {
        if (!counter) {
            return;
        }
        var length = memoTextLength(value);
        counter.textContent = String(length) + '/' + String(MEMO_MAX_LENGTH);
        counter.classList.toggle('is-limit', length >= MEMO_MAX_LENGTH);
    }

    function ensureDashboardCounter(card) {
        if (!card) {
            return;
        }
        var inner = card.querySelector('.memo-card-inner');
        var body = card.querySelector('.memo-body');
        if (!inner || !body) {
            return;
        }

        var counter = inner.querySelector('.memo-character-count');
        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'memo-character-count';
            counter.setAttribute('aria-label', 'Memo文字数');
            inner.appendChild(counter);
        }
        updateCounter(counter, body.textContent || '');
    }

    function ensureInputCounter(textarea) {
        if (!textarea) {
            return null;
        }

        var counter = textarea.nextElementSibling;
        if (!counter || !counter.classList.contains('memo-input-character-count')) {
            counter = document.createElement('small');
            counter.className = 'memo-input-character-count';
            counter.setAttribute('aria-live', 'polite');
            counter.setAttribute('aria-atomic', 'true');
            textarea.insertAdjacentElement('afterend', counter);
        }

        updateCounter(counter, textarea.value);
        return counter;
    }

    function bindTextarea(textarea) {
        if (!textarea || textarea.dataset.memoCounterBound === '1') {
            return;
        }
        textarea.dataset.memoCounterBound = '1';
        ensureInputCounter(textarea);
        textarea.addEventListener('input', function () {
            updateCounter(ensureInputCounter(textarea), textarea.value);
        });
    }

    function refreshEditCounter() {
        var textarea = document.querySelector('.changeMemoBody');
        if (textarea) {
            updateCounter(ensureInputCounter(textarea), textarea.value);
        }
    }

    function bindCardRefreshCounter() {
        if (!root || !root.jQuery) {
            return;
        }

        root.jQuery(document)
            .off('iguguru:widget-card-refreshed.v124MemoCounter')
            .on('iguguru:widget-card-refreshed.v124MemoCounter', function (event, card) {
                if (!card || !card.classList || !card.classList.contains('memo-card')) {
                    return;
                }
                ensureDashboardCounter(card);
            });
    }

    function initMemoCounters() {
        document.querySelectorAll('.memo-card').forEach(ensureDashboardCounter);
        document.querySelectorAll('.registerMemoBody, .changeMemoBody').forEach(bindTextarea);
        bindCardRefreshCounter();

        document.addEventListener('click', function (event) {
            if (event.target.closest('.memo-edit-trigger')) {
                window.setTimeout(refreshEditCounter, 0);
            }
        });

        var editModal = document.getElementById('changeMemo');
        if (editModal) {
            editModal.addEventListener('shown.bs.modal', refreshEditCounter);
        }
    }

    var api = {
        maxLength: MEMO_MAX_LENGTH,
        normalizeText: normalizeMemoText,
        textLength: memoTextLength,
        counterText: memoCounterText
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.RssMemoCounter = api;
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initMemoCounters, { once: true });
        } else {
            initMemoCounters();
        }
    }
}(typeof window !== 'undefined' ? window : null));

/* V1.26-D: do not let the aggregate Information Board RSS request compete
 * with the Dashboard's normal RSS / remote-widget startup requests. */
(function (root) {
    'use strict';

    if (!root || !root.jQuery || root.RssInfoBoardAjaxGate) {
        return;
    }

    var $ = root.jQuery;
    var originalAjax = $.ajax;
    var queue = [];
    var running = false;
    var pollTimer = null;
    var idleChecks = 0;
    var POLL_MS = 250;
    var IDLE_CHECKS_REQUIRED = 2;

    if (typeof originalAjax !== 'function' || typeof $.Deferred !== 'function') {
        return;
    }

    function isInfoBoardFetch(options) {
        return !!(
            options
            && options.data
            && String(options.data.action || '') === 'widget.infoboard.fetch'
        );
    }

    function canStart(activeRequests, stableIdleChecks) {
        var active = Number(activeRequests);
        if (!Number.isFinite(active) || active < 0) {
            active = 0;
        }
        return active === 0 && Number(stableIdleChecks) >= IDLE_CHECKS_REQUIRED;
    }

    function schedulePoll() {
        if (pollTimer !== null || running || queue.length === 0) {
            return;
        }
        pollTimer = root.setTimeout(poll, POLL_MS);
    }

    function runNext() {
        var item;
        var xhr;
        if (running || queue.length === 0) {
            return;
        }

        item = queue.shift();
        if (!item || item.aborted) {
            schedulePoll();
            return;
        }

        running = true;
        xhr = originalAjax.call($, item.options);
        item.realXhr = xhr;

        xhr.done(function () {
            item.deferred.resolveWith(this, arguments);
        }).fail(function () {
            item.deferred.rejectWith(this, arguments);
        }).always(function () {
            running = false;
            item.realXhr = null;
            schedulePoll();
        });
    }

    function poll() {
        var activeRequests;
        pollTimer = null;
        if (running || queue.length === 0) {
            return;
        }

        activeRequests = Number($.active || 0);
        if (activeRequests === 0) {
            idleChecks++;
        } else {
            idleChecks = 0;
        }

        if (!canStart(activeRequests, idleChecks)) {
            schedulePoll();
            return;
        }

        idleChecks = 0;
        runNext();
    }

    $.ajax = function (options) {
        var deferred;
        var item;
        var proxy;

        if (!isInfoBoardFetch(options)) {
            return originalAjax.apply(this, arguments);
        }

        deferred = $.Deferred();
        item = {
            options: options,
            deferred: deferred,
            realXhr: null,
            aborted: false
        };
        proxy = deferred.promise();
        proxy.abort = function (statusText) {
            var index;
            var reason = statusText || 'abort';
            if (item.realXhr && typeof item.realXhr.abort === 'function') {
                item.realXhr.abort(reason);
                return proxy;
            }

            index = queue.indexOf(item);
            if (index >= 0) {
                queue.splice(index, 1);
            }
            item.aborted = true;
            deferred.rejectWith(options && options.context ? options.context : options, [proxy, reason, reason]);
            return proxy;
        };

        queue.push(item);
        schedulePoll();
        return proxy;
    };

    root.RssInfoBoardAjaxGate = {
        isInfoBoardFetch: isInfoBoardFetch,
        canStart: canStart,
        pendingCount: function () {
            return queue.length + (running ? 1 : 0);
        }
    };
}(typeof window !== 'undefined' ? window : null));

/* V1.26-D phased Dashboard bootstrap: keep the Information Board presentation,
 * ticker, and navigation isolated from the legacy-sized dashboard.js surface. */
(function (root, document) {
    'use strict';

    if (!root || !document) {
        return;
    }

    var current = document.currentScript;
    var assetQuery = '';
    if (current && current.src) {
        var queryIndex = current.src.indexOf('?');
        assetQuery = queryIndex >= 0 ? current.src.slice(queryIndex) : '';
    }

    if (!document.querySelector('script[data-info-board-v126c-script]')) {
        var infoBoardScript = document.createElement('script');
        infoBoardScript.src = './js/info-board.js' + assetQuery;
        infoBoardScript.async = false;
        infoBoardScript.setAttribute('data-info-board-v126c-script', 'true');
        document.head.appendChild(infoBoardScript);
    }

    if (!document.querySelector('script[data-info-board-v126d-script]')) {
        var tickerScript = document.createElement('script');
        tickerScript.src = './js/info-board-ticker.js' + assetQuery;
        tickerScript.async = false;
        tickerScript.setAttribute('data-info-board-v126d-script', 'true');
        document.head.appendChild(tickerScript);
    }

    if (!document.querySelector('script[data-info-board-v126d-navigation-script]')) {
        var navigationScript = document.createElement('script');
        navigationScript.src = './js/info-board-navigation.js' + assetQuery;
        navigationScript.async = false;
        navigationScript.setAttribute('data-info-board-v126d-navigation-script', 'true');
        document.head.appendChild(navigationScript);
    }
}(typeof window !== 'undefined' ? window : null, typeof document !== 'undefined' ? document : null));
