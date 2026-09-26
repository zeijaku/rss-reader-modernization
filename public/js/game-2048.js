/* V1.37-A: 2048 Game Widget. Vanilla JavaScript, browser-local Best Score, no network request. */
(function (window, document) {
    'use strict';

    var SIZE = 4;
    var CELL_COUNT = SIZE * SIZE;
    var SWIPE_THRESHOLD = 24;
    var STORAGE_PREFIX = 'rssReader.game2048.v1';
    var MAX_SCORE = 999999999;
    var states = [];
    var observer = null;
    var memoryStorage = Object.create(null);
    var storage = null;
    var storageMode = 'memory';

    var DEFAULT_TITLES = {
        icon_quest: 'Icon Quest',
        lights_out: 'Lights Out',
        wire_defense: 'Wire Defense',
        block_collapse: 'Block Collapse',
        cursor_field: 'Cursor Field',
        game_2048: '2048'
    };

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }
    function integerInRange(value, min, max) {
        return Number.isInteger(value) && value >= min && value <= max;
    }
    function reserveCards() {
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="game_2048"]');
        for (var i = 0; i < cards.length; i++) cards[i].setAttribute('data-mini-game-initialized', '1');
    }
    function addGameOption(select) {
        if (!select || select.querySelector('option[value="game_2048"]')) return;
        var option = document.createElement('option');
        option.value = 'game_2048';
        option.textContent = '2048（4×4 Number Puzzle）';
        select.appendChild(option);
    }
    function ensureGameOptions() {
        var register = document.getElementById('registerGameType');
        var change = document.getElementById('changeGameType');
        addGameOption(register);
        addGameOption(change);
        if (register && !register.getAttribute('data-game-2048-previous-type')) register.setAttribute('data-game-2048-previous-type', String(register.value || 'icon_quest'));
        if (change && !change.getAttribute('data-game-2048-previous-type')) change.setAttribute('data-game-2048-previous-type', String(change.value || 'icon_quest'));
    }
    function replaceVisibleText(node, names, replacement) {
        if (!node || !node.childNodes) return false;
        for (var i = 0; i < node.childNodes.length; i++) {
            var child = node.childNodes[i];
            if (child.nodeType === 3) {
                var value = String(child.nodeValue || '');
                for (var n = 0; n < names.length; n++) {
                    if (value.indexOf(names[n]) !== -1) {
                        child.nodeValue = value.replace(names[n], replacement);
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
        if (!catalog || catalog.querySelector('[data-game-preset="game_2048"]')) return;
        var template = catalog.querySelector('[data-game-preset="cursor_field"]')
            || catalog.querySelector('[data-game-preset="block_collapse"]')
            || catalog.querySelector('[data-game-preset="wire_defense"]')
            || catalog.querySelector('[data-game-preset="lights_out"]');
        if (!template) return;
        var button = template.cloneNode(true);
        button.setAttribute('data-game-preset', 'game_2048');
        replaceVisibleText(button, ['Cursor Field', 'Block Collapse', 'Wire Defense', 'Lights Out'], '2048');
        if (button.hasAttribute('aria-label')) button.setAttribute('aria-label', '2048を追加');
        if (button.hasAttribute('title')) button.setAttribute('title', '2048');
        var icon = button.querySelector('i');
        if (icon && icon.classList) icon.className = 'fas fa-th fa-fw';
        template.insertAdjacentElement('afterend', button);
    }
    function knownDefaultTitle(value) {
        var keys = Object.keys(DEFAULT_TITLES);
        for (var i = 0; i < keys.length; i++) if (DEFAULT_TITLES[keys[i]] === value) return true;
        return false;
    }
    function syncGameTitle(select) {
        var isChange = select.classList.contains('changeGameType');
        var title = document.querySelector(isChange ? '.changeGameTitleValue' : '.registerGameTitleValue');
        var currentType = String(select.value || 'icon_quest');
        var previousType = String(select.getAttribute('data-game-2048-previous-type') || 'icon_quest');
        var currentTitle = title ? String(title.value || '').trim() : '';
        if (title && (currentTitle === '' || currentTitle === (DEFAULT_TITLES[previousType] || '') || knownDefaultTitle(currentTitle))) {
            title.value = DEFAULT_TITLES[currentType] || 'Icon Quest';
        }
        select.setAttribute('data-game-2048-previous-type', currentType);
    }
    function handlePresetClick(target) {
        var button = target && target.closest ? target.closest('[data-game-preset="game_2048"][data-drawer-modal-target="#registerGameWidget"]') : null;
        if (!button) return;
        var select = document.getElementById('registerGameType');
        var title = document.querySelector('.registerGameTitleValue');
        if (!select) return;
        addGameOption(select);
        select.value = 'game_2048';
        select.setAttribute('data-game-2048-previous-type', 'game_2048');
        if (title) title.value = '2048';
        if (typeof window.Event === 'function') select.dispatchEvent(new window.Event('change', {bubbles: true}));
    }

    function browserStorage(name) {
        try { return window[name] || null; } catch (error) { return null; }
    }
    function storageAvailable(candidate) {
        if (!candidate) return false;
        try {
            var key = STORAGE_PREFIX + '.probe';
            candidate.setItem(key, '1');
            candidate.removeItem(key);
            return true;
        } catch (error) { return false; }
    }
    function selectStorage() {
        var local = browserStorage('localStorage');
        var session = browserStorage('sessionStorage');
        if (storageAvailable(local)) { storage = local; storageMode = 'localStorage'; return; }
        if (storageAvailable(session)) { storage = session; storageMode = 'sessionStorage'; return; }
        storage = null;
        storageMode = 'memory';
    }
    function dashboardUserId() {
        var main = document.getElementById('main-content');
        return main ? positiveId(main.getAttribute('data-dashboard-user-id')) : null;
    }
    function storageKey(userId, widgetId) {
        var user = positiveId(userId), widget = positiveId(widgetId);
        return user === null || widget === null ? null : STORAGE_PREFIX + '.user.' + user + '.widget.' + widget;
    }
    function scoreValue(value) {
        var text = String(value === undefined || value === null ? '' : value);
        if (!/^\d{1,9}$/.test(text)) return 0;
        var score = Number(text);
        return integerInRange(score, 0, MAX_SCORE) ? score : 0;
    }
    function readStorage(candidate, key) {
        try { return candidate && key ? candidate.getItem(key) : null; } catch (error) { return null; }
    }
    function loadBest(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) return 0;
        return Math.max(
            scoreValue(readStorage(browserStorage('localStorage'), key)),
            scoreValue(readStorage(browserStorage('sessionStorage'), key)),
            scoreValue(memoryStorage[key])
        );
    }
    function saveBest(userId, widgetId, score) {
        var key = storageKey(userId, widgetId);
        var value = scoreValue(score);
        if (key === null || value !== score) return false;
        if (storage !== null) {
            try { storage.setItem(key, String(value)); return true; }
            catch (error) {
                if (storageMode === 'localStorage') {
                    var fallback = browserStorage('sessionStorage');
                    if (storageAvailable(fallback)) {
                        storage = fallback; storageMode = 'sessionStorage';
                        try { storage.setItem(key, String(value)); return true; } catch (fallbackError) {}
                    }
                }
                storage = null; storageMode = 'memory';
            }
        }
        memoryStorage[key] = String(value);
        return true;
    }
    function removeEverywhere(key) {
        if (!key) return;
        ['localStorage', 'sessionStorage'].forEach(function (name) {
            var candidate = browserStorage(name);
            try { if (candidate) candidate.removeItem(key); } catch (error) {}
        });
        delete memoryStorage[key];
    }
    function removeWidgetState(widgetId) { removeEverywhere(storageKey(dashboardUserId(), widgetId)); }

    function cloneBoard(board) { return Array.isArray(board) ? board.slice(0, CELL_COUNT) : []; }
    function validBoard(board) {
        if (!Array.isArray(board) || board.length !== CELL_COUNT) return false;
        for (var i = 0; i < CELL_COUNT; i++) {
            var value = board[i];
            if (!Number.isInteger(value) || value < 0 || (value !== 0 && (value & (value - 1)) !== 0)) return false;
        }
        return true;
    }
    function moveLineLeft(line) {
        if (!Array.isArray(line) || line.length !== SIZE) return {line: [0, 0, 0, 0], score: 0, changed: false};
        var compact = line.filter(function (value) { return value !== 0; });
        var merged = [], score = 0;
        for (var i = 0; i < compact.length; i++) {
            if (i + 1 < compact.length && compact[i] === compact[i + 1]) {
                var value = compact[i] * 2;
                merged.push(value); score += value; i += 1;
            } else merged.push(compact[i]);
        }
        while (merged.length < SIZE) merged.push(0);
        var changed = false;
        for (var cell = 0; cell < SIZE; cell++) if (merged[cell] !== line[cell]) { changed = true; break; }
        return {line: merged, score: score, changed: changed};
    }
    function boardIndex(row, column) { return row * SIZE + column; }
    function moveBoard(board, direction) {
        if (!validBoard(board) || ['left', 'right', 'up', 'down'].indexOf(direction) === -1) {
            return {board: validBoard(board) ? cloneBoard(board) : new Array(CELL_COUNT).fill(0), score: 0, changed: false};
        }
        var next = cloneBoard(board), totalScore = 0, changed = false;
        for (var outer = 0; outer < SIZE; outer++) {
            var line = [];
            for (var inner = 0; inner < SIZE; inner++) {
                var row = direction === 'up' || direction === 'down' ? inner : outer;
                var column = direction === 'up' || direction === 'down' ? outer : inner;
                line.push(board[boardIndex(row, column)]);
            }
            if (direction === 'right' || direction === 'down') line.reverse();
            var result = moveLineLeft(line);
            var output = result.line.slice();
            if (direction === 'right' || direction === 'down') output.reverse();
            for (var target = 0; target < SIZE; target++) {
                var targetRow = direction === 'up' || direction === 'down' ? target : outer;
                var targetColumn = direction === 'up' || direction === 'down' ? outer : target;
                next[boardIndex(targetRow, targetColumn)] = output[target];
            }
            totalScore += result.score;
            changed = changed || result.changed;
        }
        return {board: next, score: totalScore, changed: changed};
    }
    function canMove(board) {
        if (!validBoard(board)) return false;
        for (var i = 0; i < CELL_COUNT; i++) {
            if (board[i] === 0) return true;
            var row = Math.floor(i / SIZE), column = i % SIZE;
            if (column + 1 < SIZE && board[i] === board[i + 1]) return true;
            if (row + 1 < SIZE && board[i] === board[i + SIZE]) return true;
        }
        return false;
    }
    function maxTile(board) {
        var max = 0;
        for (var i = 0; i < board.length; i++) max = Math.max(max, board[i]);
        return max;
    }
    function randomInt(max) {
        if (!(max > 0)) return 0;
        if (window.crypto && typeof window.crypto.getRandomValues === 'function' && typeof Uint32Array === 'function') {
            var values = new Uint32Array(1);
            window.crypto.getRandomValues(values);
            return values[0] % max;
        }
        return Math.floor(Math.random() * max);
    }
    function spawnTile(board, forcedEmptyOffset, forcedValue) {
        var next = cloneBoard(board), empty = [];
        for (var i = 0; i < CELL_COUNT; i++) if (next[i] === 0) empty.push(i);
        if (empty.length === 0) return next;
        var offset = integerInRange(forcedEmptyOffset, 0, empty.length - 1) ? forcedEmptyOffset : randomInt(empty.length);
        var value = forcedValue === 2 || forcedValue === 4 ? forcedValue : (randomInt(10) === 0 ? 4 : 2);
        next[empty[offset]] = value;
        return next;
    }
    function freshBoard() {
        var board = new Array(CELL_COUNT).fill(0);
        return spawnTile(spawnTile(board));
    }
    function storageNote() {
        if (storageMode === 'localStorage') return 'Best Scoreをこの端末に保存します。';
        if (storageMode === 'sessionStorage') return 'Best ScoreをこのTab内に保存します。';
        return 'Storageを利用出来ないためBest Scoreはこの画面内だけ保持します。';
    }
    function tileClass(value) { return value >= 4096 ? 'tile-super' : 'tile-' + value; }
    function setStatus(state, message, kind) {
        if (!state.status) return;
        state.status.textContent = message;
        state.status.classList.toggle('text-danger', kind === 'danger');
        state.status.classList.toggle('text-success', kind === 'success');
        state.status.classList.toggle('text-muted', !kind);
    }
    function render(state) {
        state.scoreNode.textContent = String(state.score);
        state.bestNode.textContent = String(state.best);
        for (var i = 0; i < CELL_COUNT; i++) {
            var cell = state.cells[i], value = state.board[i];
            cell.className = 'game-2048-cell ' + tileClass(value);
            cell.textContent = value === 0 ? '' : String(value);
            cell.setAttribute('aria-label', (Math.floor(i / SIZE) + 1) + '行' + (i % SIZE + 1) + '列、' + (value === 0 ? '空き' : String(value)));
        }
        state.boardNode.setAttribute('aria-label', '2048 4×4盤面。Score ' + state.score + '、Best ' + state.best);
    }
    function updateBest(state) {
        if (state.score <= state.best) return;
        state.best = state.score;
        saveBest(state.userId, state.widgetId, state.best);
    }
    function animateMove(state, direction) {
        if (!state || !state.boardNode || ['left', 'right', 'up', 'down'].indexOf(direction) === -1) return;
        var classes = ['game-2048-move-left', 'game-2048-move-right', 'game-2048-move-up', 'game-2048-move-down'];
        for (var i = 0; i < classes.length; i++) state.boardNode.classList.remove(classes[i]);
        void state.boardNode.offsetWidth;
        var activeClass = 'game-2048-move-' + direction;
        state.boardNode.classList.add(activeClass);
        if (state.moveAnimationTimer !== null && typeof window.clearTimeout === 'function') window.clearTimeout(state.moveAnimationTimer);
        state.moveAnimationTimer = typeof window.setTimeout === 'function' ? window.setTimeout(function () {
            state.boardNode.classList.remove(activeClass);
            state.moveAnimationTimer = null;
        }, 150) : null;
    }
    function applyMove(state, direction) {
        if (!state || state.gameOver) return false;
        var result = moveBoard(state.board, direction);
        if (!result.changed) {
            if (!canMove(state.board)) {
                state.gameOver = true;
                setStatus(state, 'Game Over。New GameまたはRestartで再開できます。', 'danger');
            }
            return false;
        }
        state.board = spawnTile(result.board);
        state.score = Math.min(MAX_SCORE, state.score + result.score);
        updateBest(state);
        if (!state.reached2048 && maxTile(state.board) >= 2048) {
            state.reached2048 = true;
            setStatus(state, '2048達成。続けてPlayできます。', 'success');
        } else if (!canMove(state.board)) {
            state.gameOver = true;
            setStatus(state, 'Game Over。New GameまたはRestartで再開できます。', 'danger');
        } else setStatus(state, 'Arrow KeyまたはSwipeで同じ数字を合わせてください。', '');
        render(state);
        animateMove(state, direction);
        return true;
    }
    function resetFromBoard(state, board, rememberInitial) {
        state.board = cloneBoard(board);
        if (rememberInitial) state.initialBoard = cloneBoard(board);
        state.score = 0; state.gameOver = false; state.reached2048 = false;
        render(state);
    }
    function newGame(state) {
        resetFromBoard(state, freshBoard(), true);
        setStatus(state, 'New Gameを開始しました。', '');
        if (state.boardNode && typeof state.boardNode.focus === 'function') state.boardNode.focus();
    }
    function restartGame(state) {
        resetFromBoard(state, state.initialBoard, false);
        setStatus(state, '開始時の盤面からRestartしました。', '');
        if (state.boardNode && typeof state.boardNode.focus === 'function') state.boardNode.focus();
    }
    function keyDirection(key) {
        return {ArrowLeft:'left',ArrowRight:'right',ArrowUp:'up',ArrowDown:'down',a:'left',A:'left',d:'right',D:'right',w:'up',W:'up',s:'down',S:'down'}[key] || null;
    }
    function swipeDirection(startX, startY, endX, endY) {
        var dx = endX - startX, dy = endY - startY;
        if (Math.max(Math.abs(dx), Math.abs(dy)) < SWIPE_THRESHOLD) return null;
        if (Math.abs(dx) > Math.abs(dy)) return dx < 0 ? 'left' : 'right';
        return dy < 0 ? 'up' : 'down';
    }

    function createPanel(card) {
        var body = card.querySelector('.mini-game-card-body');
        if (!body) return null;
        body.innerHTML = '';
        var panel = document.createElement('div');
        panel.className = 'game-2048-panel';
        panel.innerHTML = ''
            + '<div class="game-2048-summary" aria-label="2048状況"><div><span>Score</span><strong class="game-2048-score">0</strong></div><div><span>Best</span><strong class="game-2048-best">0</strong></div></div>'
            + '<div class="game-2048-board" role="grid" tabindex="0" aria-label="2048 4×4盤面"></div>'
            + '<p class="game-2048-status text-muted" aria-live="polite" aria-atomic="true">準備中...</p>'
            + '<div class="game-2048-controls" role="group" aria-label="2048操作"><button type="button" class="btn btn-sm btn-outline-secondary game-2048-restart">Restart</button><button type="button" class="btn btn-sm btn-outline-primary game-2048-new-game">New Game</button></div>'
            + '<p class="game-2048-help">PC: Arrow Key / WASD　Smartphone: 盤面をSwipe</p>'
            + '<p class="game-2048-storage-note text-muted"></p>';
        body.appendChild(panel);
        var board = panel.querySelector('.game-2048-board');
        for (var i = 0; i < CELL_COUNT; i++) {
            var cell = document.createElement('div');
            cell.className = 'game-2048-cell tile-0';
            cell.setAttribute('role', 'gridcell');
            cell.setAttribute('aria-rowindex', String(Math.floor(i / SIZE) + 1));
            cell.setAttribute('aria-colindex', String(i % SIZE + 1));
            cell.setAttribute('aria-label', (Math.floor(i / SIZE) + 1) + '行' + (i % SIZE + 1) + '列、空き');
            board.appendChild(cell);
        }
        return panel;
    }
    function initCard(card) {
        if (!card || card.getAttribute('data-game-2048-initialized') === '1') return;
        var widgetId = positiveId(card.getAttribute('data-dashboard-widget-id'));
        if (widgetId === null) return;
        card.setAttribute('data-mini-game-initialized', '1');
        card.setAttribute('data-game-2048-initialized', '1');
        var panel = createPanel(card);
        if (!panel) return;
        var boardNode = panel.querySelector('.game-2048-board');
        var initial = freshBoard();
        var userId = dashboardUserId();
        var state = {
            card:card, widgetId:widgetId, userId:userId, board:cloneBoard(initial), initialBoard:cloneBoard(initial),
            score:0, best:loadBest(userId, widgetId), gameOver:false, reached2048:false, boardNode:boardNode,
            cells:Array.prototype.slice.call(boardNode.querySelectorAll('.game-2048-cell')),
            scoreNode:panel.querySelector('.game-2048-score'), bestNode:panel.querySelector('.game-2048-best'),
            status:panel.querySelector('.game-2048-status'), pointerId:null, pointerStartX:0, pointerStartY:0, moveAnimationTimer:null
        };
        states.push(state);
        card.__rssGame2048State = state;
        panel.querySelector('.game-2048-storage-note').textContent = storageNote();
        render(state);
        setStatus(state, 'Arrow KeyまたはSwipeで同じ数字を合わせてください。', '');

        boardNode.addEventListener('keydown', function (event) {
            var direction = keyDirection(event.key);
            if (direction === null) return;
            event.preventDefault();
            applyMove(state, direction);
        });
        boardNode.addEventListener('pointerdown', function (event) {
            if (event.pointerType && event.pointerType !== 'touch' && event.pointerType !== 'pen') {
                if (typeof boardNode.focus === 'function') boardNode.focus();
                return;
            }
            state.pointerId = event.pointerId; state.pointerStartX = event.clientX; state.pointerStartY = event.clientY;
            if (boardNode.setPointerCapture) { try { boardNode.setPointerCapture(event.pointerId); } catch (error) {} }
            event.preventDefault();
        });
        boardNode.addEventListener('pointerup', function (event) {
            if (state.pointerId === null || event.pointerId !== state.pointerId) return;
            var direction = swipeDirection(state.pointerStartX, state.pointerStartY, event.clientX, event.clientY);
            state.pointerId = null;
            if (direction !== null) applyMove(state, direction);
            event.preventDefault();
        });
        boardNode.addEventListener('pointercancel', function () { state.pointerId = null; });
        panel.querySelector('.game-2048-restart').addEventListener('click', function () { restartGame(state); });
        panel.querySelector('.game-2048-new-game').addEventListener('click', function () { newGame(state); });
    }
    function initCards() {
        reserveCards();
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="game_2048"]');
        for (var i = 0; i < cards.length; i++) initCard(cards[i]);
    }
    function cleanupDisconnected() { states = states.filter(function (state) { return state.card && state.card.isConnected; }); }
    function parseAjaxData(data) {
        var result = {};
        if (typeof data === 'string') {
            String(data).split('&').forEach(function (part) {
                var pair = part.split('=');
                if (!pair[0]) return;
                try { result[decodeURIComponent(pair[0].replace(/\+/g, ' '))] = decodeURIComponent(String(pair[1] || '').replace(/\+/g, ' ')); } catch (error) {}
            });
            return result;
        }
        if (data && typeof data === 'object') return data;
        return result;
    }
    function bindAjaxCleanup() {
        var jq = window.jQuery;
        if (!jq || !jq.fn || !document) return;
        jq(document).off('ajaxSuccess.rssGame2048').on('ajaxSuccess.rssGame2048', function (event, xhr, settings, response) {
            if (!response || response.ok !== true || !settings || String(settings.url || '').indexOf('api_v1.php') === -1) return;
            var payload = parseAjaxData(settings.data);
            var widgetId = positiveId(payload.widget_id);
            if (widgetId === null) return;
            if (payload.action === 'widget.game.delete') { removeWidgetState(widgetId); return; }
            if (payload.action === 'widget.game.update' && payload.game_type !== 'game_2048') removeWidgetState(widgetId);
        });
    }
    function start() {
        selectStorage();
        ensureGameOptions();
        ensureCatalogPreset();
        initCards();
        bindAjaxCleanup();
        document.addEventListener('change', function (event) {
            var target = event.target;
            if (target && (target.classList.contains('registerGameType') || target.classList.contains('changeGameType'))) syncGameTitle(target);
        });
        document.addEventListener('click', function (event) {
            handlePresetClick(event.target);
            var edit = event.target && event.target.closest ? event.target.closest('.mini-game-edit-trigger') : null;
            if (edit) {
                var select = document.getElementById('changeGameType');
                if (select) select.setAttribute('data-game-2048-previous-type', String(select.value || 'icon_quest'));
            }
        });
        if (typeof window.MutationObserver === 'function' && document.body) {
            observer = new window.MutationObserver(function () {
                reserveCards(); ensureGameOptions(); ensureCatalogPreset(); initCards(); cleanupDisconnected();
            });
            observer.observe(document.body, {childList:true, subtree:true});
        }
    }

    reserveCards();
    window.RssGame2048 = {SIZE:SIZE, moveLineLeft:moveLineLeft, moveBoard:moveBoard, canMove:canMove, spawnTile:spawnTile, swipeDirection:swipeDirection, init:initCards};
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else start();
})(window, document);
