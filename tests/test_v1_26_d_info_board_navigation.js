'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/info-board-navigation.js'), 'utf8');

let domReadyHandler = null;
const windowStub = {
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    addEventListener: function () {}
};
const documentStub = {
    readyState: 'loading',
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
    setTimeout: setTimeout,
    clearTimeout: clearTimeout
};

vm.runInNewContext(source, sandbox, {filename: 'info-board-navigation.js'});

const api = windowStub.RssInfoBoardNavigation;
if (!api) {
    throw new Error('RssInfoBoardNavigation API was not exported');
}

const checks = [];
function check(condition, message) {
    checks.push(Boolean(condition));
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

check(typeof domReadyHandler === 'function', 'navigation defers DOM initialization until DOMContentLoaded');
check(api.wrappedIndex(0, -1, 5) === 4, 'previous navigation wraps first article to last');
check(api.wrappedIndex(4, 1, 5) === 0, 'next navigation wraps last article to first');
check(api.footerMetaLabel('Example', '8/29 20:10', 2, 10) === 'Example ｜ 8/29 20:10 ｜ 3 / 10',
      'footer metadata combines source, date and position');

let currentText = 'same';
let writeCount = 0;
const textNode = {};
Object.defineProperty(textNode, 'textContent', {
    get: function () { return currentText; },
    set: function (value) { currentText = String(value); writeCount += 1; }
});

check(api.setTextIfChanged(textNode, 'same') === false && writeCount === 0,
      'identical footer text causes no DOM write');
check(api.setTextIfChanged(textNode, 'changed') === true && writeCount === 1 && currentText === 'changed',
      'changed footer text is written exactly once');
check(api.setTextIfChanged(textNode, 'changed') === false && writeCount === 1,
      'repeated footer refresh does not rewrite unchanged text');

const footerTarget = {
    closest: function (selector) {
        return selector === '.info-board-footer' ? {} : null;
    }
};
const outsideTarget = {
    closest: function () { return null; }
};

check(api.dashboardMutationNeedsRefresh([{type: 'childList', target: footerTarget}]) === false,
      'footer-only child mutation is ignored by the global dashboard observer');
check(api.dashboardMutationNeedsRefresh([{type: 'childList', target: outsideTarget}]) === true,
      'non-footer child mutation still refreshes Information Board bindings');
check(api.dashboardMutationNeedsRefresh([{type: 'attributes', target: footerTarget}]) === true,
      'relevant attribute mutations are not suppressed accidentally');
check(api.dashboardMutationNeedsRefresh([]) === true,
      'empty/unknown observer batches remain conservative');

const failed = checks.length - checks.filter(Boolean).length;
console.log('RESULT: PASS ' + (checks.length - failed) + ' / FAIL ' + failed + ' / SKIP 0');
if (failed > 0) {
    process.exit(1);
}
