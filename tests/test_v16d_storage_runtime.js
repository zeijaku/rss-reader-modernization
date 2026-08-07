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
function makeStorage(options = {}) {
    const values = Object.create(null);
    let writes = 0;
    return {
        values,
        getItem(key) {
            if (options.throwGet) throw new Error('get blocked');
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null;
        },
        setItem(key, value) {
            if (options.throwSet || (Number.isInteger(options.throwSetAfter) && writes >= options.throwSetAfter)) {
                throw new Error('set blocked');
            }
            writes += 1;
            values[key] = String(value);
        },
        removeItem(key) { delete values[key]; }
    };
}
function runtime(localStorage, sessionStorage) {
    const main = {getAttribute(name) { return name === 'data-dashboard-user-id' ? '7' : null; }};
    const document = {
        readyState: 'complete',
        getElementById(id) { return id === 'main-content' ? main : null; },
        querySelectorAll() { return []; },
        addEventListener() {}
    };
    const windowObject = {document, crypto: null};
    Object.defineProperty(windowObject, 'localStorage', {get() {
        if (localStorage instanceof Error) throw localStorage;
        return localStorage;
    }});
    Object.defineProperty(windowObject, 'sessionStorage', {get() {
        if (sessionStorage instanceof Error) throw sessionStorage;
        return sessionStorage;
    }});
    vm.runInContext(source, vm.createContext({
        window: windowObject, document, console, Array, Math, Number, String, Object, JSON, Date, Error, Uint32Array
    }));
    return windowObject.RssLightsOut;
}

const local = makeStorage();
const session = makeStorage();
const game = runtime(local, session);
check(game.storageMode() === 'localStorage', 'localStorage is preferred');
check(game.storageKey('7', '10') === 'rssReader.miniGame.lightsOut.v1.user.7.widget.10', 'Storage key separates User and Widget');
check(game.storageKey('x', '10') === null && game.storageKey('7', '0') === null, 'invalid User or Widget ID is rejected');

const initial = game.applyPress(game.emptyBoard(), 12);
let state = game.createState(initial);
state.board = game.applyPress(state.board, 0);
state.moves = 1;
check(game.saveState('7', '10', state), 'valid playing state saves');
let restored = game.loadState('7', '10');
check(restored.moves === 1 && JSON.stringify(restored.board) === JSON.stringify(state.board), 'board and Moves restore');
check(JSON.stringify(restored.initialBoard) === JSON.stringify(initial), 'Reset board restores separately');

const other = game.createState(game.applyPress(game.emptyBoard(), 6));
check(game.saveState('7', '11', other), 'second Widget state saves');
check(game.loadState('7', '10').moves === 1 && game.loadState('7', '11').moves === 0, 'multiple Widget keys remain independent');
check(game.storageKey('8', '10') !== game.storageKey('7', '10'), 'different Users receive different keys');

let clearState = game.createState(initial);
clearState.board = game.emptyBoard();
clearState.moves = 5;
clearState.status = 'cleared';
check(game.validateState(clearState) !== null && game.saveState('7', '12', clearState), 'Clear state is valid and saves');
check(game.loadState('7', '12').status === 'cleared', 'Clear state restores');
check(game.validateState({...clearState, status: 'playing'}) === null, 'all-off board cannot restore as playing');
check(game.validateState({...clearState, initialBoard: game.emptyBoard()}) === null, 'all-off initial puzzle is rejected');
check(game.validateState({...clearState, board: [false]}) === null, 'wrong board length is rejected');

const key = game.storageKey('7', '10');
local.values[key] = '{broken';
const recovered = game.loadStateResult('7', '10');
check(recovered.recovered === true && recovered.reason === 'invalid-data', 'broken JSON is reported as recovered');
check(!Object.prototype.hasOwnProperty.call(local.values, key), 'broken localStorage copy is removed');
check(recovered.state.moves === 0 && recovered.state.status === 'playing' && !game.isClear(recovered.state.board), 'broken data falls back to a safe new puzzle');

const localRepair = makeStorage();
const sessionRepair = makeStorage();
const repairedGame = runtime(localRepair, sessionRepair);
const repairKey = repairedGame.storageKey('7', '20');
localRepair.values[repairKey] = '{broken';
const validSessionState = repairedGame.createState(repairedGame.applyPress(repairedGame.emptyBoard(), 2));
validSessionState.savedAt = 100;
sessionRepair.values[repairKey] = JSON.stringify(validSessionState);
const repaired = repairedGame.loadStateResult('7', '20');
check(repaired.recovered === true && repaired.reason === 'repaired-copy', 'valid fallback copy repairs an invalid primary copy');
check(JSON.stringify(repaired.state.board) === JSON.stringify(validSessionState.board), 'valid fallback copy is restored');

const localNewest = makeStorage();
const sessionNewest = makeStorage();
const newestGame = runtime(localNewest, sessionNewest);
const newestKey = newestGame.storageKey('7', '30');
const older = newestGame.createState(newestGame.applyPress(newestGame.emptyBoard(), 1));
older.savedAt = 10;
const newer = newestGame.createState(newestGame.applyPress(newestGame.emptyBoard(), 3));
newer.moves = 2;
newer.savedAt = 20;
localNewest.values[newestKey] = JSON.stringify(older);
sessionNewest.values[newestKey] = JSON.stringify(newer);
check(newestGame.loadState('7', '30').moves === 2, 'newest valid Storage copy wins');

check(game.removeWidgetState('11'), 'Widget cleanup reports success');
check(!Object.prototype.hasOwnProperty.call(local.values, game.storageKey('7', '11')), 'Widget cleanup removes localStorage state');

const sessionGame = runtime(new Error('blocked'), makeStorage());
check(sessionGame.storageMode() === 'sessionStorage', 'sessionStorage fallback works when localStorage is unavailable');
const memoryGame = runtime(new Error('blocked'), new Error('blocked'));
check(memoryGame.storageMode() === 'memory', 'memory fallback works when Browser Storage is unavailable');
const memoryState = memoryGame.createState(memoryGame.applyPress(memoryGame.emptyBoard(), 4));
check(memoryGame.saveState('7', '40', memoryState) && memoryGame.loadState('7', '40').status === 'playing', 'memory fallback stores state for the current page');

const lateGame = runtime(makeStorage({throwSetAfter: 1}), makeStorage());
const lateState = lateGame.createState(lateGame.applyPress(lateGame.emptyBoard(), 5));
check(lateGame.saveState('7', '50', lateState) && lateGame.storageMode() === 'sessionStorage', 'late localStorage write failure falls back to sessionStorage');

check(game.keyboardTarget(0, 'ArrowRight') === 1 && game.keyboardTarget(0, 'ArrowLeft') === 0, 'horizontal keyboard navigation respects edges');
check(game.keyboardTarget(0, 'ArrowDown') === 5 && game.keyboardTarget(0, 'ArrowUp') === 0, 'vertical keyboard navigation respects edges');
check(game.keyboardTarget(7, 'Home') === 5 && game.keyboardTarget(7, 'End') === 9, 'Home and End move within the current row');

console.log('RESULT: ' + (failures === 0 ? 'PASS' : 'FAIL') + ' ' + checks + ' / FAIL ' + failures);
if (failures) process.exit(1);
