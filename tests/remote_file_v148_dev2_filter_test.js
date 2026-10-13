const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/remote-files.js'), 'utf8');
const page = fs.readFileSync(path.join(__dirname, '..', 'public/remote-files.php'), 'utf8');
assert(page.includes('id="remoteFileSearch"') && page.includes('id="remoteTypeFilter"'));
assert(page.includes('remote_sort=dev2filter1'), 'dev2 JavaScript must invalidate browser cache');
const from = source.indexOf('    function sortedEntries() {');
const until = source.indexOf('    function renderEntries() {', from);
assert(from > 0 && until > from);
const state = {entries: [
    {name:'photo2.jpg',type:'file',size:20},
    {name:'Documents',type:'directory',size:null},
    {name:'PHOTO10.PNG',type:'file',size:40},
    {name:'notes.txt',type:'file',size:5}
], sortOrder:'default', searchText:'',typeFilter:'all'};
const context = {state, Intl, Date, Number, String};
vm.createContext(context);
vm.runInContext(source.slice(from,until), context);
const visible = () => Array.from(context.visibleEntries(), item => item.name);
assert.deepStrictEqual(visible(), state.entries.map(x=>x.name));
state.searchText='pHoTo';
assert.deepStrictEqual(visible(),['photo2.jpg'], 'case-insensitive filename match');
state.searchText='';
state.typeFilter='directory';
assert.deepStrictEqual(visible(),['Documents'], 'directory-only filter');
state.typeFilter='file';
state.sortOrder='size_desc';
assert.deepStrictEqual(visible(),['PHOTO10.PNG','photo2.jpg','notes.txt'], 'sort + filter composition');
state.searchText='nothing';
assert.deepStrictEqual(visible(), [], 'no-match state');
assert(source.includes("el.search.addEventListener('input'") && source.includes("el.typeFilter.addEventListener('change'"), 'UI listeners wired');
console.log('V1.48.0 dev2 Remote Files search/filter: PASS');
