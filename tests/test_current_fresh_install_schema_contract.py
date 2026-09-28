#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
INSTALL = (ROOT / 'docs/installation.md').read_text(encoding='utf-8')
README = (ROOT / 'README.md').read_text(encoding='utf-8')
UPDATE = (ROOT / 'docs/update.md').read_text(encoding='utf-8')

checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

expected_tables = {
    'user_info', 'user_conf', 'content', 'content_stock', 'feed_item_state',
    'memo', 'task', 'calendar_event', 'calendar_event_exception',
    'dashboard_widget', 'notification', 'remember_token', 'user_file',
    'remote_connection', 'auth_totp', 'auth_recovery_code', 'auth_session',
    'auth_audit_log', 'mail_account', 'link_item', 'stock_tag',
    'stock_tag_map', 'feed_keyword', 'feed_metadata', 'feed_health',
    'rss_rule', 'rss_rule_condition',
}
declared = set(re.findall(r"SET @t_([a-z0-9_]+) =", SCHEMA))
created = set(re.findall(r"CREATE TABLE ', @t_([a-z0-9_]+)", SCHEMA))
check(declared == expected_tables, 'fresh schema declares exactly the 27 current tables')
check(created == expected_tables, 'fresh schema creates exactly the 27 current tables')

upgrade_migrations = [
    '009_v1_9_mail_account.sql',
    '010_v1_10_links.sql',
    '011_v1_11_stock_tags.sql',
    '012_v1_12_feed_keywords.sql',
    '014_v1_22_opml_feed_metadata.sql',
    '015_v1_22_feed_health.sql',
    '016_v1_22_rss_rules.sql',
    '026_v1_34_mail_smtp.sql',
    '027_v1_34_mail_sent_save_mode.sql',
    '028_v1_35_mail_google_oauth.sql',
    '029_v1_35_remember_2fa_trust.sql',
]

for migration in upgrade_migrations:
    path = ROOT / 'database/migrations' / migration
    check(path.is_file(), f'upgrade migration remains available: {migration}')
    if not path.is_file():
        continue
    body = path.read_text(encoding='utf-8')
    identifiers = {
        value for value in re.findall(r'`([a-z][a-z0-9_]+)`', body)
        if value not in {'table_schema', 'table_name', 'column_name'}
    }
    missing = sorted(value for value in identifiers if f'`{value}`' not in SCHEMA)
    check(not missing, f'fresh schema carries all identifiers from {migration}')

mail_columns = [
    'mail_account_auth_type',
    'mail_account_smtp_enabled',
    'mail_account_smtp_host',
    'mail_account_smtp_port',
    'mail_account_smtp_encryption',
    'mail_account_smtp_use_imap_credentials',
    'mail_account_smtp_username',
    'mail_account_smtp_secret',
    'mail_account_from_address',
    'mail_account_from_name',
    'mail_account_sent_save_mode',
]
check(all(f'`{column}`' in SCHEMA for column in mail_columns),
      'fresh mail_account schema includes SMTP, Sent and OAuth2 columns')
check('`remember_token_second_factor_verified_at` DATETIME NULL DEFAULT NULL' in SCHEMA,
      'fresh remember_token schema includes the trusted-browser 2FA timestamp')

check('Fresh Installでは `database/schema.sql` だけを1回実行します' in INSTALL,
      'installation guide documents the single-SQL fresh install contract')
check('Fresh InstallではMigrationを追加実行しません' in INSTALL,
      'installation guide keeps historical migrations out of fresh installs')
quick_start = README.split('## Updating', 1)[0]
check('database/schema.sql' in quick_start and 'Fresh Installでは追加Migrationは不要' in quick_start,
      'README Quick Start documents schema-only fresh installation')
check('schema.sql` はFresh Install用のCurrent完成形' in UPDATE,
      'update guide distinguishes complete fresh schema from upgrade migrations')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
