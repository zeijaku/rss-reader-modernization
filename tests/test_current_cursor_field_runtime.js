'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../public/js/cursor-field.js'), 'utf8');
const listeners = {};
const document = {
    body: {},
    hidden: false,
    readyState: 'loading',
    querySelectorAll: () => [],
    querySelector: () => null,
    getElementById: () => null,
    createElement: () => ({}),
    addEventListener: (type, handler) => { listeners[type] = handler; }
};
const window = {
    document,
    addEventListener: () => {},
    requestAnimationFrame: () => 1,
    cancelAnimationFrame: () => {},
    devicePixelRatio: 1
};

vm.runInNewContext(source, {window, document, console, Math, Object, Number, String}, {filename: 'cursor-field.js'});

let failures = 0;
function check(condition, message) {
    if (!condition) failures += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

check(window.RssCursorField && typeof window.RssCursorField.init === 'function', 'runtime exposes an explicit initializer');
check(typeof window.RssCursorField.stopAll === 'function', 'runtime exposes animation cleanup');
check(typeof listeners.DOMContentLoaded === 'function', 'runtime waits for DOM readiness');
window.RssCursorField.init();
window.RssCursorField.stopAll();
check(true, 'empty Dashboard initialization and cleanup are safe');

console.log(`RESULT: PASS ${4 - failures} / FAIL ${failures} / SKIP 0`);
process.exitCode = failures ? 1 : 0;
