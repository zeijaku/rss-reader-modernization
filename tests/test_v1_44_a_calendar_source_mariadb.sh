#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MIGRATION="$ROOT/database/migrations/033_v1_44_calendar_source.sql"

MARIADBD=$(command -v mariadbd || true)
INSTALL_DB=$(command -v mariadb-install-db || true)
if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi

TEST_ROOT=$(mktemp -d /tmp/rss-v144a-mariadb.XXXXXX)
DATA_DIR="$TEST_ROOT/data"
mkdir -p "$DATA_DIR"

cleanup() {
    find "$TEST_ROOT" -depth -mindepth 1 -delete 2>/dev/null || true
    rmdir "$TEST_ROOT" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM

"$INSTALL_DB" --basedir=/usr --datadir="$DATA_DIR"     --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1

run_bootstrap() {
    "$MARIADBD" --no-defaults --basedir=/usr --datadir="$DATA_DIR"         --bootstrap --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 2>&1
}

{
    printf '%s\n'         'CREATE DATABASE rss_v144a_migration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'         'USE rss_v144a_migration;'         'CREATE TABLE ig_calendar_event (calendar_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, calendar_event_date DATETIME NOT NULL, calendar_event_updated_at DATETIME NOT NULL, calendar_event_flag TINYINT UNSIGNED NOT NULL DEFAULT 0, calendar_event_owner INT UNSIGNED NOT NULL, calendar_event_title VARCHAR(256) NOT NULL, calendar_event_start_date DATE NOT NULL, calendar_event_end_date DATE NOT NULL, calendar_event_note TEXT NOT NULL, PRIMARY KEY (calendar_event_id), KEY idx_calendar_event_owner_range (calendar_event_owner, calendar_event_flag, calendar_event_start_date, calendar_event_end_date, calendar_event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'         "INSERT INTO ig_calendar_event (calendar_event_date, calendar_event_updated_at, calendar_event_flag, calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date, calendar_event_note) VALUES (NOW(), NOW(), 0, 42, 'existing-1', '2026-10-04', '2026-10-04', ''), (NOW(), NOW(), 0, 42, 'existing-2', '2026-10-05', '2026-10-05', ''), (NOW(), NOW(), 0, 77, 'other-owner', '2026-10-04', '2026-10-04', '');"
    cat "$MIGRATION"
    cat "$MIGRATION"
    printf '%s\n'         "SET @ok_table = ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_source') = 1);"         "SET @ok_column = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event' AND COLUMN_NAME = 'calendar_event_source_id') = 1);"         "SET @ok_index = ((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event' AND INDEX_NAME = 'idx_calendar_event_source') = 4);"         "SET @ok_defaults = ((SELECT COUNT(*) FROM ig_calendar_source WHERE calendar_source_flag = 0 AND calendar_source_default = 1) = 2);"         "SET @ok_owner42_one_default = ((SELECT COUNT(*) FROM ig_calendar_source WHERE calendar_source_owner = 42 AND calendar_source_flag = 0 AND calendar_source_default = 1) = 1);"         "SET @ok_owner77_one_default = ((SELECT COUNT(*) FROM ig_calendar_source WHERE calendar_source_owner = 77 AND calendar_source_flag = 0 AND calendar_source_default = 1) = 1);"         "SET @ok_backfill = ((SELECT COUNT(*) FROM ig_calendar_event e INNER JOIN ig_calendar_source s ON s.calendar_source_id = e.calendar_event_source_id AND s.calendar_source_owner = e.calendar_event_owner AND s.calendar_source_default = 1 WHERE e.calendar_event_source_id IS NOT NULL) = 3);"         'SET @all_ok = (@ok_table AND @ok_column AND @ok_index AND @ok_defaults AND @ok_owner42_one_default AND @ok_owner77_one_default AND @ok_backfill);'         "SET @assert_sql = IF(@all_ok, 'SELECT 1', 'SELECT * FROM v144a_migration_assertion_failed');"         'PREPARE v144a_assert_stmt FROM @assert_sql;'         'EXECUTE v144a_assert_stmt;'         'DEALLOCATE PREPARE v144a_assert_stmt;'
} | run_bootstrap >/dev/null

echo 'PASS: MariaDB accepted V1.44-A migration twice'
echo 'PASS: Calendar source table, event source column and index exist'
echo 'PASS: exactly one default Calendar was created for each existing owner'
echo 'PASS: every existing Calendar event was assigned to its owner default Calendar'
echo 'RESULT: PASS 4 / FAIL 0 / SKIP 0'
