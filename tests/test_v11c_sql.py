from pathlib import Path
import re
import sqlite3

ROOT = Path(__file__).resolve().parents[1]
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/002_v1_1_feed_item_state.sql').read_text(encoding='utf-8')
preflight = (ROOT / 'database/audit/v1_1_c_preflight.sql').read_text(encoding='utf-8')
postflight = (ROOT / 'database/audit/v1_1_c_postflight.sql').read_text(encoding='utf-8')
tool = (ROOT / 'tools/db_v11c.php').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(condition)
    print(('PASS' if condition else 'FAIL') + ': ' + message)

for text, label in [(schema, 'new-install schema'), (migration, 'existing-DB migration')]:
    check("CONCAT('`', @table_prefix, 'feed_item_state`')" in text, f'{label} uses dynamic table prefix')
    for column in ['state_id', 'owner_id', 'content_id', 'item_identity', 'first_seen_at', 'last_seen_at', 'seen_at', 'state_flag']:
        check(f'`{column}`' in text, f'{label} contains {column}')
    check('CHAR(71) CHARACTER SET ascii COLLATE ascii_bin' in text, f'{label} stores canonical identities byte-exactly')
    check('uq_feed_item_state_owner_content_identity' in text, f'{label} prevents duplicate owner/content/identity rows')
    check('idx_feed_item_state_owner_content_seen' in text, f'{label} supports owner/content unread lookup')
    check('idx_feed_item_state_last_seen' in text, f'{label} supports retention cleanup')
    check('FOREIGN KEY' not in text, f'{label} does not add foreign keys before delete/orphan policy is finalized')

check('CREATE TABLE IF NOT EXISTS' in migration, 'migration is non-destructive on a second run')
check('ALTER TABLE' not in migration and 'DROP TABLE' not in migration and 'DELETE ' not in migration, 'migration does not alter or delete existing application data')
check("SET @table_prefix = 'ig_';" in migration, 'existing-DB migration defaults to current Legacy ig_ prefix with explicit edit point')
check('information_schema.TABLES' in preflight and 'information_schema.STATISTICS' in preflight, 'preflight is read-only and checks tables/indexes')
check('information_schema.COLUMNS' in postflight and 'information_schema.STATISTICS' in postflight, 'postflight verifies columns and indexes')
check("db_table_identifier('feed_item_state')" in tool and '--backup-confirmed' in tool, 'CLI migration helper uses runtime prefix and requires backup confirmation')
check("v11c_schema_issues" in tool and "SHOW COLUMNS" in tool and "SHOW INDEX" in tool, 'CLI helper verifies an existing table instead of silently accepting mismatches')

# Portable logical constraint check. The production DDL is MySQL/MariaDB; this
# SQLite shape verifies unique owner/content/identity and nullable seen_at behavior.
conn = sqlite3.connect(':memory:')
conn.execute('''
CREATE TABLE ig_feed_item_state (
  state_id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_id INTEGER NOT NULL,
  content_id INTEGER NOT NULL,
  item_identity TEXT NOT NULL,
  first_seen_at TEXT NOT NULL,
  last_seen_at TEXT NOT NULL,
  seen_at TEXT NULL,
  state_flag INTEGER NOT NULL DEFAULT 0,
  UNIQUE(owner_id, content_id, item_identity)
)
''')
identity = 'm1i:v1:' + ('a' * 64)
conn.execute('INSERT INTO ig_feed_item_state(owner_id,content_id,item_identity,first_seen_at,last_seen_at,seen_at) VALUES(?,?,?,?,?,?)', (1, 10, identity, '2026-08-02 00:00:00', '2026-08-02 00:00:00', None))
check(conn.execute('SELECT seen_at FROM ig_feed_item_state').fetchone()[0] is None, 'unread state allows NULL seen_at')
try:
    conn.execute('INSERT INTO ig_feed_item_state(owner_id,content_id,item_identity,first_seen_at,last_seen_at) VALUES(?,?,?,?,?)', (1, 10, identity, 'x', 'x'))
    duplicate_blocked = False
except sqlite3.IntegrityError:
    duplicate_blocked = True
check(duplicate_blocked, 'unique owner/content/identity constraint blocks duplicate inserts')
conn.execute('INSERT INTO ig_feed_item_state(owner_id,content_id,item_identity,first_seen_at,last_seen_at) VALUES(?,?,?,?,?)', (2, 10, identity, 'x', 'x'))
check(conn.execute('SELECT COUNT(*) FROM ig_feed_item_state').fetchone()[0] == 2, 'same identity remains independently scoped per owner')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.1-C SQL checks failed')
print(f'All {len(checks)} V1.1-C SQL/migration checks passed.')
