from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
page = (ROOT / 'public/remote-files.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/remote-files.js').read_text(encoding='utf-8')
upload_api = (ROOT / 'public/remote_file_upload_api.php').read_text(encoding='utf-8')

passes = 0
fails = 0


def check(condition, label):
    global passes, fails
    if condition:
        passes += 1
        print(f'PASS: {label}')
    else:
        fails += 1
        print(f'FAIL: {label}')


check('id="remoteUploadFile" multiple' in page,
      'Remote Files file picker allows multiple selection')
check('複数ファイルを選択できます' in page and '成功／失敗の結果を表示します' in page,
      'Upload dialog explains sequential multi-file behavior and result reporting')
check('Array.prototype.slice.call(el.uploadFile.files)' in js,
      'browser converts FileList into an iterable upload queue')
check('for (var index = 0; index < files.length; index += 1)' in js and 'await window.fetch' in js,
      'files are sent one at a time in deterministic order')
check("form.set('file', file)" in js and "remote_file_upload_api.php" in js,
      'each selected File uses the existing single-file upload endpoint')
check("form.set('csrf_token', csrfToken())" in js and "syncCsrf(response)" in js,
      'each upload refreshes and sends the current CSRF token')
check("credentials: 'same-origin'" in js,
      'each upload retains same-origin session credentials')
check('failures.push' in js and 'successCount' in js and 'Upload完了: 成功 ' in js,
      'partial failures are collected and summarized without aborting later files')
check('el.uploadFile.disabled = true' in js and 'submitButton.disabled = true' in js,
      'duplicate submits are blocked while the queue is processing')
check('el.uploadForm.reset();' in js and 'modals.upload.hide();' in js,
      'existing post-upload form reset and modal close behavior remains')
check("$_FILES['file']" in upload_api and 'app_csrf_is_valid($csrf)' in upload_api,
      'server API remains the authenticated single-file and CSRF-protected contract')
check('remote_upload_validate_file' in upload_api and 'remote_service_upload_stream' in upload_api,
      'server-side validation and owner-scoped transfer path are unchanged')
check('multiple' not in upload_api,
      'no multipart server aggregation or database/API contract change is introduced')

print(f'RESULT: PASS {passes} / FAIL {fails}')
raise SystemExit(0 if fails == 0 else 1)
