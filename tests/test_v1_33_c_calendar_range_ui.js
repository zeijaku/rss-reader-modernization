'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/calendar-core.js'), 'utf8');
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
const handlers = {};
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
            return new FakeQuery('empty', []);
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
    on(eventName, selector, callback) {
        if (typeof selector === 'function') callback = selector;
        else handlers[selector] = callback;
        return this;
    }
    trigger(name, args) { this.dataValues['last-trigger'] = {name, args}; return this; }
    val() { return ''; }
}

const days = new FakeQuery('days');
const monthLabel = new FakeQuery('label');
const cardElement = {fakeKind: 'card-element'};
const card = new FakeQuery('card', [cardElement]);
cardElement.query = card;
card.attr('data-dashboard-widget-id', '10');
const nextButtonElement = {fakeKind: 'next', query: new FakeQuery('next')};
nextButtonElement.query.closest = () => card;
const documentObject = {fakeKind: 'document'};

function $(value) {
    if (typeof value === 'function') {
        value();
        return new FakeQuery('ready');
    }
    if (value === documentObject) return new FakeQuery('document');
    if (value === cardElement) return card;
    if (value === nextButtonElement) return nextButtonElement.query;
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
        abort() { request.aborted = true; callbacks.fail.forEach(fn => fn({}, 'abort')); return request; },
        resolve(response) {
            callbacks.done.forEach(fn => fn(response));
            const xhr = {getResponseHeader: () => null};
            callbacks.always.forEach(fn => fn(response, 'success', xhr));
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

vm.runInNewContext(source, {
    jQuery: $,
    $,
    window: windowObject,
    document: documentObject,
    Date,
    Number,
    String,
    Array,
    Object,
    Math,
    URLSearchParams,
    console
}, {filename: 'calendar-core.js'});

check(requests.length === 1, 'Calendar startup sends one unified request');
check(requests[0].settings.url === './calendar_recurrence_api.php', 'unified range uses the existing secured Calendar endpoint');
check(requests[0].settings.data.action === 'calendar.range.list', 'Calendar startup requests the range action');
check(/^\d{4}-\d{2}-01$/.test(requests[0].settings.data.calendar_range_start), 'request contains explicit month start');
check(/^\d{4}-\d{2}-\d{2}$/.test(requests[0].settings.data.calendar_range_end), 'request contains explicit month end');
check(requests[0].settings.data.csrf_token === '', 'range request carries the shared CSRF field');

check(typeof handlers['.calendar-next-month'] === 'function', 'next-month handler remains bound');
handlers['.calendar-next-month'].call(nextButtonElement);
check(requests.length === 2, 'month navigation sends one replacement range request');
check(requests[0].aborted === true, 'month navigation aborts the previous request');

const targetStart = requests[1].settings.data.calendar_range_start;
const targetYear = Number(targetStart.slice(0, 4));
const targetMonth = Number(targetStart.slice(5, 7));
const occurrenceStart = targetStart.slice(0, 8) + '10';
requests[1].resolve({
    ok: true,
    data: {
        range_start: targetStart,
        range_end: requests[1].settings.data.calendar_range_end,
        range_days: 30,
        holidays: {},
        tasks: [],
        events: [{
            kind: 'event', event_id: 5, occurrence_key: 'event:5:' + occurrenceStart,
            original_occurrence_start_date: occurrenceStart,
            title: '<b>予定</b>', note: '<script>x</script>', color: 'purple',
            occurrence_start_date: occurrenceStart, occurrence_end_date: occurrenceStart,
            source_start_date: targetStart, source_end_date: targetStart,
            all_day: false, start_time: '09:00', end_time: '10:00', url: 'https://example.com',
            repeat_type: 'weekly', repeat_until: null
        }]
    }
});

check(monthLabel.textValue === targetYear + '年' + targetMonth + '月', 'newest response renders the selected month');
check(card.attr('data-calendar-range-ready') === '1', 'successful render marks unified range ready');
check(card.attr('data-calendar-recurrence-ready') === '1', 'successful range also marks recurrence metadata ready');
check(card.data('last-trigger').name === 'calendar:rangeLoaded', 'successful render publishes the scoped range event');

const eventButton = created.find(item => item.classes.includes('calendar-event-entry') && item.attrs['data-event-id'] === '5');
check(Boolean(eventButton), 'unified event is rendered as a Calendar entry');
check(eventButton && eventButton.attrs['data-calendar-occurrence-key'] === 'event:5:' + occurrenceStart, 'rendered entry keeps stable occurrence key');
check(eventButton && eventButton.attrs['data-calendar-original-occurrence-start-date'] === occurrenceStart, 'rendered entry keeps original occurrence date');
check(eventButton && eventButton.classes.includes('calendar-event-color-purple'), 'rendered entry receives its five-color class immediately');
check(eventButton && eventButton.attrs['data-calendar-event-meta-ready'] === '1', 'rendered entry carries ready time and URL metadata');
check(eventButton && eventButton.attrs['data-calendar-event-repeat-type'] === 'weekly', 'rendered entry carries recurrence metadata');
check(eventButton && eventButton.children.some(child => child.textValue === '<b>予定</b>'), 'event title is assigned through text rather than HTML');
check(!source.includes('.html(item.title') && !source.includes('.html(item.note'), 'Calendar core contains no title/note HTML sink');

const renderedLabel = monthLabel.textValue;
requests[0].resolve({ok: true, data: {range_start: '2000-01-01', range_end: '2000-01-31', events: [], tasks: [], holidays: {}}});
check(monthLabel.textValue === renderedLabel, 'late stale response cannot overwrite the current month');
check(requests.length === 2, 'rendering unified data does not start color/time/recurrence overlay requests');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
