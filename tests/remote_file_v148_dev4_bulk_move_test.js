const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '..', 'public/js/remote-files.js'), 'utf8');
const page = fs.readFileSync(path.join(__dirname, '..', 'public/remote-files.php'), 'utf8');
for (const id of ['remoteBulkMove', 'remoteBulkMoveModal', 'remoteBulkMoveForm', 'remoteBulkMoveTarget']) {
    assert(page.includes('id="' + id + '"'), 'missing UI control: ' + id);
}
assert(page.includes('remote_sort=dev4move1'), 'cache revision must change');
assert(source.includes("overwrite: '0'"), 'bulk move must not overwrite');
assert(source.includes("await api('remote.file.list'"), 'destination must be preflighted');
assert(source.includes("await api('remote.file.move'"), 'move must use existing API');
assert(source.includes("el.bulkMoveForm.addEventListener('submit', submitBulkMove)"), 'submit listener must be bound');

const start = source.indexOf('    function bulkMoveDestination(raw) {');
const end = source.indexOf('    function openBulkMove() {', start);
assert(start !== -1 && end > start, 'missing path validation helpers');
const context = {};
vm.createContext(context);
vm.runInContext(source.slice(start, end), context);
const dest = context.bulkMoveDestination;
const target = context.bulkMoveTarget;
assert.strictEqual(dest('/backup/'), '/backup');
assert.strictEqual(dest('/'), '/');
assert.strictEqual(dest('backup'), null);
assert.strictEqual(dest('/a/../b'), null);
assert.strictEqual(dest('/a/./b'), null);
assert.strictEqual(dest('/a\\b'), null);
assert.strictEqual(dest('/a\u0000b'), null);
assert.strictEqual(target('/backup', {path:'/docs/a.txt', name:'a.txt', type:'file'}), '/backup/a.txt');
assert.strictEqual(target('/docs', {path:'/docs/a.txt', name:'a.txt', type:'file'}), null);
assert.strictEqual(target('/docs/sub', {path:'/docs', name:'docs', type:'directory'}), null);
assert.strictEqual(target('/docs', {path:'/docs', name:'docs', type:'directory'}), null);
assert.strictEqual(target('/backup', {path:'/docs', name:'docs', type:'directory'}), '/backup/docs');
console.log('V1.48.0 dev4 bulk move validation: PASS');
