'use strict';
// Focused Widget D&D / keyboard order regression using the original delegated handlers.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard-widget-order.js'), 'utf8');
let failures = 0;
function check(ok, name) {
    console.log((ok ? 'PASS: ' : 'FAIL: ') + name);
    if (!ok) failures++;
}

class Element {
    constructor(classes = '', attrs = {}, rect = {}) {
        this.classes = new Set(classes.split(/\s+/).filter(Boolean));
        this.attrs = {...attrs};
        this.children = [];
        this.parentNode = null;
        this.rect = {left: 0, top: 0, width: 100, height: 100, bottom: 100, ...rect};
        this.focused = false;
        this.textContent = '';
    }
    appendChild(child) {
        if (child.parentNode) child.parentNode.removeChild(child);
        this.children.push(child);
        child.parentNode = this;
        return child;
    }
    removeChild(child) {
        this.children.splice(this.children.indexOf(child), 1);
        child.parentNode = null;
        return child;
    }
    insertBefore(child, target) {
        if (!target) return this.appendChild(child);
        if (child === target) return child;
        if (child.parentNode) child.parentNode.removeChild(child);
        const index = this.children.indexOf(target);
        this.children.splice(index < 0 ? this.children.length : index, 0, child);
        child.parentNode = this;
        return child;
    }
    setAttribute(name, value) {this.attrs[name] = String(value);}
    getBoundingClientRect() { return this.rect; }
    focus() { this.focused = true; }
    get firstElementChild() {return this.children[0] || null;}
    get previousElementSibling() {
        if (!this.parentNode) return null;
        return this.parentNode.children[this.parentNode.children.indexOf(this) - 1] || null;
    }
    get nextElementSibling() {
        if (!this.parentNode) return null;
        return this.parentNode.children[this.parentNode.children.indexOf(this) + 1] || null;
    }
}
function matches(element, selector) {
    if (selector[0] === '.') return element.classes.has(selector.slice(1));
    const a = selector.match(/^\[data-dashboard-widget-id="(\d+)"\]$/);
    return Boolean(a && element.attrs['data-dashboard-widget-id'] === a[1]);
}
function descendants(element) {
    const result = [];
    for (const child of element.children || []) result.push(child, ...descendants(child));
    return result;
}
const events = new Map();
class Wrapper {
    constructor(nodes) { this.nodes = nodes; }
    get length() { return this.nodes.length; }
    get(index) {return this.nodes[index] || null;}
    first() {return new Wrapper(this.nodes.slice(0, 1));}
    each(fn) {this.nodes.forEach((n, i) => fn.call(n, i, n)); return this;}
    attr(name, value) {
        if (arguments.length === 1) return this.nodes[0]?.attrs?.[name];
        this.nodes.forEach(n => n.setAttribute(name, value));
        return this;
    }
    children(selector) {return new Wrapper(this.nodes.flatMap(n => n.children || []).filter(n => matches(n, selector)));}
    find(selector) {
        const parts = selector.split(' ');
        if (parts.length === 2) return this.find(parts[0]).find(parts[1]);
        return new Wrapper(this.nodes.flatMap(descendants).filter(n => matches(n, selector)));
    }
    closest(selector) {
        const found = [];
        this.nodes.forEach(n => {
            for (let cur = n; cur; cur = cur.parentNode) {
                if (matches(cur, selector)) {found.push(cur); break;}
            }
        });
        return new Wrapper(found);
    }
    addClass(names) {this.nodes.forEach(n => names.split(/\s+/).forEach(c => n.classes.add(c))); return this;}
    removeClass(names) {this.nodes.forEach(n => names.split(/\s+/).forEach(c => n.classes.delete(c))); return this;}
    text() {return this.nodes[0]?.textContent || '';}
    focus() {this.nodes[0]?.focus(); return this;}
    off(event, selector) {events.delete(event + '|' + selector); return this;}
    on(event, selector, fn) {events.set(event + '|' + selector, fn); return this;}
}
const body = new Element();
const grid = body.appendChild(new Element('feed-grid', {'data-dashboard-widget-location':'0'}));
const cards = [1, 2, 3].map((i) => {
    const card = grid.appendChild(new Element('dashboard-widget',
        {'data-dashboard-widget-id':String(i)}, {left:(i-1)*100, top:0, width:100, height:100, bottom:100}));
    const handle = card.appendChild(new Element('widget-drag-handle'));
    handle.setPointerCapture = () => {};
    const title = card.appendChild(new Element('widget-title-text'));
    title.textContent = 'Widget ' + i;
    return {card, handle, title};
});
let pointerTarget = cards[1].card;
const documentObject = {
    body,
    createElement() {
        const element = new Element();
        element.style = {transform:'',width:''};
        return element;
    },
    elementFromPoint() {return pointerTarget;}
};
const $ = input => new Wrapper(input === documentObject ? [documentObject] : input ? [input] : []);
const requests = [], notices = [], failuresApi = [];
class Deferred {
    done(fn) {this.resolveHandler = fn; return this;}
    fail(fn) {this.rejectHandler = fn; return this;}
    always(fn) {this.alwaysHandler = fn; return this;}
    resolve(data) {this.resolveHandler?.(data); this.alwaysHandler?.();}
    reject() {this.rejectHandler?.({}, 'error'); this.alwaysHandler?.();}
}
const windowObject = {IGuguruDashboardCore: {
    apiRequest(action, payload, timeout) {
        const request = new Deferred();
        requests.push({action, payload, timeout, request});
        return request;
    },
    apiResponseOk(data) {return data?.ok === true;},
    showNotice(message, type) {notices.push({message, type});},
    clearNotice() {},
    requestFail() {failuresApi.push('failed');}
}};
vm.runInNewContext(source, {jQuery:$,window:windowObject,document:documentObject,Number,String,JSON,Math,Object,RegExp}, {filename:'dashboard-widget-order.js'});
const module = windowObject.IGuguruDashboardWidgetOrder;
check(Boolean(module && module.bindEvents && module.isDragging), 'Widget order controller exports its public methods');
module.bindEvents();
module.bindEvents();
check(events.size === 6, 'click, keyboard and four pointer handlers bind idempotently');
function order() {return grid.children.map(n => n.attrs['data-dashboard-widget-id']).join(',');}
function fire(name, element, props = {}) {
    const callback = events.get(name + '.iguguruDashboard|.widget-drag-handle');
    if (!callback) throw new Error('missing handler ' + name);
    const event = {preventDefault() {this.prevented=true;},originalEvent:props, key:props.key, ...props};
    callback.call(element, event);
    return event;
}
const handle1 = cards[0].handle;
fire('keydown',handle1,{key:'ArrowDown'});
check(order() === '2,1,3', 'keyboard ArrowDown reorders within the current grid');
check(requests.length === 1 && requests[0].action === 'widget.reorder' &&
    requests[0].payload.previous_widget_ids === '["1","2","3"]' &&
    requests[0].payload.widget_ids === '["2","1","3"]',
    'reorder save keeps previous/order snapshots and action');
check(grid.attrs['aria-busy'] === 'true', 'saving marks Widget grid busy');
requests[0].request.resolve({ok:true,data:{sort_orders:{"2":10,"1":20,"3":30}}});
check(grid.attrs['aria-busy'] === 'false' && cards[0].handle.focused,
    'save completes, updates drag handle focus and restores busy status');
grid.attrs['data-dashboard-widget-location'] = 'invalid';
fire('keydown',handle1,{key:'ArrowUp'});
check(order() === '2,1,3' && requests.length === 1,
    'invalid dashboard location restores previous order without API mutation');
grid.attrs['data-dashboard-widget-location'] = '0';
fire('keydown',handle1,{key:'ArrowUp'});
check(order() === '1,2,3' && requests.length === 2, 'keyboard ArrowUp attempts a second reorder');
requests[1].request.reject();
check(order() === '2,1,3' && failuresApi.length === 1,
    'API transport failure restores order and reports error');

fire('pointerdown',handle1,{isPrimary:true,pointerType:'mouse',button:0,clientX:120,clientY:30,pointerId:1});
check(module.isDragging(), 'pointer start sets active drag state');
pointerTarget = cards[2].card;
fire('pointermove',handle1,{clientX:280,clientY:40});
check(cards[2].card.classes.has('widget-drop-target'), 'pointer movement highlights an eligible drop target');
fire('pointercancel',handle1,{});
check(!module.isDragging() && order() === '2,1,3' && requests.length === 2,
    'pointercancel clears drag state without saving');

fire('pointerdown',handle1,{isPrimary:true,pointerType:'mouse',button:0,clientX:120,clientY:30,pointerId:2});
pointerTarget = cards[2].card;
fire('pointermove',handle1,{clientX:280,clientY:40});
fire('pointerup',handle1,{});
check(!module.isDragging() && order() === '2,3,1' && requests.length === 3,
    'successful pointer drop moves card and invokes order save');
requests[2].request.resolve({ok:true,data:{sort_orders:{"2":10,"3":20,"1":30}}});
check(notices.some(n => n.type === 'success'), 'successful reorder announces completion');
const count = failures;
console.log('RESULT: PASS ' + (12-count) + ' / FAIL ' + count);
process.exit(count ? 1 : 0);
