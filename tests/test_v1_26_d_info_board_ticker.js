'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/info-board-ticker.js'), 'utf8');

let domReadyHandler = null;
const media = {
    matches: false,
    addEventListener: function () {},
    addListener: function () {}
};
const windowStub = {
    matchMedia: function (query) {
        if (query !== '(prefers-reduced-motion: reduce)') {
            throw new Error('unexpected media query: ' + query);
        }
        return media;
    },
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    addEventListener: function () {}
};
const documentStub = {
    readyState: 'loading',
    hidden: false,
    addEventListener: function (name, handler) {
        if (name === 'DOMContentLoaded') {
            domReadyHandler = handler;
        }
    }
};

const sandbox = {
    window: windowStub,
    document: documentStub,
    Date: Date,
    Math: Math,
    Number: Number,
    String: String,
    Object: Object,
    Array: Array,
    Infinity: Infinity,
    setTimeout: setTimeout,
    clearTimeout: clearTimeout
};

vm.runInNewContext(source, sandbox, {filename: 'info-board-ticker.js'});

const api = windowStub.RssInfoBoardTicker;
if (!api) {
    throw new Error('RssInfoBoardTicker API was not exported');
}

const checks = [];
function check(condition, message) {
    checks.push(Boolean(condition));
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

check(typeof domReadyHandler === 'function', 'ticker defers DOM initialization until DOMContentLoaded when document is loading');
check(api.normalizeSpeed('slow') === 'slow', 'slow speed is preserved');
check(api.normalizeSpeed('normal') === 'normal', 'normal speed is preserved');
check(api.normalizeSpeed('fast') === 'fast', 'fast speed is preserved');
check(api.normalizeSpeed('turbo') === 'normal', 'unknown speed falls back to normal');
check(api.normalizeSpeed('') === 'normal', 'empty speed falls back to normal');
check(api.delayForSpeed('slow') === 6500, 'slow interval is 6500ms');
check(api.delayForSpeed('normal') === 4200, 'normal interval is 4200ms');
check(api.delayForSpeed('fast') === 2500, 'fast interval is 2500ms');
check(api.delayForSpeed('unexpected') === 4200, 'unexpected interval uses normal speed');
check(api.interactionResumeDelay === 5000, 'manual interaction pause is five seconds');
check(api.reducedMotionPreferred() === false, 'reduced motion is initially off');
media.matches = true;
check(api.reducedMotionPreferred() === true, 'reduced motion follows the live media query state');

const failed = checks.length - checks.filter(Boolean).length;
console.log('RESULT: PASS ' + (checks.length - failed) + ' / FAIL ' + failed + ' / SKIP 0');
if (failed > 0) {
    process.exit(1);
}
