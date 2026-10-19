'use strict';
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const load = (name) => fs.readFileSync(path.join(root, 'public/js', name), 'utf8');

class Element {
    constructor({rect = null, width = 0, height = 0} = {}) {
        this.rect = rect;
        this.width = width;
        this.height = height;
        this.attributes = {};
        this.properties = {hidden: false};
        this.dataValues = {};
        this.styles = {};
        this.children = new Map();
        this.focused = false;
        this.closestMap = new Map();
        this.disabled = false;
    }
    getBoundingClientRect() { return this.rect; }
    focus() { this.focused = true; }
}
class Query {
    constructor(elements = []) { this.elements = elements; this.length = elements.length; }
    get(index) { return this.elements[index]; }
    closest(selector) {
        return new Query(this.elements.flatMap(el => el.closestMap.get(selector) || []));
    }
    css(values) { this.elements.forEach(el => Object.assign(el.styles, values)); return this; }
    prop(key, value) {
        if (arguments.length === 1) return this.elements[0]?.properties[key];
        this.elements.forEach(el => { el.properties[key] = value; }); return this;
    }
    attr(key, value) {
        if (arguments.length === 1) return this.elements[0]?.attributes[key];
        this.elements.forEach(el => { el.attributes[key] = value; }); return this;
    }
    data(key, value) {
        if (arguments.length === 1) return this.elements[0]?.dataValues[key];
        this.elements.forEach(el => { el.dataValues[key] = value; }); return this;
    }
    outerWidth() { return this.elements[0]?.width || 0; }
    outerHeight() { return this.elements[0]?.height || 0; }
    find(selector) {
        let result = this.elements.flatMap(el => el.children.get(selector) || []);
        if (selector === '.article-actions-item:not(:disabled):not([hidden])') {
            result = result.filter(el => !el.disabled && !el.properties.hidden);
        }
        return new Query(result);
    }
    first() { return new Query(this.elements.slice(0, 1)); }
    last() { return new Query(this.elements.slice(-1)); }
    focus() { this.elements.forEach(el => el.focus()); return this; }
}
const doc = {documentElement: {clientWidth: 375, clientHeight: 700,
    contains(node) { return node instanceof Element; }}};
const win = {innerWidth: 375, innerHeight: 700,
    IGuguruDashboardCore: {showNotice() { throw Error('Unexpected notice'); }}};
const menu = new Element({width: 168, height: 214});
menu.properties.hidden = true;
const card = new Element({rect: {left: 12, top: 20, right: 315, bottom: 635, width: 303, height: 615}});
const trigger = new Element({rect: {left: 270, right: 305, top: 590, bottom: 634}});
trigger.closestMap.set('.feed-card, .stock-card', [card]);
trigger.closestMap.set('[data-feed-content-id]', [card]);
trigger.attributes['data-article-url'] = 'https://example.test/post';
trigger.attributes['data-article-title'] = '記事タイトル';
trigger.attributes['data-article-context'] = 'feed';
const firstItem = new Element();
const lastItem = new Element();
menu.children.set('.article-actions-item:not(:disabled):not([hidden])', [firstItem, lastItem]);
function $(arg) {
    if (arg === '#articleActionsMenu') return new Query([menu]);
    if (arg === '.article-actions-trigger[aria-expanded="true"]') {
        return new Query(trigger.attributes['aria-expanded'] === 'true' ? [trigger] : []);
    }
    if (arg === trigger) return new Query([trigger]);
    throw Error('Unexpected selector: ' + String(arg));
}
const context = {window: win, document: doc, jQuery: $, console, String, Number, Math,
    Array, Object, JSON, RegExp, parseInt, encodeURIComponent};
vm.runInNewContext(load('dashboard-article-menu-position.js'), context,
    {filename: 'dashboard-article-menu-position.js'});
const geometry = win.IGuguruDashboardArticleMenuPosition;
assert.strictEqual(typeof geometry.positionArticleActionsMenu, 'function');
vm.runInNewContext(load('dashboard-article-menu-position.js'), context,
    {filename: 'dashboard-article-menu-position.js (repeat)'});
assert.strictEqual(win.IGuguruDashboardArticleMenuPosition, geometry);
console.log('PASS: Article menu geometry module exports one stable interface');
const query = new Query([trigger]);
assert.strictEqual(geometry.positionArticleActionsMenu(new Query([menu]), query), true);
function rectFromCss() {
    return {left: parseFloat(menu.styles.left), top: parseFloat(menu.styles.top),
        right: parseFloat(menu.styles.left) + menu.width,
        bottom: parseFloat(menu.styles.top) + menu.height};
}
let displayed = rectFromCss();
assert(displayed.left >= card.rect.left && displayed.right <= card.rect.right);
assert(displayed.top >= card.rect.top && displayed.bottom <= card.rect.bottom);
assert.strictEqual(menu.styles.visibility, 'visible');
assert.strictEqual(menu.properties.hidden, false);
assert.strictEqual(menu.styles.maxWidth, '224px');
console.log('PASS: narrow viewport clamps menu inside Feed card, including bottom-right');
const empty = new Query([]);
assert.strictEqual(geometry.positionArticleActionsMenu(new Query([menu]), empty), false);
const outside = new Element({rect: trigger.rect});
assert.strictEqual(geometry.positionArticleActionsMenu(new Query([menu]), new Query([outside])), false);
console.log('PASS: missing trigger or card leaves menu unpositioned');
win.innerWidth = doc.documentElement.clientWidth = 1400;
win.innerHeight = doc.documentElement.clientHeight = 900;
card.rect = {left: 500, top: 150, right: 1050, bottom: 800, width: 550, height: 650};
trigger.rect = {left: 550, top: 170, right: 585, bottom: 214};
menu.width = 192;
menu.height = 120;
assert.strictEqual(geometry.positionArticleActionsMenu(new Query([menu]), query), true);
displayed = rectFromCss();
assert(displayed.left >= card.rect.left && displayed.right <= card.rect.right);
assert(displayed.top >= card.rect.top && displayed.bottom <= card.rect.bottom);
console.log('PASS: desktop viewport still positions menu within active card');

// Load the real Dashboard controller without starting unrelated widgets. Opening,
// toggling and focus restoration must still use the delegated geometry function.
const main = load('dashboard.js');
const startup = '$(initDashboard);';
assert.strictEqual(main.split(startup).length, 2, 'Dashboard initialization marker unchanged');
assert(main.includes('return window.IGuguruDashboardArticleMenuPosition.positionArticleActionsMenu($menu, $trigger);'));
assert(!main.includes('maxWidth: Math.min(224, availableWidth)'));
const bridged = main.replace(startup, 'window.__openArticleMenuTest = openArticleActionsMenu;');
vm.runInNewContext(bridged, context, {filename: 'dashboard.js'});
menu.properties.hidden = true;
const menuItems = {
    '.article-action-reader': [new Element()],
    '.article-action-stock': [new Element()],
    '.article-action-copy, .article-action-x': [new Element()],
    '.article-action-stock-only': [new Element()],
    '.article-action-stock-remove': [new Element()]
};
Object.entries(menuItems).forEach(([key, children]) => menu.children.set(key, children));
win.__openArticleMenuTest(query, false);
assert.strictEqual(menu.properties.hidden, false);
assert.strictEqual(trigger.attributes['aria-expanded'], 'true');
assert.strictEqual(menu.dataValues['article-url'], 'https://example.test/post');
assert.strictEqual(menuItems['.article-action-reader'][0].properties.hidden, false);
assert(firstItem.focused);
console.log('PASS: real Dashboard entry opens menu through extracted geometry and focuses first action');
win.__openArticleMenuTest(query, true);
assert.strictEqual(menu.properties.hidden, true);
assert.strictEqual(trigger.attributes['aria-expanded'], 'false');
assert(trigger.focused);
console.log('PASS: second click closes menu and restores focus to trigger');
console.log('PASS: Dev10-A Article Actions menu positioning regression');
