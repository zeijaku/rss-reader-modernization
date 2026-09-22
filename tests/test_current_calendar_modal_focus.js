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

function run(pointerMatches, hasMatchMedia = true) {
    const handlers = {};
    const title = {
        disabled: false,
        focusCount: 0,
        focusOptions: null,
        focus(options) { this.focusCount += 1; this.focusOptions = options || null; }
    };
    const readyForm = {querySelector(selector) { return selector === '.calendar-event-detail-fields' ? {} : null; }};
    const modal = {querySelector(selector) { return selector === '.registerCalendarEventTitleValue' ? title : null; }};
    const elements = {
        registerCalendarEventForm: readyForm,
        changeCalendarEventForm: readyForm,
        registerCalendarEvent: modal
    };
    const documentObject = {
        getElementById(id) { return elements[id] || null; },
        addEventListener() {},
        createElement() { return {}; }
    };
    class FakeQuery {
        off() { return this; }
        on(eventName, selector, handler) {
            if (typeof selector === 'string' && typeof handler === 'function') {
                handlers[selector] = handler;
            }
            return this;
        }
        attr() { return ''; }
    }
    function $(value) {
        if (typeof value === 'function') { value(); return new FakeQuery(); }
        return new FakeQuery();
    }
    $.ajax = () => ({always() { return this; }});
    $.Deferred = () => ({reject() { return {promise() { return {}; }}; }});
    const queries = [];
    const windowObject = {};
    if (hasMatchMedia) {
        windowObject.matchMedia = query => {
            queries.push(query);
            return {matches: pointerMatches};
        };
    }
    vm.runInNewContext(source, {
        jQuery: $, window: windowObject, document: documentObject,
        URLSearchParams, String, Number, Array, Object, Error, console
    }, {filename: 'calendar-event-details.js'});
    const shown = handlers['#registerCalendarEvent'];
    if (typeof shown === 'function') shown.call(modal, {target: modal});
    return {title, queries, shown};
}

const fine = run(true);
check(typeof fine.shown === 'function', 'add-event modal has a Bootstrap shown handler');
check(fine.queries.includes('(hover: hover) and (pointer: fine)'),
    'focus decision uses input capability rather than User-Agent or viewport width');
check(fine.title.focusCount === 1, 'fine-pointer environment focuses the title once');
check(fine.title.focusOptions && fine.title.focusOptions.preventScroll === true,
    'desktop focus avoids an unnecessary modal scroll jump');

const coarse = run(false);
check(coarse.title.focusCount === 0, 'coarse-pointer environment does not open the software keyboard automatically');

const unsupported = run(false, false);
check(unsupported.title.focusCount === 0, 'unknown input capability fails closed without forced focus');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
