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

function check(condition, message) {
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) {
        process.exitCode = 1;
    }
}

class Deferred {
    constructor() {
        this.doneCallbacks = [];
        this.failCallbacks = [];
        this.alwaysCallbacks = [];
    }
    done(fn) { this.doneCallbacks.push(fn); return this; }
    fail(fn) { this.failCallbacks.push(fn); return this; }
    always(fn) { this.alwaysCallbacks.push(fn); return this; }
    resolve(value) {
        this.doneCallbacks.forEach((fn) => fn(value));
        this.alwaysCallbacks.forEach((fn) => fn());
    }
    reject(xhr, status) {
        this.failCallbacks.forEach((fn) => fn(xhr || {}, status || 'error'));
        this.alwaysCallbacks.forEach((fn) => fn());
    }
}

class Wrapper {
    constructor(name) {
        this.name = name;
        this.dataValues = {};
        this.attrValues = {};
        this.value = '';
        this.disabled = false;
    }
    data(key, value) {
        if (arguments.length === 1) return this.dataValues[key];
        this.dataValues[key] = value; return this;
    }
    prop(key, value) {
        if (key === 'disabled') this.disabled = value;
        if (key === 'hidden') this.hidden = value;
        return this;
    }
    attr(key, value) {
        if (arguments.length === 1) return this.attrValues[key];
        this.attrValues[key] = value; return this;
    }
    val(value) { if (arguments.length === 0) return this.value; this.value = value; return this; }
    addClass() { return this; }
    removeClass() { return this; }
    empty() { this.textValue = ''; return this; }
    text(value) { if (arguments.length === 0) return this.textValue || ''; this.textValue = String(value); return this; }
    find(selector) { return getWrapper(this.name + ' ' + selector); }
    closest() { return getWrapper('closest-card'); }
    each() { return this; }
    hide() { return this; }
    fadeIn() { return this; }
    fadeOut() { return this; }
    animate() { return this; }
    popover() { return this; }
    drawer() { return this; }
    scrollTop() { return 0; }
    off(event, selector) {
        if (selector) handlers.delete(event + '|' + selector);
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

function getWrapper(key) {
    if (!wrappers.has(key)) wrappers.set(key, new Wrapper(key));
    return wrappers.get(key);
}

const documentObject = {};
const windowObject = { location: { reload: () => { reloadCount += 1; } } };

function $(arg) {
    if (typeof arg === 'function') { arg(); return getWrapper('ready'); }
    if (arg === documentObject) return getWrapper('document');
    if (arg === windowObject) return getWrapper('window');
    if (arg instanceof Wrapper) return arg;
    if (typeof arg === 'object') {
        if (!wrappers.has(arg)) wrappers.set(arg, new Wrapper('object'));
        return wrappers.get(arg);
    }
    return getWrapper(String(arg));
}
$.extend = (...args) => Object.assign(...args);
$.fn = { drawer: function () {} };
$.ajax = (options) => {
    const deferred = new Deferred();
    ajaxCalls.push({ options, deferred });
    return deferred;
};

global.jQuery = $;
global.window = windowObject;
global.document = documentObject;

getWrapper('meta[name="csrf-token"]').attrValues.content = 'csrf-test-token';
getWrapper('.registerContentValue').value = 'https://example.com/feed.xml';
getWrapper('.style_select').value = 'success';
getWrapper('.content_location').value = '2';

vm.runInThisContext(source, { filename: 'dashboard.js' });
const firstHandlerCount = handlers.size;
vm.runInThisContext(source, { filename: 'dashboard-second-load.js' });

check(firstHandlerCount === 17, 'dashboard registers the expected event set');
check(handlers.size === firstHandlerCount, 'loading dashboard twice does not duplicate handlers');

const addHandler = handlers.get('submit.iguguruDashboard|#registerContentForm');
const rawForm = {};
const submitEvent = { preventDefault: () => {} };
addHandler.call(rawForm, submitEvent);
addHandler.call(rawForm, submitEvent);
const submitButton = getWrapper('object button[type="submit"]');

check(ajaxCalls.length === 1, 'double submit starts one API request while pending');
check(submitButton.disabled === true, 'pending action disables its control');
check(ajaxCalls[0].options.url === './api_v1.php', 'runtime request uses the existing API endpoint');
check(ajaxCalls[0].options.method === 'POST', 'runtime request uses POST');
check(ajaxCalls[0].options.data.action === 'content.create', 'runtime request keeps content.create action');
check(ajaxCalls[0].options.data.csrf_token === 'csrf-test-token', 'runtime request includes CSRF token');
check(ajaxCalls[0].options.data.content_location === '2', 'runtime request keeps selected tab location');

ajaxCalls[0].deferred.reject({}, 'timeout');
check(submitButton.disabled === false, 'failed request re-enables its control');
const notice = getWrapper('#app-notice');
check(notice.hidden === false, 'timeout displays the shared notice');
check(notice.textValue === '通信がタイムアウトしました', 'timeout receives a controlled error message');

addHandler.call(rawForm, submitEvent);
check(ajaxCalls.length === 2, 'control can submit again after request completion');
ajaxCalls[1].deferred.resolve({ ok: true });
check(reloadCount === 1, 'successful content change keeps the existing page reload behavior');
check(submitButton.disabled === false, 'successful request also releases pending state');

if (process.exitCode) process.exit(process.exitCode);
console.log('All M2-A dashboard runtime checks passed.');
