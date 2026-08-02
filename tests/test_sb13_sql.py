from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
pre = (ROOT / 'database/audit/preflight.sql').read_text(encoding='utf-8')
post = (ROOT / 'database/audit/postflight.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/001_sb13_integrity.sql').read_text(encoding='utf-8')
fixture = (ROOT / 'database/fixtures/sample.sql').read_text(encoding='utf-8')
tool = (ROOT / 'tools/db_sb13.php').read_text(encoding='utf-8')
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
config_example = (ROOT / 'config/local.php.example').read_text(encoding='utf-8')
env_example = (ROOT / 'config/.env.example').read_text(encoding='utf-8')
common_conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
common_db = (ROOT / 'app/common/common_db.php').read_text(encoding='utf-8')
db_integrity = (ROOT / 'app/db_integrity.php').read_text(encoding='utf-8')

checks = []
def check(cond, msg):
    checks.append(bool(cond))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

# Prefix configuration is explicit on both PHP and SQL sides.
check("'DB_TABLE_PREFIX' => 'rss_'" in config_example, 'local config example exposes DB_TABLE_PREFIX')
check('DB_TABLE_PREFIX=rss_' in env_example, 'env example exposes DB_TABLE_PREFIX')
check("app_env('DB_TABLE_PREFIX', 'ig_')" in common_conf, 'runtime fallback preserves existing ig_ database when prefix is omitted')
check('db_validate_table_prefix' in common_conf and '[A-Za-z0-9_]' in common_conf, 'runtime table prefix is strictly validated before SQL interpolation')
check("db_table_name('user_info')" in common_db and "db_table_name('content_stock')" in common_db, 'runtime DB access resolves physical names through centralized table helper')
check(not re.search(r'\big_(?:user_info|user_conf|content|content_stock)\b', common_db), 'runtime DB layer has no hard-coded ig_ physical table names')
check(not re.search(r'\big_(?:user_info|user_conf|content|content_stock)\b', db_integrity), 'SB-13 integrity helper has no hard-coded ig_ physical table names')

for name, text, expected_default in [
    ('schema', schema, 'rss_'),
    ('preflight', pre, 'rss_'),
    ('postflight', post, 'rss_'),
    ('fixture', fixture, 'rss_'),
    ('migration', migration, 'ig_'),
]:
    check(f"SET @table_prefix = '{expected_default}';" in text, f'{name} SQL exposes one editable @table_prefix setting')

for logical in ['user_info', 'user_conf', 'content', 'content_stock']:
    check(f"@table_prefix, '{logical}" in schema or f"@table_prefix, '{logical}')" in schema, f'schema constructs prefixed {logical} table identifier')
check(schema.count('CREATE TABLE ') == 5, 'schema defines five CREATE TABLE statements through dynamic SQL')
check(schema.count('DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci') == 5, 'all target tables are utf8mb4_unicode_ci')
check('idx_user_identity_flag_id' in schema and '`user_email`(64)' in schema, 'schema has non-unique identity lookup index')
check('UNIQUE KEY `uq_user_conf_user_id` (`user_id`)' in schema, 'schema enforces user_conf 1:1')
check('idx_content_owner_location_flag_id' in schema, 'schema has content query index')
check('idx_stock_owner_flag_id' in schema, 'schema has stock query index')
check('FOREIGN KEY' not in schema.replace('-- Foreign keys are intentionally NOT added in SB-13.', ''), 'target schema adds no foreign key')
check('CREATE TABLE `ig_' not in schema and 'CREATE TABLE `rss_' not in schema, 'schema does not hard-code a physical table prefix into CREATE TABLE')

# Audit files remain read-only even though prepared SELECTs are used for dynamic table identifiers.
for name, text in [('preflight', pre), ('postflight', post)]:
    cleaned = '\n'.join(line for line in text.splitlines() if not line.lstrip().startswith('--'))
    check(not re.search(r'\b(?:INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|TRUNCATE|REPLACE)\b', cleaned, re.I), f'{name} SQL is read-only')
    check('PREPARE sb13_stmt' in cleaned and 'EXECUTE sb13_stmt' in cleaned, f'{name} supports prefixed identifiers through prepared SELECTs')

# Migration is schema-only and preservation-oriented.
clean_migration = '\n'.join(line for line in migration.splitlines() if not line.lstrip().startswith('--'))
check(not re.search(r'\b(?:DELETE|UPDATE|INSERT|REPLACE|TRUNCATE|DROP)\b', clean_migration, re.I), 'migration contains no application-row mutation/destructive SQL')
check('FOREIGN KEY' not in clean_migration.upper(), 'migration adds no foreign key')
check(migration.count('CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') == 4, 'migration converts all four tables')
check('MODIFY `content_owner` INT UNSIGNED' in migration and 'MODIFY `stock_owner` INT UNSIGNED' in migration and 'MODIFY `user_id` INT UNSIGNED' in migration, 'relationship columns become unsigned')
check('ADD UNIQUE INDEX `uq_user_conf_user_id`' in migration, 'migration adds user_conf unique index')

# Fixture must be fake and isolated from production data.
check('SAMPLE DATA ONLY' in fixture and 'no production data' in fixture, 'fixture is explicitly marked as fake/sample-only')
check('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' in fixture, 'fixture uses explicit fake identity')
check('https://example.com/' in fixture and 'https://example.org/' in fixture, 'fixture only uses example domains')

# Tool safety controls.
check('--backup-confirmed' in tool, 'apply requires explicit backup confirmation')
check('duplicate_user_conf_user_ids' in tool and 'negative_relationship_ids' in db_integrity, 'tool honors preflight blocking gates')
check('sb13_data_consistency_issues' in tool, 'tool compares pre/post row counts and data distributions')
check('No application rows will be deleted or merged.' in tool, 'tool explicitly states non-destructive behavior')
check("db_table_identifier('user_conf')" in tool and "db_table_identifier('content_stock')" in tool, 'CLI migration uses configured physical table prefix')

# Git policy: general dumps remain ignored but curated DB files are allowed.
check('*.sql' in gitignore, 'generic SQL dumps remain ignored')
for allowed in [
    '!/database/schema.sql',
    '!/database/audit/preflight.sql',
    '!/database/audit/postflight.sql',
    '!/database/migrations/001_sb13_integrity.sql',
    '!/database/fixtures/sample.sql',
]:
    check(allowed in gitignore, f'gitignore explicitly allows curated artifact {allowed}')
check('/var/db-migration/*' in gitignore and '!/var/db-migration/.gitkeep' in gitignore, 'private migration snapshots remain ignored')
check(re.search(r"(?:Secure Baseline SB-(?:1[3-9]|[2-9][0-9])|(?:RSS Engine|Frontend|Release) M\d+-[A-Z] / R[1-9][0-9]*|RSS Reader Modernization 1\.0\.0(?:-RC\d+)?|RSS Reader Modernization V1\.1-[A-Z] / R[1-9][0-9]*)", version) is not None, 'visible version marker is SB-13 or later / M-series / V1.1')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-13 SQL/static checks passed.')
