const fs = require('fs');
const path = require('path');
const assert = require('assert');
const js = fs.readFileSync(path.join(__dirname, '../public/js/remote-files.js'), 'utf8');
const php = fs.readFileSync(path.join(__dirname, '../public/remote-files.php'), 'utf8');
for (const id of ['remoteSelectAll', 'remoteSelectedCount', 'remoteBulkDelete']) {
  assert(php.includes('id="' + id + '"'), 'Missing ' + id);
}
assert(php.includes('colspan="5"') && php.includes('remote_sort=dev3bulk1'));
assert(js.includes('state.selectedPaths.clear()'), 'selection must reset when listing changes');
assert(js.includes("await api('remote.file.delete'"), 'reuse existing authenticated delete endpoint');
assert(js.includes("directory: entry.type === 'directory' ? '1' : '0'"), 'retain existing directory semantics');
assert(js.includes('refreshSelectionControls();\n        }\n    }\n\n    async function refreshConnections'), 'unlock controls after refresh');
assert(js.includes('el.selectAll.addEventListener') && js.includes('el.bulkDelete.addEventListener'));
assert(!js.includes('remote.file.bulk_delete'), 'no new bypass API');
console.log('V1.48.0 dev3 bulk delete contract: PASS');
