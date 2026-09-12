'use strict';

const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync('public/js/calendar-copy.js', 'utf8');
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        console.log('PASS: ' + message);
    } else {
        failed += 1;
        console.log('FAIL: ' + message);
    }
}

function jqueryStub() {
    return {
        trigger: function () { return this; },
        one: function () { return this; }
    };
}

const documentStub = {
    addEventListener: function () {},
    getElementById: function () { return null; }
};
const windowStub = {jQuery: jqueryStub};
const context = {
    window: windowStub,
    document: documentStub,
    console: console,
    setTimeout: function (callback) { callback(); }
};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'calendar-copy.js'});
const api = windowStub.IguguruCalendarCopy;
check(api && typeof api.snapshotChangeForm === 'function' && typeof api.applySnapshot === 'function',
    'Calendar copy exposes testable snapshot/apply helpers');

function input(value, checked) {
    return {value: value === undefined ? '' : String(value), checked: checked === true};
}

function formWith(map, attrs) {
    attrs = attrs || {};
    return {
        querySelector: function (selector) { return Object.prototype.hasOwnProperty.call(map, selector) ? map[selector] : null; },
        getAttribute: function (name) { return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null; },
        setAttribute: function (name, value) { attrs[name] = String(value); },
        attrs: attrs
    };
}

const normalMap = {
    '.changeCalendarEventTitleValue': input('会議'),
    '.changeCalendarEventStartDate': input('2026-09-15'),
    '.changeCalendarEventEndDate': input('2026-09-16'),
    '.changeCalendarEventNote': input('議事録を準備'),
    '.changeCalendarEventColor': input('purple'),
    '.changeCalendarEventAllDay': input('', false),
    '.changeCalendarEventStartTime': input('09:30'),
    '.changeCalendarEventEndTime': input('10:45'),
    '.changeCalendarEventUrl': input('https://example.com/meeting'),
    '.changeCalendarEventRepeatType': input('weekly'),
    '.changeCalendarEventRepeatUntil': input('2026-12-31'),
    '.calendarOccurrenceScope:checked': null
};
const normal = api.snapshotChangeForm(formWith(normalMap, {}));
check(normal.title === '会議' && normal.start === '2026-09-15' && normal.end === '2026-09-16',
    'normal copy preserves title and multi-day range');
check(normal.note === '議事録を準備' && normal.color === 'purple' && normal.url === 'https://example.com/meeting',
    'normal copy preserves note, color and URL');
check(normal.allDay === false && normal.startTime === '09:30' && normal.endTime === '10:45',
    'normal copy preserves timed-event settings');
check(normal.repeat === 'weekly' && normal.repeatUntil === '2026-12-31',
    'series copy preserves recurrence settings');

const occurrenceMap = Object.assign({}, normalMap, {
    '.changeCalendarEventStartDate': input('2026-09-22'),
    '.changeCalendarEventEndDate': input('2026-09-22'),
    '.calendarOccurrenceScope:checked': {value: 'occurrence'}
});
const occurrence = api.snapshotChangeForm(formWith(occurrenceMap, {'data-calendar-occurrence-active': '1'}));
check(occurrence.occurrenceOnly === true && occurrence.repeat === 'none' && occurrence.repeatUntil === '',
    'occurrence-only copy strips recurrence identity and becomes standalone');
check(occurrence.start === '2026-09-22' && occurrence.end === '2026-09-22',
    'occurrence-only copy uses the currently displayed occurrence dates');

const seriesMap = Object.assign({}, normalMap, {
    '.calendarOccurrenceScope:checked': {value: 'series'}
});
const series = api.snapshotChangeForm(formWith(seriesMap, {'data-calendar-occurrence-active': '0'}));
check(series.occurrenceOnly === false && series.repeat === 'weekly' && series.repeatUntil === '2026-12-31',
    'series scope copy keeps the recurring series definition');

const registerMap = {
    '.registerCalendarEventTitleValue': input(''),
    '.registerCalendarEventStartDate': input(''),
    '.registerCalendarEventEndDate': input(''),
    '.registerCalendarEventNote': input(''),
    '.registerCalendarEventColor': input('blue'),
    '.registerCalendarEventAllDay': input('', true),
    '.registerCalendarEventStartTime': input(''),
    '.registerCalendarEventEndTime': input(''),
    '.registerCalendarEventUrl': input(''),
    '.registerCalendarEventRepeatType': input('none'),
    '.registerCalendarEventRepeatUntil': input('')
};
const register = formWith(registerMap, {});
check(api.applySnapshot(register, normal) === true, 'snapshot applies to the existing register form');
check(registerMap['.registerCalendarEventTitleValue'].value === '会議'
      && registerMap['.registerCalendarEventStartDate'].value === '2026-09-15'
      && registerMap['.registerCalendarEventEndDate'].value === '2026-09-16',
    'register form receives title and dates');
check(registerMap['.registerCalendarEventAllDay'].checked === false
      && registerMap['.registerCalendarEventStartTime'].value === '09:30'
      && registerMap['.registerCalendarEventEndTime'].value === '10:45',
    'register form receives timed-event state');
check(registerMap['.registerCalendarEventRepeatType'].value === 'weekly'
      && registerMap['.registerCalendarEventRepeatUntil'].value === '2026-12-31',
    'register form receives recurrence state');
check(register.attrs['data-calendar-copy-source'] === 'event', 'copy origin marker contains no event id or user data');

console.log('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0');
process.exit(failed === 0 ? 0 : 1);
