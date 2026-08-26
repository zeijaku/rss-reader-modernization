import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

migration = (ROOT / 'database/migrations/016_v1_22_rss_rules.sql').read_text(encoding='utf-8')
core = (ROOT / 'app/rss_rule.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api/rss_rule.php').read_text(encoding='utf-8')
dispatch = (ROOT / 'app/api.php').read_text(encoding='utf-8')
ui = (ROOT / 'public/js/rss-rules.js').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/rss-management.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
calendar = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
asset_match = re.search(r"APP_ASSET_REVISION\s*=\s*'([^']+)'", version)
asset_revision = asset_match.group(1) if asset_match else ''

check('rss_rule' in migration and 'rss_rule_condition' in migration, 'Migration creates normalized Rule and Condition tables')
condition_section = migration.split('CREATE TABLE IF NOT EXISTS ', 2)[-1]
check('user_id' not in condition_section and 'condition_rule_id' in condition_section, 'Condition table does not duplicate user ownership')
check("['title', 'content', 'url', 'feed', 'category']" in core, 'Allowed fields are explicit')
check("['contains', 'not_contains', 'equals', 'prefix']" in core, 'Allowed operators are explicit and Regex is absent')
check("['highlight', 'hide', 'auto_stock']" in core, 'C stores the three planned action types')
check('find_owned_active_content($ownerId, $contentId)' in core, 'Feed scope is checked against current-user ownership')
check('RSS_RULE_MAX_PER_USER = 50' in core and 'RSS_RULE_MAX_CONDITIONS = 10' in core, 'Rule and condition counts are bounded')
for action in ['rss.rule.list', 'rss.rule.create', 'rss.rule.update', 'rss.rule.toggle', 'rss.rule.delete']:
    check(action in dispatch, f'API dispatcher exposes {action}')
check('conditions_json' in api and 'JSON_THROW_ON_ERROR' in api, 'Condition JSON is bounded and strictly decoded')
check('RSS Rules' in ui and 'rssRuleForm' in ui, 'RSS Management UI exposes Rules CRUD')
check(asset_revision == '1.22.0' or asset_revision.startswith('1.22.0-'), 'V1.22 uses a staged or final asset revision')
check(f"rss-rules.js?v={asset_revision}" in loader, 'RSS Management loads Rules UI under the current V1.22 asset key')
check(f"feed-health.js?v={asset_revision}" in calendar, 'Dashboard staged assets use the current V1.22 cache key')
check('preg_match' not in ui and 'RegExp' not in ui, 'No client Regex rule mode is introduced')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
