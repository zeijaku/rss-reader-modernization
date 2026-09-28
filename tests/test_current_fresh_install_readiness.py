#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
TOOL = (ROOT / 'tools/db_current.php').read_text(encoding='utf-8')
INSTALL = (ROOT / 'docs/installation.md').read_text(encoding='utf-8')
README = (ROOT / 'README.md').read_text(encoding='utf-8')
DEPLOY = (ROOT / 'docs/deployment-checklist.md').read_text(encoding='utf-8')
UPDATE = (ROOT / 'docs/update.md').read_text(encoding='utf-8')
BACKUP = (ROOT / 'docs/backup-and-restore.md').read_text(encoding='utf-8')
ROLLBACK = (ROOT / 'docs/rollback.md').read_text(encoding='utf-8')
BUILDER = (ROOT / 'tools/build_release_package.py').read_text(encoding='utf-8')
PACKAGE_VERIFY = (ROOT / 'tools/verify_release_package.py').read_text(encoding='utf-8')
PUBLIC_HTACCESS = (ROOT / 'public/.htaccess').read_text(encoding='utf-8')
ROOT_HTACCESS = (ROOT / '.htaccess').read_text(encoding='utf-8')
WORKFLOW = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8')
RELEASE_WORKFLOW = (ROOT / '.github/workflows/release.yml').read_text(encoding='utf-8')
SMOKE = (ROOT / 'tests/test_current_fresh_install_schema_mariadb.sh').read_text(encoding='utf-8')

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

match = re.search(r'\$requiredTables\s*=\s*\[(.*?)\];', TOOL, re.S)
tool_tables = set(re.findall(r"'([a-z][a-z0-9_]+)'", match.group(1) if match else ''))
schema_tables = set(re.findall(r"SET @t_([a-z0-9_]+) =", SCHEMA))
check(tool_tables == expected_tables, 'Current DB verifier covers exactly the 27 required tables')
check(schema_tables == expected_tables, 'Current DB verifier table inventory matches fresh schema')

check('information_schema.TABLES' in TOOL
      and 'information_schema.COLUMNS' in TOOL
      and 'information_schema.STATISTICS' in TOOL,
      'Current DB verifier checks tables, columns and indexes through information_schema')
check("conn_db('mysql')" in TOOL and "CURRENT SCHEMA: PASS" in TOOL,
      'Current DB verifier uses the configured MySQL connection and has an explicit PASS result')
check(not any(token in TOOL for token in (
    '$pdo->exec(', 'ALTER TABLE ', 'CREATE TABLE ', 'DROP TABLE ',
    'INSERT INTO ', 'DELETE FROM ', 'TRUNCATE TABLE ',
)), 'Current DB verifier contains no schema/data mutation operation')

for doc_name, body in [
    ('README', README),
    ('Installation', INSTALL),
    ('Update', UPDATE),
    ('Deployment checklist', DEPLOY),
    ('Backup / Restore', BACKUP),
    ('Rollback', ROLLBACK),
]:
    check('php tools/db_current.php verify' in body,
          f'{doc_name} uses the Current schema verifier for current deployment verification')

check('ErrorDocument 404 /error.php' in PUBLIC_HTACCESS
      and 'ErrorDocument 500 /error.php' in PUBLIC_HTACCESS,
      'public DocumentRoot htaccess uses /error.php')
check('ErrorDocument 404 /public/error.php' in ROOT_HTACCESS,
      'legacy application-root htaccess keeps /public/error.php compatibility path')

check('mariadb-server' in WORKFLOW
      and 'REQUIRE_MARIADB_SCHEMA_SMOKE' in WORKFLOW,
      'CI provisions MariaDB and requires the fresh schema smoke on one matrix job')
check('mariadb-server' in RELEASE_WORKFLOW
      and "REQUIRE_MARIADB_SCHEMA_SMOKE: '1'" in RELEASE_WORKFLOW,
      'Release verification requires the MariaDB fresh schema smoke')
check('REQUIRE_MARIADB_SCHEMA_SMOKE' in SMOKE
      and 'MariaDB server tools are required' in SMOKE,
      'fresh schema MariaDB smoke fails instead of skipping when CI marks it required')

check("'CONTRIBUTING.md'" in BUILDER,
      'Runtime package includes README-linked CONTRIBUTING documentation')
check("'tools/db_current.php'" in PACKAGE_VERIFY,
      'Runtime package verifier requires the Current schema verifier')
check('runtime Markdown relative links resolve inside the package' in PACKAGE_VERIFY,
      'Runtime package verifier rejects broken relative Markdown links')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
