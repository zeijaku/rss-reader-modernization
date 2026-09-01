from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
js = (root / 'public/js/remote-files.js').read_text(encoding='utf-8')
page = (root / 'public/remote-files.php').read_text(encoding='utf-8')
version = (root / 'app/version.php').read_text(encoding='utf-8')

passed = 0
failed = 0

def check(cond, label):
    global passed, failed
    if cond:
        passed += 1
        print('PASS:', label)
    else:
        failed += 1
        print('FAIL:', label)

check("data.password = el.password.value;" in js, 'password field is always serialized for password authentication')
check("if (id <= 0 && data.password === '')" in js, 'new connection validates password before API call')
check("Password / App Passwordを入力してください。" in js, 'client-side missing-password feedback is explicit')
check("data.private_key = el.privateKey.value;" in js, 'private key field is always serialized for private-key authentication')
check("if (id <= 0 && data.private_key === '')" in js, 'new private-key connection validates key before API call')
check("catch (error) {\n            clearCredentialInputs();" not in js, 'failed save does not erase credential input')
check('id="remoteConnectionUsername" name="username"' in page, 'username has form semantic name')
check('id="remoteConnectionPassword" name="password"' in page, 'password has form semantic name')
check('autocomplete="current-password"' in page, 'remote password field uses credential-oriented autocomplete semantics')
version_match = re.search(r"APP_VERSION\s*=\s*'([^']+)'", version)
asset_match = re.search(r"APP_ASSET_REVISION\s*=\s*'([^']+)'", version)
check(version_match is not None and bool(version_match.group(1)), 'active application version marker remains defined')
check(version_match is not None and asset_match is not None and asset_match.group(1) == version_match.group(1), 'asset revision follows the active version so corrected JS reloads')

print(f'Credential submit R1 tests: {passed} passed, {failed} failed')
raise SystemExit(1 if failed else 0)
