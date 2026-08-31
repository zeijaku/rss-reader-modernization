from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ht = (ROOT / 'public/.htaccess').read_text(encoding='utf-8')
drawer = (ROOT / 'public/js/drawer-categories.js').read_text(encoding='utf-8')
page = (ROOT / 'public/remote-files.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/remote-files.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/remote-files.css').read_text(encoding='utf-8')
upload = (ROOT / 'public/remote_file_upload_api.php').read_text(encoding='utf-8')
preview = (ROOT / 'public/remote_file_preview_api.php').read_text(encoding='utf-8')
content = (ROOT / 'public/remote_file_content.php').read_text(encoding='utf-8')
api = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

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

for endpoint in ['remote-files.php', 'remote_file_content.php', 'remote_file_preview_api.php', 'remote_file_upload_api.php']:
    check(endpoint.replace('.', r'\.') in ht, f'public endpoint allowlist explicitly includes {endpoint}')
check('RewriteRule ^remote-files/?$ remote-files.php [L,QSA]' in ht,
      'Remote Files has canonical extensionless route')
check("ensureRemoteFilesItem" in drawer and ".attr('href', './remote-files')" in drawer,
      'shared Drawer organizer injects Remote Files without editing every page')
check("'display': ['./?tab=0', './?tab=1', './?tab=2', './?tab=3', './stock', './file-library', './remote-files']" in drawer,
      'Remote Files is grouped with display/file-management navigation')
check('app_session_user_id()' in page and "header('Location: ./', true, 302)" in page,
      'Remote Files page requires authenticated session')
check('<meta name="csrf-token"' in page and "str_starts_with($action, 'remote.')" in api,
      'Remote JSON actions use existing authenticated CSRF-protected API boundary')
check('app_csrf_is_valid($csrf)' in upload,
      'multipart remote upload has explicit CSRF validation')
check("($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'" in content and "($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'" in preview,
      'content and preview endpoints are read-only GET surfaces')
check("Content-Security-Policy: default-src 'none'; sandbox" in content,
      'binary inline preview has restrictive sandbox CSP')
check("frame-ancestors 'none'" in preview,
      'JSON preview response cannot be framed')
check('innerHTML' not in js and '.textContent' in js,
      'Remote UI renders remote names and preview cells as text, not HTML')
check('@media (pointer: coarse)' in css and 'min-height: 44px' in css,
      'touch devices receive 44px minimum action targets')
check('@media (max-width: 575.98px)' in css,
      'Remote Files includes narrow Smartphone layout')
check('FTPは通信とCredentialが暗号化されません' in page,
      'plain FTP risk is visible in UI')
check('Private Networkへの接続をこのConnectionで許可' in page and 'Server側Allowlist' in page,
      'private network opt-in explains server-side allowlist boundary')
check("APP_VERSION = '1.29.0-dev.3'" in version and "APP_ASSET_REVISION = '1.29.0-dev.3'" in version,
      'G-I checkpoint version and asset revision advance together')

print(f'RESULT: PASS {passes} / FAIL {fails}')
raise SystemExit(0 if fails == 0 else 1)
