from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/remote_file/remote_bootstrap.php').read_text(encoding='utf-8')
transport = (ROOT / 'app/remote_file/remote_curl_transport.php').read_text(encoding='utf-8')
placeholder = ROOT / 'var/remote-tmp/.gitkeep'
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

checks = []

def check(condition: bool, label: str) -> None:
    checks.append(condition)
    print(('PASS: ' if condition else 'FAIL: ') + label)

check(placeholder.is_file(), 'private remote temp workspace placeholder is versioned')
check('!/var/remote-tmp/' in gitignore, 'remote temp directory is unignored')
check('/var/remote-tmp/*' in gitignore, 'runtime remote temp contents remain ignored')
check('!/var/remote-tmp/.gitkeep' in gitignore, 'only remote temp placeholder is retained')
check("dirname(__DIR__, 2) . '/var/remote-tmp'" in bootstrap, 'default remote temp path stays outside public')
check("@mkdir($configured, 0700, true)" in transport, 'runtime creates workspace with private mode')
check("@chmod($configured, 0700)" in transport, 'runtime reapplies private mode')
check('!is_writable($real)' in transport, 'runtime requires PHP-writable workspace')
check("remote_local_path_is_within($real, $public)" in transport, 'runtime rejects a workspace under public')
check("const APP_VERSION = '1.30.0-dev.4';" in version, 'deployment placeholder repair does not advance feature behavior version')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
