/* V1.26-D: Information Board ticker / auto-scroll behavior. */
(function (window, document) {
    'use strict';

    var CARD_SELECTOR = '.info-board-card[data-info-board="1"]';
    var SPEED_DELAYS = {
        slow: 6500,
        normal: 4200,
        fast: 2500
    };
    var INTERACTION_RESUME_DELAY = 5000;
    var LAYOUT_RECHECK_DELAY = 80;
    var globalObserver = null;
    var resizeTimer = null;
    var reducedMotionQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    function normalizeSpeed(value) {
        value = String(value || 'normal');
        return Object.prototype.hasOwnProperty.call(SPEED_DELAYS, value) ? value : 'normal';
    }

    function delayForSpeed(value) {
        return SPEED_DELAYS[normalizeSpeed(value)];
    }

    function reducedMotionPreferred() {
        return !!(reducedMotionQuery && reducedMotionQuery.matches === true);
    }

    function stateFor(card) {
        if (!card.__infoBoardTickerState) {
            card.__infoBoardTickerState = {
                timer: null,
                interactionTimer: null,
                evaluateTimer: null,
                index: 0,
                userPaused: false,
                hovered: false,
                focused: false,
                interactionUntil: 0,
                list: null,
                listObserver: null,
                cardObserver: null,
                toggle: null
            };
        }
        return card.__infoBoardTickerState;
    }

    function clearTimer(state) {
        if (state.timer !== null) {
            window.clearTimeout(state.timer);
            state.timer = null;
        }
    }

    function clearInteractionTimer(state) {
        if (state.interactionTimer !== null) {
            window.clearTimeout(state.interactionTimer);
            state.interactionTimer = null;
        }
    }

    function clearEvaluateTimer(state) {
        if (state.evaluateTimer !== null) {
            window.clearTimeout(state.evaluateTimer);
            state.evaluateTimer = null;
        }
    }

    function itemsFor(list) {
        if (!list) {
            return [];
        }
        return Array.prototype.slice.call(list.querySelectorAll('.info-board-item'));
    }

    function maxScrollTop(list) {
        return Math.max(0, Number(list.scrollHeight || 0) - Number(list.clientHeight || 0));
    }

    function isScrollable(list, items) {
        return !!list && items.length > 1 && maxScrollTop(list) > 6;
    }

    function itemTargetTop(list, item) {
        if (!list || !item) {
            return 0;
        }
        var top = 0;
        if (typeof item.getBoundingClientRect === 'function'
            && typeof list.getBoundingClientRect === 'function') {
            var itemRect = item.getBoundingClientRect();
            var listRect = list.getBoundingClientRect();
            top = Number(list.scrollTop || 0) + Number(itemRect.top || 0) - Number(listRect.top || 0);
        } else {
            top = Number(item.offsetTop || 0) - Number(list.offsetTop || 0);
        }
        return Math.max(0, Math.min(maxScrollTop(list), top));
    }

    function closestItemIndex(list, items) {
        if (!list || !items || items.length === 0) {
            return 0;
        }
        var currentTop = Number(list.scrollTop || 0);
        var bestIndex = 0;
        var bestDistance = Infinity;
        for (var i = 0; i < items.length; i++) {
            var distance = Math.abs(itemTargetTop(list, items[i]) - currentTop);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestIndex = i;
            }
        }
        return bestIndex;
    }

    function scrollListTo(list, top) {
        top = Math.max(0, Math.min(maxScrollTop(list), Number(top || 0)));
        if (typeof list.scrollTo === 'function') {
            list.scrollTo({
                top: top,
                left: 0,
                behavior: reducedMotionPreferred() ? 'auto' : 'smooth'
            });
            return;
        }
        list.scrollTop = top;
    }

    function currentSpeed(card) {
        var config = card && card.__infoBoardConfig ? card.__infoBoardConfig : {};
        return normalizeSpeed(config.speed || card.getAttribute('data-info-board-speed') || 'normal');
    }

    function createIcon(className) {
        var icon = document.createElement('i');
        icon.className = className;
        icon.setAttribute('aria-hidden', 'true');
        return icon;
    }

    function ensureToggle(card, state) {
        var existing = card.querySelector('.info-board-motion-toggle');
        if (existing) {
            state.toggle = existing;
            return existing;
        }

        var actions = card.querySelector('.feed-card-actions, .content-actions');
        if (!actions) {
            return null;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link info-board-motion-toggle';
        button.setAttribute('data-dashboard-swipe-ignore', 'true');
        button.appendChild(createIcon('fas fa-pause'));

        var refresh = actions.querySelector('.info-board-refresh-trigger, .search-feed-refresh');
        if (refresh) {
            actions.insertBefore(button, refresh);
        } else {
            actions.appendChild(button);
        }

        button.addEventListener('click', function () {
            var activeState = stateFor(card);
            if (button.disabled) {
                return;
            }
            activeState.userPaused = !activeState.userPaused;
            if (!activeState.userPaused) {
                activeState.index = closestItemIndex(activeState.list, itemsFor(activeState.list));
            }
            evaluateCard(card);
        });

        state.toggle = button;
        return button;
    }

    function updateToggle(card, state, mode) {
        var button = ensureToggle(card, state);
        if (!button) {
            return;
        }

        var icon = button.querySelector('i');
        button.disabled = false;
        button.setAttribute('aria-pressed', state.userPaused ? 'true' : 'false');

        if (mode === 'loading') {
            button.disabled = true;
            button.setAttribute('aria-label', 'Information Boardの自動送りは準備中です');
            button.setAttribute('title', '自動送り準備中');
            if (icon) { icon.className = 'fas fa-hourglass-half'; }
            return;
        }

        if (mode === 'static') {
            button.disabled = true;
            button.setAttribute('aria-label', 'Information Boardはすべて表示されているため自動送りは不要です');
            button.setAttribute('title', '自動送り不要');
            if (icon) { icon.className = 'fas fa-minus'; }
            return;
        }

        if (mode === 'reduced') {
            button.disabled = true;
            button.setAttribute('aria-label', '端末の視差効果設定によりInformation Boardの自動送りを停止しています');
            button.setAttribute('title', '視差効果を減らす設定により自動送り停止中');
            if (icon) { icon.className = 'fas fa-universal-access'; }
            return;
        }

        if (state.userPaused) {
            button.setAttribute('aria-label', 'Information Boardの自動送りを再開');
            button.setAttribute('title', '自動送りを再開');
            if (icon) { icon.className = 'fas fa-play'; }
            return;
        }

        button.setAttribute('aria-label', 'Information Boardの自動送りを一時停止');
        button.setAttribute('title', mode === 'interaction' ? '操作中のため一時停止中' : '自動送りを一時停止');
        if (icon) { icon.className = 'fas fa-pause'; }
    }

    function motionBlocked(state) {
        return state.userPaused
            || state.hovered
            || state.focused
            || Number(state.interactionUntil || 0) > Date.now()
            || document.hidden === true;
    }

    function setMotionMode(card, state, mode) {
        card.setAttribute('data-info-board-motion', mode);
        updateToggle(card, state, mode);
    }

    function scheduleNext(card, state) {
        clearTimer(state);
        state.timer = window.setTimeout(function () {
            state.timer = null;
            advanceCard(card);
        }, delayForSpeed(currentSpeed(card)));
    }

    function advanceCard(card) {
        var state = stateFor(card);
        var list = state.list || card.querySelector('.info-board-list');
        var items = itemsFor(list);

        if (!isScrollable(list, items)
            || reducedMotionPreferred()
            || motionBlocked(state)
            || String(card.getAttribute('data-info-board-state') || '') !== 'ready') {
            evaluateCard(card);
            return;
        }

        var nextIndex = (Number(state.index || 0) + 1) % items.length;
        state.index = nextIndex;
        scrollListTo(list, nextIndex === 0 ? 0 : itemTargetTop(list, items[nextIndex]));
        scheduleNext(card, state);
    }

    function evaluateCard(card) {
        if (!card || !document.documentElement.contains(card)) {
            return;
        }

        var state = stateFor(card);
        clearTimer(state);
        bindList(card, state);

        var list = state.list;
        var items = itemsFor(list);
        var boardState = String(card.getAttribute('data-info-board-state') || '');
        var speed = currentSpeed(card);
        card.setAttribute('data-info-board-speed', speed);

        if (boardState !== 'ready' || !list) {
            setMotionMode(card, state, 'loading');
            return;
        }

        if (!isScrollable(list, items)) {
            state.index = 0;
            setMotionMode(card, state, 'static');
            return;
        }

        if (reducedMotionPreferred()) {
            state.index = closestItemIndex(list, items);
            setMotionMode(card, state, 'reduced');
            return;
        }

        if (motionBlocked(state)) {
            state.index = closestItemIndex(list, items);
            setMotionMode(card, state, state.userPaused ? 'paused' : 'interaction');
            return;
        }

        state.index = closestItemIndex(list, items);
        setMotionMode(card, state, 'running');
        scheduleNext(card, state);
    }

    function queueEvaluate(card, delay) {
        var state = stateFor(card);
        clearEvaluateTimer(state);
        state.evaluateTimer = window.setTimeout(function () {
            state.evaluateTimer = null;
            evaluateCard(card);
        }, typeof delay === 'number' ? delay : LAYOUT_RECHECK_DELAY);
    }

    function pauseForInteraction(card, delay) {
        var state = stateFor(card);
        state.interactionUntil = Math.max(
            Number(state.interactionUntil || 0),
            Date.now() + Math.max(0, Number(delay || INTERACTION_RESUME_DELAY))
        );
        state.index = closestItemIndex(state.list, itemsFor(state.list));
        clearInteractionTimer(state);
        state.interactionTimer = window.setTimeout(function () {
            state.interactionTimer = null;
            state.interactionUntil = 0;
            evaluateCard(card);
        }, Math.max(0, Number(delay || INTERACTION_RESUME_DELAY)) + 25);
        evaluateCard(card);
    }

    function bindList(card, state) {
        var list = card.querySelector('.info-board-list');
        if (state.list === list) {
            return;
        }

        if (state.listObserver) {
            state.listObserver.disconnect();
            state.listObserver = null;
        }

        state.list = list;
        state.index = 0;
        if (!list) {
            return;
        }

        list.addEventListener('wheel', function () {
            pauseForInteraction(card, INTERACTION_RESUME_DELAY);
        }, {passive: true});

        list.addEventListener('touchstart', function () {
            pauseForInteraction(card, INTERACTION_RESUME_DELAY);
        }, {passive: true});

        list.addEventListener('touchend', function () {
            pauseForInteraction(card, INTERACTION_RESUME_DELAY);
        }, {passive: true});

        if (typeof MutationObserver === 'function') {
            state.listObserver = new MutationObserver(function () {
                queueEvaluate(card, LAYOUT_RECHECK_DELAY);
            });
            state.listObserver.observe(list, {childList: true, subtree: true});
        }
    }

    function bindCardInteractions(card, state) {
        card.addEventListener('mouseenter', function () {
            state.hovered = true;
            evaluateCard(card);
        });
        card.addEventListener('mouseleave', function () {
            state.hovered = false;
            evaluateCard(card);
        });
        card.addEventListener('focusin', function () {
            state.focused = true;
            evaluateCard(card);
        });
        card.addEventListener('focusout', function () {
            window.setTimeout(function () {
                state.focused = !!(document.activeElement && card.contains(document.activeElement));
                evaluateCard(card);
            }, 0);
        });
    }

    function prepareCard(card) {
        if (!card) {
            return;
        }

        var state = stateFor(card);
        ensureToggle(card, state);
        bindList(card, state);

        if (card.getAttribute('data-info-board-ticker-bound') !== '1') {
            card.setAttribute('data-info-board-ticker-bound', '1');
            bindCardInteractions(card, state);

            if (typeof MutationObserver === 'function') {
                state.cardObserver = new MutationObserver(function () {
                    queueEvaluate(card, LAYOUT_RECHECK_DELAY);
                });
                state.cardObserver.observe(card, {
                    attributes: true,
                    attributeFilter: ['data-info-board-state'],
                    childList: true,
                    subtree: true
                });
            }
        }

        queueEvaluate(card, LAYOUT_RECHECK_DELAY);
    }

    function prepareAllCards() {
        var cards = document.querySelectorAll(CARD_SELECTOR);
        for (var i = 0; i < cards.length; i++) {
            prepareCard(cards[i]);
        }
    }

    function evaluateAllCards() {
        var cards = document.querySelectorAll(CARD_SELECTOR);
        for (var i = 0; i < cards.length; i++) {
            evaluateCard(cards[i]);
        }
    }

    function updateModalCopy() {
        var registerIntro = document.querySelector('#registerInfoBoard .modal-body > p.small.text-muted');
        var introText = 'RSSのNEWSをInformation Board形式で自動送り表示します。記事本文の追加取得は行いません。';
        if (registerIntro && registerIntro.textContent !== introText) {
            registerIntro.textContent = introText;
        }

        ['register', 'change'].forEach(function (prefix) {
            var select = document.querySelector('.' + prefix + 'InfoBoardSpeed');
            var parent = select ? select.parentElement : null;
            var help = parent ? parent.querySelector('.form-text') : null;
            var helpText = '自動送りの切り替え間隔を slow / normal / fast から指定します。';
            if (help && help.textContent !== helpText) {
                help.textContent = helpText;
            }
        });
    }

    function bindGlobalEvents() {
        document.addEventListener('visibilitychange', function () {
            evaluateAllCards();
        });

        window.addEventListener('resize', function () {
            if (resizeTimer !== null) {
                window.clearTimeout(resizeTimer);
            }
            resizeTimer = window.setTimeout(function () {
                resizeTimer = null;
                evaluateAllCards();
            }, 120);
        });

        window.addEventListener('load', function () {
            evaluateAllCards();
        });

        if (reducedMotionQuery) {
            var listener = function () {
                evaluateAllCards();
            };
            if (typeof reducedMotionQuery.addEventListener === 'function') {
                reducedMotionQuery.addEventListener('change', listener);
            } else if (typeof reducedMotionQuery.addListener === 'function') {
                reducedMotionQuery.addListener(listener);
            }
        }
    }

    function watchDashboard() {
        if (typeof MutationObserver !== 'function' || !document.body) {
            return;
        }
        globalObserver = new MutationObserver(function () {
            prepareAllCards();
            updateModalCopy();
        });
        globalObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-info-board']
        });
    }

    function init() {
        prepareAllCards();
        updateModalCopy();
        bindGlobalEvents();
        watchDashboard();
    }

    window.RssInfoBoardTicker = {
        normalizeSpeed: normalizeSpeed,
        delayForSpeed: delayForSpeed,
        reducedMotionPreferred: reducedMotionPreferred,
        prepareAllCards: prepareAllCards,
        evaluateAllCards: evaluateAllCards,
        interactionResumeDelay: INTERACTION_RESUME_DELAY
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
