'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const scripts = [
    'public/js/calendar-recurrence.js',
    'public/js/calendar-event-details.js',
    'public/js/calendar-colors.js'
];
const listeners = {submit: [], click: []};
const requests = [];
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        console.log('PASS: ' + message);
    } else {
        failed += 1;
        console.error('FAIL: ' + message);
    }
}

const documentObject = {
    addEventListener(type, callback) {
        if (listeners[type]) listeners[type].push(callback);
    },
    getElementById() { return null; },
    createElement() { return {}; }
};

const chain = {
    length: 0,
    attr(name) { return name === 'content' ? 'c'.repeat(64) : ''; },
    removeClass() { return this; }, addClass() { return this; }, prop() { return this; }, text() { return this; },
    val() { return this; }, off() { return this; }, on() { return this; }, each() { return this; }, first() { return this; }
};
function $(value) {
    if (typeof value === 'function') return chain;
    return chain;
}
$.extend = (...values) => Object.assign({}, ...values);
$.ajax = function (settings) {
    requests.push(settings);
    const ajaxChain = {
        done() { return ajaxChain; }, fail() { return ajaxChain; }, always() { return ajaxChain; }
    };
    return ajaxChain;
};

const context = {
    jQuery: $, $,
    window: {jQuery: $, setTimeout() {}, location: {reload() {}}},
    document: documentObject,
    URLSearchParams,
    String, Number, Array, Object, Math, Date,
    console
};
scripts.forEach(relative => {
    vm.runInNewContext(fs.readFileSync(path.join(root, relative), 'utf8'), context, {filename: relative});
});

check(listeners.submit.length === 3, 'recurrence, detail and color capture handlers remain registered');

const fields = {
    '.registerCalendarEventTitleValue': {value: '一度だけ保存'},
    '.registerCalendarEventStartDate': {value: '2026-09-10'},
    '.registerCalendarEventEndDate': {value: '2026-09-10'},
    '.registerCalendarEventNote': {value: 'memo'},
    '.registerCalendarEventColor': {value: 'purple'},
    '.registerCalendarEventAllDay': {checked: true, value: '1'},
    '.registerCalendarEventStartTime': {value: ''},
    '.registerCalendarEventEndTime': {value: ''},
    '.registerCalendarEventUrl': {value: ''},
    '.registerCalendarEventRepeatType': {value: 'none'},
    '.registerCalendarEventRepeatUntil': {value: ''},
    'button[type="submit"]': {disabled: false}
};
const form = {
    id: 'registerCalendarEventForm',
    querySelector(selector) { return fields[selector] || null; },
    reportValidity() { return true; },
    getAttribute(name) { return name === 'data-calendar-recurrence-submit-ready' ? '1' : null; },
    setAttribute() {}
};
const event = {
    target: form,
    stopped: false,
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopImmediatePropagation() { this.stopped = true; }
};

for (const listener of listeners.submit) {
    listener(event);
    if (event.stopped) break;
}

check(event.prevented && event.stopped, 'first capture handler prevents legacy and later submit paths');
check(requests.length === 1, 'one form submission creates exactly one AJAX request');
check(requests[0] && requests[0].url === './calendar_recurrence_api.php', 'combined submit uses the secured recurrence endpoint');
check(requests[0] && requests[0].data.action === 'calendar.recurrence.create', 'combined submit selects one create action');
check(requests[0] && requests[0].data.calendar_event_title === '一度だけ保存', 'combined request keeps the title');
check(requests[0] && requests[0].data.calendar_event_color === 'purple', 'combined request keeps a V1.33 color');
check(requests[0] && requests[0].data.csrf_token === 'c'.repeat(64), 'combined request carries CSRF token');

console.log(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0`);
process.exit(failed === 0 ? 0 : 1);
