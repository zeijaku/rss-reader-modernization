'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
let failures = 0;
function check(ok, message) {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + message);
    if (!ok) failures += 1;
}

class Element {
    constructor(tag, attrs = {}) {
        this.tag = tag;
        this.attrs = Object.assign({}, attrs);
        this.classes = new Set(String(attrs.class || '').split(/\s+/).filter(Boolean));
        this.children = [];
        this.parentNode = null;
        this.dataValues = {};
        this.hidden = false;
        this.disabled = false;
    }
    appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
}

function selectorMatches(el, selector) {
    selector = selector.trim();
    if (!el || selector === '') return false;
    if (/^[a-z]+$/i.test(selector)) return el.tag.toLowerCase() === selector.toLowerCase();
    if (selector.startsWith('.')) {
        return selector.split('.').filter(Boolean).every(name => el.classes.has(name));
    }
    const attr = selector.match(/^\[([^=\]]+)="([^"]+)"\]$/);
    if (attr) return String(el.attrs[attr[1]] || '') === attr[2];
    return false;
}

class Wrapper {
    constructor(elements = []) { this.elements = elements; }
    get length() { return this.elements.length; }
    each(fn) { this.elements.forEach((el, i) => fn.call(el, i, el)); return this; }
    attr(name, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].attrs[name] : undefined;
        this.elements.forEach(el => { el.attrs[name] = String(value); }); return this;
    }
    data(name, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].dataValues[name] : undefined;
        this.elements.forEach(el => { el.dataValues[name] = value; }); return this;
    }
    prop(name, value) { this.elements.forEach(el => { el[name] = value; }); return this; }
    empty() { return this; }
    text() { return this; }
    addClass() { return this; }
    removeClass() { return this; }
    hasClass(name) { return this.elements[0] ? this.elements[0].classes.has(name) : false; }
    closest(selectorList) {
        const selectors = String(selectorList).split(',');
        const found = [];
        this.elements.forEach(el => {
            let current = el;
            while (current) {
                if (selectors.some(selector => selectorMatches(current, selector))) {
                    found.push(current);
                    break;
                }
                current = current.parentNode;
            }
        });
        return new Wrapper(found);
    }
    find() { return new Wrapper(); }
    off() { return this; }
    on(event, selector, callback) {
        if (typeof selector === 'function') { callback = selector; selector = ''; }
        handlers.set(event + '|' + selector, callback);
        return this;
    }
    hide() { return this; }
    fadeIn() { return this; }
    fadeOut() { return this; }
    animate() { return this; }
    popover() { return this; }
    scrollTop() { return 0; }
    focus() { return this; }
    first() { return new Wrapper(this.elements.slice(0, 1)); }
}

const handlers = new Map();
const body = new Element('body', {class: 'drawer'});
const main = new Element('main', {id: 'main-content', 'data-dashboard-current-tab': '1', 'data-dashboard-tab-count': '4'});
const surface = new Element('div', {class: 'swipe-surface'});
const calendar = new Element('section', {'data-dashboard-widget-type': 'calendar'});
const button = new Element('button');
main.appendChild(surface); main.appendChild(calendar); main.appendChild(button); body.appendChild(main);
const pageTop = new Element('div', {id: 'page-top'});
const notice = new Element('div', {id: 'app-notice'});
body.appendChild(pageTop); body.appendChild(notice);
const documentObject = new Element('document');
documentObject.getElementById = () => null;
documentObject.body = body;
documentObject.documentElement = {clientWidth: 390};
let mobileMatches = true;
const assigned = [];
const windowObject = {
    innerWidth: 390,
    matchMedia: () => ({matches: mobileMatches}),
    location: {reload() {}, assign(url) { assigned.push(url); }},
    setTimeout() { return 1; }, clearTimeout() {}, setInterval() { return 1; }
};

function $(arg) {
    if (typeof arg === 'function') { arg(); return new Wrapper(); }
    if (arg instanceof Element) return new Wrapper([arg]);
    if (arg === documentObject) return new Wrapper([documentObject]);
    if (arg === windowObject) return new Wrapper([windowObject]);
    if (arg === 'body') return new Wrapper([body]);
    if (arg === '#main-content') return new Wrapper([main]);
    if (arg === '#page-top') return new Wrapper([pageTop]);
    if (arg === '#app-notice') return new Wrapper([notice]);
    if (arg === '.drawer') return new Wrapper([body]);
    if (arg === '.modal.show') return new Wrapper();
    if (arg === '#drawerMenu' || arg === '[data-toggle="popover"]' || arg === '[data-feed-content-id]' || arg === '[data-dashboard-widget-type="clock"]') return new Wrapper();
    return new Wrapper();
}
$.extend = (...args) => Object.assign(...args);
$.fn = {};
$.ajax = () => ({done() { return this; }, fail() { return this; }, always() { return this; }});

const context = {jQuery: $, window: windowObject, document: documentObject, console, Number, String, Object, Array, Math, RegExp, Date, JSON};
vm.runInNewContext(source, context, {filename: 'dashboard.js'});

const start = handlers.get('touchstart.iguguruDashboard|');
const move = handlers.get('touchmove.iguguruDashboard|');
const end = handlers.get('touchend.iguguruDashboard|');
const cancel = handlers.get('touchcancel.iguguruDashboard|');
check(typeof start === 'function' && typeof move === 'function' && typeof end === 'function' && typeof cancel === 'function', 'smartphone touch handlers are registered once');

function touchEvent(target, x, y, changed = false, count = 1) {
    const points = [];
    for (let i = 0; i < count; i += 1) points.push({clientX: x + i, clientY: y + i});
    return {
        target,
        originalEvent: changed ? {changedTouches: points} : {touches: points},
        preventDefault() { this.prevented = true; }
    };
}
function swipe(target, x1, y1, x2, y2) {
    const s = touchEvent(target, x1, y1);
    const m = touchEvent(target, x2, y2);
    const e = touchEvent(target, x2, y2, true);
    start.call(main, s); move.call(main, m); end.call(main, e);
    return {start: s, move: m, end: e};
}

let result = swipe(surface, 320, 420, 100, 425);
check(result.move.prevented === true, 'horizontal intent suppresses the competing page gesture');
check(assigned.pop() === './?tab=2', 'left swipe moves to the next tab');

result = swipe(surface, 80, 420, 290, 418);
check(assigned.pop() === './?tab=0', 'right swipe moves to the previous tab');

const beforeVertical = assigned.length;
result = swipe(surface, 210, 300, 190, 520);
check(assigned.length === beforeVertical && result.move.prevented !== true, 'vertical scrolling does not change tabs');

const beforeShort = assigned.length;
swipe(surface, 250, 420, 205, 420);
check(assigned.length === beforeShort, 'short horizontal movement does not change tabs');

const beforeCalendar = assigned.length;
swipe(calendar, 320, 200, 100, 200);
check(assigned.length === beforeCalendar, 'Calendar horizontal area is excluded from tab swipe');

const beforeButton = assigned.length;
swipe(button, 320, 420, 100, 420);
check(assigned.length === beforeButton, 'interactive controls are excluded from tab swipe');

const beforeEdge = assigned.length;
swipe(surface, 10, 420, 150, 420);
check(assigned.length === beforeEdge, 'screen-edge gesture is excluded');

const beforeMulti = assigned.length;
start.call(main, touchEvent(surface, 300, 420, false, 2));
move.call(main, touchEvent(surface, 100, 420, false, 2));
end.call(main, touchEvent(surface, 100, 420, true, 2));
check(assigned.length === beforeMulti, 'multi-touch gesture is ignored');

mobileMatches = false;
const beforeDesktop = assigned.length;
swipe(surface, 320, 420, 100, 420);
check(assigned.length === beforeDesktop, 'desktop width does not enable swipe navigation');
mobileMatches = true;

main.attrs['data-dashboard-current-tab'] = '0';
const beforeFirst = assigned.length;
swipe(surface, 80, 420, 300, 420);
check(assigned.length === beforeFirst, 'first tab does not wrap to the last tab');
main.attrs['data-dashboard-current-tab'] = '3';
const beforeLast = assigned.length;
swipe(surface, 320, 420, 100, 420);
check(assigned.length === beforeLast, 'last tab does not wrap to the first tab');
main.attrs['data-dashboard-current-tab'] = '1';

const beforeCancel = assigned.length;
start.call(main, touchEvent(surface, 320, 420));
move.call(main, touchEvent(surface, 220, 420));
cancel.call(main, {});
end.call(main, touchEvent(surface, 100, 420, true));
check(assigned.length === beforeCancel, 'touch cancellation clears pending swipe state');

body.classes.add('drawer-open');
const beforeDrawer = assigned.length;
swipe(surface, 320, 420, 100, 420);
check(assigned.length === beforeDrawer, 'open Drawer blocks tab swipe');
body.classes.delete('drawer-open');

if (failures) process.exit(1);
console.log('All V1.1-I / R2 mobile swipe runtime checks passed.');
