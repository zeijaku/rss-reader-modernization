from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
login = (ROOT / "app/common/common_login.php").read_text(encoding="utf-8")

checks = []
def check(condition, message):
    checks.append(bool(condition))
    print(("PASS" if condition else "FAIL") + ": " + message)

check('<main id="main-content" class="login-main" tabindex="-1">' in login, "Login and Register share the login main wrapper")
check(re.search(r"\.login-main\s*\{[^}]*width\s*:\s*100%\s*;[^}]*\}", login, re.S) is not None, "login main spans the available horizontal area")
check(re.search(r"\.form-signin\s*\{[^}]*max-width\s*:\s*330px\s*;[^}]*margin\s*:\s*auto\s*;[^}]*\}", login, re.S) is not None, "Login and Register panels retain the original auto-centering rule")
check('id="multiCollapseExample1"' in login and 'id="multiCollapseExample2"' in login, "both Login and Register panels remain inside the shared layout")
check(login.index('<main id="main-content" class="login-main"') < login.index('id="multiCollapseExample1"') < login.index('id="multiCollapseExample2"') < login.index('</main>'), "both panels are children of the full-width main wrapper")

if not all(checks):
    sys.exit(1)
print(f"All {len(checks)} M2-C Login layout checks passed.")
