'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const source = fs.readFileSync(path.resolve(__dirname, '../public/js/lights-out.js'), 'utf8');
let checks = 0;
let failures = 0;
function check(ok, message) {
    checks += 1;
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + message);
    if (!ok) failures += 1;
}

let randomValues = [];
let randomCursor = 0;
const documentObject = {
    readyState: 'complete',
    querySelectorAll() { return []; },
    addEventListener() {}
};
const windowObject = {
    crypto: {
        getRandomValues(values) {
            const value = randomValues[randomCursor++] ?? (randomCursor * 17);
            values[0] = value;
            return values;
        }
    }
};
const context = {
    window: windowObject,
    document: documentObject,
    console,
    Array,
    Math,
    Number,
    String,
    Uint32Array
};
vm.runInNewContext(source, context, {filename: 'lights-out.js'});
const game = windowObject.RssLightsOut;
check(!!game && game.size === 5, 'Lights Out exposes one 5x5 runtime');

const corner = Array.from(game.toggleIndexes(0)).sort((a, b) => a - b);
const edge = Array.from(game.toggleIndexes(2)).sort((a, b) => a - b);
const center = Array.from(game.toggleIndexes(12)).sort((a, b) => a - b);
check(JSON.stringify(corner) === JSON.stringify([0, 1, 5]), 'corner press toggles itself and two neighbours');
check(JSON.stringify(edge) === JSON.stringify([1, 2, 3, 7]), 'edge press toggles itself and three neighbours');
check(JSON.stringify(center) === JSON.stringify([7, 11, 12, 13, 17]), 'center press toggles itself and four neighbours');

const empty = Array.from(game.emptyBoard());
check(empty.length === 25 && empty.every(value => value === false), 'empty board contains 25 unlit cells');
const once = Array.from(game.applyPress(empty, 12));
check(once.filter(Boolean).length === 5, 'one center press lights exactly five cells');
const twice = Array.from(game.applyPress(once, 12));
check(game.isClear(twice), 'pressing the same cell twice restores the board');
check(!game.isClear(once) && game.isClear(empty), 'Clear detection distinguishes lit and unlit boards');

const sequence = [0, 6, 12, 18, 24, 2, 10];
let board = Array.from(game.emptyBoard());
sequence.forEach(index => { board = Array.from(game.applyPress(board, index)); });
sequence.forEach(index => { board = Array.from(game.applyPress(board, index)); });
check(game.isClear(board), 'a board built from valid presses is solvable by replaying those presses');

randomValues = [5, 0, 6, 12, 18, 24, 2, 10, 14, 20, 4, 8, 16, 22, 1, 3, 7, 11, 13, 17, 19, 21];
randomCursor = 0;
const generated = Array.from(game.generatePuzzle());
const pressCount = 10 + (5 % 11);
const generatedPresses = randomValues.slice(1, 1 + pressCount).map(value => value % 25);
let solved = generated.slice();
generatedPresses.forEach(index => { solved = Array.from(game.applyPress(solved, index)); });
check(!game.isClear(generated), 'generated puzzle is never already Clear');
check(game.isClear(solved), 'generated puzzle is solvable by its valid generation operations');

const state = game.createState(generated);
check(state.moves === 0 && state.status === 'playing', 'new state starts at zero moves');
check(state.board !== state.initialBoard && JSON.stringify(state.board) === JSON.stringify(state.initialBoard), 'current and Reset boards are independent copies');
state.board[0] = !state.board[0];
check(state.board[0] !== state.initialBoard[0], 'changing the current board does not mutate the Reset board');

check(!/Notification|vibrate|Audio\s*\(/.test(source), 'Lights Out adds no notification, vibration or sound behavior');
console.log('RESULT: ' + (failures === 0 ? 'PASS' : 'FAIL') + ' ' + checks + ' / FAIL ' + failures);
if (failures) process.exit(1);
