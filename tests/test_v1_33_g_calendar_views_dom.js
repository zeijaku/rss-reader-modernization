'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const monthSource = fs.readFileSync(path.join(root, 'public/js/calendar-month-layout.js'), 'utf8');
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

const created = [];
const requests = [];
const handlers = {};

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
            if (selector === '.calendar-month-label') return periodLabel;
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
    on(eventName, selector, callback) {
        if (typeof selector === 'string') handlers[selector] = callback;
        return this;
    }
    trigger(name, args) { this.dataValues['last-trigger'] = {name, args}; return this; }
    val() { return ''; }
}

const days = new FakeQuery('days');
const periodLabel = new FakeQuery('label');
const weekdays = new FakeQuery('weekdays');
const cardElement = {query: null, getBoundingClientRect: () => ({width: 540})};
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
        aborted: false,
        done(fn) { callbacks.done.push(fn); return request; },
        fail(fn) { callbacks.fail.push(fn); return request; },
        always(fn) { callbacks.always.push(fn); return request; },
        abort() { request.aborted = true; return request; },
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
    jQuery: $, $, window: windowObject, document: documentObject,
    Date, Number, String, Array, Object, Error, Math, URLSearchParams, console
};

vm.runInNewContext(monthSource, context, {filename: 'calendar-month-layout.js'});
vm.runInNewContext(viewsSource, context, {filename: 'calendar-views.js'});
vm.runInNewContext(coreSource, context, {filename: 'calendar-core.js'});

check(requests.length === 1 && requests[0].settings.data.calendar_range_start.endsWith('-01'),
    'startup remains one month-range request');
check(card.classes.includes('calendar-view-compact'), 'actual narrow card width enables compact behavior on desktop');
check(typeof handlers['.calendar-view-mode'] === 'function', 'day/week/month switch handler is delegated');

const initialAnchor = card.attr('data-calendar-selected-date');
const dayButtonElement = {query: new FakeQuery('day-button')};
dayButtonElement.query.attr('data-calendar-view-mode', 'day');
dayButtonElement.query.closest = () => card;
handlers['.calendar-view-mode'].call(dayButtonElement);
check(requests.length === 2 && requests[0].aborted === true, 'mode switch aborts the obsolete month request');
check(requests[1].settings.data.calendar_range_start === initialAnchor
    && requests[1].settings.data.calendar_range_end === initialAnchor, 'day mode requests only the selected date');

requests[1].resolve({
    ok: true,
    data: {
        range_start: initialAnchor,
        range_end: initialAnchor,
        holidays: {[initialAnchor]: '確認日'},
        tasks: [{task_id: 20, title: 'Task', due_date: initialAnchor, priority: 'normal', completed: false}],
        cancelled_occurrences: [],
        events: [
            {event_id: 31, occurrence_key: 'event:31:' + initialAnchor, original_occurrence_start_date: initialAnchor,
                title: '<b>朝会</b>', note: '<script>x</script>', color: 'purple', all_day: false,
                start_time: '09:00', end_time: '10:00', occurrence_start_date: initialAnchor,
                occurrence_end_date: initialAnchor, source_start_date: initialAnchor, source_end_date: initialAnchor,
                repeat_type: 'weekly'},
            {event_id: 32, occurrence_key: 'event:32:' + initialAnchor, original_occurrence_start_date: initialAnchor,
                title: '重複', color: 'yellow', all_day: false, start_time: '09:30', end_time: null,
                occurrence_start_date: initialAnchor, occurrence_end_date: initialAnchor,
                source_start_date: initialAnchor, source_end_date: initialAnchor, repeat_type: 'none'}
        ]
    }
});

check(card.attr('data-calendar-view') === 'day' && days.classes.includes('calendar-day-view'), 'day response activates day view');
check(periodLabel.textValue.includes('年') && periodLabel.textValue.includes('日（'),
    'day toolbar label includes date and weekday: ' + periodLabel.textValue);
check(created.filter(item => item.classes.includes('calendar-time-row')).length === 24, 'day view renders all 24 hours');
const timelineEntries = created.filter(item => item.classes.includes('calendar-timeline-entry'));
check(timelineEntries.length === 2, 'single-day timed events render in the timeline');
check(timelineEntries.every(item => String(item.attrs.style || '').includes('--calendar-entry-left:')),
    'overlap layout is transferred to bounded inline CSS variables');
check(timelineEntries.some(item => item.classes.includes('calendar-timeline-entry-open-ended')),
    'missing end time receives explicit start-only styling');
check(timelineEntries.some(item => item.children.some(child => child.textValue === '<b>朝会</b>')),
    'HTML-like day title remains text content');
check(created.some(item => item.classes.includes('calendar-task-entry') && item.attrs['data-task-id'] === '20'),
    'Task remains visible in day view');

const weekButtonElement = {query: new FakeQuery('week-button')};
weekButtonElement.query.attr('data-calendar-view-mode', 'week');
weekButtonElement.query.closest = () => card;
handlers['.calendar-view-mode'].call(weekButtonElement);
const weekRequest = requests[2];
check(weekRequest.settings.data.calendar_range_start <= initialAnchor
    && weekRequest.settings.data.calendar_range_end >= initialAnchor, 'week request contains the selected date');
const weekStart = weekRequest.settings.data.calendar_range_start;
const weekDates = windowObject.iGuguruCalendarViews.dates(weekStart, weekRequest.settings.data.calendar_range_end);
weekRequest.resolve({
    ok: true,
    data: {
        range_start: weekStart,
        range_end: weekRequest.settings.data.calendar_range_end,
        holidays: {}, tasks: [], cancelled_occurrences: [],
        events: [{event_id: 40, occurrence_key: 'event:40:' + weekDates[1], original_occurrence_start_date: weekDates[1],
            title: '三日出張', color: 'blue', all_day: true, occurrence_start_date: weekDates[1],
            occurrence_end_date: weekDates[3], source_start_date: weekDates[1], source_end_date: weekDates[3],
            repeat_type: 'none'}]
    }
});

check(card.attr('data-calendar-view') === 'week' && days.classes.includes('calendar-week-view'), 'week response activates week view');
check(created.filter(item => item.classes.includes('calendar-week-day')).length === weekDates.length,
    'week view renders every supported date including clipped boundary weeks');
const weekSpan = created.filter(item => item.classes.includes('calendar-event-entry') && item.attrs['data-event-id'] === '40');
check(weekSpan.length === 3 && weekSpan.map(item => item.attrs['data-calendar-span-position']).join(',') === 'start,middle,end',
    'wide week view reuses connected multi-day start/middle/end segments');
check(card.data('last-trigger').name === 'calendar:rangeLoaded', 'each mode keeps the existing scoped range-loaded contract');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
