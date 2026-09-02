from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

version = read('app/version.php')
editor = read('app/remote_file/remote_editor.php')
api = read('public/remote_file_editor_api.php')
page = read('public/remote-editor.php')
js = read('public/js/remote-editor.js')
remote_files = read('public/js/remote-files.js')
remote_files_css = read('public/css/remote-files.css')

checks: list[bool] = []
def check(condition: bool, label: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + label)

check("const APP_VERSION = '1.30.0-dev.5';" in version, 'E uses dev.5 visible version')
check("const APP_ASSET_REVISION = '1.30.0-dev.5';" in version, 'E bumps asset revision to avoid stale editor JS')

check('V1.30-E checkpoint' in js and '競合時はSaveを停止' in js, 'client renders current E checkpoint guidance')
check('ローカル入力を保持' in js and 'LF / CRLFとUTF-8 BOM' in js, 'E guidance explains conflict preservation and EOL/BOM scope')
check('id="remoteEditorReload"' in page, 'existing reload control remains present')
check("el.reload.setAttribute('aria-label', label)" in js, 'conflict recovery updates reload accessible label')

check('conflicted: false' in js, 'client has explicit conflicted state')
check('state.conflicted || state.loading || state.saving' in js, 'conflicted state participates in Save-disable guard')
check("el.dirty.textContent = '競合'" in js and "text-bg-danger" in js, 'conflict has explicit danger badge')
check('function setConflicted(value)' in js, 'conflict state changes are centralized')
check("setConflicted(true);" in js, 'HTTP 409 transitions editor into conflicted state')
check('Remote最新版を再読込' in js, 'conflict recovery label points to latest Remote')
check('競合後のローカル変更を破棄' in js, 'conflict reload has explicit discard confirmation')
check('Saveは停止中です' in js, 'repeat stale save gives a clear blocked-save message')
check("if (state.conflicted)" in js.split('async function saveRemoteText()', 1)[1], 'save routine fails closed before network when conflicted')
check('force_overwrite' not in js.lower() and 'overwrite: true' not in js.lower(), 'E still exposes no force-overwrite bypass')

check('function normalizeEditorText(value)' in js, 'client explicitly normalizes server CRLF into textarea LF')
check("replace(/\\r\\n/g, '\\n')" in js, 'normalization only converts supported CRLF pairs to LF')
check("var text = normalizeEditorText" in js, 'GET response uses explicit newline normalization')
check("el.text.value = normalizeEditorText" in js, 'save response also uses explicit newline normalization')
check('previous.conflicted' in js and 'previous.text' in js and 'previous.sha256' in js, 'reload failure snapshots conflict/local/SHA state')
check('ローカル入力は保持しています。' in js, 'failed reload reports that local input was retained')

check("$lineEnding === 'crlf'" in editor and 'str_replace("\\n", "\\r\\n", $text)' in editor, 'server reconstructs CRLF from LF editor text')
check("$lineEnding === 'lf' || $lineEnding === 'none'" in editor, 'server keeps LF or no-EOL source without forced conversion')
check("$current['utf8_bom']" in editor and '"\\xEF\\xBB\\xBF" . $bytes' in editor, 'server preserves UTF-8 BOM')
check("$lineEnding === 'mixed' || $lineEnding === 'cr'" in editor, 'mixed/CR-only source remains fail-closed')
check('str_contains($text, "\\r")' in editor, 'direct save input cannot inject raw CR/mixed EOL')
check("remote_editor_inspect_bytes($pathInfo['path'], $saveBytes)" in editor, 'final reconstructed bytes are re-inspected for byte limit and EOL validity')

check("text_base64" in api and "base64_decode($encoded, true)" in api, 'D-R2 WAF-safe Base64 transport remains intact')
check("'editor_conflict' => 'The remote file changed after it was opened. Reload before saving.'" in api, 'API conflict remains stable HTTP/application contract')
check("remote_editor_save($userId, $connectionId, $path, $text, $expectedSha256)" in api, 'API still dispatches through optimistic save backend')
check('JSON_HEX_TAG' in api and 'app_send_no_store_headers()' in api, 'API response hardening remains intact')

check('function backUrl()' in js and "params.set('path', pathParent(state.path))" in js, 'D-R3 editor return-flow URL remains intact')
check("new URLSearchParams(window.location.search || '')" in remote_files, 'D-R3 Remote Files restoration remains intact')
check('[title="Preview"] i { color: var(--bs-info); }' in remote_files_css, 'Preview icon uses info/cyan semantic color')
check('[title="Download"] i { color: var(--bs-primary); }' in remote_files_css, 'Download icon uses primary/blue semantic color')
check('[title="Edit"] i { color: var(--bs-warning); }' in remote_files_css, 'Edit icon uses warning/amber semantic color')
check('[title="File Libraryへ保存"] i { color: var(--bs-success); }' in remote_files_css, 'File Library icon uses success/green semantic color')
check('[title="Rename / Move"] i { color: var(--bs-secondary); }' in remote_files_css, 'Rename/Move icon uses secondary/gray semantic color')
check('[title="削除"] i { color: var(--bs-danger); }' in remote_files_css, 'Delete icon uses danger/red semantic color')
check('APP_REMOTE_CREDENTIAL_KEY_B64' not in js and 'APP_REMOTE_CREDENTIAL_KEY_B64' not in page, 'E UI does not expose credential key material')
check('innerHTML' not in js and 'localStorage' not in js and 'sessionStorage' not in js and 'console.' not in js, 'editor source remains out of unsafe DOM/storage/console sinks')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
