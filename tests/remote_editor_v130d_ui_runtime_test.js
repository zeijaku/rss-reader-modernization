const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'remote-editor.js'), 'utf8');
let pass = 0;
let fail = 0;
function check(condition, label) {
    if (condition) { pass += 1; console.log('PASS: ' + label); }
    else { fail += 1; console.log('FAIL: ' + label); }
}

class FakeClassList {
    constructor() { this.values = new Set(); }
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
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] || ''; }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    dispatch(name, event = {}) { for (const handler of this.listeners[name] || []) handler(event); }
    querySelector(selector) { return selector === 'span' ? this.childSpan : null; }
}

const ids = [
    'remoteEditorInitialState', 'remoteEditorNotice', 'remoteEditorBack', 'remoteEditorLoading',
    'remoteEditorText', 'remoteEditorReload', 'remoteEditorSave', 'remoteEditorDirtyState',
    'remoteEditorMetaType', 'remoteEditorMetaSize', 'remoteEditorMetaEol', 'remoteEditorMetaBom', 'remoteEditorMetaHash'
];
const elements = Object.fromEntries(ids.map(id => [id, new FakeElement(id)]));
const csrfMeta = new FakeElement('csrf');
csrfMeta.setAttribute('content', 'csrf-initial');
elements.remoteEditorSave.childSpan = new FakeElement('saveLabel');
elements.remoteEditorInitialState.textContent = JSON.stringify({
    available: true,
    remote_connection_id: 42,
    path: '/dir/a b.php',
    error_message: ''
});
const document = {
    getElementById(id) { return elements[id] || null; },
    querySelector(selector) { return selector === 'meta[name="csrf-token"]' ? csrfMeta : null; }
};

const dangerousText = '<script>alert("x")</script>\n日本語\n';
const readData = {
    path: '/dir/a b.php', name: 'a b.php', extension: 'php', text: dangerousText,
    byte_size: Buffer.byteLength(dangerousText), sha256: 'a'.repeat(64), utf8_bom: false, line_ending: 'lf'
};

let fetchCalls = [];
let pendingSaveResolve = null;
let mode = 'pending-success';
function response(ok, status, payload, csrf = '') {
    return {
        ok, status,
        headers: { get(name) { return name === 'X-CSRF-Token' ? csrf : null; } },
        json() { return Promise.resolve(payload); }
    };
}
const windowListeners = {};
let confirmResult = true;
let confirmCalls = 0;
const window = {
    btoa(binary) { return Buffer.from(binary, 'latin1').toString('base64'); },
    fetch(url, options = {}) {
        fetchCalls.push({url: String(url), options});
        if ((options.method || 'GET') === 'GET') {
            return Promise.resolve(response(true, 200, {ok: true, data: readData}, 'csrf-after-read'));
        }
        const requestBody = JSON.parse(options.body);
        const requestText = Buffer.from(requestBody.text_base64 || '', 'base64').toString('utf8');
        if (mode === 'pending-success') {
            return new Promise(resolve => { pendingSaveResolve = () => resolve(response(true, 200, {ok: true, data: {
                ...readData,
                text: requestText,
                byte_size: Buffer.byteLength(requestText),
                sha256: 'b'.repeat(64)
            }}, 'csrf-after-save')); });
        }
        if (mode === 'success') {
            return Promise.resolve(response(true, 200, {ok: true, data: {
                ...readData,
                text: requestText,
                byte_size: Buffer.byteLength(requestText),
                sha256: 'c'.repeat(64)
            }}, 'csrf-after-save2'));
        }
        if (mode === 'conflict') {
            return Promise.resolve(response(false, 409, {ok: false, error: {code: 'editor_conflict', message: 'conflict'}}));
        }
        return Promise.resolve(response(false, 500, {ok: false, error: {code: 'remote_operation_failed', message: 'failed'}}));
    },
    confirm() { confirmCalls += 1; return confirmResult; },
    addEventListener(name, handler) { (windowListeners[name] ||= []).push(handler); }
};

const context = {document, window, URLSearchParams, Number, String, Error, Promise, console, setTimeout, clearTimeout, encodeURIComponent, parseInt};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'remote-editor.js'});
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

(async () => {
    await flush(); await flush();
    check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'GET', 'initial load performs one GET');
    check(fetchCalls[0].url.includes('path=%2Fdir%2Fa+b.php'), 'GET safely URL-encodes remote path');
    check(elements.remoteEditorText.value === dangerousText, 'XSS-looking source remains literal textarea value');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'loaded editor starts clean');
    check(elements.remoteEditorSave.disabled === true, 'Save is disabled while editor is clean');
    check(csrfMeta.getAttribute('content') === 'csrf-after-read', 'read response can refresh CSRF token');

    elements.remoteEditorText.value = dangerousText + 'local change\n';
    elements.remoteEditorText.dispatch('input');
    check(elements.remoteEditorDirtyState.textContent === '未保存', 'typing marks editor dirty');
    check(elements.remoteEditorSave.disabled === false && elements.remoteEditorSave.attributes['aria-disabled'] === 'false', 'dirty editor enables Save accessibly');

    const confirmsBeforeSave = confirmCalls;
    elements.remoteEditorSave.dispatch('click');
    await flush();
    check(fetchCalls.length === 2 && fetchCalls[1].options.method === 'POST', 'Save uses dedicated POST request');
    check(fetchCalls[1].options.credentials === 'same-origin', 'Save sends same-origin credentials');
    check(fetchCalls[1].options.headers['Content-Type'] === 'application/json;charset=UTF-8', 'Save sends JSON content type');
    const body = JSON.parse(fetchCalls[1].options.body);
    check(body.csrf_token === 'csrf-after-read', 'Save sends current CSRF token');
    check(body.remote_connection_id === 42 && body.path === '/dir/a b.php', 'Save sends selected connection and relative path');
    check(body.expected_sha256 === 'a'.repeat(64), 'Save sends hash from opened remote state');
    check(typeof body.text_base64 === 'string' && body.text_base64 !== '', 'Save sends Base64 text transport');
    check(!Object.prototype.hasOwnProperty.call(body, 'text'), 'Save omits raw source text field');
    check(Buffer.from(body.text_base64, 'base64').toString('utf8') === dangerousText + 'local change\n', 'Save Base64 round-trip preserves current textarea text');
    check(!fetchCalls[1].options.body.includes('<script>'), 'Save request body does not contain raw script-like source');
    check(confirmCalls === confirmsBeforeSave, 'normal Save does not display repetitive confirmation');
    check(elements.remoteEditorSave.disabled === true && elements.remoteEditorText.disabled === true && elements.remoteEditorReload.disabled === true, 'Save-in-flight disables overlapping edit/reload/save');
    check(elements.remoteEditorSave.childSpan.textContent === '保存中...', 'Save button shows saving state');

    pendingSaveResolve();
    await flush(); await flush();
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'successful save clears dirty state');
    check(elements.remoteEditorSave.disabled === true, 'successful clean state disables Save again');
    check(elements.remoteEditorText.disabled === false && elements.remoteEditorReload.disabled === false, 'controls recover after save');
    check(elements.remoteEditorMetaHash.title === 'b'.repeat(64), 'successful save updates SHA metadata');
    check(csrfMeta.getAttribute('content') === 'csrf-after-save', 'successful save refreshes CSRF token');
    check(elements.remoteEditorNotice.textContent.includes('保存しました'), 'successful save shows success notice');

    mode = 'success';
    elements.remoteEditorText.value += 'ctrl-save\n';
    elements.remoteEditorText.dispatch('input');
    let keyPrevented = false;
    elements.remoteEditorText.dispatch('keydown', {ctrlKey: true, metaKey: false, key: 's', preventDefault() { keyPrevented = true; }});
    await flush(); await flush();
    check(keyPrevented === true, 'Ctrl+S prevents browser default');
    check(fetchCalls.filter(c => c.options.method === 'POST').length === 2, 'Ctrl+S uses same POST save path');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'Ctrl+S success also clears dirty state');

    mode = 'conflict';
    const textBeforeConflict = elements.remoteEditorText.value + 'conflict-local\n';
    elements.remoteEditorText.value = textBeforeConflict;
    elements.remoteEditorText.dispatch('input');
    elements.remoteEditorSave.dispatch('click');
    await flush(); await flush();
    check(elements.remoteEditorDirtyState.textContent === '競合', '409 conflict enters explicit conflict state');
    check(elements.remoteEditorText.value === textBeforeConflict, '409 conflict preserves local unsaved text');
    check(elements.remoteEditorNotice.textContent.includes('上書きせず停止しました'), '409 conflict clearly reports no overwrite');
    check(elements.remoteEditorSave.disabled === true, 'after conflict stale Save is blocked until Remote reload');

    let unloadPrevented = false;
    const unload = {returnValue: null, preventDefault() { unloadPrevented = true; }};
    for (const handler of windowListeners.beforeunload || []) handler(unload);
    check(unloadPrevented && unload.returnValue === '', 'dirty conflict state keeps beforeunload protection');

    confirmResult = false;
    const fetchBeforeCancelReload = fetchCalls.length;
    elements.remoteEditorReload.dispatch('click');
    await flush();
    check(fetchCalls.length === fetchBeforeCancelReload, 'cancelled dirty reload does not discard/refetch');

    console.log(`RESULT: PASS ${pass} / FAIL ${fail} / SKIP 0`);
    process.exit(fail === 0 ? 0 : 1);
})().catch(error => { console.error(error); process.exit(1); });
