(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.miniGame.lightsOut.v1';
    var STORAGE_SCHEMA = 1;
    var GAME_VERSION = 1;
    var SIZE = 5;
    var CELL_COUNT = SIZE * SIZE;
    var MAX_MOVES = 999999;
    var memoryStorage = Object.create(null);
    var storageMode = 'memory';
    var storage = null;

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

    function cloneBoard(board) {
        return board.slice(0, CELL_COUNT);
    }

    function emptyBoard() {
        return Array(CELL_COUNT).fill(false);
    }

    function validBoard(board) {
        return Array.isArray(board) && board.length === CELL_COUNT && board.every(function (value) {
            return value === true || value === false;
        });
    }

    function toggleIndexes(index) {
        var row = Math.floor(index / SIZE);
        var column = index % SIZE;
        var indexes = [index];
        if (row > 0) indexes.push(index - SIZE);
        if (row < SIZE - 1) indexes.push(index + SIZE);
        if (column > 0) indexes.push(index - 1);
        if (column < SIZE - 1) indexes.push(index + 1);
        return indexes;
    }

    function applyPress(board, index) {
        var next = cloneBoard(board);
        var indexes = toggleIndexes(index);
        for (var i = 0; i < indexes.length; i++) next[indexes[i]] = !next[indexes[i]];
        return next;
    }

    function isClear(board) {
        return validBoard(board) && board.every(function (value) { return value === false; });
    }

    function randomIndex() {
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            var values = new Uint32Array(1);
            window.crypto.getRandomValues(values);
            return values[0] % CELL_COUNT;
        }
        return Math.floor(Math.random() * CELL_COUNT);
    }

    function generatePuzzle() {
        var board = emptyBoard();
        var attempts = 0;
        while (attempts < 20) {
            board = emptyBoard();
            var pressCount = 10 + (randomIndex() % 11);
            for (var i = 0; i < pressCount; i++) board = applyPress(board, randomIndex());
            if (!isClear(board)) return board;
            attempts += 1;
        }
        return applyPress(emptyBoard(), 12);
    }

    function createState(board) {
        return {
            schema: STORAGE_SCHEMA,
            game: 'lights_out',
            gameVersion: GAME_VERSION,
            size: SIZE,
            board: cloneBoard(board),
            initialBoard: cloneBoard(board),
            moves: 0,
            status: 'playing',
            savedAt: 0
        };
    }

    function cloneState(value) {
        return {
            schema: value.schema,
            game: value.game,
            gameVersion: value.gameVersion,
            size: value.size,
            board: cloneBoard(value.board),
            initialBoard: cloneBoard(value.initialBoard),
            moves: value.moves,
            status: value.status,
            savedAt: value.savedAt
        };
    }

    function validateState(value) {
        if (!plainObject(value) || value.schema !== STORAGE_SCHEMA || value.game !== 'lights_out'
            || value.gameVersion !== GAME_VERSION || value.size !== SIZE || !validBoard(value.board)
            || !validBoard(value.initialBoard) || isClear(value.initialBoard)
            || !integerInRange(value.moves, 0, MAX_MOVES)
            || ['playing', 'cleared'].indexOf(value.status) === -1
            || !integerInRange(value.savedAt, 0, Number.MAX_SAFE_INTEGER)) return null;
        if (value.status === 'playing' && isClear(value.board)) return null;
        if (value.status === 'cleared' && (!isClear(value.board) || value.moves < 1)) return null;
        return cloneState(value);
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

    function browserStorage(name) {
        try { return window[name] || null; }
        catch (error) { return null; }
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

    function storageKey(userId, widgetId) {
        var safeUserId = positiveId(userId);
        var safeWidgetId = positiveId(widgetId);
        return safeUserId === null || safeWidgetId === null
            ? null
            : STORAGE_PREFIX + '.user.' + safeUserId + '.widget.' + safeWidgetId;
    }

    function writeRaw(key, value) {
        if (storage !== null) {
            try {
                storage.setItem(key, value);
                return true;
            } catch (error) {
                selectFallbackStorage();
                if (storage !== null) {
                    try {
                        storage.setItem(key, value);
                        return true;
                    } catch (fallbackError) {
                        storage = null;
                        storageMode = 'memory';
                    }
                }
            }
        }
        memoryStorage[key] = value;
        return true;
    }

    function browserStorageRaw(name, key) {
        var candidate = browserStorage(name);
        if (candidate === null) return {name: name, raw: null, available: false};
        try { return {name: name, raw: candidate.getItem(key), available: true}; }
        catch (error) { return {name: name, raw: null, available: false}; }
    }

    function removeFromBrowserStorage(name, key) {
        var candidate = browserStorage(name);
        if (candidate === null) return;
        try { candidate.removeItem(key); } catch (error) {}
    }

    function removeStorageCopy(name, key) {
        if (name === 'memory') {
            delete memoryStorage[key];
            return;
        }
        removeFromBrowserStorage(name, key);
    }

    function removeEverywhere(key) {
        removeFromBrowserStorage('localStorage', key);
        removeFromBrowserStorage('sessionStorage', key);
        delete memoryStorage[key];
    }

    function loadStateResult(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) return {state: createState(generatePuzzle()), recovered: false, reason: 'invalid-key'};
        var candidates = [browserStorageRaw('localStorage', key), browserStorageRaw('sessionStorage', key)];
        if (Object.prototype.hasOwnProperty.call(memoryStorage, key)) {
            candidates.push({name: 'memory', raw: memoryStorage[key], available: true});
        }
        var valid = [];
        var invalid = [];
        var hasValue = false;
        for (var index = 0; index < candidates.length; index++) {
            var candidate = candidates[index];
            if (!candidate.available || candidate.raw === null || candidate.raw === '') continue;
            hasValue = true;
            try {
                var normalized = validateState(JSON.parse(candidate.raw));
                if (normalized === null) invalid.push(candidate.name);
                else valid.push({name: candidate.name, state: normalized});
            } catch (error) {
                invalid.push(candidate.name);
            }
        }
        for (var invalidIndex = 0; invalidIndex < invalid.length; invalidIndex++) {
            removeStorageCopy(invalid[invalidIndex], key);
        }
        if (valid.length > 0) {
            valid.sort(function (left, right) {
                if (right.state.savedAt !== left.state.savedAt) return right.state.savedAt - left.state.savedAt;
                return left.name === 'localStorage' ? -1 : right.name === 'localStorage' ? 1 : 0;
            });
            return {
                state: valid[0].state,
                recovered: invalid.length > 0,
                reason: invalid.length > 0 ? 'repaired-copy' : 'restored'
            };
        }
        return {
            state: createState(generatePuzzle()),
            recovered: hasValue,
            reason: hasValue ? 'invalid-data' : 'empty'
        };
    }

    function loadState(userId, widgetId) {
        return loadStateResult(userId, widgetId).state;
    }

    function saveState(userId, widgetId, state) {
        var key = storageKey(userId, widgetId);
        if (key === null) return false;
        var candidate = cloneState(state);
        candidate.savedAt = Date.now();
        var normalized = validateState(candidate);
        if (normalized === null) return false;
        try {
            var written = writeRaw(key, JSON.stringify(normalized));
            if (written) state.savedAt = normalized.savedAt;
            return written;
        } catch (error) {
            return false;
        }
    }

    function cardUserId() {
        var main = document.getElementById('main-content');
        return main ? main.getAttribute('data-dashboard-user-id') : '';
    }

    function removeWidgetState(widgetId) {
        var key = storageKey(cardUserId(), widgetId);
        if (key === null) return false;
        removeEverywhere(key);
        return true;
    }

    function storageNote(recovered) {
        if (recovered) return '保存データを確認し、安全な新しい問題へ復旧しました。';
        if (storageMode === 'localStorage') return 'この端末に盤面と手数を保存しています。';
        if (storageMode === 'sessionStorage') return 'このTab内に盤面と手数を一時保存しています。';
        return 'Storageを利用できないため、この画面を開いている間だけ状態を保持します。';
    }

    function setText(card, selector, value) {
        var node = card.querySelector(selector);
        if (node) node.textContent = String(value);
    }

    function render(card, recovered) {
        var state = card.__rssLightsOutState;
        var cells = card.querySelectorAll('[data-lights-out-cell-index]');
        for (var i = 0; i < cells.length; i++) {
            var index = Number(cells[i].getAttribute('data-lights-out-cell-index'));
            var on = state.board[index] === true;
            var row = Math.floor(index / SIZE) + 1;
            var column = (index % SIZE) + 1;
            cells[i].classList.toggle('lights-out-cell-on', on);
            cells[i].setAttribute('aria-pressed', on ? 'true' : 'false');
            cells[i].setAttribute('aria-label', row + '行' + column + '列、' + (on ? '点灯' : '消灯'));
            cells[i].disabled = state.status === 'cleared';
        }
        setText(card, '.lights-out-moves', state.moves);
        setText(card, '.lights-out-status', state.status === 'cleared'
            ? 'Clear！ ' + state.moves + '手ですべて消灯しました'
            : recovered ? '保存データを復旧し、新しい問題を開始しました' : '点灯しているマスをすべて消してください');
        setText(card, '.mini-game-storage-note', storageNote(recovered === true));
        var result = card.querySelector('.lights-out-result');
        if (result) {
            result.hidden = state.status !== 'cleared';
            result.setAttribute('aria-hidden', state.status === 'cleared' ? 'false' : 'true');
            result.classList.toggle('mini-game-result-won', state.status === 'cleared');
        }
        card.setAttribute('data-lights-out-status', state.status);
        card.setAttribute('data-lights-out-storage-mode', storageMode);
    }

    function persistAndRender(card, state, recovered) {
        saveState(cardUserId(), card.getAttribute('data-dashboard-widget-id'), state);
        card.__rssLightsOutState = state;
        render(card, recovered);
    }

    function press(card, index) {
        var state = card.__rssLightsOutState;
        if (!state || state.status !== 'playing' || index < 0 || index >= CELL_COUNT) return;
        var next = cloneState(state);
        next.board = applyPress(next.board, index);
        next.moves += 1;
        if (isClear(next.board)) next.status = 'cleared';
        persistAndRender(card, next, false);
        if (next.status === 'cleared') {
            var resetButton = card.querySelector('.lights-out-reset');
            if (resetButton && typeof resetButton.focus === 'function') resetButton.focus();
        }
    }

    function reset(card) {
        var state = card.__rssLightsOutState;
        if (!state) return;
        persistAndRender(card, createState(state.initialBoard), false);
    }

    function newPuzzle(card) {
        persistAndRender(card, createState(generatePuzzle()), false);
    }

    function focusCell(card, index) {
        var cells = card.querySelectorAll('[data-lights-out-cell-index]');
        if (index < 0 || index >= cells.length) return;
        for (var i = 0; i < cells.length; i++) cells[i].setAttribute('tabindex', i === index ? '0' : '-1');
        if (typeof cells[index].focus === 'function') cells[index].focus();
    }

    function keyboardTarget(index, key) {
        var row = Math.floor(index / SIZE);
        var column = index % SIZE;
        if (key === 'ArrowUp') return row > 0 ? index - SIZE : index;
        if (key === 'ArrowDown') return row < SIZE - 1 ? index + SIZE : index;
        if (key === 'ArrowLeft') return column > 0 ? index - 1 : index;
        if (key === 'ArrowRight') return column < SIZE - 1 ? index + 1 : index;
        if (key === 'Home') return row * SIZE;
        if (key === 'End') return row * SIZE + SIZE - 1;
        return null;
    }

    function initCard(card) {
        if (!card || card.getAttribute('data-lights-out-initialized') === '1') return;
        card.setAttribute('data-lights-out-initialized', '1');
        var widgetId = card.getAttribute('data-dashboard-widget-id');
        var loaded = loadStateResult(cardUserId(), widgetId);
        card.__rssLightsOutState = loaded.state;
        persistAndRender(card, loaded.state, loaded.recovered);
        var firstCell = card.querySelector('[data-lights-out-cell-index="0"]');
        if (firstCell) firstCell.setAttribute('tabindex', '0');

        card.addEventListener('click', function (event) {
            var target = event.target;
            var cell = target && typeof target.closest === 'function' ? target.closest('[data-lights-out-cell-index]') : null;
            if (cell && card.contains(cell)) {
                press(card, Number(cell.getAttribute('data-lights-out-cell-index')));
                return;
            }
            var resetButton = target && typeof target.closest === 'function' ? target.closest('.lights-out-reset') : null;
            if (resetButton && card.contains(resetButton)) {
                reset(card);
                return;
            }
            var newButton = target && typeof target.closest === 'function' ? target.closest('.lights-out-new-game') : null;
            if (newButton && card.contains(newButton)) newPuzzle(card);
        });

        var board = card.querySelector('.lights-out-board');
        if (board) board.addEventListener('keydown', function (event) {
            var cell = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-lights-out-cell-index]') : null;
            if (!cell || !card.contains(cell)) return;
            var current = Number(cell.getAttribute('data-lights-out-cell-index'));
            var next = keyboardTarget(current, event.key);
            if (next === null) return;
            event.preventDefault();
            if (!event.repeat) focusCell(card, next);
        });
    }

    function init() {
        selectStorage();
        var cards = document.querySelectorAll('[data-dashboard-widget-type="game"][data-mini-game-type="lights_out"]');
        for (var i = 0; i < cards.length; i++) initCard(cards[i]);
    }

    window.RssLightsOut = {
        size: SIZE,
        emptyBoard: emptyBoard,
        toggleIndexes: toggleIndexes,
        applyPress: applyPress,
        isClear: isClear,
        generatePuzzle: generatePuzzle,
        createState: createState,
        validateState: validateState,
        storageKey: storageKey,
        loadState: loadState,
        loadStateResult: loadStateResult,
        saveState: saveState,
        removeWidgetState: removeWidgetState,
        keyboardTarget: keyboardTarget,
        storageMode: function () { return storageMode; },
        init: init
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);
