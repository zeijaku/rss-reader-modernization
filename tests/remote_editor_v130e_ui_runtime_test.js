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
        this.href = '';
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
const phaseNote = new FakeElement('phaseNote');
csrfMeta.setAttribute('content', 'csrf-initial');
elements.remoteEditorSave.childSpan = new FakeElement('saveLabel');
elements.remoteEditorInitialState.textContent = JSON.stringify({
    available: true,
    remote_connection_id: 42,
    path: '/dir/conflict.php',
    error_message: ''
});
const document = {
    getElementById(id) { return elements[id] || null; },
    querySelector(selector) {
        if (selector === 'meta[name="csrf-token"]') { return csrfMeta; }
        if (selector === '.remote-editor-phase-note') { return phaseNote; }
        return null;
    }
};

function response(ok, status, payload, csrf = '') {
    return {
        ok, status,
        headers: { get(name) { return name === 'X-CSRF-Token' ? csrf : null; } },
        json() { return Promise.resolve(payload); }
    };
}

let getMode = 'initial';
let saveMode = 'success';
let fetchCalls = [];
let confirmResult = true;
let confirmCalls = [];
const windowListeners = {};

const initialRemote = {
    path: '/dir/conflict.php', name: 'conflict.php', extension: 'php',
    text: 'one\r\ntwo\r\n', byte_size: 10, sha256: 'a'.repeat(64),
    utf8_bom: true, line_ending: 'crlf'
};
const latestRemote = {
    ...initialRemote,
    text: 'REMOTE\r\nLATEST', byte_size: 16, sha256: 'd'.repeat(64),
    utf8_bom: true, line_ending: 'crlf'
};

const window = {
    btoa(binary) { return Buffer.from(binary, 'latin1').toString('base64'); },
    fetch(url, options = {}) {
        fetchCalls.push({url: String(url), options});
        const method = options.method || 'GET';
        if (method === 'GET') {
            if (getMode === 'fail') {
                return Promise.resolve(response(false, 503, {ok: false, error: {code: 'remote_unavailable', message: 'reload failed'}}));
            }
            const data = getMode === 'latest' ? latestRemote : initialRemote;
            return Promise.resolve(response(true, 200, {ok: true, data}, getMode === 'latest' ? 'csrf-latest' : 'csrf-read'));
        }
        const body = JSON.parse(options.body);
        const requestText = Buffer.from(body.text_base64 || '', 'base64').toString('utf8');
        if (saveMode === 'conflict') {
            return Promise.resolve(response(false, 409, {ok: false, error: {code: 'editor_conflict', message: 'conflict'}}));
        }
        const remoteText = requestText.replace(/\n/g, '\r\n');
        return Promise.resolve(response(true, 200, {ok: true, data: {
            ...initialRemote,
            text: remoteText,
            byte_size: Buffer.byteLength(remoteText) + 3,
            sha256: 'b'.repeat(64),
            line_ending: requestText.includes('\n') ? 'crlf' : 'none'
        }}, 'csrf-save'));
    },
    confirm(message) { confirmCalls.push(String(message)); return confirmResult; },
    addEventListener(name, handler) { (windowListeners[name] ||= []).push(handler); }
};

const context = {document, window, URLSearchParams, Number, String, Error, Promise, console, setTimeout, clearTimeout, encodeURIComponent, parseInt};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'remote-editor.js'});
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

(async () => {
    await flush(); await flush();

    check(fetchCalls.length === 1 && fetchCalls[0].options.method === 'GET', 'E initial state performs one bounded read request');
    check(elements.remoteEditorText.value === 'one\ntwo\n', 'CRLF GET text is normalized explicitly to LF in textarea');
    check(elements.remoteEditorMetaEol.textContent === 'CRLF', 'metadata still reports original Remote CRLF');
    check(elements.remoteEditorMetaBom.textContent === 'Yes', 'BOM metadata remains visible while textarea omits BOM');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'initial normalized buffer is clean');
    check(elements.remoteEditorReload.attributes['aria-label'] === 'Remoteから再読込', 'normal reload accessible label is non-conflict wording');
    check(phaseNote.textContent.includes('V1.30-E checkpoint'), 'E checkpoint guidance is rendered at startup');

    elements.remoteEditorText.value = 'one\ntwo\nlocal\n';
    elements.remoteEditorText.dispatch('input');
    elements.remoteEditorSave.dispatch('click');
    await flush(); await flush();

    const saveBody = JSON.parse(fetchCalls.find(c => c.options.method === 'POST').options.body);
    check(Buffer.from(saveBody.text_base64, 'base64').toString('utf8') === 'one\ntwo\nlocal\n', 'save transport sends normalized LF editor text');
    check(elements.remoteEditorText.value === 'one\ntwo\nlocal\n', 'CRLF save response is normalized back to LF without dirty drift');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'successful CRLF round-trip remains clean');
    check(elements.remoteEditorMetaEol.textContent === 'CRLF', 'successful save keeps CRLF metadata');

    saveMode = 'conflict';
    const localConflict = elements.remoteEditorText.value + 'mine\n';
    elements.remoteEditorText.value = localConflict;
    elements.remoteEditorText.dispatch('input');
    const postBeforeConflict = fetchCalls.filter(c => c.options.method === 'POST').length;
    elements.remoteEditorSave.dispatch('click');
    await flush(); await flush();

    check(fetchCalls.filter(c => c.options.method === 'POST').length === postBeforeConflict + 1, 'conflict is observed from exactly one stale save attempt');
    check(elements.remoteEditorDirtyState.textContent === '競合', '409 changes badge to explicit conflict state');
    check(elements.remoteEditorDirtyState.className.includes('text-bg-danger'), 'conflict badge uses danger emphasis');
    check(elements.remoteEditorText.value === localConflict, '409 preserves local unsaved buffer exactly');
    check(elements.remoteEditorSave.disabled === true && elements.remoteEditorSave.attributes['aria-disabled'] === 'true', 'conflict disables stale Save accessibly');
    check(elements.remoteEditorReload.attributes['aria-label'] === 'Remote最新版を再読込' && elements.remoteEditorReload.title === 'Remote最新版を再読込', 'conflict changes reload accessible label to latest-Remote recovery');
    check(elements.remoteEditorNotice.textContent.includes('ローカル入力は保持') && elements.remoteEditorNotice.textContent.includes('上書きせず停止'), 'conflict notice explains local preservation and no overwrite');

    const postBeforeRepeat = fetchCalls.filter(c => c.options.method === 'POST').length;
    elements.remoteEditorSave.dispatch('click');
    await flush();
    check(fetchCalls.filter(c => c.options.method === 'POST').length === postBeforeRepeat, 'repeated button Save after conflict does not send another stale POST');
    check(elements.remoteEditorNotice.textContent.includes('Saveは停止中'), 'repeated stale Save explains recovery requirement');

    let keyPrevented = false;
    elements.remoteEditorText.dispatch('keydown', {ctrlKey: true, metaKey: false, key: 's', preventDefault() { keyPrevented = true; }});
    await flush();
    check(keyPrevented === true, 'Ctrl+S remains intercepted during conflict');
    check(fetchCalls.filter(c => c.options.method === 'POST').length === postBeforeRepeat, 'Ctrl+S cannot bypass conflict Save lock');

    elements.remoteEditorText.value += 'more-local\n';
    elements.remoteEditorText.dispatch('input');
    check(elements.remoteEditorDirtyState.textContent === '競合' && elements.remoteEditorSave.disabled === true, 'editing further cannot clear conflict lock');

    let unloadPrevented = false;
    const unload = {returnValue: null, preventDefault() { unloadPrevented = true; }};
    for (const handler of windowListeners.beforeunload || []) handler(unload);
    check(unloadPrevented && unload.returnValue === '', 'conflicted dirty buffer keeps beforeunload protection');

    confirmResult = false;
    const fetchBeforeCancel = fetchCalls.length;
    elements.remoteEditorReload.dispatch('click');
    await flush();
    check(fetchCalls.length === fetchBeforeCancel, 'cancelled conflict reload does not discard or refetch');
    check(confirmCalls.at(-1).includes('競合後のローカル変更') && confirmCalls.at(-1).includes('Remote最新版'), 'conflict reload confirmation names local discard and latest Remote');

    confirmResult = true;
    getMode = 'fail';
    const preservedBeforeFailure = elements.remoteEditorText.value;
    elements.remoteEditorReload.dispatch('click');
    await flush(); await flush();
    check(elements.remoteEditorText.value === preservedBeforeFailure, 'failed conflict reload keeps local buffer after confirmed recovery attempt');
    check(elements.remoteEditorDirtyState.textContent === '競合' && elements.remoteEditorSave.disabled === true, 'failed reload keeps conflict lock active');
    check(elements.remoteEditorNotice.textContent.includes('ローカル入力は保持'), 'failed reload explicitly reports local buffer retention');

    getMode = 'latest';
    elements.remoteEditorReload.dispatch('click');
    await flush(); await flush();
    check(elements.remoteEditorText.value === 'REMOTE\nLATEST', 'successful conflict reload installs latest Remote text normalized to LF');
    check(elements.remoteEditorMetaHash.title === 'd'.repeat(64), 'successful recovery updates optimistic SHA baseline');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ' && elements.remoteEditorSave.disabled === true, 'successful recovery clears dirty/conflict state');
    check(elements.remoteEditorReload.attributes['aria-label'] === 'Remoteから再読込', 'successful recovery restores normal reload accessible label');
    check(csrfMeta.getAttribute('content') === 'csrf-latest', 'recovery read can refresh CSRF token');

    let cleanUnloadPrevented = false;
    const cleanUnload = {returnValue: null, preventDefault() { cleanUnloadPrevented = true; }};
    for (const handler of windowListeners.beforeunload || []) handler(cleanUnload);
    check(cleanUnloadPrevented === false, 'clean recovered state no longer blocks navigation');

    check(elements.remoteEditorBack.href.includes('remote_connection_id=42') && elements.remoteEditorBack.href.includes('path=%2Fdir'), 'D-R3 state-preserving Remote Files back URL remains intact');

    console.log(`RESULT: PASS ${pass} / FAIL ${fail} / SKIP 0`);
    process.exit(fail === 0 ? 0 : 1);
})().catch(error => { console.error(error); process.exit(1); });
