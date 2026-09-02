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
provider = read('app/remote_file/remote_provider.php')
service = read('app/remote_file/remote_service.php')
env_example = read('config/.env.example')
local_example = read('config/local.php.example')
design = read('docs/v1.30-remote-text-editor-design.md')

version_match = re.search(r"const APP_VERSION = '(1\.30\.0(?:-dev\.[1-9][0-9]*)?)';", version)
current_version = version_match.group(1) if version_match else ''
check(bool(current_version), 'V1.30 uses the supported development/final version line')
check(f"const APP_VERSION_LABEL = 'RSS Reader Modernization {current_version}';" in version,
      'visible version label matches the current V1.30 version')
check(f"const APP_ASSET_REVISION = '{current_version}';" in version,
      'active public asset revision matches the current V1.30 version')

check("define('APP_REMOTE_EDITOR_MAX_BYTES'" in bootstrap,
      'remote bootstrap defines a dedicated editor byte ceiling')
check("app_env('APP_REMOTE_EDITOR_MAX_BYTES', '524288')" in bootstrap,
      'editor byte ceiling defaults to 512 KiB')
check('max(65536, min(1048576,' in bootstrap,
      'editor byte ceiling is clamped to a deliberately small range')
check('APP_REMOTE_EDITOR_MAX_BYTES=524288' in env_example,
      '.env example documents the editor byte ceiling')
check("'APP_REMOTE_EDITOR_MAX_BYTES' => '524288'" in local_example,
      'local.php example documents the editor byte ceiling')
check('APP_REMOTE_USER_AGENT=iGuguru-RemoteFiles/1.30' in env_example,
      '.env example uses the V1.30 Remote Files user agent')
check("app_env('APP_REMOTE_USER_AGENT', 'iGuguru-RemoteFiles/1.30')" in bootstrap,
      'remote bootstrap uses the V1.30 Remote Files user agent')

for method in ('list', 'download', 'upload', 'mkdir', 'move', 'delete'):
    check(re.search(r'public function\s+' + re.escape(method) + r'\s*\(', provider) is not None,
          f'RemoteFileProvider retains shared {method}() boundary')

check('editText' not in provider and 'saveText' not in provider,
      'RemoteFileProvider does not gain protocol-specific editor methods')
check('remote_service_owned_connection' in service,
      'owner-scoped connection lookup remains in the shared Remote Service')
check('remote_service_assert_safe_path' in service,
      'shared Remote Service retains path/symlink safety checks')
check('remote_service_download_stream' in service and 'remote_service_upload_stream' in service,
      'shared bounded stream service remains available for editor reuse')

required_design_phrases = [
    'V1.30 Remote Text Editor requires no database migration.',
    'optimistic conflict detection',
    'SHA-256',
    'UTF-8 only',
    'Mixed line endings',
    'must not promise an atomic replace across all protocols',
    'dedicated editor page',
    'tests/run-current.sh',
    'tests/run-current-features.sh',
]
for phrase in required_design_phrases:
    check(phrase in design, f'design contract records: {phrase}')

migration_dir = ROOT / 'database' / 'migrations'
v130_migrations = []
if migration_dir.is_dir():
    v130_migrations = [p.name for p in migration_dir.iterdir() if 'v1_30' in p.name.lower() or re.match(r'022_', p.name)]
check(v130_migrations == [], 'V1.30 introduces no database migration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
