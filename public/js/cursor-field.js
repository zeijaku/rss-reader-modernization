/* V1.43.0-dev.1: Cursor Field free-body physics toy. Canvas + Vanilla JS, no score, persistence, network, or dependency. */
(function (window, document) {
    'use strict';

    var INITIAL_BODIES = 3;
    var MAX_BODIES = 24;
    var BODY_SIZE = 28;
    var MIN_SPEED = 0.46;
    var MAX_INITIAL_SPEED = 1.02;
    var MAX_SPEED = 8.4;
    var WALL_RESTITUTION = 0.96;
    var BODY_RESTITUTION = 0.96;
    var POINTER_RESTITUTION = 1.02;
    var POINTER_RADIUS = 15;
    var POINTER_TRANSFER = 0.43;
    var POINTER_SPEED_LIMIT = 11.2;
    var DRAG = 0.9995;
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
        for (var index = 0; index < cards.length; index++) cards[index].setAttribute('data-mini-game-initialized', '1');
    }

    function addGameOption(select) {
        if (!select || select.querySelector('option[value="cursor_field"]')) return;
        var option = document.createElement('option');
        option.value = 'cursor_field';
        option.textContent = 'Cursor Field（物理フィールド）';
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
        if (icon && icon.classList) icon.className = 'fas fa-shapes fa-fw';
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
        var help = createElement('p', 'cursor-field-help', '○と□が漂います。カーソルで弾き、空いている場所をクリックすると物体を追加できます。');
        var widgetId = String(card.getAttribute('data-dashboard-widget-id') || '0');
        help.id = 'cursor-field-help-' + widgetId;
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', '円と正方形が壁・物体・マウスカーソルに反射する物理演算フィールド');
        canvas.setAttribute('aria-describedby', help.id);
        canvas.tabIndex = 0;
        wrap.appendChild(canvas);
        panel.appendChild(wrap);
        panel.appendChild(help);
        body.appendChild(panel);
        return {canvas: canvas, wrap: wrap};
    }

    function clamp(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
    }

    function randomBetween(minimum, maximum) {
        return minimum + Math.random() * (maximum - minimum);
    }

    function deviceScale() {
        return Math.max(1, Math.min(2, Number(window.devicePixelRatio) || 1));
    }

    function bodyRadius(body) {
        return body.size * 0.5;
    }

    function bodyMass(body) {
        return Math.max(1, body.size * body.size);
    }

    function pointInsideBody(body, x, y) {
        var half = body.size * 0.5;
        var dx = x - body.x;
        var dy = y - body.y;
        if (body.shape === 'circle') return dx * dx + dy * dy <= half * half;
        return Math.abs(dx) <= half && Math.abs(dy) <= half;
    }

    function bodiesOverlap(a, b, padding) {
        padding = Number(padding) || 0;
        if (a.shape === 'square' && b.shape === 'square') {
            return Math.abs(a.x - b.x) < (a.size + b.size) * 0.5 + padding
                && Math.abs(a.y - b.y) < (a.size + b.size) * 0.5 + padding;
        }
        var dx = a.x - b.x;
        var dy = a.y - b.y;
        var reach = bodyRadius(a) + bodyRadius(b) + padding;
        return dx * dx + dy * dy < reach * reach;
    }

    function createBody(x, y, shape, vx, vy, shade) {
        return {
            x: x,
            y: y,
            vx: Number(vx) || 0,
            vy: Number(vy) || 0,
            size: BODY_SIZE,
            shape: shape === 'square' ? 'square' : 'circle',
            shade: Number(shade) || 0
        };
    }

    function findFreePosition(bodies, width, height, size) {
        var half = size * 0.5;
        var attempt;
        for (attempt = 0; attempt < 80; attempt++) {
            var candidate = createBody(
                randomBetween(half + 4, Math.max(half + 4, width - half - 4)),
                randomBetween(half + 4, Math.max(half + 4, height - half - 4)),
                attempt % 2 ? 'square' : 'circle',
                0,
                0,
                attempt % 4
            );
            var blocked = false;
            for (var index = 0; index < bodies.length; index++) {
                if (bodiesOverlap(candidate, bodies[index], 8)) {
                    blocked = true;
                    break;
                }
            }
            if (!blocked) return candidate;
        }
        return createBody(width * 0.5, height * 0.5, 'circle', 0, 0, bodies.length % 4);
    }

    function makeInitialBodies(width, height, reducedMotion) {
        var bodies = [];
        for (var index = 0; index < INITIAL_BODIES; index++) {
            var body = findFreePosition(bodies, width, height, BODY_SIZE);
            body.shape = index % 2 === 0 ? 'circle' : 'square';
            body.shade = index % 4;
            if (!reducedMotion) {
                var angle = randomBetween(0, Math.PI * 2);
                var speed = randomBetween(MIN_SPEED, MAX_INITIAL_SPEED);
                body.vx = Math.cos(angle) * speed;
                body.vy = Math.sin(angle) * speed;
            }
            bodies.push(body);
        }
        return bodies;
    }

    function resizeState(state) {
        var rect = state.wrap.getBoundingClientRect();
        var width = Math.max(220, Math.round(rect.width || 320));
        var height = Math.max(155, Math.round(rect.height || width * 0.625));
        var scale = deviceScale();
        if (state.width === width && state.height === height && state.scale === scale) return;

        var oldWidth = state.width;
        var oldHeight = state.height;
        state.width = width;
        state.height = height;
        state.scale = scale;
        state.canvas.width = Math.round(width * scale);
        state.canvas.height = Math.round(height * scale);
        state.canvas.style.width = width + 'px';
        state.canvas.style.height = height + 'px';
        state.context.setTransform(scale, 0, 0, scale, 0, 0);

        if (!state.bodies.length) {
            state.bodies = makeInitialBodies(width, height, state.reducedMotion);
        } else if (oldWidth > 0 && oldHeight > 0) {
            var sx = width / oldWidth;
            var sy = height / oldHeight;
            for (var index = 0; index < state.bodies.length; index++) {
                var body = state.bodies[index];
                var half = body.size * 0.5;
                body.x = clamp(body.x * sx, half, width - half);
                body.y = clamp(body.y * sy, half, height - half);
                body.vx *= sx;
                body.vy *= sy;
            }
        }
        draw(state);
        startFrame(state);
    }

    function bodyColor(shade) {
        return [
            'rgba(88, 112, 126, .86)',
            'rgba(110, 126, 118, .84)',
            'rgba(121, 111, 132, .84)',
            'rgba(132, 120, 103, .84)'
        ][shade] || 'rgba(88, 112, 126, .86)';
    }

    function draw(state) {
        var context = state.context;
        context.clearRect(0, 0, state.width, state.height);
        for (var index = 0; index < state.bodies.length; index++) {
            var body = state.bodies[index];
            var half = body.size * 0.5;
            context.fillStyle = bodyColor(body.shade);
            context.strokeStyle = 'rgba(255, 255, 255, .52)';
            context.lineWidth = 1.2;
            context.beginPath();
            if (body.shape === 'circle') {
                context.arc(body.x, body.y, half, 0, Math.PI * 2);
            } else {
                context.rect(body.x - half, body.y - half, body.size, body.size);
            }
            context.fill();
            context.stroke();
        }
    }

    function wallCollision(body, width, height) {
        var half = body.size * 0.5;
        if (body.x < half) {
            body.x = half;
            if (body.vx < 0) body.vx = -body.vx * WALL_RESTITUTION;
        } else if (body.x > width - half) {
            body.x = width - half;
            if (body.vx > 0) body.vx = -body.vx * WALL_RESTITUTION;
        }
        if (body.y < half) {
            body.y = half;
            if (body.vy < 0) body.vy = -body.vy * WALL_RESTITUTION;
        } else if (body.y > height - half) {
            body.y = height - half;
            if (body.vy > 0) body.vy = -body.vy * WALL_RESTITUTION;
        }
    }

    function collisionNormal(a, b) {
        if (a.shape === 'square' && b.shape === 'square') {
            var dxSquare = b.x - a.x;
            var dySquare = b.y - a.y;
            var overlapX = (a.size + b.size) * 0.5 - Math.abs(dxSquare);
            var overlapY = (a.size + b.size) * 0.5 - Math.abs(dySquare);
            if (overlapX <= 0 || overlapY <= 0) return null;
            if (overlapX < overlapY) return {nx: dxSquare < 0 ? -1 : 1, ny: 0, overlap: overlapX};
            return {nx: 0, ny: dySquare < 0 ? -1 : 1, overlap: overlapY};
        }

        var circle = a.shape === 'circle' ? a : b;
        var square = circle === a ? b : a;
        if (square.shape === 'square') {
            var half = square.size * 0.5;
            var closestX = clamp(circle.x, square.x - half, square.x + half);
            var closestY = clamp(circle.y, square.y - half, square.y + half);
            var dxMixed = circle.x - closestX;
            var dyMixed = circle.y - closestY;
            var distanceSquared = dxMixed * dxMixed + dyMixed * dyMixed;
            var radius = circle.size * 0.5;
            if (distanceSquared >= radius * radius) return null;
            var distanceMixed = Math.sqrt(distanceSquared);
            if (distanceMixed < 0.0001) {
                var sideX = circle.x - square.x;
                var sideY = circle.y - square.y;
                if (Math.abs(sideX) > Math.abs(sideY)) {
                    dxMixed = sideX < 0 ? -1 : 1;
                    dyMixed = 0;
                } else {
                    dxMixed = 0;
                    dyMixed = sideY < 0 ? -1 : 1;
                }
                distanceMixed = 1;
            }
            var mixed = {nx: dxMixed / distanceMixed, ny: dyMixed / distanceMixed, overlap: radius - Math.sqrt(distanceSquared)};
            if (circle === a) {
                mixed.nx = -mixed.nx;
                mixed.ny = -mixed.ny;
            }
            return mixed;
        }

        var dx = b.x - a.x;
        var dy = b.y - a.y;
        var reach = bodyRadius(a) + bodyRadius(b);
        var distance = Math.sqrt(dx * dx + dy * dy);
        if (distance >= reach) return null;
        if (distance < 0.0001) return {nx: 1, ny: 0, overlap: reach};
        return {nx: dx / distance, ny: dy / distance, overlap: reach - distance};
    }

    function resolveBodyCollision(a, b) {
        var hit = collisionNormal(a, b);
        if (!hit) return false;

        var massA = bodyMass(a);
        var massB = bodyMass(b);
        var totalMass = massA + massB;
        var moveA = hit.overlap * massB / totalMass + 0.01;
        var moveB = hit.overlap * massA / totalMass + 0.01;
        a.x -= hit.nx * moveA;
        a.y -= hit.ny * moveA;
        b.x += hit.nx * moveB;
        b.y += hit.ny * moveB;

        var relativeX = b.vx - a.vx;
        var relativeY = b.vy - a.vy;
        var velocityAlongNormal = relativeX * hit.nx + relativeY * hit.ny;
        if (velocityAlongNormal >= 0) return true;

        var impulse = -(1 + BODY_RESTITUTION) * velocityAlongNormal;
        impulse /= (1 / massA) + (1 / massB);
        var impulseX = impulse * hit.nx;
        var impulseY = impulse * hit.ny;
        a.vx -= impulseX / massA;
        a.vy -= impulseY / massA;
        b.vx += impulseX / massB;
        b.vy += impulseY / massB;
        return true;
    }

    function resolvePointerCollision(body, pointer) {
        if (!pointer.active) return false;
        var half = body.size * 0.5;
        var closestX;
        var closestY;
        if (body.shape === 'square') {
            closestX = clamp(pointer.x, body.x - half, body.x + half);
            closestY = clamp(pointer.y, body.y - half, body.y + half);
        } else {
            closestX = body.x;
            closestY = body.y;
        }
        var dx = closestX - pointer.x;
        var dy = closestY - pointer.y;
        var reach = body.shape === 'circle' ? half + POINTER_RADIUS : POINTER_RADIUS;
        if (body.shape === 'circle') {
            dx = body.x - pointer.x;
            dy = body.y - pointer.y;
        }
        var distance = Math.sqrt(dx * dx + dy * dy);
        if (distance >= reach) return false;
        if (distance < 0.0001) {
            dx = body.x - pointer.x;
            dy = body.y - pointer.y;
            distance = Math.sqrt(dx * dx + dy * dy);
            if (distance < 0.0001) {
                dx = 1;
                dy = 0;
                distance = 1;
            }
        }
        var nx = dx / distance;
        var ny = dy / distance;
        var overlap = reach - distance;
        body.x += nx * (overlap + 0.5);
        body.y += ny * (overlap + 0.5);

        var relativeX = body.vx - pointer.vx;
        var relativeY = body.vy - pointer.vy;
        var velocityAlongNormal = relativeX * nx + relativeY * ny;
        if (velocityAlongNormal < 0) {
            body.vx -= (1 + POINTER_RESTITUTION) * velocityAlongNormal * nx;
            body.vy -= (1 + POINTER_RESTITUTION) * velocityAlongNormal * ny;
        }
        body.vx += pointer.vx * POINTER_TRANSFER;
        body.vy += pointer.vy * POINTER_TRANSFER;
        return true;
    }

    function limitBodySpeed(body) {
        var speed = Math.sqrt(body.vx * body.vx + body.vy * body.vy);
        if (speed > MAX_SPEED) {
            body.vx = body.vx / speed * MAX_SPEED;
            body.vy = body.vy / speed * MAX_SPEED;
        }
    }

    function advance(state, frameScale) {
        var drag = Math.pow(DRAG, frameScale);
        var index;
        for (index = 0; index < state.bodies.length; index++) {
            var body = state.bodies[index];
            body.x += body.vx * frameScale;
            body.y += body.vy * frameScale;
            body.vx *= drag;
            body.vy *= drag;
            wallCollision(body, state.width, state.height);
        }

        for (index = 0; index < state.bodies.length; index++) {
            for (var second = index + 1; second < state.bodies.length; second++) {
                resolveBodyCollision(state.bodies[index], state.bodies[second]);
            }
        }

        if (state.pointer.active) {
            for (index = 0; index < state.bodies.length; index++) resolvePointerCollision(state.bodies[index], state.pointer);
        }

        for (index = 0; index < state.bodies.length; index++) {
            limitBodySpeed(state.bodies[index]);
            wallCollision(state.bodies[index], state.width, state.height);
        }
        return state.bodies.length > 0;
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
        advance(state, frameScale);
        draw(state);
        state.pointer.vx *= 0.72;
        state.pointer.vy *= 0.72;
        state.frameId = window.requestAnimationFrame(function (nextTimestamp) { tick(state, nextTimestamp); });
    }

    function startFrame(state) {
        if (state.frameId !== null || pageHidden || !state.visible || !state.bodies.length) return;
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
        var now = Number(event.timeStamp) || Date.now();
        if (state.pointer.active && state.pointer.time > 0) {
            var frameFactor = clamp((now - state.pointer.time) / 16.67, 0.45, 4);
            state.pointer.vx = clamp((position.x - state.pointer.x) / frameFactor, -POINTER_SPEED_LIMIT, POINTER_SPEED_LIMIT);
            state.pointer.vy = clamp((position.y - state.pointer.y) / frameFactor, -POINTER_SPEED_LIMIT, POINTER_SPEED_LIMIT);
        } else {
            state.pointer.vx = 0;
            state.pointer.vy = 0;
        }
        state.pointer.active = true;
        state.pointer.x = position.x;
        state.pointer.y = position.y;
        state.pointer.time = now;
        startFrame(state);
    }

    function addBodyAt(state, position) {
        if (!position || state.bodies.length >= MAX_BODIES) return false;
        var shape = state.nextShape === 'circle' ? 'circle' : 'square';
        var body = createBody(position.x, position.y, shape, 0, 0, state.bodies.length % 4);
        var half = body.size * 0.5;
        if (body.x < half || body.x > state.width - half || body.y < half || body.y > state.height - half) return false;
        for (var index = 0; index < state.bodies.length; index++) {
            if (pointInsideBody(state.bodies[index], position.x, position.y) || bodiesOverlap(body, state.bodies[index], 2)) return false;
        }
        state.bodies.push(body);
        state.nextShape = shape === 'circle' ? 'square' : 'circle';
        state.pointer.active = false;
        state.pointer.vx = 0;
        state.pointer.vy = 0;
        state.pointer.time = 0;
        draw(state);
        startFrame(state);
        return true;
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
            bodies: [],
            pointer: {active: false, x: 0, y: 0, vx: 0, vy: 0, time: 0},
            nextShape: 'square',
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
            state.pointer.vx = 0;
            state.pointer.vy = 0;
            state.pointer.time = 0;
        });
        elements.canvas.addEventListener('click', function (event) {
            addBodyAt(state, pointerPosition(state, event));
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
                else startFrame(state);
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
                states[index].pointer.vx = 0;
                states[index].pointer.vy = 0;
                stopFrame(states[index]);
            } else startFrame(states[index]);
        }
    }

    function start() {
        ensureGameOptions();
        ensureCatalogPreset();
        initCards();

        document.addEventListener('change', function (event) {
            var target = event.target;
            if (target && (target.classList.contains('registerGameType') || target.classList.contains('changeGameType'))) syncGameTitle(target);
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
        },
        _physics: {
            createBody: createBody,
            pointInsideBody: pointInsideBody,
            collisionNormal: collisionNormal,
            resolveBodyCollision: resolveBodyCollision,
            resolvePointerCollision: resolvePointerCollision,
            wallCollision: wallCollision,
            addBodyAt: addBodyAt,
            constants: {
                initialBodies: INITIAL_BODIES,
                maxBodies: MAX_BODIES,
                bodySize: BODY_SIZE,
                pointerRadius: POINTER_RADIUS
            }
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
})(window, document);
