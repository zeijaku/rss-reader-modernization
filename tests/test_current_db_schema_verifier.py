#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
TOOL = (ROOT / 'tools/db_current.php').read_text(encoding='utf-8')
VERIFY = (ROOT / 'tools/verify_release_package.py').read_text(encoding='utf-8')
INSTALL = (ROOT / 'docs/installation.md').read_text(encoding='utf-8')
README = (ROOT / 'README.md').read_text(encoding='utf-8')

checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

expected_tables = {
    'user_info', 'user_conf', 'content', 'content_stock', 'feed_item_state',
    'memo', 'task', 'calendar_source', 'calendar_event', 'calendar_event_exception',
    'dashboard_widget', 'notification', 'remember_token', 'mail_account',
    'link_item', 'stock_tag', 'stock_tag_map', 'feed_keyword', 'feed_metadata',
    'feed_health', 'rss_rule', 'rss_rule_condition', 'user_file',
    'remote_connection', 'auth_totp', 'auth_recovery_code', 'auth_session',
    'auth_audit_log',
}
listed = set(re.findall(r"^\s*'([a-z][a-z0-9_]+)',\s*$", TOOL, re.M))
check(expected_tables <= listed, 'Current DB verifier carries all 28 required logical tables')
check("information_schema.TABLES" in TOOL and "information_schema.COLUMNS" in TOOL
      and "information_schema.STATISTICS" in TOOL,
      'Current DB verifier reads table, column and index metadata')
check("CURRENT SCHEMA: PASS" in TOOL and "CURRENT SCHEMA: FAIL" in TOOL,
      'Current DB verifier exposes stable PASS/FAIL status')
check("conn_db('mysql')" in TOOL and "DB_TABLE_PREFIX" in TOOL,
      'Current DB verifier uses configured MySQL connection and table prefix')

dangerous = re.findall(r"\b(?:CREATE|ALTER|DROP|TRUNCATE|INSERT|UPDATE|DELETE)\b", TOOL, re.I)
check(not dangerous, 'Current DB verifier contains no DDL or data mutation statements')
check("'tools/db_current.php'" in VERIFY,
      'Runtime package verifier requires the Current DB verifier')
check('php tools/db_current.php verify' in INSTALL,
      'Installation guide uses Current DB verifier after Fresh Install')
check('php tools/db_current.php verify' in README,
      'README Quick Start references Current DB verifier')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
