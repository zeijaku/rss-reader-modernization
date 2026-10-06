'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const sources = {
    Task: fs.readFileSync(path.join(root, 'public/js/dashboard-task.js'), 'utf8'),
    Memo: fs.readFileSync(path.join(root, 'public/js/dashboard-memo.js'), 'utf8'),
    Game: fs.readFileSync(path.join(root, 'public/js/dashboard-game.js'), 'utf8'),
    Clock: fs.readFileSync(path.join(root, 'public/js/dashboard-clock.js'), 'utf8')
};

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

class Element {
    constructor(name) {
        this.name = name;
        this.attrs = {};
        this.props = {};
        this.value = '';
        this.textValue = '';
        this.childrenBySelector = new Map();
    }
}

class Wrapper {
    constructor(elements = []) {
        this.elements = elements;
    }
    get length() { return this.elements.length; }
    first() { return new Wrapper(this.elements.slice(0, 1)); }
    get(index) { return this.elements[index]; }
    val(value) {
        if (arguments.length === 0) return this.elements[0] ? this.elements[0].value : undefined;
        this.elements.forEach(el => { el.value = String(value); });
        return this;
    }
    attr(name, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].attrs[name] : undefined;
        this.elements.forEach(el => { el.attrs[name] = String(value); });
        return this;
    }
    prop(name, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].props[name] : undefined;
        this.elements.forEach(el => { el.props[name] = value; });
        return this;
    }
    text(value) {
        if (arguments.length === 0) return this.elements[0] ? this.elements[0].textValue : '';
        this.elements.forEach(el => { el.textValue = String(value); });
        return this;
    }
    find(selector) {
        const first = this.elements[0];
        if (!first) return new Wrapper();
        return first.childrenBySelector.get(selector) || new Wrapper();
    }
    closest() {
        const first = this.elements[0];
        return first && first.closestWrapper ? first.closestWrapper : new Wrapper();
    }
    off(eventName, selector) {
        handlers.delete(eventName + '|' + (selector || ''));
        return this;
    }
    on(eventName, selector, callback) {
        if (typeof selector === 'function') {
            callback = selector;
            selector = '';
        }
        handlers.set(eventName + '|' + (selector || ''), callback);
        return this;
    }
}

const documentObject = {};
const handlers = new Map();
const registry = new Map();
const apiCalls = [];
const requestStarts = [];
const requestEnds = [];
let reloads = 0;

function element(name, value = '') {
    const el = new Element(name);
    el.value = value;
    return el;
}

function setSelector(selector, value, props = {}) {
    const el = element(selector, value);
    el.props = Object.assign({}, props);
    const wrapper = new Wrapper([el]);
    registry.set(selector, wrapper);
    return wrapper;
}

function form(name) {
    const el = element(name);
    const button = element(name + ':submit');
    const buttonWrapper = new Wrapper([button]);
    el.childrenBySelector.set('button[type="submit"]', buttonWrapper);
    return {el, wrapper: new Wrapper([el]), button, buttonWrapper};
}

function $(arg) {
    if (arg === documentObject) return documentWrapper;
    if (arg instanceof Element) return new Wrapper([arg]);
    if (typeof arg === 'string') return registry.get(arg) || new Wrapper();
    return new Wrapper();
}

const documentWrapper = new Wrapper([element('document')]);
const windowObject = {
    location: {
        reload() { reloads += 1; }
    },
    confirm() { return true; },
    RssClockTimer: {removeWidgetState() {}},
    RssGameWidget: {removeWidgetState() {}},
    RssMiniGame: {removeWidgetState() {}},
    RssLightsOut: {removeWidgetState() {}}
};

function deferredSuccess() {
    return {
        done(fn) { fn({ok: true}); return this; },
        fail() { return this; },
        always(fn) { fn(); return this; }
    };
}

windowObject.IGuguruDashboardCore = {
    apiRequest(action, data, timeout) {
        apiCalls.push({action, data: Object.assign({}, data), timeout});
        return deferredSuccess();
    },
    apiResponseOk(data) { return Boolean(data && data.ok === true); },
    requestStart(button) { requestStarts.push(button); return true; },
    requestEnd(button) { requestEnds.push(button); },
    requestFail() {},
    showNotice() {}
};

const context = {
    jQuery: $,
    window: windowObject,
    document: documentObject,
    console,
    String,
    Number,
    Object,
    Array,
    RegExp
};

for (const [label, source] of Object.entries(sources)) {
    vm.runInNewContext(source, context, {filename: 'dashboard-' + label.toLowerCase() + '.js'});
}

check(typeof windowObject.IGuguruDashboardTask?.bindEvents === 'function', 'Task split controller exports bindEvents');
check(typeof windowObject.IGuguruDashboardMemo?.bindEvents === 'function', 'Memo split controller exports bindEvents');
check(typeof windowObject.IGuguruDashboardGame?.bindEvents === 'function', 'Game split controller exports bindEvents');
check(typeof windowObject.IGuguruDashboardClock?.bindEvents === 'function', 'Clock split controller exports bindEvents');

for (const module of [
    windowObject.IGuguruDashboardTask,
    windowObject.IGuguruDashboardMemo,
    windowObject.IGuguruDashboardGame,
    windowObject.IGuguruDashboardClock
]) {
    module.bindEvents('.iguguruDashboard');
}

const expectedHandlers = [
    'submit.iguguruDashboard|#registerTaskWidgetForm',
    'click.iguguruDashboard|.task-widget-edit-trigger',
    'submit.iguguruDashboard|#changeTaskWidgetForm',
    'click.iguguruDashboard|.delete_task_widget',
    'submit.iguguruDashboard|.task-item-create-form',
    'click.iguguruDashboard|.task-item-edit-trigger',
    'submit.iguguruDashboard|#changeTaskItemForm',
    'click.iguguruDashboard|.task-toggle',
    'click.iguguruDashboard|.delete_task_item',
    'submit.iguguruDashboard|#registerMemoForm',
    'click.iguguruDashboard|.memo-edit-trigger',
    'submit.iguguruDashboard|#changeMemoForm',
    'click.iguguruDashboard|.delete_memo',
    'submit.iguguruDashboard|#registerGameWidgetForm',
    'change.iguguruDashboard|.registerGameType',
    'change.iguguruDashboard|.changeGameType',
    'click.iguguruDashboard|.mini-game-edit-trigger',
    'submit.iguguruDashboard|#changeGameWidgetForm',
    'click.iguguruDashboard|.delete_game_widget',
    'submit.iguguruDashboard|#registerClockForm',
    'click.iguguruDashboard|.clock-edit-trigger',
    'submit.iguguruDashboard|#changeClockForm',
    'click.iguguruDashboard|.delete_clock'
];
expectedHandlers.forEach(key => check(typeof handlers.get(key) === 'function', 'delegated handler registered: ' + key));
const firstHandlerCount = handlers.size;
windowObject.IGuguruDashboardTask.bindEvents('.iguguruDashboard');
windowObject.IGuguruDashboardMemo.bindEvents('.iguguruDashboard');
windowObject.IGuguruDashboardGame.bindEvents('.iguguruDashboard');
windowObject.IGuguruDashboardClock.bindEvents('.iguguruDashboard');
check(handlers.size === firstHandlerCount, 'rebinding keeps one namespaced handler per selector');

setSelector('.registerTaskWidgetTitleValue', 'Tasks');
setSelector('.registerTaskWidgetStyle', 'primary');
setSelector('.registerTaskWidgetWidth', '2');
setSelector('.registerTaskWidgetHeight', '3');
setSelector('.registerTaskWidgetLocation', '1');
const taskForm = form('task-form');
handlers.get('submit.iguguruDashboard|#registerTaskWidgetForm').call(taskForm.el, {preventDefault() {}});
let call = apiCalls.at(-1);
check(call.action === 'widget.task.create', 'Task create keeps API action');
check(call.data.task_widget_title === 'Tasks' && call.data.widget_location === '1' && call.data.widget_width === '2',
    'Task create keeps payload fields');

setSelector('.registerMemoTitleValue', 'Memo title');
setSelector('.registerMemoBody', 'Memo body');
setSelector('.registerMemoStyle', 'success');
setSelector('.registerMemoWidth', '2');
setSelector('.registerMemoHeight', '2');
setSelector('.registerMemoLocation', '0');
const memoForm = form('memo-form');
handlers.get('submit.iguguruDashboard|#registerMemoForm').call(memoForm.el, {preventDefault() {}});
call = apiCalls.at(-1);
check(call.action === 'widget.memo.create', 'Memo create keeps API action');
check(call.data.memo_title === 'Memo title' && call.data.memo_body === 'Memo body' && call.data.widget_location === '0',
    'Memo create keeps payload fields');

setSelector('.registerGameTitleValue', 'Maze Chase');
const registerGameType = setSelector('.registerGameType', 'maze_chase');
registerGameType.attr('data-previous-game-type', 'icon_quest');
setSelector('.registerGameStyle', 'secondary');
setSelector('.registerGameWidth', '2');
setSelector('.registerGameHeight', '2');
setSelector('.registerGameLocation', '3');
const gameForm = form('game-form');
handlers.get('submit.iguguruDashboard|#registerGameWidgetForm').call(gameForm.el, {preventDefault() {}});
call = apiCalls.at(-1);
check(call.action === 'widget.game.create', 'Game create keeps API action');
check(call.data.game_type === 'maze_chase' && call.data.game_title === 'Maze Chase' && call.data.widget_location === '3',
    'Game create keeps payload fields');

setSelector('.registerGameTitleValue', 'Icon Quest');
registerGameType.val('maze_chase').attr('data-previous-game-type', 'icon_quest');
handlers.get('change.iguguruDashboard|.registerGameType').call(registerGameType.get(0), {});
check(registry.get('.registerGameTitleValue').val() === 'Maze Chase', 'Game type change keeps automatic default-title behavior');

setSelector('.registerClockName', 'Clock');
setSelector('.registerClockHourFormat', '24');
setSelector('.registerClockShowSeconds', '', {checked: true});
setSelector('.registerClockShowDate', '', {checked: false});
setSelector('.registerClockStyle', 'primary');
setSelector('.registerClockWidth', '1');
setSelector('.registerClockHeight', '2');
setSelector('.registerClockLocation', '2');
const clockForm = form('clock-form');
handlers.get('submit.iguguruDashboard|#registerClockForm').call(clockForm.el, {preventDefault() {}});
call = apiCalls.at(-1);
check(call.action === 'widget.clock.create', 'Clock create keeps API action');
check(call.data.clock_show_seconds === '1' && call.data.clock_show_date === '0' && call.data.widget_location === '2',
    'Clock create keeps checkbox and location payload semantics');

check(requestStarts.length === 4 && requestEnds.length === 4, 'split create mutations preserve pending-request lifecycle');
check(reloads === 4, 'successful split create mutations keep reload behavior');

console.log('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0');
process.exit(failed === 0 ? 0 : 1);
