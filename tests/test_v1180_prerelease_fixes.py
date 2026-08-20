from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(condition, message):
    checks.append((bool(condition), message))
    print(("PASS" if condition else "FAIL") + ": " + message)

session = (ROOT / "app/session.php").read_text(encoding="utf-8")
persistent = (ROOT / "app/persistent_login.php").read_text(encoding="utf-8")
api = (ROOT / "public/api_v1.php").read_text(encoding="utf-8")
api_core = (ROOT / "app/api.php").read_text(encoding="utf-8")
dashboard = (ROOT / "public/js/dashboard.js").read_text(encoding="utf-8")
css = (ROOT / "public/css/dashboard.css").read_text(encoding="utf-8")
version = (ROOT / "app/version.php").read_text(encoding="utf-8")
calendar = (ROOT / "public/js/calendar.js").read_text(encoding="utf-8")
camera_streaming = (ROOT / "public/js/camera-video-streaming.js").read_text(encoding="utf-8")

check("function app_csrf_current_token()" in session, "Session exposes a read-only current CSRF token helper")
check("function app_csrf_allow_previous_token(" in session, "Session supports a short previous-token grace period")
check("csrf_previous_expires_at" in session and "min($graceSeconds, 600)" in session, "Previous CSRF token grace is bounded")
check("$_SESSION['csrf_token'] = bin2hex(random_bytes(32));" not in session.split("if ($authenticationExpired)", 1)[1].split("} else {", 1)[0], "Idle expiry does not discard the stale page token before Remember Me restoration")
check("$previousCsrfToken = app_csrf_current_token();" in persistent, "Remember Me captures the stale page CSRF token before rotating login state")
check("app_csrf_allow_previous_token($previousCsrfToken);" in persistent, "Remember Me grants only the captured stale token a short overlap")
check("$previousCsrfToken = app_csrf_current_token();" in api_core and "app_csrf_allow_previous_token($previousCsrfToken);" in api_core, "Account-setting session rotation also gives other open tabs a short CSRF overlap")
check("header('X-CSRF-Token: ' . $csrfToken);" in api, "Authenticated API responses return the fresh CSRF token in a same-origin response header")
check("getResponseHeader('X-CSRF-Token')" in dashboard, "Dashboard synchronizes its meta CSRF token from API responses")
check("xhr.status === 401" in dashboard and "code === 'unauthenticated'" in dashboard and "window.location.reload()" in dashboard, "True session expiry reloads to the normal login flow instead of leaving a raw API error")

marker = "/* V1.18 pre-release fix: fit all seven columns inside the mobile card."
marker_pos = css.find(marker)
mobile_start = css.rfind("@media (max-width: 575.98px)", 0, marker_pos if marker_pos >= 0 else len(css))
mobile_end = css.find("/* V1.1-I / R2", marker_pos if marker_pos >= 0 else 0)
mobile_css = css[mobile_start:mobile_end] if mobile_start >= 0 and mobile_end > mobile_start else ""
check(mobile_css != "", "Mobile Calendar media query exists")
check(".calendar-toolbar" in mobile_css and ".calendar-weekdays" in mobile_css and ".calendar-days" in mobile_css, "Mobile rule covers toolbar, weekday row, and day grid")
check("min-width: 500px" not in mobile_css, "Mobile Calendar no longer forces a 500px minimum width")
check("width: 100%" in mobile_css and "min-width: 0" in mobile_css and "max-width: 100%" in mobile_css, "Mobile Calendar is constrained to the card width")
check(".dashboard-grid .calendar-card-body" in css and "overflow-x: auto" in css, "Narrow desktop Calendar overflow remains contained inside the card")
check("overflow-x: hidden" in mobile_css, "Mobile Calendar does not create a document-level horizontal overflow path")

check("const APP_ASSET_REVISION = '1.18.0-r2';" in version, "Final pre-Git Asset Revision busts the immutable 1.18.0 cache")
for asset in [
    './css/mail-widget.css', './css/camera-video.css', './css/camera-video-playback.css',
    './css/camera-video-streaming.css', './css/x-widget.css', './js/app-notice.js',
    './js/widget-card-refresh.js', './js/information-widget-watchdog.js',
    './js/mail-widget-watchdog.js', './js/camera-video-watchdog.js', './js/mail-widget.js',
    './js/camera-video.js', './js/camera-video-playback.js', './js/camera-video-streaming.js',
    './js/x-widget.js', './js/widget-settings-no-reload.js'
]:
    check(asset + '?v=1.18.0-r2' in calendar, f"Dynamic asset loader uses final revision: {asset}")
check("./css/camera-video-streaming.css?v=1.18.0-r2" in camera_streaming, "Camera streaming fallback CSS uses final revision")

failed = [m for ok, m in checks if not ok]
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
raise SystemExit(1 if failed else 0)
