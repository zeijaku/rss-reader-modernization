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
    constructor(selectors = [], attrs = {}) {
        this.selectors = new Set(selectors);
        this.attrs = Object.assign({}, attrs);
        this.children = [];
        this.parentElement = null;
        this.localQueries = new Map();
        this.value = '';
        this.checked = false;
        this.disabled = false;
        this.textContent = '';
    }

    appendChild(child) {
        child.parentElement = this;
        this.children.push(child);
        return child;
    }

    matches(selector) {
        return String(selector).split(',').some((item) => this.selectors.has(item.trim()));
    }

    closest(selector) {
        let current = this;
        while (current) {
            if (current.matches(selector)) {
                return current;
            }
            current = current.parentElement;
        }
        return null;
    }

    querySelector(selector) {
        if (this.localQueries.has(selector)) {
            return this.localQueries.get(selector);
        }
        for (const child of this.children) {
            if (child.matches(selector)) {
                return child;
            }
            const nested = child.querySelector(selector);
            if (nested) {
                return nested;
            }
        }
        return null;
    }

    getAttribute(name) {
        return Object.prototype.hasOwnProperty.call(this.attrs, name) ? String(this.attrs[name]) : null;
    }

    setAttribute(name, value) {
        this.attrs[name] = String(value);
    }
}

class DocumentHarness {
    constructor() {
        this.queries = new Map();
        this.handlers = new Map();
    }

    querySelector(selector) {
        return this.queries.get(selector) || null;
    }

    add(selector, element) {
        this.queries.set(selector, element);
        element.selectors.add(selector);
        return element;
    }

    addEventListener(type, handler) {
        const list = this.handlers.get(type) || [];
        list.push(handler);
        this.handlers.set(type, list);
    }

    listenerCount(type) {
        return (this.handlers.get(type) || []).length;
    }

    dispatch(type, target) {
        const event = {
            type,
            target,
            prevented: false,
            preventDefault() {
                this.prevented = true;
            }
        };
        for (const handler of this.handlers.get(type) || []) {
            handler(event);
        }
        return event;
    }
}

function control(documentObject, selector, value = '', attrs = {}) {
    const element = new Element([selector], attrs);
    element.value = String(value);
    documentObject.add(selector, element);
    return element;
}

function form(documentObject, selector, attrs = {}) {
    const element = new Element([selector], attrs);
    const button = new Element(['button[type="submit"]']);
    element.localQueries.set('button[type="submit"]', button);
    element.appendChild(button);
    documentObject.add(selector, element);
    return {element, button};
}

function localControl(scope, selector, value = '') {
    const element = new Element([selector]);
    element.value = String(value);
    scope.localQueries.set(selector, element);
    scope.appendChild(element);
    return element;
}

async function flush() {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
}

(async () => {
    const documentObject = new DocumentHarness();
    const apiCalls = [];
    const requestStarts = [];
    const requestEnds = [];
    const failures = [];
    const notices = [];
    const gameStateCalls = [];
    let reloads = 0;

    const windowObject = {
        location: {
            reload() {
                reloads += 1;
            }
        },
        confirm() {
            return true;
        },
        RssClockTimer: {
            removeWidgetState(id) {
                gameStateCalls.push('clock:' + id);
            }
        },
        RssGameWidget: {
            removeWidgetState(id) {
                gameStateCalls.push('game:' + id);
            }
        },
        RssMiniGame: {
            removeWidgetState(id) {
                gameStateCalls.push('icon:' + id);
            }
        },
        RssLightsOut: {
            removeWidgetState(id) {
                gameStateCalls.push('lights:' + id);
            }
        }
    };

    windowObject.IGuguruDashboardCore = {
        apiRequestPromise(action, data, timeout) {
            apiCalls.push({action, data: Object.assign({}, data), timeout});
            return Promise.resolve({ok: true});
        },
        apiResponseOk(data) {
            return Boolean(data && data.ok === true);
        },
        requestStartElement(button) {
            if (!button || button.getAttribute('data-request-pending') === 'true') {
                return false;
            }
            button.setAttribute('data-request-pending', 'true');
            button.disabled = true;
            requestStarts.push(button);
            return true;
        },
        requestEndElement(button) {
            if (button) {
                button.setAttribute('data-request-pending', 'false');
                button.disabled = false;
            }
            requestEnds.push(button);
        },
        requestFailReason(reason) {
            failures.push(reason);
        },
        showNotice(message, type) {
            notices.push({message, type});
        }
    };

    const context = {
        window: windowObject,
        document: documentObject,
        console,
        Promise,
        String,
        Number,
        Object,
        Array,
        RegExp,
        Boolean
    };

    for (const [label, source] of Object.entries(sources)) {
        vm.runInNewContext(source, context, {filename: 'dashboard-' + label.toLowerCase() + '.js'});
    }

    const modules = [
        windowObject.IGuguruDashboardTask,
        windowObject.IGuguruDashboardMemo,
        windowObject.IGuguruDashboardGame,
        windowObject.IGuguruDashboardClock
    ];
    modules.forEach((module) => module.bindEvents('.iguguruDashboard'));

    check(documentObject.listenerCount('submit') === 4, 'four native delegated submit listeners are registered');
    check(documentObject.listenerCount('click') === 4, 'four native delegated click listeners are registered');
    check(documentObject.listenerCount('change') === 1, 'Game registers one native delegated change listener');

    modules.forEach((module) => module.bindEvents('.iguguruDashboard'));
    check(documentObject.listenerCount('submit') === 4, 'rebinding does not duplicate native submit listeners');
    check(documentObject.listenerCount('click') === 4, 'rebinding does not duplicate native click listeners');
    check(documentObject.listenerCount('change') === 1, 'rebinding does not duplicate native change listeners');

    control(documentObject, '.registerTaskWidgetTitleValue', 'Tasks');
    control(documentObject, '.registerTaskWidgetStyle', 'primary');
    control(documentObject, '.registerTaskWidgetWidth', '2');
    control(documentObject, '.registerTaskWidgetHeight', '3');
    control(documentObject, '.registerTaskWidgetLocation', '1');
    const taskRegister = form(documentObject, '#registerTaskWidgetForm');
    const taskSubmit = documentObject.dispatch('submit', taskRegister.element);
    check(taskSubmit.prevented, 'Task create prevents native form submission');
    await flush();
    let call = apiCalls.at(-1);
    check(call.action === 'widget.task.create', 'Task create preserves API action');
    check(call.data.task_widget_title === 'Tasks' && call.data.widget_location === '1' && call.data.widget_width === '2',
        'Task create preserves payload fields');
    check(taskRegister.button.disabled === false, 'Task create releases the native pending guard');

    control(documentObject, '.registerMemoTitleValue', 'Memo title');
    control(documentObject, '.registerMemoBody', 'Memo body');
    control(documentObject, '.registerMemoStyle', 'success');
    control(documentObject, '.registerMemoWidth', '2');
    control(documentObject, '.registerMemoHeight', '2');
    control(documentObject, '.registerMemoLocation', '0');
    const memoRegister = form(documentObject, '#registerMemoForm');
    documentObject.dispatch('submit', memoRegister.element);
    documentObject.dispatch('submit', memoRegister.element);
    check(apiCalls.filter((entry) => entry.action === 'widget.memo.create').length === 1,
        'Memo duplicate submit is blocked while the native request guard is pending');
    await flush();
    call = apiCalls.at(-1);
    check(call.action === 'widget.memo.create', 'Memo create preserves API action');
    check(call.data.memo_title === 'Memo title' && call.data.memo_body === 'Memo body' && call.data.widget_location === '0',
        'Memo create preserves payload fields');

    control(documentObject, '.registerGameTitleValue', 'Icon Quest');
    const registerGameType = control(documentObject, '.registerGameType', 'maze_chase', {'data-previous-game-type': 'icon_quest'});
    control(documentObject, '.registerGameStyle', 'secondary');
    control(documentObject, '.registerGameWidth', '2');
    control(documentObject, '.registerGameHeight', '2');
    control(documentObject, '.registerGameLocation', '3');
    documentObject.dispatch('change', registerGameType);
    check(documentObject.querySelector('.registerGameTitleValue').value === 'Maze Chase',
        'Game type change preserves automatic default-title behavior');
    const gameRegister = form(documentObject, '#registerGameWidgetForm');
    documentObject.dispatch('submit', gameRegister.element);
    await flush();
    call = apiCalls.at(-1);
    check(call.action === 'widget.game.create', 'Game create preserves API action');
    check(call.data.game_type === 'maze_chase' && call.data.game_title === 'Maze Chase' && call.data.widget_location === '3',
        'Game create preserves payload fields');

    control(documentObject, '.registerClockName', 'Clock');
    control(documentObject, '.registerClockHourFormat', '24');
    const showSeconds = control(documentObject, '.registerClockShowSeconds');
    const showDate = control(documentObject, '.registerClockShowDate');
    showSeconds.checked = true;
    showDate.checked = false;
    control(documentObject, '.registerClockStyle', 'primary');
    control(documentObject, '.registerClockWidth', '1');
    control(documentObject, '.registerClockHeight', '2');
    control(documentObject, '.registerClockLocation', '2');
    const clockRegister = form(documentObject, '#registerClockForm');
    documentObject.dispatch('submit', clockRegister.element);
    await flush();
    call = apiCalls.at(-1);
    check(call.action === 'widget.clock.create', 'Clock create preserves API action');
    check(call.data.clock_show_seconds === '1' && call.data.clock_show_date === '0' && call.data.widget_location === '2',
        'Clock create preserves checkbox and location payload semantics');

    const changeMemoWidgetId = control(documentObject, '.changeMemoWidgetId', '');
    control(documentObject, '.changeMemoId', '');
    const changeMemoTitle = control(documentObject, '.changeMemoTitleValue', '');
    const changeMemoBody = control(documentObject, '.changeMemoBody', '');
    control(documentObject, '.changeMemoStyle', '');
    control(documentObject, '.changeMemoWidth', '');
    control(documentObject, '.changeMemoHeight', '');
    const memoCard = new Element(['[data-dashboard-widget-type="memo"]']);
    const memoTitle = new Element(['.memo-title']);
    memoTitle.textContent = 'Existing memo';
    const memoBody = new Element(['.memo-body']);
    memoBody.textContent = '<b>text only</b>';
    memoCard.localQueries.set('.memo-title', memoTitle);
    memoCard.localQueries.set('.memo-body', memoBody);
    memoCard.appendChild(memoTitle);
    memoCard.appendChild(memoBody);
    const memoEdit = new Element(['.memo-edit-trigger'], {
        'data-widget-id': '51',
        'data-memo-id': '61',
        'data-widget-style': 'info',
        'data-widget-width': '2',
        'data-widget-height': '1'
    });
    const memoEditIcon = new Element(['.memo-edit-icon']);
    memoEdit.appendChild(memoEditIcon);
    memoCard.appendChild(memoEdit);
    documentObject.dispatch('click', memoEditIcon);
    check(changeMemoWidgetId.value === '51' && changeMemoTitle.value === 'Existing memo',
        'Memo edit works through delegated closest() from a nested click target');
    check(changeMemoBody.value === '<b>text only</b>', 'Memo edit keeps textContent and never interprets stored HTML');

    // Task Item partial-refresh behavior is covered by test_current_task_partial_refresh_runtime.js.

    const changeClockId = control(documentObject, '.changeClockId', '');
    const changeClockName = control(documentObject, '.changeClockName', '');
    control(documentObject, '.changeClockHourFormat', '');
    const changeSeconds = control(documentObject, '.changeClockShowSeconds');
    const changeDate = control(documentObject, '.changeClockShowDate');
    control(documentObject, '.changeClockStyle', '');
    control(documentObject, '.changeClockWidth', '');
    control(documentObject, '.changeClockHeight', '');
    const clockEdit = new Element(['.clock-edit-trigger'], {
        'data-widget-id': '81',
        'data-clock-title': 'Office',
        'data-clock-hour-format': '12',
        'data-clock-show-seconds': '1',
        'data-clock-show-date': '0',
        'data-widget-style': 'warning',
        'data-widget-width': '2',
        'data-widget-height': '2'
    });
    documentObject.dispatch('click', clockEdit);
    check(changeClockId.value === '81' && changeClockName.value === 'Office',
        'Clock edit preserves Widget ID and title');
    check(changeSeconds.checked === true && changeDate.checked === false,
        'Clock edit preserves checkbox state');

    control(documentObject, '.changeGameWidgetId', '91');
    control(documentObject, '.changeGameTitleValue', 'Maze Chase');
    const changeGameType = control(documentObject, '.changeGameType', 'falling_blocks', {
        'data-previous-game-type': 'maze_chase',
        'data-original-game-type': 'icon_quest'
    });
    control(documentObject, '.changeGameStyle', 'secondary');
    control(documentObject, '.changeGameWidth', '2');
    control(documentObject, '.changeGameHeight', '2');
    const gameChange = form(documentObject, '#changeGameWidgetForm');
    documentObject.dispatch('submit', gameChange.element);
    await flush();
    call = apiCalls.at(-1);
    check(call.action === 'widget.game.update' && call.data.widget_id === '91' && call.data.game_type === 'falling_blocks',
        'Game update preserves Widget ID and selected type');
    check(gameStateCalls.includes('icon:91'), 'Game type change preserves old-family Browser state cleanup');

    const gameDelete = new Element(['.delete_game_widget']);
    documentObject.dispatch('click', gameDelete);
    await flush();
    call = apiCalls.at(-1);
    check(call.action === 'widget.game.delete' && call.data.widget_id === '91', 'Game delete preserves Widget ID payload');
    check(gameStateCalls.includes('game:91') && gameStateCalls.includes('lights:91'),
        'Game delete preserves multi-family Browser state cleanup');

    const clockDelete = new Element(['.delete_clock']);
    documentObject.dispatch('click', clockDelete);
    await flush();
    call = apiCalls.at(-1);
    check(call.action === 'widget.clock.delete' && call.data.widget_id === '81', 'Clock delete preserves Widget ID payload');
    check(gameStateCalls.includes('clock:81'), 'Clock delete preserves Timer Browser state cleanup');

    check(failures.length === 0, 'native controller success path does not invoke request failure handling');
    check(notices.length === 0, 'valid native controller workflow does not emit error notices');
    check(requestStarts.length === requestEnds.length, 'every started native mutation releases its pending guard');
    check(reloads >= 7, 'Widget-level native mutations preserve existing reload behavior');

    console.log('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0');
    process.exit(failed === 0 ? 0 : 1);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
