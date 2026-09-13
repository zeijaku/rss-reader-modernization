#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
domain = (ROOT / 'app/calendar_exception.php').read_text(encoding='utf-8')
calendar = (ROOT / 'app/calendar.php').read_text(encoding='utf-8')
recurrence = (ROOT / 'app/calendar_recurrence.php').read_text(encoding='utf-8')
range_php = (ROOT / 'app/calendar_range.php').read_text(encoding='utf-8')
api = (ROOT / 'public/calendar_recurrence_api.php').read_text(encoding='utf-8')
main_api = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')
color_api = (ROOT / 'public/calendar_color_api.php').read_text(encoding='utf-8')
integration = (ROOT / 'app/api/integrations.php').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/025_v1_33_calendar_event_exception.sql').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
core = (ROOT / 'public/js/calendar-core.js').read_text(encoding='utf-8')
polish = (ROOT / 'public/js/calendar-polish.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

passed = 0
failed = 0


def check(condition: bool, message: str) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f'PASS: {message}')
    else:
        failed += 1
        print(f'FAIL: {message}')


check('CREATE TABLE' in migration and 'calendar_event_exception' in migration, '025 creates the occurrence exception table')
check('information_schema.TABLES' in migration and "'SELECT 1'" in migration, '025 is idempotent when the table already exists')
check("REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$'" in migration, '025 validates the configurable table prefix')
check(not re.search(r'\b(?:DROP|TRUNCATE|DELETE|ALTER)\b', migration, re.I), '025 contains no destructive or existing-table mutation SQL')
check(not re.search(r'\bFOREIGN\s+KEY\s*\(', migration, re.I), '025 avoids a foreign key that could reject legacy logical data')
check('UNIQUE KEY `uq_cal_exception_owner_event_original`' in migration, '025 enforces one row per owner, parent and original occurrence')
check('idx_cal_exception_owner_original' in migration and 'idx_cal_exception_owner_effective' in migration, '025 indexes original and effective range lookups')
for column in (
    'calendar_event_exception_owner', 'calendar_event_exception_event_id',
    'calendar_event_exception_original_start_date', 'calendar_event_exception_kind',
    'calendar_event_exception_revision', 'calendar_event_exception_flag',
    'calendar_event_exception_start_date', 'calendar_event_exception_end_date',
    'calendar_event_exception_title', 'calendar_event_exception_note',
    'calendar_event_exception_color', 'calendar_event_exception_all_day',
    'calendar_event_exception_start_time', 'calendar_event_exception_end_time',
    'calendar_event_exception_url',
):
    check(column in migration and column in schema, f'{column} is aligned in migration and fresh schema')
check('V1.33-D Calendar occurrence overrides and cancellations (025)' in schema, 'fresh-install schema documents the integrated 025 structure')
check("'calendar_event_exception'" in conf, 'fixed table-name allowlist includes the exception table')
check('!/database/migrations/025_v1_33_calendar_event_exception.sql' in gitignore, '025 is explicitly tracked despite SQL ignore rules')

check("['override', 'cancelled']" in domain, 'exception kind uses a fixed two-value allowlist')
check("preg_match('/\\A[a-f0-9]{64}\\z/D'" in domain, 'occurrence revision accepts only an opaque 64-hex token')
check("hash('sha256'" in domain and 'hash_equals(' in domain, 'optimistic concurrency token is generated and compared safely')
check("return 'event:' . $eventId . ':' . $originalOccurrenceStartDate" in recurrence, 'stable occurrence identity uses parent id and original start')
check('CALENDAR_EXCEPTION_MAX_ACTIVE = 500' in domain, 'active exception resource limit is fixed at 500')
check('CALENDAR_EXCEPTION_MAX_RANGE_ROWS = 2000' in domain, 'range exception resource limit is fixed at 2000')
check('calendar_event_exception_owner = :owner' in domain, 'all exception reads and mutations are owner scoped')
check('calendar_lock_owned_event($pdo, $ownerId, $eventId)' in domain, 'mutation locks an active owned parent before exception access')
check('calendar_event_exception_source_occurrence($parent, $originalStart)' in domain, 'mutation accepts only a real occurrence from the current series')
check('calendar_event_exception_original_start_date BETWEEN :original_floor AND :original_end' in domain, 'range load finds exceptions by original occurrence window')
check('calendar_event_exception_start_date <= :effective_end' in domain and 'calendar_event_exception_end_date >= :effective_start' in domain, 'range load also finds overrides moved into the window')
check("p.calendar_event_flag = 0" in domain, 'deleted parent rows cannot expose occurrence overrides')
check("$exception['calendar_event_exception_kind'] === 'cancelled'" in domain, 'cancelled occurrences are separated from visible events')
check('calendar_event_exception_flag = 1' in domain and 'calendar_event_exception_revision + 1' in domain, 'restore is logical and advances revision without deleting history')
check('Series change would invalidate occurrence exceptions' in domain, 'series changes explicitly protect active exception identities')
check('calendar_event_exception_assert_series_change_allowed(' in calendar, 'legacy basic Calendar update path invokes the series guard')
check('calendar_event_exception_assert_series_change_allowed(' in recurrence, 'combined recurrence update path invokes the series guard')
check('calendar_event_exception_apply_range(' in range_php, 'common range expansion applies occurrence exceptions')
check("'cancelled_occurrences' => $eventState['cancelled_occurrences']" in range_php, 'range API data retains cancelled items for restore UI')

for action in ('calendar.occurrence.update', 'calendar.occurrence.cancel', 'calendar.occurrence.restore'):
    check(f"'{action}'" in api, f'{action} is in the fixed action allowlist')
check("REQUEST_METHOD'] ?? 'GET') !== 'POST'" in api, 'occurrence actions remain POST only')
check('app_session_user_id()' in api and 'app_csrf_is_valid' in api, 'occurrence actions require authenticated session and CSRF')
check('APP_API_MAX_REQUEST_BYTES' in api, 'occurrence actions keep the request byte limit')
check('app_session_release();' in api, 'session lock is released before occurrence database work')
check('calendar_event_occurrence_update(\n            $userId' in api, 'authenticated user id is the only occurrence owner source')
check("calendar_occurrence_conflict', $exception->getMessage(), 409" in api, 'occurrence API exposes deterministic 409 conflicts')
check("calendar_exception_unavailable" in api, 'missing D migration has an explicit service-unavailable response')
check('JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT' in api, 'occurrence JSON keeps defensive HTML character escaping')
check("require_once dirname(__DIR__) . '/app/calendar_exception.php';" in main_api, 'legacy main API loads the exception guard')
check("require_once dirname(__DIR__) . '/app/calendar_exception.php';" in color_api, 'legacy color/time API loads the exception guard')
check('catch (CalendarOccurrenceConflictException $exception)' in integration, 'legacy main API maps series conflict')
check('catch (CalendarOccurrenceConflictException $exception)' in color_api, 'legacy color/time API maps series conflict')
check(not list((ROOT / 'public').glob('*occurrence*.php')), 'D adds no standalone public occurrence endpoint')

for source, label in ((core, 'month'), (polish, 'upcoming')):
    check('data-calendar-occurrence-revision' in source, f'{label} DOM carries occurrence revision')
    check('data-calendar-exception-id' in source and 'data-calendar-exception-kind' in source, f'{label} DOM carries exception metadata')
    check(not re.search(r'\.innerHTML\s*=', source), f'{label} renderer adds no HTML assignment sink')
version_match = re.search(r"const APP_VERSION = '([0-9]+\.[0-9]+\.[0-9]+(?:-dev\.[0-9]+)?)';", version)
asset_match = re.search(r"const APP_ASSET_REVISION = '([0-9]+\.[0-9]+\.[0-9]+(?:-dev\.[0-9]+)?)';", version)
version_tuple = tuple(int(part) for part in version_match.group(1).split('-', 1)[0].split('.')) if version_match else ()
check(version_match is not None and version_tuple >= (1, 33, 1),
      'release or development checkpoint version keeps the V1.33-D contract')
check(asset_match is not None and version_match is not None and asset_match.group(1) == version_match.group(1),
      'release or development checkpoint cache revision keeps the V1.33-D contract')

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)