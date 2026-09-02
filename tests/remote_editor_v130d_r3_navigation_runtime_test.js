const fs = require('fs');
const vm = require('vm');
const path = require('path');

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
    toggle(name, force) {
        const add = force === undefined ? !this.values.has(name) : !!force;
        if (add) this.values.add(name); else this.values.delete(name);
        return add;
    }
}
class FakeElement {
    constructor(tag = 'div', id = '') {
        this.tagName = tag.toUpperCase();
        this.id = id;
        this.children = [];
        this.options = tag === 'select' ? [new FakeElement('option')] : [];
        this.listeners = {};
        this.classList = new FakeClassList();
        this.attributes = {};
        this.dataset = {};
        this.textContent = '';
        this.className = '';
        this.value = '';
        this.disabled = false;
        this.title = '';
        this.href = '';
        this.type = '';
        this.selectedIndex = 0;
    }
    appendChild(child) {
        this.children.push(child);
        if (this.tagName === 'SELECT') this.options.push(child);
        return child;
    }
    append(...children) { children.forEach(c => this.appendChild(c)); }
    replaceChildren(...children) { this.children = [...children]; }
    remove(index) {
        if (this.tagName === 'SELECT') this.options.splice(index, 1);
    }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    removeAttribute(name) { delete this.attributes[name]; }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
}

const elements = {};
function add(id, tag = 'div') { return elements[id] = new FakeElement(tag, id); }
const stateNode = add('remoteFilesInitialState');
stateNode.textContent = JSON.stringify({
    connections: [{remote_connection_id: 7, name: 'Test', protocol: 'sftp', host: 'example.test', port: 22, base_path: '/', enabled: true}],
    library_files: [], private_network_server_enabled: false
});
const select = add('remoteConnectionSelect', 'select');
const pathNode = add('remoteCurrentPath');
const body = add('remoteFilesBody', 'tbody');
const libraryFile = add('remoteLibraryFile', 'select');
add('remoteConnectionMeta');
add('remoteFtpWarning');
add('remoteUp', 'button');
add('remoteRefresh', 'button');
add('remoteConnectionEdit', 'button');
add('remoteConnectionTest', 'button');
add('remoteConnectionDelete', 'button');
add('remoteUploadOpen', 'button');
add('remoteNewFolder', 'button');
add('remoteLibraryExport', 'button');

const document = {
    getElementById(id) { return elements[id] || null; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    createElement(tag) { return new FakeElement(tag); }
};

let fetchCalls = [];
const window = {
    location: {search: '?remote_connection_id=7&path=%2Fdir'},
    bootstrap: {Modal: class { show() {} hide() {} }},
    fetch(url, options = {}) {
        fetchCalls.push({url: String(url), options});
        return Promise.resolve({
            ok: true,
            status: 200,
            headers: {get() { return null; }},
            json() {
                return Promise.resolve({ok: true, data: {
                    path: '/dir',
                    entries: [
                        {type: 'file', name: 'test.txt', path: '/dir/test.txt', size: 10, modified_at: 'now'},
                        {type: 'file', name: 'test.php', path: '/dir/test.php', size: 20, modified_at: 'now'},
                        {type: 'file', name: 'manual.pdf', path: '/dir/manual.pdf', size: 30, modified_at: 'now'}
                    ]
                }});
            }
        });
    },
    confirm() { return true; }
};

const context = {document, window, URLSearchParams, Number, String, Error, Promise, console, setTimeout, clearTimeout};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'remote-files.js'});
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

function actionsForRow(index) {
    const row = body.children[index];
    const actionCell = row.children[3];
    return actionCell.children[0].children;
}
function titles(actions) { return actions.map(a => a.title); }

(async () => {
    await flush(); await flush();
    check(select.value === '7', 'return URL restores the selected connection');
    check(pathNode.textContent === '/dir', 'return URL restores the parent directory path');
    check(fetchCalls.length >= 1 && String(fetchCalls[0].options.body || '').includes('path=%2Fdir'), 'restored directory is loaded through existing API path');
    check(body.children.length === 3, 'restored directory entries are rendered');

    const txtTitles = titles(actionsForRow(0));
    const phpTitles = titles(actionsForRow(1));
    const pdfTitles = titles(actionsForRow(2));
    check(txtTitles.includes('Edit') && !txtTitles.includes('Preview'), 'TXT uses Edit without duplicate Preview action');
    check(phpTitles.includes('Edit') && !phpTitles.includes('Preview'), 'PHP uses the same Edit action shape as TXT');
    check(pdfTitles.includes('Preview') && !pdfTitles.includes('Edit'), 'PDF keeps Preview because it is not editor-eligible');

    console.log(`RESULT: PASS ${pass} / FAIL ${fail} / SKIP 0`);
    process.exit(fail === 0 ? 0 : 1);
})().catch(error => { console.error(error); process.exit(1); });
