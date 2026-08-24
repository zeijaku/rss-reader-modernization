/* V1.20.1-D3: Block Collapse stability / tension tuning. Canvas + Vanilla JS, no external dependency. */
(function (window, document) {
    'use strict';

    var COLS = 8;
    var ROWS = 8;
    var CANVAS_WIDTH = 640;
    var CANVAS_HEIGHT = 480;
    var PAD = 12;
    var GAP = 5;
    var STEP_DURATION = 105;
    var BREAK_LIMIT = 8;
    var DIRECT_BREAK_SCORE = 100;
    var CHAIN_SCORE_STEP = 200;
    var STABILITY_MAX = 100;
    var CRACK_STABILITY_COST = 3;
    var DIRECT_BREAK_STABILITY_COST = 8;
    var HEAVY_FALL_STABILITY_COST = 4;
    var HEAVY_FALL_STABILITY_CAP = 12;
    var CHAIN_STABILITY_RECOVERY = 6;
    var CHAIN_STABILITY_RECOVERY_CAP = 18;
    var STABILITY_WARNING = 30;
    var STABILITY_CRITICAL = 15;
    var states = [];
    var observer = null;
    var pageHidden = false;

    var DEFAULT_TITLES = {
        icon_quest: 'Icon Quest',
        lights_out: 'Lights Out',
        wire_defense: 'Wire Defense',
        block_collapse: 'Block Collapse'
    };

    /* Bottom row is intentionally stable. Upper rows contain holes / bridges so
     * the player has to choose supports instead of tapping every block in order. */
    var LAYOUTS = [
        ['00111000', '01111100', '11001110', '11110111', '11101111', '11111111'],
        ['00011110', '01110111', '11111101', '11011111', '11110111', '11111111'],
        ['00110110', '11111110', '11011101', '11101111', '11111011', '11111111'],
        ['00011100', '01111110', '11100111', '11011111', '11111011', '11111111']
    ];

    function reserveCards() {
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="block_collapse"]');
        for (var index = 0; index < cards.length; index++) {
            // mini-game.js treats every unknown type as Icon Quest. Reserve this
            // card before DOMContentLoaded so only Block Collapse initializes it.
            cards[index].setAttribute('data-mini-game-initialized', '1');
        }
    }

    function addGameOption(select) {
        if (!select || select.querySelector('option[value="block_collapse"]')) return;
        var option = document.createElement('option');
        option.value = 'block_collapse';
        option.textContent = 'Block Collapse（積み木崩し）';
        select.appendChild(option);
    }

    function ensureGameOptions() {
        var register = document.getElementById('registerGameType');
        var change = document.getElementById('changeGameType');
        addGameOption(register);
        addGameOption(change);
        if (register && !register.getAttribute('data-block-collapse-previous-type')) {
            register.setAttribute('data-block-collapse-previous-type', String(register.value || 'icon_quest'));
        }
        if (change && !change.getAttribute('data-block-collapse-previous-type')) {
            change.setAttribute('data-block-collapse-previous-type', String(change.value || 'icon_quest'));
        }
    }

    function replaceVisibleText(node, fromText, toText) {
        if (!node || !node.childNodes) return false;
        for (var index = 0; index < node.childNodes.length; index++) {
            var child = node.childNodes[index];
            if (child.nodeType === 3 && String(child.nodeValue || '').indexOf(fromText) !== -1) {
                child.nodeValue = String(child.nodeValue || '').replace(fromText, toText);
                return true;
            }
            if (child.nodeType === 1 && replaceVisibleText(child, fromText, toText)) return true;
        }
        return false;
    }

    function ensureCatalogPreset() {
        var catalog = document.getElementById('widgetCatalog-game');
        var template;
        var button;
        var icon;
        if (!catalog || catalog.querySelector('[data-game-preset="block_collapse"]')) return;
        template = catalog.querySelector('[data-game-preset="wire_defense"]')
            || catalog.querySelector('[data-game-preset="lights_out"]');
        if (!template) return;
        button = template.cloneNode(true);
        button.setAttribute('data-game-preset', 'block_collapse');
        replaceVisibleText(button, 'Wire Defense', 'Block Collapse');
        replaceVisibleText(button, 'Lights Out', 'Block Collapse');
        if (button.hasAttribute('aria-label')) button.setAttribute('aria-label', 'Block Collapseを追加');
        if (button.hasAttribute('title')) button.setAttribute('title', 'Block Collapse');
        icon = button.querySelector('i');
        if (icon && icon.classList) icon.className = 'fas fa-cubes fa-fw';
        template.insertAdjacentElement('afterend', button);
    }

    function knownDefaultTitle(value) {
        var keys = Object.keys(DEFAULT_TITLES);
        for (var index = 0; index < keys.length; index++) {
            if (DEFAULT_TITLES[keys[index]] === value) return true;
        }
        return false;
    }

    function syncGameTitle(select) {
        var isChange = select.classList.contains('changeGameType');
        var title = document.querySelector(isChange ? '.changeGameTitleValue' : '.registerGameTitleValue');
        var currentType = String(select.value || 'icon_quest');
        var previousType = String(select.getAttribute('data-block-collapse-previous-type') || 'icon_quest');
        var currentTitle = title ? String(title.value || '').trim() : '';
        if (title && (currentTitle === '' || currentTitle === (DEFAULT_TITLES[previousType] || '') || knownDefaultTitle(currentTitle))) {
            title.value = DEFAULT_TITLES[currentType] || 'Icon Quest';
        }
        select.setAttribute('data-block-collapse-previous-type', currentType);
    }

    function handlePresetClick(target) {
        var button = target && target.closest
            ? target.closest('[data-game-preset="block_collapse"][data-drawer-modal-target="#registerGameWidget"]')
            : null;
        var select;
        var title;
        var changeEvent;
        if (!button) return;
        select = document.getElementById('registerGameType');
        title = document.querySelector('.registerGameTitleValue');
        if (!select) return;
        addGameOption(select);
        select.value = 'block_collapse';
        select.setAttribute('data-block-collapse-previous-type', 'block_collapse');
        if (title) title.value = DEFAULT_TITLES.block_collapse;
        if (typeof window.Event === 'function') {
            changeEvent = new window.Event('change', {bubbles: true});
            select.dispatchEvent(changeEvent);
        }
    }

    function randomInt(max) {
        if (!(max > 0)) return 0;
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            var values = new Uint32Array(1);
            window.crypto.getRandomValues(values);
            return values[0] % max;
        }
        return Math.floor(Math.random() * max);
    }

    function reverseMask(mask) {
        return String(mask || '').split('').reverse().join('');
    }

    function addRunBlocks(blocks, mask, y, nextId) {
        var x = 0;
        var id = nextId;
        while (x < COLS) {
            if (mask.charAt(x) !== '1') {
                x += 1;
                continue;
            }
            var runStart = x;
            while (x < COLS && mask.charAt(x) === '1') x += 1;
            var remaining = x - runStart;
            var cursor = runStart;
            while (remaining > 0) {
                var maxWidth = Math.min(3, remaining);
                var width = 1 + randomInt(maxWidth);
                blocks.push({
                    id: id,
                    x: cursor,
                    y: y,
                    drawX: cursor,
                    drawY: y,
                    w: width,
                    hp: 2,
                    alive: true,
                    fallRows: 0
                });
                id += 1;
                cursor += width;
                remaining -= width;
            }
        }
        return id;
    }

    function makeBlocks() {
        var layout = LAYOUTS[randomInt(LAYOUTS.length)].slice();
        var mirror = randomInt(2) === 1;
        var blocks = [];
        var id = 1;
        for (var rowIndex = 0; rowIndex < layout.length; rowIndex++) {
            var mask = mirror ? reverseMask(layout[rowIndex]) : layout[rowIndex];
            id = addRunBlocks(blocks, mask, rowIndex + 2, id);
        }
        return blocks;
    }

    function cloneBlocks(blocks) {
        return blocks.map(function (block) {
            return {
                id: block.id,
                x: block.x,
                y: block.y,
                drawX: block.x,
                drawY: block.y,
                w: block.w,
                hp: block.hp,
                alive: block.alive,
                fallRows: 0
            };
        });
    }

    function activeBlocks(state) {
        return state.blocks.filter(function (block) { return block.alive; });
    }

    function overlap1d(aStart, aLength, bStart, bLength) {
        return aStart < bStart + bLength && aStart + aLength > bStart;
    }

    function canOccupy(state, block, x, y) {
        if (x < 0 || x + block.w > COLS || y < 0 || y >= ROWS) return false;
        for (var index = 0; index < state.blocks.length; index++) {
            var other = state.blocks[index];
            if (!other.alive || other.id === block.id) continue;
            if (other.y === y && overlap1d(x, block.w, other.x, other.w)) return false;
        }
        return true;
    }

    function settleInitial(state) {
        var changed = true;
        var guard = 0;
        while (changed && guard < ROWS * Math.max(1, state.blocks.length)) {
            changed = false;
            guard += 1;
            var blocks = activeBlocks(state).slice().sort(function (left, right) {
                if (right.y !== left.y) return right.y - left.y;
                return left.id - right.id;
            });
            for (var index = 0; index < blocks.length; index++) {
                var block = blocks[index];
                if (canOccupy(state, block, block.x, block.y + 1)) {
                    block.y += 1;
                    block.drawY = block.y;
                    changed = true;
                }
            }
        }
    }

    function cssValue(card, name, fallback) {
        var style = window.getComputedStyle ? window.getComputedStyle(card) : null;
        var value = style ? String(style.getPropertyValue(name) || '').trim() : '';
        return value || fallback;
    }

    function roundedRect(ctx, x, y, width, height, radius) {
        var r = Math.min(radius, width / 2, height / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + width - r, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + r);
        ctx.lineTo(x + width, y + height - r);
        ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
        ctx.lineTo(x + r, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function drawCracks(ctx, x, y, width, height, stroke) {
        var cx = x + width * .52;
        var cy = y + height * .48;
        ctx.strokeStyle = stroke;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx - width * .16, cy - height * .24);
        ctx.lineTo(cx - width * .28, cy - height * .34);
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + width * .12, cy - height * .18);
        ctx.lineTo(cx + width * .27, cy - height * .08);
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx - width * .08, cy + height * .22);
        ctx.lineTo(cx + width * .06, cy + height * .36);
        ctx.stroke();
    }

    function blockRect(block) {
        var cellWidth = (CANVAS_WIDTH - PAD * 2) / COLS;
        var cellHeight = (CANVAS_HEIGHT - PAD * 2) / ROWS;
        return {
            x: PAD + block.drawX * cellWidth + GAP / 2,
            y: PAD + block.drawY * cellHeight + GAP / 2,
            width: block.w * cellWidth - GAP,
            height: cellHeight - GAP
        };
    }

    function draw(state) {
        var canvas = state.canvas;
        var ctx = state.ctx;
        var card = state.card;
        if (!canvas || !ctx || !card) return;
        var cellHeight = (CANVAS_HEIGHT - PAD * 2) / ROWS;
        var bodyBg = cssValue(card, '--bs-body-bg', '#ffffff');
        var bodyColor = cssValue(card, '--bs-body-color', '#212529');
        var border = cssValue(card, '--bs-border-color', '#adb5bd');
        var focusColor = cssValue(card, '--bs-primary', '#0d6efd');
        var palette = [
            cssValue(card, '--bs-primary', '#0d6efd'),
            cssValue(card, '--bs-info', '#0dcaf0'),
            cssValue(card, '--bs-secondary', '#6c757d'),
            cssValue(card, '--bs-warning', '#ffc107')
        ];

        ctx.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        ctx.fillStyle = bodyBg;
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        ctx.strokeStyle = border;
        ctx.lineWidth = 1;
        for (var row = 0; row <= ROWS; row++) {
            var gy = PAD + row * cellHeight;
            ctx.beginPath();
            ctx.moveTo(PAD, gy);
            ctx.lineTo(CANVAS_WIDTH - PAD, gy);
            ctx.stroke();
        }

        var blocks = activeBlocks(state);
        for (var index = 0; index < blocks.length; index++) {
            var block = blocks[index];
            var rect = blockRect(block);
            var highlighted = block.id === state.hoverBlockId || block.id === state.targetBlockId || block.id === state.keyboardBlockId;
            roundedRect(ctx, rect.x, rect.y, rect.width, rect.height, 7);
            ctx.fillStyle = palette[(block.id - 1) % palette.length];
            ctx.globalAlpha = block.hp === 1 ? .68 : .9;
            ctx.fill();
            ctx.globalAlpha = 1;
            ctx.strokeStyle = highlighted ? focusColor : bodyColor;
            ctx.lineWidth = highlighted ? 4 : (block.hp === 1 ? 2 : 1);
            ctx.stroke();
            if (highlighted) {
                ctx.save();
                ctx.shadowColor = focusColor;
                ctx.shadowBlur = 10;
                roundedRect(ctx, rect.x + 2, rect.y + 2, rect.width - 4, rect.height - 4, 5);
                ctx.strokeStyle = focusColor;
                ctx.lineWidth = 2;
                ctx.stroke();
                ctx.restore();
            }
            if (block.hp === 1) drawCracks(ctx, rect.x, rect.y, rect.width, rect.height, bodyColor);
        }

        ctx.strokeStyle = bodyColor;
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(PAD, CANVAS_HEIGHT - PAD + 1);
        ctx.lineTo(CANVAS_WIDTH - PAD, CANVAS_HEIGHT - PAD + 1);
        ctx.stroke();

        if (state.userPaused || state.autoPaused) {
            ctx.fillStyle = 'rgba(0,0,0,.42)';
            ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 40px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('PAUSED', CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2);
        } else if (state.status === 'gameover') {
            ctx.fillStyle = 'rgba(220,53,69,.20)';
            ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
            ctx.fillStyle = cssValue(card, '--bs-danger', '#dc3545');
            ctx.font = '700 38px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(state.gameOverReason === 'stability' ? 'COLLAPSE' : 'NO BREAKS', CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2);
        } else if (state.status === 'cleared') {
            ctx.fillStyle = 'rgba(25,135,84,.18)';
            ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
            ctx.fillStyle = cssValue(card, '--bs-success', '#198754');
            ctx.font = '700 42px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('CLEAR', CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2);
        }
    }

    function stabilityLevel(state) {
        if (state.stability <= 0) return 'collapsed';
        if (state.stability <= STABILITY_CRITICAL) return 'critical';
        if (state.stability <= STABILITY_WARNING) return 'warning';
        return 'safe';
    }

    function pulseInstability(state) {
        var wrap = state.card.querySelector('.block-collapse-canvas-wrap');
        if (!wrap) return;
        wrap.classList.remove('is-shaking');
        // Restart the short CSS animation without a timer / game loop.
        void wrap.offsetWidth;
        wrap.classList.add('is-shaking');
    }

    function updateSummary(state) {
        var blocksNode = state.card.querySelector('.block-collapse-blocks');
        var breaksNode = state.card.querySelector('.block-collapse-breaks');
        var stabilityNode = state.card.querySelector('.block-collapse-stability');
        var stabilityTrack = state.card.querySelector('.block-collapse-stability-track');
        var stabilityFill = state.card.querySelector('.block-collapse-stability-fill');
        var scoreNode = state.card.querySelector('.block-collapse-score');
        var comboNode = state.card.querySelector('.block-collapse-combo');
        var level = stabilityLevel(state);
        if (blocksNode) blocksNode.textContent = String(activeBlocks(state).length);
        if (breaksNode) breaksNode.textContent = String(state.breaksLeft) + ' / ' + String(state.breakLimit);
        if (stabilityNode) stabilityNode.textContent = String(state.stability) + '%';
        if (stabilityTrack) {
            stabilityTrack.setAttribute('aria-valuenow', String(state.stability));
            stabilityTrack.setAttribute('aria-valuetext', '安定度 ' + String(state.stability) + '%');
        }
        if (stabilityFill) stabilityFill.style.width = String(state.stability) + '%';
        if (scoreNode) scoreNode.textContent = String(state.score);
        if (comboNode) comboNode.textContent = state.combo > 0 ? '×' + String(state.combo) : '—';
        state.card.setAttribute('data-block-collapse-stability', level);
    }

    function changeStability(state, delta) {
        var before = state.stability;
        state.stability = Math.max(0, Math.min(STABILITY_MAX, state.stability + delta));
        if (state.stability <= STABILITY_WARNING && !state.instabilityUsedThisAction) state.instabilityPending = true;
        if ((before > STABILITY_WARNING && state.stability <= STABILITY_WARNING)
            || (before > STABILITY_CRITICAL && state.stability <= STABILITY_CRITICAL)) {
            pulseInstability(state);
        }
        updateSummary(state);
        return state.stability;
    }

    function setStatus(state, text) {
        var node = state.card.querySelector('.block-collapse-status');
        if (node) node.textContent = String(text || '');
    }

    function setCardStatus(state) {
        var value = state.status === 'cleared'
            ? 'cleared'
            : (state.status === 'gameover' ? 'gameover' : (state.userPaused ? 'paused' : 'playing'));
        state.card.setAttribute('data-block-collapse-status', value);
        var pause = state.card.querySelector('.block-collapse-pause');
        if (pause) {
            pause.textContent = state.userPaused ? '再開' : '一時停止';
            pause.disabled = state.status === 'cleared' || state.status === 'gameover';
        }
    }

    function stopRaf(state) {
        if (state.rafId !== null && typeof window.cancelAnimationFrame === 'function') {
            window.cancelAnimationFrame(state.rafId);
        }
        state.rafId = null;
        if (state.animation) state.animation.startedAt = null;
    }

    function scheduleFrame(state) {
        if (state.rafId !== null || !state.animation || state.userPaused || state.autoPaused || pageHidden || !state.card.isConnected) return;
        if (typeof window.requestAnimationFrame !== 'function') {
            completeAnimation(state);
            return;
        }
        state.rafId = window.requestAnimationFrame(function (timestamp) {
            animationFrame(state, timestamp);
        });
    }

    function completeAnimation(state) {
        if (!state.animation) return;
        for (var index = 0; index < state.animation.moves.length; index++) {
            var move = state.animation.moves[index];
            move.block.drawX = move.block.x;
            move.block.drawY = move.block.y;
        }
        state.animation = null;
        state.rafId = null;
        draw(state);
        resolveNextStep(state);
    }

    function animationFrame(state, timestamp) {
        state.rafId = null;
        var animation = state.animation;
        if (!animation || state.userPaused || state.autoPaused || pageHidden || !state.card.isConnected) return;
        if (animation.startedAt === null) {
            animation.startedAt = timestamp;
            animation.startProgress = animation.progress;
        }
        var remaining = Math.max(.0001, 1 - animation.startProgress);
        var progress = animation.startProgress + ((timestamp - animation.startedAt) / STEP_DURATION) * remaining;
        if (progress > 1) progress = 1;
        animation.progress = progress;
        var eased = 1 - Math.pow(1 - progress, 3);
        for (var index = 0; index < animation.moves.length; index++) {
            var move = animation.moves[index];
            move.block.drawX = move.fromX + (move.block.x - move.fromX) * eased;
            move.block.drawY = move.fromY + (move.block.y - move.fromY) * eased;
        }
        draw(state);
        if (progress >= 1) {
            completeAnimation(state);
            return;
        }
        scheduleFrame(state);
    }

    function startAnimation(state, moves) {
        state.animation = {
            moves: moves,
            progress: 0,
            startProgress: 0,
            startedAt: null
        };
        scheduleFrame(state);
    }

    function computeGravityStep(state) {
        var blocks = activeBlocks(state).slice().sort(function (left, right) {
            if (right.y !== left.y) return right.y - left.y;
            return left.id - right.id;
        });
        var moves = [];
        for (var index = 0; index < blocks.length; index++) {
            var block = blocks[index];
            var fromX = block.drawX;
            var fromY = block.drawY;
            var moved = false;
            if (canOccupy(state, block, block.x, block.y + 1)) {
                block.y += 1;
                moved = true;
            } else if (block.fallRows > 0) {
                var directions = ((block.id + state.broken) % 2 === 0) ? [-1, 1] : [1, -1];
                for (var d = 0; d < directions.length; d++) {
                    var targetX = block.x + directions[d];
                    if (canOccupy(state, block, targetX, block.y)
                        && canOccupy(state, block, targetX, block.y + 1)) {
                        block.x = targetX;
                        block.y += 1;
                        moved = true;
                        break;
                    }
                }
            }
            if (moved) {
                block.fallRows += 1;
                if (block.fallRows === 3 && state.fallStressThisCascade < HEAVY_FALL_STABILITY_CAP) {
                    var fallCost = Math.min(
                        HEAVY_FALL_STABILITY_COST,
                        HEAVY_FALL_STABILITY_CAP - state.fallStressThisCascade
                    );
                    state.fallStressThisCascade += fallCost;
                    changeStability(state, -fallCost);
                }
                moves.push({block: block, fromX: fromX, fromY: fromY});
            }
        }
        return moves;
    }

    function crackedSupport(state, block) {
        if (block.fallRows < 2) return null;
        for (var index = 0; index < state.blocks.length; index++) {
            var other = state.blocks[index];
            if (!other.alive || other.id === block.id || other.hp !== 1) continue;
            if (other.y === block.y + 1 && overlap1d(block.x, block.w, other.x, other.w)) return other;
        }
        return null;
    }

    function applyImpactChain(state) {
        var blocks = activeBlocks(state).slice().sort(function (left, right) { return right.fallRows - left.fallRows; });
        var chained = false;
        for (var index = 0; index < blocks.length; index++) {
            var support = crackedSupport(state, blocks[index]);
            if (!support || !support.alive) continue;
            support.alive = false;
            support.hp = 0;
            state.broken += 1;
            state.combo += 1;
            state.maxCombo = Math.max(state.maxCombo, state.combo);
            state.score += CHAIN_SCORE_STEP * state.combo;
            if (state.chainRecoveryThisCascade < CHAIN_STABILITY_RECOVERY_CAP) {
                var recovery = Math.min(
                    CHAIN_STABILITY_RECOVERY,
                    CHAIN_STABILITY_RECOVERY_CAP - state.chainRecoveryThisCascade
                );
                state.chainRecoveryThisCascade += recovery;
                changeStability(state, recovery);
            }
            if (state.targetBlockId === support.id) state.targetBlockId = null;
            if (state.keyboardBlockId === support.id) state.keyboardBlockId = null;
            chained = true;
        }
        return chained;
    }

    function supportRatioAt(state, block, x, y) {
        if (y >= ROWS - 1) return 1;
        var supported = 0;
        for (var index = 0; index < state.blocks.length; index++) {
            var other = state.blocks[index];
            if (!other.alive || other.id === block.id || other.y !== y + 1) continue;
            var left = Math.max(x, other.x);
            var right = Math.min(x + block.w, other.x + other.w);
            if (right > left) supported += right - left;
        }
        return Math.max(0, Math.min(1, supported / block.w));
    }

    function unstableCandidates(state) {
        return activeBlocks(state).filter(function (block) {
            var ratio = supportRatioAt(state, block, block.x, block.y);
            return block.y < ROWS - 1 && ratio > 0 && ratio <= .67;
        }).sort(function (left, right) {
            var leftRatio = supportRatioAt(state, left, left.x, left.y);
            var rightRatio = supportRatioAt(state, right, right.x, right.y);
            if (leftRatio !== rightRatio) return leftRatio - rightRatio;
            return right.y - left.y;
        });
    }

    function destabilizeBlock(state) {
        var candidates = unstableCandidates(state);
        if (candidates.length === 0) return false;
        var threshold = state.stability <= STABILITY_CRITICAL ? 70 : 40;
        if (randomInt(100) >= threshold) return false;
        var candidate = candidates[Math.min(randomInt(Math.min(2, candidates.length)), candidates.length - 1)];
        var currentRatio = supportRatioAt(state, candidate, candidate.x, candidate.y);
        var options = [];
        [-1, 1].forEach(function (direction) {
            var targetX = candidate.x + direction;
            if (!canOccupy(state, candidate, targetX, candidate.y)) return;
            var ratio = supportRatioAt(state, candidate, targetX, candidate.y);
            if (ratio < currentRatio) options.push({x: targetX, ratio: ratio});
        });
        if (options.length === 0) return false;
        options.sort(function (left, right) { return left.ratio - right.ratio; });
        var chosen = options[0];
        var fromX = candidate.drawX;
        var fromY = candidate.drawY;
        candidate.x = chosen.x;
        state.fallStressThisCascade = 0;
        state.chainRecoveryThisCascade = 0;
        state.instabilityUsedThisAction = true;
        state.cascadeActive = true;
        pulseInstability(state);
        setStatus(state, '不安定化！ 支えの弱いBlockがずれました。');
        startAnimation(state, [{block: candidate, fromX: fromX, fromY: fromY}]);
        return true;
    }

    function maybeDestabilize(state) {
        if (!state.instabilityPending) return false;
        state.instabilityPending = false;
        if (state.stability <= 0 || state.stability > STABILITY_WARNING) return false;
        return destabilizeBlock(state);
    }

    function stabilityGameOver(state) {
        stopRaf(state);
        state.animation = null;
        state.cascadeActive = false;
        state.status = 'gameover';
        state.gameOverReason = 'stability';
        state.stability = 0;
        updateSummary(state);
        setStatus(state, 'Stabilityが0%になり全体が崩壊しました。Restartで再挑戦出来ます。');
        setCardStatus(state);
        pulseInstability(state);
        draw(state);
    }

    function finishCascade(state) {
        state.cascadeActive = false;
        for (var index = 0; index < state.blocks.length; index++) state.blocks[index].fallRows = 0;
        if (state.stability <= 0) {
            stabilityGameOver(state);
            return;
        }
        if (activeBlocks(state).length > 0 && maybeDestabilize(state)) return;
        if (activeBlocks(state).length === 0) {
            state.status = 'cleared';
            state.score += state.breaksLeft * 250;
            stopRaf(state);
            setStatus(state, 'Clear！ 残りBreak Bonusを加算しました。');
        } else if (state.breaksLeft <= 0) {
            state.status = 'gameover';
            state.gameOverReason = 'breaks';
            stopRaf(state);
            setStatus(state, 'Breakを使い切りました。Restartで同じ盤面に再挑戦出来ます。');
        } else if (state.combo > 0) {
            setStatus(state, 'Combo ×' + String(state.combo) + '！ 次の支点を選んでください。');
        } else {
            setStatus(state, 'ヒビを仕込み、少ないBreakでChainを狙ってください。');
        }
        updateSummary(state);
        setCardStatus(state);
        draw(state);
    }

    function resolveNextStep(state) {
        if (!state.cascadeActive || state.userPaused || state.autoPaused || pageHidden || !state.card.isConnected) return;
        if (state.stability <= 0) {
            stabilityGameOver(state);
            return;
        }
        if (activeBlocks(state).length === 0) {
            finishCascade(state);
            return;
        }
        var moves = computeGravityStep(state);
        if (moves.length > 0) {
            setStatus(state, '崩落中...');
            startAnimation(state, moves);
            return;
        }
        if (applyImpactChain(state)) {
            updateSummary(state);
            setStatus(state, 'Chain ×' + String(state.combo) + '！ Stabilityを回復しながら崩れています。');
            draw(state);
            resolveNextStep(state);
            return;
        }
        finishCascade(state);
    }

    function startCascade(state) {
        state.cascadeActive = true;
        state.fallStressThisCascade = 0;
        state.chainRecoveryThisCascade = 0;
        resolveNextStep(state);
    }

    function hitBlock(state, clientX, clientY) {
        var rect = state.canvas.getBoundingClientRect();
        if (!(rect.width > 0) || !(rect.height > 0)) return null;
        var x = (clientX - rect.left) * CANVAS_WIDTH / rect.width;
        var y = (clientY - rect.top) * CANVAS_HEIGHT / rect.height;
        var blocks = activeBlocks(state);
        for (var index = blocks.length - 1; index >= 0; index--) {
            var block = blocks[index];
            var blockBounds = blockRect(block);
            if (x >= blockBounds.x && x <= blockBounds.x + blockBounds.width
                && y >= blockBounds.y && y <= blockBounds.y + blockBounds.height) return block;
        }
        return null;
    }

    function findBlockById(state, blockId) {
        for (var index = 0; index < state.blocks.length; index++) {
            if (state.blocks[index].alive && state.blocks[index].id === blockId) return state.blocks[index];
        }
        return null;
    }

    function targetBlock(state, block) {
        if (!block || state.userPaused || state.autoPaused || state.status !== 'playing' || state.cascadeActive || state.animation) return;
        state.targetBlockId = block.id;
        state.keyboardBlockId = block.id;
        state.instabilityUsedThisAction = false;
        state.instabilityPending = false;
        if (block.hp === 2) {
            block.hp = 1;
            changeStability(state, -CRACK_STABILITY_COST);
            if (state.stability <= 0) {
                stabilityGameOver(state);
                return;
            }
            if (state.stability <= STABILITY_CRITICAL) {
                setStatus(state, 'DANGER：ヒビでStabilityが低下しました。Chainで回復を狙ってください。');
            } else if (state.stability <= STABILITY_WARNING) {
                setStatus(state, 'WARNING：構造が不安定です。次の操作でBlockがずれる可能性があります。');
            } else {
                setStatus(state, 'ヒビを入れました。Stability -' + String(CRACK_STABILITY_COST) + '。崩落先を考えてください。');
            }
            draw(state);
            if (maybeDestabilize(state)) return;
            return;
        }
        if (state.breaksLeft <= 0) {
            state.status = 'gameover';
            state.gameOverReason = 'breaks';
            setStatus(state, 'Breakが残っていません。Restartで再挑戦してください。');
            setCardStatus(state);
            draw(state);
            return;
        }
        block.hp = 0;
        block.alive = false;
        state.targetBlockId = null;
        state.keyboardBlockId = null;
        state.broken += 1;
        state.breaksLeft -= 1;
        state.score += DIRECT_BREAK_SCORE;
        state.combo = 0;
        changeStability(state, -DIRECT_BREAK_STABILITY_COST);
        if (state.stability <= 0) {
            stabilityGameOver(state);
            return;
        }
        updateSummary(state);
        setStatus(state, 'Break！ Stability -' + String(DIRECT_BREAK_STABILITY_COST) + '。落下Chainで回復を狙ってください。');
        draw(state);
        startCascade(state);
    }

    function tapCanvas(state, event) {
        if (state.userPaused || state.autoPaused || state.status !== 'playing' || state.cascadeActive || state.animation) return;
        var block = hitBlock(state, event.clientX, event.clientY);
        if (!block) {
            state.targetBlockId = null;
            setStatus(state, 'BlockをTapしてください。');
            draw(state);
            return;
        }
        targetBlock(state, block);
    }

    function blockCenter(block) {
        return {x: block.x + block.w / 2, y: block.y + .5};
    }

    function keyboardNeighbor(state, current, key) {
        var blocks = activeBlocks(state);
        if (blocks.length === 0) return null;
        if (!current) return blocks[0];
        var origin = blockCenter(current);
        var best = null;
        var bestScore = Number.POSITIVE_INFINITY;
        for (var index = 0; index < blocks.length; index++) {
            var candidate = blocks[index];
            if (candidate.id === current.id) continue;
            var center = blockCenter(candidate);
            var dx = center.x - origin.x;
            var dy = center.y - origin.y;
            var allowed = (key === 'ArrowLeft' && dx < 0)
                || (key === 'ArrowRight' && dx > 0)
                || (key === 'ArrowUp' && dy < 0)
                || (key === 'ArrowDown' && dy > 0);
            if (!allowed) continue;
            var primary = (key === 'ArrowLeft' || key === 'ArrowRight') ? Math.abs(dx) : Math.abs(dy);
            var secondary = (key === 'ArrowLeft' || key === 'ArrowRight') ? Math.abs(dy) : Math.abs(dx);
            var score = primary * 10 + secondary;
            if (score < bestScore) {
                best = candidate;
                bestScore = score;
            }
        }
        return best || current;
    }

    function canvasKeydown(state, event) {
        if (state.userPaused || state.autoPaused || state.status !== 'playing' || state.cascadeActive || state.animation) return;
        var key = event.key;
        var current = findBlockById(state, state.keyboardBlockId);
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(key) !== -1) {
            event.preventDefault();
            var next = keyboardNeighbor(state, current, key);
            if (next) {
                state.keyboardBlockId = next.id;
                state.targetBlockId = next.id;
                setStatus(state, 'Enter / Spaceで選択中のBlockを操作出来ます。');
                draw(state);
            }
            return;
        }
        if (key === 'Enter' || key === ' ') {
            event.preventDefault();
            if (!current) {
                var blocks = activeBlocks(state);
                current = blocks.length > 0 ? blocks[0] : null;
            }
            if (current) targetBlock(state, current);
        }
    }

    function togglePause(state) {
        if (state.status === 'cleared' || state.status === 'gameover') return;
        state.userPaused = !state.userPaused;
        if (state.userPaused) {
            stopRaf(state);
            setStatus(state, '一時停止中です。');
        } else {
            setStatus(state, state.cascadeActive ? '崩落を再開します。' : '少ないBreakでChainを狙ってください。');
            if (state.animation) scheduleFrame(state);
            else if (state.cascadeActive) resolveNextStep(state);
        }
        setCardStatus(state);
        draw(state);
    }

    function applyFreshState(state, blocks, rememberLayout) {
        stopRaf(state);
        state.blocks = cloneBlocks(blocks);
        settleInitial(state);
        state.initialBlocks = cloneBlocks(state.blocks);
        state.broken = 0;
        state.breakLimit = BREAK_LIMIT;
        state.breaksLeft = BREAK_LIMIT;
        state.score = 0;
        state.combo = 0;
        state.maxCombo = 0;
        state.stability = STABILITY_MAX;
        state.instabilityPending = false;
        state.instabilityUsedThisAction = false;
        state.fallStressThisCascade = 0;
        state.chainRecoveryThisCascade = 0;
        state.gameOverReason = '';
        state.status = 'playing';
        state.userPaused = false;
        state.autoPaused = false;
        state.cascadeActive = false;
        state.animation = null;
        state.hoverBlockId = null;
        state.targetBlockId = null;
        state.keyboardBlockId = activeBlocks(state).length > 0 ? activeBlocks(state)[0].id : null;
        if (rememberLayout === false) state.initialBlocks = cloneBlocks(state.blocks);
        updateSummary(state);
        setCardStatus(state);
        setStatus(state, 'Break 8回以内。ヒビや大落下でStabilityが下がり、Chainで回復します。');
        draw(state);
    }

    function restartGame(state) {
        applyFreshState(state, state.initialBlocks, true);
        setStatus(state, '同じ盤面をRestartしました。別の崩し方を試してください。');
    }

    function newGame(state) {
        applyFreshState(state, makeBlocks(), false);
        setStatus(state, '新しい盤面です。支点と落下先を探してください。');
    }

    function createPanel(card) {
        var body = card.querySelector('.mini-game-card-body');
        if (!body) return null;
        body.innerHTML = '';

        var panel = document.createElement('div');
        panel.className = 'block-collapse-panel';
        panel.innerHTML = ''
            + '<div class="block-collapse-summary" aria-label="Block Collapse状況">'
            + '<div class="block-collapse-stat"><span>Blocks</span><strong class="block-collapse-blocks">0</strong></div>'
            + '<div class="block-collapse-stat"><span>Breaks</span><strong class="block-collapse-breaks">8 / 8</strong></div>'
            + '<div class="block-collapse-stat block-collapse-stability-stat"><span>Stability</span><strong class="block-collapse-stability">100%</strong></div>'
            + '<div class="block-collapse-stat"><span>Score</span><strong class="block-collapse-score">0</strong></div>'
            + '<div class="block-collapse-stat"><span>Combo</span><strong class="block-collapse-combo">—</strong></div>'
            + '</div>'
            + '<div class="block-collapse-stability-track" role="progressbar" aria-label="Stability" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" aria-valuetext="安定度 100%"><span class="block-collapse-stability-fill"></span></div>'
            + '<div class="block-collapse-canvas-wrap"><canvas class="block-collapse-canvas" width="640" height="480" tabindex="0" aria-label="Block Collapse盤面。1回目でヒビ、2回目でBreak。ヒビと直接Break、大きな落下でStabilityが低下し、Chainで回復します。"></canvas></div>'
            + '<p class="block-collapse-status" aria-live="polite" aria-atomic="true">準備中...</p>'
            + '<div class="block-collapse-controls" role="group" aria-label="Block Collapse操作"><button type="button" class="btn btn-sm btn-outline-secondary block-collapse-pause">一時停止</button><button type="button" class="btn btn-sm btn-outline-secondary block-collapse-restart">Restart</button><button type="button" class="btn btn-sm btn-outline-primary block-collapse-new-game">New Game</button></div>'
            + '<p class="block-collapse-help">ヒビ -3、直接Break -8、大きな落下でもStabilityが低下。ChainはStabilityを回復します。30%以下は支えの弱いBlockがずれる危険域、0%でCollapseです。PCは矢印＋Enter/Spaceでも操作出来ます。</p>';
        body.appendChild(panel);
        return panel;
    }

    function initCard(card) {
        if (!card || card.getAttribute('data-block-collapse-initialized') === '1') return;
        card.setAttribute('data-mini-game-initialized', '1');
        card.setAttribute('data-block-collapse-initialized', '1');
        var panel = createPanel(card);
        if (!panel) return;
        var canvas = panel.querySelector('.block-collapse-canvas');
        var ctx = canvas && typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
        if (!canvas || !ctx) {
            setStatus({card: card}, 'Canvasを利用出来ません。');
            return;
        }
        var state = {
            card: card,
            canvas: canvas,
            ctx: ctx,
            blocks: [],
            initialBlocks: [],
            broken: 0,
            breakLimit: BREAK_LIMIT,
            breaksLeft: BREAK_LIMIT,
            score: 0,
            combo: 0,
            maxCombo: 0,
            stability: STABILITY_MAX,
            instabilityPending: false,
            instabilityUsedThisAction: false,
            fallStressThisCascade: 0,
            chainRecoveryThisCascade: 0,
            gameOverReason: '',
            status: 'playing',
            userPaused: false,
            autoPaused: false,
            cascadeActive: false,
            animation: null,
            rafId: null,
            hoverBlockId: null,
            targetBlockId: null,
            keyboardBlockId: null
        };
        states.push(state);
        card.__rssBlockCollapseState = state;
        applyFreshState(state, makeBlocks(), false);

        canvas.addEventListener('pointermove', function (event) {
            if (event.pointerType && event.pointerType !== 'mouse') return;
            var block = hitBlock(state, event.clientX, event.clientY);
            var nextId = block ? block.id : null;
            if (nextId !== state.hoverBlockId) {
                state.hoverBlockId = nextId;
                draw(state);
            }
        });
        canvas.addEventListener('pointerleave', function () {
            if (state.hoverBlockId !== null) {
                state.hoverBlockId = null;
                draw(state);
            }
        });
        canvas.addEventListener('pointerdown', function (event) {
            var block = hitBlock(state, event.clientX, event.clientY);
            if (block) {
                state.targetBlockId = block.id;
                state.keyboardBlockId = block.id;
                draw(state);
            }
        });
        canvas.addEventListener('click', function (event) {
            tapCanvas(state, event);
        });
        canvas.addEventListener('keydown', function (event) {
            canvasKeydown(state, event);
        });
        panel.querySelector('.block-collapse-pause').addEventListener('click', function () {
            togglePause(state);
        });
        panel.querySelector('.block-collapse-restart').addEventListener('click', function () {
            restartGame(state);
        });
        panel.querySelector('.block-collapse-new-game').addEventListener('click', function () {
            newGame(state);
        });
        var canvasWrap = panel.querySelector('.block-collapse-canvas-wrap');
        if (canvasWrap) {
            canvasWrap.addEventListener('animationend', function () {
                canvasWrap.classList.remove('is-shaking');
            });
        }
    }

    function initCards() {
        reserveCards();
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="block_collapse"]');
        for (var index = 0; index < cards.length; index++) initCard(cards[index]);
    }

    function cleanupDisconnected() {
        var next = [];
        for (var index = 0; index < states.length; index++) {
            var state = states[index];
            if (!state.card || !state.card.isConnected) {
                stopRaf(state);
                continue;
            }
            next.push(state);
        }
        states = next;
    }

    function pauseForVisibility() {
        for (var index = 0; index < states.length; index++) {
            var state = states[index];
            if (document.hidden || pageHidden) {
                state.autoPaused = true;
                stopRaf(state);
                draw(state);
            } else if (state.autoPaused) {
                state.autoPaused = false;
                draw(state);
                if (!state.userPaused) {
                    if (state.animation) scheduleFrame(state);
                    else if (state.cascadeActive) resolveNextStep(state);
                }
            }
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
            var target = event.target;
            handlePresetClick(target);
            var edit = target && target.closest ? target.closest('.mini-game-edit-trigger') : null;
            if (edit) {
                var select = document.getElementById('changeGameType');
                if (select) select.setAttribute('data-block-collapse-previous-type', String(select.value || 'icon_quest'));
            }
        });

        document.addEventListener('visibilitychange', pauseForVisibility);
        window.addEventListener('pagehide', function () {
            pageHidden = true;
            pauseForVisibility();
        });
        window.addEventListener('pageshow', function () {
            pageHidden = false;
            pauseForVisibility();
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

    // Reserve immediately. mini-game.js initializes unknown game types as
    // Icon Quest at DOMContentLoaded, so Block Collapse must claim its cards first.
    reserveCards();

    window.RssBlockCollapse = {
        init: initCards,
        stopAll: function () {
            for (var index = 0; index < states.length; index++) stopRaf(states[index]);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
    } else {
        start();
    }
})(window, document);
