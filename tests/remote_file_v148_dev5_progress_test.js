const fs = require('fs');
const path = require('path');
const assert = require('assert');
const page = fs.readFileSync(path.join(__dirname, '..', 'public/remote-files.php'), 'utf8');
const js = fs.readFileSync(path.join(__dirname, '..', 'public/js/remote-files.js'), 'utf8');
for (const id of ['remoteUploadStop','remoteUploadClose','remoteUploadProgressWrap','remoteUploadProgressBar','remoteUploadProgressFill','remoteUploadProgressCounts']) {
    assert(page.includes('id="' + id + '"'), 'missing upload UI: ' + id);
}
assert(page.includes('remote_sort=dev5progress1'), 'asset revision must invalidate cache');
assert(js.includes('function updateUploadProgress('));
assert(js.includes('function requestUploadStop('));
assert(js.includes('state.uploadStopRequested = true;'));
assert(js.includes('if (state.uploadStopRequested) { break; }'), 'stop pending queue before next request');
assert(js.includes('await window.fetch(\'./remote_file_upload_api.php\''), 'retain existing per-file upload');
assert(js.includes("syncCsrf(response)"), 'refresh CSRF on each upload');
assert(js.includes('state.uploadRunning = false;'), 'ensure UI unlock');
assert(js.includes("if (state.uploadRunning) { event.preventDefault(); }"), 'prevent closing during transfer');
assert(js.includes("closeWhenFinished = !stopped"), 'only keep interrupted queue open');
assert(!js.includes('AbortController'), 'avoid aborting active backend transfers and truncated files');
console.log('V1.48 dev5 upload progress contract: PASS');
