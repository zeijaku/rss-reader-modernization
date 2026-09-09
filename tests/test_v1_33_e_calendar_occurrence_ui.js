'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/calendar-occurrence.js'), 'utf8');
let passed = 0;
let failed = 0;
function check(condition, message) {
    if (condition) { passed += 1; process.stdout.write('PASS: ' + message + '\n'); }
    else { failed += 1; process.stderr.write('FAIL: ' + message + '\n'); }
}

class Element {
    constructor(classes) {
        this.classes = new Set((classes || '').split(/\s+/).filter(Boolean));
        this.value = '';
        this.checked = false;
        this.hidden = false;
        this.disabled = false;
        this.attrs = {};
        this.textContent = '';
        this.parent = null;
    }
    getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; }
    setAttribute(name, value) { this.attrs[name] = String(value); }
    removeAttribute(name) { delete this.attrs[name]; }
    closest(selector) {
        if (selector === '.calendar-event-edit-trigger' && this.classes.has('calendar-event-edit-trigger')) return this;
        if (selector === '.restore_calendar_occurrence' && this.classes.has('restore_calendar_occurrence')) return this;
        if (selector === '.delete_calendar_event' && this.classes.has('delete_calendar_event')) return this;
        if (selector === '#changeCalendarEventForm') return this.form || null;
        if (selector === '.modal') return this.modal || (this.form && this.form.modal) || null;
        return null;
    }
}

const selectors = {};
function field(selector, classes) {
    const element = new Element(classes || selector.replace(/^\./, ''));
    selectors[selector] = element;
    return element;
}
const form = new Element();
form.id = 'changeCalendarEventForm';
form.reportValidity = () => true;
form.querySelector = selector => {
    if (selector === '.calendarOccurrenceScope:checked') return occurrenceRadio.checked ? occurrenceRadio : seriesRadio;
    return selectors[selector] || null;
};
form.querySelectorAll = selector => selector.includes('calendar-event-submit') ? [submit, remove, restore] : [];
form.closest = selector => selector === '.modal' ? modal : null;
const modal = new Element('modal');
form.modal = modal;

const scopeBox = field('.calendar-occurrence-scope');
const context = field('.calendar-occurrence-context');
const recurrence = field('.calendar-event-recurrence-fields');
const submit = field('.calendar-event-submit', 'calendar-event-submit');
const remove = field('.delete_calendar_event', 'delete_calendar_event');
const restore = field('.restore_calendar_occurrence', 'restore_calendar_occurrence');
const occurrenceRadio = field('.calendarOccurrenceScope[value="occurrence"]', 'calendarOccurrenceScope');
occurrenceRadio.value = 'occurrence'; occurrenceRadio.checked = true;
const seriesRadio = new Element('calendarOccurrenceScope'); seriesRadio.value = 'series';
[
    '.changeCalendarEventId', '.changeCalendarOccurrenceOriginalStartDate', '.changeCalendarOccurrenceRevision',
    '.changeCalendarEventTitleValue', '.changeCalendarEventStartDate', '.changeCalendarEventEndDate',
    '.changeCalendarEventNote', '.changeCalendarEventColor', '.changeCalendarEventStartTime',
    '.changeCalendarEventEndTime', '.changeCalendarEventUrl', '.changeCalendarEventRepeatType',
    '.changeCalendarEventRepeatUntil'
].forEach(selector => field(selector));
const allDay = field('.changeCalendarEventAllDay'); allDay.checked = true;
[submit, remove, restore].forEach(button => button.form = form);

const documentListeners = {click: [], submit: []};
const jqueryHandlers = {};
const documentObject = {
    getElementById(id) { return id === 'changeCalendarEventForm' ? form : null; },
    addEventListener(name, callback) { documentListeners[name].push(callback); }
};
const requests = [];
let documentRefreshes = 0;
const notice = new Element();

class Query {
    constructor(target) { this.target = target; this.length = target ? 1 : 0; }
    attr(name, value) { if (value === undefined) return this.target ? this.target.getAttribute(name) : undefined; this.target.setAttribute(name, value); return this; }
    removeClass() { return this; }
    addClass() { return this; }
    prop(name, value) { if (this.target) this.target[name] = value; return this; }
    text(value) { if (this.target) this.target.textContent = String(value); return this; }
    trigger(name) { if (this.target === documentObject && name === 'calendar:occurrenceChanged') documentRefreshes += 1; return this; }
    on(name, selector, callback) { jqueryHandlers[selector] = callback; return this; }
}
function $(target) {
    if (target === documentObject) return new Query(documentObject);
    if (target === '#app-notice') return new Query(notice);
    if (target === 'meta[name="csrf-token"]') {
        const meta = new Element(); meta.attrs.content = 'a'.repeat(64); return new Query(meta);
    }
    if (target instanceof Element) return new Query(target);
    return new Query(null);
}
$.extend = (...items) => Object.assign({}, ...items);
$.ajax = settings => {
    const callbacks = {done: [], fail: [], always: []};
    const request = {
        settings,
        done(fn) { callbacks.done.push(fn); return request; },
        fail(fn) { callbacks.fail.push(fn); return request; },
        always(fn) { callbacks.always.push(fn); return request; },
        resolve(data) { callbacks.done.forEach(fn => fn(data)); callbacks.always.forEach(fn => fn(data, 'success', {getResponseHeader: () => null})); },
        reject(xhr, status) { callbacks.fail.forEach(fn => fn(xhr, status)); callbacks.always.forEach(fn => fn(xhr, status)); }
    };
    requests.push(request);
    return request;
};

let hidden = 0;
const windowObject = {
    jQuery: $,
    setTimeout(fn) { fn(); return 1; },
    confirm() { return true; },
    bootstrap: {Modal: {getInstance() { return {hide() { hidden += 1; }}; }}}
};
vm.runInNewContext(source, {window: windowObject, document: documentObject, jQuery: $, $, String, Array, Object});

const trigger = new Element('calendar-event-edit-trigger');
Object.assign(trigger.attrs, {
    'data-event-id': '7', 'data-event-title': '個別会議', 'data-event-note': '個別メモ',
    'data-event-start-date': '2026-09-01', 'data-event-end-date': '2026-09-01',
    'data-calendar-occurrence-start-date': '2026-09-15', 'data-calendar-occurrence-end-date': '2026-09-15',
    'data-calendar-original-occurrence-start-date': '2026-09-08', 'data-calendar-occurrence-revision': 'b'.repeat(64),
    'data-calendar-exception-kind': 'override', 'data-calendar-event-repeat-type': 'weekly',
    'data-calendar-event-repeat-until': '2026-12-31', 'data-calendar-event-color': 'purple',
    'data-calendar-event-all-day': '0', 'data-calendar-event-start-time': '10:00',
    'data-calendar-event-end-time': '11:00', 'data-calendar-event-url': 'https://example.com/override',
    'data-calendar-source-title': '定例会議', 'data-calendar-source-note': '共通メモ',
    'data-calendar-source-color': 'blue', 'data-calendar-source-all-day': '1',
    'data-calendar-source-start-time': '', 'data-calendar-source-end-time': '', 'data-calendar-source-url': ''
});
function event(target) { return {target, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }}; }
documentListeners.click[0](event(trigger));
check(scopeBox.hidden === false, 'recurring entry reveals the scope selector');
check(occurrenceRadio.checked && selectors['.changeCalendarEventTitleValue'].value === '個別会議', 'this occurrence is the safe default with effective values');
check(selectors['.changeCalendarEventStartDate'].value === '2026-09-15', 'moved effective date is editable in occurrence scope');
check(context.textContent.includes('元の予定日: 2026-09-08'), 'moved occurrence shows its immutable original date');
check(recurrence.hidden === true && submit.textContent === 'この予定を変更', 'occurrence scope hides recurrence fields and labels submit precisely');
check(restore.hidden === false, 'existing override offers restore');

occurrenceRadio.checked = false; seriesRadio.checked = true;
jqueryHandlers['.calendarOccurrenceScope'].call(seriesRadio);
check(selectors['.changeCalendarEventTitleValue'].value === '定例会議', 'series scope restores parent title');
check(selectors['.changeCalendarEventStartDate'].value === '2026-09-01', 'series scope restores parent dates');
check(recurrence.hidden === false && submit.textContent === 'シリーズを変更', 'series scope reveals recurrence fields');
const seriesSubmit = event(form); documentListeners.submit[0](seriesSubmit);
check(!seriesSubmit.prevented && requests.length === 0, 'series submit is delegated to existing recurrence handler exactly once');

seriesRadio.checked = false; occurrenceRadio.checked = true;
jqueryHandlers['.calendarOccurrenceScope'].call(occurrenceRadio);
selectors['.changeCalendarEventTitleValue'].value = '<b>個別のみ</b>';
const individualSubmit = event(form); documentListeners.submit[0](individualSubmit);
check(individualSubmit.prevented && individualSubmit.stopped, 'individual submit claims the event before series handler');
check(requests[0].settings.data.action === 'calendar.occurrence.update', 'individual submit uses occurrence update action');
check(requests[0].settings.data.original_occurrence_start_date === '2026-09-08' && requests[0].settings.data.occurrence_revision === 'b'.repeat(64), 'individual submit carries stable identity and revision');
check(requests[0].settings.data.calendar_event_title === '<b>個別のみ</b>', 'HTML-like title remains request data');
documentListeners.submit[0](event(form));
check(requests.length === 1, 'aria-busy blocks duplicate individual submission');
requests[0].resolve({ok: true});
check(hidden === 1 && documentRefreshes === 1, 'successful individual update closes modal and refreshes Calendar views');

const cancelEvent = event(remove); documentListeners.click[0](cancelEvent);
check(cancelEvent.prevented && requests[1].settings.data.action === 'calendar.occurrence.cancel', 'this occurrence delete maps to cancellation');
requests[1].resolve({ok: true});
const restoreEvent = event(restore); documentListeners.click[0](restoreEvent);
check(restoreEvent.prevented && requests[2].settings.data.action === 'calendar.occurrence.restore', 'restore action maps to occurrence restore');
requests[2].reject({status: 409, responseJSON: {error: {message: '競合'}}}, 'error');
check(documentRefreshes === 3 && notice.textContent === '競合', '409 conflict is shown and refreshes stale Calendar state');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
