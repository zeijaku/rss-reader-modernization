/* V1.26-D: Information Board horizontal summary ticker behavior. */
(function (window, document) {
    'use strict';

    var CARD_SELECTOR = '.info-board-card[data-info-board="1"]';
    var SPEED_PIXELS = {
        slow: 70,
        normal: 105,
        fast: 150
    };
    var TITLE_ONLY_DELAYS = {
        slow: 4200,
        normal: 3000,
        fast: 2200
    };
    var ITEM_GAP_DELAY = 500;
    var INTERACTION_RESUME_DELAY = 5000;
    var LAYOUT_RECHECK_DELAY = 80;
    var globalObserver = null;
    var resizeTimer = null;
    var reducedMotionQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    function normalizeSpeed(value) {
        value = String(value || 'normal');
        return Object.prototype.hasOwnProperty.call(SPEED_PIXELS, value) ? value : 'normal';
    }

    function pixelsForSpeed(value) {
        return SPEED_PIXELS[normalizeSpeed(value)];
    }

    function titleOnlyDelay(value) {
        return TITLE_ONLY_DELAYS[normalizeSpeed(value)];
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

    function clampProgress(value) {
        value = Number(value || 0);
        if (!Number.isFinite(value)) {
            return 0;
        }
        return Math.max(0, Math.min(1, value));
    }

    function progressForPosition(laneWidth, summaryWidth, x) {
        laneWidth = Math.max(0, Number(laneWidth || 0));
        summaryWidth = Math.max(0, Number(summaryWidth || 0));
        x = Number(x);
        var travel = laneWidth + summaryWidth;
        if (!Number.isFinite(x) || travel <= 0) {
            return 0;
        }
        return clampProgress((laneWidth - x) / travel);
    }

    function itemTitleText(item) {
        var title = item ? item.querySelector('.info-board-item-title') : null;
        return title ? String(title.textContent || '').trim() : '';
    }

    function reducedMotionPreferred() {
        return !!(reducedMotionQuery && reducedMotionQuery.matches === true);
    }

    function requestFrame(callback) {
        if (typeof window.requestAnimationFrame === 'function') {
            return window.requestAnimationFrame(callback);
        }
        return window.setTimeout(function () {
            callback(Date.now());
        }, 16);
    }

    function cancelFrame(frameId) {
        if (frameId === null) {
            return;
        }
        if (typeof window.cancelAnimationFrame === 'function') {
            window.cancelAnimationFrame(frameId);
            return;
        }
        window.clearTimeout(frameId);
    }

    function stateFor(card) {
        if (!card.__infoBoardTickerState) {
            card.__infoBoardTickerState = {
                frame: null,
                timer: null,
                interactionTimer: null,
                evaluateTimer: null,
                index: 0,
                x: null,
                lastFrameAt: null,
                phase: 'idle',
                needsRestart: true,
                userPaused: false,
                hovered: false,
                focused: false,
                interactionUntil: 0,
                list: null,
                listObserver: null,
                cardObserver: null,
                toggle: null,
                footer: null,
                footerMeta: null,
                previousButton: null,
                nextButton: null,
                extras: null,
                nextRow: null,
                nextTitle: null,
                progressTrack: null,
                progressBar: null,
                progressValue: null,
                activeItem: null,
                lane: null,
                summary: null
            };
        }
        return card.__infoBoardTickerState;
    }

    function clearFrame(state) {
        if (state.frame !== null) {
            cancelFrame(state.frame);
            state.frame = null;
        }
        state.lastFrameAt = null;
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
            evaluateCard(card);
        });

        state.toggle = button;
        return button;
    }

    function ensureFooter(card, state) {
        var body = card ? card.querySelector('.info-board-card-body') : null;
        if (!body) {
            return null;
        }

        var footer = body.querySelector('.info-board-footer');
        if (!footer) {
            footer = document.createElement('div');
            footer.className = 'info-board-footer';
            footer.setAttribute('data-dashboard-swipe-ignore', 'true');
            footer.hidden = true;

            var previous = document.createElement('button');
            previous.type = 'button';
            previous.className = 'btn btn-link info-board-nav-button info-board-nav-previous';
            previous.setAttribute('aria-label', '前の記事');
            previous.setAttribute('title', '前の記事');
            previous.appendChild(createIcon('fas fa-chevron-left'));

            var meta = document.createElement('div');
            meta.className = 'info-board-footer-meta';
            meta.setAttribute('aria-live', 'off');

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'btn btn-link info-board-nav-button info-board-nav-next';
            next.setAttribute('aria-label', '次の記事');
            next.setAttribute('title', '次の記事');
            next.appendChild(createIcon('fas fa-chevron-right'));

            footer.appendChild(previous);
            footer.appendChild(meta);
            footer.appendChild(next);
            body.appendChild(footer);

            previous.addEventListener('click', function () {
                navigateItem(card, -1);
            });
            next.addEventListener('click', function () {
                navigateItem(card, 1);
            });
        }

        state.footer = footer;
        state.footerMeta = footer.querySelector('.info-board-footer-meta');
        state.previousButton = footer.querySelector('.info-board-nav-previous');
        state.nextButton = footer.querySelector('.info-board-nav-next');
        return footer;
    }

    function updateFooter(card, state, items) {
        var footer = ensureFooter(card, state);
        items = Array.isArray(items) ? items : [];
        var ready = String(card.getAttribute('data-info-board-state') || '') === 'ready';
        if (!footer || !ready || items.length === 0) {
            if (footer) {
                setHiddenIfChanged(footer, true);
            }
            setTextIfChanged(state.footerMeta, '');
            return;
        }

        var index = Math.max(0, Math.min(items.length - 1, Number(state.index || 0)));
        var parts = itemMetaParts(items[index]);
        var label = footerMetaLabel(parts.sourceTitle, parts.dateLabel, index, items.length);
        setHiddenIfChanged(footer, false);
        setTextIfChanged(state.footerMeta, label);
        if (state.footerMeta && state.footerMeta.title !== label) {
            state.footerMeta.title = label;
        }
        if (state.previousButton) {
            state.previousButton.disabled = items.length <= 1;
        }
        if (state.nextButton) {
            state.nextButton.disabled = items.length <= 1;
        }
    }

    function ensureExtras(card, state) {
        var body = card ? card.querySelector('.info-board-card-body') : null;
        if (!body) {
            return null;
        }

        var extras = body.querySelector('.info-board-extras');
        if (!extras) {
            extras = document.createElement('div');
            extras.className = 'info-board-extras';
            extras.setAttribute('data-dashboard-swipe-ignore', 'true');
            extras.hidden = true;

            var nextRow = document.createElement('div');
            nextRow.className = 'info-board-next-row';

            var nextLabel = document.createElement('span');
            nextLabel.className = 'info-board-next-label';
            nextLabel.textContent = 'NEXT';

            var nextTitle = document.createElement('span');
            nextTitle.className = 'info-board-next-title';
            nextTitle.setAttribute('aria-live', 'off');

            nextRow.appendChild(nextLabel);
            nextRow.appendChild(nextTitle);

            var progressTrack = document.createElement('div');
            progressTrack.className = 'info-board-progress-track';
            progressTrack.setAttribute('aria-hidden', 'true');

            var progressBar = document.createElement('span');
            progressBar.className = 'info-board-progress-bar';
            progressTrack.appendChild(progressBar);

            extras.appendChild(nextRow);
            extras.appendChild(progressTrack);

            var footer = ensureFooter(card, state);
            if (footer && footer.parentNode === body) {
                body.insertBefore(extras, footer);
            } else {
                body.appendChild(extras);
            }
        }

        state.extras = extras;
        state.nextRow = extras.querySelector('.info-board-next-row');
        state.nextTitle = extras.querySelector('.info-board-next-title');
        state.progressTrack = extras.querySelector('.info-board-progress-track');
        state.progressBar = extras.querySelector('.info-board-progress-bar');
        return extras;
    }

    function setProgress(state, value, visible) {
        if (!state.progressTrack || !state.progressBar) {
            return;
        }
        visible = visible === true;
        setHiddenIfChanged(state.progressTrack, !visible);
        if (!visible) {
            state.progressValue = null;
            if (state.progressBar.style.transform !== 'scaleX(0)') {
                state.progressBar.style.transform = 'scaleX(0)';
            }
            return;
        }

        value = clampProgress(value);
        if (state.progressValue !== null && Math.abs(state.progressValue - value) < 0.001) {
            return;
        }
        state.progressValue = value;
        state.progressBar.style.transform = 'scaleX(' + value.toFixed(4) + ')';
    }

    function updateExtras(card, state, items) {
        var extras = ensureExtras(card, state);
        items = Array.isArray(items) ? items : [];
        var ready = String(card.getAttribute('data-info-board-state') || '') === 'ready';
        if (!extras || !ready || items.length === 0) {
            if (extras) {
                setHiddenIfChanged(extras, true);
            }
            setTextIfChanged(state.nextTitle, '');
            setProgress(state, 0, false);
            return;
        }

        var index = Math.max(0, Math.min(items.length - 1, Number(state.index || 0)));
        var hasNext = items.length > 1;
        var nextIndex = hasNext ? wrappedIndex(index, 1, items.length) : index;
        var nextText = hasNext ? itemTitleText(items[nextIndex]) : '';

        setHiddenIfChanged(extras, false);
        setHiddenIfChanged(state.nextRow, !hasNext);
        setTextIfChanged(state.nextTitle, nextText);
        if (state.nextTitle && state.nextTitle.title !== nextText) {
            state.nextTitle.title = nextText;
        }

        var showProgress = !!(state.summary && state.lane && !reducedMotionPreferred());
        if (!showProgress) {
            setProgress(state, 0, false);
            if (!hasNext) {
                setHiddenIfChanged(extras, true);
            }
            return;
        }

        var laneWidth = Math.max(0, Number(state.lane.clientWidth || 0));
        var summaryWidth = Math.max(1, Number(state.summary.scrollWidth || state.summary.offsetWidth || 1));
        var value = state.x === null ? 0 : progressForPosition(laneWidth, summaryWidth, state.x);
        setProgress(state, value, true);
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
            button.setAttribute('aria-label', 'Information BoardのTickerは準備中です');
            button.setAttribute('title', 'Ticker準備中');
            if (icon) { icon.className = 'fas fa-hourglass-half'; }
            return;
        }

        if (mode === 'static') {
            button.disabled = true;
            button.setAttribute('aria-label', 'Information Boardに自動送りする内容がありません');
            button.setAttribute('title', 'Ticker停止');
            if (icon) { icon.className = 'fas fa-minus'; }
            return;
        }

        if (mode === 'reduced') {
            button.disabled = true;
            button.setAttribute('aria-label', '端末の視差効果設定によりInformation BoardのTickerを停止しています');
            button.setAttribute('title', '視差効果を減らす設定によりTicker停止中');
            if (icon) { icon.className = 'fas fa-universal-access'; }
            return;
        }

        if (state.userPaused) {
            button.setAttribute('aria-label', 'Information BoardのTickerを再開');
            button.setAttribute('title', 'Tickerを再開');
            if (icon) { icon.className = 'fas fa-play'; }
            return;
        }

        button.setAttribute('aria-label', 'Information BoardのTickerを一時停止');
        button.setAttribute('title', mode === 'interaction' ? '操作中のためTicker一時停止中' : 'Tickerを一時停止');
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

    function ensureSummaryLane(item) {
        if (!item) {
            return null;
        }
        var summary = item.querySelector('.info-board-item-summary');
        if (!summary) {
            return null;
        }
        var parent = summary.parentElement;
        if (parent && parent.classList && parent.classList.contains('info-board-summary-lane')) {
            return parent;
        }

        var lane = document.createElement('div');
        lane.className = 'info-board-summary-lane';
        lane.setAttribute('aria-label', '概要');
        summary.parentNode.insertBefore(lane, summary);
        lane.appendChild(summary);
        return lane;
    }

    function prepareSummaryLanes(list) {
        var items = itemsFor(list);
        for (var i = 0; i < items.length; i++) {
            ensureSummaryLane(items[i]);
        }
    }

    function activateItem(card, state, items, index) {
        if (!items.length) {
            state.activeItem = null;
            state.lane = null;
            state.summary = null;
            return null;
        }

        index = Math.max(0, Math.min(items.length - 1, Number(index || 0)));
        state.index = index;
        for (var i = 0; i < items.length; i++) {
            var active = i === index;
            items[i].classList.toggle('is-active', active);
            items[i].setAttribute('aria-hidden', active ? 'false' : 'true');
        }

        state.activeItem = items[index];
        state.lane = ensureSummaryLane(state.activeItem);
        state.summary = state.lane ? state.lane.querySelector('.info-board-item-summary') : null;
        updateFooter(card, state, items);
        updateExtras(card, state, items);
        return state.activeItem;
    }

    function resetSummaryPosition(state) {
        if (!state.lane || !state.summary) {
            state.x = null;
            return false;
        }

        var laneWidth = Math.max(0, Number(state.lane.clientWidth || 0));
        if (laneWidth <= 0) {
            return false;
        }
        state.x = laneWidth;
        state.summary.style.transform = 'translate3d(' + state.x + 'px, 0, 0)';
        setProgress(state, 0, true);
        return true;
    }

    function finishCurrentItem(card, state) {
        clearFrame(state);
        clearTimer(state);
        state.phase = 'gap';
        state.timer = window.setTimeout(function () {
            state.timer = null;
            advanceItem(card);
        }, ITEM_GAP_DELAY);
    }

    function runFrame(card, state, timestamp) {
        state.frame = null;

        if (String(card.getAttribute('data-info-board-state') || '') !== 'ready'
            || reducedMotionPreferred()
            || motionBlocked(state)
            || state.phase !== 'running'
            || !state.summary
            || !state.lane) {
            evaluateCard(card);
            return;
        }

        if (state.lastFrameAt === null) {
            state.lastFrameAt = Number(timestamp || Date.now());
        } else {
            var now = Number(timestamp || Date.now());
            var elapsed = Math.max(0, Math.min(100, now - state.lastFrameAt));
            state.lastFrameAt = now;
            state.x -= pixelsForSpeed(currentSpeed(card)) * (elapsed / 1000);
        }

        var summaryWidth = Math.max(1, Number(state.summary.scrollWidth || state.summary.offsetWidth || 1));
        var laneWidth = Math.max(0, Number(state.lane.clientWidth || 0));
        var endX = -summaryWidth;
        if (state.x <= endX) {
            state.x = endX;
            state.summary.style.transform = 'translate3d(' + state.x + 'px, 0, 0)';
            setProgress(state, 1, true);
            finishCurrentItem(card, state);
            return;
        }

        state.summary.style.transform = 'translate3d(' + state.x + 'px, 0, 0)';
        setProgress(state, progressForPosition(laneWidth, summaryWidth, state.x), true);
        state.frame = requestFrame(function (nextTimestamp) {
            runFrame(card, state, nextTimestamp);
        });
    }

    function startCurrentItem(card, state, restart) {
        var items = itemsFor(state.list);
        if (!items.length) {
            return false;
        }

        if (restart || !state.activeItem || items.indexOf(state.activeItem) === -1) {
            activateItem(card, state, items, state.index);
            state.phase = 'idle';
            state.x = null;
        }

        if (reducedMotionPreferred()) {
            if (state.summary) {
                state.summary.style.transform = 'none';
            }
            setProgress(state, 0, false);
            state.phase = 'reduced';
            return true;
        }

        if (!state.summary || !state.lane) {
            setProgress(state, 0, false);
            if (items.length <= 1) {
                state.phase = 'static';
                return false;
            }
            state.phase = 'title-only';
            clearTimer(state);
            state.timer = window.setTimeout(function () {
                state.timer = null;
                advanceItem(card);
            }, titleOnlyDelay(currentSpeed(card)));
            return true;
        }

        if (state.phase !== 'running') {
            if (!resetSummaryPosition(state)) {
                state.needsRestart = true;
                queueEvaluate(card, LAYOUT_RECHECK_DELAY);
                return true;
            }
            state.phase = 'running';
        }

        clearFrame(state);
        state.frame = requestFrame(function (timestamp) {
            runFrame(card, state, timestamp);
        });
        return true;
    }

    function advanceItem(card) {
        var state = stateFor(card);
        var items = itemsFor(state.list);
        if (!items.length) {
            evaluateCard(card);
            return;
        }

        state.index = (Number(state.index || 0) + 1) % items.length;
        state.activeItem = null;
        state.lane = null;
        state.summary = null;
        state.x = null;
        state.progressValue = null;
        state.phase = 'idle';
        state.needsRestart = true;
        evaluateCard(card);
    }

    function navigateItem(card, delta) {
        if (!card) {
            return;
        }
        var state = stateFor(card);
        bindList(card, state);
        var items = itemsFor(state.list);
        if (items.length <= 1) {
            updateFooter(card, state, items);
            return;
        }

        clearFrame(state);
        clearTimer(state);
        state.index = wrappedIndex(state.index, delta, items.length);
        state.activeItem = null;
        state.lane = null;
        state.summary = null;
        state.x = null;
        state.progressValue = null;
        state.phase = 'idle';
        state.needsRestart = true;
        pauseForInteraction(card, INTERACTION_RESUME_DELAY);
    }

    function evaluateCard(card) {
        if (!card || !document.documentElement.contains(card)) {
            return;
        }

        var state = stateFor(card);
        bindList(card, state);

        var list = state.list;
        var items = itemsFor(list);
        var boardState = String(card.getAttribute('data-info-board-state') || '');
        var speed = currentSpeed(card);
        card.setAttribute('data-info-board-speed', speed);

        if (boardState !== 'ready' || !list) {
            clearFrame(state);
            clearTimer(state);
            updateFooter(card, state, []);
            updateExtras(card, state, []);
            setMotionMode(card, state, 'loading');
            return;
        }

        if (!items.length) {
            clearFrame(state);
            clearTimer(state);
            updateFooter(card, state, items);
            updateExtras(card, state, items);
            setMotionMode(card, state, 'static');
            return;
        }

        if (state.index >= items.length) {
            state.index = 0;
            state.needsRestart = true;
        }

        if (state.needsRestart || !state.activeItem || items.indexOf(state.activeItem) === -1) {
            clearFrame(state);
            clearTimer(state);
            activateItem(card, state, items, state.index);
            state.phase = 'idle';
            state.x = null;
            state.needsRestart = false;
        }

        updateFooter(card, state, items);
        updateExtras(card, state, items);

        if (reducedMotionPreferred()) {
            clearFrame(state);
            clearTimer(state);
            if (state.summary) {
                state.summary.style.transform = 'none';
            }
            setProgress(state, 0, false);
            state.phase = 'reduced';
            setMotionMode(card, state, 'reduced');
            return;
        }

        if (motionBlocked(state)) {
            clearFrame(state);
            clearTimer(state);
            setMotionMode(card, state, state.userPaused ? 'paused' : 'interaction');
            return;
        }

        if (!state.summary && items.length <= 1) {
            clearFrame(state);
            clearTimer(state);
            setMotionMode(card, state, 'static');
            return;
        }

        setMotionMode(card, state, 'running');
        startCurrentItem(card, state, false);
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

        clearFrame(state);
        clearTimer(state);
        state.list = list;
        state.index = 0;
        state.activeItem = null;
        state.lane = null;
        state.summary = null;
        state.x = null;
        state.progressValue = null;
        state.phase = 'idle';
        state.needsRestart = true;
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

        prepareSummaryLanes(list);

        if (typeof MutationObserver === 'function') {
            state.listObserver = new MutationObserver(function () {
                state.listObserver.disconnect();
                prepareSummaryLanes(list);
                state.listObserver.observe(list, {childList: true, subtree: true});
                state.index = 0;
                state.activeItem = null;
                state.lane = null;
                state.summary = null;
                state.x = null;
                state.phase = 'idle';
                state.needsRestart = true;
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
        ensureFooter(card, state);
        ensureExtras(card, state);
        bindList(card, state);

        if (card.getAttribute('data-info-board-ticker-bound') !== '1') {
            card.setAttribute('data-info-board-ticker-bound', '1');
            bindCardInteractions(card, state);

            if (typeof MutationObserver === 'function') {
                state.cardObserver = new MutationObserver(function () {
                    state.needsRestart = true;
                    queueEvaluate(card, LAYOUT_RECHECK_DELAY);
                });
                state.cardObserver.observe(card, {
                    attributes: true,
                    attributeFilter: ['data-info-board-state']
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

    function evaluateAllCards(restart) {
        var cards = document.querySelectorAll(CARD_SELECTOR);
        for (var i = 0; i < cards.length; i++) {
            if (restart === true) {
                stateFor(cards[i]).needsRestart = true;
            }
            evaluateCard(cards[i]);
        }
    }

    function updateModalCopy() {
        var registerIntro = document.querySelector('#registerInfoBoard .modal-body > p.small.text-muted');
        var introText = 'RSSのNEWSを、タイトルを固定し概要だけ右から左へ流すInformation Board形式で表示します。RSS内の概要を可能な限り表示し、記事本文の追加取得は行いません。';
        if (registerIntro && registerIntro.textContent !== introText) {
            registerIntro.textContent = introText;
        }

        ['register', 'change'].forEach(function (prefix) {
            var select = document.querySelector('.' + prefix + 'InfoBoardSpeed');
            var parent = select ? select.parentElement : null;
            var help = parent ? parent.querySelector('.form-text') : null;
            var helpText = '概要の横スクロール速度を slow / normal / fast から指定します。';
            if (help && help.textContent !== helpText) {
                help.textContent = helpText;
            }
        });
    }

    function bindGlobalEvents() {
        document.addEventListener('visibilitychange', function () {
            evaluateAllCards(false);
        });

        window.addEventListener('resize', function () {
            if (resizeTimer !== null) {
                window.clearTimeout(resizeTimer);
            }
            resizeTimer = window.setTimeout(function () {
                resizeTimer = null;
                evaluateAllCards(true);
            }, 120);
        });

        window.addEventListener('load', function () {
            evaluateAllCards(true);
        });

        if (reducedMotionQuery) {
            var listener = function () {
                evaluateAllCards(true);
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
        pixelsForSpeed: pixelsForSpeed,
        titleOnlyDelay: titleOnlyDelay,
        reducedMotionPreferred: reducedMotionPreferred,
        prepareAllCards: prepareAllCards,
        evaluateAllCards: evaluateAllCards,
        interactionResumeDelay: INTERACTION_RESUME_DELAY,
        itemGapDelay: ITEM_GAP_DELAY,
        wrappedIndex: wrappedIndex,
        footerMetaLabel: footerMetaLabel,
        progressForPosition: progressForPosition,
        setTextIfChanged: setTextIfChanged
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
