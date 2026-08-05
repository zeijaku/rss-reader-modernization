(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.miniGame.iconQuest.v1';
    var GAME_VERSION = 1;
    var BOARD_SIZE = 5;
    var MAX_STATS = 999999;
    var memoryStorage = Object.create(null);
    var storageMode = 'memory';
    var storage = null;

    var DIRECTIONS = {
        up: {row: -1, column: 0},
        left: {row: 0, column: -1},
        right: {row: 0, column: 1},
        down: {row: 1, column: 0}
    };
    var ENEMY_DIRECTION_ORDER = ['up', 'left', 'right', 'down'];
    var KEY_DIRECTIONS = {
        ArrowUp: 'up', ArrowLeft: 'left', ArrowRight: 'right', ArrowDown: 'down',
        w: 'up', W: 'up', a: 'left', A: 'left', d: 'right', D: 'right', s: 'down', S: 'down'
    };

    var LEVELS = [
        {id: 'iq-01', name: 'Level 1', player: 0, enemy: 4, treasure: 12, goal: 24, walls: [2, 7, 10, 14, 17], maxMoves: 20},
        {id: 'iq-02', name: 'Level 2', player: 23, enemy: 10, treasure: 3, goal: 7, walls: [5, 8, 16, 17, 20], maxMoves: 20},
        {id: 'iq-03', name: 'Level 3', player: 10, enemy: 8, treasure: 6, goal: 21, walls: [4, 9, 11, 12, 15, 17], maxMoves: 20},
        {id: 'iq-04', name: 'Level 4', player: 21, enemy: 17, treasure: 9, goal: 3, walls: [4, 5, 10, 20, 23], maxMoves: 20}
    ];

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function integerInRange(value, min, max) {
        return Number.isInteger(value) && value >= min && value <= max;
    }

    function plainObject(value) {
        return value && Object.prototype.toString.call(value) === '[object Object]';
    }

    function levelById(levelId) {
        for (var index = 0; index < LEVELS.length; index++) {
            if (LEVELS[index].id === levelId) return LEVELS[index];
        }
        return null;
    }

    function nextLevelId(levelId) {
        for (var index = 0; index < LEVELS.length; index++) {
            if (LEVELS[index].id === levelId) return LEVELS[(index + 1) % LEVELS.length].id;
        }
        return LEVELS[0].id;
    }

    function copyBestMoves(value) {
        var result = {};
        if (!plainObject(value)) return result;
        for (var index = 0; index < LEVELS.length; index++) {
            var level = LEVELS[index];
            var moves = value[level.id];
            if (integerInRange(moves, 1, level.maxMoves)) result[level.id] = moves;
        }
        return result;
    }

    function copyStats(value) {
        if (!plainObject(value) || !integerInRange(value.wins, 0, MAX_STATS) || !integerInRange(value.losses, 0, MAX_STATS)) {
            return {wins: 0, losses: 0};
        }
        return {wins: value.wins, losses: value.losses};
    }

    function validateBestMoves(value) {
        if (!plainObject(value)) return null;
        var known = Object.create(null);
        for (var index = 0; index < LEVELS.length; index++) known[LEVELS[index].id] = LEVELS[index];
        var keys = Object.keys(value);
        for (var keyIndex = 0; keyIndex < keys.length; keyIndex++) {
            var key = keys[keyIndex];
            var level = known[key];
            if (!level || !integerInRange(value[key], 1, level.maxMoves)) return null;
        }
        return copyBestMoves(value);
    }

    function validateStats(value) {
        if (!plainObject(value) || !integerInRange(value.wins, 0, MAX_STATS) || !integerInRange(value.losses, 0, MAX_STATS)) return null;
        return {wins: value.wins, losses: value.losses};
    }

    function initialState(levelId, previous) {
        var level = levelById(levelId) || LEVELS[0];
        var source = plainObject(previous) ? previous : {};
        return {
            schema: 1, game: 'icon_quest', gameVersion: GAME_VERSION, levelId: level.id,
            player: level.player, enemy: level.enemy, treasureCollected: false, moves: 0, enemyPhase: 0,
            status: 'playing', resultReason: '', bestMoves: copyBestMoves(source.bestMoves), stats: copyStats(source.stats),
            tutorialSeen: source.tutorialSeen === true, savedAt: 0
        };
    }

    function defaultState() { return initialState(LEVELS[0].id, null); }
    function isWall(level, cellIndex) { return level.walls.indexOf(cellIndex) !== -1; }
    function statePositionIsValid(level, cellIndex) { return integerInRange(cellIndex, 0, 24) && !isWall(level, cellIndex); }

    function validateState(value) {
        if (!plainObject(value) || value.schema !== 1 || value.game !== 'icon_quest' || value.gameVersion !== GAME_VERSION) return null;
        var level = levelById(value.levelId);
        if (level === null || !statePositionIsValid(level, value.player) || !statePositionIsValid(level, value.enemy)
            || typeof value.treasureCollected !== 'boolean' || !integerInRange(value.moves, 0, level.maxMoves)
            || !integerInRange(value.enemyPhase, 0, 1) || ['playing', 'won', 'lost'].indexOf(value.status) === -1
            || ['', 'clear', 'enemy', 'moves'].indexOf(value.resultReason) === -1 || typeof value.tutorialSeen !== 'boolean'
            || !integerInRange(value.savedAt, 0, Number.MAX_SAFE_INTEGER)) return null;
        var bestMoves = validateBestMoves(value.bestMoves);
        var stats = validateStats(value.stats);
        if (bestMoves === null || stats === null) return null;
        if (value.status === 'playing' && (value.resultReason !== '' || value.player === value.enemy)) return null;
        if (value.status === 'won' && (value.resultReason !== 'clear' || value.treasureCollected !== true || value.player !== level.goal)) return null;
        if (value.status === 'lost' && (['enemy', 'moves'].indexOf(value.resultReason) === -1
            || (value.resultReason === 'enemy' && value.player !== value.enemy)
            || (value.resultReason === 'moves' && value.moves < level.maxMoves))) return null;
        return {
            schema: 1, game: 'icon_quest', gameVersion: GAME_VERSION, levelId: level.id,
            player: value.player, enemy: value.enemy, treasureCollected: value.treasureCollected, moves: value.moves,
            enemyPhase: value.enemyPhase, status: value.status, resultReason: value.resultReason,
            bestMoves: bestMoves, stats: stats, tutorialSeen: value.tutorialSeen, savedAt: value.savedAt
        };
    }

    function cloneState(value) {
        return {
            schema: value.schema, game: value.game, gameVersion: value.gameVersion, levelId: value.levelId,
            player: value.player, enemy: value.enemy, treasureCollected: value.treasureCollected, moves: value.moves,
            enemyPhase: value.enemyPhase, status: value.status, resultReason: value.resultReason,
            bestMoves: copyBestMoves(value.bestMoves), stats: copyStats(value.stats), tutorialSeen: value.tutorialSeen, savedAt: value.savedAt
        };
    }

    function storageAvailable(candidate) {
        if (!candidate) return false;
        try {
            var key = STORAGE_PREFIX + '.probe';
            candidate.setItem(key, '1'); candidate.removeItem(key); return true;
        } catch (error) { return false; }
    }

    function browserStorage(name) { try { return window[name] || null; } catch (error) { return null; } }
    function selectStorage() {
        var local = browserStorage('localStorage');
        if (storageAvailable(local)) { storage = local; storageMode = 'localStorage'; return; }
        var session = browserStorage('sessionStorage');
        if (storageAvailable(session)) { storage = session; storageMode = 'sessionStorage'; return; }
        storage = null; storageMode = 'memory';
    }
    function selectFallbackStorage() {
        if (storageMode === 'localStorage') {
            var session = browserStorage('sessionStorage');
            if (storageAvailable(session)) { storage = session; storageMode = 'sessionStorage'; return; }
        }
        storage = null; storageMode = 'memory';
    }
    function storageKey(userId, widgetId) {
        var safeUserId = positiveId(userId), safeWidgetId = positiveId(widgetId);
        return safeUserId === null || safeWidgetId === null ? null : STORAGE_PREFIX + '.user.' + safeUserId + '.widget.' + safeWidgetId;
    }
    function readRaw(key) {
        if (storage !== null) {
            try { return storage.getItem(key); }
            catch (error) {
                selectFallbackStorage();
                if (storage !== null) try { return storage.getItem(key); } catch (fallbackError) { storage = null; storageMode = 'memory'; }
            }
        }
        return Object.prototype.hasOwnProperty.call(memoryStorage, key) ? memoryStorage[key] : null;
    }
    function writeRaw(key, value) {
        if (storage !== null) {
            try { storage.setItem(key, value); return true; }
            catch (error) {
                selectFallbackStorage();
                if (storage !== null) try { storage.setItem(key, value); return true; } catch (fallbackError) { storage = null; storageMode = 'memory'; }
            }
        }
        memoryStorage[key] = value; return true;
    }
    function removeRaw(key) {
        if (storage !== null) {
            try { storage.removeItem(key); }
            catch (error) {
                selectFallbackStorage();
                if (storage !== null) try { storage.removeItem(key); } catch (fallbackError) { storage = null; storageMode = 'memory'; }
            }
        }
        delete memoryStorage[key];
    }
    function loadState(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) return defaultState();
        var raw = readRaw(key);
        if (raw === null || raw === '') return defaultState();
        try {
            var normalized = validateState(JSON.parse(raw));
            if (normalized === null) { removeRaw(key); return defaultState(); }
            return normalized;
        } catch (error) { removeRaw(key); return defaultState(); }
    }
    function saveState(userId, widgetId, state) {
        var key = storageKey(userId, widgetId);
        if (key === null) return false;
        var candidate = cloneState(state); candidate.savedAt = Date.now();
        var normalized = validateState(candidate);
        if (normalized === null) return false;
        try {
            var written = writeRaw(key, JSON.stringify(normalized));
            if (written) state.savedAt = normalized.savedAt;
            return written;
        } catch (error) { return false; }
    }
    function removeWidgetState(widgetId) {
        var main = document.getElementById('main-content');
        var key = storageKey(main ? main.getAttribute('data-dashboard-user-id') : '', widgetId);
        if (key === null) return false;
        removeRaw(key); return true;
    }

    function adjacentCell(cellIndex, direction, level) {
        var move = DIRECTIONS[direction];
        if (!move || !integerInRange(cellIndex, 0, 24)) return null;
        var row = Math.floor(cellIndex / BOARD_SIZE), column = cellIndex % BOARD_SIZE;
        var nextRow = row + move.row, nextColumn = column + move.column;
        if (nextRow < 0 || nextRow >= BOARD_SIZE || nextColumn < 0 || nextColumn >= BOARD_SIZE) return null;
        var next = nextRow * BOARD_SIZE + nextColumn;
        return isWall(level, next) ? null : next;
    }
    function directionBetween(from, to) {
        if (!integerInRange(from, 0, 24) || !integerInRange(to, 0, 24)) return null;
        var difference = to - from;
        if (difference === -5) return 'up'; if (difference === 5) return 'down';
        if (difference === -1 && Math.floor(from / 5) === Math.floor(to / 5)) return 'left';
        if (difference === 1 && Math.floor(from / 5) === Math.floor(to / 5)) return 'right';
        return null;
    }
    function nextEnemyStep(enemy, player, level) {
        if (enemy === player) return enemy;
        var queue = [{cell: enemy, first: null}], visited = Object.create(null); visited[String(enemy)] = true;
        while (queue.length > 0) {
            var current = queue.shift();
            for (var index = 0; index < ENEMY_DIRECTION_ORDER.length; index++) {
                var next = adjacentCell(current.cell, ENEMY_DIRECTION_ORDER[index], level);
                if (next === null || visited[String(next)] === true) continue;
                var first = current.first === null ? next : current.first;
                if (next === player) return first;
                visited[String(next)] = true; queue.push({cell: next, first: first});
            }
        }
        return enemy;
    }
    function finishState(state, status, reason) {
        state.status = status; state.resultReason = reason; state.enemyPhase = 0;
        if (status === 'won') {
            var previousBest = state.bestMoves[state.levelId];
            if (!integerInRange(previousBest, 1, 20) || state.moves < previousBest) state.bestMoves[state.levelId] = state.moves;
            state.stats.wins = Math.min(MAX_STATS, state.stats.wins + 1);
        } else if (status === 'lost') state.stats.losses = Math.min(MAX_STATS, state.stats.losses + 1);
    }
    function applyMove(value, direction) {
        var normalized = validateState(value);
        if (normalized === null || normalized.status !== 'playing') return {state: normalized || defaultState(), changed: false, event: 'inactive', enemyMoved: false};
        var level = levelById(normalized.levelId), target = adjacentCell(normalized.player, direction, level);
        if (target === null) return {state: normalized, changed: false, event: 'blocked', enemyMoved: false};
        var next = cloneState(normalized); next.player = target; next.moves += 1; next.enemyPhase += 1;
        var event = 'moved', enemyMoved = false;
        if (!next.treasureCollected && next.player === level.treasure) { next.treasureCollected = true; event = 'treasure'; }
        if (next.player === next.enemy) { finishState(next, 'lost', 'enemy'); return {state: next, changed: true, event: 'lost-enemy', enemyMoved: false}; }
        if (next.treasureCollected && next.player === level.goal) { finishState(next, 'won', 'clear'); return {state: next, changed: true, event: 'won', enemyMoved: false}; }
        if (next.moves >= level.maxMoves) { finishState(next, 'lost', 'moves'); return {state: next, changed: true, event: 'lost-moves', enemyMoved: false}; }
        if (next.enemyPhase >= 2) {
            next.enemyPhase = 0; next.enemy = nextEnemyStep(next.enemy, next.player, level); enemyMoved = true;
            if (next.enemy === next.player) { finishState(next, 'lost', 'enemy'); return {state: next, changed: true, event: 'lost-enemy', enemyMoved: true}; }
        }
        return {state: next, changed: true, event: event, enemyMoved: enemyMoved};
    }

    function cellView(state, cellIndex) {
        var level = levelById(state.levelId) || LEVELS[0];
        var row = Math.floor(cellIndex / 5) + 1, column = cellIndex % 5 + 1, labels = [], classes = ['mini-game-cell'], icon = '';
        if (isWall(level, cellIndex)) { labels.push('Wall'); classes.push('mini-game-cell-wall'); icon = 'fas fa-cube'; }
        else {
            if (cellIndex === level.goal) { labels.push('Goal'); classes.push('mini-game-cell-goal'); }
            if (!state.treasureCollected && cellIndex === level.treasure) { labels.push('Treasure'); classes.push('mini-game-cell-treasure'); }
            if (cellIndex === state.player) { labels.unshift('Player'); classes.push('mini-game-cell-player'); icon = 'fas fa-user-shield'; }
            if (cellIndex === state.enemy) { labels.push('Enemy'); classes.push('mini-game-cell-enemy'); icon = 'fas fa-skull-crossbones'; }
            if (icon === '' && !state.treasureCollected && cellIndex === level.treasure) icon = 'fas fa-gem';
            if (icon === '' && cellIndex === level.goal) icon = 'fas fa-door-open';
            if (labels.length === 0) { labels.push('空きマス'); classes.push('mini-game-cell-floor'); }
        }
        return {label: row + '行' + column + '列、' + labels.join('、'), classes: classes, icon: icon, player: cellIndex === state.player};
    }
    function setText(card, selector, text) { var target = card.querySelector(selector); if (target) target.textContent = String(text); }
    function replaceCellContent(cell, view) {
        while (cell.firstChild) cell.removeChild(cell.firstChild);
        var node = document.createElement(view.icon !== '' ? 'i' : 'span');
        if (view.icon !== '') node.setAttribute('class', view.icon); else node.textContent = '·';
        node.setAttribute('aria-hidden', 'true'); cell.appendChild(node);
    }
    function statusMessage(state, event, enemyMoved) {
        if (state.status === 'won') return 'Clear！ ' + state.moves + '手で出口へ到達しました';
        if (state.status === 'lost' && state.resultReason === 'enemy') return 'Game Over：敵に捕まりました';
        if (state.status === 'lost' && state.resultReason === 'moves') return 'Game Over：手数の上限に達しました';
        if (event === 'blocked') return 'その方向には進めません';
        if (event === 'treasure') return 'Treasureを取得しました。Goalへ向かってください';
        if (enemyMoved) return '敵が1マス近づきました';
        if (event === 'restored') return '保存した途中状態を復元しました';
        if (event === 'reset') return '現在のLevelを最初からやり直します';
        if (event === 'new-game') return '新しいLevelを開始しました';
        return 'Treasureを取り、敵を避けてGoalへ進んでください';
    }
    function renderCard(card, state, event, enemyMoved, focusPlayer) {
        var level = levelById(state.levelId) || LEVELS[0], cells = card.querySelectorAll('.mini-game-cell');
        for (var index = 0; index < cells.length; index++) {
            var cell = cells[index], cellIndex = Number(cell.getAttribute('data-mini-game-cell-index')), view = cellView(state, cellIndex);
            cell.setAttribute('class', view.classes.join(' ')); cell.setAttribute('aria-label', view.label);
            cell.setAttribute('aria-disabled', state.status === 'playing' && !isWall(level, cellIndex) ? 'false' : 'true');
            cell.setAttribute('tabindex', view.player ? '0' : '-1');
            if (view.player) cell.setAttribute('aria-current', 'true'); else cell.removeAttribute('aria-current');
            replaceCellContent(cell, view); if (view.player && focusPlayer && typeof cell.focus === 'function') cell.focus();
        }
        setText(card, '.mini-game-level', level.name); setText(card, '.mini-game-moves', state.moves + ' / ' + level.maxMoves);
        setText(card, '.mini-game-best', state.bestMoves[level.id] ? String(state.bestMoves[level.id]) : '--');
        setText(card, '.mini-game-treasure-state', state.treasureCollected ? '取得済み' : '未取得');
        setText(card, '.mini-game-enemy-turn', state.status === 'playing' ? String(2 - state.enemyPhase) : '--');
        setText(card, '.mini-game-status', statusMessage(state, event, enemyMoved));
        var board = card.querySelector('.mini-game-board');
        if (board) { board.setAttribute('aria-label', 'Icon Quest 5×5盤面、' + level.name); board.setAttribute('data-mini-game-status', state.status); }
        var directions = card.querySelectorAll('.mini-game-direction');
        for (var d = 0; d < directions.length; d++) directions[d].disabled = state.status !== 'playing';
        card.setAttribute('data-mini-game-level-id', level.id); card.setAttribute('data-mini-game-status', state.status);
    }
    function cardUserId() { var main = document.getElementById('main-content'); return main ? main.getAttribute('data-dashboard-user-id') : ''; }
    function persistAndRender(card, state, event, enemyMoved, focusPlayer) {
        saveState(cardUserId(), card.getAttribute('data-dashboard-widget-id'), state); card.__rssMiniGameState = state;
        renderCard(card, state, event, enemyMoved, focusPlayer);
        setText(card, '.mini-game-storage-note', storageMode === 'memory' ? 'このPageを閉じると進行状態は失われます' : '進行状態はこのBrowserへ保存されます');
    }
    function moveCard(card, direction, focusPlayer) {
        var result = applyMove(card.__rssMiniGameState, direction);
        if (!result.changed) { renderCard(card, result.state, result.event, result.enemyMoved, focusPlayer); return; }
        persistAndRender(card, result.state, result.event, result.enemyMoved, focusPlayer);
    }
    function cardCellClick(card, cell) {
        if (!card.__rssMiniGameState || card.__rssMiniGameState.status !== 'playing') return;
        var direction = directionBetween(card.__rssMiniGameState.player, Number(cell.getAttribute('data-mini-game-cell-index')));
        if (direction === null) { renderCard(card, card.__rssMiniGameState, 'blocked', false, false); return; }
        moveCard(card, direction, true);
    }
    function initCard(card) {
        if (!card || card.getAttribute('data-mini-game-initialized') === '1') return;
        card.setAttribute('data-mini-game-initialized', '1');
        var widgetId = card.getAttribute('data-dashboard-widget-id'), loaded = loadState(cardUserId(), widgetId);
        var restored = loaded.moves > 0 || loaded.status !== 'playing' || loaded.levelId !== LEVELS[0].id;
        card.__rssMiniGameState = loaded; persistAndRender(card, loaded, restored ? 'restored' : 'ready', false, false);
        card.addEventListener('click', function (event) {
            var target = event.target;
            var cell = target && typeof target.closest === 'function' ? target.closest('.mini-game-cell') : null;
            if (cell && card.contains(cell)) { cardCellClick(card, cell); return; }
            var button = target && typeof target.closest === 'function' ? target.closest('.mini-game-direction') : null;
            if (button && card.contains(button)) moveCard(card, button.getAttribute('data-mini-game-direction'), true);
        });
        var board = card.querySelector('.mini-game-board');
        if (board) board.addEventListener('keydown', function (event) {
            var direction = KEY_DIRECTIONS[event.key];
            if (direction) { event.preventDefault(); moveCard(card, direction, true); return; }
            if (event.key === 'Escape') {
                var newGame = card.querySelector('.mini-game-new-game');
                if (newGame && typeof newGame.focus === 'function') { event.preventDefault(); newGame.focus(); }
            }
        });
        var reset = card.querySelector('.mini-game-reset');
        if (reset) reset.addEventListener('click', function () {
            if (!window.confirm('現在のLevelを最初からやり直しますか？')) return;
            persistAndRender(card, initialState(card.__rssMiniGameState.levelId, card.__rssMiniGameState), 'reset', false, false);
        });
        var newGame = card.querySelector('.mini-game-new-game');
        if (newGame) newGame.addEventListener('click', function () {
            if (card.__rssMiniGameState.status === 'playing' && card.__rssMiniGameState.moves > 0
                && !window.confirm('現在の進行を破棄して次のLevelを開始しますか？')) return;
            persistAndRender(card, initialState(nextLevelId(card.__rssMiniGameState.levelId), card.__rssMiniGameState), 'new-game', false, false);
        });
    }
    function init() {
        selectStorage();
        var cards = document.querySelectorAll('[data-dashboard-widget-type="game"]');
        for (var index = 0; index < cards.length; index++) initCard(cards[index]);
    }

    window.RssMiniGame = {
        levels: LEVELS, storageKey: storageKey, defaultState: defaultState, initialState: initialState,
        validateState: validateState, loadState: loadState, saveState: saveState, removeWidgetState: removeWidgetState,
        directionBetween: directionBetween, nextEnemyStep: nextEnemyStep, applyMove: applyMove, cellView: cellView,
        storageMode: function () { return storageMode; }, init: init
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);
