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

function makeStorage(values = Object.create(null)) {
    return {
        values,
        getItem(key) { return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null; },
        setItem(key, value) { values[key] = String(value); },
        removeItem(key) { delete values[key]; }
    };
}

function createRuntime(local, session) {
    const main = {getAttribute: name => name === 'data-dashboard-user-id' ? '77' : null};
    const document = {
        readyState: 'complete', hidden: false, activeElement: null,
        getElementById: id => id === 'main-content' ? main : null,
        querySelectorAll: () => [], addEventListener() {}
    };
    const window = {
        document, localStorage: local, sessionStorage: session,
        setInterval() { return 1; }, clearInterval() {}, setTimeout() { return 1; }, clearTimeout() {},
        addEventListener() {}, Number, JSON
    };
    vm.runInContext(source, vm.createContext({window, document, console, Number, JSON, Object, String, Error, Date, Math}));
    return window.RssClockTimer;
}

const localValues = Object.create(null);
const sessionValues = Object.create(null);
const local = makeStorage(localValues);
const session = makeStorage(sessionValues);
const timer = createRuntime(local, session);
const key = timer.storageKey('77', '901');

let older = timer.defaultState();
older.view = 'timer';
older.durationSeconds = 300;
older.remainingSeconds = 300;
older.savedAt = 100;
let newer = timer.defaultState();
newer.view = 'timer';
newer.durationSeconds = 600;
newer.remainingSeconds = 600;
newer.savedAt = 200;
localValues[key] = JSON.stringify(older);
sessionValues[key] = JSON.stringify(newer);
let result = timer.loadStateResult('77', '901');
check(result.state.durationSeconds === 600, 'newest valid Storage copy wins across local and session backends');
check(result.recovered === false && result.reason === 'restored', 'multiple valid copies restore without a recovery warning');

localValues[key] = '{broken';
sessionValues[key] = JSON.stringify(newer);
result = timer.loadStateResult('77', '901');
check(result.state.durationSeconds === 600, 'valid session copy survives broken local JSON');
check(result.recovered === true && result.reason === 'repaired-copy', 'broken companion copy reports repaired recovery');
check(!Object.prototype.hasOwnProperty.call(localValues, key), 'only the broken local copy is removed');
check(Object.prototype.hasOwnProperty.call(sessionValues, key), 'valid session copy remains after recovery');

localValues[key] = JSON.stringify({...newer, schema: 99});
sessionValues[key] = JSON.stringify(older);
result = timer.loadStateResult('77', '901');
check(result.state.durationSeconds === 300, 'unknown schema falls back to a compatible copy');
check(!Object.prototype.hasOwnProperty.call(localValues, key), 'unknown schema copy is removed');

localValues[key] = '{broken';
sessionValues[key] = JSON.stringify({...older, remainingSeconds: 999});
result = timer.loadStateResult('77', '901');
check(result.recovered === true && result.reason === 'invalid-data', 'all-invalid Storage copies report safe reset');
check(result.state.status === 'idle' && result.state.durationSeconds === 300, 'all-invalid data resets to the default Timer');
check(!Object.prototype.hasOwnProperty.call(localValues, key) && !Object.prototype.hasOwnProperty.call(sessionValues, key), 'all-invalid copies are removed from both Browser backends');

const now = 2_000_000;
let running = timer.startState(timer.setDurationState(timer.defaultState(), 60), now);
let resumed = timer.tickState(running, now + 75_000);
check(resumed.completed === true && resumed.state.status === 'completed', 'long background delay completes from absolute endAt');
check(resumed.state.remainingSeconds === 0 && resumed.state.endAt === 0, 'background completion normalizes remaining time and endAt');

running = timer.startState(timer.setDurationState(timer.defaultState(), 600), now);
let visibleAgain = timer.tickState(running, now + 125_000);
check(visibleAgain.state.remainingSeconds === 475, 'resume calculation catches up after suspended intervals');

check(timer.loadStateResult('x', '901').reason === 'invalid-key', 'invalid User IDs remain rejected by recovery loader');
check(timer.loadStateResult('77', '0').reason === 'invalid-key', 'invalid Widget IDs remain rejected by recovery loader');

if (failures) {
    console.error(`${failures}/${checks} V1.5-C Clock Timer runtime checks failed.`);
    process.exitCode = 1;
} else {
    console.log(`All ${checks} V1.5-C Clock Timer runtime checks passed.`);
}
