from pathlib import Path
import json
import re
import sys

from version_test_utils import is_later_application_release, is_later_visible_label
root = Path(__file__).resolve().parents[1]
checks = []
def check(cond, message):
    checks.append((bool(cond), message))
    print(('PASS' if cond else 'FAIL') + ': ' + message)

conf = (root/'app/common/common_conf.php').read_text()
local = (root/'config/local.php.example').read_text()
env = (root/'config/.env.example').read_text()
bootstrap = (root/'app/bootstrap.php').read_text()
holiday = (root/'app/holiday.php').read_text()
calendar = (root/'app/calendar.php').read_text()
api = (root/'app/api.php').read_text()
js = (root/'public/js/calendar-core.js').read_text()
css = (root/'public/css/dashboard.css').read_text()
gitignore = (root/'.gitignore').read_text()
snapshot = json.loads((root/'app/data/japanese_holidays_snapshot.json').read_text())
version = (root/'app/version.php').read_text()

check(is_later_application_release(version, (1, 7, 0)) or ("1.7.0-dev.10" in version and 'V1.7-H / R4' in version) or ("APP_VERSION = '1.7.0'" in version and "RSS Reader Modernization 1.7.0" in version), 'R4 behavior is retained in final cache-busting version marker')
check('APP_HOLIDAY_CSV_URL' in conf and 'APP_HOLIDAY_CACHE_DAYS' in conf and "'60'" in conf, 'holiday source URL and 60-day interval are runtime configuration')
check('APP_HOLIDAY_CSV_URL' in local and 'APP_HOLIDAY_CACHE_DAYS' in local, 'private local.php example exposes the URL and interval')
check('APP_HOLIDAY_CSV_URL=' in env and 'APP_HOLIDAY_CACHE_DAYS=60' in env, 'environment example exposes the same holiday settings')
check("require_once __DIR__ . '/holiday.php';" in bootstrap and bootstrap.index("'/http_fetch.php'") < bootstrap.index("'/holiday.php'"), 'holiday service loads after the safe HTTP primitives')
check("strtolower((string) ($parts['scheme'] ?? '')) !== 'https'" in holiday, 'holiday URL is restricted to HTTPS')
check('app_validate_fetch_target' in holiday and 'app_resolve_redirect_url' in holiday, 'holiday download reuses public-IP SSRF validation for each hop')
check('APP_HOLIDAY_CSV_MAX_BYTES' in holiday and 'APP_HOLIDAY_TIMEOUT_MS' in holiday, 'holiday fetch has explicit size and timeout bounds')
check("'accept' => 'text/csv" in holiday, 'holiday fetch explicitly requests CSV while RSS fetch keeps its default Accept header')
check("tempnam($dir, '.holiday-')" in holiday and 'rename($tmp, $path)' in holiday, 'validated holiday cache is replaced atomically')
check("'holiday_refresh_due'" in calendar and "'holidays'" in calendar, 'Calendar month response carries holiday data and refresh state')
check("'calendar.holiday.refresh'" in api and 'japanese_holiday_refresh()' in api, 'authenticated API exposes the background holiday refresh action')
check("calendar-day-holiday" in js and "holidayName" in js and "aria-label" in js and "title" in js, 'Calendar renderer marks holidays red with tooltip/accessibility metadata')
check("requestHolidayRefresh" in js and "holiday_refresh_due" in js and "calendar.holiday.refresh" in js, 'stale holiday refresh happens after normal Calendar rendering')
check('.calendar-day.calendar-day-holiday .calendar-day-number' in css and '#bd2130' in css, 'holiday date color overrides weekday and Saturday colors')
check('/var/cache/*' in gitignore and '!/var/cache/.gitkeep' in gitignore, 'runtime holiday cache files remain excluded from source control')
check(snapshot.get('holidays',{}).get('2026-08-11') == '山の日', 'bundled snapshot contains the current official 2026 Mountain Day')
check(snapshot.get('holidays',{}).get('2026-09-22') == '休日', 'bundled snapshot contains the 2026 holiday-law bridging day')
check(snapshot.get('holidays',{}).get('2027-03-22') == '休日', 'bundled snapshot contains the 2027 substitute holiday')
check('information_schema' not in holiday, 'holiday support introduces no database metadata dependency')

failed = [m for ok,m in checks if not ok]
if failed:
    print(f'{len(failed)}/{len(checks)} V1.7-H/R4 architecture checks failed.')
    sys.exit(1)
print(f'All {len(checks)} V1.7-H/R4 architecture checks passed.')
