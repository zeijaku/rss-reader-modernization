'use strict';
// Regression: execute the actual split Feed modules and Reader controller together.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const read = (name) => fs.readFileSync(path.join(root, 'public/js', name), 'utf8');

class Deferred {
    constructor() { this.success = []; this.failure = []; this.finally = []; }
    done(fn) { this.success.push(fn); return this; }
    fail(fn) { this.failure.push(fn); return this; }
    always(fn) { this.finally.push(fn); return this; }
    resolve(data) { this.success.forEach(fn => fn(data)); this.finally.forEach(fn => fn()); }
    reject(xhr, status) { this.failure.forEach(fn => fn(xhr, status)); this.finally.forEach(fn => fn()); }
}
class Element {
    constructor(length = 1) {
        this.length = length; this.attrs = {}; this.props = {}; this.values = {};
        this.textValue = ''; this.htmlValue = ''; this.classes = new Set();
        this.children = new Map(); this.closestMap = {};
    }
    text(v) { if (arguments.length === 0) return this.textValue; this.textValue = v; return this; }
    html(v) { if (arguments.length === 0) return this.htmlValue; this.htmlValue = v; return this; }
    attr(k, v) { if (arguments.length === 1) return this.attrs[k]; this.attrs[k] = v; return this; }
    prop(k, v) { if (arguments.length === 1) return this.props[k]; this.props[k] = v; return this; }
    data(k, v) { if (arguments.length === 1) return this.values[k]; this.values[k] = v; return this; }
    removeData(k) { delete this.values[k]; return this; }
    addClass(names) { String(names).split(/\s+/).forEach(n => this.classes.add(n)); return this; }
    removeClass(names) { String(names).split(/\s+/).forEach(n => this.classes.delete(n)); return this; }
    find(selector) { if (!this.children.has(selector)) this.children.set(selector, new Element()); return this.children.get(selector); }
    closest(selector) { return this.closestMap[selector] || new Element(0); }
    contents() { return this; }
    filter() { return this; }
    last() { return this; }
    replaceWith(value) { this.textValue = value; return this; }
    modal(action) { this.modalAction = action; return this; }
}
const elements = new Map();
function $(value) {
    if (typeof value === 'function') throw new Error('Unexpected Dashboard startup during Reader test');
    if (value instanceof Element) return value;
    if (typeof value !== 'string') return new Element(0);
    if (!elements.has(value)) elements.set(value, new Element());
    return elements.get(value);
}
const requests = [], notices = [];
const windowObject = {
    IGuguruDashboardCore: {
        apiRequest(action, data, timeout) {
            const deferred = new Deferred();
            requests.push({action, data, timeout, deferred});
            return deferred;
        },
        showNotice(message) { notices.push(message); }
    }
};
const ctx = {window: windowObject, document: {documentElement: {contains() {return true;}}},
    jQuery: $, console, Number, String, Array, Object, Math, JSON, RegExp};
vm.runInNewContext(read('dashboard-feed-render.js'), ctx, {filename: 'dashboard-feed-render.js'});
vm.runInNewContext(read('dashboard-feed-fetch.js'), ctx, {filename: 'dashboard-feed-fetch.js'});
// Test-only setter injected into the actual menu module closure; production does not expose it.
const articleMenuSource = read('dashboard-article-menu.js');
const insertionPoint = '    window.IGuguruDashboardArticleMenu = {';
if (articleMenuSource.split(insertionPoint).length !== 2) throw new Error('Article menu module contract changed');
vm.runInNewContext(articleMenuSource.replace(insertionPoint,
    '    window.__readerTestSetTrigger = function (value) { articleActionsTrigger = value; };\n' + insertionPoint),
    ctx, {filename: 'dashboard-article-menu.js'});
vm.runInNewContext(read('dashboard-article-reader.js'), ctx, {filename: 'dashboard-article-reader.js'});
const actualReader = windowObject.IGuguruDashboardArticleReader;
if (!actualReader || typeof actualReader.openReaderMode !== 'function') throw new Error('Reader module is unavailable');
vm.runInNewContext(read('dashboard-article-reader.js'), ctx, {filename: 'dashboard-article-reader.js repeated'});
if (windowObject.IGuguruDashboardArticleReader !== actualReader) throw new Error('Reader module duplicate load replaced its state');
const original = read('dashboard.js');
const entry = '$(initDashboard);';
if (original.split(entry).length !== 2) throw new Error('Dashboard entrypoint changed');
const instrumented = original.replace(entry,
    'window.__readerTest = {openReaderMode: openReaderMode, ' +
    'renderReaderModePayload: window.IGuguruDashboardArticleReader.renderReaderModePayload, fetchReaderFullText: fetchReaderFullText, ' +
    'setTrigger: window.__readerTestSetTrigger};');
vm.runInNewContext(instrumented, ctx, {filename: 'dashboard.js'});
const ui = windowObject.__readerTest;
if (typeof ui.openReaderMode !== 'function' || typeof ui.fetchReaderFullText !== 'function') throw new Error('Reader Dashboard delegates missing');
const assert = (condition, message) => {if (!condition) throw new Error(message); console.log('PASS: ' + message);};
const id = 'm1i:v1:' + 'a'.repeat(64);
const trigger = new Element(), row = new Element(), card = new Element();
row.attr('data-feed-item-index', '0');
card.attr('data-feed-content-id', '9');
card.data('feed-render-items', [{item_identity: id}]);
trigger.closestMap['.feed-item-row'] = row;
trigger.closestMap['[data-feed-content-id]'] = card;
ui.setTrigger(trigger);

// Reader must keep its loading state until the RSS payload is returned.
ui.openReaderMode();
assert($('#readerModeTitle').text() === '記事を読み込み中...' &&
    $('#readerModeStatus').text() === 'RSS本文を読み込んでいます...', 'Reader shows RSS loading state');
assert(requests[0].action === 'feed.reader' && requests[0].timeout === 25000 &&
    requests[0].data.content_id === '9' && requests[0].data.item_identity === id,
    'Reader uses the owned article identity without passing client URL');
requests[0].deferred.resolve({ok: true, data: {reader: {
    title: '記事タイトル', source: 'Example RSS', date: '2026-10-19',
    body: '<img src=x onerror=alert(1)> RSS本文', article_url: 'https://example.org/post'
}}});
assert($('#readerModeTitle').text() === '記事タイトル' &&
    $('#readerModeBody').text() === '<img src=x onerror=alert(1)> RSS本文' &&
    $('#readerModeBody').html() === '', 'RSS body is rendered with text, not injected HTML');
assert($('#readerModeStatus').prop('hidden') === true &&
    $('#readerModeOriginalLink').attr('href') === 'https://example.org/post' &&
    $('#readerModeFullTextButton').prop('hidden') === false,
    'Reader finishes loading and enables safe original article URL');

// URL filtering must reuse the Feed renderer rather than a second local validator.
assert(typeof windowObject.IGuguruDashboardFeedRender.safeFeedLink === 'function' &&
    windowObject.IGuguruDashboardFeedRender.safeFeedLink('javascript:alert(1)') === '',
    'Feed renderer exports its existing safe URL validator');
for (const url of ['javascript:alert(1)', 'data:text/html,hi', '/relative/path']) {
    ui.renderReaderModePayload({title: 'Unsafe', body: 'RSS本文', article_url: url});
    assert($('#readerModeOriginalLink').prop('hidden') === true &&
        $('#readerModeFullTextButton').prop('disabled') === true &&
        $('#readerModeOriginalLink').attr('href') === '#', 'Reader rejects unsafe link: ' + url);
}

// Full Text is a separate explicit request and receives only owned identity.
ui.renderReaderModePayload({title: '記事', body: 'RSS本文', article_url: 'https://example.org/post'});
const button = $('#readerModeFullTextButton');
ui.fetchReaderFullText(button);
assert(requests[1].action === 'feed.reader.fulltext' &&
    requests[1].data.content_id === '9' && requests[1].data.item_identity === id &&
    button.prop('disabled') === true &&
    $('#readerModeFullTextStatus').text() === '元記事を安全に取得しています...',
    'Full Text request shows pending state and keeps server-side URL resolution');
ui.fetchReaderFullText(button);
assert(requests.length === 2, 'Full Text prevents simultaneous duplicate requests');
requests[1].deferred.resolve({ok: true, data: {
    full_text_fetch: {fetched: true, stale: false},
    full_text: {html: '<p>サーバーで安全化した本文</p>'}
}});
assert($('#readerModeBody').html() === '<p>サーバーで安全化した本文</p>' &&
    $('#readerModeBody').classes.has('reader-mode-body-rich') &&
    $('#readerModeFullTextStatus').text() === '元記事から本文を表示しています。' &&
    button.data('request-pending') === undefined,
    'Sanitized Full Text replaces RSS text and always clears pending state');

// Communication failure does not remove the original RSS body or leave a spinner running.
ui.renderReaderModePayload({body: '復旧用RSS本文', article_url: 'https://example.org/post'});
ui.fetchReaderFullText(button);
requests[2].deferred.reject({status: 503}, 'timeout');
assert($('#readerModeBody').text() === '復旧用RSS本文' &&
    $('#readerModeFullTextStatus').text().includes('タイムアウト') &&
    button.prop('disabled') === false && button.data('request-pending') === undefined,
    'Full Text timeout preserves the RSS fallback and allows retry');
ui.fetchReaderFullText(button);
requests[3].deferred.resolve({ok: true, data: {full_text_fetch: {fetched: false}, full_text: {html: ''}}});
assert($('#readerModeBody').text() === '復旧用RSS本文' &&
    $('#readerModeFullTextStatus').text().includes('抽出できませんでした'),
    'Full Text extraction failure retains RSS content');

// The old undefined feedRequestErrorMessage also broke the Reader network-error path.
ui.setTrigger(trigger);
ui.openReaderMode();
requests[4].deferred.reject({status: 503}, 'timeout');
assert($('#readerModeStatus').text() === 'コンテンツの取得がタイムアウトしました',
    'Initial Reader timeout uses the extracted Feed error classifier');
ui.setTrigger(trigger);
ui.openReaderMode();
requests[5].deferred.resolve({ok: true, data: {}});
assert($('#readerModeStatus').text() === 'RSS本文を表示出来ませんでした。',
    'Malformed RSS Reader response exits the loading state');
console.log('PASS: Reader Mode split integration regression');