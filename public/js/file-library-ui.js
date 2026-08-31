(function (document, window) {
    'use strict';

    function setText(element, value) {
        if (element) {
            element.textContent = value;
        }
    }

    function finalizeActionGroups() {
        var cards = document.querySelectorAll('.file-library-card');
        var i;
        for (i = 0; i < cards.length; i++) {
            var card = cards[i];
            var actions = card.querySelector('.file-library-actions');
            var nameElement = card.querySelector('.file-library-name');
            var fileName = nameElement ? nameElement.textContent.trim() : '';
            var count;
            if (!actions) { continue; }
            count = actions.children.length;
            actions.classList.remove('file-library-actions-count-2', 'file-library-actions-count-3', 'file-library-actions-count-4');
            actions.classList.add('file-library-actions-count-' + Math.min(4, Math.max(2, count)));
            actions.setAttribute('role', 'group');
            actions.setAttribute('aria-label', (fileName || '\u30d5\u30a1\u30a4\u30eb') + '\u306e\u64cd\u4f5c');
        }
    }

    function localizeDetailModal() {
        var labels = document.querySelectorAll('#fileLibraryDetailContent dt');
        var values = [
            '\u30d5\u30a1\u30a4\u30eb\u540d',
            'MIME\u30bf\u30a4\u30d7',
            '\u30b5\u30a4\u30ba',
            '\u753b\u50cf\u30b5\u30a4\u30ba',
            '\u767b\u9332\u65e5\u6642',
            '\u30d5\u30a1\u30a4\u30ebID'
        ];
        var i;
        for (i = 0; i < labels.length && i < values.length; i++) {
            setText(labels[i], values[i]);
        }
    }

    function finalizeFileLibraryUi() {
        var badge = document.querySelector('.file-library-toolbar .badge');
        if (badge) {
            badge.textContent = 'V1.28-F';
        }
        finalizeActionGroups();
        localizeDetailModal();
        window.setTimeout(function () {
            finalizeActionGroups();
            localizeDetailModal();
            var lateBadge = document.querySelector('.file-library-toolbar .badge');
            if (lateBadge) {
                lateBadge.textContent = 'V1.28-F';
            }
        }, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', finalizeFileLibraryUi);
    } else {
        finalizeFileLibraryUi();
    }
})(document, window);
