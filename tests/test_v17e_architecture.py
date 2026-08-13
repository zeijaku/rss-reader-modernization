#!/usr/bin/env python3
from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
remember = (ROOT / 'app/remember_token.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/007_v1_7_remember_token.sql').read_text(encoding='utf-8')
index = dashboard_source(ROOT)
session = (ROOT / 'app/session.php').read_text(encoding='utf-8')

check("APP_VERSION = '1.7.0-dev.4'" in version or "APP_VERSION = '1.7.0-dev.5'" in version or "APP_VERSION = '1.7.0-dev.6'" in version or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version) or is_later_application_release(version, (1, 7, 0)), 'Application Version is V1.7-E or a compatible V1.7-F successor')
check("V1.7-E / R1" in version or "V1.7-F / R1" in version or "V1.7-G / R1" in version or "V1.7-H / R1" in version or "V1.7-H / R2" in version or "V1.7-H / R3" in version or "V1.7-H / R4" in version or "RSS Reader Modernization 1.7.0" in version or is_later_visible_label(version, (1, 7, 0)), 'Application Label is V1.7-E or later')
check("require_once __DIR__ . '/remember_token.php';" in bootstrap, 'Bootstrap loads Remember Token domain backend')
check("'remember_token'" in conf and 'Unknown database table name.' in conf, 'Remember Token table uses the existing logical table allowlist')

for token in [
    'REMEMBER_TOKEN_SELECTOR_BYTES = 12',
    'REMEMBER_TOKEN_VALIDATOR_BYTES = 32',
    'REMEMBER_TOKEN_TTL_SECONDS = 2592000',
    'function remember_token_issue(',
    'function remember_token_validate_and_rotate(',
    'function remember_token_revoke_cookie(',
    'function remember_token_revoke_user(',
    'function remember_token_cleanup_expired(',
    "hash('sha256', $validator)",
    'hash_equals($storedHash, $candidateHash)',
    'random_bytes(REMEMBER_TOKEN_VALIDATOR_BYTES)',
]:
    check(token in remember, f'Remember Token backend contains: {token}')

check('remember_token_expires_at = :expires_at' not in remember, 'Validator rotation does not extend fixed expiry')
check('error_log' not in remember and 'var_dump' not in remember, 'Token material is never written to application logs or debug output')
check('FOR UPDATE' in remember and 'beginTransaction' in remember, 'Validation and rotation use transactional row locking on MySQL')
check('remember_token_validator_hash = :previous_hash' in remember, 'Rotation uses optimistic previous-hash protection')
check("reason' => 'invalid_token'" in remember and "reason' => 'expired'" in remember and "reason' => 'inactive_user'" in remember, 'Validation fails closed for invalid, expired and inactive-user tokens')

for sql in (schema, migration):
    for fragment in [
        '`remember_token_selector` CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
        '`remember_token_validator_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
        'UNIQUE KEY `uq_remember_token_selector`',
        'KEY `idx_remember_token_user_expiry`',
        'KEY `idx_remember_token_expiry`',
    ]:
        check(fragment in sql, f'Database definition contains: {fragment}')
    check('remember_token_validator`' not in sql and 'cookie_value' not in sql.lower(), 'Database definition has no raw validator or cookie column')

check((ROOT / 'database/audit/v1_7_e_preflight.sql').is_file(), 'Read-only V1.7-E preflight exists')
check((ROOT / 'database/audit/v1_7_e_postflight.sql').is_file(), 'Read-only V1.7-E postflight exists')

# V1.7-E stops before integration; later V1.7 checkpoints may enable it.
if "APP_VERSION = '1.7.0-dev.4'" in version:
    check('remember_me' not in index and 'remember_token' not in index, 'Login UI does not expose Remember Me before V1.7-F')
    check('remember_token' not in session, 'Session auto-login integration remains deferred to V1.7-F')
else:
    check('remember_me' in index, 'V1.7-F successor connects the Remember Me login input')
    check('persistent_login_restore_session' in session, 'V1.7-F successor connects Session auto-login')
check((not (ROOT / 'database/migrations/008_v1_7_widget_height.sql').exists()) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version) or is_later_application_release(version, (1, 7, 0)), 'Widget height is deferred through G and implemented in H')

for rel in [
    'APPLY_NOTE_V1_7_E.md', 'CHECKLIST_FOR_USER_V1_7_E.md', 'UPDATED_FILES_V1_7_E.md',
    'docs/v1-7-e-implementation.md', 'docs/v1-7-e-files.md', 'docs/test-report-v1-7-e.md',
    'docs/v1-7-e-migration.md'
]:
    check((ROOT / rel).is_file(), f'{rel} exists')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
