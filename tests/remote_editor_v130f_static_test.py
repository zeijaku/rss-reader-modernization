from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

version = read('app/version.php')
files_js = read('public/js/remote-files.js')
files_css = read('public/css/remote-files.css')
editor_page = read('public/remote-editor.php')
editor_css = read('public/css/remote-editor.css')
editor_js = read('public/js/remote-editor.js')
editor_backend = read('app/remote_file/remote_editor.php')
editor_api = read('public/remote_file_editor_api.php')

checks: list[bool] = []
def check(condition: bool, label: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + label)

check("const APP_VERSION = '1.30.0-dev." in version, 'current V1.30 development checkpoint remains visible')
check("const APP_ASSET_REVISION = '1.30.0-dev." in version, 'current V1.30 development asset revision remains visible')

# File-type visuals are presentation-only. Existing row behavior is untouched.
check(':has(' in files_css, 'file-type styling uses modern presentation-only :has selector')
for suffix in ['.php','.html','.htm','.css','.js','.json','.xml','.ini','.conf','.yml','.yaml','.txt','.md','.csv','.pdf','.jpg','.jpeg','.png','.gif','.webp','.svg','.zip','.tar','.gz','.tgz','.bz2','.xz','.7z','.rar','.sql']:
    check(f'title$="{suffix}" i' in files_css, f'file-type visual rule includes {suffix}')
for glyph, label in [('\\f1c9','code'),('\\f15c','text'),('\\f6dd','csv'),('\\f1c1','pdf'),('\\f1c5','image'),('\\f1c6','archive'),('\\f1c0','database')]:
    check(f'content: "{glyph}"' in files_css, f'file-type CSS provides {label} glyph')
check('.remote-files-entry-icon.fa-folder' in files_css, 'directory retains folder shape with differentiated color')
check('.remote-files-entry-icon.fa-link' in files_css, 'symlink retains link shape with differentiated color')
check('textContent = name' in files_js and 'label.title = name' in files_js, 'filename stays visible as a non-color cue')

# Existing action differentiation/accessibility remains.
for title in ['Preview', 'Download', 'Edit', 'File Libraryへ保存', 'Rename / Move', '削除']:
    check(f'[title="{title}"] i' in files_css, f'action color remains for {title}')
check("button.setAttribute('aria-label', label);" in files_js, 'button action aria-label remains')
check("editLink.setAttribute('aria-label', name + 'をEdit');" in files_js, 'Edit link aria-label remains')
check("download.setAttribute('aria-label', name + 'をDownload');" in files_js, 'Download link aria-label remains')

# Existing browser assistance hardening + F mobile adjustment.
check('spellcheck="false"' in editor_page, 'editor disables browser spellcheck')
check('autocomplete="off"' in editor_page, 'editor disables autocomplete')
check('autocapitalize="off"' in editor_page, 'editor disables autocapitalize')
check('wrap="off"' in editor_page, 'editor keeps code-like no-wrap textarea')
check('@media (pointer: coarse)' in editor_css and 'min-height: 44px' in editor_css, 'coarse pointer controls retain 44px touch target')
check('@media (max-width: 575.98px)' in editor_css and 'font-size: 16px' in editor_css, 'small-screen editor uses 16px text to avoid mobile focus zoom')

# Security boundary remains unchanged around F presentation/mobile changes.
check("text_base64: textBase64" in editor_js and 'text: el.text.value' not in editor_js, 'WAF-safe Base64 save transport remains')
check("csrf_token: csrfToken()" in editor_js, 'CSRF token remains in save request')
check("credentials: 'same-origin'" in editor_js, 'same-origin credentials remain')
check('state.conflicted' in editor_js and 'state.conflicted || state.loading || state.saving' in editor_js, 'conflict lock still blocks stale save')
check('force_overwrite' not in editor_js.lower(), 'no force-overwrite client bypass added')
check('innerHTML' not in editor_js, 'editor source is not injected through innerHTML')
check('localStorage' not in editor_js and 'sessionStorage' not in editor_js, 'source is not persisted in browser storage')
check('console.' not in editor_js, 'source/editor state is not logged to browser console')
check(all(proto not in editor_backend.lower() for proto in ["'ftp'", "'ftps'", "'sftp'", "'webdav'"]), 'editor backend remains protocol-neutral')
check('$provider->move($stagePath, $pathInfo[\'path\'], true);' in editor_backend, 'editor still uses common provider move')
check("$provider->delete($stagePath, false);" in editor_backend, 'cleanup targets staged path, not delete-original fallback')
check('error_log(' not in editor_backend, 'editor backend does not log source/path data')
check('app_csrf_is_valid($csrf)' in editor_api, 'editor save API still enforces CSRF')
check('app_session_user_id()' in editor_api, 'editor API still requires authenticated user')
check('app_send_no_store_headers()' in editor_api, 'editor API still sends no-store headers')
check('Cross-Origin-Resource-Policy: same-origin' in editor_api, 'editor API remains same-origin resource')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
