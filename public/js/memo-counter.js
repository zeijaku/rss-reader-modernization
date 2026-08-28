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

/* V1.26-C phased Dashboard bootstrap: reuse this always-loaded small asset
 * instead of widening the main dashboard.js or PHP view surface in C. */
(function (root, document) {
    'use strict';

    if (!root || !document || document.querySelector('script[data-info-board-v126c-script]')) {
        return;
    }

    var current = document.currentScript;
    var assetQuery = '';
    if (current && current.src) {
        var queryIndex = current.src.indexOf('?');
        assetQuery = queryIndex >= 0 ? current.src.slice(queryIndex) : '';
    }

    var script = document.createElement('script');
    script.src = './js/info-board.js' + assetQuery;
    script.async = false;
    script.setAttribute('data-info-board-v126c-script', 'true');
    document.head.appendChild(script);
}(typeof window !== 'undefined' ? window : null, typeof document !== 'undefined' ? document : null));
