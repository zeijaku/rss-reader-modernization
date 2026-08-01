'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const ajaxCalls = [];
let failures = 0;

function check(condition, message) {
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
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

class Element {
    constructor(tag, attrs) {
        this.tag = tag || 'div';
        this.attrs = Object.assign({}, attrs || {});
        this.dataValues = {};
        this.classes = new Set();
        this.children = [];
        this.parent = null;
        this.textValue = '';
        this.value = '';
        this.disabled = false;
        const className = this.attrs.class || '';
        className.split(/\s+/).filter(Boolean).forEach((name) => this.classes.add(name));
    }
}

function aggregateText(element) {
    return element.textValue + element.children.map(aggregateText).join('');
}

function findDescendants(element, selector, result) {
    element.children.forEach((child) => {
        if (selector.startsWith('.') && child.classes.has(selector.slice(1))) {
            result.push(child);
        }
        findDescendants(child, selector, result);
    });
}

class Wrapper {
    constructor(elements) {
        this.elements = elements || [];
    }
    get length() { return this.elements.length; }
    each(fn) {
        this.elements.forEach((element, index) => fn.call(element, index, element));
        return this;
    }
    attr(key, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].attrs[key] : undefined;
        this.elements.forEach((element) => { element.attrs[key] = String(value); });
        return this;
    }
    data(key, value) {
        if (arguments.length === 1) return this.elements[0] ? this.elements[0].dataValues[key] : undefined;
        this.elements.forEach((element) => { element.dataValues[key] = value; });
        return this;
    }
    addClass(names) {
        String(names || '').split(/\s+/).filter(Boolean).forEach((name) => {
            this.elements.forEach((element) => element.classes.add(name));
        });
        return this;
    }
    text(value) {
        if (arguments.length === 0) return this.elements[0] ? aggregateText(this.elements[0]) : '';
        this.elements.forEach((element) => {
            element.textValue = String(value);
            element.children = [];
        });
        return this;
    }
    empty() {
        this.elements.forEach((element) => {
            element.textValue = '';
            element.children = [];
        });
        return this;
    }
    appendTo(target) {
        const parent = target instanceof Wrapper ? target.elements[0] : null;
        if (!parent) return this;
        this.elements.forEach((element) => {
            element.parent = parent;
            parent.children.push(element);
        });
        return this;
    }
    find(selector) {
        const found = [];
        this.elements.forEach((element) => findDescendants(element, selector, found));
        return new Wrapper(found);
    }
    closest(selector) {
        const found = [];
        this.elements.forEach((element) => {
            let current = element;
            while (current) {
                if (selector === '[data-feed-content-id]' && current.attrs['data-feed-content-id']) {
                    found.push(current);
                    break;
                }
                current = current.parent;
            }
        });
        return new Wrapper(found);
    }
    val(value) {
        if (arguments.length === 0) return this.elements[0] ? this.elements[0].value : '';
        this.elements.forEach((element) => { element.value = value; });
        return this;
    }
    prop(key, value) {
        if (key === 'disabled') this.elements.forEach((element) => { element.disabled = value; });
        return this;
    }
    hide() { return this; }
    fadeIn() { return this; }
    fadeOut() { return this; }
    animate() { return this; }
    popover() { return this; }
    drawer() { return this; }
    scrollTop() { return 0; }
    off() { return this; }
    on() { return this; }
}

function parseCreatedElement(sourceText) {
    const tagMatch = sourceText.match(/^<\s*([a-z0-9]+)/i);
    const tag = tagMatch ? tagMatch[1].toLowerCase() : 'div';
    const attrs = {};
    const attrPattern = /([a-zA-Z0-9_-]+)="([^"]*)"/g;
    let match;
    while ((match = attrPattern.exec(sourceText)) !== null) attrs[match[1]] = match[2];
    return new Element(tag, attrs);
}

function createFeedCard(id) {
    const card = new Element('div', {'data-feed-content-id': String(id), 'data-feed-state': 'loading'});
    const title = new Element('span', {class: 'content-title'});
    const body = new Element('tbody', {class: 'content-body'});
    title.parent = card;
    body.parent = card;
    card.children.push(title, body);
    return card;
}

const body = new Element('body');
const meta = new Element('meta', {content: 'csrf-m2b-token'});
const cards = [1, 2, 3, 4, 5, 6, 7, 8].map(createFeedCard);
const documentObject = new Element('document');
const windowObject = {location: {reload: () => {}}};
const dummy = new Element('div');

function $(arg) {
    if (typeof arg === 'function') {
        arg();
        return new Wrapper([]);
    }
    if (arg instanceof Element) return new Wrapper([arg]);
    if (arg === documentObject) return new Wrapper([documentObject]);
    if (arg === windowObject) return new Wrapper([dummy]);
    if (typeof arg === 'string' && arg.startsWith('<')) return new Wrapper([parseCreatedElement(arg)]);
    if (arg === '[data-feed-content-id]') return new Wrapper(cards);
    if (arg === 'body') return new Wrapper([body]);
    if (arg === 'meta[name="csrf-token"]') return new Wrapper([meta]);
    return new Wrapper([dummy]);
}

$.extend = (...args) => Object.assign(...args);
$.fn = {drawer: function () {}};
$.ajax = (options) => {
    const deferred = new Deferred();
    ajaxCalls.push({options, deferred});
    return deferred;
};

const context = {
    jQuery: $,
    window: windowObject,
    document: documentObject,
    alert: () => {},
    console,
    Array,
    String,
    Math,
    RegExp
};
vm.runInNewContext(source, context, {filename: 'dashboard.js'});

check(ajaxCalls.length === 8, 'one Feed request starts for each card');
vm.runInNewContext(source, context, {filename: 'dashboard-second-load.js'});
check(ajaxCalls.length === 8, 'loading dashboard twice does not duplicate Feed requests');
check(ajaxCalls.every((call, index) => call.options.data.action === 'feed.fetch' && call.options.data.content_id === String(index + 1)), 'Feed requests keep action and content_id mapping');
check(ajaxCalls.every((call) => call.options.data.csrf_token === 'csrf-m2b-token'), 'all Feed requests include the CSRF token');
check(cards.every((card) => card.attrs['data-feed-state'] === 'loading'), 'all cards enter loading state before response');
check(cards.every((card) => card.attrs['aria-busy'] === 'true'), 'loading Feed cards expose aria-busy');
check(cards.every((card) => aggregateText(card).includes('フィードを読み込んでいます')), 'loading state is visible in each card');
check(cards.every((card) => card.children[1].children[0].children[0].attrs.role === 'status'), 'loading rows use status semantics');
check(cards.every((card) => card.dataValues['feed-request-pending'] === true), 'Feed requests are marked pending while active');

const longEmojiTitle = '😀'.repeat(65);
ajaxCalls[0].deferred.resolve({
    ok: true,
    data: {
        result_feed: {
            channel: {title: '<b>安全なタイトル</b>', link: 'https://example.com/feed'},
            item: [
                {title: '<script>alert(1)</script>', link: 'https://example.com/1'},
                {title: 'unsafe link', link: 'javascript:alert(1)'},
                {title: longEmojiTitle, link: 'https://example.com/3'},
                {title: '', link: ''},
                {title: 'fifth', link: 'https://example.com/5'},
                {title: 'sixth must not render', link: 'https://example.com/6'}
            ]
        }
    }
});

const firstTitle = cards[0].children[0];
const firstBody = cards[0].children[1];
check(cards[0].attrs['data-feed-state'] === 'ready', 'valid Feed enters ready state');
check(cards[0].attrs['aria-busy'] === 'false', 'valid Feed clears aria-busy');
check(firstTitle.children.length === 1 && firstTitle.children[0].tag === 'a', 'valid channel link renders as an anchor');
check(firstTitle.children[0].attrs.href === 'https://example.com/feed', 'channel anchor keeps the validated URL');
check(aggregateText(firstTitle).includes('<b>安全なタイトル</b>'), 'HTML-looking channel title remains literal text');
check(firstBody.children.length === 5, 'only five valid items are rendered');
check(aggregateText(firstBody.children[0]).includes('<script>alert(1)</script>'), 'HTML-looking item title remains literal text');
const firstStockButton = firstBody.children[0].children[0].children[0];
check(firstStockButton.tag === 'button', 'valid item Stock action is a real button');
check(String(firstStockButton.attrs['aria-label'] || '').includes('<script>alert(1)</script>'), 'Stock button accessible name includes the article title as text');
check(!aggregateText(firstBody.children[0]).includes('sixth must not render'), 'sixth item is not rendered');

const unsafeRow = firstBody.children[1];
const unsafeStockCell = unsafeRow.children[0];
const unsafeLinkCell = unsafeRow.children[1];
check(unsafeStockCell.children.length === 0, 'unsafe item URL does not create a Stock button');
check(unsafeLinkCell.children.length === 1 && unsafeLinkCell.children[0].tag === 'span', 'unsafe item URL renders non-clickable text');

const emojiRowText = aggregateText(firstBody.children[2]);
check(Array.from(emojiRowText).filter((char) => char === '😀').length === 64, 'emoji title is truncated at 64 complete code points');
check(emojiRowText.endsWith('...'), 'truncated title receives an ellipsis');
check(aggregateText(firstBody.children[3]).includes('タイトルなし'), 'missing item title receives a fallback');

ajaxCalls[1].deferred.resolve({
    ok: true,
    data: {result_feed: {channel: {title: 'Empty Feed', link: 'https://example.com/empty'}, item: []}}
});
check(cards[1].attrs['data-feed-state'] === 'empty', 'zero-item Feed enters empty state');
check(cards[1].attrs['aria-busy'] === 'false', 'empty Feed clears aria-busy');
check(aggregateText(cards[1]).includes('記事はありません'), 'zero-item Feed shows an explicit empty message');
check(cards[1].children[0].children[0].tag === 'a', 'empty Feed keeps its channel link');

ajaxCalls[2].deferred.resolve({ok: true, data: {result_feed: []}});
check(cards[2].attrs['data-feed-state'] === 'error', 'malformed result_feed enters error state');
check(aggregateText(cards[2]).includes('フィードの応答形式を確認出来ませんでした'), 'malformed response has a controlled message');

ajaxCalls[3].deferred.reject({status: 0}, 'timeout');
check(cards[3].attrs['data-feed-state'] === 'error', 'timeout enters error state');
check(cards[3].attrs['aria-busy'] === 'false', 'Feed error clears aria-busy');
check(cards[3].children[1].children[0].children[0].attrs.role === 'alert', 'Feed error row uses alert semantics');
check(aggregateText(cards[3]).includes('コンテンツの取得がタイムアウトしました'), 'timeout message is specific');

ajaxCalls[4].deferred.reject({status: 404}, 'error');
check(aggregateText(cards[4]).includes('登録されたコンテンツが見つかりませんでした'), '404 response has a controlled not-found message');

ajaxCalls[5].deferred.reject({
    status: 502,
    responseJSON: {ok: false, error: {code: 'upstream_error', message: 'Feed could not be fetched.'}}
}, 'error');
check(cards[5].attrs['data-feed-state'] === 'error', 'upstream HTTP 502 enters error state without stopping other cards');
check(aggregateText(cards[5]).includes('しばらくしてから再度お試しください'), 'upstream failure uses a controlled generic message');

ajaxCalls[6].deferred.resolve({
    ok: true,
    data: {result_feed: {channel: {title: '', link: 'javascript:alert(1)'}, item: []}}
});
check(cards[6].attrs['data-feed-state'] === 'empty', 'valid empty item list enters the empty state');
check(cards[6].children[0].children[0].tag === 'span', 'unsafe channel URL does not create an anchor');
check(aggregateText(cards[6].children[0]).includes('タイトルなし'), 'missing channel title receives a fallback');

ajaxCalls[7].deferred.resolve({
    ok: true,
    data: {result_feed: {channel: {title: 'Malformed item list', link: 'https://example.com/'}, item: null}}
});
check(cards[7].attrs['data-feed-state'] === 'error', 'missing item array is rejected as an invalid response');
check(aggregateText(cards[7]).includes('フィードの応答形式を確認出来ませんでした'), 'missing item array receives a controlled error message');
check(cards.every((card) => card.dataValues['feed-request-pending'] === false), 'pending flag is released after every response path');

if (failures > 0) process.exit(1);
console.log('All M2-B Feed runtime checks passed.');
