from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(condition: bool, message: str) -> None:
    checks.append(condition)
    print(('PASS' if condition else 'FAIL') + ': ' + message)

state = (ROOT / 'app/feed/feed_item_state.php').read_text(encoding='utf-8')
parser = (ROOT / 'app/feed/feed_parser.php').read_text(encoding='utf-8')
item = (ROOT / 'app/feed/normalized_item.php').read_text(encoding='utf-8')
service = (ROOT / 'app/feed/feed_fetch_service.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
local_example = (ROOT / 'config/local.php.example').read_text(encoding='utf-8')
env_example = (ROOT / 'config/.env.example').read_text(encoding='utf-8')

check("'feed_item_state'" in conf and "DB_TABLE_PREFIX" in conf, 'new table uses the existing prefixed table-name resolver')
check("require_once __DIR__ . '/feed/feed_item_state.php';" in bootstrap, 'Feed item state module loads through bootstrap')
check("function feed_item_state_valid_identity" in state and "m1i:v1:[a-f0-9]{64}" in state, 'only canonical opaque Item Identity values are accepted')
check("feed_item_state_lock_owned_content" in state and "content_owner = :owner_id" in state, 'state writes lock and scope the owned active Feed')
check("FOR UPDATE" in state and "beginTransaction" in state and "rollBack" in state, 'state synchronization uses transaction and per-Feed locking')
check("UNIQUE" not in state or "item_identity" in state, 'state code is identity based rather than title based')
check("initialBaseline ? $now : null" in state, 'first fetch becomes a seen baseline while later inserts stay unread')
check("seen_at IS NULL" in state and "feed_item_state_mark_seen" in state, 'NEW clear updates only unread state')
check("content.content_flag <> 0" in state and "APP_FEED_ITEM_STATE_RETENTION_DAYS" in state, 'retention cleanup is limited to inactive/deleted Feed state')

check("public function toArray(): array" in item and "public function toStateArray(): array" in item, 'legacy five-field item contract and internal state array are separated')
check("bool $includeIdentity = false" in parser, 'identity metadata is opt-in at parser compatibility boundary')
check("$includeIdentity ? $item->toStateArray() : $item->toArray()" in parser, 'legacy parse_start callers keep the original shape')
check(service.count("parse_start(") == 3 and service.count(", true)") >= 3, 'cache hit, stale and fetched Feed paths all retain Item Identity internally')

check("'feed.new.clear' => api_feed_new_clear" in api, 'explicit NEW clear API action is registered')
check("find_owned_active_content($userId, $contentId)" in api[api.find('function api_feed_new_clear'):], 'NEW clear checks authenticated ownership before state update')
check("$input['owner_id']" not in api and "$input['content_owner']" not in api, 'API never trusts a client-supplied owner')
check("feed_item_state_sync" in api and api.find('feed_item_state_sync') > api.find('find_owned_active_content($userId, $contentId)'), 'Feed ownership is checked before NEW state synchronization')
check("feed_item_state_valid_identity" in api and "'is_new'" in api and "'new_count'" in api, 'safe Feed payload exposes only validated identity and NEW metadata')
check("feed_item_state_unavailable" in api and "503" in api, 'missing/failed migration returns a structured service-unavailable error')

check(dashboard.count("addClass('fas fa-bell')") >= 2 and ".text(newCount)" in dashboard and ".text('NEW')" not in dashboard, 'Feed and item NEW indicators use Bell icons without the NEW text label')
check("feed-new-clear, .feed-item-new" in dashboard and "feed.new.clear" in dashboard, 'Feed-level and item-level NEW controls share the protected API action')
check("/^m1i:v1:[a-f0-9]{64}$/" in dashboard, 'browser validates opaque identity shape before submitting')
check("aria-label" in dashboard[dashboard.find('function renderFeedTitle'):dashboard.find('function feedRequestErrorMessage')], 'NEW controls include accessible labels')
check("button:focus" in css and ".feed-new-clear" in css and ".feed-item-new" in css and "width: 22px" in css, 'NEW controls retain visible focus and compact Bell button styling')

check("APP_FEED_ITEM_STATE_RETENTION_DAYS" in local_example and "APP_FEED_ITEM_STATE_RETENTION_DAYS=90" in env_example, 'optional retention setting is documented in both configuration examples')
check(("const APP_VERSION = '1.1.0-dev." in version and "RSS Reader Modernization V1.1-" in version) or ("const APP_VERSION = '1.1.0';" in version and "RSS Reader Modernization 1.1.0" in version), 'visible Version marker remains a V1.1 checkpoint or final release')

failed = [message for ok, message in zip(checks, [
    'new table uses the existing prefixed table-name resolver',
    'Feed item state module loads through bootstrap',
    'only canonical opaque Item Identity values are accepted',
    'state writes lock and scope the owned active Feed',
    'state synchronization uses transaction and per-Feed locking',
    'state code is identity based rather than title based',
    'first fetch becomes a seen baseline while later inserts stay unread',
    'NEW clear updates only unread state',
    'retention cleanup is limited to inactive/deleted Feed state',
    'legacy five-field item contract and internal state array are separated',
    'identity metadata is opt-in at parser compatibility boundary',
    'legacy parse_start callers keep the original shape',
    'cache hit, stale and fetched Feed paths all retain Item Identity internally',
    'explicit NEW clear API action is registered',
    'NEW clear checks authenticated ownership before state update',
    'API never trusts a client-supplied owner',
    'Feed ownership is checked before NEW state synchronization',
    'safe Feed payload exposes only validated identity and NEW metadata',
    'missing/failed migration returns a structured service-unavailable error',
    'Feed and item NEW indicators use Bell icons without the NEW text label',
    'Feed-level and item-level NEW controls share the protected API action',
    'browser validates opaque identity shape before submitting',
    'NEW controls include accessible labels',
    'NEW controls retain visible focus and compact Bell button styling',
    'optional retention setting is documented in both configuration examples',
    'visible Version marker remains a V1.1 development checkpoint',
]) if not ok]
if failed:
    raise SystemExit(f'{len(failed)}/{len(checks)} V1.1-C architecture checks failed')
print(f'All {len(checks)} V1.1-C architecture checks passed.')
