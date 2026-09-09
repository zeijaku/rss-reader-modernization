const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'remote-files.js'), 'utf8');
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
    toggle(name, force) { const result = force === undefined ? !this.values.has(name) : !!force; if (result) this.add(name); else this.remove(name); return result; }
}

class FakeElement {
    constructor(id) {
        this.id = id;
        this.textContent = '';
        this.value = '';
        this.className = '';
        this.classList = new FakeClassList();
        this.attributes = {};
        this.listeners = {};
        this.options = [];
        this.files = [];
        this.checked = false;
        this.disabled = false;
        this.selectedIndex = 0;
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] || ''; }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    appendChild(child) { this.children ||= []; this.children.push(child); if (this.tagName === 'SELECT') this.options.push(child); return child; }
    append(...children) { children.forEach(child => this.appendChild(child)); }
    remove(index) { this.options.splice(index, 1); }
    replaceChildren(...children) { this.children = children; }
    querySelector(selector) { return selector === 'button[type="submit"]' ? this.submitButton : null; }
    reset() { this.files = []; this.value = ''; this.checked = false; }
}

const ids = [
    'remoteFilesInitialState', 'remoteFilesNotice', 'remoteConnectionSelect', 'remoteConnectionMeta',
    'remoteFtpWarning', 'remoteConnectionAdd', 'remoteConnectionEdit', 'remoteConnectionTest',
    'remoteConnectionDelete', 'remoteUp', 'remoteRefresh', 'remoteUploadOpen', 'remoteNewFolder',
    'remoteLibraryExport', 'remoteCurrentPath', 'remoteFilesLoading', 'remoteFilesBody',
    'remoteConnectionForm', 'remoteConnectionModal', 'remoteConnectionModalTitle', 'remoteConnectionId',
    'remoteConnectionName', 'remoteConnectionProtocol', 'remoteConnectionPort', 'remoteConnectionHost',
    'remoteConnectionUsername', 'remoteConnectionBasePath', 'remoteConnectionAuthType', 'remoteConnectionPassword',
    'remotePasswordGroup', 'remoteConnectionPrivateKey', 'remotePrivateKeyGroup', 'remoteConnectionPassphrase',
    'remotePassphraseGroup', 'remoteConnectionAllowPrivate', 'remoteConnectionEnabled', 'remoteNameModal',
    'remoteNameForm', 'remoteNameModalTitle', 'remoteNameLabel', 'remoteNameInput', 'remoteNameHelp',
    'remoteUploadModal', 'remoteUploadForm', 'remoteUploadFile', 'remoteUploadOverwrite', 'remoteLibraryModal',
    'remoteLibraryForm', 'remoteLibraryFile', 'remoteLibraryTargetName', 'remoteLibraryOverwrite',
    'remotePreviewModal', 'remotePreviewModalTitle', 'remotePreviewLoading', 'remotePreviewImage',
    'remotePreviewPdf', 'remotePreviewText', 'remotePreviewCsvWrap', 'remotePreviewCsvBody', 'remotePreviewDownload'
];
const elements = Object.fromEntries(ids.map(id => [id, new FakeElement(id)]));
elements.remoteConnectionSelect.tagName = 'SELECT';
elements.remoteLibraryFile.tagName = 'SELECT';
elements.remoteUploadForm.submitButton = new FakeElement('remoteUploadSubmit');
const csrf = new FakeElement('csrf');
csrf.setAttribute('content', 'csrf-initial');
elements.remoteFilesInitialState.textContent = JSON.stringify({
    connections: [{remote_connection_id: 42, name: 'Test', protocol: 'sftp', host: 'example.test', port: 22, base_path: '/', enabled: false}],
    library_files: []
});

const document = {
    getElementById(id) { return elements[id] || null; },
    querySelector(selector) { return selector === 'meta[name="csrf-token"]' ? csrf : null; },
    createElement(tagName) { const element = new FakeElement(tagName); element.tagName = String(tagName).toUpperCase(); return element; }
};

class FakeFormData {
    constructor() { this.values = {}; }
    set(name, value) { this.values[name] = value; }
}
const files = [{name: 'first.txt', size: 10}, {name: 'second.txt', size: 20}, {name: 'third.txt', size: 30}];
const fetchCalls = [];
let uploadNumber = 0;
function response(ok, status, payload, token) {
    return {ok, status, headers: {get(name) { return name === 'X-CSRF-Token' ? token : null; }}, json() { return Promise.resolve(payload); }};
}
const window = {
    location: {search: '?remote_connection_id=42'},
    bootstrap: {Modal: class { show() {} hide() {} }},
    confirm() { return true; },
    fetch(url, options) {
        fetchCalls.push({url: String(url), options});
        if (String(url).includes('api_v1.php')) {
            return Promise.resolve(response(true, 200, {ok: true, data: {entries: []}}, 'csrf-after-refresh'));
        }
        uploadNumber += 1;
        if (uploadNumber === 2) {
            return Promise.resolve(response(false, 409, {ok: false, error: {message: '同名ファイルがあります。'}}, 'csrf-after-second'));
        }
        return Promise.resolve(response(true, 200, {ok: true, data: {}}, 'csrf-after-' + String(uploadNumber)));
    }
};
const context = {document, window, FormData: FakeFormData, URLSearchParams, Number, String, Error, Promise, console, setTimeout, clearTimeout};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'remote-files.js'});

(async () => {
    elements.remoteUploadFile.files = files;
    elements.remoteUploadOverwrite.checked = true;
    const submit = elements.remoteUploadForm.listeners.submit[0];
    let prevented = false;
    await submit({preventDefault() { prevented = true; }});

    const uploadCalls = fetchCalls.filter(call => call.url.includes('remote_file_upload_api.php'));
    check(prevented, 'upload submit prevents browser form navigation');
    check(uploadCalls.length === 3, 'one request is issued for every selected file');
    check(uploadCalls.every(call => call.options.method === 'POST' && call.options.credentials === 'same-origin'), 'every request uses the existing POST and same-origin boundary');
    check(uploadCalls.map(call => call.options.body.values.file.name).join(',') === 'first.txt,second.txt,third.txt', 'requests preserve selected file order');
    check(uploadCalls.map(call => call.options.body.values.csrf_token).join(',') === 'csrf-initial,csrf-after-1,csrf-after-second', 'each request uses the latest CSRF token');
    check(fetchCalls.filter(call => call.url.includes('api_v1.php')).length === 1, 'directory refresh runs once after the queue completes');
    check(elements.remoteFilesNotice.textContent.includes('成功 2件、失敗 1件'), 'partial result summary reports successful and failed counts');
    check(elements.remoteFilesNotice.textContent.includes('second.txt'), 'partial result summary identifies the failed file');
    check(elements.remoteUploadFile.disabled === false && elements.remoteUploadForm.submitButton.disabled === false, 'controls recover after all requests settle');
    console.log(`RESULT: PASS ${pass} / FAIL ${fail} / SKIP 0`);
    process.exit(fail === 0 ? 0 : 1);
})().catch(error => { console.error(error); process.exit(1); });
