#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MIGRATION="$ROOT/database/migrations/025_v1_33_calendar_event_exception.sql"

if [ -n "${V133D_MARIADB_ROOT:-}" ]; then
    MARIADBD="$V133D_MARIADB_ROOT/usr/sbin/mariadbd"
    INSTALL_DB="$V133D_MARIADB_ROOT/usr/bin/mariadb-install-db"
    BASEDIR="$V133D_MARIADB_ROOT/usr"
    export LD_LIBRARY_PATH="$V133D_MARIADB_ROOT/usr/lib/x86_64-linux-gnu:$V133D_MARIADB_ROOT/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
else
    MARIADBD=$(command -v mariadbd || true)
    INSTALL_DB=$(command -v mariadb-install-db || true)
    BASEDIR=/usr
fi

if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi

TEST_ROOT=$(mktemp -d /tmp/rss-v133d-mariadb.XXXXXX)
DATA_DIR="$TEST_ROOT/data"
mkdir -p "$DATA_DIR"

cleanup() {
    find "$TEST_ROOT" -depth -mindepth 1 -delete 2>/dev/null || true
    rmdir "$TEST_ROOT" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM

"$INSTALL_DB" --basedir="$BASEDIR" --datadir="$DATA_DIR" \
    --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1

run_bootstrap() {
    "$MARIADBD" --no-defaults --basedir="$BASEDIR" --datadir="$DATA_DIR" \
        --bootstrap --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 2>&1
}

{
    printf '%s\n' \
        'CREATE DATABASE rss_v133d_migration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' \
        'USE rss_v133d_migration;' \
        'CREATE TABLE ig_calendar_event (calendar_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, calendar_event_owner INT UNSIGNED NOT NULL, calendar_event_title VARCHAR(128) NOT NULL, PRIMARY KEY (calendar_event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' \
        "INSERT INTO ig_calendar_event (calendar_event_owner, calendar_event_title) VALUES (42, 'existing');"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'ig_';/" "$MIGRATION"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'ig_';/" "$MIGRATION"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$MIGRATION"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$MIGRATION"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'bad-name';/" "$MIGRATION"
    printf '%s\n' \
        "SET @ok_tables = ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ig_calendar_event_exception', 'qa_calendar_event_exception')) = 2);" \
        "SET @ok_existing = ((SELECT COUNT(*) FROM ig_calendar_event WHERE calendar_event_owner = 42 AND calendar_event_title = 'existing') = 1);" \
        "SET @ok_columns = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event_exception') = 18);" \
        "SET @ok_types = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event_exception' AND ((COLUMN_NAME = 'calendar_event_exception_kind' AND CHARACTER_SET_NAME = 'ascii' AND COLLATION_NAME = 'ascii_bin') OR (COLUMN_NAME = 'calendar_event_exception_revision' AND COLUMN_DEFAULT = '1') OR (COLUMN_NAME = 'calendar_event_exception_flag' AND COLUMN_DEFAULT = '0'))) = 3);" \
        "SET @ok_unique = ((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event_exception' AND INDEX_NAME = 'uq_cal_exception_owner_event_original' AND NON_UNIQUE = 0) = 3);" \
        "SET @ok_indexes = ((SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event_exception') = 5);" \
        "SET @ok_engine = ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event_exception' AND ENGINE = 'InnoDB' AND TABLE_COLLATION = 'utf8mb4_unicode_ci') = 1);" \
        "SET @ok_invalid_prefix = ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bad-namecalendar_event_exception') = 0);" \
        'SET @all_ok = (@ok_tables AND @ok_existing AND @ok_columns AND @ok_types AND @ok_unique AND @ok_indexes AND @ok_engine AND @ok_invalid_prefix);' \
        "SET @assert_sql = IF(@all_ok, 'SELECT 1', 'SELECT * FROM v133d_migration_assertion_failed');" \
        'PREPARE v133d_assert_stmt FROM @assert_sql;' \
        'EXECUTE v133d_assert_stmt;' \
        'DEALLOCATE PREPARE v133d_assert_stmt;'
} | run_bootstrap >/dev/null

echo 'PASS: MariaDB 10.11 accepted 025 twice for default and alternate prefixes'
echo 'PASS: 025 preserved the pre-existing Calendar row'
echo 'PASS: exception columns, defaults, charset, collation, unique key and indexes match the contract'
echo 'PASS: invalid table prefix caused no table creation'
echo 'RESULT: PASS 4 / FAIL 0 / SKIP 0'
