'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../public/js/reversi.js'), 'utf8');
const listeners = {};
const document = {
    readyState: 'loading',
    body: {},
    querySelectorAll: () => [],
    querySelector: () => null,
    getElementById: () => null,
    createElement: () => ({setAttribute() {}, appendChild() {}, querySelector() { return null; }, classList: {contains() { return false; }}}),
    addEventListener: (type, handler) => { listeners[type] = handler; }
};
const window = {document, addEventListener() {}, Event: function Event() {}};
vm.runInNewContext(source, {window, document, console, Math, Number, String, Object, Array}, {filename:'reversi.js'});

let failures = 0;
let checks = 0;
function check(condition, message) {
    checks += 1;
    if (!condition) failures += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}
function same(actual, expected) {
    return JSON.stringify(Array.from(actual)) === JSON.stringify(expected);
}

const api = window.RssReversi;
check(api && api.SIZE === 8, 'Reversi exposes an 8x8 runtime contract');
check(api.BLACK === 1 && api.WHITE === 2 && api.EMPTY === 0, 'disc constants remain stable');

const initial = api.initialBoard();
const initialCounts = api.countDiscs(initial);
check(initial.length === 64 && initialCounts.black === 2 && initialCounts.white === 2 && initialCounts.empty === 60, 'initial board contains standard four center discs');

const blackMoves = api.legalMoves(initial, api.BLACK);
check(same(blackMoves, [19,26,37,44]), 'Black starts with the standard four legal moves');
const whiteMoves = api.legalMoves(initial, api.WHITE);
check(same(whiteMoves, [20,29,34,43]), 'White starts with the standard four legal moves');

let result = api.applyMove(initial, 19, api.BLACK);
check(result.changed && same(result.flips, [27]), 'Black legal move flips the bracketed white disc');
let counts = api.countDiscs(result.board);
check(counts.black === 4 && counts.white === 1 && counts.empty === 59, 'disc counts update after a legal move');

result = api.applyMove(initial, 0, api.BLACK);
check(!result.changed && result.flips.length === 0 && same(result.board, initial), 'illegal move is rejected without mutating the board');

const lineBoard = new Array(64).fill(api.EMPTY);
lineBoard[0] = api.BLACK;
lineBoard[1] = api.WHITE;
lineBoard[2] = api.WHITE;
result = api.applyMove(lineBoard, 3, api.BLACK);
check(result.changed && same(result.flips, [2,1]), 'multiple discs flip along one captured direction');

const cpuOpening = api.chooseCpuMove(initial);
check(api.legalMoves(initial, api.WHITE).includes(cpuOpening), 'CPU always selects a legal opening move');

const cornerBoard = new Array(64).fill(api.EMPTY);
cornerBoard[1] = api.BLACK;
cornerBoard[2] = api.WHITE;
cornerBoard[17] = api.BLACK;
cornerBoard[18] = api.WHITE;
const cornerMoves = api.legalMoves(cornerBoard, api.WHITE);
check(cornerMoves.includes(0) && cornerMoves.includes(16), 'CPU test position exposes both corner and non-corner legal moves');
check(api.chooseCpuMove(cornerBoard) === 0, 'CPU heuristic prefers an available corner');

const fullBoard = new Array(64).fill(api.BLACK);
check(api.legalMoves(fullBoard, api.BLACK).length === 0 && api.legalMoves(fullBoard, api.WHITE).length === 0, 'full board has no legal moves for either side');
counts = api.countDiscs(fullBoard);
check(counts.black === 64 && counts.white === 0 && counts.empty === 0, 'full-board counts remain exact');

check(typeof api.flipsForMove === 'function' && typeof api.chooseCpuMove === 'function', 'move validator and CPU selector remain testable pure helpers');
check(typeof listeners.DOMContentLoaded === 'function', 'runtime waits for DOM readiness before UI initialization');

console.log(`RESULT: PASS ${checks - failures} / FAIL ${failures} / SKIP 0`);
process.exitCode = failures ? 1 : 0;
