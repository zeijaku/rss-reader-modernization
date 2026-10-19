'use strict';
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const dashboard = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const feedRender = fs.readFileSync(path.join(root, 'public/js/dashboard-feed-render.js'), 'utf8');
const feedFetch = fs.readFileSync(path.join(root, 'public/js/dashboard-feed-fetch.js'), 'utf8');

function functionSource(source, name) {
    const marker = 'function ' + name + '(';
    const start = source.indexOf(marker);
    assert(start >= 0, name + ' was removed from Dashboard');
    const brace = source.indexOf('{', start);
    let depth = 0, end = -1;
    for (let i = brace; i < source.length; i++) {
        if (source[i] === '{') depth++;
        if (source[i] === '}') {
            depth--;
            if (depth === 0) { end = i + 1; break; }
        }
    }
    assert(end > start, 'unable to isolate ' + name);
    return source.slice(start, end);
}

class Element {
    constructor() {
        this.attrs = {};
        this.props = {};
        this.textValue = '';
        this.dataMap = {};
        this.calls = [];
        this.length = 1;
    }
    text(value) {
        if (arguments.length === 0) return this.textValue;
        this.calls.push(['text', value]);
        this.textValue = value;
        return this;
    }
    html(value) {throw Error('Reader RSS must never render untrusted HTML: ' + value);}
    attr(key, value) {
        if (arguments.length === 1) return this.attrs[key];
        this.attrs[key] = value;
        return this;
    }
    prop(key, value) {
        if (arguments.length === 1) return this.props[key];
        this.props[key] = value;
        return this;
    }
    removeClass() {return this;}
    addClass() {return this;}
    data(key, value) {
        if (arguments.length === 1) return this.dataMap[key];
        this.dataMap[key] = value;
        return this;
    }
    removeData(key) {delete this.dataMap[key];return this;}
    find() {return this;}
    contents() {return this;}
    filter() {return this;}
    last() {return this;}
    replaceWith() {return this;}
    modal(arg) {this.modalStatus = arg;return this;}
}
const elements = new Map();
function $(selector) {
    if (!elements.has(selector)) elements.set(selector, new Element());
    return elements.get(selector);
}
const core = {
    apiRequest() {throw Error('Unexpected Feed transport during Reader rendering test');},
    showNotice() {},
};
const windowObject = {IGuguruDashboardCore: core, location:{}};
const context = {
    jQuery: $, $,
    window: windowObject,
    String, Array, Number, Object, RegExp, Math,
};
vm.runInNewContext(feedRender, context, {filename:'dashboard-feed-render.js'});
vm.runInNewContext(feedFetch, context, {filename:'dashboard-feed-fetch.js'});
assert.strictEqual(typeof windowObject.IGuguruDashboardFeedRender.safeFeedLink, 'function');
assert.strictEqual(typeof windowObject.IGuguruDashboardFeedFetch.feedRequestErrorMessage, 'function');
vm.runInNewContext(
    "function feedRenderer() { return window.IGuguruDashboardFeedRender; }\n"
    + functionSource(dashboard, 'renderReaderModePayload'),
    context, {filename:'Reader-render-runtime'}
);

function render(url, body) {
    vm.runInNewContext(
        'renderReaderModePayload({title:"Article",source:"RSS",date:"2026",body:body,article_url:url});',
        {...context, body, url}, {filename:'Reader-test-payload'}
    );
    const original=$('#readerModeOriginalLink');
    return {original,fulltext:$('#readerModeFullTextButton'),body:$('#readerModeBody'),title:$('#readerModeTitle'),status:$('#readerModeStatus')};
}
const valid=render('https://example.net/post','<b>NOT HTML</b>');
assert.strictEqual(valid.original.attrs.href,'https://example.net/post');
assert.strictEqual(valid.original.props.hidden,false);
assert.strictEqual(valid.fulltext.props.disabled,false);
assert.strictEqual(valid.body.textValue,'<b>NOT HTML</b>');
assert.strictEqual(valid.status.props.hidden,true);
assert.strictEqual(valid.title.textValue,'Article');
const blocked=render('javascript:alert(1)','plain');
assert.strictEqual(blocked.original.props.hidden,true);
assert.strictEqual(blocked.original.attrs.href,'#');
assert.strictEqual(blocked.fulltext.props.hidden,true);
assert.strictEqual(blocked.fulltext.props.disabled,true);
const malformed=render('data:text/html,evil','');
assert.strictEqual(malformed.original.attrs.href,'#');
assert.strictEqual(malformed.body.props.hidden,true);

// Exercise the actual Reader fail callback, not merely the Feed error helper:
let pending=null;
const errors=[];
context.articleActionsTrigger={};
context.readerItemContext=()=>({content_id:'12',item_identity:'m1i:v1:'+'a'.repeat(64)});
context.closeArticleActionsMenu=()=>{};
context.resetReaderModeModal=()=>{};
context.showNotice=(x)=>errors.push(x);
context.apiRequest=(action, payload)=>{
    assert.strictEqual(action,'feed.reader');
    assert.strictEqual(payload.content_id,'12');
    pending={};
    return {
        done(fn) {pending.done=fn;return this;},
        fail(fn) {pending.fail=fn;return this;}
    };
};
vm.runInNewContext(functionSource(dashboard,'openReaderMode'),context,{filename:'Reader-open-runtime'});
vm.runInNewContext('openReaderMode();',context);
assert.strictEqual($('#readerModeModal').modalStatus,'show');
assert.strictEqual(typeof pending.done,'function');
assert.strictEqual(typeof pending.fail,'function');
pending.fail({status:503,responseJSON:{error:{code:'reader_unavailable',message:'not ready'}}},'error');
assert.strictEqual($('#readerModeStatus').props.hidden,false);
assert($('#readerModeStatus').textValue.length>0);
console.log('PASS: Reader Mode renders sanitized URLs, plain RSS body and transport errors after Feed modules are split.');
