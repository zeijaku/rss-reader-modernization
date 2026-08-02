from pathlib import Path
import sqlite3

ROOT = Path(__file__).resolve().parents[1]
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/003_v1_1_dashboard_widget.sql').read_text(encoding='utf-8')
preflight = (ROOT / 'database/audit/v1_1_d_preflight.sql').read_text(encoding='utf-8')
postflight = (ROOT / 'database/audit/v1_1_d_postflight.sql').read_text(encoding='utf-8')
tool = (ROOT / 'tools/db_v11d.php').read_text(encoding='utf-8')
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


columns = [
    'widget_id', 'widget_owner', 'widget_location', 'widget_type',
    'widget_reference_id', 'widget_sort_order', 'widget_width',
    'widget_style', 'widget_config', 'widget_flag',
    'widget_created_at', 'widget_updated_at',
]
for text, label in [(schema, 'new-install schema'), (migration, 'existing-DB migration')]:
    check("CONCAT('`', @table_prefix, 'dashboard_widget`')" in text, f'{label} uses dynamic table prefix')
    for column in columns:
        check(f'`{column}`' in text, f'{label} contains {column}')
    check('uq_dashboard_widget_owner_type_reference' in text, f'{label} prevents duplicate referenced Widgets per owner/type')
    check('idx_dashboard_widget_owner_location_order' in text, f'{label} supports tab/order lookup')
    check('idx_dashboard_widget_owner_type_flag' in text, f'{label} supports owner/type lookup')
    check('FOREIGN KEY' not in text, f'{label} adds no foreign key before legacy delete policy is finalized')

check('CREATE TABLE IF NOT EXISTS' in migration, 'migration table creation is safe to repeat')
check("FROM ', @t_content, ' c WHERE c.content_flag = 0" in migration, 'migration backfills only active Feed records')
check("'feed', c.content_id, c.content_id" in migration or "''feed'', c.content_id, c.content_id" in migration, 'initial Feed Widget sort order preserves content_id order')
check('ON DUPLICATE KEY UPDATE' in migration, 'migration backfill is repeatable')
update_part = migration.split('ON DUPLICATE KEY UPDATE', 1)[1]
check('widget_sort_order = VALUES' not in update_part, 'migration re-run does not reset a later Drag and Drop order')
check('DROP TABLE' not in migration and 'DELETE FROM' not in migration and 'ALTER TABLE' not in migration, 'migration is additive and non-destructive')
check("SET @table_prefix = 'ig_';" in migration, 'existing-DB migration has an explicit current-prefix edit point')
check('information_schema.TABLES' in preflight and 'information_schema.STATISTICS' in preflight, 'preflight is read-only and inspects existing structures')
check('missing_feed_widgets' in postflight and 'information_schema.COLUMNS' in postflight, 'postflight verifies schema and Feed backfill')
check("db_table_identifier('dashboard_widget')" in tool and '--backup-confirmed' in tool, 'CLI helper uses runtime prefix and requires backup confirmation')
check('SHOW COLUMNS' in tool and 'SHOW INDEX' in tool and 'Active Feed without Widget' in tool, 'CLI helper verifies both schema and migration data')

conn = sqlite3.connect(':memory:')
conn.executescript('''
CREATE TABLE ig_content (
  content_id INTEGER PRIMARY KEY,
  content_date TEXT NOT NULL,
  content_flag INTEGER NOT NULL DEFAULT 0,
  content_owner INTEGER NOT NULL,
  content_location INTEGER NOT NULL,
  content_style TEXT NOT NULL,
  content_value TEXT NOT NULL
);
CREATE TABLE ig_dashboard_widget (
  widget_id INTEGER PRIMARY KEY AUTOINCREMENT,
  widget_owner INTEGER NOT NULL,
  widget_location INTEGER NOT NULL,
  widget_type TEXT NOT NULL,
  widget_reference_id INTEGER NULL,
  widget_sort_order INTEGER NOT NULL,
  widget_width INTEGER NOT NULL DEFAULT 1,
  widget_style TEXT NOT NULL,
  widget_config TEXT NULL,
  widget_flag INTEGER NOT NULL DEFAULT 0,
  widget_created_at TEXT NOT NULL,
  widget_updated_at TEXT NOT NULL,
  UNIQUE(widget_owner, widget_type, widget_reference_id)
);
''')
rows = [
    (10, '2026-08-02 10:00:00', 0, 1, 0, 'success', 'https://a.example/feed'),
    (20, '2026-08-02 11:00:00', 0, 1, 0, 'info', 'https://b.example/feed'),
    (30, '2026-08-02 12:00:00', 1, 1, 1, 'danger', 'https://deleted.example/feed'),
    (40, '2026-08-02 13:00:00', 0, 2, 3, 'warning', 'https://other.example/feed'),
]
conn.executemany('INSERT INTO ig_content VALUES(?,?,?,?,?,?,?)', rows)
backfill = '''
INSERT INTO ig_dashboard_widget(
 widget_owner,widget_location,widget_type,widget_reference_id,widget_sort_order,
 widget_width,widget_style,widget_config,widget_flag,widget_created_at,widget_updated_at
)
SELECT content_owner,content_location,'feed',content_id,content_id,1,content_style,NULL,0,content_date,content_date
FROM ig_content WHERE content_flag=0
ON CONFLICT(widget_owner,widget_type,widget_reference_id) DO UPDATE SET
 widget_location=excluded.widget_location,
 widget_style=excluded.widget_style,
 widget_flag=0,
 widget_updated_at=excluded.widget_updated_at
'''
conn.execute(backfill)
check(conn.execute('SELECT COUNT(*) FROM ig_dashboard_widget').fetchone()[0] == 3, 'logical backfill creates one Widget for each active Feed only')
check(conn.execute('SELECT widget_reference_id FROM ig_dashboard_widget WHERE widget_owner=1 ORDER BY widget_sort_order').fetchall() == [(10,), (20,)], 'logical backfill preserves existing content_id display order')
conn.execute('UPDATE ig_dashboard_widget SET widget_sort_order=1 WHERE widget_reference_id=20')
conn.execute('UPDATE ig_dashboard_widget SET widget_sort_order=2 WHERE widget_reference_id=10')
conn.execute("UPDATE ig_content SET content_style='dark' WHERE content_id=10")
conn.execute(backfill)
check(conn.execute('SELECT widget_sort_order FROM ig_dashboard_widget WHERE widget_reference_id=10').fetchone()[0] == 2, 'backfill re-run keeps a user-defined sort order')
check(conn.execute('SELECT widget_style FROM ig_dashboard_widget WHERE widget_reference_id=10').fetchone()[0] == 'dark', 'backfill re-run repairs mirrored Feed style')
try:
    conn.execute("INSERT INTO ig_dashboard_widget(widget_owner,widget_location,widget_type,widget_reference_id,widget_sort_order,widget_width,widget_style,widget_created_at,widget_updated_at) VALUES(1,0,'feed',10,99,1,'success','x','x')")
    duplicate_blocked = False
except sqlite3.IntegrityError:
    duplicate_blocked = True
check(duplicate_blocked, 'unique owner/type/reference constraint blocks duplicate Feed Widgets')
conn.execute("INSERT INTO ig_dashboard_widget(widget_owner,widget_location,widget_type,widget_reference_id,widget_sort_order,widget_width,widget_style,widget_created_at,widget_updated_at) VALUES(1,0,'clock',NULL,100,1,'success','x','x')")
conn.execute("INSERT INTO ig_dashboard_widget(widget_owner,widget_location,widget_type,widget_reference_id,widget_sort_order,widget_width,widget_style,widget_created_at,widget_updated_at) VALUES(1,0,'clock',NULL,101,1,'success','x','x')")
check(conn.execute("SELECT COUNT(*) FROM ig_dashboard_widget WHERE widget_type='clock'").fetchone()[0] == 2, 'nullable reference allows multiple standalone future Widgets')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.1-D SQL checks failed')
print(f'All {len(checks)} V1.1-D SQL/migration checks passed.')
