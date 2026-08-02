from pathlib import Path
import re
import sys

ROOT=Path(__file__).resolve().parents[1]
schema=(ROOT/'database/schema.sql').read_text(encoding='utf-8')
migration=(ROOT/'database/migrations/004_v1_1_memo.sql').read_text(encoding='utf-8')
pre=(ROOT/'database/audit/v1_1_g_preflight.sql').read_text(encoding='utf-8')
post=(ROOT/'database/audit/v1_1_g_postflight.sql').read_text(encoding='utf-8')
tool=(ROOT/'tools/db_v11g.php').read_text(encoding='utf-8')
checks=[]
def check(cond,msg): checks.append(bool(cond)); print(('PASS' if cond else 'FAIL')+': '+msg)

check("SET @t_memo = CONCAT('`', @table_prefix, 'memo`');" in schema, 'new-install schema uses configurable Memo prefix')
check("'CREATE TABLE ', @t_memo" in schema, 'new-install schema creates Memo table')
for col in ['memo_id','memo_date','memo_updated_at','memo_flag','memo_owner','memo_title','memo_body']:
    check(f'`{col}`' in schema and f'`{col}`' in migration, f'Memo column is present in schema and migration: {col}')
check('PRIMARY KEY (`memo_id`)' in schema and 'PRIMARY KEY (`memo_id`)' in migration, 'Memo primary key is consistent')
check('idx_memo_owner_flag_id' in schema and 'idx_memo_owner_flag_id' in migration, 'Memo owner/flag index is consistent')
check('ENGINE=InnoDB' in migration and 'utf8mb4_unicode_ci' in migration, 'Memo migration uses current engine and charset')
check('CREATE TABLE IF NOT EXISTS' in migration, 'Memo migration can be re-run safely')
check('INSERT INTO' not in migration and 'UPDATE ' not in migration, 'Memo migration does not change existing application rows')
check("SET @table_prefix = 'ig_';" in migration, 'migration documents the runtime prefix setting')
check('SELECT DATABASE()' in pre, 'preflight shows selected database')
check('information_schema.TABLES' in pre, 'preflight checks required tables read-only')
check('@database_is_application' in post and "NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')" in post, 'postflight refuses system database selection')
check('active_memo_count' in post and 'active_memo_widget_count' in post, 'postflight reports Memo and Widget counts')
check('w.widget_id IS NULL' in post, 'postflight reports active Memo rows without Widgets')
check('CREATE TABLE IF NOT EXISTS' in tool and "db_table_identifier('memo')" in tool, 'CLI apply creates the prefixed Memo table')
check('verify' in tool and '--backup-confirmed' in tool, 'CLI separates read-only verify from guarded apply')
check('v11g_schema_issues' in tool and 'v11g_data_issues' in tool, 'CLI checks schema and data consistency')
check('Active Memo without Widget' in tool and 'Memo Widget without active Memo' in tool, 'CLI checks both orphan directions')
check('FOREIGN KEY' not in migration.upper(), 'V1.1-G does not introduce a foreign key into legacy data')

if not all(checks): sys.exit(1)
print(f'All {len(checks)} V1.1-G SQL checks passed.')
