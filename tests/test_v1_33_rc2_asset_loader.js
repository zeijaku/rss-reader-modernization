'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/calendar.js'), 'utf8');
const expectedScripts = Array.from(source.matchAll(/loadScript\('([^']+)'\);/g), match => match[1]);
const expectedStyles = Array.from(source.matchAll(/loadStyle\('([^']+)', '([^']+)'\);/g), match => ({
    href: match[1], marker: match[2]
}));
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS: ' + message + '\n');
        return;
    }
    failed += 1;
    process.stderr.write('FAIL: ' + message + '\n');
}

function createParent(name) {
    return {
        name,
        children: [],
        appendChild(node) {
            node.parentNode = this;
            this.children.push(node);
            if (node.tagName === 'SCRIPT') {
                state.activeScripts += 1;
                state.maxActiveScripts = Math.max(state.maxActiveScripts, state.activeScripts);
                state.scriptRequests.push(node.src);
            }
            return node;
        },
        insertBefore(node, target) {
            const index = this.children.indexOf(target);
            node.parentNode = this;
            if (index === -1) {
                this.children.push(node);
            } else {
                this.children.splice(index, 0, node);
            }
            return node;
        },
        removeChild(node) {
            const index = this.children.indexOf(node);
            if (index !== -1) {
                this.children.splice(index, 1);
            }
            node.parentNode = null;
            return node;
        }
    };
}

const state = {
    activeScripts: 0,
    maxActiveScripts: 0,
    scriptRequests: [],
    timers: [],
    errors: []
};
const head = createParent('head');
const body = createParent('body');
const documentObject = {
    head,
    body,
    createElement(tagName) {
        return {
            tagName: String(tagName).toUpperCase(),
            parentNode: null,
            attributes: {},
            setAttribute(name, value) {
                this.attributes[name] = String(value);
            }
        };
    },
    querySelector(selector) {
        const match = /^link\[([^\]]+)\]$/.exec(selector);
        if (!match) {
            return null;
        }
        return head.children.find(node => node.tagName === 'LINK'
            && Object.prototype.hasOwnProperty.call(node.attributes, match[1])) || null;
    },
    querySelectorAll() {
        return [];
    }
};

function setTimeoutFake(callback, delay) {
    state.timers.push({callback, delay});
    return state.timers.length;
}

function runNextTimer(delay) {
    const index = state.timers.findIndex(timer => timer.delay === delay);
    if (index === -1) {
        return false;
    }
    const timer = state.timers.splice(index, 1)[0];
    timer.callback();
    return true;
}

function pendingScript() {
    const scripts = body.children.filter(node => node.tagName === 'SCRIPT' && typeof node.onload === 'function');
    return scripts[scripts.length - 1] || null;
}

function loadScriptNode(node) {
    state.activeScripts -= 1;
    node.onload();
}

function failScriptNode(node) {
    state.activeScripts -= 1;
    node.onerror();
}

const context = {
    document: documentObject,
    setTimeout: setTimeoutFake,
    Math,
    console: {
        error(message) {
            state.errors.push(String(message));
        }
    }
};

vm.runInNewContext(source, context, {filename: 'calendar.js'});

check(expectedScripts.length >= 20, 'test inventory contains the Dashboard script chain');
check(expectedStyles.length >= 10, 'test inventory contains the staged stylesheet chain');
check(!source.includes('api_v1.php') && !source.includes('$.ajax') && !source.includes('fetch('),
    'asset recovery does not retry application API requests');
check(!source.includes('innerHTML') && !source.includes('eval(') && !source.includes('new Function'),
    'loader stabilization introduces no HTML or executable-string sink');
check(state.scriptRequests.length === 1 && state.scriptRequests[0] === expectedScripts[0],
    'only the first JavaScript request starts initially');
check(head.children.filter(node => node.tagName === 'LINK').length === 4,
    'only the first stylesheet batch starts initially');

const firstScript = pendingScript();
failScriptNode(firstScript);
check(state.activeScripts === 0 && state.scriptRequests.length === 1,
    'a failed script blocks the next dependency until retry');
check(runNextTimer(600), 'script retry uses the bounded delay');
const firstRetry = pendingScript();
check(firstRetry && firstRetry.src === expectedScripts[0] + '&asset_retry=1',
    'script retry keeps the asset and adds a cache-safe retry marker');
loadScriptNode(firstRetry);
check(state.scriptRequests[2] === expectedScripts[1],
    'the second script starts only after the first retry succeeds');

while (pendingScript()) {
    const node = pendingScript();
    const isLastOriginal = node.src === expectedScripts[expectedScripts.length - 1];
    if (!isLastOriginal) {
        loadScriptNode(node);
        continue;
    }
    failScriptNode(node);
    check(runNextTimer(600), 'last script receives one retry');
    const retry = pendingScript();
    const timerCount = state.timers.length;
    failScriptNode(retry);
    check(state.timers.length === timerCount, 'a second script failure is not retried indefinitely');
}

check(state.maxActiveScripts === 1, 'JavaScript transfer concurrency remains one');
check(state.scriptRequests.length === expectedScripts.length + 2,
    'only the two simulated failures add retry requests');
check(state.scriptRequests.filter(url => !url.includes('asset_retry=' )).join('\n') === expectedScripts.join('\n'),
    'every original JavaScript request retains its declaration order');
check(state.errors.length === 1 && state.errors[0].includes(expectedScripts[expectedScripts.length - 1]),
    'permanent JavaScript failure is reported without stopping the completed queue');

while (runNextTimer(100)) {
    // Drain the deliberately staggered stylesheet batches.
}
let styleLinks = head.children.filter(node => node.tagName === 'LINK');
check(styleLinks.length === expectedStyles.length, 'all stylesheets are eventually appended');
check(styleLinks.map(node => node.href).join('\n') === expectedStyles.map(entry => entry.href).join('\n'),
    'stylesheet cascade order matches the declaration order');

const failedStyle = styleLinks[1];
failedStyle.onerror();
check(runNextTimer(600), 'stylesheet retry uses the bounded delay');
styleLinks = head.children.filter(node => node.tagName === 'LINK');
check(styleLinks.length === expectedStyles.length
    && styleLinks[1].href === expectedStyles[1].href + '&asset_retry=1',
    'stylesheet retry replaces the failed link at the same cascade position');
const timerCountBeforeSecondStyleFailure = state.timers.length;
styleLinks[1].onerror();
check(state.timers.length === timerCountBeforeSecondStyleFailure,
    'a second stylesheet failure is not retried indefinitely');
check(state.errors.length === 2 && state.errors[1].includes(expectedStyles[1].href),
    'permanent stylesheet failure is reported');

process.stdout.write('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0\n');
process.exit(failed === 0 ? 0 : 1);
