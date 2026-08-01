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

checks = {
    "version constant format": bool(re.fullmatch(r"(?:SB-\d+|M\d+-[A-Z]) R\d+", version_value)),
    "version label format": bool(re.fullmatch(r"(?:Secure Baseline SB-\d+|RSS Engine M\d+-[A-Z]) / R\d+", label_value)),
    "version and label stages match": ((version_value.startswith('SB-') and version_value.replace(' ', ' / ', 1) == label_value.replace('Secure Baseline ', '')) or (version_value.startswith('M') and version_value.replace(' ', ' / ', 1) == label_value.replace('RSS Engine ', ''))) if version_value and label_value else False,
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
