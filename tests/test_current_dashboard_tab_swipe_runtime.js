'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard-tab-swipe.js'), 'utf8');
const checks = [];
function check(pass, name) {
    checks.push(Boolean(pass));
    console.log((pass ? 'PASS: ' : 'FAIL: ') + name);
}
const events = new Map();
const timeouts = [];
const navigations = [];
const documentObject = {
    documentElement: {clientWidth: 390},
    body: {
        appendChild(el) { el.parentNode = this; }
    },
    createElement() {
        return {
            className: '',
            textContent: '',
            setAttribute() {},
            style: {
                opacity: '',
                setProperty() {},
                removeProperty() {}
            }
        };
    }
};
const main = {
    attrs: {
        'data-dashboard-current-tab': '1',
        'data-dashboard-tab-count': '4'
    },
    length: 1,
    attr(name) { return this.attrs[name] || ''; },
    off(event) { events.delete(event); return this; },
    on(event, handler) { events.set(event, handler); return this; }
};
let modalVisible = false;
function $(input) {
    if (input === '#main-content') { return main; }
    if (input === '.modal.show') { return {length: modalVisible ? 1 : 0}; }
    if (typeof input === 'object') {
        return {closest() { return {length: input.blocked ? 1 : 0}; }};
    }
    throw new Error('Unexpected jQuery selector: ' + String(input));
}
let seq = 0;
const windowObject = {
    innerWidth: 390,
    matchMedia() { return {matches: true}; },
    setTimeout(callback, delay) {
        const t = {id: ++seq, callback, delay};
        timeouts.push(t);
        return t.id;
    },
    clearTimeout(id) {
        const idx = timeouts.findIndex(t => t.id === id);
        if (idx >= 0) { timeouts.splice(idx, 1); }
    },
    location: {assign(target) { navigations.push(target); }}
};
const context = {jQuery: $, window: windowObject, document: documentObject, Date, Number, Math, String, RegExp};
vm.runInNewContext(source, context, {filename: 'dashboard-tab-swipe.js'});
const feature = windowObject.IGuguruDashboardTabSwipe;
check(Boolean(feature && typeof feature.initTabSwipe === 'function'), 'standalone swipe controller exports its initializer');
let drawerVisible = false;
let widgetDragging = false;
function initialize() {
    feature.initTabSwipe({
        isDrawerActive() { return drawerVisible; },
        isWidgetDragging() { return widgetDragging; }
    });
}
initialize();
initialize();
check(events.size === 4, 'swipe handlers rebind idempotently');
const startHandler = events.get('touchstart.iguguruDashboard');
const moveHandler = events.get('touchmove.iguguruDashboard');
const endHandler = events.get('touchend.iguguruDashboard');
function gesture(fromX, fromY, toX, toY, opts = {}) {
    const element = opts.element || {};
    const start = {clientX: fromX, clientY: fromY};
    const end = {clientX: toX, clientY: toY};
    const make = (name, target, touch) => ({
        target, originalEvent: {[name]: [touch]},
        prevented: false, preventDefault() { this.prevented = true; }
    });
    startHandler(make('touches', element, start));
    const move = make('touches', element, end);
    moveHandler(move);
    endHandler(make('changedTouches', element, end));
    return move.prevented;
}
function flushNavigation() {
    const i = timeouts.findIndex(t => t.delay === 160);
    if (i < 0) { return false; }
    const item = timeouts.splice(i, 1)[0];
    item.callback();
    return true;
}
check(gesture(280, 100, 140, 104), 'left swipe qualifies as horizontal gesture');
check(flushNavigation() && navigations.at(-1) === './?tab=2', 'left swipe navigates to next tab using existing route');
check(gesture(115, 100, 240, 105), 'right swipe qualifies as horizontal gesture');
check(flushNavigation() && navigations.at(-1) === './?tab=0', 'right swipe navigates to previous tab');
const before = navigations.length;
gesture(200, 120, 195, 235);
check(!flushNavigation() && navigations.length === before, 'vertical scrolling is never converted to tab navigation');
gesture(200, 100, 30, 105, {element: {blocked: true}});
check(!flushNavigation(), 'interactive element targets are excluded');
drawerVisible = true;
gesture(200, 100, 30, 105);
check(!flushNavigation(), 'open Drawer blocks tab swipe');
drawerVisible = false;
widgetDragging = true;
gesture(200, 100, 30, 105);
check(!flushNavigation(), 'Widget drag operation blocks tab swipe');
widgetDragging = false;
modalVisible = true;
gesture(200, 100, 30, 105);
check(!flushNavigation(), 'open modal blocks tab swipe');
modalVisible = false;
gesture(8, 100, 180, 100);
check(!flushNavigation(), 'screen-edge gesture is ignored');
main.attrs['data-dashboard-current-tab'] = '3';
gesture(260, 100, 100, 105);
check(!flushNavigation(), 'cannot swipe beyond the last tab');
main.attrs['data-dashboard-current-tab'] = '1';
windowObject.matchMedia = () => ({matches: false});
gesture(260, 100, 100, 105);
check(!flushNavigation(), 'desktop-width swipe is not activated');
const fails = checks.filter(ok => !ok).length;
console.log('RESULT: PASS ' + (checks.length - fails) + ' / FAIL ' + fails);
if (fails) { process.exit(1); }
