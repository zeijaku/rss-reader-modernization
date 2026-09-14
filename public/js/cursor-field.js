/* V1.35: Cursor Field. Canvas + Vanilla JS, no score, persistence, network, or dependency. */
(function (window, document) {
    'use strict';

    var COLS = 14;
    var ROWS = 9;
    var POINTER_RADIUS = 43;
    var SPRING = 0.075;
    var DAMPING = 0.82;
    var PUSH = 0.2;
    var MAX_SPEED = 18;
    var states = [];
    var observer = null;
    var pageHidden = false;

    var DEFAULT_TITLES = {
        icon_quest: 'Icon Quest',
        lights_out: 'Lights Out',
        wire_defense: 'Wire Defense',
        block_collapse: 'Block Collapse',
        cursor_field: 'Cursor Field'
    };

    function reserveCards() {
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="cursor_field"]');
        for (var index = 0; index < cards.length; index++) {
            cards[index].setAttribute('data-mini-game-initialized', '1');
        }
    }

    function addGameOption(select) {
        if (!select || select.querySelector('option[value="cursor_field"]')) return;
        var option = document.createElement('option');
        option.value = 'cursor_field';
        option.textContent = 'Cursor Field（マウス反発）';
        select.appendChild(option);
    }

    function ensureGameOptions() {
        var register = document.getElementById('registerGameType');
        var change = document.getElementById('changeGameType');
        addGameOption(register);
        addGameOption(change);
        if (register && !register.getAttribute('data-cursor-field-previous-type')) {
            register.setAttribute('data-cursor-field-previous-type', String(register.value || 'icon_quest'));
        }
        if (change && !change.getAttribute('data-cursor-field-previous-type')) {
            change.setAttribute('data-cursor-field-previous-type', String(change.value || 'icon_quest'));
        }
    }

    function replaceVisibleText(node, names, replacement) {
        if (!node || !node.childNodes) return false;
        for (var index = 0; index < node.childNodes.length; index++) {
            var child = node.childNodes[index];
            if (child.nodeType === 3) {
                var value = String(child.nodeValue || '');
                for (var nameIndex = 0; nameIndex < names.length; nameIndex++) {
                    if (value.indexOf(names[nameIndex]) !== -1) {
                        child.nodeValue = value.replace(names[nameIndex], replacement);
                        return true;
                    }
                }
            }
            if (child.nodeType === 1 && replaceVisibleText(child, names, replacement)) return true;
        }
        return false;
    }

    function ensureCatalogPreset() {
        var catalog = document.getElementById('widgetCatalog-game');
        var template;
        var button;
        var icon;
        if (!catalog || catalog.querySelector('[data-game-preset="cursor_field"]')) return;
        template = catalog.querySelector('[data-game-preset="block_collapse"]')
            || catalog.querySelector('[data-game-preset="wire_defense"]')
            || catalog.querySelector('[data-game-preset="lights_out"]');
        if (!template) return;
        button = template.cloneNode(true);
        button.setAttribute('data-game-preset', 'cursor_field');
        replaceVisibleText(button, ['Block Collapse', 'Wire Defense', 'Lights Out'], 'Cursor Field');
        if (button.hasAttribute('aria-label')) button.setAttribute('aria-label', 'Cursor Fieldを追加');
        if (button.hasAttribute('title')) button.setAttribute('title', 'Cursor Field');
        icon = button.querySelector('i');
        if (icon && icon.classList) icon.className = 'fas fa-border-all fa-fw';
        template.insertAdjacentElement('afterend', button);
    }

    function knownDefaultTitle(value) {
        var types = Object.keys(DEFAULT_TITLES);
        for (var index = 0; index < types.length; index++) {
            if (DEFAULT_TITLES[types[index]] === value) return true;
        }
        return false;
    }

    function syncGameTitle(select) {
        var isChange = select.classList.contains('changeGameType');
        var title = document.querySelector(isChange ? '.changeGameTitleValue' : '.registerGameTitleValue');
        var currentType = String(select.value || 'icon_quest');
        var previousType = String(select.getAttribute('data-cursor-field-previous-type') || 'icon_quest');
        var currentTitle = title ? String(title.value || '').trim() : '';
        if (title && (currentTitle === '' || currentTitle === (DEFAULT_TITLES[previousType] || '') || knownDefaultTitle(currentTitle))) {
            title.value = DEFAULT_TITLES[currentType] || 'Icon Quest';
        }
        select.setAttribute('data-cursor-field-previous-type', currentType);
    }

    function handlePresetClick(target) {
        var button = target && target.closest
            ? target.closest('[data-game-preset="cursor_field"][data-drawer-modal-target="#registerGameWidget"]')
            : null;
        var select;
        var title;
        var changeEvent;
        if (!button) return;
        select = document.getElementById('registerGameType');
        title = document.querySelector('.registerGameTitleValue');
        if (!select) return;
        addGameOption(select);
        select.value = 'cursor_field';
        select.setAttribute('data-cursor-field-previous-type', 'cursor_field');
        if (title) title.value = DEFAULT_TITLES.cursor_field;
        if (typeof window.Event === 'function') {
            changeEvent = new window.Event('change', {bubbles: true});
            select.dispatchEvent(changeEvent);
        }
    }

    function createElement(tagName, className, textValue) {
        var element = document.createElement(tagName);
        if (className) element.className = className;
        if (typeof textValue === 'string') element.textContent = textValue;
        return element;
    }

    function buildPanel(card) {
        var body = card.querySelector('.mini-game-card-body');
        if (!body) return null;
        body.textContent = '';

        var panel = createElement('div', 'cursor-field-panel');
        var wrap = createElement('div', 'cursor-field-canvas-wrap');
        var canvas = createElement('canvas', 'cursor-field-canvas');
        var help = createElement('p', 'cursor-field-help', 'マウスを重ねると、正方形が押しのけられて元の位置へ戻ります。');
        var widgetId = String(card.getAttribute('data-dashboard-widget-id') || '0');
        help.id = 'cursor-field-help-' + widgetId;
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', 'マウスカーソルで正方形を押しのける物理演算フィールド');
        canvas.setAttribute('aria-describedby', help.id);
        canvas.tabIndex = 0;
        wrap.appendChild(canvas);
        panel.appendChild(wrap);
        panel.appendChild(help);
        body.appendChild(panel);
        return {canvas: canvas, wrap: wrap};
    }

    function deviceScale() {
        return Math.max(1, Math.min(2, Number(window.devicePixelRatio) || 1));
    }

    function makeBlocks(width, height) {
        var blocks = [];
        var cellWidth = width / COLS;
        var cellHeight = height / ROWS;
        var size = Math.max(8, Math.min(cellWidth, cellHeight) * 0.72);
        for (var row = 0; row < ROWS; row++) {
            for (var column = 0; column < COLS; column++) {
                var anchorX = cellWidth * (column + 0.5);
                var anchorY = cellHeight * (row + 0.5);
                blocks.push({
                    anchorX: anchorX,
                    anchorY: anchorY,
                    x: anchorX,
                    y: anchorY,
                    vx: 0,
                    vy: 0,
                    size: size,
                    shade: (row + column) % 4
                });
            }
        }
        return blocks;
    }

    function resizeState(state) {
        var rect = state.wrap.getBoundingClientRect();
        var width = Math.max(220, Math.round(rect.width || 320));
        var height = Math.max(155, Math.round(rect.height || width * 0.625));
        var scale = deviceScale();
        if (state.width === width && state.height === height && state.scale === scale) return;
        state.width = width;
        state.height = height;
        state.scale = scale;
        state.canvas.width = Math.round(width * scale);
        state.canvas.height = Math.round(height * scale);
        state.canvas.style.width = width + 'px';
        state.canvas.style.height = height + 'px';
        state.context.setTransform(scale, 0, 0, scale, 0, 0);
        state.blocks = makeBlocks(width, height);
        draw(state);
    }

    function blockColor(shade) {
        return [
            'rgba(13, 110, 253, .78)',
            'rgba(13, 202, 240, .76)',
            'rgba(111, 66, 193, .74)',
            'rgba(32, 201, 151, .76)'
        ][shade] || 'rgba(13, 110, 253, .78)';
    }

    function draw(state) {
        var context = state.context;
        context.clearRect(0, 0, state.width, state.height);
        for (var index = 0; index < state.blocks.length; index++) {
            var block = state.blocks[index];
            var half = block.size / 2;
            context.fillStyle = blockColor(block.shade);
            context.fillRect(block.x - half, block.y - half, block.size, block.size);
            context.strokeStyle = 'rgba(255, 255, 255, .42)';
            context.lineWidth = 1;
            context.strokeRect(block.x - half + 0.5, block.y - half + 0.5, block.size - 1, block.size - 1);
        }
    }

    function clamp(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
    }

    function advance(state, frameScale) {
        var moving = state.pointer.active;
        var spring = state.reducedMotion ? 0.12 : SPRING;
        var damping = state.reducedMotion ? 0.72 : DAMPING;
        for (var index = 0; index < state.blocks.length; index++) {
            var block = state.blocks[index];
            if (state.pointer.active) {
                var dx = block.x - state.pointer.x;
                var dy = block.y - state.pointer.y;
                var distance = Math.sqrt(dx * dx + dy * dy);
                var reach = POINTER_RADIUS + block.size * 0.62;
                if (distance < reach) {
                    if (distance < 0.001) {
                        dx = ((index % COLS) - (COLS - 1) / 2) || 1;
                        dy = (Math.floor(index / COLS) - (ROWS - 1) / 2) || 1;
                        distance = Math.sqrt(dx * dx + dy * dy);
                    }
                    var force = (reach - distance) * PUSH * frameScale;
                    block.vx += dx / distance * force;
                    block.vy += dy / distance * force;
                }
            }

            block.vx += (block.anchorX - block.x) * spring * frameScale;
            block.vy += (block.anchorY - block.y) * spring * frameScale;
            block.vx = clamp(block.vx * Math.pow(damping, frameScale), -MAX_SPEED, MAX_SPEED);
            block.vy = clamp(block.vy * Math.pow(damping, frameScale), -MAX_SPEED, MAX_SPEED);
            block.x += block.vx * frameScale;
            block.y += block.vy * frameScale;

            var half = block.size / 2;
            block.x = clamp(block.x, half, state.width - half);
            block.y = clamp(block.y, half, state.height - half);

            if (Math.abs(block.x - block.anchorX) > 0.08
                || Math.abs(block.y - block.anchorY) > 0.08
                || Math.abs(block.vx) > 0.04
                || Math.abs(block.vy) > 0.04) {
                moving = true;
            } else if (!state.pointer.active) {
                block.x = block.anchorX;
                block.y = block.anchorY;
                block.vx = 0;
                block.vy = 0;
            }
        }
        return moving;
    }

    function stopFrame(state) {
        if (state.frameId !== null) {
            window.cancelAnimationFrame(state.frameId);
            state.frameId = null;
        }
        state.lastFrame = 0;
    }

    function tick(state, timestamp) {
        state.frameId = null;
        if (pageHidden || !state.visible || !state.card.isConnected) {
            stopFrame(state);
            return;
        }
        var elapsed = state.lastFrame > 0 ? timestamp - state.lastFrame : 16.67;
        state.lastFrame = timestamp;
        var frameScale = clamp(elapsed / 16.67, 0.5, 2);
        var moving = advance(state, frameScale);
        draw(state);
        if (moving) state.frameId = window.requestAnimationFrame(function (nextTimestamp) { tick(state, nextTimestamp); });
        else state.lastFrame = 0;
    }

    function startFrame(state) {
        if (state.frameId !== null || pageHidden || !state.visible) return;
        state.frameId = window.requestAnimationFrame(function (timestamp) { tick(state, timestamp); });
    }

    function pointerPosition(state, event) {
        var rect = state.canvas.getBoundingClientRect();
        if (!(rect.width > 0) || !(rect.height > 0)) return null;
        return {
            x: (event.clientX - rect.left) * state.width / rect.width,
            y: (event.clientY - rect.top) * state.height / rect.height
        };
    }

    function handlePointerMove(state, event) {
        if (event.pointerType && event.pointerType !== 'mouse' && event.pointerType !== 'pen') return;
        var position = pointerPosition(state, event);
        if (!position) return;
        state.pointer.active = true;
        state.pointer.x = position.x;
        state.pointer.y = position.y;
        startFrame(state);
    }

    function initCard(card) {
        if (!card || card.__rssCursorFieldState) return;
        card.setAttribute('data-mini-game-initialized', '1');
        var elements = buildPanel(card);
        if (!elements) return;
        var context = elements.canvas.getContext('2d');
        if (!context) return;
        var reducedMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var state = {
            card: card,
            canvas: elements.canvas,
            wrap: elements.wrap,
            context: context,
            blocks: [],
            pointer: {active: false, x: 0, y: 0},
            width: 0,
            height: 0,
            scale: 0,
            frameId: null,
            lastFrame: 0,
            visible: true,
            reducedMotion: reducedMotion,
            resizeObserver: null,
            resizeHandler: null,
            intersectionObserver: null
        };
        card.__rssCursorFieldState = state;
        states.push(state);
        resizeState(state);

        elements.canvas.addEventListener('pointerenter', function (event) { handlePointerMove(state, event); });
        elements.canvas.addEventListener('pointermove', function (event) { handlePointerMove(state, event); });
        elements.canvas.addEventListener('pointerleave', function () {
            state.pointer.active = false;
            startFrame(state);
        });

        if (typeof window.ResizeObserver === 'function') {
            state.resizeObserver = new window.ResizeObserver(function () { resizeState(state); });
            state.resizeObserver.observe(elements.wrap);
        } else {
            state.resizeHandler = function () { resizeState(state); };
            window.addEventListener('resize', state.resizeHandler);
        }

        if (typeof window.IntersectionObserver === 'function') {
            state.intersectionObserver = new window.IntersectionObserver(function (entries) {
                if (!entries[0]) return;
                state.visible = entries[0].isIntersecting;
                if (!state.visible) stopFrame(state);
                else if (state.pointer.active) startFrame(state);
            });
            state.intersectionObserver.observe(card);
        }
    }

    function initCards() {
        reserveCards();
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="cursor_field"]');
        for (var index = 0; index < cards.length; index++) initCard(cards[index]);
    }

    function cleanupDisconnected() {
        var connected = [];
        for (var index = 0; index < states.length; index++) {
            var state = states[index];
            if (!state.card || !state.card.isConnected) {
                stopFrame(state);
                if (state.resizeObserver) state.resizeObserver.disconnect();
                if (state.resizeHandler) window.removeEventListener('resize', state.resizeHandler);
                if (state.intersectionObserver) state.intersectionObserver.disconnect();
                continue;
            }
            connected.push(state);
        }
        states = connected;
    }

    function pauseForVisibility() {
        pageHidden = document.hidden === true;
        for (var index = 0; index < states.length; index++) {
            if (pageHidden) {
                states[index].pointer.active = false;
                stopFrame(states[index]);
            }
            else startFrame(states[index]);
        }
    }

    function start() {
        ensureGameOptions();
        ensureCatalogPreset();
        initCards();

        document.addEventListener('change', function (event) {
            var target = event.target;
            if (target && (target.classList.contains('registerGameType') || target.classList.contains('changeGameType'))) {
                syncGameTitle(target);
            }
        });
        document.addEventListener('click', function (event) {
            handlePresetClick(event.target);
            var edit = event.target && event.target.closest ? event.target.closest('.mini-game-edit-trigger') : null;
            if (edit) {
                var select = document.getElementById('changeGameType');
                if (select) select.setAttribute('data-cursor-field-previous-type', String(select.value || 'icon_quest'));
            }
        });
        document.addEventListener('visibilitychange', pauseForVisibility);
        window.addEventListener('pagehide', function () {
            pageHidden = true;
            for (var index = 0; index < states.length; index++) {
                states[index].pointer.active = false;
                stopFrame(states[index]);
            }
        });
        window.addEventListener('pageshow', function () {
            pageHidden = false;
            for (var index = 0; index < states.length; index++) startFrame(states[index]);
        });

        if (typeof window.MutationObserver === 'function' && document.body) {
            observer = new window.MutationObserver(function () {
                reserveCards();
                ensureGameOptions();
                ensureCatalogPreset();
                initCards();
                cleanupDisconnected();
            });
            observer.observe(document.body, {childList: true, subtree: true});
        }
    }

    reserveCards();

    window.RssCursorField = {
        init: initCards,
        stopAll: function () {
            for (var index = 0; index < states.length; index++) stopFrame(states[index]);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
    } else {
        start();
    }
})(window, document);
