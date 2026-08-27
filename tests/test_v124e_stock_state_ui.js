'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'stock-state-ui.js'), 'utf8');

function fake$() {
    return {
        first() { return this; },
        attr() { return ''; },
        removeClass() { return this; },
        addClass() { return this; },
        prop() { return this; },
        text() { return this; }
    };
}
fake$.extend = function (...args) { return Object.assign({}, ...args); };
fake$.ajax = function () { throw new Error('AJAX must not run in pure V1.24-E unit checks'); };

const windowObject = {
    jQuery: fake$,
    URLSearchParams,
    URL,
    location: {
        search: '',
        href: 'https://example.test/stock'
    },
    confirm() { return true; }
};
const documentObject = {};
const context = {
    window: windowObject,
    document: documentObject,
    console,
    Number,
    String,
    Array,
    Object,
    RegExp
};
vm.createContext(context);
vm.runInContext(source, context, {filename: 'stock-state-ui.js'});
const ui = windowObject.RssStockStateUi;

let tests = 0;
let failures = 0;
function check(condition, message) {
    tests += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
}

check(!!ui, 'Stock state UI exports its focused test surface');
windowObject.location.search = '';
check(JSON.stringify(ui.currentFilters()) === JSON.stringify({processed: 'all', important: 'all', archive: 'active'}), 'default filters are all/all/active');
windowObject.location.search = '?processed=processed&important=important&archive=archived';
check(JSON.stringify(ui.currentFilters()) === JSON.stringify({processed: 'processed', important: 'important', archive: 'archived'}), 'valid state filters are parsed');
windowObject.location.search = '?processed=x&important=y&archive=z';
check(JSON.stringify(ui.currentFilters()) === JSON.stringify({processed: 'all', important: 'all', archive: 'active'}), 'invalid state filters fall back safely');

const active = {processed: 'all', important: 'all', archive: 'active'};
const archived = {processed: 'all', important: 'all', archive: 'archived'};
const processed = {processed: 'processed', important: 'all', archive: 'all'};
const unprocessed = {processed: 'unprocessed', important: 'all', archive: 'all'};
const important = {processed: 'all', important: 'important', archive: 'all'};
const normal = {processed: 'all', important: 'normal', archive: 'all'};
const all = {processed: 'all', important: 'all', archive: 'all'};
check(ui.stateWouldLeaveFilter(active, 'archived', 1) === true, 'archiving from normal view requires list resync');
check(ui.stateWouldLeaveFilter(archived, 'archived', 0) === true, 'unarchiving from Archive view requires list resync');
check(ui.stateWouldLeaveFilter(all, 'archived', 1) === false, 'Archive all view can update in place');
check(ui.stateWouldLeaveFilter(processed, 'processed', 0) === true, 'processed filter removes an item changed back to unprocessed');
check(ui.stateWouldLeaveFilter(unprocessed, 'processed', 1) === true, 'unprocessed filter removes an item changed to processed');
check(ui.stateWouldLeaveFilter(important, 'important', 0) === true, 'important filter removes an item changed to normal');
check(ui.stateWouldLeaveFilter(normal, 'important', 1) === true, 'normal filter removes an item changed to important');
check(ui.stateWouldLeaveFilter(all, 'important', 1) === false, 'all state view keeps individual updates in place');
check(ui.statePresentation('processed', 1).label === '処理済み', 'processed active label is preserved');
check(ui.statePresentation('important', 1).label === '重要', 'important active label is preserved');
check(ui.statePresentation('archived', 1).label === 'Archive済み', 'archive active label is preserved');

if (failures > 0) {
    console.error(`${failures}/${tests} V1.24-E UI checks failed.`);
    process.exit(1);
}
console.log(`All ${tests} V1.24-E UI checks passed.`);
