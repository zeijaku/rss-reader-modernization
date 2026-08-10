'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const handlers = new Map();
let closeCalls = 0;
let failures = 0;

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

const body = new Element('body', {class: 'drawer drawer--right'});
const menu = new Element('drawerMenu');
const firstItem = new Element('firstItem');
const lastItem = new Element('lastItem');
const mobileToggle = new Element('mobileToggle', {class: 'drawer-toggle'});
const desktopToggle = new Element('desktopToggle', {class: 'drawer-toggle'});
const main = new Element('main-content');
const pageTop = new Element('page-top');
const modal = new Element('modal');
const modalTrigger = new Element('modalTrigger');
const meta = new Element('meta', {content: 'csrf-m2c-token'});
const dummy = new Element('dummy');
const documentObject = new Element('document');
documentObject.getElementById = () => null;
documentObject.activeElement = null;
const windowObject = {
    location: {reload: () => {}},
    matchMedia: () => ({matches: false}),
    setTimeout: () => 1,
    clearTimeout: () => {},
    setInterval: () => 1,
    clearInterval: () => {}
};

menu.children.push(firstItem, lastItem);
firstItem.parent = menu;
lastItem.parent = menu;

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
        if (key === 'disabled') this.elements.forEach((element) => { element.disabled = value; });
        return this;
    }
    val() { return ''; }
    text(value) { return this; }
    find(selector) {
        if (this.elements.includes(menu) && selector.indexOf('a[href]') !== -1) {
            return new Wrapper([firstItem, lastItem]);
        }
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
    drawer(method) {
        if (method === 'close') {
            closeCalls += 1;
            body.classes.delete('drawer-open');
        }
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
    if (arg === 'body' || arg === '.drawer') return new Wrapper([body]);
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
$.fn = {drawer: function () {}};
$.ajax = () => ({done() { return this; }, fail() { return this; }, always() { return this; }});

const context = {
    jQuery: $,
    window: windowObject,
    document: documentObject,
    alert: () => {},
    console,
    Array,
    String,
    Math,
    RegExp
};
vm.runInNewContext(source, context, {filename: 'dashboard.js'});

check(mobileToggle.attrs['aria-expanded'] === 'false' && desktopToggle.attrs['aria-expanded'] === 'false', 'Drawer triggers start collapsed');
check(mobileToggle.attrs['aria-label'] === 'メニューを開く', 'Drawer trigger starts with an open label');

const toggleHandler = handlers.get('click.iguguruDashboard|.drawer-toggle[aria-controls="drawerMenu"]');
check(typeof toggleHandler === 'function', 'Drawer trigger click handler is registered');
toggleHandler.call(mobileToggle);
body.classes.add('drawer-open');
const openedHandler = handlers.get('drawer.opened.iguguruDashboard|');
openedHandler.call(body);
check(mobileToggle.attrs['aria-expanded'] === 'true' && desktopToggle.attrs['aria-expanded'] === 'true', 'opening Drawer expands both trigger states');
check(mobileToggle.attrs['aria-label'] === 'メニューを閉じる', 'opening Drawer changes the trigger label');
check(documentObject.activeElement === firstItem, 'opening Drawer focuses the first menu item');

const keyHandler = handlers.get('keydown.iguguruDashboard.drawer|');
let prevented = false;
keyHandler.call(documentObject, {key: 'Escape', keyCode: 27, preventDefault: () => { prevented = true; }});
check(prevented, 'Escape key prevents the background key action');
check(closeCalls === 1, 'Escape key asks the Drawer plugin to close');

body.classes.add('drawer-open');
documentObject.activeElement = firstItem;
prevented = false;
keyHandler.call(documentObject, {key: 'Tab', keyCode: 9, shiftKey: true, preventDefault: () => { prevented = true; }});
check(prevented && documentObject.activeElement === lastItem, 'Shift+Tab wraps from first to last Drawer item');

documentObject.activeElement = lastItem;
prevented = false;
keyHandler.call(documentObject, {key: 'Tab', keyCode: 9, shiftKey: false, preventDefault: () => { prevented = true; }});
check(prevented && documentObject.activeElement === firstItem, 'Tab wraps from last to first Drawer item');

const closedHandler = handlers.get('drawer.closed.iguguruDashboard|');
closedHandler.call(body);
check(mobileToggle.attrs['aria-expanded'] === 'false', 'closing Drawer collapses trigger state');
check(documentObject.activeElement === mobileToggle, 'closing Drawer returns focus to the opening trigger');

const modalShowHandler = handlers.get('show.bs.modal.iguguruDashboard|.modal');
const modalHiddenHandler = handlers.get('hidden.bs.modal.iguguruDashboard|.modal');
check(typeof modalShowHandler === 'function' && typeof modalHiddenHandler === 'function', 'modal focus handlers are registered');
modalShowHandler.call(modal, {relatedTarget: modalTrigger});
modalHiddenHandler.call(modal);
check(documentObject.activeElement === modalTrigger, 'closing a modal returns focus to its trigger');
check(modal.dataValues['return-focus'] === undefined, 'modal focus reference is cleared after use');

const pageTopHandler = handlers.get('click.iguguruDashboard|');
prevented = false;
pageTopHandler.call(pageTop, {preventDefault: () => { prevented = true; }});
check(prevented, 'Page Top prevents the default jump');
check(documentObject.activeElement === main, 'Page Top moves focus to main content');

check(handlers.has('submit.iguguruDashboard|#registerContentForm'), 'RSS add form supports keyboard submit');
check(handlers.has('submit.iguguruDashboard|#changeContentForm'), 'RSS change form supports keyboard submit');

if (failures > 0) process.exit(1);
console.log('All M2-C accessibility runtime checks passed.');
