from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
PASS = 0
FAIL = 0


def check(condition: bool, message: str) -> None:
    global PASS, FAIL
    if condition:
        PASS += 1
        print(f"PASS: {message}")
    else:
        FAIL += 1
        print(f"FAIL: {message}")


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")

x_php = text("app/x_widget.php")
api_php = text("app/api.php")
http_php = text("app/http_fetch.php")
dash_php = text("app/dashboard_widget.php")
info_php = text("app/information_widget.php")
conf_php = text("app/common/common_conf.php")
version_php = text("app/version.php")
calendar_js = text("public/js/calendar.js")
x_js = text("public/js/x-widget.js")
x_css = text("public/css/x-widget.css")
local_example = text("config/local.php.example")
env_example = text("config/.env.example")
schema = text("database/schema.sql")

check("'x_timeline'" in dash_php, "dashboard widget type includes x_timeline")
check("'x_timeline'" in info_php, "shared information widget persistence accepts x_timeline")
check("widget.x.create" in api_php and "widget.x.update" in api_php and "widget.x.delete" in api_php, "X widget CRUD API actions are registered")
check("x.timeline.fetch" in api_php, "X timeline fetch API action is registered")
check("X_API_HOST = 'api.x.com'" in x_php, "server client pins X API host")
check("https://' . X_API_HOST . '/2/users/by/username/" in x_php, "username lookup uses X API v2 endpoint")
check("'/2/users/' . rawurlencode($account['id']) . '/tweets?'" in x_php, "user posts endpoint is built from validated numeric user id")
check("'max_results' => $maxResults" in x_php and "'post.fields' => 'created_at'" in x_php, "timeline requests bounded post fields and max_results")
check("$exclude[] = 'replies'" in x_php and "$exclude[] = 'retweets'" in x_php, "reply and repost filters use X timeline exclude values")
check("APP_X_CACHE_TTL_SECONDS" in conf_php and "APP_X_STALE_MAX_AGE_SECONDS" in conf_php and "APP_X_TIMEOUT_MS" in conf_php, "X timeout/cache settings have bounded server defaults")
check("authorization_bearer" in http_php and "Authorization: Bearer " in http_php, "safe HTTP transport has a narrow explicit Bearer path")
check("request_headers" in http_php and "If-None-Match" in http_php, "existing arbitrary-header restriction remains in safe HTTP transport")
check("api.x.com" not in x_js and "Authorization: Bearer" not in x_js and "replace-with-your-x-api-bearer-token" not in x_js, "browser module contains no X API endpoint or credential value/header")
check("./api_v1.php" in x_js, "browser X data requests stay on the local API")
check("window.location.reload" not in x_js, "X widget CRUD and refresh do not force a whole-page reload")
check("function insertCardSorted" in x_js and ".insertBefore(this)" in x_js, "X widget insertion targets one location without rebuilding the full grid")
check(".replaceWith($card)" in x_js, "X settings update replaces only the target X card")
check("$('<div>').addClass('x-widget-post-text').text" in x_js, "post text is rendered with text() rather than HTML")
check("/^https:\\/\\/x\\.com\\//i.test(url)" in x_js, "post links are restricted to x.com before rendering")
check("x-widget.js?v=1.17.2-a" in calendar_js and "x-widget.css?v=1.17.2-a" in calendar_js, "staged X assets are loaded with the V1.17.2-A revision")
check("APP_ASSET_REVISION = '1.17.2-a'" in version_php and "APP_VERSION = '1.17.1'" in version_php, "staged asset revision changes without prematurely finalizing APP_VERSION")
check("replace-with-your-x-api-bearer-token" in local_example and "replace-with-your-x-api-bearer-token" in env_example, "configuration examples contain placeholders only")
check("APP_X_BEARER_TOKEN" in local_example and "APP_X_BEARER_TOKEN" in env_example, "both supported private config examples document the Bearer Token")
check("connect-src" not in text("public/.htaccess") if (ROOT / "public/.htaccess").exists() else True, "V1.17.2-A does not require browser CSP access to api.x.com")

match = re.search(r"`widget_type` VARCHAR\((\d+)\)", schema)
check(bool(match) and len("x_timeline") <= int(match.group(1)), "x_timeline fits the existing dashboard_widget.widget_type column")
check("CREATE TABLE" not in x_php and "ALTER TABLE" not in x_php, "X widget runtime adds no schema mutation")
check("APP_X_BEARER_TOKEN' => 'replace-with-your-x-api-bearer-token'" in local_example, "real Bearer Token is not embedded in the production example")
check(".x-widget-card" in x_css and "var(--bs-body-bg" in x_css and "var(--bs-body-color" in x_css, "X card styling follows Bootstrap theme variables")
check("x_protected_account" in x_php and "x_rate_or_usage_limited" in api_php, "public-app-only access and usage-limit errors are explicit")
check("function x_widget_exception_allows_stale" in x_php and "x_access_forbidden" not in x_php.split("function x_widget_exception_allows_stale", 1)[1].split("/** @param", 1)[0], "stale fallback is limited to transient failures, not authorization failures")

print(f"SUMMARY PASS={PASS} FAIL={FAIL}")
sys.exit(0 if FAIL == 0 else 1)
