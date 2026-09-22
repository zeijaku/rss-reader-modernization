'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/calendar-recurrence.js'), 'utf8');
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) { passed += 1; process.stdout.write('PASS: ' + message + '\n'); }
    else { failed += 1; process.stderr.write('FAIL: ' + message + '\n'); }
}

function run(formId, repeatType) {
    const isChange = formId === 'changeCalendarEventForm';
    const listeners = {};
    const ajaxCalls = [];
    const triggered = [];
    const submitButton = {disabled: false};
    const modal = {};
    const notice = {textValue: ''};
    let hideCount = 0;
    let reloadCount = 0;
    const prefix = isChange ? 'change' : 'register';
    const fields = {
        ['.' + prefix + 'CalendarEventTitleValue']: {value: '確認予定'},
        ['.' + prefix + 'CalendarEventStartDate']: {value: '2026-09-22'},
        ['.' + prefix + 'CalendarEventEndDate']: {value: '2026-09-22'},
        ['.' + prefix + 'CalendarEventNote']: {value: 'メモ'},
        ['.' + prefix + 'CalendarEventColor']: {value: 'blue'},
        ['.' + prefix + 'CalendarEventAllDay']: {checked: true},
        ['.' + prefix + 'CalendarEventStartTime']: {value: ''},
        ['.' + prefix + 'CalendarEventEndTime']: {value: ''},
        ['.' + prefix + 'CalendarEventUrl']: {value: ''},
        ['.' + prefix + 'CalendarEventRepeatType']: {value: repeatType},
        ['.' + prefix + 'CalendarEventRepeatUntil']: {value: repeatType === 'none' ? '' : '2026-12-31'},
        '.changeCalendarEventId': {value: '73'},
        'button[type="submit"]': submitButton
    };
    const form = {
        id: formId,
        querySelector(selector) { return fields[selector] || null; },
        reportValidity() { return true; },
        getAttribute(name) { return name === 'data-calendar-recurrence-submit-ready' ? '1' : null; },
        setAttribute() {},
        closest(selector) { return selector === '.modal' ? modal : null; }
    };
    const documentObject = {
        addEventListener(name, handler) { listeners[name] = handler; },
        getElementById() { return null; },
        createElement() { return {}; }
    };

    class FakeQuery {
        constructor(value) { this.value = value; this.length = value === '#app-notice' ? 1 : 0; }
        off() { return this; }
        on() { return this; }
        first() { return this; }
        val() { return this; }
        data() { return this; }
        removeClass() { return this; }
        addClass() { return this; }
        prop() { return this; }
        attr(name, value) {
            if (value === undefined && this.value === 'meta[name="csrf-token"]' && name === 'content') {
                return 'c'.repeat(64);
            }
            return this;
        }
        text(value) {
            if (value !== undefined && this.value === '#app-notice') notice.textValue = String(value);
            return value === undefined ? notice.textValue : this;
        }
        trigger(name) {
            if (this.value === documentObject) triggered.push(name);
            return this;
        }
    }
    function $(value) {
        if (typeof value === 'function') return new FakeQuery(null);
        return new FakeQuery(value);
    }
    $.extend = (...values) => Object.assign({}, ...values);
    $.ajax = options => {
        ajaxCalls.push(options);
        const xhr = {getResponseHeader() { return ''; }};
        return {
            done(handler) { handler({ok: true, data: {event_id: 73}}); return this; },
            fail() { return this; },
            always(handler) { handler(xhr, 'success', xhr); return this; }
        };
    };

    const windowObject = {
        setTimeout() {},
        location: {reload() { reloadCount += 1; }},
        bootstrap: {Modal: {getInstance(value) {
            return value === modal ? {hide() { hideCount += 1; }} : null;
        }}}
    };
    vm.runInNewContext(source, {
        jQuery: $, window: windowObject, document: documentObject,
        URLSearchParams, String, Number, Array, Object, Math, Date, console
    }, {filename: 'calendar-recurrence.js'});

    const event = {
        target: form,
        prevented: false,
        stopped: false,
        preventDefault() { this.prevented = true; },
        stopImmediatePropagation() { this.stopped = true; }
    };
    listeners.submit(event);
    return {ajaxCalls, event, hideCount, reloadCount, triggered, notice, submitButton};
}

const created = run('registerCalendarEventForm', 'none');
check(created.event.prevented && created.event.stopped, 'ordinary event create uses the production recurrence submit path');
check(created.ajaxCalls.length === 1 && created.ajaxCalls[0].data.action === 'calendar.recurrence.create',
    'ordinary event create keeps its existing API action');
check(created.reloadCount === 0, 'ordinary event create does not reload the page');
check(created.hideCount === 1, 'ordinary event create closes its modal');
check(created.triggered.join(',') === 'calendar:occurrenceChanged',
    'ordinary event create refreshes Calendar projections');
check(created.notice.textValue === '予定を追加しました', 'ordinary event create shows a success notice');
check(created.submitButton.disabled === false, 'ordinary event create restores the submit button');

const changed = run('changeCalendarEventForm', 'weekly');
check(changed.ajaxCalls.length === 1 && changed.ajaxCalls[0].data.action === 'calendar.recurrence.update',
    'series update keeps its existing API action');
check(changed.ajaxCalls[0].data.event_id === '73', 'series update keeps the selected event identity');
check(changed.ajaxCalls[0].data.calendar_event_repeat_type === 'weekly', 'series update keeps its recurrence setting');
check(changed.reloadCount === 0, 'series update does not reload the page');
check(changed.hideCount === 1, 'series update closes its modal');
check(changed.triggered.join(',') === 'calendar:occurrenceChanged',
    'series update refreshes Calendar projections');
check(changed.notice.textValue === '予定を変更しました', 'series update shows a success notice');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
