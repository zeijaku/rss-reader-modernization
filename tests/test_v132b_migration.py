from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
SQL = (ROOT / 'database' / 'migrations' / '022_v1_32_auth_2fa.sql').read_text(encoding='utf-8')
CONF = (ROOT / 'app' / 'common' / 'common_conf.php').read_text(encoding='utf-8')
BOOTSTRAP = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
GITIGNORE = (ROOT / '.gitignore').read_text(encoding='utf-8')
LOCAL_EXAMPLE = (ROOT / 'config' / 'local.php.example').read_text(encoding='utf-8')
ENV_EXAMPLE = (ROOT / 'config' / '.env.example').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(condition)
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("auth_totp" in SQL and "auth_recovery_code" in SQL, 'Migration creates TOTP and reserved recovery-code tables')
check('auth_totp_enabled_at` DATETIME NULL' in SQL, 'TOTP starts with nullable enabled timestamp')
check('auth_totp_last_used_step' in SQL, 'Migration includes accepted-step replay prevention')
check('AEAD encrypted TOTP secret envelope' in SQL, 'Migration documents encrypted-at-rest TOTP secret')
check('One-way recovery code hash only' in SQL, 'Recovery table is hash-only by contract')
check(re.search(r'\b(DROP|TRUNCATE|DELETE\s+FROM|ALTER\s+TABLE)\b', SQL, re.I) is None, 'V1.32-B migration is additive and non-destructive')
check("'auth_totp'" in CONF and "'auth_recovery_code'" in CONF, 'DB table allowlist includes the V1.32 auth tables')
check("APP_TOTP_SECRET_KEY_ID" in CONF and "APP_TOTP_SECRET_KEY_B64" in CONF and "APP_TOTP_ISSUER" in CONF, 'Runtime configuration exposes dedicated TOTP key and issuer names')
check("require_once __DIR__ . '/auth_totp.php';" in BOOTSTRAP, 'Bootstrap loads the TOTP foundation')
check('!/database/migrations/022_v1_32_auth_2fa.sql' in GITIGNORE, 'Migration 022 is explicitly allowlisted despite the global SQL ignore rule')
check('APP_TOTP_SECRET_KEY_B64' in LOCAL_EXAMPLE and 'replace-with-base64-encoded-32-byte-key' in LOCAL_EXAMPLE, 'local.php example contains only a TOTP key placeholder')
check('APP_TOTP_SECRET_KEY_B64=replace-with-base64-encoded-32-byte-key' in ENV_EXAMPLE, '.env example contains only a TOTP key placeholder')
check('APP_REMOTE_CREDENTIAL_KEY_B64' in LOCAL_EXAMPLE and 'APP_TOTP_SECRET_KEY_B64' in LOCAL_EXAMPLE, 'TOTP and Remote credential keys remain separately named')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
