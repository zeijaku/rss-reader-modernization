#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(ok: bool, label: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + label)

def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

version = text('app/version.php')
schema = text('database/schema.sql')
install = text('docs/installation.md')
local_example = text('config/local.php.example')
env_example = text('config/.env.example')

m = re.search(r"const APP_VERSION = '([^']+)';", version)
check(m is not None, 'current application version is readable')
if m:
    vm = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)(?:-(?:rc\d+|dev\.\d+))?', m.group(1))
    check(vm is not None and tuple(map(int, vm.groups()[:3])) >= (1, 32, 0), 'V1.32 release contract runs on 1.32.0 or later')

for table in ['auth_totp', 'auth_recovery_code', 'auth_session', 'auth_audit_log']:
    check(f"@t_{table}" in schema, f'fresh-install schema declares {table}')
    check(f"CREATE TABLE ', @t_{table}" in schema, f'fresh-install schema creates {table}')

for migration in [
    '022_v1_32_auth_2fa.sql',
    '023_v1_32_auth_session.sql',
    '024_v1_32_auth_audit_log.sql',
]:
    path = ROOT / 'database' / 'migrations' / migration
    check(path.is_file(), f'migration exists: {migration}')
    if path.is_file():
        sql = path.read_text(encoding='utf-8')
        upper = sql.upper()
        check('DROP TABLE' not in upper and 'TRUNCATE ' not in upper and 'DELETE FROM' not in upper,
              f'migration remains additive/non-destructive: {migration}')
        check('INFORMATION_SCHEMA.TABLES' in upper, f'migration checks existing table before create: {migration}')

order = [install.find(x) for x in [
    '022_v1_32_auth_2fa.sql',
    '023_v1_32_auth_session.sql',
    '024_v1_32_auth_audit_log.sql',
]]
check(all(i >= 0 for i in order) and order == sorted(order), 'installation guide documents V1.32 migrations in numeric order')
check('APP_TOTP_SECRET_KEY_B64' in install and '変更しない' in install, 'installation guide warns not to rotate an enrolled TOTP key')
for cfg, name in [(local_example, 'local.php.example'), (env_example, '.env.example')]:
    check('APP_TOTP_SECRET_KEY_B64' in cfg, f'{name} documents dedicated TOTP encryption key')
    check('AUTH_STEP_UP_TIMEOUT' in cfg, f'{name} documents Step-up timeout')

check(not (ROOT / 'config' / 'local.php').exists(), 'private config/local.php is absent from source tree')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
