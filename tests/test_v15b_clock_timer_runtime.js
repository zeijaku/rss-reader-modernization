'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../public/js/clock-timer.js'), 'utf8');
let checks = 0;
let failures = 0;

function check(condition, message) {
    checks += 1;
    if (!condition) failures += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

function makeStorage(options = {}) {
    const values = Object.create(null);
    let writes = 0;
    return {
        values,
        getItem(key) {
            if (options.throwGet) throw new Error('get');
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null;
        },
        setItem(key, value) {
            if (options.throwSet || (Number.isInteger(options.throwSetAfter) && writes >= options.throwSetAfter)) {
                throw new Error('set');
            }
            writes += 1;
            values[key] = String(value);
        },
        removeItem(key) {
            delete values[key];
        }
    };
}

function createRuntime(localStorage, sessionStorage) {
    const main = {getAttribute: name => name === 'data-dashboard-user-id' ? '7' : null};
    const document = {
        readyState: 'complete',
        activeElement: null,
        getElementById: id => id === 'main-content' ? main : null,
        querySelectorAll: () => [],
        addEventListener() {}
    };
    const intervals = [];
    const window = {
        document,
        setInterval(callback) { intervals.push(callback); return intervals.length; },
        clearInterval() {},
        Number,
        JSON
    };
    Object.defineProperty(window, 'localStorage', {
        get() {
            if (localStorage instanceof Error) throw localStorage;
            return localStorage;
        }
    });
    Object.defineProperty(window, 'sessionStorage', {
        get() {
            if (sessionStorage instanceof Error) throw sessionStorage;
            return sessionStorage;
        }
    });
    const context = vm.createContext({window, document, console, Number, JSON, Object, String, Error, Date, Math});
    vm.runInContext(source, context);
    return window.RssClockTimer;
}

const local = makeStorage();
const timer = createRuntime(local, makeStorage());
check(timer.storageMode() === 'localStorage', 'localStorage is preferred');
check(timer.storageKey('7', '10') === 'rssReader.clockTimer.v1.user.7.widget.10', 'Storage key separates User and Widget');
check(timer.storageKey('x', '10') === null && timer.storageKey('7', '0') === null, 'invalid Storage IDs are rejected');

let state = timer.defaultState();
check(state.view === 'clock' && state.status === 'idle', 'default state starts in Clock view and idle Timer state');
check(state.durationSeconds === 300 && state.remainingSeconds === 300, 'default Timer duration is five minutes');
check(timer.validateState(state) !== null, 'default state passes strict validation');
check(timer.validateState({...state, schema: 2}) === null, 'unknown schema is rejected');
check(timer.validateState({...state, durationSeconds: 59, remainingSeconds: 59}) === null, 'duration below one minute is rejected');
check(timer.validateState({...state, durationSeconds: 86401, remainingSeconds: 86401}) === null, 'duration above 24 hours is rejected');
check(timer.validateState({...state, status: 'running', endAt: 0}) === null, 'running state requires an end timestamp');

state = timer.setDurationState(state, 600);
check(state.durationSeconds === 600 && state.remainingSeconds === 600 && state.status === 'idle', 'Preset duration updates idle state');
check(timer.setDurationState(state, 30).durationSeconds === 600, 'invalid duration does not replace the current value');

const startedAt = 1000000;
state = timer.startState(state, startedAt);
check(state.status === 'running' && state.endAt === startedAt + 600000, 'Start stores an absolute end timestamp');
check(timer.remainingAt(state, startedAt + 125000) === 475, 'remaining time is calculated from Date.now and endAt');

state = timer.pauseState(state, startedAt + 125000);
check(state.status === 'paused' && state.remainingSeconds === 475 && state.endAt === 0, 'Pause stores remaining seconds and clears endAt');
state = timer.startState(state, startedAt + 200000);
check(state.status === 'running' && state.endAt === startedAt + 675000, 'Resume creates a new endAt from paused remaining time');

let tick = timer.tickState(state, startedAt + 674000);
check(tick.completed === false && tick.state.remainingSeconds === 1, 'Timer remains running before endAt');
tick = timer.tickState(state, startedAt + 675000);
check(tick.completed === true && tick.state.status === 'completed' && tick.state.remainingSeconds === 0, 'Timer completes at endAt');
state = timer.resetState(tick.state);
check(state.status === 'idle' && state.remainingSeconds === 600 && state.endAt === 0, 'Reset returns to selected duration');

check(timer.formatDuration(0) === '00:00:00', 'zero duration formatting is stable');
check(timer.formatDuration(3661) === '01:01:01', 'hours, minutes and seconds format correctly');
check(timer.formatDuration(86400) === '24:00:00', '24-hour maximum formats correctly');

state.view = 'timer';
check(timer.saveState('7', '10', state), 'valid Timer state saves');
check(timer.loadState('7', '10').view === 'timer', 'saved view restores');
check(timer.loadState('7', '11').durationSeconds === 300, 'Widget states are isolated');
check(timer.loadState('8', '10').durationSeconds === 300, 'User states are isolated');

const key = timer.storageKey('7', '10');
local.values[key] = '{broken';
check(timer.loadState('7', '10').durationSeconds === 300, 'broken JSON recovers to defaults');
check(!Object.prototype.hasOwnProperty.call(local.values, key), 'broken JSON is removed');

timer.saveState('7', '10', timer.defaultState());
check(timer.removeWidgetState('10') && !Object.prototype.hasOwnProperty.call(local.values, key), 'Clock deletion removes Timer state');

const sessionTimer = createRuntime(new Error('blocked'), makeStorage());
check(sessionTimer.storageMode() === 'sessionStorage', 'sessionStorage fallback works');
const memoryTimer = createRuntime(new Error('blocked'), new Error('blocked'));
check(memoryTimer.storageMode() === 'memory' && memoryTimer.saveState('7', '20', memoryTimer.defaultState()), 'Memory fallback works');
const lateFallback = createRuntime(makeStorage({throwSetAfter: 1}), makeStorage());
check(lateFallback.saveState('7', '21', lateFallback.defaultState()) && lateFallback.storageMode() === 'sessionStorage', 'write failure falls back to sessionStorage');

if (failures) {
    console.error(`${failures}/${checks} V1.5-B Clock Timer runtime checks failed.`);
    process.exitCode = 1;
} else {
    console.log(`All ${checks} V1.5-B Clock Timer runtime checks passed.`);
}
