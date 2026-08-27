#!/usr/bin/env python3
from pathlib import Path

from version_contract_utils import current_asset_revision

ROOT = Path(__file__).resolve().parents[1]
checks = []

def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

engine = text('app/rss_rule_engine.php')
health_api = text('app/api/feed_health.php')
rules_api = text('app/api/rss_rule.php')
dispatch = text('app/api.php')
display = text('public/js/rss-rule-display.js')
management = text('public/js/rss-management.js')
guidance = text('public/js/rss-rules-integration.js')
calendar = text('public/js/calendar.js')
asset_revision = current_asset_revision(ROOT)

check("RSS_RULE_AUTO_STOCK_MAX_PER_FETCH = 10" in engine, 'Auto Stock writes are bounded per fetch')
check("['contains', 'not_contains', 'equals', 'prefix']" not in engine and "'regex'" not in engine, 'D does not add a Regex rule mode')
check('rss_rule_list_owned($ownerId)' in engine and "($rule['enabled'] ?? false) !== true" in engine, 'Only current-owner enabled Rules are evaluated')
check("$scope !== null && (int) $scope !== $contentId" in engine, 'Feed-scoped Rules are restricted to the current content')
check('app_remove_tracking_parameters($url)' in engine, 'Auto Stock removes tracking parameters')
check('rss_rule_stock_exists_owned' in engine and 'stock_owner = :owner' in engine, 'Auto Stock duplicate lookup is owner-scoped')
check('rss_rule_lock_owner' in engine and 'FOR UPDATE' in engine, 'Auto Stock serializes owner writes on MySQL')
check('info_dbsave($ownerId, $url, $title)' in engine, 'Auto Stock reuses existing Stock persistence without article refetch')
check('FeedFetcher' not in engine and 'app_safe_http_fetch' not in engine and 'curl_' not in engine, 'Rule engine performs no outbound HTTP')
check("$item['rule_highlight'] = true" in engine and 'continue;' in engine, 'Engine marks Highlight and filters Hide before response')
check("$feed['new_count'] = count(array_filter($visible" in engine, 'Visible new count is recalculated after Hide')
check("function_exists('rss_rule_apply_to_feed')" in health_api, 'Rules are applied only after existing Feed Health wrapper')
check(health_api.find('feed_health_finalize_success') < health_api.find("function_exists('rss_rule_apply_to_feed')"), 'Feed Health finalization remains ahead of Rule actions')
check("'feed.fetch' => api_feed_fetch_with_health" in dispatch, 'Existing feed.fetch dispatcher is unchanged')
check("require_once dirname(__DIR__) . '/rss_rule_engine.php';" in rules_api, 'Rules API loads the D execution engine')
check('rule_highlight' in display and 'rss-rule-highlight' in display, 'Client renders server-evaluated Rule Highlight')
check('renderFeedKeywordTitle' not in display, 'Rule display layer does not replace existing keyword highlight logic')
check(bool(asset_revision), 'Current application asset revision is available')
check(
    f"rss-rules.js?v={asset_revision}" in management and
    f"rss-rules-integration.js?v={asset_revision}" in management,
    'RSS Management loads Rule assets with the current application revision'
)
check('Auto Stockは一致記事を重複を避けてStockへ追加' in guidance, 'Rules UI explains D action behavior')
check(
    f"rss-rule-display.js?v={asset_revision}" in calendar and
    f"rss-rule-display.css?v={asset_revision}" in calendar,
    'Dashboard loads Rule display assets with the current application revision'
)

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
