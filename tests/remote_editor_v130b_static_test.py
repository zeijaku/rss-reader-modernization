from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


version = read('app/version.php')
bootstrap = read('app/remote_file/remote_bootstrap.php')
editor = read('app/remote_file/remote_editor.php')
endpoint = read('public/remote_file_editor_api.php')
htaccess = read('public/.htaccess')

version_match = re.search(r"const APP_VERSION = '1\.30\.0(?:-dev\.([1-9][0-9]*))?';", version)
check(version_match is not None, 'V1.30-B stays on the V1.30 version line')
if version_match is not None and version_match.group(1) is not None:
    check(int(version_match.group(1)) >= 2, 'V1.30-B development checkpoint is dev.2 or later')
else:
    check(True, 'formal 1.30.0 also satisfies the V1.30-B version contract')

check("require_once __DIR__ . '/remote_service.php';" in bootstrap, 'bootstrap loads shared Remote Service')
check("require_once __DIR__ . '/remote_editor.php';" in bootstrap, 'bootstrap loads Remote Editor helper')
check(bootstrap.index("'/remote_service.php'") < bootstrap.index("'/remote_editor.php'") < bootstrap.index("'/remote_api.php'"),
      'Remote Editor helper is loaded after Remote Service and before Remote API')

allowed = {'txt','md','csv','json','xml','html','htm','css','js','php','ini','conf','yml','yaml'}
for extension in allowed:
    check(re.search(r"['\"]" + re.escape(extension) + r"['\"]", editor) is not None,
          f'helper includes .{extension} allowlist entry')
for forbidden in ('zip','pdf','jpg','jpeg','png','gif','webp','exe','sqlite','db'):
    check(re.search(r"['\"]" + re.escape(forbidden) + r"['\"]", editor) is None,
          f'helper does not add .{forbidden} to editable allowlist')

check('remote_service_download_temp(' in editor, 'read helper reuses bounded shared Remote Service download')
check('APP_REMOTE_EDITOR_MAX_BYTES' in editor, 'read helper uses dedicated editor byte ceiling')
check("hash('sha256', $bytes)" in editor, 'helper hashes exact remote bytes with SHA-256')
check('str_starts_with($bytes, "\\xEF\\xBB\\xBF")' in editor, 'helper detects UTF-8 BOM')
check("preg_match('//u', $text)" in editor, 'helper validates UTF-8')
check('strpos($text, "\\0")' in editor, 'helper explicitly rejects NUL')
check('editor_binary_unsupported' in editor, 'helper has binary/control rejection')
check("$lineEnding === 'mixed' || $lineEnding === 'cr'" in editor, 'mixed and CR-only EOL fail closed')
check("'lf'" in editor and "'crlf'" in editor and "'none'" in editor, 'LF/CRLF/no-EOL are distinguished')
check('finally {' in editor and '@unlink($tempPath);' in editor, 'private temp file is cleaned in finally')
check('->upload(' not in editor and '->move(' not in editor and 'remote_service_upload_stream(' not in editor,
      'V1.30-B helper is read-only and adds no save path')
check('ftp_' not in editor.lower() and 'sftp_' not in editor.lower() and 'webdav_' not in editor.lower(),
      'helper has no protocol-specific editor branches')

check("($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'" in endpoint, 'editor endpoint is GET-only in V1.30-B')
check('app_session_user_id()' in endpoint, 'editor endpoint requires authenticated session')
check('app_session_release();' in endpoint, 'session lock is released before remote network read')
check('remote_path_normalize_relative' in endpoint, 'endpoint normalizes requested remote path')
check('remote_editor_read(' in endpoint, 'endpoint delegates to Remote Editor read helper')
check('app_send_no_store_headers();' in endpoint and 'app_send_private_no_store_headers();' in endpoint,
      'editor source response is no-store/private')
check("Cross-Origin-Resource-Policy: same-origin" in endpoint, 'editor API keeps same-origin resource policy')
check("Content-Security-Policy: default-src 'none'" in endpoint, 'editor API uses restrictive JSON CSP')
check('JSON_HEX_TAG' in endpoint and 'JSON_HEX_AMP' in endpoint and 'JSON_HEX_APOS' in endpoint and 'JSON_HEX_QUOT' in endpoint,
      'JSON source response uses defensive HTML-sensitive escaping')
check("remote_api_failure('editor.read'" in endpoint, 'generic remote failures use existing credential-safe error mapping')
check('password' not in endpoint.lower() and 'credential_key' not in endpoint.lower(),
      'editor endpoint does not handle or expose remote credential material')
check('remote_file_editor_api\\.php$' in htaccess, 'new editor API is explicitly allowlisted by public endpoint matrix')

migration_dir = ROOT / 'database' / 'migrations'
v130_migrations = []
if migration_dir.is_dir():
    v130_migrations = [p.name for p in migration_dir.iterdir() if 'v1_30' in p.name.lower() or re.match(r'022_', p.name)]
check(v130_migrations == [], 'V1.30-B introduces no database migration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
