'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const handlers = new Map();
const wrappers = new Map();
const ajaxCalls = [];
let reloadCount = 0;
let modalHideCount = 0;
let confirmResult = false;
let failures = 0;

function check(condition, message) {
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
}

class Deferred {
    constructor() { this.doneCallbacks = []; this.failCallbacks = []; this.alwaysCallbacks = []; }
    done(fn) { this.doneCallbacks.push(fn); return this; }
    fail(fn) { this.failCallbacks.push(fn); return this; }
    always(fn) { this.alwaysCallbacks.push(fn); return this; }
    resolve(value) { this.doneCallbacks.forEach((fn) => fn(value)); this.alwaysCallbacks.forEach((fn) => fn()); }
    reject(xhr, status) { this.failCallbacks.forEach((fn) => fn(xhr || {}, status || 'error')); this.alwaysCallbacks.forEach((fn) => fn()); }
}

class Wrapper {
    constructor(name) {
        this.name = name;
        this.dataValues = {};
        this.attrValues = {};
        this.classes = new Set();
        this.value = '';
        this.textValue = '';
        this.disabled = false;
        this.hidden = true;
    }
    data(key, value) { if (arguments.length === 1) return this.dataValues[key]; this.dataValues[key] = value; return this; }
    prop(key, value) { if (key === 'disabled') this.disabled = value; if (key === 'hidden') this.hidden = value; return this; }
    attr(key, value) { if (arguments.length === 1) return this.attrValues[key]; this.attrValues[key] = String(value); return this; }
    val(value) { if (arguments.length === 0) return this.value; this.value = value; return this; }
    addClass(names) { String(names || '').split(/\s+/).filter(Boolean).forEach((name) => this.classes.add(name)); return this; }
    removeClass(names) { String(names || '').split(/\s+/).filter(Boolean).forEach((name) => this.classes.delete(name)); return this; }
    empty() { this.textValue = ''; return this; }
    text(value) { if (arguments.length === 0) return this.textValue; this.textValue = String(value); return this; }
    find(selector) { return getWrapper(this.name + ' ' + selector); }
    closest() { return getWrapper('closest-card'); }
    each() { return this; }
    hide() { return this; }
    fadeIn() { return this; }
    fadeOut() { return this; }
    animate() { return this; }
    popover() { return this; }
    drawer() { return this; }
    modal(method) { if (method === 'hide') modalHideCount += 1; return this; }
    scrollTop() { return 0; }
    off(event, selector) { if (selector) handlers.delete(event + '|' + selector); return this; }
    on(event, selector, callback) {
        if (typeof selector === 'function') { callback = selector; selector = ''; }
        handlers.set(event + '|' + selector, callback);
        return this;
    }
}

function getWrapper(key) {
    if (!wrappers.has(key)) wrappers.set(key, new Wrapper(String(key)));
    return wrappers.get(key);
}

const documentObject = {};
const windowObject = {
    location: {reload: () => { reloadCount += 1; }},
    confirm: () => confirmResult,
    matchMedia: () => ({matches: false})
};

function $(arg) {
    if (typeof arg === 'function') { arg(); return getWrapper('ready'); }
    if (arg === documentObject) return getWrapper('document');
    if (arg === windowObject) return getWrapper('window');
    if (arg instanceof Wrapper) return arg;
    if (typeof arg === 'object') return getWrapper(arg);
    return getWrapper(String(arg));
}
$.extend = (...args) => Object.assign(...args);
$.fn = {drawer: function () {}};
$.ajax = (options) => {
    const deferred = new Deferred();
    ajaxCalls.push({options, deferred});
    return deferred;
};

global.jQuery = $;
global.window = windowObject;
global.document = documentObject;

getWrapper('meta[name="csrf-token"]').attrValues.content = 'csrf-m2d-token';
getWrapper('.changeContentId').value = '12';
getWrapper('.changeContentValue').value = 'https://example.com/updated.xml';
getWrapper('.changeContentStyle').value = 'info';

vm.runInThisContext(source, {filename: 'dashboard.js'});

const deleteHandler = handlers.get('click.iguguruDashboard|.delete_content');
const deleteButton = getWrapper('delete-button');
check(typeof deleteHandler === 'function', 'explicit delete handler is registered');
deleteHandler.call(deleteButton);
check(ajaxCalls.length === 0, 'cancelled delete sends no request');

confirmResult = true;
deleteHandler.call(deleteButton);
deleteHandler.call(deleteButton);
check(ajaxCalls.length === 1, 'double delete click starts one request while pending');
check(ajaxCalls[0].options.data.action === 'content.delete', 'delete keeps content.delete action');
check(ajaxCalls[0].options.data.content_id === '12', 'delete sends the selected content_id');
check(ajaxCalls[0].options.data.csrf_token === 'csrf-m2d-token', 'delete includes the CSRF token');
check(deleteButton.disabled === true, 'pending delete disables its button');
ajaxCalls[0].deferred.resolve({ok: true});
check(reloadCount === 1, 'successful delete keeps page reload behavior');
check(deleteButton.disabled === false, 'delete button is released after completion');

const changeHandler = handlers.get('submit.iguguruDashboard|#changeContentForm');
const changeForm = getWrapper('change-form');
changeHandler.call(changeForm, {preventDefault: () => {}});
check(ajaxCalls[1].options.data.action === 'content.update', 'RSS form submit always uses content.update');
check(ajaxCalls[1].options.data.content_value === 'https://example.com/updated.xml', 'RSS update sends the edited URL');
ajaxCalls[1].deferred.reject({responseJSON: {error: {message: 'Validation failed.'}}}, 'error');
const notice = getWrapper('#app-notice');
check(notice.hidden === false, 'failed mutation displays the shared notice');
check(notice.textValue === 'Validation failed.', 'server error message is shown as text');
check(notice.attrValues.role === 'alert', 'error notice uses alert semantics');

const stockButton = getWrapper('stock-button');
stockButton.attrValues['data-stock-url'] = 'https://example.com/article';
stockButton.attrValues['data-stock-title'] = 'Article title';
const stockHandler = handlers.get('click.iguguruDashboard|.information_modal_dbsave');
stockHandler.call(stockButton);
check(ajaxCalls[2].options.data.action === 'stock.create', 'Stock save keeps stock.create action');
check(ajaxCalls[2].options.data.stock_data === 'https://example.com/article', 'Stock save keeps article URL');
ajaxCalls[2].deferred.resolve({ok: true});
check(modalHideCount === 1, 'Stock modal closes after successful save');
check(notice.textValue === 'Stockへ保存しました', 'Stock success is shown in the shared notice');
check(notice.classes.has('alert-success'), 'Stock success uses success presentation');
check(stockButton.disabled === false, 'Stock button is released after completion');

if (failures > 0) process.exit(1);
console.log('All M2-D mutation runtime checks passed.');
