/* V1.37-B: Reversi Game Widget. Player (Black) vs local CPU (White), no network request. */
(function (window, document) {
    'use strict';

    var SIZE = 8;
    var CELL_COUNT = SIZE * SIZE;
    var EMPTY = 0;
    var BLACK = 1;
    var WHITE = 2;
    var CPU_DELAY_MS = 280;
    var states = [];
    var observer = null;

    var DIRECTIONS = [
        [-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]
    ];
    var POSITION_WEIGHTS = [
        120,-25,20,8,8,20,-25,120,
        -25,-45,-5,-5,-5,-5,-45,-25,
        20,-5,15,3,3,15,-5,20,
        8,-5,3,3,3,3,-5,8,
        8,-5,3,3,3,3,-5,8,
        20,-5,15,3,3,15,-5,20,
        -25,-45,-5,-5,-5,-5,-45,-25,
        120,-25,20,8,8,20,-25,120
    ];
    var DEFAULT_TITLES = {
        icon_quest: 'Icon Quest',
        lights_out: 'Lights Out',
        wire_defense: 'Wire Defense',
        block_collapse: 'Block Collapse',
        cursor_field: 'Cursor Field',
        game_2048: '2048',
        reversi: 'Reversi'
    };

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }
    function index(row, column) { return row * SIZE + column; }
    function inBounds(row, column) { return row >= 0 && row < SIZE && column >= 0 && column < SIZE; }
    function other(player) { return player === BLACK ? WHITE : BLACK; }
    function cloneBoard(board) { return Array.isArray(board) ? board.slice(0, CELL_COUNT) : []; }
    function validBoard(board) {
        if (!Array.isArray(board) || board.length !== CELL_COUNT) return false;
        for (var i = 0; i < CELL_COUNT; i++) {
            if (board[i] !== EMPTY && board[i] !== BLACK && board[i] !== WHITE) return false;
        }
        return true;
    }
    function initialBoard() {
        var board = new Array(CELL_COUNT).fill(EMPTY);
        board[index(3,3)] = WHITE;
        board[index(3,4)] = BLACK;
        board[index(4,3)] = BLACK;
        board[index(4,4)] = WHITE;
        return board;
    }
    function flipsForMove(board, moveIndex, player) {
        if (!validBoard(board) || (player !== BLACK && player !== WHITE) || moveIndex < 0 || moveIndex >= CELL_COUNT || board[moveIndex] !== EMPTY) return [];
        var row = Math.floor(moveIndex / SIZE), column = moveIndex % SIZE, opponent = other(player), flips = [];
        for (var d = 0; d < DIRECTIONS.length; d++) {
            var dr = DIRECTIONS[d][0], dc = DIRECTIONS[d][1];
            var r = row + dr, c = column + dc, line = [];
            while (inBounds(r, c) && board[index(r,c)] === opponent) {
                line.push(index(r,c));
                r += dr; c += dc;
            }
            if (line.length > 0 && inBounds(r,c) && board[index(r,c)] === player) flips = flips.concat(line);
        }
        return flips;
    }
    function legalMoves(board, player) {
        if (!validBoard(board) || (player !== BLACK && player !== WHITE)) return [];
        var moves = [];
        for (var i = 0; i < CELL_COUNT; i++) if (board[i] === EMPTY && flipsForMove(board, i, player).length > 0) moves.push(i);
        return moves;
    }
    function applyMove(board, moveIndex, player) {
        if (!validBoard(board)) return {board:new Array(CELL_COUNT).fill(EMPTY), flips:[], changed:false};
        var flips = flipsForMove(board, moveIndex, player);
        if (flips.length === 0) return {board:cloneBoard(board), flips:[], changed:false};
        var next = cloneBoard(board);
        next[moveIndex] = player;
        for (var i = 0; i < flips.length; i++) next[flips[i]] = player;
        return {board:next, flips:flips, changed:true};
    }
    function countDiscs(board) {
        var black = 0, white = 0, empty = 0;
        for (var i = 0; i < board.length; i++) {
            if (board[i] === BLACK) black++;
            else if (board[i] === WHITE) white++;
            else empty++;
        }
        return {black:black, white:white, empty:empty};
    }
    function evaluateCpuMove(board, moveIndex) {
        var result = applyMove(board, moveIndex, WHITE);
        if (!result.changed) return -Infinity;
        var counts = countDiscs(result.board);
        var opponentMobility = legalMoves(result.board, BLACK).length;
        return POSITION_WEIGHTS[moveIndex]
            + result.flips.length * 4
            - opponentMobility * 3
            + (counts.white - counts.black);
    }
    function chooseCpuMove(board) {
        var moves = legalMoves(board, WHITE);
        if (moves.length === 0) return null;
        var bestMove = moves[0], bestScore = evaluateCpuMove(board, bestMove);
        for (var i = 1; i < moves.length; i++) {
            var score = evaluateCpuMove(board, moves[i]);
            if (score > bestScore) { bestScore = score; bestMove = moves[i]; }
        }
        return bestMove;
    }

    function reserveCards() {
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="reversi"]');
        for (var i = 0; i < cards.length; i++) cards[i].setAttribute('data-mini-game-initialized', '1');
    }
    function addGameOption(select) {
        if (!select || select.querySelector('option[value="reversi"]')) return;
        var option = document.createElement('option');
        option.value = 'reversi';
        option.textContent = 'Reversi（Player vs CPU）';
        select.appendChild(option);
    }
    function ensureGameOptions() {
        var register = document.getElementById('registerGameType');
        var change = document.getElementById('changeGameType');
        addGameOption(register);
        addGameOption(change);
        if (register && !register.getAttribute('data-reversi-previous-type')) register.setAttribute('data-reversi-previous-type', String(register.value || 'icon_quest'));
        if (change && !change.getAttribute('data-reversi-previous-type')) change.setAttribute('data-reversi-previous-type', String(change.value || 'icon_quest'));
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
        if (!catalog || catalog.querySelector('[data-game-preset="reversi"]')) return;
        var template = catalog.querySelector('[data-game-preset="game_2048"]')
            || catalog.querySelector('[data-game-preset="cursor_field"]')
            || catalog.querySelector('[data-game-preset="block_collapse"]')
            || catalog.querySelector('[data-game-preset="wire_defense"]')
            || catalog.querySelector('[data-game-preset="lights_out"]');
        if (!template) return;
        var button = template.cloneNode(true);
        button.setAttribute('data-game-preset', 'reversi');
        replaceVisibleText(button, ['2048', 'Cursor Field', 'Block Collapse', 'Wire Defense', 'Lights Out'], 'Reversi');
        if (button.hasAttribute('aria-label')) button.setAttribute('aria-label', 'Reversiを追加');
        if (button.hasAttribute('title')) button.setAttribute('title', 'Reversi');
        var icon = button.querySelector('i');
        if (icon && icon.classList) icon.className = 'fas fa-circle-half-stroke fa-fw';
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
        var previousType = String(select.getAttribute('data-reversi-previous-type') || 'icon_quest');
        var currentTitle = title ? String(title.value || '').trim() : '';
        if (title && (currentTitle === '' || currentTitle === (DEFAULT_TITLES[previousType] || '') || knownDefaultTitle(currentTitle))) {
            title.value = DEFAULT_TITLES[currentType] || 'Icon Quest';
        }
        select.setAttribute('data-reversi-previous-type', currentType);
    }
    function handlePresetClick(target) {
        var button = target && target.closest ? target.closest('[data-game-preset="reversi"][data-drawer-modal-target="#registerGameWidget"]') : null;
        if (!button) return;
        var select = document.getElementById('registerGameType');
        var title = document.querySelector('.registerGameTitleValue');
        if (!select) return;
        addGameOption(select);
        select.value = 'reversi';
        select.setAttribute('data-reversi-previous-type', 'reversi');
        if (title) title.value = 'Reversi';
        if (typeof window.Event === 'function') select.dispatchEvent(new window.Event('change', {bubbles:true}));
    }

    function setStatus(state, text, kind) {
        state.status.textContent = text;
        state.status.classList.toggle('text-danger', kind === 'danger');
        state.status.classList.toggle('text-success', kind === 'success');
        state.status.classList.toggle('text-muted', !kind);
    }
    function resultText(counts) {
        if (counts.black > counts.white) return 'Game Over：あなたの勝ちです。';
        if (counts.black < counts.white) return 'Game Over：CPUの勝ちです。';
        return 'Game Over：引き分けです。';
    }
    function finishGame(state) {
        state.gameOver = true;
        state.turn = EMPTY;
        if (state.cpuTimer !== null && typeof window.clearTimeout === 'function') {
            window.clearTimeout(state.cpuTimer); state.cpuTimer = null;
        }
        var counts = countDiscs(state.board);
        render(state);
        setStatus(state, resultText(counts), counts.black > counts.white ? 'success' : (counts.black < counts.white ? 'danger' : ''));
    }
    function render(state) {
        var counts = countDiscs(state.board);
        state.blackNode.textContent = String(counts.black);
        state.whiteNode.textContent = String(counts.white);
        state.turnNode.textContent = state.gameOver ? '終了' : (state.turn === BLACK ? 'あなた（黒）' : 'CPU（白）');
        var legal = state.gameOver || state.turn !== BLACK ? [] : legalMoves(state.board, BLACK);
        var legalMap = Object.create(null);
        for (var m = 0; m < legal.length; m++) legalMap[legal[m]] = true;
        for (var i = 0; i < CELL_COUNT; i++) {
            var cell = state.cells[i], value = state.board[i], isLegal = !!legalMap[i];
            cell.className = 'reversi-cell' + (isLegal ? ' reversi-legal' : '');
            cell.disabled = state.gameOver || state.turn !== BLACK || !isLegal;
            cell.innerHTML = value === EMPTY ? '' : '<span class="reversi-disc ' + (value === BLACK ? 'reversi-black' : 'reversi-white') + '" aria-hidden="true"></span>';
            cell.setAttribute('aria-label', (Math.floor(i / SIZE) + 1) + '行' + (i % SIZE + 1) + '列、' + (value === BLACK ? '黒' : value === WHITE ? '白' : isLegal ? '置けます' : '空き'));
        }
    }
    function scheduleCpu(state) {
        if (state.gameOver) return;
        var moves = legalMoves(state.board, WHITE);
        if (moves.length === 0) {
            if (legalMoves(state.board, BLACK).length === 0) { finishGame(state); return; }
            state.turn = BLACK;
            render(state);
            setStatus(state, 'CPUは置ける場所がないためPassしました。あなたの番です。', '');
            return;
        }
        state.turn = WHITE;
        render(state);
        setStatus(state, 'CPUが考えています...', '');
        if (state.cpuTimer !== null && typeof window.clearTimeout === 'function') window.clearTimeout(state.cpuTimer);
        if (typeof window.setTimeout !== 'function') { cpuMove(state); return; }
        state.cpuTimer = window.setTimeout(function () {
            state.cpuTimer = null;
            cpuMove(state);
        }, CPU_DELAY_MS);
    }
    function cpuMove(state) {
        if (!state || state.gameOver) return;
        var move = chooseCpuMove(state.board);
        if (move === null) { scheduleCpu(state); return; }
        var result = applyMove(state.board, move, WHITE);
        if (!result.changed) { scheduleCpu(state); return; }
        state.board = result.board;
        var blackMoves = legalMoves(state.board, BLACK);
        var whiteMoves = legalMoves(state.board, WHITE);
        if (blackMoves.length === 0 && whiteMoves.length === 0) { finishGame(state); return; }
        if (blackMoves.length === 0) {
            render(state);
            setStatus(state, 'あなたは置ける場所がないためPassです。CPUが続けます。', '');
            scheduleCpu(state);
            return;
        }
        state.turn = BLACK;
        render(state);
        setStatus(state, 'あなたの番です。緑の印が置ける場所です。', '');
    }
    function playerMove(state, moveIndex) {
        if (!state || state.gameOver || state.turn !== BLACK) return false;
        var result = applyMove(state.board, moveIndex, BLACK);
        if (!result.changed) return false;
        state.board = result.board;
        var blackMoves = legalMoves(state.board, BLACK);
        var whiteMoves = legalMoves(state.board, WHITE);
        if (blackMoves.length === 0 && whiteMoves.length === 0) { finishGame(state); return true; }
        scheduleCpu(state);
        return true;
    }
    function restart(state) {
        if (state.cpuTimer !== null && typeof window.clearTimeout === 'function') {
            window.clearTimeout(state.cpuTimer); state.cpuTimer = null;
        }
        state.board = initialBoard();
        state.turn = BLACK;
        state.gameOver = false;
        render(state);
        setStatus(state, 'あなたの番です。緑の印が置ける場所です。', '');
    }

    function createPanel(card) {
        var body = card.querySelector('.mini-game-card-body');
        if (!body) return null;
        body.innerHTML = '';
        var panel = document.createElement('div');
        panel.className = 'reversi-panel';
        panel.innerHTML = ''
            + '<div class="reversi-summary" aria-label="Reversi状況">'
            + '<div><span>あなた（黒）</span><strong class="reversi-black-count">2</strong></div>'
            + '<div><span>CPU（白）</span><strong class="reversi-white-count">2</strong></div>'
            + '<div><span>Turn</span><strong class="reversi-turn">あなた（黒）</strong></div></div>'
            + '<div class="reversi-board" role="grid" aria-label="Reversi 8×8盤面"></div>'
            + '<p class="reversi-status text-muted" aria-live="polite" aria-atomic="true">準備中...</p>'
            + '<div class="reversi-controls"><button type="button" class="btn btn-sm btn-outline-primary reversi-restart">Restart</button></div>'
            + '<p class="reversi-help">あなたは黒。緑の印が置ける場所です。PCはClick、SmartphoneはTapで操作します。</p>';
        body.appendChild(panel);
        var board = panel.querySelector('.reversi-board');
        for (var i = 0; i < CELL_COUNT; i++) {
            var cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'reversi-cell';
            cell.setAttribute('role', 'gridcell');
            cell.setAttribute('data-reversi-index', String(i));
            cell.setAttribute('aria-rowindex', String(Math.floor(i / SIZE) + 1));
            cell.setAttribute('aria-colindex', String(i % SIZE + 1));
            board.appendChild(cell);
        }
        return panel;
    }
    function initCard(card) {
        if (!card || card.getAttribute('data-reversi-initialized') === '1') return;
        if (positiveId(card.getAttribute('data-dashboard-widget-id')) === null) return;
        card.setAttribute('data-mini-game-initialized', '1');
        card.setAttribute('data-reversi-initialized', '1');
        var panel = createPanel(card);
        if (!panel) return;
        var boardNode = panel.querySelector('.reversi-board');
        var state = {
            card:card,
            board:initialBoard(),
            turn:BLACK,
            gameOver:false,
            cpuTimer:null,
            boardNode:boardNode,
            cells:Array.prototype.slice.call(boardNode.querySelectorAll('.reversi-cell')),
            blackNode:panel.querySelector('.reversi-black-count'),
            whiteNode:panel.querySelector('.reversi-white-count'),
            turnNode:panel.querySelector('.reversi-turn'),
            status:panel.querySelector('.reversi-status')
        };
        states.push(state);
        card.__rssReversiState = state;
        render(state);
        setStatus(state, 'あなたの番です。緑の印が置ける場所です。', '');

        boardNode.addEventListener('click', function (event) {
            var cell = event.target && event.target.closest ? event.target.closest('.reversi-cell[data-reversi-index]') : null;
            if (!cell || !boardNode.contains(cell)) return;
            playerMove(state, Number(cell.getAttribute('data-reversi-index')));
        });
        panel.querySelector('.reversi-restart').addEventListener('click', function () { restart(state); });
    }
    function initCards() {
        reserveCards();
        var cards = document.querySelectorAll('.mini-game-card[data-mini-game-type="reversi"]');
        for (var i = 0; i < cards.length; i++) initCard(cards[i]);
    }
    function cleanupDisconnected() {
        states = states.filter(function (state) {
            if (state.card && state.card.isConnected) return true;
            if (state.cpuTimer !== null && typeof window.clearTimeout === 'function') window.clearTimeout(state.cpuTimer);
            return false;
        });
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
                if (select) select.setAttribute('data-reversi-previous-type', String(select.value || 'icon_quest'));
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
    window.RssReversi = {
        SIZE:SIZE, EMPTY:EMPTY, BLACK:BLACK, WHITE:WHITE,
        initialBoard:initialBoard, flipsForMove:flipsForMove, legalMoves:legalMoves,
        applyMove:applyMove, countDiscs:countDiscs, chooseCpuMove:chooseCpuMove, init:initCards
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else start();
})(window, document);
