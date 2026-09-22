'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/calendar-event-details.js'), 'utf8');
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) { passed += 1; process.stdout.write('PASS: ' + message + '\n'); }
    else { failed += 1; process.stderr.write('FAIL: ' + message + '\n'); }
}

function run(formId) {
    const isChange = formId === 'changeCalendarEventForm';
    const listeners = {};
    const triggered = [];
    const ajaxCalls = [];
    const notice = {textValue: ''};
    const submitButton = {disabled: false};
    const modal = {};
    let hideCount = 0;
    let reloadCount = 0;

    const fields = {
        '.calendar-event-detail-fields': {},
        [isChange ? '.changeCalendarEventTitleValue' : '.registerCalendarEventTitleValue']: {value: '予定'},
        [isChange ? '.changeCalendarEventStartDate' : '.registerCalendarEventStartDate']: {value: '2026-09-22'},
        [isChange ? '.changeCalendarEventEndDate' : '.registerCalendarEventEndDate']: {value: '2026-09-22'},
        [isChange ? '.changeCalendarEventNote' : '.registerCalendarEventNote']: {value: 'メモ'},
        [isChange ? '.changeCalendarEventColor' : '.registerCalendarEventColor']: {value: 'green'},
        [isChange ? '.changeCalendarEventAllDay' : '.registerCalendarEventAllDay']: {checked: true},
        [isChange ? '.changeCalendarEventStartTime' : '.registerCalendarEventStartTime']: {value: ''},
        [isChange ? '.changeCalendarEventEndTime' : '.registerCalendarEventEndTime']: {value: ''},
        [isChange ? '.changeCalendarEventUrl' : '.registerCalendarEventUrl']: {value: ''},
        '.changeCalendarEventId': {value: '42'},
        'button[type="submit"]': submitButton
    };
    const form = {
        id: formId,
        querySelector(selector) { return fields[selector] || null; },
        reportValidity() { return true; },
        closest(selector) { return selector === '.modal' ? modal : null; }
    };
    const otherForm = {
        querySelector(selector) { return selector === '.calendar-event-detail-fields' ? {} : null; }
    };
    const documentObject = {
        addEventListener(name, handler) { listeners[name] = handler; },
        getElementById(id) {
            if (id === formId) return form;
            if (id === 'registerCalendarEventForm' || id === 'changeCalendarEventForm') return otherForm;
            return null;
        },
        createElement() { return {}; }
    };

    class FakeQuery {
        constructor(value) { this.value = value; this.length = value === '#app-notice' ? 1 : 0; }
        off() { return this; }
        on() { return this; }
        first() { return this; }
        attr() { return this; }
        prop() { return this; }
        removeClass() { return this; }
        addClass() { return this; }
        trigger(name) {
            if (this.value === documentObject) triggered.push(name);
            return this;
        }
        text(value) {
            if (value !== undefined && this.value === '#app-notice') notice.textValue = String(value);
            return value === undefined ? notice.textValue : this;
        }
    }
    function $(value) {
        if (typeof value === 'function') { value(); return new FakeQuery(null); }
        return new FakeQuery(value);
    }
    $.extend = Object.assign;
    $.Deferred = () => ({reject() { return {promise() { return {}; }}; }});
    $.ajax = options => {
        ajaxCalls.push(options);
        const xhr = {getResponseHeader() { return ''; }};
        return {
            done(handler) { handler({ok: true, data: {event_id: 42}}); return this; },
            fail() { return this; },
            always(handler) { handler(xhr, 'success', xhr); return this; }
        };
    };

    const windowObject = {
        location: {reload() { reloadCount += 1; }},
        bootstrap: {Modal: {getInstance(value) {
            return value === modal ? {hide() { hideCount += 1; }} : null;
        }}},
        matchMedia() { return {matches: false}; }
    };
    vm.runInNewContext(source, {
        jQuery: $, window: windowObject, document: documentObject,
        URLSearchParams, String, Number, Array, Object, Error, console
    }, {filename: 'calendar-event-details.js'});

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

const created = run('registerCalendarEventForm');
check(created.event.prevented && created.event.stopped, 'event create remains a single intercepted Ajax transaction');
check(created.ajaxCalls.length === 1 && created.ajaxCalls[0].data.action === 'calendar.color.create',
    'event create keeps the detailed Calendar API action');
check(created.reloadCount === 0, 'event create does not reload the page');
check(created.hideCount === 1, 'event create closes its modal');
check(created.triggered.join(',') === 'calendar:occurrenceChanged',
    'event create requests Calendar-card and upcoming refresh');
check(created.notice.textValue === '予定を追加しました', 'event create shows a success notice');
check(created.submitButton.disabled === false, 'event create restores the submit button');

const changed = run('changeCalendarEventForm');
check(changed.ajaxCalls.length === 1 && changed.ajaxCalls[0].data.action === 'calendar.color.update',
    'ordinary or series update keeps the detailed Calendar API action');
check(changed.ajaxCalls[0].data.event_id === '42', 'event update keeps the selected event identity');
check(changed.reloadCount === 0, 'event update does not reload the page');
check(changed.hideCount === 1, 'event update closes its modal');
check(changed.triggered.join(',') === 'calendar:occurrenceChanged',
    'event update requests Calendar-card and upcoming refresh');
check(changed.notice.textValue === '予定を変更しました', 'event update shows a success notice');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
