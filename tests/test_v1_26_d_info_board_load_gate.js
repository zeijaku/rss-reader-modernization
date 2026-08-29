'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/memo-counter.js'), 'utf8');

let ajaxCalls = 0;
function originalAjax(options) {
    ajaxCalls += 1;
    return {options: options};
}

function Deferred() {
    return {
        promise: function () { return {}; },
        resolveWith: function () {},
        rejectWith: function () {}
    };
}

const jQueryStub = {
    ajax: originalAjax,
    Deferred: Deferred,
    active: 0
};
const windowStub = {
    jQuery: jQueryStub,
    setTimeout: function () { return 1; },
    clearTimeout: function () {}
};
const sandbox = {
    window: windowStub,
    Number: Number,
    String: String,
    Array: Array,
    Object: Object,
    Math: Math,
    setTimeout: windowStub.setTimeout,
    clearTimeout: windowStub.clearTimeout
};

vm.runInNewContext(source, sandbox, {filename: 'memo-counter.js'});

const gate = windowStub.RssInfoBoardAjaxGate;
if (!gate) {
    throw new Error('RssInfoBoardAjaxGate was not exported');
}

const checks = [];
function check(condition, message) {
    checks.push(Boolean(condition));
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

check(gate.isInfoBoardFetch({data: {action: 'widget.infoboard.fetch'}}) === true,
      'Information Board fetch is recognized');
check(gate.isInfoBoardFetch({data: {action: 'feed.fetch'}}) === false,
      'normal RSS feed fetch is not gated');
check(gate.isInfoBoardFetch({data: {action: 'widget.weather.fetch'}}) === false,
      'other remote widget fetch is not gated');
check(gate.canStart(4, 10) === false, 'active Dashboard requests block Information Board');
check(gate.canStart(1, 2) === false, 'one active Dashboard request still blocks Information Board');
check(gate.canStart(0, 1) === false, 'one idle poll is not enough');
check(gate.canStart(0, 2) === true, 'two stable idle polls allow Information Board');

const normalResult = jQueryStub.ajax({data: {action: 'feed.fetch'}});
check(ajaxCalls === 1 && normalResult && normalResult.options.data.action === 'feed.fetch',
      'normal RSS request reaches the original Ajax implementation immediately');

jQueryStub.ajax({data: {action: 'widget.infoboard.fetch'}});
check(ajaxCalls === 1 && gate.pendingCount() === 1,
      'Information Board request is queued instead of competing immediately');

const failed = checks.length - checks.filter(Boolean).length;
console.log('RESULT: PASS ' + (checks.length - failed) + ' / FAIL ' + failed + ' / SKIP 0');
if (failed > 0) {
    process.exit(1);
}
