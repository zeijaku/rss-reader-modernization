'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/mini-game.js'), 'utf8');
let checks = 0;
let failures = 0;
function check(condition, message) {
    checks += 1;
    if (!condition) failures += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

function makeStorage(options = {}) {
    const values = Object.create(null);
    let setCount = 0;
    return {
        values,
        getItem(key) {
            if (options.throwGet) throw new Error('get blocked');
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null;
        },
        setItem(key, value) {
            if (options.throwSet || (Number.isInteger(options.throwSetAfter) && setCount >= options.throwSetAfter)) {
                throw new Error('set blocked');
            }
            setCount += 1;
            values[key] = String(value);
        },
        removeItem(key) {
            if (options.throwRemove) throw new Error('remove blocked');
            delete values[key];
        }
    };
}

function makeCard(widgetId) {
    const attrs = {
        'data-dashboard-widget-id': String(widgetId),
        'data-mini-game-initialized': '0'
    };
    const status = {textContent: ''};
    const reset = {
        listener: null,
        addEventListener(type, callback) {
            if (type === 'click') this.listener = callback;
        }
    };
    return {
        attrs,
        status,
        reset,
        getAttribute(name) { return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null; },
        setAttribute(name, value) { attrs[name] = String(value); },
        querySelector(selector) {
            if (selector === '.mini-game-status') return status;
            if (selector === '.mini-game-reset') return reset;
            return null;
        }
    };
}

function runRuntime({localStorage, sessionStorage, cards = []}) {
    const main = {getAttribute(name) { return name === 'data-dashboard-user-id' ? '7' : null; }};
    const document = {
        readyState: 'complete',
        getElementById(id) { return id === 'main-content' ? main : null; },
        querySelectorAll(selector) { return selector === '[data-dashboard-widget-type="game"]' ? cards : []; },
        addEventListener() {}
    };
    const window = {document, confirm: () => true, Number, JSON};
    Object.defineProperty(window, 'localStorage', {get() {
        if (localStorage instanceof Error) throw localStorage;
        return localStorage;
    }});
    Object.defineProperty(window, 'sessionStorage', {get() {
        if (sessionStorage instanceof Error) throw sessionStorage;
        return sessionStorage;
    }});
    const context = vm.createContext({window, document, console, Number, JSON, Object, String, Error});
    vm.runInContext(source, context, {filename: 'mini-game.js'});
    return window.RssMiniGame;
}

const local = makeStorage();
const session = makeStorage();
const cards = [makeCard(10), makeCard(11)];
const runtime = runRuntime({localStorage: local, sessionStorage: session, cards});
check(runtime.storageMode() === 'localStorage', 'localStorage is preferred when available');
check(runtime.storageKey('7', '10') === 'rssReader.miniGame.iconQuest.v1.user.7.widget.10', 'Storage key includes Game version, User ID and Widget ID');
check(runtime.storageKey('0', '10') === null && runtime.storageKey('7', 'x') === null, 'invalid IDs cannot create a Storage key');
check(cards.every(card => card.attrs['data-mini-game-initialized'] === '1'), 'multiple Game Widgets initialize independently');
check(cards[0].status.textContent === '準備完了' && cards[1].status.textContent === '準備完了', 'each initialized Widget receives a safe status');
check(Object.keys(local.values).some(key => key.endsWith('.widget.10')) && Object.keys(local.values).some(key => key.endsWith('.widget.11')), 'multiple Widget states use separate localStorage entries');

const state = runtime.defaultState();
state.moves = 4;
state.savedAt = 123;
check(runtime.saveState('7', '10', state) === true, 'valid state is saved');
check(runtime.loadState('7', '10').moves === 4, 'valid state is restored');
check(runtime.validateState({...state, moves: -1}) === null, 'negative move count is rejected');
check(runtime.validateState({...state, schema: 2}) === null, 'unknown Storage schema is rejected');

const key10 = runtime.storageKey('7', '10');
local.values[key10] = JSON.stringify({...state, schema: 2});
check(runtime.loadState('7', '10').moves === 0, 'unknown schema recovers to default state');
check(!Object.prototype.hasOwnProperty.call(local.values, key10), 'unknown schema is removed from Storage');

local.values[key10] = '{broken';
check(runtime.loadState('7', '10').moves === 0, 'broken JSON recovers to default state');
check(!Object.prototype.hasOwnProperty.call(local.values, key10), 'broken JSON is removed from Storage');

runtime.saveState('7', '10', state);
check(runtime.removeWidgetState('10') === true && !Object.prototype.hasOwnProperty.call(local.values, key10), 'Widget state can be removed after successful deletion');

runtime.saveState('7', '11', state);
cards[1].reset.listener();
check(runtime.loadState('7', '11').moves === 0, 'Reset returns one Widget to the initial state');
check(runtime.loadState('7', '10').moves === 0, 'Reset does not create cross-Widget state');

const sessionOnly = makeStorage();
const runtimeSession = runRuntime({localStorage: new Error('local blocked'), sessionStorage: sessionOnly});
check(runtimeSession.storageMode() === 'sessionStorage', 'sessionStorage is used when localStorage is unavailable');

const runtimeMemory = runRuntime({localStorage: new Error('local blocked'), sessionStorage: new Error('session blocked')});
check(runtimeMemory.storageMode() === 'memory', 'Memory fallback is used when Browser Storage is unavailable');
check(runtimeMemory.saveState('7', '20', runtimeMemory.defaultState()) === true, 'Memory fallback keeps the Game usable');
check(runtimeMemory.loadState('7', '20').status === 'ready', 'Memory fallback restores state during the current page');


const lateFailureLocal = makeStorage({throwSetAfter: 1});
const lateFailureSession = makeStorage();
const runtimeLateFailure = runRuntime({localStorage: lateFailureLocal, sessionStorage: lateFailureSession});
check(runtimeLateFailure.storageMode() === 'localStorage', 'localStorage can pass the initial availability probe');
check(runtimeLateFailure.saveState('7', '21', runtimeLateFailure.defaultState()) === true, 'late localStorage write failure still saves state');
check(runtimeLateFailure.storageMode() === 'sessionStorage', 'late localStorage failure falls back to sessionStorage');
check(Object.keys(lateFailureSession.values).some(key => key.endsWith('.widget.21')), 'fallback state is written to sessionStorage');

const quotaLocal = makeStorage({throwSet: true});
const runtimeQuota = runRuntime({localStorage: quotaLocal, sessionStorage: makeStorage()});
check(runtimeQuota.storageMode() === 'sessionStorage', 'Storage probe skips a localStorage implementation that cannot write');

if (failures > 0) {
    process.exitCode = 1;
} else {
    console.log(`All ${checks} V1.4-B Storage runtime checks passed.`);
}
