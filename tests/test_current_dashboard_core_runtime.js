'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard-core.js'), 'utf8');
const handlers = new Map();
const ajaxCalls = [];
const timers = [];
let reloadCount = 0;
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        console.log('PASS: ' + message);
        return;
    }
    failed += 1;
    console.error('FAIL: ' + message);
}

class Wrapper {
    constructor(name) {
        this.name = name;
        this.length = 1;
        this.attrs = {};
        this.props = {};
        this.dataValues = {};
        this.classes = new Set();
        this.textValue = '';
    }

    attr(name, value) {
        if (arguments.length === 1) {
            return this.attrs[name];
        }
        this.attrs[name] = String(value);
        return this;
    }

    prop(name, value) {
        if (arguments.length === 1) {
            return this.props[name];
        }
        this.props[name] = value;
        return this;
    }

    data(name, value) {
        if (arguments.length === 1) {
            return this.dataValues[name];
        }
        this.dataValues[name] = value;
        return this;
    }

    addClass(names) {
        String(names || '').split(/\s+/).filter(Boolean).forEach((name) => this.classes.add(name));
        return this;
    }

    removeClass(names) {
        String(names || '').split(/\s+/).filter(Boolean).forEach((name) => this.classes.delete(name));
        return this;
    }

    text(value) {
        if (arguments.length === 0) {
            return this.textValue;
        }
        this.textValue = String(value);
        return this;
    }

    empty() {
        this.textValue = '';
        return this;
    }

    off(eventName) {
        handlers.delete(eventName);
        return this;
    }

    on(eventName, callback) {
        handlers.set(eventName, callback);
        return this;
    }
}

const meta = new Wrapper('meta');
meta.attrs.content = 'csrf-original';
const notice = new Wrapper('notice');
notice.props.hidden = true;
const button = new Wrapper('button');
const documentWrapper = new Wrapper('document');
const emptyWrapper = new Wrapper('empty');
emptyWrapper.length = 0;

const documentObject = {};
const windowObject = {
    location: {
        reload() {
            reloadCount += 1;
        }
    },
    setTimeout(callback, delay) {
        const timer = {callback, delay, cleared: false};
        timers.push(timer);
        return timer;
    },
    clearTimeout(timer) {
        if (timer) {
            timer.cleared = true;
        }
    }
};

function $(value) {
    if (value === documentObject) {
        return documentWrapper;
    }
    if (value === 'meta[name="csrf-token"]') {
        return meta;
    }
    if (value === '#app-notice') {
        return notice;
    }
    if (value === button) {
        return button;
    }
    return emptyWrapper;
}

$.extend = (...args) => Object.assign(...args);
$.ajax = (options) => {
    ajaxCalls.push(options);
    return {options};
};

const context = {
    jQuery: $,
    window: windowObject,
    document: documentObject,
    console,
    Number,
    String,
    Object,
    Array,
    RegExp
};

vm.runInNewContext(source, context, {filename: 'dashboard-core.js'});

const core = windowObject.IGuguruDashboardCore;
check(Boolean(core), 'Dashboard core exports its namespace');

const request = core.apiRequest('content.create', {content_value: 'https://example.com/feed.xml'}, 3000);
check(ajaxCalls.length === 1 && request.options === ajaxCalls[0], 'API helper returns the underlying request object');
check(ajaxCalls[0].url === './api_v1.php', 'API helper keeps the existing endpoint');
check(ajaxCalls[0].method === 'POST' && ajaxCalls[0].dataType === 'json', 'API helper keeps POST JSON-response transport');
check(ajaxCalls[0].timeout === 3000, 'API helper keeps caller timeout');
check(ajaxCalls[0].data.action === 'content.create', 'API helper keeps the requested action');
check(ajaxCalls[0].data.csrf_token === 'csrf-original', 'API helper injects the current CSRF token');
check(ajaxCalls[0].data.content_value === 'https://example.com/feed.xml', 'API helper preserves caller payload');

check(core.apiErrorMessage({}, 'timeout') === '通信がタイムアウトしました', 'timeout keeps the controlled error message');
check(
    core.apiErrorMessage({responseJSON: {error: {message: 'Controlled API error'}}}, 'error') === 'Controlled API error',
    'API error message is preserved when supplied by the backend'
);
check(core.apiErrorMessage({}, 'error') === '通信に失敗しました', 'generic request failure keeps the controlled fallback');

core.showNotice('保存しました', 'success', 2500);
check(notice.props.hidden === false && notice.textValue === '保存しました', 'shared notice renders text and becomes visible');
check(notice.attrs.role === 'status' && notice.classes.has('alert-success'), 'success notice keeps accessible status semantics');
check(timers.length === 1 && timers[0].delay === 2500, 'explicit notice timeout is scheduled');
timers[0].callback();
check(notice.props.hidden === true && notice.textValue === '', 'notice timer clears only the current message');

notice.props.hidden = false;
notice.textValue = '古いメッセージ';
check(core.requestStart(button) === true, 'first mutation request acquires the pending guard');
check(button.dataValues['request-pending'] === true && button.props.disabled === true, 'pending guard disables the control');
check(notice.props.hidden === true && notice.textValue === '', 'starting a request clears an old shared notice');
check(core.requestStart(button) === false, 'duplicate mutation request is rejected while pending');
core.requestEnd(button);
check(button.dataValues['request-pending'] === false && button.props.disabled === false, 'request completion releases the pending guard');

core.apiResponseOk({ok: false, error: {message: '入力を確認してください'}});
check(notice.textValue === '入力を確認してください', 'failed API response surfaces the controlled backend message');
check(notice.attrs.role === 'alert' && notice.classes.has('alert-danger'), 'failed API response uses alert semantics');
check(core.apiResponseOk({ok: true}) === true, 'successful API response is accepted');

core.initCsrfSessionSync('.dashboardCoreTest');
check(handlers.has('ajaxComplete.dashboardCoreTest'), 'CSRF sync registers a namespaced completion handler');
check(handlers.has('ajaxError.dashboardCoreTest'), 'auth sync registers a namespaced error handler');

const complete = handlers.get('ajaxComplete.dashboardCoreTest');
complete({}, {
    getResponseHeader(name) {
        return name === 'X-CSRF-Token' ? 'a'.repeat(64) : '';
    }
}, {url: './api_v1.php'});
check(meta.attrs.content === 'a'.repeat(64), 'valid rotated CSRF token replaces the meta token');

complete({}, {
    getResponseHeader() {
        return 'not-a-valid-token';
    }
}, {url: './api_v1.php'});
check(meta.attrs.content === 'a'.repeat(64), 'invalid rotated CSRF token is ignored');

const authError = handlers.get('ajaxError.dashboardCoreTest');
authError({}, {status: 401, responseJSON: {error: {code: 'unauthenticated'}}}, {url: './other.php'});
check(reloadCount === 0, 'non-API 401 does not trigger Dashboard authentication reload');
authError({}, {status: 401, responseJSON: {error: {code: 'unauthenticated'}}}, {url: './api_v1.php'});
authError({}, {status: 401, responseJSON: {error: {code: 'unauthenticated'}}}, {url: './api_v1.php'});
check(reloadCount === 1, 'unauthenticated API response reloads at most once per initialized handler');

console.log('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0');
process.exit(failed === 0 ? 0 : 1);
