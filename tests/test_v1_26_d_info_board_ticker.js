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
check(api.pixelsForSpeed('slow') === 70, 'slow ticker speed is 70px/s');
check(api.pixelsForSpeed('normal') === 105, 'normal ticker speed is 105px/s');
check(api.pixelsForSpeed('fast') === 150, 'fast ticker speed is 150px/s');
check(api.pixelsForSpeed('unexpected') === 105, 'unexpected ticker speed uses normal');
check(api.titleOnlyDelay('slow') === 4200, 'title-only slow fallback remains readable');
check(api.titleOnlyDelay('normal') === 3000, 'title-only normal fallback remains readable');
check(api.titleOnlyDelay('fast') === 2200, 'title-only fast fallback remains readable');
check(api.itemGapDelay === 500, 'article switch keeps a short 500ms gap');
check(api.interactionResumeDelay === 5000, 'manual interaction pause is five seconds');
check(api.reducedMotionPreferred() === false, 'reduced motion is initially off');
media.matches = true;
check(api.reducedMotionPreferred() === true, 'reduced motion follows the live media query state');

check(api.wrappedIndex(0, 1, 5) === 1, 'next navigation moves to the following article');
check(api.wrappedIndex(4, 1, 5) === 0, 'next navigation wraps from the final article to the first');
check(api.wrappedIndex(0, -1, 5) === 4, 'previous navigation wraps from the first article to the final article');
check(api.wrappedIndex(3, -1, 5) === 2, 'previous navigation moves to the preceding article');
check(api.wrappedIndex(9, 1, 0) === 0, 'navigation fails closed when the article list is empty');

check(api.footerMetaLabel('Example News', '8/30 12:34', 0, 5) === 'Example News · 8/30 12:34 · 1/5',
    'footer combines site name, date and current article count');
check(api.footerMetaLabel('', '', 4, 5) === '5/5',
    'footer still exposes article count when source/date metadata is absent');

check(api.progressForPosition(100, 300, 400) === 0, 'summary progress starts at zero at the right edge');
check(api.progressForPosition(-300, 300, 400) === 1, 'summary progress reaches one only after the text fully exits left');
const middleProgress = api.progressForPosition(-100, 300, 400);
check(middleProgress > 0 && middleProgress < 1, 'summary progress remains bounded while text is moving');

const textNode = {textContent: 'same'};
check(api.setTextIfChanged(textNode, 'same') === false && textNode.textContent === 'same',
    'idempotent footer update avoids rewriting unchanged text');
check(api.setTextIfChanged(textNode, 'changed') === true && textNode.textContent === 'changed',
    'footer update changes text only when the value actually changes');

const failed = checks.length - checks.filter(Boolean).length;
console.log('RESULT: PASS ' + (checks.length - failed) + ' / FAIL ' + failed + ' / SKIP 0');
if (failed > 0) {
    process.exit(1);
}
