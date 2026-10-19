'use strict';
// Real extracted Article Actions menu controller runs in a small DOM/jQuery VM.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard-article-menu.js'), 'utf8');
const doc = {activeElement: null, documentElement: {contains(node) { return !node.detached; }}};
class Element {
    constructor(attrs = {}) {
        this.attrs = attrs; this.props = {}; this.values = {};
        this.style = {}; this.locals = new Map(); this.near = new Map();
        this.disabled = false; this.detached = false;
    }
    focus() { doc.activeElement = this; }
}
class Query {
    constructor(items = []) { this.items = items; this.length = items.length; }
    get(i) { return this.items[i]; }
    attr(k, v) {
        if (arguments.length === 1) return this.items[0]?.attrs[k];
        this.items.forEach(e => e.attrs[k] = v); return this;
    }
    prop(k, v) {
        if (arguments.length === 1) return this.items[0]?.props[k];
        this.items.forEach(e => e.props[k] = v); return this;
    }
    data(k, v) {
        if (arguments.length === 1) return this.items[0]?.values[k];
        this.items.forEach(e => e.values[k] = v); return this;
    }
    css(style) { this.items.forEach(e => Object.assign(e.style, style)); return this; }
    find(sel) {
        const els = this.items.flatMap(e => e.locals.get(sel) || []);
        return new Query(sel === '.article-actions-item:not(:disabled):not([hidden])'
            ? els.filter(e => !e.props.hidden && !e.props.disabled) : els);
    }
    closest(sel) { return new Query(this.items.flatMap(e => e.near.get(sel) || [])); }
    first() { return new Query(this.items.slice(0, 1)); }
    last() { return new Query(this.items.slice(-1)); }
    eq(i) { return new Query(i < 0 ? [] : this.items.slice(i,i+1)); }
    index(el) { return this.items.indexOf(el); }
    focus() { this.items.forEach(e => e.focus()); return this; }
}
const menu = new Element(), notice = [];
menu.props.hidden = true;
const feedCard = new Element(), stockCard = new Element();
const rss = new Element({'data-article-url':'https://example.test/p',
    'data-article-title': 'RSSタイトル', 'data-article-context': 'feed'});
rss.near.set('.feed-card, .stock-card', [feedCard]);
rss.near.set('[data-feed-content-id]', [feedCard]);
const stock = new Element({'data-article-url': 'https://example.test/s',
    'data-article-title': '保存済み記事', 'data-article-context': 'stock', 'data-stock-id':'22'});
stock.near.set('.feed-card, .stock-card', [stockCard]);
stock.near.set('.stock-card', [stockCard]);
let positionSucceeds = true, calls = 0;
const win = {IGuguruDashboardCore: {showNotice(message) {notice.push(message);}},
    IGuguruDashboardArticleMenuPosition: {positionArticleActionsMenu($menu) {
        calls++;
        if (positionSucceeds) $menu.prop('hidden', false);
        return positionSucceeds;
    }}};
function $(value) {
    if (value === '#articleActionsMenu') return new Query([menu]);
    if (value === '.article-actions-trigger[aria-expanded="true"]')
        return new Query([rss,stock].filter(el=>el.attrs['aria-expanded']==='true'));
    if (value instanceof Element) return new Query([value]);
    return new Query([]);
}
function menuButton() { return new Element(); }
const controls = ['.article-action-reader', '.article-action-stock',
    '.article-action-copy, .article-action-x', '.article-action-stock-only',
    '.article-action-stock-remove'];
const items = controls.map(menuButton);
controls.forEach((sel,i) => menu.locals.set(sel,[items[i]]));
const first = menuButton(), middle = menuButton(), last = menuButton();
menu.locals.set('.article-actions-item:not(:disabled):not([hidden])',[first,middle,last]);
const ctx = {jQuery: $, window:win, document:doc, Math, Array, Number, String, RegExp, parseInt};
vm.runInNewContext(source,ctx,{filename:'dashboard-article-menu.js'});
const m = win.IGuguruDashboardArticleMenu;
assert(m && typeof m.getTrigger === 'function' && typeof m.handleMenuKeydown === 'function');
vm.runInNewContext(source,ctx,{filename:'dashboard-article-menu.js repeat'});
assert.strictEqual(win.IGuguruDashboardArticleMenu, m);
assert.strictEqual(m.getTrigger(),null);
console.log('PASS: single Article menu state owner survives duplicate module load');
m.openArticleActionsMenu($(rss),false);
assert.strictEqual(m.getTrigger(),rss);
assert.strictEqual(menu.props.hidden,false);
assert.strictEqual(menu.values['article-url'],'https://example.test/p');
assert.strictEqual(menu.values['article-title'],'RSSタイトル');
assert.strictEqual(items[0].props.disabled,false);
assert.strictEqual(items[1].props.disabled,false);
assert.strictEqual(items[4].props.disabled,true);
assert.strictEqual(doc.activeElement,first);
console.log('PASS: RSS selection preserves action URL, Reader entry and initial focus');
m.openArticleActionsMenu($(stock),true);
assert.strictEqual(m.getTrigger(),stock);
assert.strictEqual(rss.attrs['aria-expanded'],'false');
assert.strictEqual(items[0].props.hidden,true);
assert.strictEqual(items[1].props.hidden,true);
assert.strictEqual(items[4].props.disabled,false);
assert.strictEqual(menu.values['stock-id'],22);
assert.strictEqual(doc.activeElement,last);
console.log('PASS: switching to Stock clears old trigger and scopes visible actions');
function key(value) {const ev={key:value,prevented:false,preventDefault(){this.prevented=true;}};
    m.handleMenuKeydown(ev,$(menu));return ev;}
doc.activeElement=last;
assert(key('ArrowDown').prevented);
assert.strictEqual(doc.activeElement,first);
key('ArrowUp'); assert.strictEqual(doc.activeElement,last);
key('Home'); assert.strictEqual(doc.activeElement,first);
key('End'); assert.strictEqual(doc.activeElement,last);
console.log('PASS: keyboard wrap-around and Home/End focus navigation');
assert(key('Escape').prevented);
assert.strictEqual(m.getTrigger(),null);
assert.strictEqual(menu.props.hidden,true);
assert.strictEqual(doc.activeElement,stock);
console.log('PASS: Escape restores original trigger focus and clears selection');
m.openArticleActionsMenu($(rss),false);
key('Tab');
assert.strictEqual(m.getTrigger(),null);
assert.strictEqual(menu.props.hidden,true);
assert.strictEqual(doc.activeElement,first);
console.log('PASS: Tab closes menu without forcibly redirecting keyboard focus');
m.openArticleActionsMenu($(rss),false);
m.openArticleActionsMenu($(rss),false);
assert.strictEqual(menu.props.hidden,true);
assert.strictEqual(m.getTrigger(),null);
console.log('PASS: second click on same article toggles closed and clears trigger');
positionSucceeds=false;
m.openArticleActionsMenu($(rss),false);
assert.strictEqual(m.getTrigger(),null);
assert.strictEqual(menu.props.hidden,true);
assert(notice.includes('記事Actionsを開けませんでした'));
console.log('PASS: failed positioning clears state and reports error');
positionSucceeds=true;
m.openArticleActionsMenu($(rss),false);
rss.detached=true;
m.closeArticleActionsMenu(true);
assert.notStrictEqual(doc.activeElement,rss);
assert.strictEqual(m.getTrigger(),null);
console.log('PASS: removed article never receives return focus');
console.log('PASS: Dev10-B Article menu controller regression');
