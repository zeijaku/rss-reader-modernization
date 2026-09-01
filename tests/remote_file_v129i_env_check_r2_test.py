from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
tool = (ROOT / 'tools/remote_file_env_check.php').read_text(encoding='utf-8')
doc = (ROOT / 'docs/v1.29-remote-file-manager-checkpoint.md').read_text(encoding='utf-8')
example = (ROOT / 'config/local.php.example').read_text(encoding='utf-8')

checks = [
    ('environment checker reads config/local.php without exposing values', "dirname(__DIR__) . '/config/local.php'" in tool and 'printf("%s", $credentialKey)' not in tool),
    ('environment variable retains precedence over local config', "getenv($name)" in tool and "array_key_exists($name, $localConfig)" in tool),
    ('credential key uses strict base64 decoding', "base64_decode(trim($encoded), true)" in tool),
    ('credential key requires exactly 32 decoded bytes', "strlen($decoded) === 32" in tool),
    ('credential key memory is cleared when sodium_memzero is available', "sodium_memzero($decoded)" in tool),
    ('production checker reports credential-key readiness without printing the secret', "Remote credential key (base64 -> 32 bytes)" in tool and 'APP_REMOTE_CREDENTIAL_KEY_B64=' not in tool),
    ('checkpoint documentation warns against arbitrary character strings', 'not an arbitrary 32/64-character password' in doc),
    ('local config example includes the exact secure generation command', 'base64_encode(random_bytes(32))' in example),
]

passed = 0
failed = 0
for label, ok in checks:
    if ok:
        passed += 1
        print(f'PASS: {label}')
    else:
        failed += 1
        print(f'FAIL: {label}')

print(f'RESULT: PASS {passed} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
