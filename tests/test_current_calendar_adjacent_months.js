'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const layoutSource = fs.readFileSync(path.join(root, 'public/js/calendar-month-layout.js'), 'utf8');
const viewsSource = fs.readFileSync(path.join(root, 'public/js/calendar-views.js'), 'utf8');
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

const pureWindow = {};
vm.runInNewContext(viewsSource, {window: pureWindow, Date, String, Number, Array, Object, Math});
const views = pureWindow.iGuguruCalendarViews;
const september = views.period('month', '2026-09-15');
const august = views.period('month', '2026-08-15');
const february = views.period('month', '2026-02-15');

check(september.start === '2026-08-30' && september.end === '2026-10-03',
    'month request includes the leading and trailing week dates');
check(views.dates(september.start, september.end).length === 35,
    'five-row month requests exactly 35 visible dates');
check(views.dates(august.start, august.end).length === 42,
    'six-row month remains within the 42-day server limit');
check(views.dates(february.start, february.end).length === 28,
    'four-row month does not fetch an unnecessary fifth or sixth row');
check(views.label('month', september) === '2026年9月',
    'month label follows the anchor month instead of the leading adjacent date');
check(views.period('month', '2000-01-15').start === '2000-01-01',
    'minimum supported month clips an unsupported leading week');
check(views.period('month', '2100-12-15').end === '2100-12-31',
    'maximum supported month clips an unsupported trailing week');

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
        if (typeof name === 'object') { Object.assign(this.attrs, name); return this; }
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
    removeClass(value) {
        const removed = String(value || '').split(/\s+/).filter(Boolean);
        this.classes = this.classes.filter(name => !removed.includes(name));
        return this;
    }
    toggleClass(value, enabled) {
        if (enabled) this.addClass(value); else this.removeClass(value);
        return this;
    }
    prop(name, value) { if (value !== undefined) this.attrs[name] = value; return this; }
    empty() { this.children = []; this.textValue = ''; return this; }
    append(...values) { this.children.push(...values); return this; }
    appendTo(target) { target.append(this); return this; }
    text(value) { if (value === undefined) return this.textValue; this.textValue = String(value); return this; }
    first() { return this; }
    get(index) { return this.items[index]; }
    find(selector) {
        if (this.kind === 'card') {
            if (selector === '.calendar-days') return days;
            if (selector === '.calendar-month-label') return monthLabel;
            if (selector === '.calendar-weekdays') return weekdays;
        }
        return new FakeQuery('empty', []);
    }
    closest(selector) {
        if (selector === '[data-dashboard-widget-type="calendar"]') return card;
        return new FakeQuery('empty', []);
    }
    each(callback) { this.items.forEach((item, index) => callback.call(item, index, item)); return this; }
    off() { return this; }
    on() { return this; }
    trigger(name, args) { this.dataValues['last-trigger'] = {name, args}; return this; }
    val() { return ''; }
}

const days = new FakeQuery('days');
const monthLabel = new FakeQuery('label');
const weekdays = new FakeQuery('weekdays');
const cardElement = {query: null, getBoundingClientRect: () => ({width: 700})};
const card = new FakeQuery('card', [cardElement]);
cardElement.query = card;
card.attr('data-dashboard-widget-id', '10');
const documentObject = {fakeKind: 'document'};

function $(value) {
    if (typeof value === 'function') { value(); return new FakeQuery('ready'); }
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

class FixedDate extends Date {
    constructor(...args) {
        super(...(args.length === 0 ? ['2026-09-15T12:00:00+09:00'] : args));
    }
    static UTC(...args) { return Date.UTC(...args); }
}

const windowObject = {
    clearTimeout() {}, setTimeout() { return 1; }, confirm() { return true; }, location: {reload() {}}
};
const context = {
    jQuery: $, $, window: windowObject, document: documentObject,
    Date: FixedDate, Number, String, Array, Object, Error, Math, URLSearchParams, console
};

vm.runInNewContext(layoutSource, context, {filename: 'calendar-month-layout.js'});
vm.runInNewContext(viewsSource, context, {filename: 'calendar-views.js'});
vm.runInNewContext(coreSource, context, {filename: 'calendar-core.js'});

check(requests.length === 1, 'Calendar starts one bounded range request');
check(requests[0].settings.data.calendar_range_start === '2026-08-30'
    && requests[0].settings.data.calendar_range_end === '2026-10-03',
    'month request sends the complete visible grid range');

requests[0].resolve({
    ok: true,
    data: {
        range_start: '2026-08-30', range_end: '2026-10-03',
        holidays: {'2026-10-01': '隣接月休日'},
        tasks: [{task_id: 91, title: '隣接月Task', due_date: '2026-10-02', priority: 'normal', completed: false}],
        cancelled_occurrences: [{event_id: 92, title: '取消予定', original_occurrence_start_date: '2026-08-30'}],
        events: [
            {event_id: 41, occurrence_key: 'event:41:2026-08-31', original_occurrence_start_date: '2026-08-31',
                title: '前月予定', color: 'purple', all_day: true, occurrence_start_date: '2026-08-31',
                occurrence_end_date: '2026-09-02', source_start_date: '2026-08-31', source_end_date: '2026-09-02',
                repeat_type: 'none'},
            {event_id: 42, occurrence_key: 'event:42:2026-10-03', original_occurrence_start_date: '2026-10-03',
                title: '次月予定', color: 'blue', all_day: true, occurrence_start_date: '2026-10-03',
                occurrence_end_date: '2026-10-03', source_start_date: '2026-10-03', source_end_date: '2026-10-03',
                repeat_type: 'none'}
        ]
    }
});

const cells = created.filter(item => item.classes.includes('calendar-day') && item.attrs['data-calendar-date']);
const outside = cells.filter(item => item.classes.includes('calendar-day-outside-month'));
const augustCell = cells.find(item => item.attrs['data-calendar-date'] === '2026-08-31');
const octoberCell = cells.find(item => item.attrs['data-calendar-date'] === '2026-10-03');

check(cells.length === 35, 'five-row month renders every visible date cell');
check(outside.length === 5, 'leading and trailing adjacent dates are marked as outside the anchor month');
check(outside.every(item => item.attrs['aria-hidden'] !== 'true' && item.attrs.role === 'gridcell'),
    'adjacent date cells remain visible and exposed as grid cells');
check(augustCell && augustCell.children.some(child => child.classes.includes('calendar-day-number') && child.textValue === '31'),
    'leading adjacent date has an interactive day number');
check(octoberCell && octoberCell.children.some(child => child.classes.includes('calendar-day-number') && child.textValue === '3'),
    'trailing adjacent date has an interactive day number');
check(created.some(item => item.classes.includes('calendar-event-entry') && item.attrs['data-event-id'] === '41'),
    'event spanning from the previous month is rendered');
check(created.some(item => item.classes.includes('calendar-event-entry') && item.attrs['data-event-id'] === '42'),
    'event in the next-month portion is rendered');
check(created.some(item => item.classes.includes('calendar-task-entry') && item.attrs['data-task-id'] === '91'),
    'Task in the next-month portion is rendered');
check(created.some(item => item.classes.includes('calendar-event-cancelled') && item.attrs['data-event-id'] === '92'),
    'cancelled occurrence in the previous-month portion remains restorable');
check(monthLabel.textValue === '2026年9月', 'toolbar label remains the selected anchor month');
check(card.data('last-trigger').args[0].range_start === '2026-08-30',
    'range-loaded integration event receives the expanded visible range');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
