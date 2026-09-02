from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

version = read('app/version.php')
page = read('public/remote-editor.php')
editor_js = read('public/js/remote-editor.js')
files_js = read('public/js/remote-files.js')
css = read('public/css/remote-editor.css')
ht = read('public/.htaccess')

checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("const APP_VERSION = '1.30.0-dev.3';" in version, 'V1.30-C uses dev.3 version')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.30.0-dev.3';" in version, 'visible label matches dev.3')
check("const APP_ASSET_REVISION = '1.30.0-dev.3';" in version, 'asset revision matches dev.3')

check('app_session_user_id()' in page, 'editor page requires authenticated user')
check("header('Location: ./', true, 302)" in page, 'unauthenticated editor page redirects to login/root')
check('remote_path_normalize_relative' in page, 'editor page normalizes the requested remote path')
check('remote_editor_path_info' in page, 'editor page applies server-side editable extension gate')
check('remote_connection_find_owned($currentUserId, $connectionId, false)' in page, 'editor page owner-scopes connection lookup without loading encrypted secret')
check('remote_connection_safe_row' in page, 'editor page reduces connection metadata to the safe row')
check('remote_connection_secret' not in page, 'editor page never references encrypted credential payload')
check('APP_REMOTE_CREDENTIAL_KEY_B64' not in page, 'editor page never references credential key material')
check("'connection_name'" in page and "'protocol'" in page, 'initial state exposes only display connection metadata')
check("'host'" not in page.split('$initialState = [', 1)[1].split('];', 1)[0], 'initial state does not expose remote host')
check("'username'" not in page.split('$initialState = [', 1)[1].split('];', 1)[0], 'initial state does not expose remote username')
for flag in ['JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT']:
    check(flag in page, f'initial editor state uses {flag}')
check('app_send_private_no_store_headers()' in page, 'editor page sends private no-store headers')
check('<meta name="robots" content="noindex,nofollow">' in page, 'editor page is noindex/nofollow')
check('id="remoteEditorSave" disabled' in page, 'save button is disabled in V1.30-C')
check('V1.30-C checkpoint' in page, 'page visibly explains C checkpoint save limitation')
check("app_asset_url('css/remote-editor.css')" in page, 'editor page loads versioned editor CSS')
check("app_asset_url('js/remote-editor.js')" in page, 'editor page loads versioned editor JS')
check('id="remoteEditorInitialState"' in page, 'editor page emits a dedicated JSON state node')
check('drawer-item-current' in page and './remote-files' in page, 'Remote Files remains the parent/current drawer destination')

check("window.fetch(readUrl(), {" in editor_js, 'editor UI reads through the dedicated B API')
check("method: 'GET'" in editor_js, 'editor UI read is explicitly GET')
check("credentials: 'same-origin'" in editor_js, 'editor read keeps same-origin credentials')
check("headers: {'Accept': 'application/json'}" in editor_js, 'editor read requests JSON explicitly')
check("return './remote_file_editor_api.php?' + params.toString();" in editor_js, 'editor UI calls only the dedicated read API')
check("el.text.value = text;" in editor_js, 'remote source is assigned through textarea.value')
check('innerHTML' not in editor_js, 'editor UI never injects source through innerHTML')
check('.textContent' in editor_js, 'metadata/notices render as textContent')
check("el.save.disabled = true;" in editor_js, 'editor JS keeps Save disabled')
check("method: 'POST'" not in editor_js, 'V1.30-C editor JS has no POST mutation path')
check('remote.file.' not in editor_js, 'V1.30-C editor JS does not call generic remote mutation actions')
check("el.text.value !== state.initialText" in editor_js, 'dirty state compares current textarea value to loaded text')
check("'beforeunload'" in editor_js and "event.returnValue = ''" in editor_js, 'dirty page installs browser navigation protection')
check("window.confirm('未保存の変更があります。Remoteから再読込すると入力内容は破棄されます。')" in editor_js, 'reload requires confirmation only when dirty')
check("window.confirm('未保存の変更があります。Remote Filesへ戻ると入力内容は破棄されます。')" in editor_js, 'back navigation warns when dirty')
check("event.ctrlKey || event.metaKey" in editor_js and "event.preventDefault();" in editor_js, 'Ctrl/Cmd+S is intercepted while save is unavailable')
check("state.sha256 = typeof data.sha256 === 'string'" in editor_js, 'loaded optimistic conflict token is retained in browser state for later phase')
check("el.metaHash.textContent" in editor_js and "el.metaHash.title = hash" in editor_js, 'SHA metadata is rendered as text only')
check("lineEndingLabel" in editor_js and "crlf: 'CRLF'" in editor_js, 'editor displays read-back EOL metadata')
check("data.utf8_bom === true ? 'Yes' : 'No'" in editor_js, 'editor displays UTF-8 BOM metadata')

allowed = ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'ini', 'conf', 'yml', 'yaml']
match = re.search(r"var editableExtensions = \[([^\]]+)\];", files_js)
ui_allowed = re.findall(r"'([a-z0-9]+)'", match.group(1)) if match else []
check(ui_allowed == allowed, 'Remote Files Edit hint uses the exact initial editor extension allowlist')
check('Server-side remote_editor_allowed_extensions() remains authoritative' in files_js, 'Remote Files documents that UI extension check is non-authoritative')
check("return './remote-editor?' + params.toString();" in files_js, 'Remote Files builds canonical editor URL with URLSearchParams')
check("params.set('remote_connection_id', String(state.currentConnectionId));" in files_js, 'editor link carries selected owner-scoped connection id')
check("params.set('path', path);" in files_js, 'editor link carries connection-relative path')
check("if (editorExtensionAllowed(extension)) {" in files_js, 'Edit link is limited to allowed text extensions in the file branch')
check("editLink.title = 'Edit';" in files_js, 'Remote Files presents explicit Edit action')
check("editLink.setAttribute('aria-label', name + 'をEdit');" in files_js, 'Edit action has an accessible file-specific label')
check("editLink.className = 'btn btn-sm btn-outline-secondary';" in files_js, 'Edit action follows existing compact Remote Files action style')
check('innerHTML' not in files_js, 'Remote Files continues rendering remote names without innerHTML')

check('remote-editor\\.php$' in ht, 'public PHP endpoint matrix explicitly allows remote-editor.php')
check('RewriteRule ^remote-editor/?$ remote-editor.php [L,QSA]' in ht, 'Remote Editor has canonical extensionless route')
check('public/remote-editor\\.php' in ht, 'direct public/remote-editor.php requests canonicalize')
check('remote_file_editor_api\\.php$' in ht, 'B read API remains explicitly allowlisted')

check('.remote-editor-text' in css and 'font-family: var(--bs-font-monospace' in css, 'editor textarea uses monospace presentation')
check('100dvh' in css, 'editor height responds to dynamic mobile viewport')
check('@media (pointer: coarse)' in css and 'min-height: 44px' in css, 'touch controls preserve 44px target size')
check('@media (max-width: 767.98px)' in css, 'tablet/smartphone layout is present')
check('@media (max-width: 575.98px)' in css, 'narrow smartphone layout is present')
check('grid-template-columns: 1fr 1fr' in css, 'narrow toolbar remains usable as two touch columns')

check('remote_file_upload_api.php' not in editor_js, 'editor UI cannot accidentally use existing general upload endpoint')
check('remote_file_content.php' not in editor_js, 'editor UI does not bypass bounded editor read validation')
check('localStorage' not in editor_js and 'sessionStorage' not in editor_js, 'source text is not persisted in browser storage')
check('console.' not in editor_js, 'source/editor state is not written to browser console')
check(all(token not in editor_js.lower() for token in ['password', 'private_key', 'passphrase', 'app_remote_credential_key_b64']), 'editor JS has no credential handling')
check('monaco' not in editor_js.lower() and 'codemirror' not in editor_js.lower(), 'V1.30-C remains plain textarea, not IDE framework')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
