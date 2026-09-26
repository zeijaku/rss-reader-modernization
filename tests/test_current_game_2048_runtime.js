'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../public/js/game-2048.js'), 'utf8');
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
const window = {document, addEventListener() {}, Event: function Event() {}, Math};
vm.runInNewContext(source, {window, document, console, Math, Number, String, Object, Array, Uint32Array}, {filename: 'game-2048.js'});

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

const api = window.RssGame2048;
check(api && api.SIZE === 4, '2048 exposes a 4x4 runtime contract');
check(typeof api.moveLineLeft === 'function' && typeof api.moveBoard === 'function', 'pure movement helpers are exposed for regression tests');

let result = api.moveLineLeft([2, 2, 0, 0]);
check(same(result.line, [4, 0, 0, 0]) && result.score === 4 && result.changed, 'simple pair merges left and scores merged value');
result = api.moveLineLeft([2, 2, 2, 2]);
check(same(result.line, [4, 4, 0, 0]) && result.score === 8, 'two independent pairs merge once each');
result = api.moveLineLeft([2, 2, 4, 0]);
check(same(result.line, [4, 4, 0, 0]) && result.score === 4, 'newly merged tile does not merge again in the same move');
result = api.moveLineLeft([4, 0, 4, 4]);
check(same(result.line, [8, 4, 0, 0]) && result.score === 8, 'gaps collapse before pair evaluation');

const board = [2,2,4,4, 0,2,0,2, 4,0,4,0, 8,8,8,0];
result = api.moveBoard(board, 'left');
check(same(result.board, [4,8,0,0, 4,0,0,0, 8,0,0,0, 16,8,0,0]) && result.score === 40, 'left move transforms all rows and accumulates score');
result = api.moveBoard([2,0,0,0, 2,0,0,0, 4,0,0,0, 4,0,0,0], 'up');
check(same(result.board, [4,0,0,0, 8,0,0,0, 0,0,0,0, 0,0,0,0]) && result.score === 12, 'vertical merge obeys the same one-merge rule');
result = api.moveBoard([2,0,0,0, 0,0,0,0, 0,0,0,0, 0,0,0,0], 'right');
check(same(result.board, [0,0,0,2, 0,0,0,0, 0,0,0,0, 0,0,0,0]), 'right movement preserves orientation');
result = api.moveBoard([2,0,0,0, 0,0,0,0, 0,0,0,0, 0,0,0,0], 'up');
check(!result.changed, 'no-op direction is reported as unchanged');

check(api.canMove([2,4,8,16, 32,64,128,256, 512,1024,2,4, 8,16,32,64]) === false, 'full board without adjacent equals is game over');
check(api.canMove([2,4,8,16, 32,64,128,256, 512,1024,2,4, 8,16,32,32]) === true, 'full board with an adjacent pair remains playable');
check(api.canMove([2,4,8,16, 32,64,0,256, 512,1024,2,4, 8,16,32,64]) === true, 'empty cell keeps the game playable');

const spawned = api.spawnTile(new Array(16).fill(0), 5, 4);
check(spawned.filter(v => v !== 0).length === 1 && spawned[5] === 4, 'spawnTile places one forced test tile without mutating other cells');
check(api.swipeDirection(100,100,20,105) === 'left', 'horizontal smartphone swipe resolves left');
check(api.swipeDirection(100,100,105,180) === 'down', 'vertical smartphone swipe resolves down');
check(api.swipeDirection(100,100,110,110) === null, 'short touch movement is ignored');
check(typeof listeners.DOMContentLoaded === 'function', 'runtime waits for DOM readiness before UI initialization');

console.log(`RESULT: PASS ${checks - failures} / FAIL ${failures} / SKIP 0`);
process.exitCode = failures ? 1 : 0;
