'use strict';
// Execute the real extracted Article Actions URL-copy and X-share module.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const load = (file) => fs.readFileSync(path.join(root, 'public/js', file), 'utf8');

let menuData = {}, closed = [], notices = [], copiedValue = null;
let executed = 0, copiedLegacy = true, textareaCreated = null, appended = [];
let clipboardMode = 'success', secureClipboardWritten = null;
let openCount = 0, allowPopup = true, popup = null;
const doc = {
    createElement(name) {
        assert.strictEqual(name, 'textarea');
        textareaCreated = {
            value: '', attributes: {}, style: {}, focusCalls: 0, selectCalls: 0,
            setAttribute(k, v) { this.attributes[k] = v; },
            focus() { this.focusCalls++; },
            select() { this.selectCalls++; },
            setSelectionRange(a, b) {
                assert.strictEqual(a, 0);
                assert.strictEqual(b, this.value.length);
            }
        };
        return textareaCreated;
    },
    body: {
        appendChild(value) { appended.push(value); },
        removeChild(value) { assert.strictEqual(appended.pop(), value); }
    },
    execCommand(command) { assert.strictEqual(command, 'copy'); executed++; return copiedLegacy; }
};
const win = {
    isSecureContext: false,
    navigator: {
        clipboard: {
            writeText(value) {
                secureClipboardWritten = value;
                if (clipboardMode === 'throw') throw Error('clipboard denied');
                if (clipboardMode === 'reject') return Promise.reject(Error('clipboard denied'));
                return Promise.resolve();
            }
        }
    },
    open(url, target) {
        assert.strictEqual(url, '');
        assert.strictEqual(target, '_blank');
        openCount++;
        if (!allowPopup) return null;
        popup = {opener: {privileged: true}, location: {href: ''}};
        return popup;
    },
    IGuguruDashboardCore: {
        showNotice(message, type, duration) { notices.push({message, type, duration}); }
    },
    IGuguruDashboardArticleMenu: {
        closeArticleActionsMenu(returnFocus) {
            closed.push(returnFocus);
            menuData = {};
        }
    }
};
function $(value) {
    if (value !== '#articleActionsMenu') throw Error('Unexpected selector: ' + value);
    return {data(key) { return menuData[key]; }};
}
const ctx = {jQuery: $, window:win, document:doc, String, Array, Number,
    Math, Object, encodeURIComponent, console};
const real = load('dashboard-article-share.js');
vm.runInNewContext(real, ctx, {filename:'dashboard-article-share.js'});
const share = win.IGuguruDashboardArticleShare;
assert(share && typeof share.copyArticleUrl === 'function' && typeof share.openArticleOnX === 'function');
vm.runInNewContext(real, ctx, {filename:'dashboard-article-share.js repeated'});
assert.strictEqual(win.IGuguruDashboardArticleShare, share);
console.log('PASS: share module exports a stable duplicate-load-safe interface');

const dash = load('dashboard.js');
const marker = '$(initDashboard);';
assert.strictEqual(dash.split(marker).length, 2, 'Dashboard startup marker must remain unique');
vm.runInNewContext(dash.replace(marker,
    'window.__articleShareDelegates = {copyArticleUrl: copyArticleUrl, openArticleOnX: openArticleOnX};'),
    ctx, {filename: 'dashboard.js'});
const controller = win.__articleShareDelegates;
assert(typeof controller.copyArticleUrl === 'function' && typeof controller.openArticleOnX === 'function');
console.log('PASS: actual Dashboard controller delegates actions through the new module');

async function run() {
    const url = 'https://example.org/item?a=1&b=2#part';
    win.isSecureContext = false;
    menuData = {'article-url': url};
    controller.copyArticleUrl({});
    assert.strictEqual(executed, 1);
    assert.strictEqual(textareaCreated.value, url);
    assert.strictEqual(textareaCreated.attributes.readonly, 'readonly');
    assert.strictEqual(textareaCreated.focusCalls, 1);
    assert.strictEqual(textareaCreated.selectCalls, 1);
    assert.strictEqual(appended.length, 0);
    assert.strictEqual(closed.at(-1), true);
    assert.strictEqual(notices.at(-1).message, '記事URLをコピーしました');
    console.log('PASS: insecure context falls back to legacy exact URL copy and restores focus');

    copiedLegacy = false;
    menuData = {'article-url': url};
    controller.copyArticleUrl({});
    assert.strictEqual(notices.at(-1).type, 'danger');
    assert.strictEqual(notices.at(-1).message, '記事URLをコピー出来ませんでした');
    console.log('PASS: failed legacy copy reports original controlled error');
    copiedLegacy = true;
    const countBeforeEmpty = executed;
    menuData = {'article-url': ''};
    controller.copyArticleUrl({});
    assert.strictEqual(executed, countBeforeEmpty);
    assert.strictEqual(notices.at(-1).message, 'コピーする記事URLを確認出来ませんでした');
    console.log('PASS: empty article URL never reaches clipboard');

    win.isSecureContext = true;
    clipboardMode = 'success';
    menuData = {'article-url': url};
    controller.copyArticleUrl({});
    await Promise.resolve();
    assert.strictEqual(secureClipboardWritten, url);
    assert.strictEqual(notices.at(-1).type, 'success');
    console.log('PASS: secure context uses Clipboard API with exact article URL');

    clipboardMode = 'reject';
    const beforeReject = executed;
    menuData = {'article-url': url};
    controller.copyArticleUrl({});
    await Promise.resolve();
    await Promise.resolve();
    assert.strictEqual(executed, beforeReject + 1);
    assert.strictEqual(notices.at(-1).message, '記事URLをコピーしました');
    console.log('PASS: rejected Clipboard API falls back to one legacy copy');

    clipboardMode = 'throw';
    const beforeThrow = executed;
    menuData = {'article-url': url};
    controller.copyArticleUrl({});
    assert.strictEqual(executed, beforeThrow + 1);
    console.log('PASS: synchronous Clipboard API exception keeps legacy fallback');

    allowPopup = true;
    const title = '😀'.repeat(202);
    menuData = {'article-url': url, 'article-title': title};
    controller.openArticleOnX();
    assert.strictEqual(popup.opener, null);
    const parsed = new URL(popup.location.href);
    assert.strictEqual(parsed.origin, 'https://x.com');
    assert.strictEqual(parsed.pathname, '/intent/post');
    assert.strictEqual(parsed.searchParams.get('url'), url);
    const text = parsed.searchParams.get('text');
    assert.strictEqual(Array.from(text).length, 200);
    assert(text.endsWith('…'));
    assert.strictEqual(notices.at(-1).message, 'Xの投稿画面を開きました');
    assert.strictEqual(closed.at(-1), true);
    console.log('PASS: X intent escapes URL, trims 200 Unicode codepoints and detaches opener');

    const openBefore = openCount;
    menuData = {'article-url': '', 'article-title': 'Test'};
    controller.openArticleOnX();
    assert.strictEqual(openCount, openBefore);
    assert.strictEqual(notices.at(-1).message, 'Xへ投稿する記事URLを確認出来ませんでした');
    console.log('PASS: empty article URL does not open X intent');

    allowPopup = false;
    menuData = {'article-url': url, 'article-title':'短い見出し'};
    controller.openArticleOnX();
    assert.strictEqual(notices.at(-1).type, 'danger');
    assert(notices.at(-1).message.includes('ポップアップ設定'));
    console.log('PASS: blocked X popup reports controlled error');

    assert(!real.includes('apiRequest(') && !real.includes('stock.delete') &&
        !real.includes('task.item.create') && !real.includes('feed.reader'),
        'Article share module must not introduce application mutations or Reader dependencies');
    console.log('PASS: module contains no Stock/Task/Reader API changes');
    console.log('PASS: Dev10-C1 Article share regression');
}
run().catch(error => {console.error(error);process.exitCode=1;});
