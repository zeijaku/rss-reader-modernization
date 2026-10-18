'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard-feed-title.js'), 'utf8');
let passed = 0, failed = 0;
function check(value, label) {
    if (value) passed++; else failed++;
    console.log((value ? 'PASS: ' : 'FAIL: ') + label);
}
class Element {
    constructor(text = '') {
        this.attributes = {};
        this.properties = {};
        this.textContent = text;
        this.cssValues = {};
        this.scrollWidth = 0; this.clientWidth = 0;
        this.scrollHeight = 0; this.clientHeight = 0;
        this.rectangle = {left: 0, top: 0, bottom: 0};
    }
    getBoundingClientRect() { return this.rectangle; }
}
let tooltip = null;
class Wrapper {
    constructor(elements) { this.elements = elements; this.length = elements.length; }
    attr(key, value) {
        if (arguments.length === 1) return this.elements[0]?.attributes[key];
        this.elements.forEach(el => { el.attributes[key] = String(value); });
        return this;
    }
    removeAttr(key) {this.elements.forEach(el => {delete el.attributes[key];}); return this;}
    prop(key, value) {
        if (arguments.length === 1) return this.elements[0]?.properties[key];
        this.elements.forEach(el => {el.properties[key] = value;});
        return this;
    }
    addClass(name) { this.elements.forEach(el => {el.className = name;}); return this; }
    appendTo(where) {
        if (where === 'body') tooltip = this.elements[0];
        return this;
    }
    text(value) {
        if (arguments.length === 0) return this.elements[0]?.textContent || '';
        this.elements.forEach(el => {el.textContent = String(value);});
        return this;
    }
    empty() {return this.text('');}
    outerWidth() {return 200;}
    outerHeight() {return 54;}
    css(values) {this.elements.forEach(el => Object.assign(el.cssValues, values)); return this;}
    each(fn) {this.elements.forEach((el, i) => fn.call(el, i, el)); return this;}
    find(name) {
        return new Wrapper(name === '.feed-item-title-text'
            ? (this.elements[0]?.feedTitles || []) : []);
    }
}
function $(arg) {
    if (arg === '#feed-title-tooltip') return new Wrapper(tooltip ? [tooltip] : []);
    if (arg === '<div>') return new Wrapper([new Element()]);
    return new Wrapper(arg instanceof Element ? [arg] : []);
}
const tasks = new Map(), frames = [];
let seq = 0;
const windowObject = {
    innerWidth: 320,
    innerHeight: 500,
    setTimeout(fn, delay) {const id = ++seq; tasks.set(id, {fn, delay}); return id;},
    clearTimeout(id) {tasks.delete(id);},
    requestAnimationFrame(fn) {frames.push(fn);}
};
const documentObject = {documentElement: {clientWidth: 320, clientHeight: 500}};
const context = {jQuery: $, window: windowObject, document: documentObject,
    String, Number, Math, Array};
vm.runInNewContext(source, context, {filename: 'dashboard-feed-title.js'});
const ui = windowObject.IGuguruDashboardFeedTitle;
check(!!ui && typeof ui.hideFeedTitleTooltip === 'function'
    && typeof ui.scheduleFeedTitleTooltip === 'function',
    'title tooltip module exposes compatibility helpers');
vm.runInNewContext(source, context, {filename: 'dashboard-feed-title-reload.js'});
check(ui === windowObject.IGuguruDashboardFeedTitle,
    'loading the module again does not replace existing tooltip state');

const title = new Element('短縮された記事タイトル');
title.scrollWidth = 290; title.clientWidth = 110;
title.rectangle = {left: 280, top: 465, bottom: 480};
title.attributes['data-full-title'] = '<b>安全な記事タイトル</b>';
check(ui.feedTitleIsTruncated(title), 'horizontal overflow is detected');
const vertical = new Element();
vertical.scrollHeight = 80; vertical.clientHeight = 32;
check(ui.feedTitleIsTruncated(vertical), 'vertical overflow is detected');
check(!ui.feedTitleIsTruncated(new Element()), 'untruncated title stays unmarked');

ui.scheduleFeedTitleTooltip(title);
check(tasks.size === 1 && [...tasks.values()][0].delay === 240,
    'tooltip waits for existing 240ms focus/hover delay');
const [timerId, pending] = [...tasks.entries()][0];
tasks.delete(timerId);
pending.fn();
check(tooltip?.textContent === '<b>安全な記事タイトル</b>'
    && tooltip.properties.hidden === false
    && tooltip.attributes.role === 'tooltip',
    'full title is rendered as literal text in the visible tooltip');
check(title.attributes['aria-describedby'] === 'feed-title-tooltip',
    'tooltip is attached to the article with aria-describedby');
check(tooltip.cssValues.left === '112px' && tooltip.cssValues.top === '404px',
    'tooltip position remains clamped to screen edges');
ui.hideFeedTitleTooltip();
check(tooltip.properties.hidden === true && tooltip.textContent === ''
    && !('aria-describedby' in title.attributes),
    'hiding tooltip clears content and accessible association');

ui.scheduleFeedTitleTooltip(title);
ui.hideFeedTitleTooltip();
check(tasks.size === 0, 'moving focus away cancels the pending delayed tooltip');
const short = new Element('短いタイトル');
short.scrollWidth = 50; short.clientWidth = 120;
ui.scheduleFeedTitleTooltip(short);
const [shortId, shortTask] = [...tasks.entries()][0];
tasks.delete(shortId);
shortTask.fn();
check(tooltip.properties.hidden === true,
    'non-truncated title does not open tooltip');

const scope = new Element();
scope.feedTitles = [title, short, vertical];
ui.refreshFeedTitleOverflow($(scope));
check(title.attributes['data-feed-title-truncated'] === '1'
    && short.attributes['data-feed-title-truncated'] === '0'
    && vertical.attributes['data-feed-title-truncated'] === '1',
    'overflow marker is maintained per article title');
title.scrollWidth = 90;
ui.scheduleFeedTitleOverflowRefresh($(scope));
check(frames.length === 1, 'overflow refresh is scheduled via animation frame');
frames.shift()();
check(title.attributes['data-feed-title-truncated'] === '0',
    'scheduled overflow refresh sees latest layout');
delete windowObject.requestAnimationFrame;
title.scrollWidth = 400;
ui.scheduleFeedTitleOverflowRefresh($(scope));
const [fallbackId, fallback] = [...tasks.entries()][0];
check(fallback.delay === 0, 'overflow refresh falls back to zero-delay timer');
tasks.delete(fallbackId);
fallback.fn();
check(title.attributes['data-feed-title-truncated'] === '1',
    'fallback refresh preserves the truncation marker');

console.log('RESULT: PASS ' + passed + ' / FAIL ' + failed);
process.exit(failed ? 1 : 0);
