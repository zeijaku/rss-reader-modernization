const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'remote-editor.js'), 'utf8');

let pass = 0;
let fail = 0;
function check(condition, label) {
    if (condition) {
        pass += 1;
        console.log('PASS: ' + label);
    } else {
        fail += 1;
        console.log('FAIL: ' + label);
    }
}

class FakeClassList {
    constructor() { this.values = new Set(); }
    add(name) { this.values.add(name); }
    remove(name) { this.values.delete(name); }
    toggle(name, force) {
        const shouldAdd = force === undefined ? !this.values.has(name) : !!force;
        if (shouldAdd) this.values.add(name); else this.values.delete(name);
        return shouldAdd;
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
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    addEventListener(name, handler) {
        (this.listeners[name] ||= []).push(handler);
    }
    dispatch(name, event = {}) {
        for (const handler of this.listeners[name] || []) {
            handler(event);
        }
    }
}

const ids = [
    'remoteEditorInitialState', 'remoteEditorNotice', 'remoteEditorBack',
    'remoteEditorLoading', 'remoteEditorText', 'remoteEditorReload',
    'remoteEditorSave', 'remoteEditorDirtyState', 'remoteEditorMetaType',
    'remoteEditorMetaSize', 'remoteEditorMetaEol', 'remoteEditorMetaBom',
    'remoteEditorMetaHash'
];
const elements = Object.fromEntries(ids.map(id => [id, new FakeElement(id)]));
elements.remoteEditorInitialState.textContent = JSON.stringify({
    available: true,
    remote_connection_id: 42,
    path: '/dir/a b.php',
    error_message: ''
});

const document = {
    getElementById(id) { return elements[id] || null; }
};

let fetchCount = 0;
let lastFetchUrl = '';
let lastFetchOptions = null;
const dangerousText = '<script>alert("x")</script>\n日本語\n';
const responseData = {
    path: '/dir/a b.php',
    name: 'a b.php',
    extension: 'php',
    text: dangerousText,
    byte_size: Buffer.byteLength(dangerousText, 'utf8'),
    sha256: 'a'.repeat(64),
    utf8_bom: false,
    line_ending: 'lf'
};

let confirmResult = true;
const windowListeners = {};
const window = {
    fetch(url, options) {
        fetchCount += 1;
        lastFetchUrl = String(url);
        lastFetchOptions = options;
        return Promise.resolve({
            ok: true,
            json() { return Promise.resolve({ok: true, data: responseData}); }
        });
    },
    confirm() { return confirmResult; },
    addEventListener(name, handler) { (windowListeners[name] ||= []).push(handler); }
};

const context = {
    document,
    window,
    URLSearchParams,
    Number,
    String,
    Error,
    Promise,
    console,
    setTimeout,
    clearTimeout
};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'remote-editor.js'});

function flush() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

(async () => {
    await flush();
    await flush();

    check(fetchCount === 1, 'initial editor load performs one read request');
    check(lastFetchOptions && lastFetchOptions.method === 'GET', 'editor uses GET');
    check(lastFetchOptions && lastFetchOptions.credentials === 'same-origin', 'editor uses same-origin credentials');
    check(lastFetchUrl.includes('remote_connection_id=42'), 'read URL includes connection id');
    check(lastFetchUrl.includes('path=%2Fdir%2Fa+b.php'), 'read URL safely encodes remote path');
    check(elements.remoteEditorText.value === dangerousText, 'dangerous-looking source remains literal textarea value');
    check(elements.remoteEditorText.disabled === false, 'textarea becomes editable after successful load');
    check(elements.remoteEditorText.classList.contains('d-none') === false, 'textarea becomes visible after successful load');
    check(elements.remoteEditorSave.disabled === true, 'save remains disabled');
    check(elements.remoteEditorSave.attributes['aria-disabled'] === 'true', 'save exposes disabled state accessibly');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'loaded editor starts clean');
    check(elements.remoteEditorMetaType.textContent === 'PHP', 'extension metadata is rendered');
    check(elements.remoteEditorMetaEol.textContent === 'LF', 'EOL metadata is rendered');
    check(elements.remoteEditorMetaBom.textContent === 'No', 'BOM metadata is rendered');
    check(elements.remoteEditorMetaHash.textContent === 'a'.repeat(16) + '…', 'hash is shortened in visible metadata');
    check(elements.remoteEditorMetaHash.title === 'a'.repeat(64), 'full hash is retained as text title');

    elements.remoteEditorText.value = dangerousText + 'change';
    elements.remoteEditorText.dispatch('input');
    check(elements.remoteEditorDirtyState.textContent === '未保存', 'typing marks editor dirty');

    let preventedReload = false;
    confirmResult = false;
    elements.remoteEditorReload.dispatch('click', {preventDefault() { preventedReload = true; }});
    await flush();
    check(fetchCount === 1, 'dirty reload cancellation does not refetch or discard');

    let backPrevented = false;
    elements.remoteEditorBack.dispatch('click', {preventDefault() { backPrevented = true; }});
    check(backPrevented === true, 'dirty back cancellation prevents navigation');

    let saveKeyPrevented = false;
    elements.remoteEditorText.dispatch('keydown', {
        ctrlKey: true,
        metaKey: false,
        key: 's',
        preventDefault() { saveKeyPrevented = true; }
    });
    check(saveKeyPrevented === true, 'Ctrl+S is blocked while save backend is absent');
    check(elements.remoteEditorNotice.textContent.includes('保存機能はまだ利用できません'), 'Ctrl+S explains save limitation');

    let unloadPrevented = false;
    const unloadEvent = {
        returnValue: null,
        preventDefault() { unloadPrevented = true; }
    };
    for (const handler of windowListeners.beforeunload || []) handler(unloadEvent);
    check(unloadPrevented === true && unloadEvent.returnValue === '', 'dirty beforeunload protection is active');

    confirmResult = true;
    elements.remoteEditorReload.dispatch('click');
    await flush();
    await flush();
    check(fetchCount === 2, 'confirmed reload refetches remote text');
    check(elements.remoteEditorText.value === dangerousText, 'confirmed reload restores remote text');
    check(elements.remoteEditorDirtyState.textContent === 'Remoteと同じ', 'confirmed reload clears dirty state');

    console.log(`RESULT: PASS ${pass} / FAIL ${fail} / SKIP 0`);
    process.exit(fail === 0 ? 0 : 1);
})().catch(error => {
    console.error(error);
    process.exit(1);
});
