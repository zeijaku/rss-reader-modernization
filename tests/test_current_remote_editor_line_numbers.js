'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/remote-editor.js'), 'utf8');
let passed = 0;
let failed = 0;
function check(condition, label) {
    if (condition) { passed += 1; process.stdout.write('PASS: ' + label + '\n'); }
    else { failed += 1; process.stderr.write('FAIL: ' + label + '\n'); }
}

class FakeClassList {
    constructor(initial = []) { this.values = new Set(initial); }
    add(name) { this.values.add(name); }
    remove(name) { this.values.delete(name); }
    toggle(name, force) {
        const add = force === undefined ? !this.values.has(name) : !!force;
        if (add) this.values.add(name); else this.values.delete(name);
        return add;
    }
    contains(name) { return this.values.has(name); }
}
class FakeElement {
    constructor(id) {
        this.id = id;
        this.textContent = '';
        this.value = '';
        this.className = '';
        this.classList = new FakeClassList();
        this.disabled = false;
        this.title = '';
        this.attributes = {};
        this.listeners = {};
        this.childSpan = null;
        this.scrollTop = 0;
        this.scrollLeft = 0;
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] || ''; }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    dispatch(name, event = {}) { for (const handler of this.listeners[name] || []) handler(event); }
    querySelector(selector) { return selector === 'span' ? this.childSpan : null; }
}

const ids = [
    'remoteEditorInitialState', 'remoteEditorNotice', 'remoteEditorBack', 'remoteEditorLoading',
    'remoteEditorSurface', 'remoteEditorLineNumbers', 'remoteEditorLineNumbersContent',
    'remoteEditorText', 'remoteEditorReload', 'remoteEditorSave', 'remoteEditorDirtyState',
    'remoteEditorMetaType', 'remoteEditorMetaSize', 'remoteEditorMetaEol', 'remoteEditorMetaBom', 'remoteEditorMetaHash'
];
const elements = Object.fromEntries(ids.map(id => [id, new FakeElement(id)]));
elements.remoteEditorSurface.classList.add('d-none');
elements.remoteEditorText.classList.add('d-none');
elements.remoteEditorSave.childSpan = new FakeElement('saveLabel');
elements.remoteEditorInitialState.textContent = JSON.stringify({
    available: true, remote_connection_id: 7, path: '/notes/sample.txt', error_message: ''
});
const csrf = new FakeElement('csrf');
csrf.setAttribute('content', 'csrf-token');
const documentObject = {
    getElementById(id) { return elements[id] || null; },
    querySelector(selector) { return selector === 'meta[name="csrf-token"]' ? csrf : null; }
};

let fetchCalls = [];
function response(ok, status, payload) {
    return {ok, status, headers: {get() { return null; }}, json() { return Promise.resolve(payload); }};
}
const loadedText = 'one\r\n日本語\r\n<script>literal</script>\r\n';
const windowListeners = {};
const windowObject = {
    btoa(binary) { return Buffer.from(binary, 'latin1').toString('base64'); },
    fetch(url, options = {}) {
        fetchCalls.push({url: String(url), options});
        if ((options.method || 'GET') === 'GET') {
            return Promise.resolve(response(true, 200, {ok: true, data: {
                path: '/notes/sample.txt', extension: 'txt', text: loadedText,
                byte_size: Buffer.byteLength(loadedText), sha256: 'a'.repeat(64),
                utf8_bom: false, line_ending: 'crlf'
            }}));
        }
        const body = JSON.parse(options.body);
        const text = Buffer.from(body.text_base64, 'base64').toString('utf8');
        return Promise.resolve(response(true, 200, {ok: true, data: {
            path: '/notes/sample.txt', extension: 'txt', text,
            byte_size: Buffer.byteLength(text), sha256: 'b'.repeat(64),
            utf8_bom: false, line_ending: 'crlf'
        }}));
    },
    confirm() { return true; },
    addEventListener(name, handler) { (windowListeners[name] ||= []).push(handler); }
};
const context = {
    document: documentObject, window: windowObject, URLSearchParams,
    Number, String, Error, Promise, console, setTimeout, clearTimeout, encodeURIComponent, parseInt
};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'remote-editor.js'});
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

(async () => {
    await flush(); await flush();
    check(elements.remoteEditorSurface.classList.contains('d-none') === false,
        'editor surface becomes visible after a successful load');
    check(elements.remoteEditorLineNumbersContent.textContent === '1\n2\n3\n4',
        'CRLF content and its trailing empty line receive exact logical line numbers');
    check(!elements.remoteEditorLineNumbersContent.textContent.includes('script'),
        'line-number gutter never copies source text');

    elements.remoteEditorText.value = 'single';
    elements.remoteEditorText.dispatch('input');
    check(elements.remoteEditorLineNumbersContent.textContent === '1', 'single-line edit collapses the gutter to line 1');

    elements.remoteEditorText.value = 'alpha\n日本語\nomega\n';
    elements.remoteEditorText.dispatch('input');
    check(elements.remoteEditorLineNumbersContent.textContent === '1\n2\n3\n4',
        'input updates line numbers for multibyte text and a trailing newline');

    const manyLines = Array.from({length: 1000}, (_, index) => 'line-' + (index + 1)).join('\n');
    elements.remoteEditorText.value = manyLines;
    elements.remoteEditorText.dispatch('input');
    check(elements.remoteEditorLineNumbersContent.textContent.split('\n').length === 1000
        && elements.remoteEditorLineNumbersContent.textContent.endsWith('1000'),
        'large edit keeps a complete monotonically increasing gutter');

    elements.remoteEditorText.scrollTop = 321;
    elements.remoteEditorText.scrollLeft = 777;
    elements.remoteEditorText.dispatch('scroll');
    check(elements.remoteEditorLineNumbers.scrollTop === 321, 'vertical editor scrolling stays aligned with the gutter');
    check(elements.remoteEditorLineNumbers.scrollLeft === 0, 'horizontal source scrolling does not move the gutter');

    elements.remoteEditorSave.dispatch('click');
    await flush(); await flush();
    const post = fetchCalls.find(call => call.options.method === 'POST');
    const body = post ? JSON.parse(post.options.body) : {};
    const saved = body.text_base64 ? Buffer.from(body.text_base64, 'base64').toString('utf8') : '';
    check(saved === manyLines, 'Save transport contains only textarea text');
    check(!saved.startsWith('1\n2\n3'), 'visible line numbers are never added to the remote file');
    check(elements.remoteEditorLineNumbersContent.textContent.split('\n').length === 1000,
        'successful save response preserves gutter state');

    process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
    process.exit(failed === 0 ? 0 : 1);
})().catch(error => { console.error(error); process.exit(1); });
