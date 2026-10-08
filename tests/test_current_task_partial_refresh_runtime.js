'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard-task.js'), 'utf8');

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

function splitSelectorList(selector) {
    return String(selector).split(',').map((part) => part.trim()).filter(Boolean);
}

function matchesSimple(element, selector) {
    if (!element || element.tagName === '#text') return false;
    if (selector.startsWith('#')) {
        return element.getAttribute('id') === selector.slice(1);
    }

    const tagAttr = selector.match(/^([a-zA-Z0-9_-]+)\[([^=]+)="([^"]*)"\]$/);
    if (tagAttr) {
        return element.tagName.toLowerCase() === tagAttr[1].toLowerCase()
            && element.getAttribute(tagAttr[2]) === tagAttr[3];
    }

    const classAttr = selector.match(/^\.([a-zA-Z0-9_-]+)\[([^=]+)="([^"]*)"\]$/);
    if (classAttr) {
        return element.classes.has(classAttr[1]) && element.getAttribute(classAttr[2]) === classAttr[3];
    }

    const attr = selector.match(/^\[([^=]+)="([^"]*)"\]$/);
    if (attr) {
        return element.getAttribute(attr[1]) === attr[2];
    }

    if (selector.startsWith('.')) {
        return element.classes.has(selector.slice(1));
    }

    return element.tagName.toLowerCase() === selector.toLowerCase();
}

function matchesSelector(element, selector) {
    return splitSelectorList(selector).some((part) => {
        const tokens = part.split(/\s+/).filter(Boolean);
        if (tokens.length === 1) {
            return matchesSimple(element, tokens[0]);
        }
        if (!matchesSimple(element, tokens[tokens.length - 1])) {
            return false;
        }
        let ancestor = element.parentElement;
        for (let index = tokens.length - 2; index >= 0; index -= 1) {
            while (ancestor && !matchesSimple(ancestor, tokens[index])) {
                ancestor = ancestor.parentElement;
            }
            if (!ancestor) {
                return false;
            }
            ancestor = ancestor.parentElement;
        }
        return true;
    });
}

function descendants(rootElement) {
    const output = [];
    for (const child of rootElement.children) {
        output.push(child);
        output.push(...descendants(child));
    }
    return output;
}

class Element {
    constructor(tagName = 'div', attrs = {}) {
        this.tagName = tagName;
        this.attrs = {};
        this.classes = new Set();
        this.children = [];
        this.parentElement = null;
        this.localQueries = new Map();
        this.value = '';
        this.checked = false;
        this.disabled = false;
        this.textContent = '';
        this.type = '';
        this.title = '';
        this.focused = false;
        this.clickCount = 0;
        this.resetCount = 0;
        Object.entries(attrs).forEach(([name, value]) => this.setAttribute(name, value));
    }

    set className(value) {
        this.classes = new Set(String(value || '').split(/\s+/).filter(Boolean));
        this.attrs.class = Array.from(this.classes).join(' ');
    }

    get className() {
        return Array.from(this.classes).join(' ');
    }

    get firstChild() {
        return this.children[0] || null;
    }

    setAttribute(name, value) {
        this.attrs[name] = String(value);
        if (name === 'class') {
            this.className = value;
        }
        if (name === 'id') {
            this.id = String(value);
        }
    }

    getAttribute(name) {
        return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
    }

    appendChild(child) {
        if (child.parentElement) {
            child.parentElement.removeChild(child);
        }
        child.parentElement = this;
        this.children.push(child);
        return child;
    }

    removeChild(child) {
        const index = this.children.indexOf(child);
        if (index >= 0) {
            this.children.splice(index, 1);
            child.parentElement = null;
        }
        return child;
    }

    matches(selector) {
        return matchesSelector(this, selector);
    }

    closest(selector) {
        let current = this;
        while (current) {
            if (matchesSelector(current, selector)) {
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
        for (const item of descendants(this)) {
            if (matchesSelector(item, selector)) {
                return item;
            }
        }
        return null;
    }

    reset() {
        this.resetCount += 1;
    }

    click() {
        this.clickCount += 1;
    }

    focus() {
        this.focused = true;
    }
}

class TextNode extends Element {
    constructor(text) {
        super('#text');
        this.textContent = String(text);
    }
}

class DocumentHarness {
    constructor() {
        this.roots = [];
        this.handlers = new Map();
    }

    addRoot(element) {
        this.roots.push(element);
        return element;
    }

    querySelector(selector) {
        for (const rootElement of this.roots) {
            if (matchesSelector(rootElement, selector)) {
                return rootElement;
            }
            const match = rootElement.querySelector(selector);
            if (match) {
                return match;
            }
        }
        return null;
    }

    createElement(tagName) {
        return new Element(tagName);
    }

    createTextNode(text) {
        return new TextNode(text);
    }

    addEventListener(type, handler) {
        const list = this.handlers.get(type) || [];
        list.push(handler);
        this.handlers.set(type, list);
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

function child(parent, tagName, className = '', attrs = {}) {
    const element = new Element(tagName, attrs);
    element.className = className;
    parent.appendChild(element);
    return element;
}

function formControl(parent, className, value = '') {
    const control = child(parent, 'input', className);
    control.value = String(value);
    return control;
}

function taskItemShell(list, taskId) {
    const item = child(list, 'li', 'task-item', {'data-task-id': String(taskId)});
    return item;
}

async function flush() {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
}

(async () => {
    const documentObject = new DocumentHarness();
    const apiCalls = [];
    const notices = [];
    let reloads = 0;
    let nextTaskId = 73;
    let tasks = [
        {task_id: 71, title: 'Original', due_date: '', priority: 'normal', completed: false},
        {task_id: 72, title: 'Done later', due_date: '2026-10-09', priority: 'low', completed: false}
    ];

    const taskCard = new Element('section', {
        class: 'dashboard-widget task-card',
        'data-dashboard-widget-id': '44',
        'data-dashboard-widget-type': 'task',
        'data-dashboard-widget-location': '0'
    });
    const taskList = child(taskCard, 'ul', 'task-list');
    taskItemShell(taskList, 71);
    taskItemShell(taskList, 72);

    const createForm = child(taskCard, 'form', 'task-item-create-form', {'data-widget-id': '44'});
    const createTitle = formControl(createForm, 'task-create-title', '<img src=x onerror=alert(1)>');
    const createDue = formControl(createForm, 'task-create-due', '2026-10-10');
    const createPriority = child(createForm, 'select', 'task-create-priority');
    createPriority.value = 'high';
    const createButton = child(createForm, 'button', 'task-create-submit', {type: 'submit'});
    createForm.localQueries.set('button[type="submit"]', createButton);
    createForm.localQueries.set('.task-create-title, .changeTaskItemTitleValue', createTitle);
    createForm.localQueries.set('.task-create-due, .changeTaskItemDueDate', createDue);
    createForm.localQueries.set('.task-create-priority, .changeTaskItemPriority', createPriority);
    documentObject.addRoot(taskCard);

    const modal = new Element('div', {id: 'changeTaskItem', class: 'modal'});
    const dismiss = child(modal, 'button', 'btn-close', {'data-bs-dismiss': 'modal'});
    const changeForm = child(modal, 'form', '', {id: 'changeTaskItemForm'});
    const changeId = formControl(changeForm, 'changeTaskItemId', '71');
    const changeTitle = formControl(changeForm, 'changeTaskItemTitleValue', 'Changed task');
    const changeDue = formControl(changeForm, 'changeTaskItemDueDate', '2026-10-08');
    const changePriority = child(changeForm, 'select', 'changeTaskItemPriority');
    changePriority.value = 'high';
    const deleteButton = child(changeForm, 'button', 'delete_task_item');
    const changeSubmit = child(changeForm, 'button', '', {type: 'submit'});
    changeForm.localQueries.set('button[type="submit"]', changeSubmit);
    changeForm.localQueries.set('.task-create-title, .changeTaskItemTitleValue', changeTitle);
    changeForm.localQueries.set('.task-create-due, .changeTaskItemDueDate', changeDue);
    changeForm.localQueries.set('.task-create-priority, .changeTaskItemPriority', changePriority);
    documentObject.addRoot(modal);

    const windowObject = {
        location: {
            reload() {
                reloads += 1;
            }
        },
        confirm() {
            return true;
        }
    };

    function sortedTasks() {
        return tasks.slice().sort((a, b) => {
            if (Boolean(a.completed) !== Boolean(b.completed)) {
                return a.completed ? 1 : -1;
            }
            return a.task_id - b.task_id;
        });
    }

    windowObject.IGuguruDashboardCore = {
        apiRequestPromise(action, data, timeout) {
            apiCalls.push({action, data: Object.assign({}, data), timeout});
            if (action === 'task.item.create') {
                tasks.push({
                    task_id: nextTaskId++,
                    title: String(data.task_title || ''),
                    due_date: String(data.task_due_date || ''),
                    priority: String(data.task_priority || 'normal'),
                    completed: false
                });
                return Promise.resolve({ok: true, data: {task_id: nextTaskId - 1, widget_id: 44}});
            }
            if (action === 'task.item.update') {
                tasks = tasks.map((task) => task.task_id === Number(data.task_id)
                    ? Object.assign({}, task, {
                        title: String(data.task_title || ''),
                        due_date: String(data.task_due_date || ''),
                        priority: String(data.task_priority || 'normal')
                    })
                    : task);
                return Promise.resolve({ok: true, data: {task_id: Number(data.task_id)}});
            }
            if (action === 'task.item.toggle') {
                tasks = tasks.map((task) => task.task_id === Number(data.task_id)
                    ? Object.assign({}, task, {completed: String(data.task_completed) === '1'})
                    : task);
                return Promise.resolve({ok: true, data: {task_id: Number(data.task_id), completed: String(data.task_completed) === '1'}});
            }
            if (action === 'task.item.delete') {
                tasks = tasks.filter((task) => task.task_id !== Number(data.task_id));
                return Promise.resolve({ok: true, data: {task_id: Number(data.task_id)}});
            }
            if (action === 'widget.list') {
                return Promise.resolve({
                    ok: true,
                    data: {
                        widgets: [{
                            widget_id: 44,
                            widget_type: 'task',
                            widget_location: 0,
                            tasks: sortedTasks()
                        }]
                    }
                });
            }
            return Promise.resolve({ok: true, data: {}});
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
            return true;
        },
        requestEndElement(button) {
            if (button) {
                button.setAttribute('data-request-pending', 'false');
                button.disabled = false;
            }
        },
        requestFailReason(reason) {
            notices.push({message: 'request failed', type: 'danger', reason});
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

    vm.runInNewContext(source, context, {filename: 'dashboard-task.js'});
    windowObject.IGuguruDashboardTask.bindEvents();

    const updateEvent = documentObject.dispatch('submit', changeForm);
    check(updateEvent.prevented, 'Task Item update prevents full HTML form submission');
    await flush();
    check(reloads === 0, 'Task Item update does not reload the whole page');
    check(apiCalls.slice(-2).map((call) => call.action).join(',') === 'task.item.update,widget.list',
        'Task Item update is followed by a scoped Widget refresh');
    let updatedTitle = taskList.querySelector('.task-item-title');
    check(updatedTitle && updatedTitle.textContent === 'Changed task',
        'Task Item update redraws the Task Widget with the saved title');
    check(dismiss.clickCount === 1, 'Task Item update closes the edit modal after redraw');

    const createEvent = documentObject.dispatch('submit', createForm);
    check(createEvent.prevented, 'Task Item create prevents full HTML form submission');
    await flush();
    check(reloads === 0, 'Task Item create does not reload the whole page');
    check(apiCalls.slice(-2).map((call) => call.action).join(',') === 'task.item.create,widget.list',
        'Task Item create is followed by a scoped Widget refresh');
    const injectedTitle = descendants(taskList).find((element) =>
        element.classes.has('task-item-title') && element.textContent === '<img src=x onerror=alert(1)>'
    );
    check(Boolean(injectedTitle), 'Task Item redraw keeps untrusted title as textContent');
    check(createForm.resetCount === 1, 'Task Item create resets only its local form after redraw');

    const toggle = taskList.querySelector('.task-toggle[data-task-id="72"]');
    check(Boolean(toggle), 'Task Item toggle exists after partial redraw');
    documentObject.dispatch('click', toggle);
    await flush();
    check(reloads === 0, 'Task completion toggle does not reload the whole page');
    check(apiCalls.slice(-2).map((call) => call.action).join(',') === 'task.item.toggle,widget.list',
        'Task completion toggle is followed by a scoped Widget refresh');
    const refreshedToggle = taskList.querySelector('.task-toggle[data-task-id="72"]');
    check(refreshedToggle && refreshedToggle.getAttribute('data-task-completed') === '1',
        'Task completion redraw preserves completed state');
    check(refreshedToggle && refreshedToggle.focused === true,
        'Task completion redraw restores keyboard focus to the refreshed toggle');

    changeId.value = '71';
    documentObject.dispatch('click', deleteButton);
    await flush();
    check(reloads === 0, 'Task Item delete does not reload the whole page');
    check(apiCalls.slice(-2).map((call) => call.action).join(',') === 'task.item.delete,widget.list',
        'Task Item delete is followed by a scoped Widget refresh');
    check(taskList.querySelector('[data-task-id="71"]') === null,
        'Task Item delete removes the deleted Task from the local Widget');
    check(dismiss.clickCount === 2, 'Task Item delete closes the edit modal after redraw');

    check(notices.length === 0, 'successful Task partial refreshes do not emit error notices');
    check(apiCalls.filter((call) => call.action === 'widget.list').every((call) => call.data.widget_location === '0'),
        'Task partial refresh requests only the current Dashboard location');

    console.log('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0');
    process.exit(failed === 0 ? 0 : 1);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
