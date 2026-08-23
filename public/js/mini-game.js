/* V1.20-D R6: Wire Defense drawer integration + interceptor missile / hex-server Core. */
(function (window, document) {
    'use strict';

    var observer = null;

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

    function ensureWireDefensePreset() {
        if (!document || typeof document.querySelector !== 'function') return false;
        var catalog = document.querySelector('#widgetCatalog-game');
        var template;
        var button;
        var icon;

        if (!catalog) return false;
        if (catalog.querySelector('[data-game-preset="wire_defense"]')) return true;

        template = catalog.querySelector('[data-drawer-modal-target="#registerGameWidget"][data-game-preset="lights_out"]');
        if (!template) return false;

        button = template.cloneNode(true);
        button.setAttribute('data-game-preset', 'wire_defense');
        replaceVisibleText(button, 'Lights Out', 'Wire Defense');

        if (button.hasAttribute('aria-label')) {
            button.setAttribute('aria-label', String(button.getAttribute('aria-label') || '').replace('Lights Out', 'Wire Defense'));
        }
        if (button.hasAttribute('title')) {
            button.setAttribute('title', String(button.getAttribute('title') || '').replace('Lights Out', 'Wire Defense'));
        }

        icon = button.querySelector('i');
        if (icon && icon.classList) {
            icon.classList.remove('far', 'fa-lightbulb');
            icon.classList.add('fas', 'fa-network-wired');
        }

        template.insertAdjacentElement('afterend', button);
        return true;
    }

    function selectWireDefensePreset(event) {
        if (!document || typeof document.querySelector !== 'function') return;
        var target = event && event.target ? event.target : null;
        var button = target && target.closest
            ? target.closest('[data-game-preset="wire_defense"][data-drawer-modal-target="#registerGameWidget"]')
            : null;
        var select;
        var title;
        var changeEvent;

        if (!button) return;

        select = document.querySelector('#registerGameType');
        title = document.querySelector('#registerGameWidgetForm .registerGameTitleValue');
        if (select && select.querySelector('option[value="wire_defense"]')) {
            select.value = 'wire_defense';
            if (typeof window.Event === 'function') {
                changeEvent = new window.Event('change', {bubbles: true});
            } else if (document.createEvent) {
                changeEvent = document.createEvent('Event');
                changeEvent.initEvent('change', true, false);
            }
            if (changeEvent) select.dispatchEvent(changeEvent);
        }
        if (title) title.value = 'Wire Defense';
    }

    function init() {
        if (ensureWireDefensePreset()) return;
        if (typeof MutationObserver !== 'function' || !document.documentElement) return;
        observer = new MutationObserver(function () {
            if (ensureWireDefensePreset() && observer) {
                observer.disconnect();
                observer = null;
            }
        });
        observer.observe(document.documentElement, {childList: true, subtree: true});
    }

    window.RssWireDefenseCatalog = {
        ensurePreset: ensureWireDefensePreset,
        selectPreset: selectWireDefensePreset
    };

    if (document && typeof document.addEventListener === 'function') {
        document.addEventListener('click', selectWireDefensePreset, true);
    }
    init();
    if (document && document.readyState === 'loading' && typeof document.addEventListener === 'function') {
        document.addEventListener('DOMContentLoaded', ensureWireDefensePreset, {once: true});
    }
})(window, document);

(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.miniGame.iconQuest.v1';
    var STORAGE_SCHEMA = 1;
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
            schema: STORAGE_SCHEMA, game: 'icon_quest', gameVersion: GAME_VERSION, levelId: level.id,
            player: level.player, enemy: level.enemy, treasureCollected: false, moves: 0, enemyPhase: 0,
            status: 'playing', resultReason: '', bestMoves: copyBestMoves(source.bestMoves), stats: copyStats(source.stats),
            tutorialSeen: source.tutorialSeen === true, savedAt: 0
        };
    }

    function defaultState() { return initialState(LEVELS[0].id, null); }
    function isWall(level, cellIndex) { return level.walls.indexOf(cellIndex) !== -1; }
    function statePositionIsValid(level, cellIndex) { return integerInRange(cellIndex, 0, 24) && !isWall(level, cellIndex); }

    function validateState(value) {
        if (!plainObject(value) || value.schema !== STORAGE_SCHEMA || value.game !== 'icon_quest' || value.gameVersion !== GAME_VERSION) return null;
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
            schema: STORAGE_SCHEMA, game: 'icon_quest', gameVersion: GAME_VERSION, levelId: level.id,
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
    function removeFromBrowserStorage(name, key) {
        var candidate = browserStorage(name);
        if (candidate === null) return;
        try { candidate.removeItem(key); } catch (error) {}
    }
    function removeEverywhere(key) {
        removeFromBrowserStorage('localStorage', key);
        removeFromBrowserStorage('sessionStorage', key);
        delete memoryStorage[key];
    }
    function browserStorageRaw(name, key) {
        var candidate = browserStorage(name);
        if (candidate === null) return {name: name, raw: null, available: false};
        try { return {name: name, raw: candidate.getItem(key), available: true}; }
        catch (error) { return {name: name, raw: null, available: false}; }
    }
    function removeStorageCopy(name, key) {
        if (name === 'memory') { delete memoryStorage[key]; return; }
        removeFromBrowserStorage(name, key);
    }
    function loadStateResult(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) return {state: defaultState(), recovered: false, reason: 'invalid-key'};
        var candidates = [browserStorageRaw('localStorage', key), browserStorageRaw('sessionStorage', key)];
        if (Object.prototype.hasOwnProperty.call(memoryStorage, key)) candidates.push({name: 'memory', raw: memoryStorage[key], available: true});
        var valid = [], invalid = [], hasValue = false;
        for (var index = 0; index < candidates.length; index++) {
            var candidate = candidates[index];
            if (!candidate.available || candidate.raw === null || candidate.raw === '') continue;
            hasValue = true;
            try {
                var normalized = validateState(JSON.parse(candidate.raw));
                if (normalized === null) invalid.push(candidate.name);
                else valid.push({name: candidate.name, state: normalized});
            } catch (error) { invalid.push(candidate.name); }
        }
        for (var invalidIndex = 0; invalidIndex < invalid.length; invalidIndex++) removeStorageCopy(invalid[invalidIndex], key);
        if (valid.length > 0) {
            valid.sort(function (left, right) {
                if (right.state.savedAt !== left.state.savedAt) return right.state.savedAt - left.state.savedAt;
                return left.name === 'localStorage' ? -1 : right.name === 'localStorage' ? 1 : 0;
            });
            return {state: valid[0].state, recovered: invalid.length > 0, reason: invalid.length > 0 ? 'repaired-copy' : 'restored'};
        }
        if (hasValue) return {state: defaultState(), recovered: true, reason: 'invalid-data'};
        return {state: defaultState(), recovered: false, reason: 'empty'};
    }
    function loadState(userId, widgetId) { return loadStateResult(userId, widgetId).state; }
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
        removeEverywhere(key); return true;
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

    function storageModeLabel() {
        if (storageMode === 'localStorage') return 'localStorage';
        if (storageMode === 'sessionStorage') return 'sessionStorage';
        return '一時Memory';
    }
    function storageNote(recovered) {
        if (recovered) return '保存データの異常なCopyを除去し、このWidgetを安全に復元しました（' + storageModeLabel() + '）';
        if (storageMode === 'localStorage') return '進行状態はこのBrowserへ保存されます';
        if (storageMode === 'sessionStorage') return '進行状態はこのTabのSessionへ保存されます';
        return 'Storageを利用出来ないため、このPageを閉じると進行状態は失われます';
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
        if (event === 'storage-reset') return 'このWidgetの進行状態と記録を削除しました';
        if (event === 'recovered') return '保存データを確認し、安全な状態へ復元しました';
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
        setText(card, '.mini-game-wins', state.stats.wins); setText(card, '.mini-game-losses', state.stats.losses);
        setText(card, '.mini-game-status', statusMessage(state, event, enemyMoved));
        var result = card.querySelector('.mini-game-result'), resultIcon = card.querySelector('.mini-game-result-icon');
        if (result) {
            result.hidden = state.status === 'playing';
            result.setAttribute('aria-hidden', state.status === 'playing' ? 'true' : 'false');
            result.setAttribute('class', 'mini-game-result' + (state.status === 'won' ? ' mini-game-result-won' : state.status === 'lost' ? ' mini-game-result-lost' : ''));
            setText(card, '.mini-game-result-text', state.status === 'won' ? 'CLEAR' : state.status === 'lost' ? 'GAME OVER' : '');
        }
        if (resultIcon) resultIcon.setAttribute('class', 'mini-game-result-icon fas ' + (state.status === 'won' ? 'fa-flag-checkered' : 'fa-skull-crossbones'));
        var board = card.querySelector('.mini-game-board');
        if (board) { board.setAttribute('aria-label', 'Icon Quest 5×5盤面、' + level.name); board.setAttribute('data-mini-game-status', state.status); }
        var directions = card.querySelectorAll('.mini-game-direction');
        for (var d = 0; d < directions.length; d++) directions[d].disabled = state.status !== 'playing';
        card.setAttribute('data-mini-game-level-id', level.id); card.setAttribute('data-mini-game-status', state.status);
    }
    function cardUserId() { var main = document.getElementById('main-content'); return main ? main.getAttribute('data-dashboard-user-id') : ''; }
    function setTutorialOpen(card, open) {
        var panel = card.querySelector('.mini-game-tutorial'), button = card.querySelector('.mini-game-tutorial-toggle');
        if (panel) panel.hidden = !open;
        if (button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function markTutorialSeen(card, hidePanel) {
        if (!card.__rssMiniGameState || card.__rssMiniGameState.tutorialSeen === true) {
            if (hidePanel) setTutorialOpen(card, false);
            return;
        }
        var state = cloneState(card.__rssMiniGameState); state.tutorialSeen = true;
        saveState(cardUserId(), card.getAttribute('data-dashboard-widget-id'), state); card.__rssMiniGameState = state;
        if (hidePanel) setTutorialOpen(card, false);
    }
    function persistAndRender(card, state, event, enemyMoved, focusPlayer, recovered) {
        saveState(cardUserId(), card.getAttribute('data-dashboard-widget-id'), state); card.__rssMiniGameState = state;
        renderCard(card, state, event, enemyMoved, focusPlayer);
        setText(card, '.mini-game-storage-note', storageNote(recovered === true));
    }
    function moveCard(card, direction, focusPlayer) {
        var result = applyMove(card.__rssMiniGameState, direction);
        if (!result.changed) { renderCard(card, result.state, result.event, result.enemyMoved, focusPlayer); return; }
        if (result.state.tutorialSeen !== true) result.state.tutorialSeen = true;
        setTutorialOpen(card, false);
        persistAndRender(card, result.state, result.event, result.enemyMoved, focusPlayer, false);
    }
    function cardCellClick(card, cell) {
        if (!card.__rssMiniGameState || card.__rssMiniGameState.status !== 'playing') return;
        var direction = directionBetween(card.__rssMiniGameState.player, Number(cell.getAttribute('data-mini-game-cell-index')));
        if (direction === null) { renderCard(card, card.__rssMiniGameState, 'blocked', false, false); return; }
        moveCard(card, direction, true);
    }
    function initCard(card) {
        if (!card || ['lights_out', 'wire_defense'].indexOf(card.getAttribute('data-mini-game-type')) !== -1 || card.getAttribute('data-mini-game-initialized') === '1') return;
        card.setAttribute('data-mini-game-initialized', '1');
        var widgetId = card.getAttribute('data-dashboard-widget-id'), loadedResult = loadStateResult(cardUserId(), widgetId), loaded = loadedResult.state;
        var restored = loaded.moves > 0 || loaded.status !== 'playing' || loaded.levelId !== LEVELS[0].id;
        card.__rssMiniGameState = loaded;
        persistAndRender(card, loaded, loadedResult.recovered ? 'recovered' : restored ? 'restored' : 'ready', false, false, loadedResult.recovered);
        setTutorialOpen(card, loaded.tutorialSeen !== true);
        card.addEventListener('click', function (event) {
            if (event.detail > 1) { event.preventDefault(); return; }
            if (event.detail === 0) {
                var now = Date.now();
                if (card.__rssMiniGameKeyboardClickAt && now - card.__rssMiniGameKeyboardClickAt < 120) { event.preventDefault(); return; }
                card.__rssMiniGameKeyboardClickAt = now;
            }
            var target = event.target;
            var cell = target && typeof target.closest === 'function' ? target.closest('.mini-game-cell') : null;
            if (cell && card.contains(cell)) { cardCellClick(card, cell); return; }
            var button = target && typeof target.closest === 'function' ? target.closest('.mini-game-direction') : null;
            if (button && card.contains(button)) moveCard(card, button.getAttribute('data-mini-game-direction'), true);
        });
        var board = card.querySelector('.mini-game-board');
        if (board) board.addEventListener('keydown', function (event) {
            var direction = KEY_DIRECTIONS[event.key];
            if (direction) {
                event.preventDefault();
                if (event.repeat) return;
                moveCard(card, direction, true); return;
            }
            if (event.key === 'Escape') {
                var newGame = card.querySelector('.mini-game-new-game');
                if (newGame && typeof newGame.focus === 'function') { event.preventDefault(); newGame.focus(); }
            }
        });
        var reset = card.querySelector('.mini-game-reset');
        if (reset) reset.addEventListener('click', function () {
            if (!window.confirm('現在のLevelを最初からやり直しますか？')) return;
            persistAndRender(card, initialState(card.__rssMiniGameState.levelId, card.__rssMiniGameState), 'reset', false, false, false);
        });
        var newGame = card.querySelector('.mini-game-new-game');
        if (newGame) newGame.addEventListener('click', function () {
            if (card.__rssMiniGameState.status === 'playing' && card.__rssMiniGameState.moves > 0
                && !window.confirm('現在の進行を破棄して次のLevelを開始しますか？')) return;
            persistAndRender(card, initialState(nextLevelId(card.__rssMiniGameState.levelId), card.__rssMiniGameState), 'new-game', false, false, false);
        });
        var tutorial = card.querySelector('.mini-game-tutorial-toggle');
        if (tutorial) tutorial.addEventListener('click', function () {
            var open = tutorial.getAttribute('aria-expanded') !== 'true';
            setTutorialOpen(card, open);
            if (!open) markTutorialSeen(card, false);
        });
        var storageReset = card.querySelector('.mini-game-storage-reset');
        if (storageReset) storageReset.addEventListener('click', function () {
            if (!window.confirm('このWidgetの進行・Best・勝敗記録・Tutorial確認状態を削除しますか？')) return;
            removeWidgetState(widgetId);
            var state = defaultState();
            persistAndRender(card, state, 'storage-reset', false, false, false);
            setTutorialOpen(card, true);
        });
    }
    function init() {
        selectStorage();
        var cards = document.querySelectorAll('[data-dashboard-widget-type="game"]');
        for (var index = 0; index < cards.length; index++) initCard(cards[index]);
    }

    window.RssMiniGame = {
        levels: LEVELS, storageKey: storageKey, defaultState: defaultState, initialState: initialState,
        validateState: validateState, loadState: loadState, loadStateResult: loadStateResult, saveState: saveState,
        removeWidgetState: removeWidgetState, directionBetween: directionBetween, nextEnemyStep: nextEnemyStep,
        applyMove: applyMove, cellView: cellView, storageMode: function () { return storageMode; }, init: init
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);

/* V1.20-C RSS Typing Game */
(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.rssTyping.v1';
    var GAME_SECONDS = 60;
    var SCORE_MAX = 99999999;
    var storage = null;
    var storageMode = 'memory';
    var memoryStorage = Object.create(null);
    var activeSession = null;

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function normalizeText(value) {
        var text = String(value || '');
        try { return typeof text.normalize === 'function' ? text.normalize('NFC') : text; }
        catch (error) { return text; }
    }

    function textLength(value) { return Array.from(normalizeText(value)).length; }

    function evaluateInput(target, typed) {
        var expected = normalizeText(target), entered = normalizeText(typed);
        return {
            valid: expected.indexOf(entered) === 0,
            complete: entered !== '' && expected === entered,
            enteredLength: textLength(entered),
            targetLength: textLength(expected)
        };
    }

    function scoreTitle(title) { return textLength(title) * 10; }

    function browserStorage(name) {
        try { return window[name] || null; }
        catch (error) { return null; }
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
        var local = browserStorage('localStorage'), session = browserStorage('sessionStorage');
        if (storageAvailable(local)) { storage = local; storageMode = 'localStorage'; return; }
        if (storageAvailable(session)) { storage = session; storageMode = 'sessionStorage'; return; }
        storage = null;
        storageMode = 'memory';
    }

    function storageKey(userId, contentId) {
        var user = positiveId(userId), content = positiveId(contentId);
        return user === null || content === null ? null : STORAGE_PREFIX + '.user.' + user + '.feed.' + content;
    }

    function dashboardUserId() {
        var main = document.getElementById('main-content');
        return main ? positiveId(main.getAttribute('data-dashboard-user-id')) : null;
    }

    function storageRead(candidate, key) {
        try { return candidate ? candidate.getItem(key) : null; }
        catch (error) { return null; }
    }

    function bestValue(raw) {
        if (!/^\d{1,8}$/.test(String(raw || ''))) return 0;
        var score = Number(raw);
        return Number.isInteger(score) && score >= 0 && score <= SCORE_MAX ? score : 0;
    }

    function loadBest(userId, contentId) {
        var key = storageKey(userId, contentId);
        if (key === null) return 0;
        var local = bestValue(storageRead(browserStorage('localStorage'), key));
        var session = bestValue(storageRead(browserStorage('sessionStorage'), key));
        var memory = bestValue(memoryStorage[key]);
        return Math.max(local, session, memory);
    }

    function saveBest(userId, contentId, score) {
        var key = storageKey(userId, contentId), value = bestValue(score);
        if (key === null || value !== score) return false;
        if (storage !== null) {
            try { storage.setItem(key, String(value)); return true; }
            catch (error) {
                if (storageMode === 'localStorage') {
                    var fallback = browserStorage('sessionStorage');
                    if (storageAvailable(fallback)) {
                        storage = fallback;
                        storageMode = 'sessionStorage';
                        try { storage.setItem(key, String(value)); return true; }
                        catch (fallbackError) {}
                    }
                }
                storage = null;
                storageMode = 'memory';
            }
        }
        memoryStorage[key] = String(value);
        return true;
    }

    function collectTitles(card) {
        var result = [], nodes = card ? card.querySelectorAll('.content-body .feed-item-title-text[data-full-title]') : [];
        for (var i = 0; i < nodes.length && result.length < 30; i++) {
            var title = String(nodes[i].getAttribute('data-full-title') || '').trim();
            if (title !== '') result.push(title);
        }
        return result;
    }

    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = String(text);
        return node;
    }

    function closeArticleActionsMenu() {
        var menu = document.getElementById('articleActionsMenu');
        if (menu) menu.hidden = true;
        var triggers = document.querySelectorAll('.article-actions-trigger[aria-expanded="true"]');
        for (var i = 0; i < triggers.length; i++) triggers[i].setAttribute('aria-expanded', 'false');
    }

    function triggerView(card, active) {
        var button = card ? card.querySelector('.rss-typing-trigger') : null, icon;
        if (!button) return;
        icon = button.querySelector('i');
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.setAttribute('aria-label', active ? 'RSS Typingを終了して記事一覧へ戻る' : 'RSS Typingを開始');
        button.setAttribute('title', active ? 'RSS Typingを終了' : 'RSS Typing');
        if (icon) {
            icon.classList.toggle('fa-keyboard', !active);
            icon.classList.toggle('fa-times', active);
        }
    }

    function syncTrigger(card) {
        var button = card ? card.querySelector('.rss-typing-trigger') : null;
        if (!button) return;
        if (activeSession && activeSession.card === card) {
            button.disabled = false;
            triggerView(card, true);
            return;
        }
        button.disabled = card.getAttribute('data-feed-state') !== 'ready'
            || card.getAttribute('aria-busy') === 'true'
            || collectTitles(card).length === 0;
        triggerView(card, false);
    }

    function addTrigger(card) {
        var actions = card ? card.querySelector('.feed-card-actions') : null;
        if (!actions || actions.querySelector('.rss-typing-trigger')) return;
        var button = element('button', 'btn btn-link rss-typing-trigger');
        var icon = element('i', 'fas fa-keyboard');
        button.type = 'button';
        button.setAttribute('data-dashboard-swipe-ignore', 'true');
        icon.setAttribute('aria-hidden', 'true');
        button.appendChild(icon);
        actions.insertBefore(button, actions.firstChild);
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (activeSession && activeSession.card === card) stopSession(true);
            else startSession(card);
        });
        button.addEventListener('pointerdown', function (event) { event.stopPropagation(); });
        syncTrigger(card);
    }

    function stat(label, className) {
        var item = element('span', 'rss-typing-stat'), value = element('strong', className);
        item.appendChild(document.createTextNode(label + ' '));
        item.appendChild(value);
        return item;
    }

    function buildGameBody(session) {
        var body = element('tbody', 'rss-typing-body'), row = element('tr'), cell = element('td');
        var panel = element('div', 'rss-typing-panel'), stats = element('div', 'rss-typing-stats');
        var progress = element('div', 'rss-typing-progress-text'), target = element('div', 'rss-typing-target');
        var label = element('label', 'visually-hidden', 'RSSタイトルを入力');
        var input = element('input', 'form-control rss-typing-input'), status = element('p', 'rss-typing-status');
        var result = element('div', 'rss-typing-result'), actions = element('div', 'rss-typing-actions');
        var retry = element('button', 'btn btn-sm btn-outline-primary rss-typing-retry', 'もう一度');
        var exit = element('button', 'btn btn-sm btn-outline-secondary rss-typing-exit', 'RSSへ戻る');

        stats.appendChild(stat('残り', 'rss-typing-time'));
        stats.appendChild(stat('Score', 'rss-typing-score'));
        stats.appendChild(stat('Best', 'rss-typing-best'));
        stats.appendChild(stat('Round', 'rss-typing-round'));
        target.setAttribute('role', 'text');
        target.setAttribute('aria-label', '入力するRSSタイトル');
        input.type = 'text';
        input.id = 'rss-typing-input-' + session.contentId;
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('enterkeyhint', 'next');
        input.setAttribute('data-dashboard-swipe-ignore', 'true');
        label.setAttribute('for', input.id);
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        result.hidden = true;
        retry.type = 'button';
        retry.hidden = true;
        exit.type = 'button';
        panel.setAttribute('data-dashboard-swipe-ignore', 'true');
        actions.appendChild(retry);
        actions.appendChild(exit);
        panel.appendChild(stats);
        panel.appendChild(progress);
        panel.appendChild(target);
        panel.appendChild(label);
        panel.appendChild(input);
        panel.appendChild(status);
        panel.appendChild(result);
        panel.appendChild(actions);
        cell.colSpan = 3;
        cell.appendChild(panel);
        row.appendChild(cell);
        body.appendChild(row);
        session.body = body;
        session.panel = panel;
        session.target = target;
        session.input = input;
        session.status = status;
        session.result = result;
        session.retry = retry;
        session.exit = exit;
        return body;
    }

    function renderTarget(session, typed) {
        var title = session.titles[session.titleIndex] || '', check = evaluateInput(title, typed || '');
        var count = check.valid ? check.enteredLength : 0, chars = Array.from(title);
        while (session.target.firstChild) session.target.removeChild(session.target.firstChild);
        if (count > 0) session.target.appendChild(element('span', 'rss-typing-target-matched', chars.slice(0, count).join('')));
        session.target.appendChild(element('span', 'rss-typing-target-remaining', chars.slice(count).join('')));
    }

    function updateStats(session) {
        var remaining = session.state === 'playing' ? Math.max(0, session.deadline - Date.now()) : 0;
        session.panel.querySelector('.rss-typing-time').textContent = (remaining / 1000).toFixed(1) + 's';
        session.panel.querySelector('.rss-typing-score').textContent = String(session.score);
        session.panel.querySelector('.rss-typing-best').textContent = String(Math.max(session.best, session.score));
        session.panel.querySelector('.rss-typing-round').textContent = String(session.round);
        session.panel.querySelector('.rss-typing-progress-text').textContent = (session.titleIndex + 1) + ' / ' + session.titles.length
            + '　完了 ' + session.completed + '　Miss ' + session.misses;
    }

    function clearTimer(session) {
        if (session && session.timer !== null) {
            window.clearInterval(session.timer);
            session.timer = null;
        }
    }

    function saveBestIfNeeded(session) {
        if (session.score <= session.best) return;
        session.best = session.score;
        saveBest(session.userId, session.contentId, session.best);
    }

    function finish(session) {
        if (!session || session.state !== 'playing') return;
        session.state = 'finished';
        clearTimer(session);
        saveBestIfNeeded(session);
        session.input.disabled = true;
        session.input.classList.remove('is-invalid');
        session.retry.hidden = false;
        session.result.hidden = false;
        session.result.textContent = 'Time Up　Score ' + session.score + ' / 完了 ' + session.completed + ' / Miss ' + session.misses + ' / Best ' + session.best;
        session.status.textContent = 'もう一度挑戦するか、RSSへ戻ってください。';
        updateStats(session);
        if (typeof session.retry.focus === 'function') session.retry.focus();
    }

    function startTimer(session) {
        if (!session || session.state !== 'playing' || session.timer !== null || document.hidden) return;
        session.timer = window.setInterval(function () {
            if (activeSession !== session || session.state !== 'playing') { clearTimer(session); return; }
            if (Date.now() >= session.deadline) { finish(session); return; }
            updateStats(session);
        }, 100);
    }

    function nextTitle(session) {
        session.score = Math.min(SCORE_MAX, session.score + scoreTitle(session.titles[session.titleIndex]));
        session.completed += 1;
        session.titleIndex += 1;
        if (session.titleIndex >= session.titles.length) { session.titleIndex = 0; session.round += 1; }
        session.lastWrong = '';
        session.input.value = '';
        session.input.classList.remove('is-invalid');
        session.status.textContent = 'Clear！ 次のタイトルを入力してください。';
        renderTarget(session, '');
        updateStats(session);
    }

    function processInput(session) {
        if (!session || session.state !== 'playing' || session.composing) return;
        var value = String(session.input.value || ''), check = evaluateInput(session.titles[session.titleIndex], value);
        session.input.classList.toggle('is-invalid', !check.valid);
        renderTarget(session, check.valid ? value : '');
        if (!check.valid) {
            if (value !== '' && value !== session.lastWrong) session.misses += 1;
            session.lastWrong = value;
            session.status.textContent = 'タイトルと一致していません。Backspaceで修正してください。';
        } else {
            session.lastWrong = '';
            session.status.textContent = value === '' ? '表示されたタイトルを入力してください。日本語IME対応 / EscでRSSへ戻ります。' : '一致しています。続きを入力してください。';
            if (check.complete) { nextTitle(session); return; }
        }
        updateStats(session);
    }

    function setControls(session, disabled) {
        var selectors = ['.content-edit-trigger', '.feed-refresh-trigger', '.widget-drag-handle'];
        if (disabled) session.controlState = [];
        if (disabled) {
            for (var i = 0; i < selectors.length; i++) {
                var nodes = session.card.querySelectorAll(selectors[i]);
                for (var j = 0; j < nodes.length; j++) {
                    session.controlState.push({node: nodes[j], disabled: nodes[j].disabled === true});
                    nodes[j].disabled = true;
                }
            }
            return;
        }
        for (var k = 0; k < session.controlState.length; k++) session.controlState[k].node.disabled = session.controlState[k].disabled;
        session.controlState = [];
    }

    function resetSession(session) {
        clearTimer(session);
        session.best = loadBest(session.userId, session.contentId);
        session.score = 0;
        session.completed = 0;
        session.misses = 0;
        session.titleIndex = 0;
        session.round = 1;
        session.composing = false;
        session.lastWrong = '';
        session.hiddenAt = 0;
        session.state = 'playing';
        session.deadline = Date.now() + GAME_SECONDS * 1000;
        session.input.disabled = false;
        session.input.value = '';
        session.input.classList.remove('is-invalid');
        session.result.hidden = true;
        session.result.textContent = '';
        session.retry.hidden = true;
        session.status.textContent = '表示されたタイトルを入力してください。日本語IME対応 / EscでRSSへ戻ります。';
        renderTarget(session, '');
        updateStats(session);
        startTimer(session);
        if (typeof session.input.focus === 'function') session.input.focus();
    }

    function bindSession(session) {
        session.onInput = function () { processInput(session); };
        session.onStartComposition = function () {
            session.composing = true;
            session.input.classList.remove('is-invalid');
            session.status.textContent = 'IME変換中です。確定後に判定します。';
        };
        session.onEndComposition = function () { session.composing = false; processInput(session); };
        session.onRetry = function () { resetSession(session); };
        session.onExit = function () { stopSession(true); };
        session.onKey = function (event) {
            if (activeSession === session && event.key === 'Escape') { event.preventDefault(); stopSession(true); }
        };
        session.onVisibility = function () {
            if (activeSession !== session || session.state !== 'playing') return;
            if (document.hidden) {
                session.hiddenAt = Date.now();
                clearTimer(session);
                session.status.textContent = 'Tabが非表示のためTimerを一時停止しています。';
                return;
            }
            if (session.hiddenAt > 0) { session.deadline += Date.now() - session.hiddenAt; session.hiddenAt = 0; }
            session.status.textContent = '再開しました。続きを入力してください。';
            startTimer(session);
            updateStats(session);
        };
        session.onPageHide = function () { clearTimer(session); };
        session.input.addEventListener('input', session.onInput);
        session.input.addEventListener('compositionstart', session.onStartComposition);
        session.input.addEventListener('compositionend', session.onEndComposition);
        session.retry.addEventListener('click', session.onRetry);
        session.exit.addEventListener('click', session.onExit);
        session.panel.addEventListener('keydown', session.onKey);
        document.addEventListener('visibilitychange', session.onVisibility);
        window.addEventListener('pagehide', session.onPageHide);
    }

    function unbindSession(session) {
        session.input.removeEventListener('input', session.onInput);
        session.input.removeEventListener('compositionstart', session.onStartComposition);
        session.input.removeEventListener('compositionend', session.onEndComposition);
        session.retry.removeEventListener('click', session.onRetry);
        session.exit.removeEventListener('click', session.onExit);
        session.panel.removeEventListener('keydown', session.onKey);
        document.removeEventListener('visibilitychange', session.onVisibility);
        window.removeEventListener('pagehide', session.onPageHide);
    }

    function startSession(card) {
        var titles, contentId, userId, originalBody, session;
        if (!card || card.classList.contains('search-feed-card') || card.getAttribute('data-feed-state') !== 'ready' || card.getAttribute('aria-busy') === 'true') return false;
        titles = collectTitles(card);
        contentId = positiveId(card.getAttribute('data-feed-content-id'));
        userId = dashboardUserId();
        originalBody = card.querySelector('.content-body');
        if (titles.length === 0 || contentId === null || userId === null || !originalBody) return false;
        if (activeSession) stopSession(false);
        closeArticleActionsMenu();
        session = {
            card: card, titles: titles, contentId: contentId, userId: userId, originalBody: originalBody,
            originalHidden: originalBody.hidden === true, timer: null, controlState: []
        };
        activeSession = session;
        card.setAttribute('data-rss-typing-active', '1');
        originalBody.hidden = true;
        buildGameBody(session);
        originalBody.parentNode.insertBefore(session.body, originalBody.nextSibling);
        setControls(session, true);
        triggerView(card, true);
        bindSession(session);
        resetSession(session);
        return true;
    }

    function stopSession(focusTrigger) {
        var session = activeSession;
        if (!session) return;
        activeSession = null;
        clearTimer(session);
        saveBestIfNeeded(session);
        unbindSession(session);
        setControls(session, false);
        if (session.body && session.body.parentNode) session.body.parentNode.removeChild(session.body);
        session.originalBody.hidden = session.originalHidden;
        session.card.removeAttribute('data-rss-typing-active');
        syncTrigger(session.card);
        if (focusTrigger === true) {
            var trigger = session.card.querySelector('.rss-typing-trigger');
            if (trigger && typeof trigger.focus === 'function') trigger.focus();
        }
    }

    function initCard(card) {
        if (!card || card.classList.contains('search-feed-card') || card.getAttribute('data-rss-typing-initialized') === '1') return;
        card.setAttribute('data-rss-typing-initialized', '1');
        addTrigger(card);
        if (typeof window.MutationObserver === 'function') {
            card.__rssTypingObserver = new window.MutationObserver(function () { syncTrigger(card); });
            card.__rssTypingObserver.observe(card, {attributes: true, attributeFilter: ['data-feed-state', 'aria-busy']});
        }
    }

    function init() {
        selectStorage();
        var cards = document.querySelectorAll('.feed-card[data-feed-content-id]:not(.search-feed-card)');
        for (var i = 0; i < cards.length; i++) initCard(cards[i]);
    }

    window.RssTypingGame = {
        GAME_SECONDS: GAME_SECONDS,
        normalizeText: normalizeText,
        textLength: textLength,
        evaluateInput: evaluateInput,
        scoreTitle: scoreTitle,
        storageKey: storageKey,
        collectTitles: collectTitles,
        loadBest: loadBest,
        start: startSession,
        stop: function () { stopSession(false); },
        storageMode: function () { return storageMode; },
        init: init
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);

/* V1.20-D R7 Wire Defense: missile reload, damage palette, curved packet routes */
(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.miniGame.wireDefense.v1';
    var STORAGE_SCHEMA = 1;
    var GAME_VERSION = 1;
    var START_LIVES = 3;
    var BASE_CHAIN_RADIUS = 52;
    var MAX_PACKETS = 26;
    var MAX_INTERCEPTORS = 8;
    var MISSILE_RELOAD_MS = 1000;
    var INTERCEPTOR_SPEED = 0.48;
    var INTERCEPTOR_BLAST_RADIUS = 38;
    var PACKET_BLAST_RADIUS = 24;
    var memoryStorage = Object.create(null);
    var storageMode = 'memory';
    var storage = null;

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function plainObject(value) {
        return value && Object.prototype.toString.call(value) === '[object Object]';
    }

    function integerInRange(value, min, max) {
        return Number.isInteger(value) && value >= min && value <= max;
    }

    function recordDefaults() {
        return {schema: STORAGE_SCHEMA, game: 'wire_defense', gameVersion: GAME_VERSION, best: 0, games: 0, maxChain: 0};
    }

    function validateRecord(value) {
        if (!plainObject(value)
            || value.schema !== STORAGE_SCHEMA
            || value.game !== 'wire_defense'
            || value.gameVersion !== GAME_VERSION
            || !integerInRange(value.best, 0, 999999999)
            || !integerInRange(value.games, 0, 999999999)
            || !integerInRange(value.maxChain, 0, 999999)) {
            return null;
        }
        return {
            schema: STORAGE_SCHEMA,
            game: 'wire_defense',
            gameVersion: GAME_VERSION,
            best: value.best,
            games: value.games,
            maxChain: value.maxChain
        };
    }

    function browserStorage(name) {
        try { return window[name] || null; }
        catch (error) { return null; }
    }

    function storageAvailable(candidate) {
        if (!candidate) return false;
        try {
            var key = STORAGE_PREFIX + '.probe';
            candidate.setItem(key, '1');
            candidate.removeItem(key);
            return true;
        } catch (error) {
            return false;
        }
    }

    function selectStorage() {
        var local = browserStorage('localStorage');
        if (storageAvailable(local)) {
            storage = local;
            storageMode = 'localStorage';
            return;
        }
        var session = browserStorage('sessionStorage');
        if (storageAvailable(session)) {
            storage = session;
            storageMode = 'sessionStorage';
            return;
        }
        storage = null;
        storageMode = 'memory';
    }

    function selectFallbackStorage() {
        if (storageMode === 'localStorage') {
            var session = browserStorage('sessionStorage');
            if (storageAvailable(session)) {
                storage = session;
                storageMode = 'sessionStorage';
                return;
            }
        }
        storage = null;
        storageMode = 'memory';
    }

    function dashboardUserId() {
        var main = document.getElementById('main-content');
        return positiveId(main ? main.getAttribute('data-dashboard-user-id') : '');
    }

    function storageKey(userId, widgetId) {
        var safeUserId = positiveId(userId);
        var safeWidgetId = positiveId(widgetId);
        if (safeUserId === null || safeWidgetId === null) return null;
        return STORAGE_PREFIX + '.user.' + safeUserId + '.widget.' + safeWidgetId;
    }

    function removeFromStorage(candidate, key) {
        if (!candidate || key === null) return;
        try { candidate.removeItem(key); } catch (error) {}
    }

    function removeEverywhere(key) {
        removeFromStorage(browserStorage('localStorage'), key);
        removeFromStorage(browserStorage('sessionStorage'), key);
        if (key !== null) delete memoryStorage[key];
    }

    function loadRecord(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) return recordDefaults();
        var candidates = [browserStorage('localStorage'), browserStorage('sessionStorage')];
        for (var i = 0; i < candidates.length; i++) {
            if (!candidates[i]) continue;
            try {
                var raw = candidates[i].getItem(key);
                if (!raw) continue;
                var valid = validateRecord(JSON.parse(raw));
                if (valid) return valid;
                candidates[i].removeItem(key);
            } catch (error) {}
        }
        if (Object.prototype.hasOwnProperty.call(memoryStorage, key)) {
            try {
                var memoryValid = validateRecord(JSON.parse(memoryStorage[key]));
                if (memoryValid) return memoryValid;
            } catch (error) {}
            delete memoryStorage[key];
        }
        return recordDefaults();
    }

    function saveRecord(userId, widgetId, record) {
        var key = storageKey(userId, widgetId);
        var valid = validateRecord(record);
        if (key === null || valid === null) return false;
        var raw = JSON.stringify(valid);
        if (storage !== null) {
            try {
                storage.setItem(key, raw);
                return true;
            } catch (error) {
                selectFallbackStorage();
                if (storage !== null) {
                    try {
                        storage.setItem(key, raw);
                        return true;
                    } catch (fallbackError) {
                        storage = null;
                        storageMode = 'memory';
                    }
                }
            }
        }
        memoryStorage[key] = raw;
        return true;
    }

    function removeWidgetState(widgetId) {
        var key = storageKey(dashboardUserId(), widgetId);
        if (key === null) return false;
        removeEverywhere(key);
        return true;
    }

    function gameDefaultTitle(gameType) {
        if (gameType === 'lights_out') return 'Lights Out';
        if (gameType === 'wire_defense') return 'Wire Defense';
        return 'Icon Quest';
    }

    function gameTitleInputForSelect(select) {
        if (!select || !select.classList) return null;
        var selector = select.classList.contains('changeGameType') ? '.changeGameTitleValue' : '.registerGameTitleValue';
        return document.querySelector(selector);
    }

    function syncDefaultTitleBeforeDashboard(event) {
        var select = event.target;
        if (!select || !select.matches || !select.matches('.registerGameType, .changeGameType')) return;
        var title = gameTitleInputForSelect(select);
        if (!title) return;
        var previousType = String(select.getAttribute('data-previous-game-type') || 'icon_quest');
        var currentTitle = String(title.value || '').trim();
        if (currentTitle === '' || currentTitle === gameDefaultTitle(previousType)) {
            title.value = gameDefaultTitle(String(select.value || 'icon_quest'));
        }
    }

    function randomUnit() {
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            var values = new Uint32Array(1);
            window.crypto.getRandomValues(values);
            return values[0] / 4294967296;
        }
        return Math.random();
    }

    function spawnIntervalForWave(wave) {
        var value = 1120 - Math.max(0, wave - 1) * 70;
        return Math.max(390, value);
    }

    function packetSpeedForWave(wave) {
        return Math.min(0.00023, 0.000115 + Math.max(0, wave - 1) * 0.000009);
    }

    function distanceSquared(left, right) {
        var dx = Number(left.x || 0) - Number(right.x || 0);
        var dy = Number(left.y || 0) - Number(right.y || 0);
        return dx * dx + dy * dy;
    }

    function chainIndexes(packets, seedIndex, radius) {
        if (!Array.isArray(packets) || seedIndex < 0 || seedIndex >= packets.length) return [];
        var limit = Number(radius) > 0 ? Number(radius) : BASE_CHAIN_RADIUS;
        var limitSquared = limit * limit;
        var selected = Object.create(null);
        var queue = [seedIndex];
        var result = [];
        selected[seedIndex] = true;
        while (queue.length > 0) {
            var index = queue.shift();
            var current = packets[index];
            if (!current) continue;
            result.push(index);
            for (var i = 0; i < packets.length; i++) {
                if (selected[i] || !packets[i]) continue;
                if (distanceSquared(current, packets[i]) <= limitSquared) {
                    selected[i] = true;
                    queue.push(i);
                }
            }
        }
        return result;
    }

    function chainScore(count) {
        var safeCount = Math.max(0, Math.floor(Number(count) || 0));
        if (safeCount === 0) return 0;
        return safeCount * 10 + Math.max(0, safeCount - 1) * 8;
    }

    function createRuntime() {
        return {
            status: 'idle',
            score: 0,
            lives: START_LIVES,
            wave: 1,
            maxChain: 0,
            packets: [],
            interceptors: [],
            explosions: [],
            shotChains: Object.create(null),
            reloadElapsed: MISSILE_RELOAD_MS,
            frameId: null,
            lastFrameAt: 0,
            spawnElapsed: 0,
            width: 0,
            height: 0,
            nextPacketId: 1,
            nextShotId: 1,
            hiddenResume: false,
            record: recordDefaults()
        };
    }

    function setText(card, selector, value) {
        var node = card.querySelector(selector);
        if (node) node.textContent = String(value);
    }

    function storageNote() {
        if (storageMode === 'localStorage') return 'Best ScoreとMax Chainをこの端末に保存します。SoundはOFFです。';
        if (storageMode === 'sessionStorage') return 'Best ScoreとMax ChainをこのTab内に保存します。SoundはOFFです。';
        return 'Storageを利用出来ないため記録はこの画面内だけ保持します。SoundはOFFです。';
    }

    function buildBody(card) {
        var body = card.querySelector('.mini-game-card-body');
        if (!body) return null;
        body.innerHTML = '';

        var summary = document.createElement('div');
        summary.className = 'wire-defense-summary';
        summary.setAttribute('aria-label', 'Wire Defense状況');
        summary.innerHTML = '<span>Score <strong class="wire-defense-score">0</strong></span>'
            + '<span>Best <strong class="wire-defense-best">0</strong></span>'
            + '<span>Lives <strong class="wire-defense-lives">3</strong></span>'
            + '<span>Wave <strong class="wire-defense-wave">1</strong></span>'
            + '<span>Max Chain <strong class="wire-defense-chain">0</strong></span>';
        body.appendChild(summary);

        var canvasWrap = document.createElement('div');
        canvasWrap.className = 'wire-defense-canvas-wrap';
        var canvas = document.createElement('canvas');
        canvas.className = 'wire-defense-canvas';
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', '六角形のCoreへ向かう通信Packetを、クリックまたはタップ地点へ迎撃Missileを発射して防衛するNetwork画面');
        canvas.setAttribute('tabindex', '0');
        canvasWrap.appendChild(canvas);
        body.appendChild(canvasWrap);

        var status = document.createElement('p');
        status.className = 'mini-game-status wire-defense-status text-muted';
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');
        status.textContent = 'Startを押すとNetwork Defenseを開始します。';
        body.appendChild(status);

        var controls = document.createElement('div');
        controls.className = 'wire-defense-controls';
        controls.setAttribute('role', 'group');
        controls.setAttribute('aria-label', 'Wire Defense操作');
        controls.innerHTML = '<button type="button" class="btn btn-sm btn-primary wire-defense-start">Start</button>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary wire-defense-pause" disabled>Pause</button>'
            + '<button type="button" class="btn btn-sm btn-outline-danger wire-defense-stop" disabled>Stop</button>';
        body.appendChild(controls);

        var note = document.createElement('p');
        note.className = 'mini-game-storage-note wire-defense-storage-note text-muted';
        note.textContent = storageNote();
        body.appendChild(note);

        var help = document.createElement('p');
        help.className = 'visually-hidden';
        help.textContent = '防衛画面をクリックまたはタップすると、装填ゲージが満タンの時だけCoreからその地点へ迎撃Missileを発射します。再装填には1秒かかります。着弾時の爆発でPacketを遮断し、近いPacketは二次爆発で連鎖します。敵Packetは直線・曲線・蛇行で侵入し、Coreへ3回侵入されるとGame Overです。';
        body.appendChild(help);
        return body;
    }

    function resizeCanvas(card) {
        var canvas = card.querySelector('.wire-defense-canvas');
        var runtime = card.__rssWireDefenseState;
        if (!canvas || !runtime) return null;
        var rect = canvas.getBoundingClientRect();
        var width = Math.max(220, Math.round(Number(rect.width || 0)) || 320);
        var height = Math.max(170, Math.round(Number(rect.height || 0)) || 190);
        if (runtime.width === width && runtime.height === height && canvas.width > 0 && canvas.height > 0) return canvas.getContext('2d');
        var ratio = Math.max(1, Math.min(2, Number(window.devicePixelRatio || 1)));
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        runtime.width = width;
        runtime.height = height;
        var context = canvas.getContext('2d');
        if (context && typeof context.setTransform === 'function') context.setTransform(ratio, 0, 0, ratio, 0, 0);
        return context;
    }

    function sourcePoint(width, height) {
        var edge = Math.floor(randomUnit() * 3);
        if (edge === 0) return {x: 18 + randomUnit() * Math.max(1, width - 36), y: 8};
        if (edge === 1) return {x: 8, y: 18 + randomUnit() * Math.max(1, height * 0.58)};
        return {x: Math.max(8, width - 8), y: 18 + randomUnit() * Math.max(1, height * 0.58)};
    }

    function packetTrajectoryType() {
        var roll = randomUnit();
        if (roll < 0.5) return 'straight';
        if (roll < 0.8) return 'curve';
        return 'wave';
    }

    function spawnPacket(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime || runtime.packets.length >= MAX_PACKETS) return;
        var source = sourcePoint(runtime.width || 320, runtime.height || 190);
        var trajectory = packetTrajectoryType();
        var direction = randomUnit() < 0.5 ? -1 : 1;
        runtime.packets.push({
            id: runtime.nextPacketId++,
            startX: source.x,
            startY: source.y,
            x: source.x,
            y: source.y,
            progress: 0,
            speed: packetSpeedForWave(runtime.wave) * (0.86 + randomUnit() * 0.3),
            radius: 6,
            trajectory: trajectory,
            curveOffset: direction * (24 + randomUnit() * 34),
            waveAmplitude: direction * (12 + randomUnit() * 18),
            waveCycles: randomUnit() < 0.5 ? 1 : 1.5
        });
    }

    function corePoint(runtime) {
        return {x: runtime.width * 0.5, y: runtime.height * 0.68};
    }

    function packetPosition(packet, core, progress) {
        var t = Math.max(0, Math.min(1, Number(progress) || 0));
        var startX = Number(packet.startX || 0);
        var startY = Number(packet.startY || 0);
        var endX = Number(core.x || 0);
        var endY = Number(core.y || 0);
        var dx = endX - startX;
        var dy = endY - startY;
        var length = Math.max(1, Math.sqrt(dx * dx + dy * dy));
        var px = -dy / length;
        var py = dx / length;
        var baseX = startX + dx * t;
        var baseY = startY + dy * t;
        var type = String(packet.trajectory || 'straight');

        if (type === 'curve') {
            var curve = Number(packet.curveOffset || 0) * 4 * t * (1 - t);
            return {x: baseX + px * curve, y: baseY + py * curve};
        }
        if (type === 'wave') {
            var wave = Number(packet.waveAmplitude || 0)
                * Math.sin(Math.PI * 2 * Number(packet.waveCycles || 1) * t)
                * Math.sin(Math.PI * t);
            return {x: baseX + px * wave, y: baseY + py * wave};
        }
        return {x: baseX, y: baseY};
    }

    function updatePacketPositions(runtime, deltaMs) {
        var core = corePoint(runtime);
        var survivors = [];
        var breaches = 0;
        for (var i = 0; i < runtime.packets.length; i++) {
            var packet = runtime.packets[i];
            packet.progress += packet.speed * deltaMs;
            if (packet.progress >= 1) {
                breaches += 1;
                continue;
            }
            var position = packetPosition(packet, core, packet.progress);
            packet.x = position.x;
            packet.y = position.y;
            survivors.push(packet);
        }
        runtime.packets = survivors;
        return breaches;
    }

    function coreLaunchPoint(runtime) {
        var core = corePoint(runtime);
        return {x: core.x, y: core.y - 23};
    }

    function clampTarget(runtime, point) {
        var margin = 10;
        var core = corePoint(runtime);
        return {
            x: Math.max(margin, Math.min(runtime.width - margin, Number(point.x || 0))),
            y: Math.max(margin, Math.min(core.y - 18, Number(point.y || 0)))
        };
    }

    function reloadRatio(runtime) {
        if (!runtime) return 0;
        return Math.max(0, Math.min(1, Number(runtime.reloadElapsed || 0) / MISSILE_RELOAD_MS));
    }

    function missileReady(runtime) {
        return reloadRatio(runtime) >= 1;
    }

    function updateReload(runtime, deltaMs) {
        if (!runtime || runtime.reloadElapsed >= MISSILE_RELOAD_MS) return;
        runtime.reloadElapsed = Math.min(MISSILE_RELOAD_MS, runtime.reloadElapsed + Math.max(0, Number(deltaMs) || 0));
    }

    function launchInterceptor(runtime, point) {
        if (!runtime || runtime.status !== 'playing' || !missileReady(runtime) || runtime.interceptors.length >= MAX_INTERCEPTORS) return null;
        var start = coreLaunchPoint(runtime);
        var target = clampTarget(runtime, point);
        var shotId = runtime.nextShotId++;
        runtime.shotChains[shotId] = 0;
        runtime.reloadElapsed = 0;
        runtime.interceptors.push({
            shotId: shotId,
            x: start.x,
            y: start.y,
            startX: start.x,
            startY: start.y,
            targetX: target.x,
            targetY: target.y
        });
        return shotId;
    }

    function addExplosion(runtime, x, y, shotId, kind) {
        runtime.explosions.push({
            x: x,
            y: y,
            age: 0,
            duration: kind === 'packet' ? 360 : 520,
            maxRadius: kind === 'packet' ? PACKET_BLAST_RADIUS : INTERCEPTOR_BLAST_RADIUS,
            shotId: shotId,
            kind: kind
        });
    }

    function explosionRadius(explosion) {
        if (!explosion || explosion.duration <= 0) return 0;
        var ratio = Math.max(0, Math.min(1, explosion.age / explosion.duration));
        if (ratio < 0.56) return explosion.maxRadius * (ratio / 0.56);
        return explosion.maxRadius * Math.max(0, 1 - ((ratio - 0.56) / 0.44) * 0.28);
    }

    function updateInterceptors(runtime, deltaMs) {
        var keep = [];
        for (var i = 0; i < runtime.interceptors.length; i++) {
            var interceptor = runtime.interceptors[i];
            var dx = interceptor.targetX - interceptor.x;
            var dy = interceptor.targetY - interceptor.y;
            var distance = Math.sqrt(dx * dx + dy * dy);
            var step = INTERCEPTOR_SPEED * deltaMs;
            if (distance <= step || distance <= 0.8) {
                interceptor.x = interceptor.targetX;
                interceptor.y = interceptor.targetY;
                addExplosion(runtime, interceptor.targetX, interceptor.targetY, interceptor.shotId, 'interceptor');
                continue;
            }
            interceptor.x += (dx / distance) * step;
            interceptor.y += (dy / distance) * step;
            keep.push(interceptor);
        }
        runtime.interceptors = keep;
    }

    function updateExplosions(runtime, deltaMs) {
        var keep = [];
        for (var i = 0; i < runtime.explosions.length; i++) {
            runtime.explosions[i].age += deltaMs;
            if (runtime.explosions[i].age < runtime.explosions[i].duration) keep.push(runtime.explosions[i]);
        }
        runtime.explosions = keep;
    }

    function scoreExplosionHit(runtime, shotId) {
        var previous = Number(runtime.shotChains[shotId] || 0);
        var next = previous + 1;
        runtime.shotChains[shotId] = next;
        runtime.score += chainScore(next) - chainScore(previous);
        runtime.maxChain = Math.max(runtime.maxChain, next);
        return next;
    }

    function resolveExplosionHits(runtime) {
        if (!runtime || runtime.packets.length === 0 || runtime.explosions.length === 0) return 0;
        var survivors = [];
        var hits = 0;
        for (var p = 0; p < runtime.packets.length; p++) {
            var packet = runtime.packets[p];
            var hitExplosion = null;
            for (var e = 0; e < runtime.explosions.length; e++) {
                var explosion = runtime.explosions[e];
                var radius = explosionRadius(explosion);
                if (radius <= 0) continue;
                if (distanceSquared(packet, explosion) <= radius * radius) {
                    hitExplosion = explosion;
                    break;
                }
            }
            if (!hitExplosion) {
                survivors.push(packet);
                continue;
            }
            hits += 1;
            scoreExplosionHit(runtime, hitExplosion.shotId);
            addExplosion(runtime, packet.x, packet.y, hitExplosion.shotId, 'packet');
        }
        runtime.packets = survivors;
        return hits;
    }

    function pruneShotChains(runtime) {
        var active = Object.create(null);
        for (var i = 0; i < runtime.interceptors.length; i++) active[runtime.interceptors[i].shotId] = true;
        for (var e = 0; e < runtime.explosions.length; e++) active[runtime.explosions[e].shotId] = true;
        Object.keys(runtime.shotChains).forEach(function (key) {
            if (!active[key]) delete runtime.shotChains[key];
        });
    }

    function corePalette(lives) {
        if (Number(lives) <= 1) {
            return {fill: 'rgba(220,53,69,.12)', stroke: 'rgba(220,53,69,.90)', bright: 'rgba(220,53,69,.96)'};
        }
        if (Number(lives) === 2) {
            return {fill: 'rgba(253,126,20,.12)', stroke: 'rgba(253,126,20,.88)', bright: 'rgba(253,126,20,.96)'};
        }
        return {fill: 'rgba(25,135,84,.10)', stroke: 'rgba(25,135,84,.82)', bright: 'rgba(25,135,84,.94)'};
    }

    function drawReloadGauge(context, runtime, core) {
        var ratio = reloadRatio(runtime);
        var width = 52;
        var height = 5;
        var left = core.x - width / 2;
        var top = core.y + 37;
        context.save();
        context.fillStyle = 'rgba(108,117,125,.18)';
        context.fillRect(left, top, width, height);
        context.strokeStyle = 'rgba(108,117,125,.55)';
        context.lineWidth = 1;
        context.strokeRect(left, top, width, height);
        if (ratio > 0) {
            context.fillStyle = ratio >= 1 ? 'rgba(25,135,84,.92)' : 'rgba(255,193,7,.88)';
            context.fillRect(left + 1, top + 1, Math.max(0, (width - 2) * ratio), Math.max(1, height - 2));
        }
        context.fillStyle = 'rgba(108,117,125,.82)';
        context.font = '8px sans-serif';
        context.textAlign = 'center';
        context.fillText(ratio >= 1 ? 'READY' : 'RELOAD ' + Math.floor(ratio * 100) + '%', core.x, top + 13);
        context.restore();
    }

    function drawHexCore(context, runtime, core) {
        var radius = 22;
        var palette = corePalette(runtime.lives);
        context.save();
        context.beginPath();
        for (var i = 0; i < 6; i++) {
            var angle = -Math.PI / 2 + i * Math.PI / 3;
            var x = core.x + Math.cos(angle) * radius;
            var y = core.y + Math.sin(angle) * radius;
            if (i === 0) context.moveTo(x, y); else context.lineTo(x, y);
        }
        context.closePath();
        context.fillStyle = palette.fill;
        context.fill();
        context.lineWidth = 2;
        context.strokeStyle = palette.stroke;
        context.stroke();

        var rackLeft = core.x - 10;
        var rackRight = core.x + 10;
        var rackTop = core.y - 9;
        context.lineWidth = 1.4;
        context.strokeStyle = palette.bright;
        for (var row = 0; row < 3; row++) {
            var y = rackTop + row * 8;
            context.beginPath();
            context.roundRect ? context.roundRect(rackLeft, y, rackRight - rackLeft, 5, 1.5) : context.rect(rackLeft, y, rackRight - rackLeft, 5);
            context.stroke();
            context.beginPath();
            context.arc(rackLeft + 4, y + 2.5, 1.1, 0, Math.PI * 2);
            context.fillStyle = palette.bright;
            context.fill();
        }
        context.fillStyle = 'rgba(108,117,125,.82)';
        context.font = '9px sans-serif';
        context.textAlign = 'center';
        context.fillText('CORE', core.x, core.y + radius + 11);
        context.restore();
        drawReloadGauge(context, runtime, core);
    }

    function drawInterceptors(context, runtime) {
        for (var i = 0; i < runtime.interceptors.length; i++) {
            var interceptor = runtime.interceptors[i];
            var dx = interceptor.targetX - interceptor.x;
            var dy = interceptor.targetY - interceptor.y;
            var length = Math.max(1, Math.sqrt(dx * dx + dy * dy));
            var ux = dx / length;
            var uy = dy / length;
            context.beginPath();
            context.arc(interceptor.targetX, interceptor.targetY, 5, 0, Math.PI * 2);
            context.strokeStyle = 'rgba(25,135,84,.35)';
            context.lineWidth = 1;
            context.stroke();
            context.beginPath();
            context.moveTo(interceptor.x - ux * 10, interceptor.y - uy * 10);
            context.lineTo(interceptor.x, interceptor.y);
            context.strokeStyle = 'rgba(25,135,84,.72)';
            context.lineWidth = 2;
            context.stroke();
            context.beginPath();
            context.arc(interceptor.x, interceptor.y, 2.8, 0, Math.PI * 2);
            context.fillStyle = 'rgba(255,193,7,.96)';
            context.fill();
        }
    }

    function drawNetwork(context, runtime) {
        var width = runtime.width;
        var height = runtime.height;
        var core = corePoint(runtime);
        context.clearRect(0, 0, width, height);
        context.save();
        context.lineWidth = 1;
        context.strokeStyle = 'rgba(108,117,125,.22)';
        var nodes = [
            {x: width * .12, y: height * .14}, {x: width * .35, y: height * .08}, {x: width * .65, y: height * .09},
            {x: width * .88, y: height * .15}, {x: width * .18, y: height * .48}, {x: width * .82, y: height * .48}
        ];
        for (var i = 0; i < nodes.length; i++) {
            context.beginPath();
            context.moveTo(nodes[i].x, nodes[i].y);
            context.lineTo(core.x, core.y);
            context.stroke();
            context.beginPath();
            context.arc(nodes[i].x, nodes[i].y, 3, 0, Math.PI * 2);
            context.fillStyle = 'rgba(108,117,125,.48)';
            context.fill();
        }

        drawHexCore(context, runtime, core);

        for (var p = 0; p < runtime.packets.length; p++) {
            var packet = runtime.packets[p];
            context.beginPath();
            var trailSteps = Math.max(2, Math.ceil(12 * packet.progress));
            for (var trail = 0; trail <= trailSteps; trail++) {
                var trailProgress = packet.progress * (trail / trailSteps);
                var trailPoint = packetPosition(packet, core, trailProgress);
                if (trail === 0) context.moveTo(trailPoint.x, trailPoint.y); else context.lineTo(trailPoint.x, trailPoint.y);
            }
            context.strokeStyle = 'rgba(13,110,253,.18)';
            context.lineWidth = 1;
            context.stroke();
            context.beginPath();
            context.arc(packet.x, packet.y, packet.radius + 4, 0, Math.PI * 2);
            context.fillStyle = 'rgba(13,110,253,.12)';
            context.fill();
            context.beginPath();
            context.arc(packet.x, packet.y, packet.radius, 0, Math.PI * 2);
            context.fillStyle = 'rgba(13,110,253,.9)';
            context.fill();
        }

        drawInterceptors(context, runtime);

        for (var e = 0; e < runtime.explosions.length; e++) {
            var explosion = runtime.explosions[e];
            var ratio = Math.max(0, Math.min(1, explosion.age / explosion.duration));
            var radius = explosionRadius(explosion);
            context.beginPath();
            context.arc(explosion.x, explosion.y, radius, 0, Math.PI * 2);
            context.strokeStyle = 'rgba(255,193,7,' + Math.max(0, .9 - ratio * .72) + ')';
            context.lineWidth = explosion.kind === 'interceptor' ? 2.4 : 1.6;
            context.stroke();
            if (explosion.kind === 'interceptor' && ratio < .45) {
                context.beginPath();
                context.arc(explosion.x, explosion.y, Math.max(2, radius * .38), 0, Math.PI * 2);
                context.strokeStyle = 'rgba(255,193,7,' + Math.max(0, .55 - ratio) + ')';
                context.lineWidth = 1;
                context.stroke();
            }
        }
        context.restore();
    }

    function render(card, message) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime) return;
        var recordBest = runtime.record ? runtime.record.best : 0;
        setText(card, '.wire-defense-score', runtime.score);
        setText(card, '.wire-defense-best', Math.max(recordBest, runtime.score));
        setText(card, '.wire-defense-lives', runtime.lives);
        setText(card, '.wire-defense-wave', runtime.wave);
        setText(card, '.wire-defense-chain', Math.max(runtime.maxChain, runtime.record ? runtime.record.maxChain : 0));
        var status = card.querySelector('.wire-defense-status');
        if (status && message) status.textContent = message;
        var start = card.querySelector('.wire-defense-start');
        var pause = card.querySelector('.wire-defense-pause');
        var stop = card.querySelector('.wire-defense-stop');
        if (start) {
            start.disabled = runtime.status === 'playing' || runtime.status === 'paused';
            start.textContent = runtime.status === 'gameover' || runtime.status === 'stopped' ? 'New Game' : 'Start';
        }
        if (pause) {
            pause.disabled = runtime.status !== 'playing' && runtime.status !== 'paused';
            pause.textContent = runtime.status === 'paused' ? 'Resume' : 'Pause';
        }
        if (stop) stop.disabled = runtime.status !== 'playing' && runtime.status !== 'paused';
        card.setAttribute('data-wire-defense-status', runtime.status);
        var context = resizeCanvas(card);
        if (context) drawNetwork(context, runtime);
    }

    function persistRecord(card, finishedGame) {
        var runtime = card.__rssWireDefenseState;
        var widgetId = card.getAttribute('data-dashboard-widget-id');
        var userId = dashboardUserId();
        if (!runtime || userId === null || positiveId(widgetId) === null) return;
        if (runtime.score > runtime.record.best) runtime.record.best = runtime.score;
        if (runtime.maxChain > runtime.record.maxChain) runtime.record.maxChain = runtime.maxChain;
        if (finishedGame === true) runtime.record.games += 1;
        saveRecord(userId, widgetId, runtime.record);
    }

    function stopLoop(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime || runtime.frameId === null) return;
        if (typeof window.cancelAnimationFrame === 'function') window.cancelAnimationFrame(runtime.frameId);
        runtime.frameId = null;
    }

    function gameOver(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime) return;
        runtime.status = 'gameover';
        runtime.hiddenResume = false;
        stopLoop(card);
        persistRecord(card, true);
        render(card, 'Game Over。Coreへの侵入を3回許しました。New Gameで再挑戦出来ます。');
    }

    function frame(card, now) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime || runtime.status !== 'playing' || document.hidden || !card.isConnected) {
            stopLoop(card);
            return;
        }
        var context = resizeCanvas(card);
        if (!context) {
            stopLoop(card);
            return;
        }
        var delta = runtime.lastFrameAt > 0 ? Math.min(50, Math.max(0, now - runtime.lastFrameAt)) : 16;
        runtime.lastFrameAt = now;
        runtime.spawnElapsed += delta;
        updateReload(runtime, delta);
        var interval = spawnIntervalForWave(runtime.wave);
        while (runtime.spawnElapsed >= interval) {
            runtime.spawnElapsed -= interval;
            spawnPacket(card);
        }
        var breaches = updatePacketPositions(runtime, delta);
        if (breaches > 0) {
            runtime.lives = Math.max(0, runtime.lives - breaches);
            if (runtime.lives <= 0) {
                gameOver(card);
                return;
            }
        }
        updateInterceptors(runtime, delta);
        updateExplosions(runtime, delta);
        var explosionHits = resolveExplosionHits(runtime);
        if (explosionHits > 0) {
            runtime.wave = 1 + Math.floor(runtime.score / 250);
            persistRecord(card, false);
            render(card, explosionHits > 1
                ? '迎撃成功。Packetを' + explosionHits + '件遮断しました。爆発が連鎖しています。'
                : '迎撃成功。Packetを遮断しました。');
        }
        pruneShotChains(runtime);
        runtime.wave = 1 + Math.floor(runtime.score / 250);
        drawNetwork(context, runtime);
        if (typeof window.requestAnimationFrame === 'function') runtime.frameId = window.requestAnimationFrame(function (stamp) { frame(card, stamp); });
    }

    function startLoop(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime || runtime.status !== 'playing' || document.hidden || runtime.frameId !== null || !card.isConnected) return;
        runtime.lastFrameAt = 0;
        if (typeof window.requestAnimationFrame === 'function') runtime.frameId = window.requestAnimationFrame(function (stamp) { frame(card, stamp); });
    }

    function resetRun(runtime) {
        runtime.score = 0;
        runtime.lives = START_LIVES;
        runtime.wave = 1;
        runtime.maxChain = 0;
        runtime.packets = [];
        runtime.interceptors = [];
        runtime.explosions = [];
        runtime.shotChains = Object.create(null);
        runtime.reloadElapsed = MISSILE_RELOAD_MS;
        runtime.spawnElapsed = 0;
        runtime.lastFrameAt = 0;
        runtime.nextPacketId = 1;
        runtime.nextShotId = 1;
        runtime.hiddenResume = false;
    }

    function startGame(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime || runtime.status === 'playing' || runtime.status === 'paused') return;
        stopLoop(card);
        resetRun(runtime);
        runtime.status = 'playing';
        render(card, '装填ゲージがREADYの時にクリックまたはタップすると迎撃Missileを発射します。再装填は1秒です。');
        spawnPacket(card);
        startLoop(card);
    }

    function togglePause(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime) return;
        if (runtime.status === 'playing') {
            runtime.status = 'paused';
            runtime.hiddenResume = false;
            stopLoop(card);
            render(card, 'Pause中です。Resumeで再開します。');
            return;
        }
        if (runtime.status === 'paused') {
            runtime.status = 'playing';
            render(card, '再開しました。');
            startLoop(card);
        }
    }

    function stopGame(card) {
        var runtime = card.__rssWireDefenseState;
        if (!runtime || (runtime.status !== 'playing' && runtime.status !== 'paused')) return;
        stopLoop(card);
        runtime.status = 'stopped';
        runtime.hiddenResume = false;
        persistRecord(card, false);
        render(card, '停止しました。Scoreは記録済みです。New Gameで最初から開始出来ます。');
    }

    function pointerPosition(canvas, event) {
        var rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return null;
        return {
            x: (Number(event.clientX) - rect.left) * ((canvas.__wireCssWidth || rect.width) / rect.width),
            y: (Number(event.clientY) - rect.top) * ((canvas.__wireCssHeight || rect.height) / rect.height)
        };
    }

    function handlePointer(card, event) {
        var runtime = card.__rssWireDefenseState;
        var canvas = card.querySelector('.wire-defense-canvas');
        if (!runtime || runtime.status !== 'playing' || !canvas) return;
        var rect = canvas.getBoundingClientRect();
        canvas.__wireCssWidth = runtime.width || rect.width;
        canvas.__wireCssHeight = runtime.height || rect.height;
        var point = pointerPosition(canvas, event);
        if (!point) return;
        event.preventDefault();
        var status = card.querySelector('.wire-defense-status');
        if (!missileReady(runtime)) {
            if (status) status.textContent = 'Missileを装填中です。ゲージがREADYになるまで待ってください。';
            return;
        }
        var shotId = launchInterceptor(runtime, point);
        if (shotId === null) {
            if (status) status.textContent = '迎撃Missileを発射出来ません。着弾を待ってください。';
            return;
        }
        if (status) status.textContent = '迎撃Missileを発射しました。再装填には1秒かかります。';
    }

    function initCard(card) {
        if (!card || card.getAttribute('data-wire-defense-initialized') === '1') return;
        card.setAttribute('data-wire-defense-initialized', '1');
        var runtime = createRuntime();
        runtime.record = loadRecord(dashboardUserId(), card.getAttribute('data-dashboard-widget-id'));
        card.__rssWireDefenseState = runtime;
        buildBody(card);
        render(card, 'Startを押すとNetwork Defenseを開始します。');

        var canvas = card.querySelector('.wire-defense-canvas');
        var start = card.querySelector('.wire-defense-start');
        var pause = card.querySelector('.wire-defense-pause');
        var stop = card.querySelector('.wire-defense-stop');
        if (canvas) canvas.addEventListener('pointerdown', function (event) { handlePointer(card, event); }, {passive: false});
        if (start) start.addEventListener('click', function () { startGame(card); });
        if (pause) pause.addEventListener('click', function () { togglePause(card); });
        if (stop) stop.addEventListener('click', function () { stopGame(card); });

        card.__rssWireDefenseVisibility = function () {
            if (document.hidden) {
                if (runtime.status === 'playing') {
                    runtime.hiddenResume = true;
                    stopLoop(card);
                    render(card, 'Tabが非表示のため描画を停止しています。');
                }
                return;
            }
            if (runtime.hiddenResume && runtime.status === 'playing') {
                runtime.hiddenResume = false;
                render(card, '表示に戻ったため再開しました。');
                startLoop(card);
            }
        };
        card.__rssWireDefensePageHide = function () {
            runtime.hiddenResume = false;
            stopLoop(card);
        };
        document.addEventListener('visibilitychange', card.__rssWireDefenseVisibility);
        window.addEventListener('pagehide', card.__rssWireDefensePageHide);
    }

    function parseAjaxData(data) {
        var result = Object.create(null);
        if (typeof data !== 'string') return result;
        var pairs = data.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var parts = pairs[i].split('=');
            var key = decodeURIComponent(String(parts.shift() || '').replace(/\+/g, ' '));
            var value = decodeURIComponent(String(parts.join('=') || '').replace(/\+/g, ' '));
            if (key !== '') result[key] = value;
        }
        return result;
    }

    function bindAjaxCleanup() {
        var jq = window.jQuery;
        if (!jq || !jq.fn || !document) return;
        jq(document).off('ajaxSuccess.rssWireDefense').on('ajaxSuccess.rssWireDefense', function (event, xhr, settings, response) {
            if (!response || response.ok !== true || !settings || String(settings.url || '').indexOf('api_v1.php') === -1) return;
            var payload = parseAjaxData(settings.data);
            if (payload.action === 'widget.game.delete' && positiveId(payload.widget_id) !== null) {
                removeWidgetState(payload.widget_id);
                return;
            }
            if (payload.action === 'widget.game.update' && positiveId(payload.widget_id) !== null && payload.game_type !== 'wire_defense') {
                removeWidgetState(payload.widget_id);
            }
        });
    }

    function init() {
        selectStorage();
        document.addEventListener('change', syncDefaultTitleBeforeDashboard, true);
        var cards = document.querySelectorAll('[data-dashboard-widget-type="game"][data-mini-game-type="wire_defense"]');
        for (var i = 0; i < cards.length; i++) initCard(cards[i]);
        bindAjaxCleanup();
    }

    window.RssWireDefense = {
        START_LIVES: START_LIVES,
        BASE_CHAIN_RADIUS: BASE_CHAIN_RADIUS,
        MAX_INTERCEPTORS: MAX_INTERCEPTORS,
        MISSILE_RELOAD_MS: MISSILE_RELOAD_MS,
        INTERCEPTOR_BLAST_RADIUS: INTERCEPTOR_BLAST_RADIUS,
        PACKET_BLAST_RADIUS: PACKET_BLAST_RADIUS,
        explosionRadius: explosionRadius,
        reloadRatio: reloadRatio,
        missileReady: missileReady,
        updateReload: updateReload,
        packetPosition: packetPosition,
        corePalette: corePalette,
        launchInterceptor: launchInterceptor,
        storageKey: storageKey,
        validateRecord: validateRecord,
        loadRecord: loadRecord,
        saveRecord: saveRecord,
        removeWidgetState: removeWidgetState,
        spawnIntervalForWave: spawnIntervalForWave,
        packetSpeedForWave: packetSpeedForWave,
        chainIndexes: chainIndexes,
        chainScore: chainScore,
        gameDefaultTitle: gameDefaultTitle,
        storageMode: function () { return storageMode; },
        init: init
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);
