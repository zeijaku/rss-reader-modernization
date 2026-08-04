from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
login = (ROOT / "app/common/common_login.php").read_text(encoding="utf-8")
css = (ROOT / "public/css/auth.css").read_text(encoding="utf-8")

checks = []
def check(condition, message):
    checks.append(bool(condition))
    print(("PASS" if condition else "FAIL") + ": " + message)

check('<main id="main-content" class="auth-shell" tabindex="-1">' in login, "Login and Register share the authentication main wrapper")
check(re.search(r"\.auth-shell\s*\{[^}]*min-height\s*:\s*100vh\s*;[^}]*align-items\s*:\s*center\s*;[^}]*justify-content\s*:\s*center\s*;", css, re.S) is not None, "authentication shell centers the card vertically and horizontally")
check(re.search(r"\.auth-frame\s*\{[^}]*width\s*:\s*100%\s*;[^}]*max-width\s*:\s*430px\s*;", css, re.S) is not None, "authentication frame keeps a readable maximum width")
check('data-auth-panel="login"' in login and 'data-auth-panel="register"' in login, "both Login and Register panels remain in the shared layout")
check(login.index('data-auth-panel="login"') < login.index('data-auth-panel="register"') < login.index('</main>'), "both panels are children of the authentication main wrapper")
check('@media (max-width: 520px)' in css and '.auth-card' in css, "authentication layout has a smartphone breakpoint")
check('.auth-input:focus-visible' in css and '.auth-button:focus-visible' in css, "authentication controls keep visible keyboard focus")

if not all(checks):
    sys.exit(1)
print(f"All {len(checks)} M2-C Login layout checks passed.")
