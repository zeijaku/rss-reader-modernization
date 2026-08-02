from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
version = (ROOT / "app" / "version.php").read_text(encoding="utf-8")
login = (ROOT / "app" / "common" / "common_login.php").read_text(encoding="utf-8")
index = (ROOT / "public" / "index.php").read_text(encoding="utf-8")
bootstrap = (ROOT / "app" / "bootstrap.php").read_text(encoding="utf-8")

version_match = re.search(r"const APP_VERSION = '([^']+)';", version)
label_match = re.search(r"const APP_VERSION_LABEL = '([^']+)';", version)
version_value = version_match.group(1) if version_match else ''
label_value = label_match.group(1) if label_match else ''

is_checkpoint = bool(re.fullmatch(r"(?:SB-\d+|M\d+-[A-Z]) R\d+", version_value))
is_release = bool(re.fullmatch(r"1\.0\.0(?:-rc[1-9][0-9]*)?", version_value))
checkpoint_label = bool(re.fullmatch(r"(?:Secure Baseline SB-\d+|(?:RSS Engine|Frontend|Release) M\d+-[A-Z]) / R\d+", label_value))
release_label = label_value == ('RSS Reader Modernization ' + version_value.upper()) if is_release else False
stage_match = False
if version_value and label_value:
    if version_value.startswith('SB-'):
        stage_match = version_value.replace(' ', ' / ', 1) == label_value.replace('Secure Baseline ', '')
    elif version_value.startswith('M'):
        stage_match = version_value.replace(' ', ' / ', 1) == label_value.replace('RSS Engine ', '').replace('Frontend ', '').replace('Release ', '')
    elif is_release:
        stage_match = release_label

checks = {
    "version constant format": is_checkpoint or is_release,
    "version label format": checkpoint_label or release_label,
    "version and label stages match": stage_match,
    "bootstrap loads version": "require_once __DIR__ . '/version.php';" in bootstrap,
    "login marker": login.count('data-app-version') >= 2,
    "login uses label": "APP_VERSION_LABEL" in login,
    "dashboard footer marker": 'footer class="text-center text-muted small py-3" data-app-version' in index,
    "dashboard uses label": "APP_VERSION_LABEL" in index,
}
failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(("PASS" if ok else "FAIL") + ": " + name)
if failed:
    raise SystemExit(1)
