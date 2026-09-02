from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

version = read('app/version.php')
bootstrap = read('app/remote_file/remote_bootstrap.php')
editor = read('app/remote_file/remote_editor.php')
api = read('public/remote_file_editor_api.php')
page = read('public/remote-editor.php')
editor_js = read('public/js/remote-editor.js')
files_js = read('public/js/remote-files.js')
files_css = read('public/css/remote-files.css')
checklist = read('docs/v1-30-g-production-checklist.md')
gitignore = read('.gitignore')

checks = []
def check(value: bool, label: str) -> None:
    checks.append(bool(value))
    print(('PASS' if value else 'FAIL') + ': ' + label)

check("const APP_VERSION = '1.30.0-dev.7';" in version, 'G checkpoint version is dev.7')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.30.0-dev.7';" in version, 'G visible version label is dev.7')
check("const APP_ASSET_REVISION = '1.30.0-dev.7';" in version, 'G asset revision is dev.7')
check('V1.30-G is the production/integration checkpoint' in checklist, 'G checklist identifies integration checkpoint')
check('not' in checklist.lower() and 'formal `v1.30.0`' in checklist, 'G checklist defers formal release')
check('No database migration is required' in checklist, 'G records no DB migration')
check('Do not create or overwrite `v1.30.0` during G.' in checklist, 'G forbids premature tag overwrite')

allow = ['txt','md','csv','json','xml','html','htm','css','js','php','ini','conf','yml','yaml']
for ext in allow:
    check("'" + ext + "'" in editor, f'editor allowlist retains {ext}')
for ext in ['zip','pdf','jpg','png','exe','db','sqlite']:
    check("'" + ext + "'" not in re.search(r"function remote_editor_allowed_extensions\(\): array\s*\{.*?\}", editor, re.S).group(0), f'editor allowlist rejects {ext}')

check('APP_REMOTE_EDITOR_MAX_BYTES' in bootstrap, 'dedicated editor size bound remains configured')
check('app_session_user_id()' in api, 'editor API requires authentication')
check('app_csrf_is_valid($csrf)' in api, 'save API requires CSRF')
check('app_send_no_store_headers()' in api, 'editor API remains no-store')
check('Cross-Origin-Resource-Policy: same-origin' in api, 'editor API stays same-origin')
check('text_base64' in editor_js and 'text: el.text.value' not in editor_js, 'WAF-safe Base64 save transport remains')
check('state.conflicted' in editor_js, 'explicit conflict state remains')
check('force_overwrite' not in editor_js.lower(), 'no force-overwrite client bypass exists')
check('$provider->move($stagePath, $pathInfo[\'path\'], true);' in editor, 'save replacement still uses common provider move')
check("$provider->delete($stagePath, false);" in editor, 'cleanup only targets staged path')
check(all(proto not in editor.lower() for proto in ["'ftp'", "'ftps'", "'sftp'", "'webdav'"]), 'editor backend stays protocol-neutral')
check('spellcheck="false"' in page and 'autocomplete="off"' in page and 'autocapitalize="off"' in page, 'browser input assistance hardening remains')
check('editorExtensionAllowed(extension)' in files_js, 'Remote Files Edit gate remains')
check('remote-files-entry-icon' in files_css and ':has(' in files_css, 'file-type presentation differentiation remains')
check('/var/*' in gitignore and '!/var/remote-tmp/' in gitignore, 'private temp placeholder ignore contract remains')
check('!/var/remote-tmp/.gitkeep' in gitignore, 'only temp placeholder is retained')

# G itself introduces no migration/provider implementation; package path verification is performed separately.
failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
