'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/calendar-usability.js'), 'utf8');
let passed = 0;
let failed = 0;
function check(condition, message) {
    if (condition) { passed += 1; process.stdout.write('PASS: ' + message + '\n'); }
    else { failed += 1; process.stderr.write('FAIL: ' + message + '\n'); }
}

class Input {
    constructor(className, value = '') {
        this.className = className;
        this.value = value;
        this.checked = false;
        this.min = '';
        this.form = null;
    }
    matches(selector) {
        return selector.split(',').some(part => {
            part = part.trim();
            return part.startsWith('.') && this.className.split(/\s+/).includes(part.slice(1));
        });
    }
    closest(selector) {
        return selector.includes('#registerCalendarEventForm') || selector.includes('#changeCalendarEventForm')
            ? this.form : null;
    }
    removeAttribute(name) {
        if (name === 'min') this.min = '';
    }
}

function makeForm(id, values) {
    const prefix = id === 'changeCalendarEventForm' ? 'change' : 'register';
    const map = {};
    function add(suffix, value = '') {
        const input = new Input(prefix + 'CalendarEvent' + suffix, value);
        input.form = form;
        map['.' + input.className] = input;
        return input;
    }
    const form = {
        id,
        querySelector(selector) {
            if (selector === '.calendar-event-more' || selector === '.modal-body' || selector === '.calendar-event-url-field') return null;
            return map[selector] || null;
        }
    };
    const fields = {
        startDate: add('StartDate', values.startDate || ''),
        endDate: add('EndDate', values.endDate || ''),
        startTime: add('StartTime', values.startTime || ''),
        endTime: add('EndTime', values.endTime || ''),
        allDay: add('AllDay', '')
    };
    fields.allDay.checked = values.allDay !== false;
    return {form, fields};
}

const listeners = {focusin: [], change: []};
const forms = {};
const documentObject = {
    addEventListener(type, handler) {
        if (listeners[type]) listeners[type].push(handler);
    },
    getElementById(id) { return forms[id] || null; },
    createElement() { throw new Error('details enhancement should not run in schedule runtime test'); }
};
class Query {
    off() { return this; }
    on() { return this; }
}
function $(value) {
    if (typeof value === 'function') { value(); return new Query(); }
    return new Query();
}
const windowObject = {setTimeout(fn) { fn(); return 1; }};
vm.runInNewContext(source, {
    jQuery: $, $,
    window: windowObject,
    document: documentObject,
    Date, String, Number, Array, Object, Math, RegExp, console
}, {filename: 'calendar-usability.js'});

function fire(type, target) {
    listeners[type].forEach(handler => handler({target}));
}

const allDay = makeForm('changeCalendarEventForm', {
    startDate: '2026-09-10', endDate: '2026-09-12', allDay: true
});
forms.changeCalendarEventForm = allDay.form;
fire('focusin', allDay.fields.startDate);
allDay.fields.startDate.value = '2026-10-01';
fire('change', allDay.fields.startDate);
check(allDay.fields.endDate.value === '2026-10-03', 'moving start date preserves a two-day all-day span');
check(allDay.fields.endDate.min === '2026-10-01', 'end date minimum follows the moved start date');

const timed = makeForm('registerCalendarEventForm', {
    startDate: '2026-09-26', endDate: '2026-09-26',
    startTime: '10:00', endTime: '11:30', allDay: false
});
forms.registerCalendarEventForm = timed.form;
fire('focusin', timed.fields.startTime);
timed.fields.startTime.value = '14:00';
fire('change', timed.fields.startTime);
check(timed.fields.endDate.value === '2026-09-26' && timed.fields.endTime.value === '15:30',
    'moving start time preserves a 90-minute duration');
check(timed.fields.endTime.min === '14:00', 'same-day end time minimum follows start time');

timed.fields.endTime.value = '16:00';
fire('change', timed.fields.endTime);
fire('focusin', timed.fields.startTime);
timed.fields.startTime.value = '15:00';
fire('change', timed.fields.startTime);
check(timed.fields.endTime.value === '17:00',
    'a user-edited end time becomes the new duration baseline');

const overnight = makeForm('changeCalendarEventForm', {
    startDate: '2026-09-26', endDate: '2026-09-27',
    startTime: '23:00', endTime: '01:00', allDay: false
});
forms.changeCalendarEventForm = overnight.form;
fire('focusin', overnight.fields.startTime);
overnight.fields.startTime.value = '23:30';
fire('change', overnight.fields.startTime);
check(overnight.fields.endDate.value === '2026-09-27' && overnight.fields.endTime.value === '01:30',
    'cross-midnight duration remains intact when start time moves');

const newEvent = makeForm('registerCalendarEventForm', {
    startDate: '', endDate: '', allDay: true
});
forms.registerCalendarEventForm = newEvent.form;
fire('focusin', newEvent.fields.startDate);
newEvent.fields.startDate.value = '2026-11-05';
fire('change', newEvent.fields.startDate);
check(newEvent.fields.endDate.value === '2026-11-05',
    'new event initializes an empty end date to the chosen start date');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
