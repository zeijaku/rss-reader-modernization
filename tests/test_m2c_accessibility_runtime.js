'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const handlers = new Map();
let failures = 0;
let offcanvasCreates = 0;
let hideCalls = 0;
let modalShowCalls = 0;
let modalShowRelatedTarget = null;

function check(condition, message) {
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
}

class Element {
    constructor(name, attrs) {
        this.name = name;
        this.attrs = Object.assign({}, attrs || {});
        this.dataValues = {};
        this.classes = new Set(String(this.attrs.class || '').split(/\s+/).filter(Boolean));
        this.children = [];
        this.parent = null;
        this.focusCount = 0;
        this.disabled = false;
    }
    focus() {
        this.focusCount += 1;
        documentObject.activeElement = this;
    }
}

const body = new Element('body');
const menu = new Element('drawerMenu', {class: 'offcanvas offcanvas-end drawer-nav'});
const mobileToggle = new Element('mobileToggle', {class: 'drawer-toggle', 'aria-controls': 'drawerMenu', 'aria-expanded': 'false', 'aria-label': 'メニューを開く'});
const desktopToggle = new Element('desktopToggle', {class: 'drawer-toggle', 'aria-controls': 'drawerMenu', 'aria-expanded': 'false', 'aria-label': 'メニューを開く'});
const drawerAction = new Element('drawerAction', {'data-drawer-modal-target': '#testModal'});
const main = new Element('main-content');
const pageTop = new Element('page-top');
const modal = new Element('modal');
const modalTrigger = new Element('modalTrigger');
const meta = new Element('meta', {content: 'csrf-m2c-token'});
const dummy = new Element('dummy');
const documentObject = new Element('document');
documentObject.activeElement = null;
documentObject.getElementById = (id) => id === 'drawerMenu' ? menu : null;
documentObject.querySelector = (selector) => selector === '#testModal' ? modal : null;

const windowObject = {
    location: {reload: () => {}},
    matchMedia: () => ({matches: false}),
    setTimeout: () => 1,
    clearTimeout: () => {},
    setInterval: () => 1,
    clearInterval: () => {}
};

const drawerInstance = {
    hide() {
        hideCalls += 1;
    }
};
const modalInstance = {
    show(relatedTarget) {
        modalShowCalls += 1;
        modalShowRelatedTarget = relatedTarget || null;
    }
};
const bootstrapObject = {
    Offcanvas: {
        getOrCreateInstance(element) {
            if (element === menu) offcanvasCreates += 1;
            return drawerInstance;
        }
    },
    Modal: {
        getOrCreateInstance(element) {
            return element === modal ? modalInstance : modalInstance;
        }
    }
};

class Wrapper {
    constructor(elements) {
        this.elements = elements || [];
    }
    get length() { return this.elements.length; }
    data(key, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].dataValues[key] : undefined;
        this.elements.forEach((element) => { element.dataValues[key] = value; });
        return this;
    }
    removeData(key) {
        this.elements.forEach((element) => { delete element.dataValues[key]; });
        return this;
    }
    attr(key, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].attrs[key] : undefined;
        this.elements.forEach((element) => { element.attrs[key] = String(value); });
        return this;
    }
    prop(key, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0][key] : undefined;
        if (key === 'disabled') this.elements.forEach((element) => { element.disabled = value; });
        if (key === 'hidden') this.elements.forEach((element) => { element.hidden = value; });
        return this;
    }
    val() { return ''; }
    text() { return this; }
    empty() { return this; }
    append() { return this; }
    find(selector) {
        if (selector === 'button[type="submit"]') return new Wrapper([dummy]);
        return new Wrapper([]);
    }
    closest() { return new Wrapper([]); }
    each(fn) {
        this.elements.forEach((element, index) => fn.call(element, index, element));
        return this;
    }
    get(index) { return this.elements[index]; }
    first() { return new Wrapper(this.elements.length ? [this.elements[0]] : []); }
    focus() {
        this.elements.forEach((element) => element.focus());
        return this;
    }
    hasClass(name) { return this.elements[0] ? this.elements[0].classes.has(name) : false; }
    addClass(name) { this.elements.forEach((e) => e.classes.add(name)); return this; }
    removeClass(name) { this.elements.forEach((e) => e.classes.delete(name)); return this; }
    hide() { return this; }
    fadeIn() { return this; }
    fadeOut() { return this; }
    animate() { return this; }
    popover() { return this; }
    scrollTop() { return 0; }
    off(event, selector) {
        if (selector) handlers.delete(event + '|' + selector);
        else handlers.delete(event + '|');
        return this;
    }
    on(event, selector, callback) {
        if (typeof selector === 'function') {
            callback = selector;
            selector = '';
        }
        handlers.set(event + '|' + selector, callback);
        return this;
    }
}

function $(arg) {
    if (typeof arg === 'function') {
        arg();
        return new Wrapper([]);
    }
    if (arg instanceof Element) return new Wrapper([arg]);
    if (arg === documentObject) return new Wrapper([documentObject]);
    if (arg === windowObject) return new Wrapper([dummy]);
    if (arg === 'body') return new Wrapper([body]);
    if (arg === '#drawerMenu') return new Wrapper([menu]);
    if (arg === '.drawer-toggle[aria-controls="drawerMenu"]') return new Wrapper([mobileToggle, desktopToggle]);
    if (arg === '#main-content') return new Wrapper([main]);
    if (arg === '#page-top') return new Wrapper([pageTop]);
    if (arg === '.modal.show') return new Wrapper([]);
    if (arg === '[data-feed-content-id]' || arg === '[data-dashboard-widget-type="clock"]') return new Wrapper([]);
    if (arg === 'meta[name="csrf-token"]') return new Wrapper([meta]);
    if (typeof arg === 'string' && arg.startsWith('#rssHighlightKeyword')) return new Wrapper([]);
    if (typeof arg === 'string' && arg.startsWith('<')) return new Wrapper([new Element('created')]);
    return new Wrapper([dummy]);
}

$.extend = (...args) => Object.assign(...args);
$.ajax = () => ({done() { return this; }, fail() { return this; }, always() { return this; }});

const context = {
    jQuery: $,
    bootstrap: bootstrapObject,
    window: windowObject,
    document: documentObject,
    alert: () => {},
    console,
    Array,
    String,
    Math,
    RegExp,
    Number,
    JSON
};
vm.runInNewContext(source, context, {filename: 'dashboard.js'});

check(offcanvasCreates === 1, 'dashboard initializes Bootstrap Offcanvas exactly once');
check(mobileToggle.attrs['aria-expanded'] === 'false' && desktopToggle.attrs['aria-expanded'] === 'false', 'Offcanvas triggers start collapsed');
check(mobileToggle.attrs['aria-label'] === 'メニューを開く', 'Offcanvas trigger starts with an open label');

const toggleHandler = handlers.get('click.iguguruDashboard|.drawer-toggle[aria-controls="drawerMenu"]');
const showHandler = handlers.get('show.bs.offcanvas.iguguruDashboard|');
const hiddenHandler = handlers.get('hidden.bs.offcanvas.iguguruDashboard|');
check(typeof toggleHandler === 'function', 'Offcanvas trigger tracking handler is registered');
check(typeof showHandler === 'function' && typeof hiddenHandler === 'function', 'Bootstrap Offcanvas lifecycle handlers are registered');

toggleHandler.call(mobileToggle);
showHandler.call(menu);
check(mobileToggle.attrs['aria-expanded'] === 'true' && desktopToggle.attrs['aria-expanded'] === 'true', 'show.bs.offcanvas expands both trigger states');
check(mobileToggle.attrs['aria-label'] === 'メニューを閉じる', 'show.bs.offcanvas changes the trigger label');

hiddenHandler.call(menu);
check(mobileToggle.attrs['aria-expanded'] === 'false' && desktopToggle.attrs['aria-expanded'] === 'false', 'hidden.bs.offcanvas collapses both trigger states');
check(mobileToggle.attrs['aria-label'] === 'メニューを開く', 'hidden.bs.offcanvas restores the open label');
check(!handlers.has('keydown.iguguruDashboard.drawer|'), 'application no longer duplicates Bootstrap Escape/focus-trap handling');

const drawerModalHandler = handlers.get('click.iguguruDashboard|.drawer-menu-action[data-drawer-modal-target]');
check(typeof drawerModalHandler === 'function', 'Drawer-to-Modal transition handler is registered');
let prevented = false;
drawerModalHandler.call(drawerAction, {preventDefault: () => { prevented = true; }});
check(prevented, 'Drawer modal action prevents a competing Data API transition');
check(hideCalls === 1, 'Drawer modal action hides Offcanvas first');
hiddenHandler.call(menu);
check(modalShowCalls === 1, 'Modal opens after Offcanvas has fully hidden');
check(modalShowRelatedTarget === mobileToggle, 'Drawer-to-Modal transition preserves the visible menu trigger as return-focus target');

const modalShowHandler = handlers.get('show.bs.modal.iguguruDashboard|.modal');
const modalHiddenHandler = handlers.get('hidden.bs.modal.iguguruDashboard|.modal');
check(typeof modalShowHandler === 'function' && typeof modalHiddenHandler === 'function', 'modal focus handlers are registered');
modalShowHandler.call(modal, {relatedTarget: modalTrigger});
modalHiddenHandler.call(modal);
check(documentObject.activeElement === modalTrigger, 'closing a modal returns focus to its trigger');
check(modal.dataValues['return-focus'] === undefined, 'modal focus reference is cleared after use');

check(handlers.has('submit.iguguruDashboard|#registerContentForm'), 'RSS add form supports keyboard submit');
check(handlers.has('submit.iguguruDashboard|#changeContentForm'), 'RSS change form supports keyboard submit');

if (failures > 0) process.exit(1);
console.log('All M2-C accessibility runtime checks passed.');
