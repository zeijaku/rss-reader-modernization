const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(path.join(__dirname, '../public/js/remote-files.js'), 'utf8');
const page = fs.readFileSync(path.join(__dirname, '../public/remote-files.php'), 'utf8');
assert(page.includes('id="remoteUploadDirectory" webkitdirectory multiple'));
assert(page.includes('remote_sort=dev6dir1'));
const begin = source.indexOf('    function directoryUploadItems(files) {');
const end = source.indexOf('    async function uploadFile(event) {', begin);
assert(begin > 0 && end > begin);
const operations = [];
const ctx = {
  pathJoin(parent, name) { return (parent === '/' ? '' : parent) + '/' + name; },
  api: async (action, data) => {
    operations.push({action, data});
    if (action === 'remote.file.list') {
      return {entries: operations.some(x => x.action === 'remote.file.mkdir' && x.data.path === data.path + '/root')
        ? [{name:'root', type:'directory'}] : []};
    }
    return {path:data.path};
  },
  String, Number, Array, Set, Error
};
vm.createContext(ctx);
vm.runInContext(source.slice(begin, end), ctx);
const pick = ctx.directoryUploadItems;
const items = Array.from(pick([
  {name:'one.txt', size:100, webkitRelativePath:'root/one.txt'},
  {name:'two.txt', size:200, webkitRelativePath:'root/sub/two.txt'}
]));
assert.deepStrictEqual(Array.from(items[1].folders), ['root','sub']);
assert.throws(() => pick([{name:'x',size:1,webkitRelativePath:'root/../x'}]));
assert.throws(() => pick([{name:'x',size:1,webkitRelativePath:'root/a\\b/x'}]));
assert.throws(() => pick([{name:'x',size:1,webkitRelativePath:'root/./x'}]));
assert.throws(() => pick([{name:'x',size:1,webkitRelativePath:'root/other'}]));
assert.throws(() => pick([{name:'big',size:501*1024*1024,webkitRelativePath:'root/big'}]));
assert.throws(() => pick(Array.from({length:1001}, () => ({name:'x',size:1,webkitRelativePath:'root/x'}))));
(async () => {
  const known = new Set();
  assert.strictEqual(await ctx.ensureRemoteUploadFolder(42, '/uploads', ['root','sub'], known), '/uploads/root/sub');
  const count = operations.length;
  assert.strictEqual(await ctx.ensureRemoteUploadFolder(42, '/uploads', ['root','sub'], known), '/uploads/root/sub');
  assert.strictEqual(operations.length, count, 'do not create folders twice');
  assert(source.includes("await window.fetch('./remote_file_upload_api.php'"), 'retain single-file upload');
  assert(source.includes('if (state.uploadStopRequested) { break; }'), 'stop before next file');
  console.log('V1.48 dev6 directory upload: PASS');
})().catch(err => {console.error(err);process.exitCode=1;});
