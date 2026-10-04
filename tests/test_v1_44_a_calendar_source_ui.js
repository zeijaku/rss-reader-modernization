'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

function jqueryStub(arg) {
    if (typeof arg === 'function') {
        arg();
        return;
    }
    return {
        length: 0,
        on() { return this; },
        off() { return this; },
        each() { return this; },
        attr() { return ''; },
        data() { return null; },
        find() { return this; },
        text() { return this; },
        prop() { return this; },
        val() { return ''; },
        empty() { return this; },
        append() { return this; },
        removeClass() { return this; },
        addClass() { return this; },
        closest() { return this; }
    };
}
jqueryStub.extend = Object.assign;
jqueryStub.ajax = function () {
    throw new Error('No network request is expected in the V1.44-A UI unit test.');
};

const documentStub = {};
const windowStub = {
    jQuery: jqueryStub,
    localStorage: null,
    setTimeout(fn) { return 1; },
    confirm() { return true; }
};

const context = {
    window: windowStub,
    document: documentStub,
    console,
    setTimeout: windowStub.setTimeout
};
context.global = context;

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'calendar-sources.js'), 'utf8');
vm.runInNewContext(source, context, {filename: 'calendar-sources.js'});

const api = windowStub.iGuguruCalendarSources;
assert(api && typeof api.normalizeSources === 'function', 'Calendar source helper API must be exported.');

const sources = api.normalizeSources([
    {source_id: 1, name: '仕事', color: 'green', is_default: true},
    {source_id: '2', name: '私用', color: 'purple', is_default: false},
    {source_id: 'bad', name: '無効', color: 'blue'}
]);
assert(sources.length === 2, 'Invalid source IDs must be rejected.');
assert(sources[0].source_id === '1' && sources[1].source_id === '2', 'Source IDs must be normalized to strings.');

const visible = api.visibleSourceIds(sources, ['2']);
assert(visible.length === 1 && visible[0] === '1', 'Hidden source filtering must preserve visible Calendar IDs.');

assert(api.filterLabel(sources, ['1', '2']) === 'すべて', 'All visible sources should display すべて.');
assert(api.filterLabel(sources, []) === 'なし', 'No visible sources should display なし.');
assert(api.filterLabel(sources, ['2']) === '私用', 'One visible source should display its Calendar name.');

const three = api.normalizeSources([
    {source_id: 1, name: 'A', color: 'blue'},
    {source_id: 2, name: 'B', color: 'green'},
    {source_id: 3, name: 'C', color: 'red'}
]);
assert(api.filterLabel(three, ['1', '3']) === '2/3', 'Partial multi-source selection should display a count.');

assert(typeof api.filterRangeData === 'function', 'Calendar source helper must expose pre-layout range filtering.');
const range = {
    sources,
    events: [
        {event_id: 10, calendar_source_id: 1, title: '仕事'},
        {event_id: 11, calendar_source_id: 2, title: '私用'}
    ],
    cancelled_occurrences: [
        {event_id: 12, calendar_source_id: 2, title: '取消'}
    ],
    tasks: [
        {task_id: 20, title: 'Task'}
    ],
    holidays: {'2026-10-04': 'test'}
};
const workOnly = api.filterRangeData(range, ['2']);
assert(workOnly.events.length === 1 && workOnly.events[0].event_id === 10,
    'Hidden Calendar events must be removed before Calendar layout.');
assert(workOnly.cancelled_occurrences.length === 0,
    'Hidden Calendar cancelled occurrences must be removed before Calendar layout.');
assert(workOnly.tasks.length === 1 && workOnly.tasks[0].task_id === 20,
    'Calendar source filtering must not hide Task items.');
assert(range.events.length === 2 && range.cancelled_occurrences.length === 1,
    'Calendar source filtering must not mutate the raw range response.');
const noneVisible = api.filterRangeData(range, ['1', '2']);
assert(noneVisible.events.length === 0 && noneVisible.cancelled_occurrences.length === 0,
    'Hiding all Calendars must leave no event lanes to consume layout space.');
assert(noneVisible.tasks.length === 1, 'Task items must remain visible when all Calendars are hidden.');

console.log('V1.44-A Calendar source UI helpers: OK');
