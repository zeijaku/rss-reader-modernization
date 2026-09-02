from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = []


def read(path):
    return (ROOT / path).read_text(encoding='utf-8')


def check(ok, label):
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + label)


version = read('app/version.php')
editor = read('app/remote_file/remote_editor.php')
api = read('public/remote_file_editor_api.php')
page = read('public/remote-editor.php')
js = read('public/js/remote-editor.js')
files_js = read('public/js/remote-files.js')
files_css = read('public/css/remote-files.css')
editor_css = read('public/css/remote-editor.css')

version_match = re.search(r"const APP_VERSION = '([^']+)';", version)
label_match = re.search(r"const APP_VERSION_LABEL = '([^']+)';", version)
asset_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
check(version_match is not None and version_match.group(1) == '1.30.0', 'formal V1.30 version marker')
check(label_match is not None and label_match.group(1) == 'RSS Reader Modernization 1.30.0', 'formal V1.30 visible label')
check(asset_match is not None and version_match is not None and asset_match.group(1) == version_match.group(1), 'active asset revision follows current version')

for ext in ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'ini', 'conf', 'yml', 'yaml']:
    check(re.search(r"['\"]" + re.escape(ext) + r"['\"]", editor) is not None, f'editable allowlist contains {ext}')
for ext in ['zip', 'pdf', 'jpg', 'png', 'exe', 'sqlite', 'db']:
    check(re.search(r"['\"]" + re.escape(ext) + r"['\"]", editor) is None, f'editable allowlist excludes {ext}')
check('APP_REMOTE_EDITOR_MAX_BYTES' in editor, 'bounded editor size')
check("hash('sha256', $bytes)" in editor, 'raw-byte SHA-256 metadata')
check("$lineEnding === 'mixed' || $lineEnding === 'cr'" in editor, 'mixed/CR-only EOL fail closed')
check('str_replace("\\n", "\\r\\n", $text)' in editor, 'CRLF reconstruction remains')
check("$current['utf8_bom']" in editor, 'UTF-8 BOM preservation remains')
check('$provider = remote_service_provider($ownerId, $connectionId);' in editor, 'owner-scoped common provider construction')
check('$provider->upload($stream, $size, $stagePath, false);' in editor, 'stage upload refuses overwrite')
check("$provider->move($stagePath, $pathInfo['path'], true);" in editor, 'common provider move performs final replacement')
check(editor.count("throw new AppRemoteEditorException('editor_conflict', 409)") >= 2, 'pre/post-stage conflict checks remain')
check('editor_save_verification_failed' in editor, 'post-save verification remains')
check('$provider->delete($stagePath, false);' in editor, 'staged cleanup remains')
check(all(proto not in editor.lower() for proto in ["'ftp'", "'ftps'", "'sftp'", "'webdav'"]), 'editor backend remains protocol-neutral')
check('error_log(' not in editor, 'editor backend does not log source/path data')

check("in_array($method, ['GET', 'POST'], true)" in api, 'editor API remains GET/POST only')
check('app_session_user_id()' in api, 'editor API requires authentication')
check('app_csrf_is_valid($csrf)' in api, 'editor save requires CSRF')
check('base64_decode($encoded, true)' in api and 'text_base64' in api, 'strict WAF-safe Base64 transport remains')
check('app_send_no_store_headers()' in api, 'editor API remains no-store')
check('Cross-Origin-Resource-Policy: same-origin' in api, 'editor API remains same-origin')
check('JSON_HEX_TAG' in api, 'defensive JSON escaping remains')

check('spellcheck="false"' in page and 'autocomplete="off"' in page and 'autocapitalize="off"' in page, 'browser assistance hardening remains')
check('conflicted: false' in js and 'state.conflicted || state.loading || state.saving' in js, 'client conflict lock remains')
check("text_base64: textBase64" in js and 'text: el.text.value' not in js, 'client sends Base64 rather than raw source')
check('force_overwrite' not in js.lower(), 'no force-overwrite client bypass')
check('innerHTML' not in js and 'localStorage' not in js and 'sessionStorage' not in js and 'console.' not in js, 'source avoids unsafe DOM/storage/console sinks')
check('function backUrl()' in js and "params.set('path', pathParent(state.path))" in js, 'editor return flow retains parent directory')
check("new URLSearchParams(window.location.search || '')" in files_js, 'Remote Files restores return parameters')
check('@media (pointer: coarse)' in editor_css and 'min-height: 44px' in editor_css, 'coarse-pointer target remains')
check('@media (max-width: 575.98px)' in editor_css and 'font-size: 16px' in editor_css, 'mobile editor focus-zoom mitigation remains')
for title in ['Preview', 'Download', 'Edit', 'File Libraryへ保存', 'Rename / Move', '削除']:
    check(f'[title="{title}"] i' in files_css, f'action differentiation remains: {title}')
check('title$=".php" i' in files_css and 'title$=".pdf" i' in files_css and 'title$=".zip" i' in files_css, 'file-type differentiation remains')

migrations = ROOT / 'database' / 'migrations'
v130 = [p.name for p in migrations.iterdir() if 'v1_30' in p.name.lower() or re.match(r'022_', p.name)] if migrations.is_dir() else []
check(v130 == [], 'V1.30 adds no database migration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
