/* V1.26-D: Information Board article navigation and footer metadata. */
(function (window, document) {
    'use strict';

    var CARD_SELECTOR = '.info-board-card[data-info-board="1"]';
    var INTERACTION_RESUME_DELAY = 5000;
    var globalObserver = null;

    function itemsFor(card) {
        var list = card ? card.querySelector('.info-board-list') : null;
        return list ? Array.prototype.slice.call(list.querySelectorAll('.info-board-item')) : [];
    }

    function wrappedIndex(current, delta, length) {
        length = Math.max(0, Number(length || 0));
        if (length <= 0) {
            return 0;
        }
        current = Number(current || 0);
        delta = Number(delta || 0);
        return ((current + delta) % length + length) % length;
    }

    function itemMetaParts(item) {
        var meta = item ? item.querySelector('.info-board-item-meta') : null;
        var text = meta ? String(meta.textContent || '').trim() : '';
        var dateLabel = '';
        var sourceTitle = '';

        if (text.indexOf(' · ') >= 0) {
            var parts = text.split(' · ');
            dateLabel = String(parts.shift() || '').trim();
            sourceTitle = parts.join(' · ').trim();
        } else if (/^\d{1,2}\/\d{1,2}(?:\s|$)/.test(text)) {
            dateLabel = text;
        } else {
            sourceTitle = text;
        }

        return {
            sourceTitle: sourceTitle || 'RSS',
            dateLabel: dateLabel
        };
    }

    function footerMetaLabel(sourceTitle, dateLabel, index, total) {
        var parts = [];
        sourceTitle = String(sourceTitle || '').trim();
        dateLabel = String(dateLabel || '').trim();
        if (sourceTitle !== '') {
            parts.push(sourceTitle);
        }
        if (dateLabel !== '') {
            parts.push(dateLabel);
        }
        if (Number(total || 0) > 0) {
            parts.push(String(Number(index || 0) + 1) + ' / ' + String(Number(total || 0)));
        }
        return parts.join(' ｜ ');
    }

    function setTextIfChanged(node, value) {
        if (!node) {
            return false;
        }
        value = String(value == null ? '' : value);
        if (String(node.textContent || '') === value) {
            return false;
        }
        node.textContent = value;
        return true;
    }

    function setHiddenIfChanged(node, hidden) {
        if (!node) {
            return false;
        }
        hidden = hidden === true;
        if (node.hidden === hidden) {
            return false;
        }
        node.hidden = hidden;
        return true;
    }

    function mutationIsInsideFooter(mutation) {
        var target = mutation && mutation.target ? mutation.target : null;
        return !!(target && typeof target.closest === 'function' && target.closest('.info-board-footer'));
    }

    function dashboardMutationNeedsRefresh(records) {
        if (!records || records.length === 0) {
            return true;
        }
        for (var i = 0; i < records.length; i++) {
            if (records[i].type === 'childList' && mutationIsInsideFooter(records[i])) {
                continue;
            }
            return true;
        }
        return false;
    }

    function activeIndex(card, items) {
        if (!items.length) {
            return 0;
        }
        var tickerState = card.__infoBoardTickerState || null;
        if (tickerState && Number(tickerState.index) >= 0 && Number(tickerState.index) < items.length) {
            return Number(tickerState.index);
        }
        for (var i = 0; i < items.length; i++) {
            if (items[i].classList.contains('is-active')) {
                return i;
            }
        }
        return 0;
    }

    function createNavButton(className, label, iconClass) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link info-board-nav-button ' + className;
        button.setAttribute('data-dashboard-swipe-ignore', 'true');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        var icon = document.createElement('i');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');
        button.appendChild(icon);
        return button;
    }

    function ensureFooter(card) {
        var body = card ? card.querySelector('.info-board-card-body') : null;
        if (!body) {
            return null;
        }

        var existing = body.querySelector('.info-board-footer');
        if (existing) {
            return existing;
        }

        var footer = document.createElement('div');
        footer.className = 'info-board-footer';
        footer.setAttribute('data-dashboard-swipe-ignore', 'true');
        footer.hidden = true;

        var previous = createNavButton('info-board-nav-prev', '前の記事へ戻る', 'fas fa-chevron-left');
        var meta = document.createElement('div');
        meta.className = 'info-board-footer-meta';
        meta.setAttribute('aria-live', 'off');
        var next = createNavButton('info-board-nav-next', '次の記事へ進む', 'fas fa-chevron-right');

        previous.addEventListener('click', function () {
            navigate(card, -1);
        });
        next.addEventListener('click', function () {
            navigate(card, 1);
        });

        footer.appendChild(previous);
        footer.appendChild(meta);
        footer.appendChild(next);
        body.appendChild(footer);
        return footer;
    }

    function updateFooter(card) {
        var footer = ensureFooter(card);
        if (!footer) {
            return;
        }

        var items = itemsFor(card);
        var previous = footer.querySelector('.info-board-nav-prev');
        var next = footer.querySelector('.info-board-nav-next');
        var meta = footer.querySelector('.info-board-footer-meta');

        if (!items.length || String(card.getAttribute('data-info-board-state') || '') !== 'ready') {
            setHiddenIfChanged(footer, true);
            setTextIfChanged(meta, '');
            return;
        }

        var index = activeIndex(card, items);
        var parts = itemMetaParts(items[index]);
        var label = footerMetaLabel(parts.sourceTitle, parts.dateLabel, index, items.length);
        setHiddenIfChanged(footer, false);
        if (meta) {
            setTextIfChanged(meta, label);
            if (meta.title !== label) {
                meta.title = label;
            }
        }
        if (previous && previous.disabled !== (items.length <= 1)) {
            previous.disabled = items.length <= 1;
        }
        if (next && next.disabled !== (items.length <= 1)) {
            next.disabled = items.length <= 1;
        }
    }

    function cancelTickerMotion(state) {
        if (!state) {
            return;
        }
        if (state.frame !== null && state.frame !== undefined) {
            if (typeof window.cancelAnimationFrame === 'function') {
                window.cancelAnimationFrame(state.frame);
            } else {
                window.clearTimeout(state.frame);
            }
            state.frame = null;
        }
        if (state.timer !== null && state.timer !== undefined) {
            window.clearTimeout(state.timer);
            state.timer = null;
        }
        state.lastFrameAt = null;
    }

    function scheduleTickerResume(card, state) {
        if (!state) {
            return;
        }
        if (state.interactionTimer !== null && state.interactionTimer !== undefined) {
            window.clearTimeout(state.interactionTimer);
        }
        state.interactionUntil = Date.now() + INTERACTION_RESUME_DELAY;
        state.interactionTimer = window.setTimeout(function () {
            state.interactionTimer = null;
            state.interactionUntil = 0;
            if (window.RssInfoBoardTicker && typeof window.RssInfoBoardTicker.evaluateAllCards === 'function') {
                window.RssInfoBoardTicker.evaluateAllCards(false);
            }
        }, INTERACTION_RESUME_DELAY + 25);
    }

    function navigate(card, delta) {
        var items = itemsFor(card);
        if (items.length <= 1) {
            return;
        }

        var state = card.__infoBoardTickerState || null;
        var current = activeIndex(card, items);
        var target = wrappedIndex(current, delta, items.length);

        if (state) {
            cancelTickerMotion(state);
            state.index = target;
            state.activeItem = null;
            state.lane = null;
            state.summary = null;
            state.x = null;
            state.phase = 'idle';
            state.needsRestart = true;
            scheduleTickerResume(card, state);
        } else {
            for (var i = 0; i < items.length; i++) {
                var active = i === target;
                items[i].classList.toggle('is-active', active);
                items[i].setAttribute('aria-hidden', active ? 'false' : 'true');
            }
        }

        if (window.RssInfoBoardTicker && typeof window.RssInfoBoardTicker.evaluateAllCards === 'function') {
            window.RssInfoBoardTicker.evaluateAllCards(false);
        }
        updateFooter(card);
    }

    function bindCard(card) {
        if (!card) {
            return;
        }
        ensureFooter(card);
        if (card.getAttribute('data-info-board-navigation-bound') !== '1') {
            card.setAttribute('data-info-board-navigation-bound', '1');
            var list = card.querySelector('.info-board-list');
            if (list && typeof MutationObserver === 'function') {
                var observer = new MutationObserver(function () {
                    updateFooter(card);
                });
                observer.observe(list, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class']
                });
                card.__infoBoardNavigationObserver = observer;
            }
        }
        updateFooter(card);
    }

    function prepareAllCards() {
        var cards = document.querySelectorAll(CARD_SELECTOR);
        for (var i = 0; i < cards.length; i++) {
            bindCard(cards[i]);
        }
    }

    function updateModalCopy() {
        var registerIntro = document.querySelector('#registerInfoBoard .modal-body > p.small.text-muted');
        var introText = 'RSSのNEWSを、タイトルを固定し概要だけ右から左へ流すInformation Board形式で表示します。概要はRSS内のdescription/contentから可能な限り利用し、最大3000文字まで表示します。記事ページの追加取得は行いません。';
        if (registerIntro && registerIntro.textContent !== introText) {
            registerIntro.textContent = introText;
        }

        ['register', 'change'].forEach(function (prefix) {
            var summarySelect = document.querySelector('.' + prefix + 'InfoBoardSummaryMax');
            var summaryWrap = summarySelect ? summarySelect.parentElement : null;
            if (summaryWrap && summaryWrap.hidden !== true) {
                summaryWrap.hidden = true;
            }
        });
    }

    function watchDashboard() {
        if (typeof MutationObserver !== 'function' || !document.body) {
            return;
        }
        globalObserver = new MutationObserver(function (records) {
            if (!dashboardMutationNeedsRefresh(records)) {
                return;
            }
            prepareAllCards();
            updateModalCopy();
        });
        globalObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-info-board', 'data-info-board-state']
        });
    }

    function init() {
        prepareAllCards();
        updateModalCopy();
        watchDashboard();
    }

    window.RssInfoBoardNavigation = {
        wrappedIndex: wrappedIndex,
        footerMetaLabel: footerMetaLabel,
        setTextIfChanged: setTextIfChanged,
        dashboardMutationNeedsRefresh: dashboardMutationNeedsRefresh,
        prepareAllCards: prepareAllCards
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
