'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
let failures = 0;
let checks = 0;
function check(ok, message) {
    checks += 1;
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + message);
    if (!ok) failures += 1;
}

class StyleDeclaration {
    constructor() { this.values = {}; this.opacity = ''; }
    setProperty(name, value) { this.values[name] = String(value); }
    removeProperty(name) { delete this.values[name]; }
    getPropertyValue(name) { return this.values[name] || ''; }
}

class Element {
    constructor(tag, attrs = {}) {
        this.tag = tag;
        this.attrs = Object.assign({}, attrs);
        this.classes = new Set(String(attrs.class || '').split(/\s+/).filter(Boolean));
        this.className = String(attrs.class || '');
        this.children = [];
        this.parentNode = null;
        this.dataValues = {};
        this.hidden = false;
        this.disabled = false;
        this.style = new StyleDeclaration();
        this.textContent = '';
    }
    appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
    setAttribute(name, value) { this.attrs[name] = String(value); }
}

function selectorMatches(el, selector) {
    selector = selector.trim();
    if (!el || selector === '') return false;
    if (/^[a-z]+$/i.test(selector)) return el.tag.toLowerCase() === selector.toLowerCase();
    if (selector.startsWith('.')) return selector.split('.').filter(Boolean).every(name => el.classes.has(name));
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
                if (selectors.some(selector => selectorMatches(current, selector))) { found.push(current); break; }
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
const ignored = new Element('div', {'data-dashboard-swipe-ignore': 'true'});
const input = new Element('input');
main.appendChild(surface); main.appendChild(ignored); main.appendChild(input); body.appendChild(main);
const pageTop = new Element('div', {id: 'page-top'});
const notice = new Element('div', {id: 'app-notice'});
body.appendChild(pageTop); body.appendChild(notice);
const documentObject = new Element('document');
documentObject.body = body;
documentObject.documentElement = {clientWidth: 390};
documentObject.createElement = tag => new Element(tag);
let mobileMatches = true;
const assigned = [];
let timerId = 0;
const timers = new Map();
const windowObject = {
    innerWidth: 390,
    matchMedia: query => ({matches: query.indexOf('max-width') >= 0 ? mobileMatches : false}),
    location: {reload() {}, assign(url) { assigned.push(url); }},
    setTimeout(fn, delay) { timerId += 1; timers.set(timerId, {fn, delay: Number(delay || 0)}); return timerId; },
    clearTimeout(id) { timers.delete(id); },
    setInterval() { return 1; }
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
check([start, move, end, cancel].every(fn => typeof fn === 'function'), 'V1.6-B keeps one namespaced touch handler set');

function touchEvent(target, x, y, changed = false) {
    const points = [{clientX: x, clientY: y}];
    return {
        target,
        originalEvent: changed ? {changedTouches: points} : {touches: points},
        preventDefault() { this.prevented = true; }
    };
}
function indicator() { return body.children.find(el => el.attrs['data-dashboard-swipe-indicator'] === 'true') || null; }
function runTimerWithDelay(delay) {
    const entry = [...timers.entries()].find(([, value]) => value.delay === delay);
    if (!entry) return false;
    timers.delete(entry[0]); entry[1].fn(); return true;
}
function clearAllTimers() {
    [...timers.entries()].sort((a, b) => a[1].delay - b[1].delay).forEach(([id, value]) => { if (timers.has(id)) { timers.delete(id); value.fn(); } });
}

let s = touchEvent(surface, 320, 420);
let m1 = touchEvent(surface, 275, 420);
start.call(main, s); move.call(main, m1);
let edge = indicator();
check(edge !== null, 'Indicator is created lazily only after clear horizontal intent');
check(edge.className.includes('is-right') && edge.textContent === '‹', 'left Swipe shows a left arrow at the right edge for the next tab');
const firstOpacity = Number(edge.style.opacity);
let m2 = touchEvent(surface, 100, 420);
move.call(main, m2);
check(Number(edge.style.opacity) > firstOpacity, 'Indicator becomes stronger as Swipe distance increases');
check(m2.prevented === true, 'existing horizontal gesture suppression remains active');
end.call(main, touchEvent(surface, 100, 420, true));
check(assigned.length === 0, 'accepted Swipe does not navigate before the visual confirmation frame');
check(edge.className.includes('is-complete') && edge.style.opacity === '1', 'accepted Swipe receives a strong completion state');
check(runTimerWithDelay(160) && assigned.pop() === './?tab=2', 'accepted Swipe navigates to the next tab after the bounded delay');
clearAllTimers();

start.call(main, touchEvent(surface, 80, 420));
move.call(main, touchEvent(surface, 290, 420));
edge = indicator();
check(edge.className.includes('is-left') && edge.textContent === '›', 'right Swipe shows a right arrow at the left edge for the previous tab');
end.call(main, touchEvent(surface, 290, 420, true));
check(runTimerWithDelay(160) && assigned.pop() === './?tab=0', 'right Swipe still navigates to the previous tab');
clearAllTimers();

const beforeShort = assigned.length;
start.call(main, touchEvent(surface, 250, 420));
move.call(main, touchEvent(surface, 210, 420));
end.call(main, touchEvent(surface, 210, 420, true));
edge = indicator();
check(assigned.length === beforeShort && edge.className.includes('is-hiding'), 'short Swipe quietly hides the indicator without navigation');
clearAllTimers();

start.call(main, touchEvent(surface, 210, 300));
move.call(main, touchEvent(surface, 195, 520));
check(indicator().className.includes('is-hiding') && indicator().style.opacity === '0', 'vertical Scroll quietly cancels the visual indicator');

const beforeIgnored = body.children.length;
start.call(main, touchEvent(input, 320, 420));
move.call(main, touchEvent(input, 100, 420));
check(body.children.length === beforeIgnored && indicator().className === 'dashboard-swipe-indicator', 'Form controls remain excluded from Swipe visuals');
start.call(main, touchEvent(ignored, 320, 420));
move.call(main, touchEvent(ignored, 100, 420));
check(indicator().className === 'dashboard-swipe-indicator', 'Timer and Game swipe-ignore regions remain excluded');

main.attrs['data-dashboard-current-tab'] = '3';
start.call(main, touchEvent(surface, 320, 420));
move.call(main, touchEvent(surface, 100, 420));
check(indicator().className === 'dashboard-swipe-indicator', 'last tab does not show a false next-tab indicator');
main.attrs['data-dashboard-current-tab'] = '1';

start.call(main, touchEvent(surface, 320, 420));
move.call(main, touchEvent(surface, 180, 420));
cancel.call(main, {});
check(indicator().className.includes('is-hiding'), 'touchcancel quietly dismisses the indicator');
clearAllTimers();

mobileMatches = false;
const childrenBeforeDesktop = body.children.length;
start.call(main, touchEvent(surface, 320, 420));
move.call(main, touchEvent(surface, 100, 420));
check(body.children.length === childrenBeforeDesktop && indicator().className === 'dashboard-swipe-indicator', 'desktop width never activates the Swipe visual');

console.log(`RESULT: PASS ${checks - failures} / FAIL ${failures} / SKIP 0`);
if (failures) process.exit(1);
