#!/usr/bin/env python3
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


health = text('app/feed_health.php')
api = text('app/api/feed_health.php')
dispatch = text('app/api.php')
fetcher = text('app/feed/feed_fetcher.php')
migration = text('database/migrations/015_v1_22_feed_health.sql')
client = text('public/js/feed-health.js')
management = text('public/js/rss-management.js')
calendar = text('public/js/calendar.js')
version = text('app/version.php')
asset_match = re.search(r"APP_ASSET_REVISION\s*=\s*'([^']+)'", version)
asset_revision = asset_match.group(1) if asset_match else ''

check('`user_id`' not in migration.lower(), 'Feed Health child table does not duplicate user_id')
check('health_content_id' in migration and 'PRIMARY KEY (`health_content_id`)' in migration, 'Feed Health is keyed by content_id')
check('find_owned_active_content($ownerId, $contentId)' in health, 'Health reads/writes derive ownership from content')
check("'feed.health.get'" in dispatch and "'feed.health.list'" in dispatch and "'feed.health.recheck'" in dispatch, 'Feed Health API actions are registered')
check('api_feed_fetch_with_health' in dispatch, 'Normal feed.fetch is wrapped with Health enrichment')
check('feed_health_observe_transport($source, $result)' in fetcher, 'Only actual FeedFetcher outbound attempts create transport observations')
check('new FeedFetcher()' in api and 'new FeedParser()' in api, 'Manual recheck reuses the existing safe FeedFetcher and FeedParser')
check("api_positive_int($input, 'content_id')" in api and "content['content_value']" in api, 'Manual recheck accepts content_id and resolves the stored owned URL')
recheck = api.split('function api_feed_health_recheck', 1)[1]
check("api_string($input, 'url')" not in recheck and "$input['url']" not in recheck and '$input["url"]' not in recheck,
      'Manual recheck never accepts an arbitrary request URL')
check('new FeedFetcher()' in recheck and 'new FeedParser()' in recheck, 'Manual recheck stays on the safe Feed fetch/parser path')
check('feed_health_get_owned($userId, $contentId);' in recheck and recheck.find('feed_health_get_owned($userId, $contentId);') < recheck.find('new FeedFetcher()'),
      'Manual recheck verifies Health persistence before outbound I/O')
check('FEED_HEALTH_INACTIVITY_DAYS = 30' in health, 'Long inactivity threshold is explicit and bounded')
for status in ['normal', 'warning', 'error', 'unknown']:
    check("'" + status + "'" in health, f'Health status supports {status}')
check("+ 1" in health and "consecutive_failure_count=0" in health, 'Failure writes increment the counter and success resets it')
check("status !== 'warning' && status !== 'error'" in client, 'RSS card header stays quiet for Normal/Unknown')
check('feedHealthRecheck' in client and 'feed.health.recheck' in client, 'RSS settings exposes manual recheck')
check('feed.health.list' in management and 'rss-health-heading' in management, 'RSS management shows compact Health overview')
check('function loadHealthForFeeds(feeds)' in management, 'RSS management isolates Health loading from the core RSS list')
check("renderFeeds(feeds, {});" in management and "loadHealthForFeeds(feeds);" in management, 'RSS list renders before optional Health enrichment')
check('Feed Healthの取得に失敗しました' in management and "$('#rssManagementTableWrap').prop('hidden', false);" in management,
      'Health failure leaves the RSS list visible with a bounded warning')
check('$.when(feedsRequest, healthRequest)' not in management, 'Health failure can no longer fail the OPML/RSS list request as one combined promise')
check(asset_revision.startswith('1.22.0-'), 'V1.22 keeps a staged asset revision')
check(f'./js/feed-health.js?v={asset_revision}' in calendar, 'Dashboard loads Feed Health under the current V1.22 asset key')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
