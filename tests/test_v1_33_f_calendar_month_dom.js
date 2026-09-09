'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const layoutSource = fs.readFileSync(path.join(root, 'public/js/calendar-month-layout.js'), 'utf8');
const coreSource = fs.readFileSync(path.join(root, 'public/js/calendar-core.js'), 'utf8');
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS: ' + message + '\n');
    } else {
        failed += 1;
        process.stderr.write('FAIL: ' + message + '\n');
    }
}

const created = [];
const requests = [];

class FakeQuery {
    constructor(kind, items) {
        this.kind = kind || 'generic';
        this.items = items || [];
        this.attrs = {};
        this.dataValues = {};
        this.classes = [];
        this.children = [];
        this.textValue = '';
        this.length = this.items.length || 1;
        created.push(this);
    }
    attr(name, value) {
        if (typeof name === 'object') {
            Object.assign(this.attrs, name);
            return this;
        }
        if (value === undefined) return this.attrs[name];
        if (value === null) delete this.attrs[name]; else this.attrs[name] = String(value);
        return this;
    }
    removeAttr(name) { delete this.attrs[name]; return this; }
    data(name, value) {
        if (value === undefined) return this.dataValues[name];
        this.dataValues[name] = value;
        return this;
    }
    removeData(name) { delete this.dataValues[name]; return this; }
    addClass(value) { this.classes.push(...String(value || '').split(/\s+/).filter(Boolean)); return this; }
    removeClass() { return this; }
    toggleClass(value, enabled) { if (enabled) this.addClass(value); return this; }
    prop() { return this; }
    empty() { this.children = []; this.textValue = ''; return this; }
    append(...values) { this.children.push(...values); return this; }
    appendTo(target) { target.append(this); return this; }
    text(value) { if (value === undefined) return this.textValue; this.textValue = String(value); return this; }
    first() { return this; }
    find(selector) {
        if (this.kind === 'card') {
            if (selector === '.calendar-days') return days;
            if (selector === '.calendar-month-label') return monthLabel;
        }
        return new FakeQuery('empty', []);
    }
    closest(selector) {
        if (selector === '[data-dashboard-widget-type="calendar"]') return card;
        return new FakeQuery('empty', []);
    }
    each(callback) {
        this.items.forEach((item, index) => callback.call(item, index, item));
        return this;
    }
    off() { return this; }
    on() { return this; }
    trigger(name, args) { this.dataValues['last-trigger'] = {name, args}; return this; }
    val() { return ''; }
}

const days = new FakeQuery('days');
const monthLabel = new FakeQuery('label');
const cardElement = {fakeKind: 'card-element'};
const card = new FakeQuery('card', [cardElement]);
cardElement.query = card;
card.attr('data-dashboard-widget-id', '10');
const documentObject = {fakeKind: 'document'};

function $(value) {
    if (typeof value === 'function') {
        value();
        return new FakeQuery('ready');
    }
    if (value === documentObject) return new FakeQuery('document');
    if (value === cardElement) return card;
    if (typeof value === 'string' && value.startsWith('<')) return new FakeQuery(value);
    if (value === '[data-dashboard-widget-type="calendar"]') return new FakeQuery('cards', [cardElement]);
    return value && value.query ? value.query : new FakeQuery('generic');
}

$.extend = (...values) => Object.assign({}, ...values);
$.ajax = function (settings) {
    const callbacks = {done: [], fail: [], always: []};
    const request = {
        settings,
        done(fn) { callbacks.done.push(fn); return request; },
        fail(fn) { callbacks.fail.push(fn); return request; },
        always(fn) { callbacks.always.push(fn); return request; },
        abort() { return request; },
        resolve(response) {
            callbacks.done.forEach(fn => fn(response));
            callbacks.always.forEach(fn => fn(response, 'success', {getResponseHeader: () => null}));
        }
    };
    requests.push(request);
    return request;
};

const windowObject = {
    clearTimeout() {},
    setTimeout() { return 1; },
    confirm() { return true; },
    location: {reload() {}}
};
const context = {
    jQuery: $,
    $,
    window: windowObject,
    document: documentObject,
    Date,
    Number,
    String,
    Array,
    Object,
    Error,
    Math,
    URLSearchParams,
    console
};

vm.runInNewContext(layoutSource, context, {filename: 'calendar-month-layout.js'});
vm.runInNewContext(coreSource, context, {filename: 'calendar-core.js'});
check(requests.length === 1, 'Calendar starts one range request with the F placement module loaded');

const rangeStart = requests[0].settings.data.calendar_range_start;
const rangeEnd = requests[0].settings.data.calendar_range_end;
const year = Number(rangeStart.slice(0, 4));
const month = Number(rangeStart.slice(5, 7));
let monday = 1;
while (new Date(year, month - 1, monday).getDay() !== 1) monday += 1;
function iso(day) { return rangeStart.slice(0, 8) + String(day).padStart(2, '0'); }
const start = iso(monday);
const middle = iso(monday + 1);
const end = iso(monday + 2);
const overlapEnd = iso(monday + 3);

requests[0].resolve({
    ok: true,
    data: {
        range_start: rangeStart,
        range_end: rangeEnd,
        holidays: {},
        tasks: [{task_id: 91, title: 'Task', due_date: middle, priority: 'normal', completed: false}],
        events: [
            {
                event_id: 41,
                occurrence_key: 'event:41:' + start,
                original_occurrence_start_date: start,
                title: '<b>三日出張</b>',
                note: '<script>note</script>',
                color: 'purple',
                occurrence_start_date: start,
                occurrence_end_date: end,
                source_start_date: start,
                source_end_date: end,
                all_day: true,
                repeat_type: 'weekly'
            },
            {
                event_id: 42,
                occurrence_key: 'event:42:' + middle,
                original_occurrence_start_date: middle,
                title: '重複予定',
                color: 'yellow',
                occurrence_start_date: middle,
                occurrence_end_date: overlapEnd,
                source_start_date: middle,
                source_end_date: overlapEnd,
                all_day: true,
                repeat_type: 'none',
                exception_kind: 'override',
                exception_id: 7,
                occurrence_revision: 'a'.repeat(64)
            }
        ]
    }
});

const firstSpan = created.filter(item => item.classes.includes('calendar-event-entry') && item.attrs['data-event-id'] === '41');
const overlapSpan = created.filter(item => item.classes.includes('calendar-event-entry') && item.attrs['data-event-id'] === '42');
check(days.classes.includes('calendar-month-layout-ready'), 'month grid is marked ready for connected styling');
check(firstSpan.length === 3, 'three-day occurrence renders exactly three daily segments');
check(firstSpan.map(item => item.attrs['data-calendar-span-position']).join(',') === 'start,middle,end', 'daily DOM segments receive start middle and end roles');
check(new Set(firstSpan.map(item => item.attrs['data-calendar-lane'])).size === 1, 'all segments of one weekly span keep the same lane');
check(firstSpan[1].classes.includes('calendar-event-span-continuation'), 'middle segment hides duplicate visible content');
check(firstSpan.every(item => item.attrs['data-calendar-occurrence-key'] === 'event:41:' + start), 'every segment retains the occurrence identity used by E editing');
check(firstSpan.every(item => item.attrs['aria-label'].includes('複数日予定')), 'every segment keeps an accessible multi-day label');
check(firstSpan[0].children.some(child => child.textValue === '<b>三日出張</b>'), 'HTML-like title is appended as text in the visible segment');
check(overlapSpan.length === 3 && new Set(overlapSpan.map(item => item.attrs['data-calendar-lane'])).size === 1, 'overlapping override also keeps one stable lane');
check(overlapSpan.every(item => item.attrs['data-calendar-lane'] !== firstSpan[0].attrs['data-calendar-lane']), 'overlapping spans are placed on different lanes');
check(overlapSpan.every(item => item.classes.includes('calendar-event-exception')), 'override styling remains on every connected segment');
check(created.some(item => item.classes.includes('calendar-task-entry') && item.attrs['data-task-id'] === '91'), 'Task remains rendered beside connected events');
check(created.some(item => item.classes.includes('calendar-entry-placeholder')), 'empty lane gaps are represented by invisible placeholders');
check(card.data('last-trigger').name === 'calendar:rangeLoaded', 'connected render still publishes the existing range-loaded event');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
