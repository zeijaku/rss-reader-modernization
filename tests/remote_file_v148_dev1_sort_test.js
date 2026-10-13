const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/remote-files.js'), 'utf8');
const page = fs.readFileSync(path.join(__dirname, '..', 'public/remote-files.php'), 'utf8');
assert(page.includes('id="remoteSortOrder"'), 'sort selector must exist');

const start = source.indexOf('    function sortedEntries() {');
const end = source.indexOf('    function renderEntries() {', start);
assert(start >= 0 && end > start, 'sort helper must be defined before rendering');
const state = {entries: [], sortOrder: 'default'};
const ctx = {state, Intl, Date, Number, String};
vm.createContext(ctx);
vm.runInContext(source.slice(start, end), ctx);
const ordered = (order, entries) => {
    state.sortOrder = order;
    state.entries = entries;
    return Array.from(ctx.sortedEntries(), e => e.name);
};
const entries = [
    {type:'file', name:'file10.txt', size:10, modified_at:'2026-10-01T00:00:00Z'},
    {type:'file', name:'file2.txt', size:20, modified_at:'2026-10-03T00:00:00Z'},
    {type:'directory', name:'folder2', size:null, modified_at:null},
    {type:'file', name:'unknown.txt', size:null, modified_at:null},
    {type:'directory', name:'folder10', size:null, modified_at:null}
];
assert.deepStrictEqual(ordered('default', entries), entries.map(e=>e.name), 'default retains backend order');
assert.deepStrictEqual(ordered('name_asc', entries).slice(0,2), ['folder2','folder10'], 'directories first in natural name order');
assert.deepStrictEqual(ordered('name_desc', entries).slice(0,2), ['folder10','folder2'], 'reverse natural name sort');
assert.deepStrictEqual(ordered('size_desc', entries).slice(2), ['file2.txt','file10.txt','unknown.txt'], 'known large files first');
assert.deepStrictEqual(ordered('date_desc', entries).slice(2), ['file2.txt','file10.txt','unknown.txt'], 'recent files first, missing dates last');
assert.deepStrictEqual(ordered('date_asc', entries).slice(2), ['file10.txt','file2.txt','unknown.txt'], 'oldest files first, missing dates last');
assert.strictEqual(entries[0].name, 'file10.txt', 'underlying data remains unchanged');
console.log('Remote Files V1.48 dev1 sort: PASS');
